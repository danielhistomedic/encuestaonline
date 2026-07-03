/* ===================================================
   ENCUESTA ANTIGRAVITY — LÓGICA JAVASCRIPT
   =================================================== */

(function () {
  'use strict';

  // ── Elementos del DOM ─────────────────────────────
  const form          = document.getElementById('surveyForm');
  const successScreen = document.getElementById('successScreen');
  const resetBtn      = document.getElementById('resetBtn');
  const progressFill  = document.getElementById('progressFill');
  const progressPct   = document.getElementById('progressPercent');
  const charCount     = document.getElementById('charCount');
  const textarea      = document.getElementById('comentarios_adicionales');

  // Estado de respuestas escalares
  const scaleFields = ['nivel_satisfaccion', 'claridad_contenido', 'aplicabilidad_practica'];
  const state = {
    nivel_satisfaccion:      null,
    claridad_contenido:      null,
    aplicabilidad_practica:  null,
  };

  // ── Progreso ───────────────────────────────────────
  function calcProgress() {
    let filled = 0;
    const idVal = document.getElementById('id_estudiante').value.trim();
    if (idVal) filled++;
    scaleFields.forEach(f => { if (state[f] !== null) filled++; });
    // textarea es opcional, no cuenta para el progreso obligatorio
    const total  = 4; // 1 texto + 3 escalas
    const pct    = Math.round((filled / total) * 100);
    progressFill.style.width  = pct + '%';
    progressPct.textContent   = pct + '%';
  }

  // ── Botones de escala ──────────────────────────────
  document.querySelectorAll('.scale-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const field = btn.dataset.field;
      const value = parseInt(btn.dataset.value, 10);

      // Deseleccionar todos los botones del mismo campo
      document.querySelectorAll(`.scale-btn[data-field="${field}"]`).forEach(b => {
        b.classList.remove('selected');
        b.setAttribute('aria-pressed', 'false');
      });

      // Seleccionar el pulsado
      btn.classList.add('selected');
      btn.setAttribute('aria-pressed', 'true');

      // Guardar estado
      state[field] = value;
      document.getElementById(field).value = value;

      // Limpiar error
      clearError(field);

      // Actualizar progreso
      calcProgress();
    });

    // Soporte de teclado
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('role', 'radio');
  });

  // ── ID estudiante ──────────────────────────────────
  document.getElementById('id_estudiante').addEventListener('input', () => {
    calcProgress();
    clearError('id_estudiante');
  });

  // ── Contador de caracteres en textarea ─────────────
  textarea.addEventListener('input', () => {
    charCount.textContent = textarea.value.length;
  });

  // ── Validación ─────────────────────────────────────
  function showError(field, cardId) {
    const errEl  = document.getElementById('error-' + field);
    const cardEl = document.getElementById('card-' + cardId);
    if (errEl)  errEl.classList.add('visible');
    if (cardEl) cardEl.classList.add('has-error');
  }

  function clearError(field) {
    // Determinar qué card corresponde
    const cardMap = {
      'id_estudiante':       '1',
      'nivel_satisfaccion':  '2',
      'claridad_contenido':  '3',
      'aplicabilidad_practica': '4',
    };
    const errEl  = document.getElementById('error-' + field);
    const cardId = cardMap[field];
    const cardEl = cardId ? document.getElementById('card-' + cardId) : null;
    if (errEl)  errEl.classList.remove('visible');
    if (cardEl) cardEl.classList.remove('has-error');
  }

  function validateForm() {
    let valid = true;

    // 1. ID estudiante
    const idVal = document.getElementById('id_estudiante').value.trim();
    if (!idVal) {
      showError('id_estudiante', '1');
      valid = false;
    } else {
      clearError('id_estudiante');
    }

    // 2-4. Escalas obligatorias
    const scaleMap = {
      'nivel_satisfaccion':     '2',
      'claridad_contenido':     '3',
      'aplicabilidad_practica': '4',
    };
    Object.entries(scaleMap).forEach(([field, cardNum]) => {
      if (state[field] === null) {
        showError(field, cardNum);
        valid = false;
      } else {
        clearError(field);
      }
    });

    return valid;
  }

  // ── Envío ──────────────────────────────────────────
  form.addEventListener('submit', (e) => {
    e.preventDefault();

    if (!validateForm()) {
      // Scroll al primer error
      const firstError = form.querySelector('.has-error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }

    // Recopilar datos
    const formData = {
      id_estudiante:          document.getElementById('id_estudiante').value.trim(),
      nivel_satisfaccion:     state['nivel_satisfaccion'],
      claridad_contenido:     state['claridad_contenido'],
      aplicabilidad_practica: state['aplicabilidad_practica'],
      comentarios_adicionales: textarea.value.trim(),
      timestamp:              new Date().toISOString(),
    };

    // Log de los datos (en producción se enviarían al servidor)
    console.log('📋 Datos de encuesta:', formData);

    // Animación de envío
    const submitBtn  = document.getElementById('submitBtn');
    const submitText = submitBtn.querySelector('.submit-text');
    submitBtn.disabled = true;
    submitText.textContent = 'Enviando…';

    // Enviar datos al backend PHP
    fetch('submit.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    })
    .then(response => {
      if (!response.ok) {
        return response.json().then(err => { throw new Error(err.message || 'Error en el servidor'); });
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showSuccess();
      } else {
        throw new Error(data.message || 'Error desconocido');
      }
    })
    .catch(error => {
      console.error('Error al enviar la encuesta:', error);
      alert('Hubo un inconveniente al enviar la encuesta: ' + error.message);
      submitBtn.disabled = false;
      submitText.textContent = 'Enviar Encuesta';
    });
  });

  // ── Pantalla de éxito ──────────────────────────────
  function showSuccess() {
    form.style.opacity    = '0';
    form.style.transform  = 'scale(.97)';
    form.style.transition = 'all .4s ease';

    setTimeout(() => {
      form.hidden            = true;
      successScreen.hidden   = false;
      successScreen.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 400);
  }

  // ── Reiniciar formulario ───────────────────────────
  resetBtn.addEventListener('click', () => {
    // Limpiar campos de texto
    document.getElementById('id_estudiante').value  = '';
    textarea.value = '';
    charCount.textContent = '0';

    // Limpiar estado de escalas
    scaleFields.forEach(f => { state[f] = null; });
    document.querySelectorAll('.scale-btn').forEach(b => {
      b.classList.remove('selected');
      b.setAttribute('aria-pressed', 'false');
    });
    scaleFields.forEach(f => {
      document.getElementById(f).value = '';
    });

    // Limpiar errores
    document.querySelectorAll('.error-msg').forEach(e => e.classList.remove('visible'));
    document.querySelectorAll('.card').forEach(c => c.classList.remove('has-error'));

    // Reiniciar progreso
    calcProgress();

    // Restaurar botón de envío
    const submitBtn  = document.getElementById('submitBtn');
    submitBtn.disabled = false;
    submitBtn.querySelector('.submit-text').textContent = 'Enviar Encuesta';

    // Mostrar formulario
    successScreen.hidden = true;
    form.hidden          = false;
    form.style.opacity   = '1';
    form.style.transform = 'none';

    // Scroll al inicio
    document.querySelector('.hero').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // ── Inicializar progreso ───────────────────────────
  calcProgress();

})();
