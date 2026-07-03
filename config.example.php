<?php
// Evitar acceso directo
if (!defined('SECURE_ACCESS')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Acceso denegado.");
}

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'user' => 'tu_usuario',
        'pass' => 'tu_contraseña',
        'name' => 'proyecto_encuesta',
        'table' => 'url_bd_airtable',
    ],
    'n8n' => [
        'webhook_url' => 'https://tu-dominio.n8n.cloud/webhook/tu-webhook-id',
    ],
    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
    ]
];
