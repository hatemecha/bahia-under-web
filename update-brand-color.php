<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => false,
    'httponly' => true, 'samesite' => 'Lax'
  ]);
  session_name('ugb_session');
  session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Debes iniciar sesión']);
  exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$brand_color = $input['brand_color'] ?? '';

// Validar color
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $brand_color)) {
  http_response_code(400);
  echo json_encode(['error' => 'Color inválido']);
  exit;
}

try {
  // Actualizar el color en la base de datos
  $stmt = $pdo->prepare("UPDATE users SET brand_color = ? WHERE id = ?");
  $stmt->execute([$brand_color, $_SESSION['uid']]);
  
  if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'brand_color' => $brand_color]);
  } else {
    echo json_encode(['error' => 'No se pudo actualizar el color']);
  }
  
} catch (Exception $e) {
  // Solo registrar errores críticos de actualización de color
  devlog('brand_color_update_failed', [
    'err' => $e->getMessage(), 
    'user_id' => $_SESSION['uid'],
    'color' => $brand_color
  ]);
  http_response_code(500);
  echo json_encode(['error' => 'Error interno del servidor']);
}
?>
