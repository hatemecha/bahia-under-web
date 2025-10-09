<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';

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

include __DIR__ . '/includes/header.php';
?>

<main class="container max-w-860 p-2">
  <div class="page-head">
    <h1 class="page-title">Acerca de Bahia Under</h1>
    <p class="lede">La plataforma de música independiente de Bahía Blanca</p>
  </div>

  <section class="card mb-4">
    <h2>¿Qué es Bahia Under?</h2>
    <p>Bahia Under es una plataforma digital creada para la escena musical bahiense. Tiene como objetivo dar visibilidad y apoyo a los artistas locales, facilitando la difusión de su música y la conexión con la audiencia.</p>
  </section>

  <section class="card mb-4">
    <h2>Características de la Plataforma</h2>
    
    <h3 class="mt-4">Para Artistas</h3>
    <ul class="feature-list">
      <li>Subida gratuita de álbumes, EPs y singles</li>
      <li>Reproductor integrado con streaming de alta calidad</li>
      <li>Estadísticas básicas de reproducción</li>
      <li>Opción de descarga gratuita para los oyentes</li>
    </ul>

    <h3 class="mt-4">Para Oyentes</h3>
    <ul class="feature-list">
      <li>Descubrimiento de música local por género, fecha o popularidad</li>
      <li>Reproductor web integrado</li>
      <li>Descarga gratuita de lanzamientos autorizados</li>
      <li>Seguimiento de artistas favoritos</li>
    </ul>

    <h3 class="mt-4">Para la Comunidad</h3>
    <ul class="feature-list">
      <li>Agenda de eventos con mapas y detalles</li>
      <li>Blog con contenido editorial de la escena</li>
    </ul>
  </section>


  <section class="card mb-4">
    <h2>¿Cómo Funciona?</h2>
    
    <div class="process-grid">
      <div class="process-step">
        <h3>1. Registro</h3>
        <p>Los artistas se registran creando una cuenta gratuita con su información básica.</p>
      </div>
      
      <div class="process-step">
        <h3>2. Subida</h3>
        <p>Suben sus lanzamientos con portada, metadatos y archivos de audio.</p>
      </div>
      
      <div class="process-step">
        <h3>3. Moderación</h3>
        <p>Nuestro equipo de moderadores revisa el contenido para asegurar calidad y cumplimiento de normas.</p>
      </div>
      
      <div class="process-step">
        <h3>4. Publicación</h3>
        <p>Una vez aprobado, el lanzamiento se publica y está disponible para toda la comunidad.</p>
      </div>
      
      <div class="process-step">
        <h3>5. Descubrimiento</h3>
        <p>Los oyentes pueden descubrir, reproducir y descargar la música que les guste.</p>
      </div>
    </div>
  </section>

  <section class="card mb-4">
    <h2>Equipo y Contacto</h2>
    <p>Bahia Under es desarrollado y mantenido por Alex aka <span class="brand-color">hatemecha</span>.</p>
    
    <p>Si querés formar parte o tenés ideas para mejorar la plataforma, <a href="contacto.php">contactame</a>.</p>
  </section>

  <section class="card mb-4">
    <h2>Preguntas Frecuentes</h2>
    
    <div class="faq-item">
      <h3>¿Cómo puedo subir mi música?</h3>
      <p>Primero <a href="register.php">creá una cuenta</a> como artista. Una vez logueado, andá a la sección "Subir Música" en el menú principal. Tu lanzamiento será revisado por moderadores antes de publicarse.</p>
    </div>

    <div class="faq-item">
      <h3>¿Cuánto cuesta usar la plataforma?</h3>
      <p>Bahia Under es completamente gratuito, tanto para artistas como para oyentes. No hay suscripciones ni comisiones.</p>
    </div>

    <div class="faq-item">
      <h3>¿Puedo colaborar con el proyecto?</h3>
      <p>¡Por supuesto! Si sos desarrollador, diseñador, escritor o simplemente tenés ideas, <a href="contacto.php">contactame</a>.</p>
    </div>

    <div class="faq-item">
      <h3>Encontré un problema o bug</h3>
      <p>Por favor, reportalo por email o GitHub. Podés encontrar todos los canales de contacto en la página de <a href="contacto.php">contacto</a>.</p>
    </div>
  </section>

  <section class="card mb-4">
    <h2>Privacidad y Transparencia</h2>
    <p>Respetamos tu privacidad y somos transparentes sobre cómo usamos tus datos. No vendemos información personal ni compartimos datos con terceros.</p>
    
    <p>Para más detalles, consultá nuestra <a href="terminos.php">política de privacidad y términos de uso</a>.</p>
  </section>

  <section class="card">
    <h2>Contacto</h2>
    <p>¿Tenés preguntas, sugerencias o querés colaborar?</p>
    
    <div class="form-actions">
      <a class="btn primary" href="contacto.php">Contactar</a>
      <a class="btn" href="index.php">Volver al inicio</a>
    </div>
  </section>
</main>

<style>
/* Estilos para FAQ items */
.faq-item {
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.1));
}

.faq-item:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}

.faq-item h3 {
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
  color: var(--text-color);
}

.faq-item p {
  margin: 0;
  opacity: 0.85;
}

.faq-item a {
  color: var(--brand-color);
  text-decoration: none;
  border-bottom: 1px solid transparent;
  transition: border-color 0.2s ease;
}

.faq-item a:hover {
  border-bottom-color: var(--brand-color);
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

