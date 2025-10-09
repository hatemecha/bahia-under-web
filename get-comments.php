<?php
// Evitar cualquier output antes del JSON
ob_start();

require_once __DIR__ . '/includes/init.php';

// Limpiar cualquier output buffer
ob_clean();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;

// Parámetros de paginación
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10))); // Máximo 50 comentarios por request

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


try {
  if ($type === 'release') {
    $table = 'release_comments';
    $target_field = 'release_id';
    
    // Verificar que el release existe
    $stmt = $pdo->prepare("SELECT id FROM releases WHERE id = ?");
    $stmt->execute([$target_id]);
    if (!$stmt->fetch()) {
      throw new Exception('Lanzamiento no encontrado');
    }
  } else {
    $table = 'blog_comments';
    $target_field = 'blog_id';
    
    // Verificar que el blog existe
    $stmt = $pdo->prepare("SELECT id FROM blogs WHERE id = ?");
    $stmt->execute([$target_id]);
    if (!$stmt->fetch()) {
      throw new Exception('Blog no encontrado');
    }
  }
  
  // Contar total de comentarios principales
  $countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM $table c
    WHERE c.$target_field = ? AND c.parent_id IS NULL AND c.status IN ('active', 'deleted')
  ");
  $countStmt->execute([$target_id]);
  $totalComments = $countStmt->fetch()['total'] ?? 0;
  
  // Obtener comentarios principales con paginación
  $stmt = $pdo->prepare("
    SELECT c.*, u.username, u.display_name, u.avatar_path
    FROM $table c
    JOIN users u ON u.id = c.user_id
    WHERE c.$target_field = ? AND c.parent_id IS NULL AND c.status IN ('active', 'deleted')
    ORDER BY c.created_at ASC
    LIMIT ? OFFSET ?
  ");
  
  // Bind con tipos explícitos para seguridad
  $stmt->bindValue(1, $target_id, PDO::PARAM_INT);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();
  
  $comments = $stmt->fetchAll();
  
  // Calcular si hay más comentarios
  $hasMore = ($offset + count($comments)) < $totalComments;
  $nextOffset = $offset + count($comments);
  
  // Log de depuración
  devlog('get_comments', [
    'type' => $type,
    'target_id' => $target_id,
    'offset' => $offset,
    'limit' => $limit,
    'found' => count($comments),
    'total' => $totalComments,
    'has_more' => $hasMore
  ]);
  
  // Función recursiva para cargar respuestas de cualquier nivel
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
    
    // Contar total de respuestas
    $totalReplies = count($allReplies);
    
    // Mostrar solo las primeras respuestas inicialmente
    $replies = array_slice($allReplies, 0, $limit);
    $hasMoreReplies = $totalReplies > $limit;
    
    // Para cada respuesta, cargar sus sub-respuestas recursivamente
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
  
  // Para cada comentario principal, cargar sus respuestas recursivamente
  foreach ($comments as &$comment) {
    $repliesData = loadRepliesRecursively($pdo, $table, $comment['id'], 2);
    $comment['replies'] = $repliesData['replies'];
    $comment['total_replies'] = $repliesData['total_replies'];
    $comment['has_more_replies'] = $repliesData['has_more_replies'];
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
      
      // Procesar respuestas recursivamente
      if (isset($comment['replies']) && is_array($comment['replies'])) {
        structureUserDataRecursively($comment['replies']);
      }
    }
  }
  
  // Estructurar los datos del usuario correctamente
  structureUserDataRecursively($comments);
  
  echo json_encode([
    'success' => true,
    'comments' => $comments,
    'pagination' => [
      'total' => $totalComments,
      'offset' => $offset,
      'limit' => $limit,
      'has_more' => $hasMore,
      'next_offset' => $hasMore ? $nextOffset : null,
      'loaded' => count($comments)
    ]
  ]);
  
} catch (Exception $e) {
  // Limpiar cualquier output antes del error
  ob_clean();
  
  devlog('get_comments_error', ['err' => $e->getMessage(), 'type' => $type, 'target_id' => $target_id]);
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
} catch (Error $e) {
  // Limpiar cualquier output antes del error
  ob_clean();
  
  devlog('get_comments_fatal_error', ['err' => $e->getMessage(), 'type' => $type, 'target_id' => $target_id]);
  http_response_code(500);
  echo json_encode(['error' => 'Error interno del servidor']);
}
