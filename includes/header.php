<?php
require_once __DIR__ . '/dev.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security-config.php';

// Iniciar sesión segura usando la función centralizada
if (session_status() === PHP_SESSION_NONE) {
    start_secure_session();
}

// Headers de seguridad centralizados (solo si la función existe)
if (function_exists('set_security_headers')) {
    set_security_headers();
}


// Autologin por cookie "remember"
if (empty($_SESSION['uid']) && !empty($_COOKIE['remember'])) {
  $token = $_COOKIE['remember'];
  $user = validate_remember_token($token);
  
  if ($user) {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    // Log del autologin
    log_security_event('remember_login_success', [
      'user_id' => $user['user_id'],
      'username' => $user['username']
    ]);
  } else {
    // Token inválido, limpiar cookie
    setcookie('remember', '', time() - 3600, '/', '', false, true);
  }
}

// Refrescar datos de sesión si el usuario está logueado (para actualizar roles)
if (!empty($_SESSION['uid']) && empty($_SESSION['last_refresh'])) {
  $_SESSION['last_refresh'] = time();
} elseif (!empty($_SESSION['uid']) && (time() - $_SESSION['last_refresh']) > 300) { // 5 minutos
  try {
    $q = $pdo->prepare("SELECT username, role, status FROM users WHERE id = ? LIMIT 1");
    $q->execute([$_SESSION['uid']]);
    if ($row = $q->fetch()) {
      $_SESSION['username'] = $row['username'];
      $_SESSION['role'] = $row['role'];
      $_SESSION['last_refresh'] = time();
    }
  } catch (Throwable $e) {
    devlog('session refresh failed', ['err'=>$e->getMessage()]);
  }
}

// Headers de seguridad ya configurados en set_security_headers()
// Cache control adicional para usuarios logueados
if (!empty($_SESSION['uid']) || !empty($_COOKIE['remember'])) {
  header('Vary: Cookie', true);
}

// Helper de rutas web (sirve en / y en /mod/)
if (!function_exists('u')) {
  function u(string $path): string {
    $inMod = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/mod/') !== false)
             || (substr(dirname($_SERVER['SCRIPT_NAME'] ?? ''), -4) === '/mod');
    $prefix = $inMod ? '../' : '';
    return $prefix . ltrim($path, '/');
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="dark light" />
  <title>Bahia Under</title>

  <!-- CSS + JS -->
  <link rel="stylesheet" href="<?php echo u('css/style.css'); ?>" />
  <link rel="stylesheet" href="<?php echo u('css/matrix-theme.css'); ?>" />
  <script src="<?php echo u('js/scripts.js'); ?>" defer></script>

  <!-- Fuentes -->
  <link rel="preconnect" href="https://fonts.cdnfonts.com" crossorigin>
  <link href="https://fonts.cdnfonts.com/css/druk-wide-bold" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inconsolata:wght@200..900&display=swap" rel="stylesheet">
  
  <!-- Tema personalizado del usuario -->
  <link rel="stylesheet" href="<?php echo u('css/user-theme.php'); ?>">
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="<?php echo u('index.php'); ?>" aria-label="Ir al inicio">
      <!-- <img class="logo-img" src="<?php echo u('img/logo.svg'); ?>" alt="Bahia Under" onerror="this.style.display='none'"> -->
      <span class="brand-text">Bahia<span class="ground">Under</span></span>
    </a>

    <!-- Botón hamburguesa para móviles -->
    <button class="mobile-menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false">
      <span class="hamburger">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </span>
    </button>

    <nav class="menu" aria-label="Principal">
      <a href="<?php echo u('musica.php'); ?>">Música</a>
      <a href="<?php echo u('eventos.php'); ?>">Agenda</a>
      <a href="<?php echo u('blog.php'); ?>">Blog</a>
      <?php if (in_array($_SESSION['role'] ?? 'user', ['artist', 'mod', 'admin'])): ?>
      <a href="<?php echo u('upload.php'); ?>">Subir Musica</a>  
      <?php endif; ?>
    </nav>

    <div class="actions">
      <form class="search" role="search" method="get" action="<?php echo u('buscar.php'); ?>">
        <input aria-label="Buscar" name="q" type="search" placeholder="Buscar..." autocomplete="off" spellcheck="false" />
      </form>

      <?php if (!empty($_SESSION['uid'])): ?>
        <div class="auth">
          <div class="profile-dropdown">
            <button class="btn profile-btn" type="button" aria-expanded="false" aria-haspopup="true">
              <?php
              // Obtener avatar del usuario desde la base de datos
              $avatarPath = '';
              $avatarExists = false;
              try {
                $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['uid']]);
                $userData = $stmt->fetch();
                if ($userData && !empty($userData['avatar_path'])) {
                  $fullPath = __DIR__ . '/../' . $userData['avatar_path'];
                  if (file_exists($fullPath)) {
                    $avatarPath = u($userData['avatar_path']);
                    $avatarExists = true;
                  } else {
                    // Si el archivo no existe, limpiar la referencia en la BD
                    $stmt = $pdo->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?");
                    $stmt->execute([$_SESSION['uid']]);
                    devlog('avatar file not found, cleared from DB', ['path' => $userData['avatar_path']]);
                  }
                }
              } catch (Throwable $e) {
                devlog('avatar fetch failed', ['err'=>$e->getMessage()]);
              }
              ?>
              <?php if ($avatarExists): ?>
                <img class="profile-avatar" 
                     src="<?php echo $avatarPath; ?>" 
                     alt="Avatar de <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
              <?php else: ?>
                <div class="profile-avatar profile-avatar-placeholder">
                  <?php echo strtoupper(($_SESSION['username'] ?? 'U')[0]); ?>
                </div>
              <?php endif; ?>
              <span>Mi perfil</span>
              <svg class="dropdown-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none">
                <path d="M1 1.5L6 6.5L11 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <div class="dropdown-menu" role="menu">
              <a class="dropdown-item" href="<?php echo u('mi-perfil.php'); ?>" role="menuitem">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M8 8C10.2091 8 12 6.20914 12 4C12 1.79086 10.2091 0 8 0C5.79086 0 4 1.79086 4 4C4 6.20914 5.79086 8 8 8Z" fill="currentColor"/>
                  <path d="M8 10C4.68629 10 2 12.6863 2 16H14C14 12.6863 11.3137 10 8 10Z" fill="currentColor"/>
                </svg>
                Mi perfil
              </a>
              <?php if (in_array($_SESSION['role'] ?? 'user', ['mod','admin'], true)): ?>
                <a class="dropdown-item" href="<?php echo u('mod/review.php'); ?>" role="menuitem">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M2 3H14V13H2V3Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    <path d="M6 7H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M6 9H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                  Moderar
                </a>
                <a class="dropdown-item" href="<?php echo u('apache-logs.php'); ?>" role="menuitem">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M2 2H14V14H2V2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    <path d="M5 6H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M5 8H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M5 10H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                  Logs
                </a>
              <?php endif; ?>
              <hr class="dropdown-divider">
              <a class="dropdown-item" href="<?php echo u('logout.php'); ?>" role="menuitem">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M10 11L14 7L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M14 7H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Salir
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="auth">
          <a class="btn" href="<?php echo u('login.php'); ?>">Ingresar</a>
          <a class="btn primary" href="<?php echo u('register.php'); ?>">Registrarse</a>
        </div>
      <?php endif; ?>

      <button class="btn theme" type="button" data-theme-toggle aria-pressed="false" title="Cambiar tema">🌙</button>
    </div>
  </div>
</header>
