<?php
require_once __DIR__ . '/includes/init.php';

// Log del logout usando el sistema optimizado
if (!empty($_SESSION['uid'])) {
  log_user_activity('logout', $_SESSION['uid'], [
    'username' => $_SESSION['username'] ?? 'unknown'
  ]);
}

// Invalidar todos los tokens remember del usuario
if (!empty($_SESSION['uid'])) {
  invalidate_remember_tokens($_SESSION['uid']);
}

// Limpiar cookie remember
if (!empty($_COOKIE['remember'])) {
  setcookie('remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
  ]);
}

// destruir sesión
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', false, true);
}
session_destroy();

header('Location: index.php');
exit;
