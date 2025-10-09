<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security-config.php';
// Usar configuración centralizada de sesiones
start_secure_session();
if (empty($_SESSION['uid'])) { header('Location: login.php'); exit; }

$u = null;
try {
  $stmt = $pdo->prepare("SELECT username, email, role, status, display_name, bio, avatar_path, links_json, brand_color, created_at FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['uid']]);
  $u = $stmt->fetch();
} catch (Throwable $e) { devlog('profile fetch failed', ['err'=>$e->getMessage()]); }

// Obtener estadísticas del usuario
$stats = [
  'releases' => 0,
  'tracks' => 0,
  'blogs' => 0,
  'comments' => 0
];

if ($u) {
  try {
    // Contar releases
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM releases WHERE artist_id = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['uid']]);
    $stats['releases'] = $stmt->fetch()['count'] ?? 0;
    
    // Contar tracks
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tracks t JOIN releases r ON r.id = t.release_id WHERE r.artist_id = ? AND r.status = 'approved'");
    $stmt->execute([$_SESSION['uid']]);
    $stats['tracks'] = $stmt->fetch()['count'] ?? 0;
    
    // Contar blogs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blogs WHERE author_id = ? AND status = 'published'");
    $stmt->execute([$_SESSION['uid']]);
    $stats['blogs'] = $stmt->fetch()['count'] ?? 0;
    
    // Contar comentarios
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM release_comments WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['uid']]);
    $releaseComments = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_comments WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['uid']]);
    $blogComments = $stmt->fetch()['count'] ?? 0;
    
    $stats['comments'] = $releaseComments + $blogComments;
  } catch (Throwable $e) {
    devlog('profile stats failed', ['err'=>$e->getMessage()]);
  }
}

include __DIR__ . '/includes/header.php';
?>
<main class="container max-w-800 p-2">
  <div class="profile-header">
    <h1 class="title">Mi perfil</h1>
    <a href="profile-edit.php" class="btn">Editar perfil</a>
  </div>
  
  <?php if ($u): ?>
    <div class="profile-container">
      <!-- Información principal -->
      <div class="card profile-main">
        <div class="profile-info">
          <div class="avatar-section">
            <?php if (!empty($u['avatar_path'])): ?>
              <img src="<?php echo htmlspecialchars(u($u['avatar_path'])); ?>" alt="Avatar de <?php echo htmlspecialchars($u['display_name'] ?: $u['username']); ?>" class="avatar-img">
            <?php else: ?>
              <div class="avatar-placeholder">
                <?php echo strtoupper(($u['display_name'] ?: $u['username'])[0]); ?>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="profile-details">
            <h2 class="profile-name"><?php echo htmlspecialchars($u['display_name'] ?: $u['username']); ?></h2>
            <p class="profile-username">@<?php echo htmlspecialchars($u['username']); ?></p>
            
            <?php if (!empty($u['bio'])): ?>
              <p class="profile-bio"><?php echo nl2br(htmlspecialchars($u['bio'])); ?></p>
            <?php endif; ?>
            
            <div class="profile-meta">
              <span class="meta-item">
                <strong>Rol:</strong> <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
              </span>
              <span class="meta-item">
                <strong>Estado:</strong> 
                <span class="status status-<?php echo $u['status']; ?>">
                  <?php echo htmlspecialchars(ucfirst($u['status'])); ?>
                </span>
              </span>
              <span class="meta-item">
                <strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
              </span>
            </div>
            
            <?php if (!empty($u['links_json'])): ?>
              <?php $links = json_decode($u['links_json'], true); ?>
              <div class="profile-links">
                <?php if (!empty($links['web'])): ?>
                  <a href="<?php echo htmlspecialchars($links['web']); ?>" target="_blank" rel="noopener" class="profile-link">
                    🌐 Web
                  </a>
                <?php endif; ?>
                <?php if (!empty($links['ig'])): ?>
                  <a href="<?php echo htmlspecialchars($links['ig']); ?>" target="_blank" rel="noopener" class="profile-link">
                    📷 Instagram
                  </a>
                <?php endif; ?>
                <?php if (!empty($links['yt'])): ?>
                  <a href="<?php echo htmlspecialchars($links['yt']); ?>" target="_blank" rel="noopener" class="profile-link">
                    🎥 YouTube
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Estadísticas -->
      <div class="card profile-stats">
        <h3>Estadísticas</h3>
        <div class="stats-grid">
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['releases']; ?></div>
            <div class="stat-label">Lanzamientos</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['tracks']; ?></div>
            <div class="stat-label">Pistas</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['blogs']; ?></div>
            <div class="stat-label">Blogs</div>
          </div>
          <div class="stat-item">
            <div class="stat-number"><?php echo $stats['comments']; ?></div>
            <div class="stat-label">Comentarios</div>
          </div>
        </div>
      </div>
      
      <!-- Información de contacto -->
      <div class="card profile-contact">
        <h3>Información de contacto</h3>
        <div class="contact-info">
          <div class="contact-item">
            <strong>Email:</strong> 
            <span class="email-masked"><?php echo htmlspecialchars($u['email']); ?></span>
          </div>
        </div>
      </div>
      
      <!-- Acciones rápidas -->
      <div class="card profile-actions">
        <h3>Acciones rápidas</h3>
        <div class="action-buttons">
          <?php if (in_array($_SESSION['role'] ?? 'user', ['artist', 'mod', 'admin'])): ?>
          <a href="upload.php" class="btn primary">Subir música</a>
          <?php endif; ?>
          <a href="blog-editor.php" class="btn">Escribir blog</a>
          <a href="musica.php" class="btn">Ver mi música</a>
          <a href="blog.php" class="btn">Ver mis blogs</a>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="empty"><p>No se pudo cargar tu perfil.</p></div>
  <?php endif; ?>
</main>


<?php include __DIR__ . '/includes/footer.php'; ?>
