<?php
// Incluir inicialización (sin HTML) para procesar POST antes de enviar headers
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';

// Verificar permisos para gestión
$canManage = !empty($_SESSION['uid']) && in_array($_SESSION['role'] ?? 'user', ['admin', 'mod'], true);

// Acciones de administración - PROCESAR ANTES DE GENERAR HTML
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar token CSRF antes de procesar cualquier acción
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Token CSRF inválido. Por favor, recarga la página e intenta de nuevo.');
  }
  
  $act = $_POST['action'] ?? '';
  
  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        // Cargar blog para verificar permisos
        $stmt = $pdo->prepare("SELECT author_id FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch();
        
        if (!$blog) {
          http_response_code(404);
          die('Blog no encontrado');
        }
        
        // Verificar permisos de ownership (admin/mod pueden eliminar cualquier blog)
        require_ownership($blog['author_id']);
        
        // Si llegamos aquí, el usuario tiene permisos - ejecutar UNA sola consulta
        $stmt = $pdo->prepare("UPDATE blogs SET status = 'archived' WHERE id = ?");
        $stmt->execute([$id]);
        
        // Log de la acción
        log_user_activity('blog_deleted', $_SESSION['uid'], ['blog_id' => $id]);
        
        // Redirigir después de eliminar (ahora SÍ funciona porque no se envió HTML)
        header('Location: blog.php?deleted=1');
        exit;
      } catch (Throwable $e) {
        devlog('blog.delete_failed', ['err' => $e->getMessage()]);
        http_response_code(500);
        die('Error al eliminar el blog');
      }
    }
  }
  
  if ($act === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id > 0 && in_array($status, ['draft', 'published'])) {
      try {
        // Cargar blog para verificar permisos
        $stmt = $pdo->prepare("SELECT author_id FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch();
        
        if (!$blog) {
          http_response_code(404);
          die('Blog no encontrado');
        }
        
        // Verificar permisos de ownership (admin/mod pueden editar cualquier blog)
        require_ownership($blog['author_id']);
        
        // Si llegamos aquí, el usuario tiene permisos - ejecutar UNA sola consulta
        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE blogs SET status = ?, published_at = ? WHERE id = ?");
        $stmt->execute([$status, $published_at, $id]);
        
        // Log de la acción
        log_user_activity('blog_status_changed', $_SESSION['uid'], [
          'blog_id' => $id,
          'new_status' => $status
        ]);
        
        // Redirigir después de actualizar (ahora SÍ funciona porque no se envió HTML)
        header('Location: blog.php?updated=1');
        exit;
      } catch (Throwable $e) {
        devlog('blog.toggle_status_failed', ['err' => $e->getMessage()]);
        http_response_code(500);
        die('Error al actualizar el estado del blog');
      }
    }
  }
}

// Filtros
$search = sanitize_search_query($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'published';
$author = (int)($_GET['author'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// Construir consulta
$where = [];
$params = [];

if ($search) {
  $where[] = "(b.title LIKE ? OR b.content LIKE ? OR b.excerpt LIKE ?)";
  $searchTerm = "%$search%";
  $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status !== 'all') {
  $where[] = "b.status = ?";
  $params[] = $status;
}

if ($author > 0) {
  $where[] = "b.author_id = ?";
  $params[] = $author;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Contar total
try {
  $countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM blogs b
    LEFT JOIN users u ON u.id = b.author_id
    $whereClause
  ");
  $countStmt->execute($params);
  $total = $countStmt->fetch()['total'] ?? 0;
} catch (Throwable $e) {
  devlog('blog.count_failed', ['err' => $e->getMessage()]);
  $total = 0;
}

// Obtener blogs
$offset = ($page - 1) * $perPage;
$blogs = [];

try {
  $stmt = $pdo->prepare("
    SELECT b.*, u.username, u.display_name
    FROM blogs b
    LEFT JOIN users u ON u.id = b.author_id
    $whereClause
    ORDER BY b.featured DESC, b.published_at DESC, b.created_at DESC
    LIMIT ? OFFSET ?
  ");
  
  // Añadir LIMIT y OFFSET a los parámetros con tipado explícito
  $allParams = array_merge($params, [$perPage, $offset]);
  
  // Vincular los últimos dos parámetros como enteros para mayor seguridad
  $paramCount = count($params);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
  }
  $stmt->bindValue($paramCount + 1, $perPage, PDO::PARAM_INT);
  $stmt->bindValue($paramCount + 2, $offset, PDO::PARAM_INT);
  
  $stmt->execute();
  $blogs = $stmt->fetchAll();
} catch (Throwable $e) {
  devlog('blog.list_failed', ['err' => $e->getMessage()]);
}

// Obtener autores para filtro
$authors = [];
if ($canManage) {
  try {
    $stmt = $pdo->query("SELECT id, username, display_name FROM users WHERE status = 'active' ORDER BY username");
    $authors = $stmt->fetchAll();
  } catch (Throwable $e) {
    devlog('blog.authors_failed', ['err' => $e->getMessage()]);
  }
}

// Paginación
$totalPages = ceil($total / $perPage);
$hasNext = $page < $totalPages;
$hasPrev = $page > 1;

// Función para generar slug
function generate_slug($title) {
  $slug = strtolower($title);
  $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
  $slug = preg_replace('/[\s-]+/', '-', $slug);
  return trim($slug, '-');
}

// Función para extraer excerpt del markdown
function extract_excerpt($markdown, $length = 150) {
  $text = strip_tags($markdown);
  $text = preg_replace('/[#*_`]/', '', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = trim($text);
  
  if (strlen($text) <= $length) {
    return $text;
  }
  
  return substr($text, 0, $length) . '...';
}

// AHORA SÍ incluir el header (que genera HTML)
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section id="blog">
    <div class="container">
      <div class="page-head">
        <h1>Blog</h1>
        <p>Noticias, reseñas y contenido de la escena underground</p>
      </div>

      <!-- Mensajes de éxito -->
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
          Blog eliminado correctamente
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
          Blog actualizado correctamente
        </div>
      <?php endif; ?>

      <!-- Filtros -->
      <div class="filters">
        <form method="get" class="filters-form">
          <div class="filter-group">
            <input class="input" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar en blogs...">
          </div>
          
          <div class="filter-group">
            <select class="input" name="status">
              <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Publicados</option>
              <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Borradores</option>
              <?php if ($canManage): ?>
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos</option>
              <?php endif; ?>
            </select>
          </div>
          
          <?php if ($canManage && $authors): ?>
            <div class="filter-group">
              <select class="input" name="author">
                <option value="0">Todos los autores</option>
                <?php foreach ($authors as $authorOption): ?>
                  <option value="<?php echo $authorOption['id']; ?>" <?php echo $authorOption['id'] == $author ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($authorOption['display_name'] ?: $authorOption['username']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          
          <button class="btn" type="submit">Filtrar</button>
          <?php if ($search || $status !== 'published' || $author > 0): ?>
            <a class="btn muted" href="blog.php">Limpiar</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Acciones de gestión -->
      <?php if ($canManage): ?>
        <div class="blog-actions">
          <a class="btn primary" href="blog-editor.php">Escribir nuevo blog</a>
        </div>
      <?php endif; ?>

      <!-- Listado de blogs -->
      <?php if (empty($blogs)): ?>
        <div class="empty">
          <p>No se encontraron blogs con los filtros seleccionados.</p>
          <?php if ($canManage): ?>
            <a class="btn" href="blog-editor.php">Crear el primer blog</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="blog-grid">
          <?php foreach ($blogs as $blog): ?>
            <article class="blog-card <?php echo $blog['featured'] ? 'featured' : ''; ?>">
              <div class="blog-header">
                <h2 class="blog-title">
                  <a href="blog-view.php?slug=<?php echo htmlspecialchars($blog['slug']); ?>">
                    <?php echo htmlspecialchars($blog['title']); ?>
                  </a>
                </h2>
                <div class="blog-meta">
                  <span class="blog-author">
                    Por <strong><?php echo htmlspecialchars($blog['display_name'] ?: $blog['username']); ?></strong>
                  </span>
                  <time class="blog-date" datetime="<?php echo htmlspecialchars($blog['published_at'] ?: $blog['created_at']); ?>">
                    <?php echo date('d/m/Y', strtotime($blog['published_at'] ?: $blog['created_at'])); ?>
                  </time>
                </div>
              </div>
              
              <div class="blog-content">
                <p class="blog-excerpt">
                  <?php echo htmlspecialchars($blog['excerpt'] ?: extract_excerpt($blog['content'])); ?>
                </p>
              </div>
              
              <div class="blog-footer">
                <div class="blog-tags">
                  <?php if ($blog['featured']): ?>
                    <span class="tag featured">Destacado</span>
                  <?php endif; ?>
                  <span class="tag status-<?php echo $blog['status']; ?>">
                    <?php echo ucfirst($blog['status']); ?>
                  </span>
                </div>
                
                <div class="blog-actions">
                  <a class="btn small" href="blog-view.php?slug=<?php echo htmlspecialchars($blog['slug']); ?>">
                    Leer más
                  </a>
                  
                  <?php if ($canManage && ($_SESSION['uid'] == $blog['author_id'] || $_SESSION['role'] === 'admin')): ?>
                    <a class="btn small" href="blog-editor.php?id=<?php echo $blog['id']; ?>">
                      Editar
                    </a>
                    
                    <form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar este blog?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo $blog['id']; ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                      <button class="btn small muted" type="submit">Eliminar</button>
                    </form>
                    
                    <?php if ($blog['status'] === 'draft'): ?>
                      <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?php echo $blog['id']; ?>">
                        <input type="hidden" name="status" value="published">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button class="btn small primary" type="submit">Publicar</button>
                      </form>
                    <?php elseif ($blog['status'] === 'published'): ?>
                      <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?php echo $blog['id']; ?>">
                        <input type="hidden" name="status" value="draft">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button class="btn small muted" type="submit">Despublicar</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php if ($hasPrev): ?>
              <a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                ← Anterior
              </a>
            <?php endif; ?>
            
            <span class="pagination-info">
              Página <?php echo $page; ?> de <?php echo $totalPages; ?>
              (<?php echo $total; ?> blogs)
            </span>
            
            <?php if ($hasNext): ?>
              <a class="btn" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                Siguiente →
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
