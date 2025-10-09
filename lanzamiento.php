<?php
require_once __DIR__ . '/includes/init.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Falta id.'); }

// Release + artista
$rel = null;
try {
  $stmt = $pdo->prepare("
    SELECT r.id, r.artist_id, r.title, r.cover_path, r.release_date, r.download_enabled, r.description, r.genre, r.status, r.created_at, r.updated_at, u.username
    FROM releases r
    JOIN users u ON u.id = r.artist_id
    WHERE r.id = :id
    LIMIT 1
  ");
  $stmt->execute(['id'=>$id]);
  $rel = $stmt->fetch();
} catch (Throwable $e) {
  devlog('release.fetch_failed', ['err'=>$e->getMessage(), 'id'=>$id]);
}
if (!$rel) { http_response_code(404); exit('Lanzamiento no encontrado.'); }

// Permisos de visualización
$role = $_SESSION['role'] ?? 'user';
$uid  = (int)($_SESSION['uid'] ?? 0);
$canView = ($rel['status']==='approved') || ($uid && ($uid===(int)$rel['artist_id'] || in_array($role,['mod','admin'],true)));
if (!$canView) { http_response_code(403); exit('Aún no está disponible.'); }

// Pistas
$tracks = [];
try {
  $q = $pdo->prepare("SELECT id, track_no, title, audio_path, audio_mime, duration_ms FROM tracks WHERE release_id = :rid ORDER BY track_no ASC");
  $q->execute(['rid'=>$rel['id']]);
  $tracks = $q->fetchAll();
} catch (Throwable $e) {
  devlog('release.tracks_failed', ['err'=>$e->getMessage(), 'id'=>$id]);
}

include __DIR__ . '/includes/header.php';
?>
<main class="site-main">
<div class="container">
<div class="release-hero">
  <div class="cover">
    <?php if (!empty($rel['cover_path'])): ?>
      <img src="<?php echo htmlspecialchars(u($rel['cover_path'])); ?>" alt="Portada de <?php echo htmlspecialchars($rel['title']); ?>">
    <?php endif; ?>
  </div>

  <div>
    <div class="page-head page-head-compact">
      <h1 class="page-title"><?php echo htmlspecialchars($rel['title']); ?></h1>
      <div class="release-actions">
        <?php if ((int)$rel['download_enabled'] === 1): ?>
          <a class="btn" href="<?php echo u('download_release.php'); ?>?id=<?php echo (int)$rel['id']; ?>">⬇︎ Descargar</a>
        <?php else: ?>
          <span class="btn muted">Solo streaming</span>
        <?php endif; ?>
        <button class="btn primary play-all"
                data-queue-id="rel-<?php echo (int)$rel['id']; ?>"
                data-release-id="<?php echo (int)$rel['id']; ?>"
                data-release-title="<?php echo htmlspecialchars($rel['title']); ?>"
                data-artist="@<?php echo htmlspecialchars($rel['username']); ?>"
                data-artist-id="<?php echo (int)$rel['artist_id']; ?>"
                data-cover="<?php echo htmlspecialchars(!empty($rel['cover_path']) ? u($rel['cover_path']) : ''); ?>">
          ▶︎ Reproducir
        </button>
      </div>
    </div>

    <div class="release-meta">
      <span><a href="perfil.php?id=<?php echo (int)$rel['artist_id']; ?>" class="profile-link">@<?php echo htmlspecialchars($rel['username']); ?></a></span>
      <?php if (!empty($rel['genre'])): ?><span>· <?php echo htmlspecialchars($rel['genre']); ?></span><?php endif; ?>
      <?php if (!empty($rel['release_date'])): ?>
        <span>· <time datetime="<?php echo htmlspecialchars($rel['release_date']); ?>">
          <?php echo date('d/m/Y', strtotime($rel['release_date'])); ?>
        </time></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($rel['description'])): ?>
      <div class="card m-0"><?php echo nl2br(htmlspecialchars($rel['description'])); ?></div>
    <?php endif; ?>
  </div>
</div>


  <section class="card card-p-0">
    <?php if (!$tracks): ?>
      <div class="empty"><p>Este lanzamiento todavía no tiene pistas.</p></div>
    <?php else: ?>
      <div class="tracks" data-queue-id="rel-<?php echo (int)$rel['id']; ?>">
        <?php foreach ($tracks as $i => $t): ?>
          <?php
            $sec = $t['duration_ms'] ? (int)round($t['duration_ms']/1000) : null;
            $mmss = $sec!==null ? sprintf('%d:%02d', floor($sec/60), $sec%60) : '—:—';
          ?>
          <div class="track track-inline">
            <button class="btn track-play"
                    title="Reproducir"
                    data-queue-id="rel-<?php echo (int)$rel['id']; ?>"
                    data-index="<?php echo (int)$i; ?>"
                    data-src="<?php echo htmlspecialchars(u($t['audio_path'])); ?>"
                    data-title="<?php echo htmlspecialchars($t['title']); ?>"
                    data-artist="@<?php echo htmlspecialchars($rel['username']); ?>"
                    data-artist-id="<?php echo (int)$rel['artist_id']; ?>"
                    data-release-title="<?php echo htmlspecialchars($rel['title']); ?>"
                    data-release-id="<?php echo (int)$rel['id']; ?>"
                    data-cover="<?php echo htmlspecialchars(!empty($rel['cover_path']) ? u($rel['cover_path']) : ''); ?>">
              ▶︎
            </button>
            <div class="text-ellipsis">
              <strong class="text-ellipsis">
                #<?php echo (int)$t['track_no']; ?> · <?php echo htmlspecialchars($t['title']); ?>
              </strong>
              <span class="muted"><?php echo $mmss; ?></span>
            </div>
            <a class="btn" href="<?php echo htmlspecialchars(u($t['audio_path'])); ?>" target="_blank" rel="noopener">Archivo</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Sección de comentarios -->
  <section class="card">
    <h2>Comentarios</h2>
    <!-- Formulario de comentarios fijo arriba (estilo YouTube) -->
    <?php if (!empty($_SESSION['uid'])): ?>
      <div class="comment-form comment-form-fixed">
        <form id="comment-form" method="post">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="type" value="release">
          <input type="hidden" name="target_id" value="<?php echo (int)$rel['id']; ?>">
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
</main>

<script src="js/vars.php?release_id=<?php echo (int)$rel['id']; ?>"></script>
<script src="js/comments.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
