<?php
// Incluir inicialización (sin HTML) para poder procesar POST primero
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';

// Verificar permisos
require_role(['admin', 'mod']);

// Funciones auxiliares
function generate_slug($title) {
  $slug = strtolower($title);
  $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
  $slug = preg_replace('/[\s-]+/', '-', $slug);
  return trim($slug, '-');
}

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

$blogId = (int)($_GET['id'] ?? 0);
$blog = null;
$isEdit = $blogId > 0;
$errors = [];

// Procesar formulario ANTES de generar HTML (para poder redirigir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar token CSRF
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Token CSRF inválido. Por favor, recarga la página e intenta de nuevo.');
  }
  
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $excerpt = trim($_POST['excerpt'] ?? '');
  $status = $_POST['status'] ?? 'draft';
  $featured = isset($_POST['featured']) ? 1 : 0;
  
  if (empty($title)) {
    $errors[] = 'El título es obligatorio';
  }
  
  if (empty($content)) {
    $errors[] = 'El contenido es obligatorio';
  }
  
  if (!in_array($status, ['draft', 'published'])) {
    $errors[] = 'Estado inválido';
  }
  
  if (empty($errors)) {
    try {
      // Generar slug único
      $slug = generate_slug($title);
      $originalSlug = $slug;
      $counter = 1;
      
      while (true) {
        $checkStmt = $pdo->prepare("SELECT id FROM blogs WHERE slug = ? AND id != ?");
        $checkStmt->execute([$slug, $blogId]);
        if (!$checkStmt->fetch()) break;
        $slug = $originalSlug . '-' . $counter;
        $counter++;
      }
      
      // Generar excerpt automático si no se proporciona
      if (empty($excerpt)) {
        $excerpt = extract_excerpt($content);
      }
      
      $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
      
      if ($isEdit) {
        // Actualizar blog existente
        $stmt = $pdo->prepare("
          UPDATE blogs 
          SET title = ?, slug = ?, content = ?, excerpt = ?, status = ?, featured = ?, published_at = ?
          WHERE id = ?
        ");
        $stmt->execute([$title, $slug, $content, $excerpt, $status, $featured, $publishedAt, $blogId]);
        $message = 'Blog actualizado correctamente';
      } else {
        // Crear nuevo blog
        $stmt = $pdo->prepare("
          INSERT INTO blogs (title, slug, content, excerpt, status, featured, author_id, published_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $slug, $content, $excerpt, $status, $featured, $_SESSION['uid'], $publishedAt]);
        $blogId = $pdo->lastInsertId();
        $message = 'Blog creado correctamente';
      }
      
      // Redirigir después de guardar (ahora SÍ podemos porque no se envió HTML aún)
      header("Location: blog.php?success=1&id=$blogId");
      exit;
      
    } catch (Throwable $e) {
      devlog('blog.save_failed', ['err' => $e->getMessage()]);
      $errors[] = 'Error al guardar el blog';
    }
  }
}

// Cargar blog existente para edición
if ($isEdit && empty($_POST)) {
  try {
    $stmt = $pdo->prepare("
      SELECT b.*, u.username, u.display_name
      FROM blogs b
      LEFT JOIN users u ON u.id = b.author_id
      WHERE b.id = ?
    ");
    $stmt->execute([$blogId]);
    $blog = $stmt->fetch();
    
    if (!$blog) {
      http_response_code(404);
      die('Blog no encontrado');
    }
    
    // Verificar permisos de edición
    require_ownership($blog['author_id']);
  } catch (Throwable $e) {
    devlog('blog.load_failed', ['err' => $e->getMessage()]);
    http_response_code(500);
    die('Error al cargar el blog');
  }
}

// Si hubo errores en POST, cargar los datos enviados para no perderlos
if (!empty($_POST) && !empty($errors)) {
  $blog = [
    'title' => $_POST['title'] ?? '',
    'content' => $_POST['content'] ?? '',
    'excerpt' => $_POST['excerpt'] ?? '',
    'featured' => isset($_POST['featured'])
  ];
}

// AHORA SÍ incluir el header (que genera HTML)
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section id="blog-editor">
    <div class="container">
      <div class="page-head">
        <h1><?php echo $isEdit ? 'Editar blog' : 'Escribir nuevo blog'; ?></h1>
        <p>Editor de markdown con vista previa en tiempo real</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="blog-editor-form">
        <!-- Token CSRF para protección -->
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="editor-header">
          <div class="editor-tabs">
            <button type="button" class="tab-btn active" data-tab="write">Escribir</button>
            <button type="button" class="tab-btn" data-tab="preview">Vista previa</button>
            <button type="button" class="tab-btn" data-tab="split">Dividido</button>
          </div>
          
          <div class="editor-actions">
            <button type="submit" class="btn" name="status" value="draft">Guardar borrador</button>
            <button type="submit" class="btn primary" name="status" value="published">Publicar</button>
          </div>
        </div>

        <div class="editor-content">
          <div class="editor-panel" id="write-panel">
            <div class="form-group">
              <label for="title">Título</label>
              <input class="input" type="text" id="title" name="title" 
                     value="<?php echo htmlspecialchars($blog['title'] ?? ''); ?>" 
                     placeholder="Título del blog" required>
            </div>

            <div class="form-group">
              <label for="excerpt">Resumen (opcional)</label>
              <textarea class="input" id="excerpt" name="excerpt" rows="2" 
                        placeholder="Breve descripción del blog..."><?php echo htmlspecialchars($blog['excerpt'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
              <label for="content">Contenido (Markdown)</label>
              <div class="markdown-toolbar">
                <button type="button" class="toolbar-btn" data-action="bold" title="Negrita">
                  <strong>B</strong>
                </button>
                <button type="button" class="toolbar-btn" data-action="italic" title="Cursiva">
                  <em>I</em>
                </button>
                <button type="button" class="toolbar-btn" data-action="heading" title="Título">
                  H
                </button>
                <button type="button" class="toolbar-btn" data-action="link" title="Enlace">
                  🔗
                </button>
                <button type="button" class="toolbar-btn" data-action="image" title="Imagen">
                  🖼️
                </button>
                <button type="button" class="toolbar-btn" data-action="list" title="Lista">
                  • Lista
                </button>
                <button type="button" class="toolbar-btn" data-action="quote" title="Cita">
                  " Cita
                </button>
                <button type="button" class="toolbar-btn" data-action="code" title="Código">
                  &lt;/&gt;
                </button>
                <button type="button" class="toolbar-btn" data-action="hr" title="Línea horizontal">
                  ─
                </button>
              </div>
              <textarea class="input markdown-editor" id="content" name="content" rows="20" 
                        placeholder="Escribe tu blog en markdown..."><?php echo htmlspecialchars($blog['content'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" name="featured" value="1" 
                       <?php echo ($blog['featured'] ?? false) ? 'checked' : ''; ?>>
                <span>Marcar como destacado</span>
              </label>
            </div>
          </div>

          <div class="editor-panel d-none" id="preview-panel">
            <div class="markdown-preview">
              <div class="preview-content" id="preview-content">
                <!-- Vista previa se genera aquí -->
              </div>
            </div>
          </div>

          <div class="editor-panel d-none" id="split-panel">
            <div class="split-container">
              <div class="split-pane">
                <h3>Editor</h3>
                <textarea class="input markdown-editor" id="content-split" rows="20" 
                          placeholder="Escribe tu blog en markdown..."><?php echo htmlspecialchars($blog['content'] ?? ''); ?></textarea>
              </div>
              <div class="split-pane">
                <h3>Vista previa</h3>
                <div class="markdown-preview">
                  <div class="preview-content" id="preview-content-split">
                    <!-- Vista previa se genera aquí -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
