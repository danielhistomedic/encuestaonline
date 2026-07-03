<?php
/**
 * Backend seguro para registrar las respuestas de la encuesta en la base de datos MySQL local.
 */

// Declarar constante para permitir la inclusión de archivos sensibles
define('SECURE_ACCESS', true);

// Cargar el archivo de configuración externa
$config = require 'config.php';

// Cabeceras para respuesta JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Restricción dinámica de CORS basada en la configuración
$allowedOrigins = $config['allowed_origins'] ?? [];
$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($httpOrigin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $httpOrigin");
}

// Manejar peticiones preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido. Utilice POST."
    ]);
    exit();
}

// Obtener parámetros desde la configuración
$dbHost = $config['db']['host'] ?? '127.0.0.1';
$dbPort = $config['db']['port'] ?? '1865';
$dbUser = $config['db']['user'] ?? '';
$dbPass = $config['db']['pass'] ?? '';
$dbName = $config['db']['name'] ?? 'proyecto_encuesta';
$tableName = $config['db']['table'] ?? 'url_bd_airtable';
$webhookUrl = $config['n8n']['webhook_url'] ?? '';

try {
    // 1. Conectar al servidor MySQL inicialmente sin especificar la base de datos
    // Usamos charset=utf8 porque la versión local 5.1.67 no soporta utf8mb4.
    $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5 // Timeout de conexión de 5 segundos
    ]);

    // 2. Crear la base de datos (schema) si no existe con codificación UTF-8 estándar
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8 COLLATE utf8_general_ci;");

    // 3. Seleccionar la base de datos recién creada o existente
    $pdo->exec("USE `$dbName`;");

    // 4. Crear la tabla si no existe (dejamos que MySQL elija el motor por defecto ya que InnoDB está desactivado)
    $createTableSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `IDEstudiante` VARCHAR(45) NOT NULL,
        `NivelSatisfaccion` INT UNSIGNED NOT NULL,
        `ClaridadContenido` INT UNSIGNED NOT NULL,
        `AplicabilidadPractica` INT UNSIGNED NOT NULL,
        `ComentariosAdicionales` VARCHAR(500) NOT NULL
    );";
    
    $pdo->exec($createTableSQL);

    // 5. Obtener los datos JSON de la petición
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // Validar que se hayan recibido datos válidos
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Datos inválidos o vacíos."
        ]);
        exit();
    }

    // Validar y tipar campos requeridos
    $idEstudiante = isset($data['id_estudiante']) ? trim((string)$data['id_estudiante']) : '';
    $nivelSatisfaccion = isset($data['nivel_satisfaccion']) ? intval($data['nivel_satisfaccion']) : null;
    $claridadContenido = isset($data['claridad_contenido']) ? intval($data['claridad_contenido']) : null;
    $aplicabilidadPractica = isset($data['aplicabilidad_practica']) ? intval($data['aplicabilidad_practica']) : null;
    $comentariosAdicionales = isset($data['comentarios_adicionales']) ? trim((string)$data['comentarios_adicionales']) : '';

    // Validar campos obligatorios
    if (empty($idEstudiante) || $nivelSatisfaccion === null || $claridadContenido === null || $aplicabilidadPractica === null) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios en el formulario."
        ]);
        exit();
    }

    // Validar rangos permitidos (1-5) para evitar inserción de puntuaciones arbitrarias
    if ($nivelSatisfaccion < 1 || $nivelSatisfaccion > 5 ||
        $claridadContenido < 1 || $claridadContenido > 5 ||
        $aplicabilidadPractica < 1 || $aplicabilidadPractica > 5) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Valores de puntuación inválidos."
        ]);
        exit();
    }

    // Truncar textos para evitar errores de almacenamiento y desbordamientos en la BD
    // (Matrícula límite VARCHAR(45), comentarios límite VARCHAR(500))
    $idEstudiante = mb_substr($idEstudiante, 0, 45, 'UTF-8');
    $comentariosAdicionales = mb_substr($comentariosAdicionales, 0, 500, 'UTF-8');

    // 6. Insertar los datos en la tabla de forma segura mediante parámetros prevenidos
    $insertSQL = "INSERT INTO `$tableName` (
        IDEstudiante, 
        NivelSatisfaccion, 
        ClaridadContenido, 
        AplicabilidadPractica, 
        ComentariosAdicionales
    ) VALUES (
        :id_estudiante, 
        :nivel_satisfaccion, 
        :claridad_contenido, 
        :aplicabilidad_practica, 
        :comentarios_adicionales
    );";

    $stmt = $pdo->prepare($insertSQL);
    $stmt->execute([
        ':id_estudiante' => $idEstudiante,
        ':nivel_satisfaccion' => $nivelSatisfaccion,
        ':claridad_contenido' => $claridadContenido,
        ':aplicabilidad_practica' => $aplicabilidadPractica,
        ':comentarios_adicionales' => $comentariosAdicionales
    ]);

    // 7. Enviar datos al webhook de n8n para envío de email
    if (!empty($webhookUrl)) {
        $webhookData = [
            'id_estudiante' => $idEstudiante,
            'nivel_satisfaccion' => $nivelSatisfaccion,
            'claridad_contenido' => $claridadContenido,
            'aplicabilidad_practica' => $aplicabilidadPractica,
            'comentarios_adicionales' => $comentariosAdicionales,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        try {
            $jsonPayload = json_encode($webhookData);
            if (function_exists('curl_version')) {
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload)
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout de 3 segundos
                curl_exec($ch);
                curl_close($ch);
            } else {
                // Fallback usando file_get_contents
                $options = [
                    'http' => [
                        'header'  => "Content-Type: application/json\r\n",
                        'method'  => 'POST',
                        'content' => $jsonPayload,
                        'timeout' => 3
                    ]
                ];
                $context = stream_context_create($options);
                @file_get_contents($webhookUrl, false, $context);
            }
        } catch (Exception $webEx) {
            // Registrar fallo del webhook silenciosamente en logs
            error_log("Fallo al contactar Webhook n8n: " . $webEx->getMessage());
        }
    }

    // Responder con éxito
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Respuestas guardadas correctamente en la base de datos."
    ]);

} catch (PDOException $e) {
    // Registrar el error de manera interna en los logs del servidor
    error_log("Error de Base de Datos en Encuesta: " . $e->getMessage());
    
    // Responder con mensaje seguro genérico para no exponer configuraciones internas
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Ocurrió un error interno en el servidor. Por favor, inténtelo de nuevo más tarde."
    ]);
}
