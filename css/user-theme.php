<?php
header('Content-Type: text/css');

require_once __DIR__ . '/../includes/dev.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => false,
    'httponly' => true, 'samesite' => 'Lax'
  ]);
  session_name('ugb_session');
  session_start();
}

// Aplicar tema personalizado del usuario
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid'])) {
  try {
    $stmt = $pdo->prepare("SELECT brand_color FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['uid']]);
    $user = $stmt->fetch();
    
    if ($user && !empty($user['brand_color'])) {
      $brand_color = htmlspecialchars($user['brand_color']);
      $brand_hover = adjust_color($brand_color, -20);
      $brand_light = $brand_color . '1a';
      
      echo ":root {
        --brand: {$brand_color} !important;
        --brand-hover: {$brand_hover} !important;
        --brand-light: {$brand_light} !important;
      }
      
      /* Forzar aplicación en elementos específicos */
      .btn.primary, .btn.primary:hover {
        background-color: {$brand_color} !important;
        border-color: {$brand_color} !important;
      }
      
      .btn.primary:hover {
        background-color: {$brand_hover} !important;
        border-color: {$brand_hover} !important;
      }
      
      .badge {
        background-color: {$brand_color} !important;
      }
      
      .brand {
        color: {$brand_color} !important;
      }";
      

    }
  } catch (Exception $e) {
    // Si hay error, usar colores por defecto
    devlog('user_theme_failed', [
      'err' => $e->getMessage(),
      'uid' => $_SESSION['uid'] ?? 'NO_SESSION'
    ]);
  }
}

// Función para ajustar brillo de color
function adjust_color($color, $amount) {
  $usePound = $color[0] === '#';
  $col = $usePound ? substr($color, 1) : $color;
  $num = hexdec($col);
  $r = ($num >> 16) + $amount;
  $g = (($num >> 8) & 0x00FF) + $amount;
  $b = ($num & 0x0000FF) + $amount;
  $r = $r > 255 ? 255 : ($r < 0 ? 0 : $r);
  $g = $g > 255 ? 255 : ($g < 0 ? 0 : $g);
  $b = $b > 255 ? 255 : ($b < 0 ? 0 : $b);
  return ($usePound ? '#' : '') . str_pad(dechex(($r << 16) | ($g << 8) | $b), 6, '0', STR_PAD_LEFT);
}
?>
