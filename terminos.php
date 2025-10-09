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

<main class="container max-w-720 p-2">
  <div class="page-head">
    <h1 class="page-title">Términos de Uso y Política de Privacidad</h1>
    <p class="lede">Última actualización: <?php echo date('d/m/Y'); ?></p>
  </div>

  <section class="card">
    <h2>1. Aceptación de los Términos</h2>
    <p>Al acceder y usar Bahia Under, aceptás estos términos de uso y nuestra política de privacidad. Si no estás de acuerdo con alguna parte de estos términos, no debés usar nuestra plataforma.</p>
  </section>

  <section class="card">
    <h2>2. Descripción del Servicio</h2>
    <p>Bahia Under es una plataforma digital que permite a los artistas locales subir, distribuir y promocionar su música, y a los usuarios descubrir, reproducir y descargar contenido musical independiente de Bahía Blanca.</p>
    
    <h3>Servicios Incluidos:</h3>
    <ul>
      <li>Subida y distribución de música en formato digital</li>
      <li>Reproductor web integrado</li>
      <li>Sistema de búsqueda y descubrimiento</li>
      <li>Agenda de eventos musicales</li>
      <li>Blog y contenido editorial</li>
      <li>Perfiles de artistas y usuarios</li>
    </ul>
  </section>

  <section class="card">
    <h2>3. Cuentas de Usuario</h2>
    
    <h3>3.1 Registro</h3>
    <p>Para usar ciertas funciones de la plataforma, necesitás crear una cuenta. Te comprometés a:</p>
    <ul>
      <li>Proporcionar información veraz, precisa y actualizada</li>
      <li>Mantener la confidencialidad de tu contraseña</li>
      <li>Ser responsable de todas las actividades bajo tu cuenta</li>
      <li>Notificarnos inmediatamente sobre cualquier uso no autorizado</li>
    </ul>

    <h3>3.2 Tipos de Cuenta</h3>
    <ul>
      <li><strong>Usuario:</strong> Puede reproducir, descargar y seguir artistas</li>
      <li><strong>Artista:</strong> Puede subir música y gestionar su perfil</li>
      <li><strong>Moderador:</strong> Puede revisar y moderar contenido</li>
      <li><strong>Administrador:</strong> Acceso completo a la plataforma</li>
    </ul>
  </section>

  <section class="card">
    <h2>4. Contenido y Propiedad Intelectual</h2>
    
    <h3>4.1 Contenido del Usuario</h3>
    <p>Al subir contenido a Bahia Under, declarás que:</p>
    <ul>
      <li>Tenés los derechos necesarios sobre el contenido</li>
      <li>El contenido no infringe derechos de terceros</li>
      <li>El contenido cumple con nuestras políticas de contenido</li>
      <li>Nos otorgás una licencia no exclusiva para usar, reproducir y distribuir tu contenido</li>
    </ul>

    <h3>4.2 Política de Contenido</h3>
    <p>No está permitido subir contenido que:</p>
    <ul>
      <li>Viole leyes aplicables</li>
      <li>Infrinja derechos de propiedad intelectual</li>
      <li>Sea ofensivo, difamatorio o inapropiado</li>
      <li>Contenga malware o código malicioso</li>
      <li>Promueva actividades ilegales</li>
    </ul>

    <h3>4.3 Moderación</h3>
    <p>Nos reservamos el derecho de revisar, editar, rechazar o eliminar cualquier contenido que consideremos inapropiado, sin previo aviso.</p>
  </section>

  <section class="card">
    <h2>5. Uso Aceptable</h2>
    <p>Al usar Bahia Under, te comprometés a:</p>
    <ul>
      <li>Usar la plataforma solo para fines legales</li>
      <li>Respetar los derechos de otros usuarios</li>
      <li>No intentar acceder a cuentas de otros usuarios</li>
      <li>No usar la plataforma para spam o actividades comerciales no autorizadas</li>
      <li>No interferir con el funcionamiento de la plataforma</li>
      <li>Reportar contenido inapropiado cuando lo encuentres</li>
    </ul>
  </section>

  <section class="card">
    <h2>6. Privacidad y Protección de Datos</h2>
    
    <h3>6.1 Información que Recopilamos</h3>
    <ul>
      <li><strong>Información de cuenta:</strong> Nombre de usuario, email, nombre real (opcional)</li>
      <li><strong>Contenido:</strong> Música, portadas, metadatos, biografías</li>
      <li><strong>Uso:</strong> Estadísticas de reproducción, interacciones, preferencias</li>
      <li><strong>Técnica:</strong> Dirección IP, tipo de navegador, cookies</li>
    </ul>

    <h3>6.2 Cómo Usamos tu Información</h3>
    <ul>
      <li>Proporcionar y mejorar nuestros servicios</li>
      <li>Personalizar tu experiencia en la plataforma</li>
      <li>Comunicarnos contigo sobre actualizaciones y novedades</li>
      <li>Generar estadísticas anónimas de uso</li>
      <li>Cumplir con obligaciones legales</li>
    </ul>

    <h3>6.3 Compartir Información</h3>
    <p>No vendemos, alquilamos ni compartimos tu información personal con terceros, excepto:</p>
    <ul>
      <li>Cuando sea necesario para proporcionar el servicio</li>
      <li>Con tu consentimiento explícito</li>
      <li>Para cumplir con obligaciones legales</li>
      <li>Para proteger nuestros derechos y la seguridad de los usuarios</li>
    </ul>

    <h3>6.4 Tus Derechos</h3>
    <p>Tenés derecho a:</p>
    <ul>
      <li>Acceder a tu información personal</li>
      <li>Corregir información inexacta</li>
      <li>Eliminar tu cuenta y datos asociados</li>
      <li>Exportar tus datos</li>
      <li>Retirar tu consentimiento en cualquier momento</li>
    </ul>
  </section>

  <section class="card">
    <h2>7. Limitación de Responsabilidad</h2>
    <p>Bahia Under se proporciona "tal como está". No garantizamos que:</p>
    <ul>
      <li>El servicio esté siempre disponible o libre de errores</li>
      <li>El contenido sea preciso o completo</li>
      <li>Los archivos estén libres de virus o malware</li>
      <li>La plataforma sea compatible con todos los dispositivos</li>
    </ul>
    
    <p>En ningún caso seremos responsables por daños directos, indirectos, incidentales o consecuenciales que surjan del uso de la plataforma.</p>
  </section>

  <section class="card">
    <h2>8. Suspensión y Terminación</h2>
    <p>Nos reservamos el derecho de suspender o terminar tu cuenta si:</p>
    <ul>
      <li>Violás estos términos de uso</li>
      <li>Subís contenido inapropiado</li>
      <li>Realizás actividades fraudulentas</li>
      <li>No usás tu cuenta por un período prolongado</li>
    </ul>
    
    <p>Podés cerrar tu cuenta en cualquier momento contactándonos o usando las opciones en tu perfil.</p>
  </section>

  <section class="card">
    <h2>9. Modificaciones</h2>
    <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Las modificaciones entrarán en vigor inmediatamente después de su publicación en la plataforma. Es tu responsabilidad revisar periódicamente estos términos.</p>
  </section>

  <section class="card">
    <h2>10. Ley Aplicable</h2>
    <p>Estos términos se rigen por las leyes de la República Argentina. Cualquier disputa será resuelta en los tribunales competentes de Bahía Blanca, Provincia de Buenos Aires.</p>
  </section>

  <section class="card">
    <h2>11. Contacto</h2>
    <p>Si tenés preguntas sobre estos términos o nuestra política de privacidad, podés contactarnos a través de:</p>
    <ul>
      <li>Formulario de contacto en la plataforma</li>
      <li>Email: contacto@bahiaunder.com</li>
      <li>Redes sociales oficiales</li>
    </ul>
    
    <div class="form-actions">
      <a class="btn primary" href="contacto.php">Contactar</a>
      <a class="btn" href="acerca.php">Acerca de</a>
      <a class="btn" href="index.php">Volver al inicio</a>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

