<?php
// Incluir archivos de configuración necesarios
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_name('ugb_session');
  session_start();
}

// Incluir header del sitio
include __DIR__ . '/includes/header.php';
?>

<main class="container max-w-720 p-2">
  <!-- Encabezado de la página -->
  <div class="page-head">
    <h1 class="page-title">Contacto</h1>
    <p class="lede">¿Tenés dudas, sugerencias o querés colaborar? ¡Hablemos!</p>
  </div>

  <!-- Introducción -->
  <section class="card">
    <h2>¡Estamos acá para escucharte!</h2>
    <p>
      Bahia Under es un proyecto en constante evolución, y tu feedback es fundamental para hacerlo mejor.
      Ya sea que tengas preguntas, sugerencias, quieras reportar un problema o simplemente charlar sobre 
      la escena musical bahiense, no dudes en contactarme.
    </p>
  </section>

  <!-- Métodos de contacto -->
  <section class="card">
    <h2>Canales de Contacto</h2>
    
    <!-- Email -->
    <div class="contact-method">
      <div class="contact-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
          <polyline points="22,6 12,13 2,6"></polyline>
        </svg>
      </div>
      <div class="contact-info">
        <h3>Email</h3>
        <p>La forma más directa de contactarme. Respondo en 24-48 horas.</p>
        <a href="mailto:alex_dlarg@proton.me" class="contact-link">alex_dlarg@proton.me</a>
      </div>
    </div>

    <!-- Instagram -->
    <div class="contact-method">
      <div class="contact-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
          <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
          <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
        </svg>
      </div>
      <div class="contact-info">
        <h3>Instagram</h3>
        <p>Seguime para novedades, actualizaciones y detrás de escena del proyecto.</p>
        <a href="https://instagram.com/hatemecha" target="_blank" rel="noopener noreferrer" class="contact-link">@hatemecha</a>
      </div>
    </div>

    <!-- GitHub -->
    <div class="contact-method">
      <div class="contact-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
        </svg>
      </div>
      <div class="contact-info">
        <h3>GitHub</h3>
        <p>Reportá bugs, sugerí features o contribuí al código del proyecto.</p>
        <a href="https://github.com/hatemecha" target="_blank" rel="noopener noreferrer" class="contact-link">github.com/hatemecha</a>
      </div>
    </div>
  </section>

  <!-- Call to action -->
  <section class="card">
    <p>Si querés saber más sobre el proyecto o tenés preguntas frecuentes, visitá la página de <a href="acerca.php">acerca de</a>.</p>
    
    <div class="form-actions">
      <a class="btn primary" href="acerca.php">Acerca de</a>
      <a class="btn" href="terminos.php">Términos y Privacidad</a>
      <a class="btn" href="index.php">Volver al inicio</a>
    </div>
  </section>
</main>

<style>
/* Estilos específicos para la página de contacto */

/* Métodos de contacto */
.contact-method {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
  padding: 1.5rem 0;
  border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.1));
}

.contact-method:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.contact-method:first-child {
  padding-top: 0;
}

.contact-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--brand-color);
  color: var(--bg-color);
  border-radius: 12px;
  transition: transform 0.2s ease;
}

.contact-method:hover .contact-icon {
  transform: scale(1.1);
}

.contact-info {
  flex: 1;
}

.contact-info h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: var(--brand-color);
}

.contact-info p {
  margin: 0 0 0.75rem 0;
  opacity: 0.85;
}

.contact-link {
  display: inline-block;
  color: var(--text-color);
  text-decoration: none;
  font-weight: 600;
  padding: 0.5rem 1rem;
  background: color-mix(in srgb, var(--brand-color) 10%, transparent);
  border-radius: 6px;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.contact-link:hover {
  background: color-mix(in srgb, var(--brand-color) 20%, transparent);
  border-color: var(--brand-color);
  transform: translateX(4px);
}

/* Responsive */
@media (max-width: 600px) {
  .contact-method {
    flex-direction: column;
    gap: 1rem;
  }
  
  .contact-icon {
    width: 40px;
    height: 40px;
  }
  
  .contact-icon svg {
    width: 24px;
    height: 24px;
  }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

