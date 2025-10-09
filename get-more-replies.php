<?php
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

// Validar tipo
if (!in_array($type, ['release', 'blog'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Tipo inválido']);
  exit;
}

// Validar ID
if ($parent_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID de comentario padre inválido']);
  exit;
}

try {
  $table = $type === 'release' ? 'release_comments' : 'blog_comments';
  
  // Obtener respuestas adicionales - incluyendo borrados
  $stmt = $pdo->prepare("
    SELECT c.*, u.username, u.display_name, u.avatar_path
    FROM $table c
    JOIN users u ON u.id = c.user_id
    WHERE c.parent_id = ? AND c.status IN ('active', 'deleted')
    ORDER BY c.created_at ASC
    LIMIT ? OFFSET ?
  ");
  $stmt->execute([$parent_id, $limit, $offset]);
  $replies = $stmt->fetchAll();
  
  // Función recursiva para cargar respuestas (reutilizada de get-comments.php)
  function loadRepliesRecursively($pdo, $table, $parent_id, $limit = 2) {
    $stmt = $pdo->prepare("
      SELECT c.*, u.username, u.display_name, u.avatar_path
      FROM $table c
      JOIN users u ON u.id = c.user_id
      WHERE c.parent_id = ? AND c.status IN ('active', 'deleted')
      ORDER BY c.created_at ASC
    ");
    $stmt->execute([$parent_id]);
    $allReplies = $stmt->fetchAll();
    
    $totalReplies = count($allReplies);
    $replies = array_slice($allReplies, 0, $limit);
    $hasMoreReplies = $totalReplies > $limit;
    
    foreach ($replies as &$reply) {
      $subReplies = loadRepliesRecursively($pdo, $table, $reply['id'], $limit);
      $reply['replies'] = $subReplies['replies'];
      $reply['total_replies'] = $subReplies['total_replies'];
      $reply['has_more_replies'] = $subReplies['has_more_replies'];
    }
    
    return [
      'replies' => $replies,
      'total_replies' => $totalReplies,
      'has_more_replies' => $hasMoreReplies
    ];
  }
  
  // Función recursiva para estructurar datos del usuario
  function structureUserDataRecursively(&$comments) {
    foreach ($comments as &$comment) {
      $comment['user'] = [
        'id' => $comment['user_id'],
        'username' => $comment['username'],
        'display_name' => $comment['display_name'],
        'avatar_path' => $comment['avatar_path']
      ];
      
      if (isset($comment['replies']) && is_array($comment['replies'])) {
        structureUserDataRecursively($comment['replies']);
      }
    }
  }
  
  // Cargar respuestas recursivamente para cada respuesta
  foreach ($replies as &$reply) {
    $repliesData = loadRepliesRecursively($pdo, $table, $reply['id'], 2);
    $reply['replies'] = $repliesData['replies'];
    $reply['total_replies'] = $repliesData['total_replies'];
    $reply['has_more_replies'] = $repliesData['has_more_replies'];
  }
  
  // Estructurar datos del usuario recursivamente
  structureUserDataRecursively($replies);
  
  echo json_encode([
    'success' => true,
    'replies' => $replies,
    'has_more' => count($replies) === $limit
  ]);
  
} catch (Exception $e) {
  devlog('get_more_replies_error', ['err' => $e->getMessage(), 'type' => $type, 'parent_id' => $parent_id]);
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
