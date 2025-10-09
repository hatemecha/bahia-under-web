<?php
require_once __DIR__ . '/includes/init.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) { 
  http_response_code(400); 
  include __DIR__ . '/includes/header.php';
  echo '<main class="container"><div class="empty"><p>ID de usuario inválido.</p></div></main>';
  include __DIR__ . '/includes/footer.php';
  exit; 
}

// Obtener información del usuario
$user = null;
try {
  $stmt = $pdo->prepare("
    SELECT id, username, display_name, bio, avatar_path, links_json, brand_color, role, status, created_at
    FROM users 
    WHERE id = ? AND status = 'active'
  ");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch();
} catch (Throwable $e) {
  error_log('public_profile.fetch_failed: ' . $e->getMessage() . ' (user_id: ' . $user_id . ')');
}

if (!$user) {
  http_response_code(404);
  include __DIR__ . '/includes/header.php';
  echo '<main class="container"><div class="empty"><p>Usuario no encontrado.</p></div></main>';
  include __DIR__ . '/includes/footer.php';
  exit;
}

// Obtener estadísticas del usuario
$stats = [
  'releases' => 0,
  'tracks' => 0,
  'blogs' => 0,
  'comments' => 0
];

try {
  // Contar releases
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM releases WHERE artist_id = ? AND status = 'approved'");
  $stmt->execute([$user_id]);
  $stats['releases'] = $stmt->fetch()['count'] ?? 0;
  
  // Contar tracks
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tracks t JOIN releases r ON r.id = t.release_id WHERE r.artist_id = ? AND r.status = 'approved'");
  $stmt->execute([$user_id]);
  $stats['tracks'] = $stmt->fetch()['count'] ?? 0;
  
  // Contar blogs
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blogs WHERE author_id = ? AND status = 'published'");
  $stmt->execute([$user_id]);
  $stats['blogs'] = $stmt->fetch()['count'] ?? 0;
  
  // Contar comentarios
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM release_comments WHERE user_id = ? AND status = 'active'");
  $stmt->execute([$user_id]);
  $releaseComments = $stmt->fetch()['count'] ?? 0;
  
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_comments WHERE user_id = ? AND status = 'active'");
  $stmt->execute([$user_id]);
  $blogComments = $stmt->fetch()['count'] ?? 0;
  
  $stats['comments'] = $releaseComments + $blogComments;
} catch (Throwable $e) {
  error_log('public_profile.stats_failed: ' . $e->getMessage() . ' (user_id: ' . $user_id . ')');
}

// Obtener últimos lanzamientos del usuario
$releases = [];
try {
  $stmt = $pdo->prepare("
    SELECT r.id, r.title, r.cover_path, r.release_date, r.type_derived, r.download_enabled,
           COUNT(t.id) as track_count
    FROM releases r
    LEFT JOIN tracks t ON t.release_id = r.id
    WHERE r.artist_id = ? AND r.status = 'approved'
    GROUP BY r.id
    ORDER BY r.release_date DESC, r.created_at DESC
    LIMIT 6
  ");
  $stmt->execute([$user_id]);
  $releases = $stmt->fetchAll();
} catch (Throwable $e) {
  error_log('public_profile.releases_failed: ' . $e->getMessage() . ' (user_id: ' . $user_id . ')');
}

// Obtener últimos blogs del usuario
$blogs = [];
try {
  $stmt = $pdo->prepare("
    SELECT id, title, excerpt, created_at, slug
    FROM blogs 
    WHERE author_id = ? AND status = 'published'
    ORDER BY created_at DESC
    LIMIT 3
  ");
  $stmt->execute([$user_id]);
  $blogs = $stmt->fetchAll();
} catch (Throwable $e) {
  error_log('public_profile.blogs_failed: ' . $e->getMessage() . ' (user_id: ' . $user_id . ')');
}

include __DIR__ . '/includes/header.php';
?>
<main class="container max-w-800 p-2">
  <div class="profile-header">
    <h1 class="title">Perfil de <?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h1>
  </div>
  
  <div class="profile-container">
    <!-- Información principal -->
    <div class="card profile-main">
      <div class="profile-info">
        <div class="avatar-section">
          <?php if (!empty($user['avatar_path'])): ?>
            <img src="<?php echo htmlspecialchars(u($user['avatar_path'])); ?>" alt="Avatar de <?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?>" class="avatar-img">
          <?php else: ?>
            <div class="avatar-placeholder">
              <?php echo strtoupper(($user['display_name'] ?: $user['username'])[0]); ?>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="profile-details">
          <h2 class="profile-name"><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></h2>
          <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>
          
          <?php if (!empty($user['bio'])): ?>
            <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
          <?php endif; ?>
          
          <div class="profile-meta">
            <span class="meta-item">
              <strong>Rol:</strong> <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
            </span>
            <span class="meta-item">
              <strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
            </span>
          </div>
          
          <?php if (!empty($user['links_json'])): ?>
            <?php $links = json_decode($user['links_json'], true); ?>
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
    
    <!-- Últimos lanzamientos -->
    <?php if (!empty($releases)): ?>
      <div class="card">
        <h3>Últimos lanzamientos</h3>
        <div class="grid releases">
          <?php foreach ($releases as $release): ?>
            <article class="card release-card col-4">
              <a class="cover" href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$release['id']; ?>">
                <?php if (!empty($release['cover_path'])): ?>
                  <img src="<?php echo htmlspecialchars(u($release['cover_path'])); ?>" alt="Portada de <?php echo htmlspecialchars($release['title']); ?>">
                <?php endif; ?>
              </a>
              <div class="release-info">
                <h4 class="release-title">
                  <a href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$release['id']; ?>">
                    <?php echo htmlspecialchars($release['title']); ?>
                  </a>
                </h4>
                <div class="meta">
                  <span><?php echo htmlspecialchars($release['type_derived']); ?></span>
                  <?php if (!empty($release['release_date'])): ?>
                    · <time datetime="<?php echo htmlspecialchars($release['release_date']); ?>">
                        <?php echo date('d/m/Y', strtotime($release['release_date'])); ?>
                      </time>
                  <?php endif; ?>
                </div>
                <div class="actions">
                  <span class="chip"><?php echo (int)$release['track_count']; ?> pistas</span>
                  <?php if ((int)$release['download_enabled'] === 1): ?>
                    <span class="badge">⬇︎ Descargable</span>
                  <?php else: ?>
                    <span class="badge muted">Solo streaming</span>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-2">
          <a href="<?php echo u('musica.php'); ?>?artist=<?php echo (int)$user_id; ?>" class="btn">Ver todos los lanzamientos</a>
        </div>
      </div>
    <?php endif; ?>
    
    <!-- Últimos blogs -->
    <?php if (!empty($blogs)): ?>
      <div class="card">
        <h3>Últimos blogs</h3>
        <div class="blog-list">
          <?php foreach ($blogs as $blog): ?>
            <article class="blog-item">
              <h4 class="blog-title">
                <a href="<?php echo u('blog-view.php'); ?>?id=<?php echo (int)$blog['id']; ?>">
                  <?php echo htmlspecialchars($blog['title']); ?>
                </a>
              </h4>
              <?php if (!empty($blog['excerpt'])): ?>
                <p class="blog-excerpt"><?php echo htmlspecialchars($blog['excerpt']); ?></p>
              <?php endif; ?>
              <div class="blog-meta">
                <time datetime="<?php echo htmlspecialchars($blog['created_at']); ?>">
                  <?php echo date('d/m/Y', strtotime($blog['created_at'])); ?>
                </time>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-2">
          <a href="<?php echo u('blog.php'); ?>?author=<?php echo (int)$user_id; ?>" class="btn">Ver todos los blogs</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
