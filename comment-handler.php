<?php
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Debes iniciar sesión para comentar']);
  exit;
}

$action = trim($_POST['action'] ?? '');
$type = $_POST['type'] ?? '';
$target_id = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

// Validar acción
if (!in_array($action, ['add', 'delete'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Acción inválida']);
  exit;
}

// Validar tipo
if (!in_array($type, ['release', 'blog'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Tipo inválido']);
  exit;
}

// Validar ID
if ($target_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

// Debug logging
error_log("COMMENT DEBUG - Action: $action, Type: $type, Target ID: $target_id, POST: " . json_encode($_POST));

$user_id = (int)$_SESSION['uid'];

try {
  switch ($action) {
    case 'add':
      $content = trim($_POST['content'] ?? '');
      $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
      
      if (empty($content)) {
        throw new Exception('El comentario no puede estar vacío');
      }
      
      if (strlen($content) > 1000) {
        throw new Exception('El comentario es demasiado largo');
      }
      
      // Verificar que el target existe
      if ($type === 'release') {
        $stmt = $pdo->prepare("SELECT id FROM releases WHERE id = ? AND status = 'approved'");
        $stmt->execute([$target_id]);
        if (!$stmt->fetch()) {
          throw new Exception('Lanzamiento no encontrado');
        }
        
        $table = 'release_comments';
        $target_field = 'release_id';
      } else {
        $stmt = $pdo->prepare("SELECT id FROM blogs WHERE id = ? AND status = 'published'");
        $stmt->execute([$target_id]);
        if (!$stmt->fetch()) {
          throw new Exception('Blog no encontrado');
        }
        
        $table = 'blog_comments';
        $target_field = 'blog_id';
      }
      
      // Si es respuesta, verificar que el comentario padre existe
      if ($parent_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? AND $target_field = ? AND status = 'active'");
        $stmt->execute([$parent_id, $target_id]);
        if (!$stmt->fetch()) {
          throw new Exception('Comentario padre no encontrado');
        }
      }
      
      $stmt = $pdo->prepare("
        INSERT INTO $table ($target_field, user_id, content, parent_id) 
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([$target_id, $user_id, $content, $parent_id ?: null]);
      
      $comment_id = $pdo->lastInsertId();
      
      // Obtener el comentario recién creado con datos del usuario
      $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.display_name, u.avatar_path
        FROM $table c
        JOIN users u ON u.id = c.user_id
        WHERE c.id = ?
      ");
      $stmt->execute([$comment_id]);
      $comment = $stmt->fetch();
      
      echo json_encode([
        'success' => true,
        'comment' => [
          'id' => $comment['id'],
          'content' => $comment['content'],
          'parent_id' => $comment['parent_id'],
          'created_at' => $comment['created_at'],
          'user' => [
            'id' => $comment['user_id'],
            'username' => $comment['username'],
            'display_name' => $comment['display_name'],
            'avatar_path' => $comment['avatar_path']
          ]
        ]
      ]);
      break;
      
    case 'delete':
      $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
      
      if (!$comment_id) {
        throw new Exception('ID de comentario inválido');
      }
      
      $table = $type === 'release' ? 'release_comments' : 'blog_comments';
      $target_field = $type === 'release' ? 'release_id' : 'blog_id';
      
      // Verificar permisos (solo el autor o admin/mod)
      $stmt = $pdo->prepare("
        SELECT user_id FROM $table 
        WHERE id = ? AND $target_field = ?
      ");
      $stmt->execute([$comment_id, $target_id]);
      $comment = $stmt->fetch();
      
      if (!$comment) {
        throw new Exception('Comentario no encontrado');
      }
      
      $can_delete = ($comment['user_id'] == $user_id) || 
                   in_array($_SESSION['role'] ?? 'user', ['admin', 'mod'], true);
      
      if (!$can_delete) {
        throw new Exception('No tienes permisos para eliminar este comentario');
      }
      
      $stmt = $pdo->prepare("UPDATE $table SET status = 'deleted' WHERE id = ?");
      $stmt->execute([$comment_id]);
      
      echo json_encode(['success' => true]);
      break;
      
    default:
      throw new Exception('Acción no válida');
  }
  
} catch (Exception $e) {
  devlog('comment_handler_error', ['err' => $e->getMessage(), 'action' => $action, 'type' => $type]);
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
