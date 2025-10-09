<?php
require_once __DIR__ . '/includes/header.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
  http_response_code(404);
  die('Blog no encontrado');
}

// Cargar blog
try {
  $stmt = $pdo->prepare("
    SELECT b.*, u.username, u.display_name
    FROM blogs b
    LEFT JOIN users u ON u.id = b.author_id
    WHERE b.slug = ? AND b.status = 'published'
  ");
  $stmt->execute([$slug]);
  $blog = $stmt->fetch();
  
  if (!$blog) {
    http_response_code(404);
    die('Blog no encontrado');
  }
} catch (Throwable $e) {
  devlog('blog.view_failed', ['err' => $e->getMessage()]);
  http_response_code(500);
  die('Error al cargar el blog');
}

// Verificar permisos de edición
$canEdit = !empty($_SESSION['uid']) && 
           ($_SESSION['role'] === 'admin' || $blog['author_id'] == $_SESSION['uid']);

// Función para renderizar markdown básico
function render_markdown($markdown) {
  // Headers
  $markdown = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $markdown);
  $markdown = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $markdown);
  $markdown = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $markdown);
  
  // Bold y italic
  $markdown = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $markdown);
  $markdown = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $markdown);
  
  // Links
  $markdown = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $markdown);
  
  // Images
  $markdown = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $markdown);
  
  // Code blocks
  $markdown = preg_replace('/```([^`]+)```/s', '<pre><code>$1</code></pre>', $markdown);
  $markdown = preg_replace('/`([^`]+)`/', '<code>$1</code>', $markdown);
  
  // Lists
  $markdown = preg_replace('/^\- (.*$)/m', '<li>$1</li>', $markdown);
  $markdown = preg_replace('/^(\d+)\. (.*$)/m', '<li>$2</li>', $markdown);
  
  // Wrap consecutive list items in ul/ol
  $markdown = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $markdown);
  
  // Blockquotes
  $markdown = preg_replace('/^> (.*$)/m', '<blockquote>$1</blockquote>', $markdown);
  
  // Horizontal rules
  $markdown = preg_replace('/^---$/m', '<hr>', $markdown);
  
  // Paragraphs
  $markdown = preg_replace('/\n\n/', '</p><p>', $markdown);
  $markdown = '<p>' . $markdown . '</p>';
  
  // Clean up empty paragraphs
  $markdown = preg_replace('/<p><\/p>/', '', $markdown);
  
  return $markdown;
}
?>

<main>
  <section id="blog-view">
    <div class="container">
      <article class="blog-post">
        <header class="blog-post-header">
          <h1 class="blog-post-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
          
          <div class="blog-post-meta">
            <div class="blog-author">
              <span>Por <strong><?php echo htmlspecialchars($blog['display_name'] ?: $blog['username']); ?></strong></span>
            </div>
            
            <time class="blog-date" datetime="<?php echo htmlspecialchars($blog['published_at']); ?>">
              <?php echo date('d/m/Y H:i', strtotime($blog['published_at'])); ?>
            </time>
            
            <div class="blog-tags">
              <?php if ($blog['featured']): ?>
                <span class="tag featured">Destacado</span>
              <?php endif; ?>
              <span class="tag">Blog</span>
            </div>
          </div>
        </header>

        <div class="blog-post-content">
          <?php echo render_markdown($blog['content']); ?>
        </div>

        <footer class="blog-post-footer">
          <div class="blog-post-actions">
            <a class="btn" href="blog.php">← Volver al blog</a>
            
            <?php if ($canEdit): ?>
              <a class="btn" href="blog-editor.php?id=<?php echo $blog['id']; ?>">
                Editar blog
              </a>
            <?php endif; ?>
            
            <button class="btn" onclick="window.print()">Imprimir</button>
          </div>
          
          <div class="blog-post-info">
            <p>
              <strong>Última actualización:</strong> 
              <?php echo date('d/m/Y H:i', strtotime($blog['updated_at'])); ?>
            </p>
          </div>
        </footer>
      </article>

      <!-- Navegación entre blogs -->
      <div class="blog-navigation">
        <?php
        // Obtener blog anterior y siguiente
        try {
          $prevStmt = $pdo->prepare("
            SELECT title, slug 
            FROM blogs 
            WHERE status = 'published' AND published_at < ? 
            ORDER BY published_at DESC 
            LIMIT 1
          ");
          $prevStmt->execute([$blog['published_at']]);
          $prevBlog = $prevStmt->fetch();
          
          $nextStmt = $pdo->prepare("
            SELECT title, slug 
            FROM blogs 
            WHERE status = 'published' AND published_at > ? 
            ORDER BY published_at ASC 
            LIMIT 1
          ");
          $nextStmt->execute([$blog['published_at']]);
          $nextBlog = $nextStmt->fetch();
        } catch (Throwable $e) {
          devlog('blog.navigation_failed', ['err' => $e->getMessage()]);
          $prevBlog = $nextBlog = null;
        }
        ?>
        
        <?php if ($prevBlog || $nextBlog): ?>
          <div class="nav-links">
            <?php if ($prevBlog): ?>
              <a class="nav-link prev" href="blog-view.php?slug=<?php echo htmlspecialchars($prevBlog['slug']); ?>">
                <span class="nav-arrow">←</span>
                <div class="nav-content">
                  <span class="nav-label">Anterior</span>
                  <span class="nav-title"><?php echo htmlspecialchars($prevBlog['title']); ?></span>
                </div>
              </a>
            <?php endif; ?>
            
            <?php if ($nextBlog): ?>
              <a class="nav-link next" href="blog-view.php?slug=<?php echo htmlspecialchars($nextBlog['slug']); ?>">
                <div class="nav-content">
                  <span class="nav-label">Siguiente</span>
                  <span class="nav-title"><?php echo htmlspecialchars($nextBlog['title']); ?></span>
                </div>
                <span class="nav-arrow">→</span>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sección de comentarios -->
      <div class="blog-comments">
        <section class="card">
          <h2>Comentarios</h2>
          
          <!-- Formulario de comentarios fijo arriba (estilo YouTube) -->
          <?php if (!empty($_SESSION['uid'])): ?>
            <div class="comment-form comment-form-fixed">
              <form id="comment-form" method="post">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" value="blog">
                <input type="hidden" name="target_id" value="<?php echo (int)$blog['id']; ?>">
                <div class="form-group">
                  <textarea name="content" class="input" rows="3" placeholder="Escribe tu comentario..." required></textarea>
                </div>
                <button type="submit" class="btn primary">Comentar</button>
              </form>
            </div>
          <?php else: ?>
            <div class="text-center">
              <p><a href="login.php" class="btn">Inicia sesión para comentar</a></p>
            </div>
          <?php endif; ?>
          
          <!-- Lista de comentarios -->
          <div id="comments-section">
            <div id="comments-loading" class="text-center">
              <p>Cargando comentarios...</p>
            </div>
            <div id="comments-list" class="hidden"></div>
            <div id="comments-empty" class="hidden empty">
              <p>No hay comentarios aún. ¡Sé el primero en comentar!</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</main>

<script src="js/vars.php?blog_id=<?php echo (int)$blog['id']; ?>"></script>
<script src="js/comments.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
