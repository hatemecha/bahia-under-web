<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/security.php';

// Verificar autenticación y autorización
require_role(['mod', 'admin']);

$role = $_SESSION['role'] ?? 'user';
$uid  = $_SESSION['uid']  ?? 0;

// Acciones POST (aprobar / rechazar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rid  = isset($_POST['rid']) ? (int)$_POST['rid'] : 0;
  $act  = $_POST['action'] ?? '';
  $note = trim($_POST['review_notes'] ?? '');

  if ($rid > 0 && in_array($act, ['approve','reject','pending','toggle_dl','delete'], true)) {
    try {
      if (in_array($act, ['approve','reject','pending'], true)) {
        $map = ['approve'=>'approved','reject'=>'rejected','pending'=>'pending_review'];
        $status = $map[$act];
        $stmt = $pdo->prepare("UPDATE releases SET status = :st, reviewed_by = :rb, reviewed_at = NOW(), review_notes = :rn WHERE id = :id");
        $stmt->execute(['st'=>$status, 'rb'=>$uid, 'rn'=>($note?:null), 'id'=>$rid]);
      } elseif ($act === 'toggle_dl') {
        $pdo->prepare("UPDATE releases SET download_enabled = IF(download_enabled=1,0,1) WHERE id = :id")
            ->execute(['id'=>$rid]);
      } elseif ($act === 'delete') {
        $pdo->prepare("UPDATE releases SET status='rejected', review_notes = CONCAT(COALESCE(review_notes,''),' | eliminado por moderación'), reviewed_by=:rb, reviewed_at=NOW() WHERE id=:id")
            ->execute(['rb'=>$uid, 'id'=>$rid]);
      }
    } catch (Throwable $e) {
      devlog('moderation update failed', ['err'=>$e->getMessage(), 'rid'=>$rid]);
    }
  }
  header('Location: review.php'); exit;
}

$status = $_GET['status'] ?? 'pending';
$search = sanitize_search_query($_GET['q'] ?? '');
$rels = [];
try {
  $where = [];
  $params = [];
  if ($status !== 'all') {
    $map = ['pending'=>'pending_review','approved'=>'approved','rejected'=>'rejected'];
    $where[] = 'r.status = :st';
    $params[':st'] = $map[$status] ?? 'pending_review';
  }
  if ($search !== '') {
    $where[] = '(r.title LIKE :q OR u.username LIKE :q)';
    $params[':q'] = '%'.$search.'%';
  }
  $sql = "SELECT r.id, r.title, r.slug, r.genre, r.tags_csv, r.cover_path, r.download_enabled, r.status, r.created_at, u.username, u.id AS artist_id FROM releases r JOIN users u ON u.id = r.artist_id ".($where?('WHERE '.implode(' AND ',$where)):'')." ORDER BY r.created_at DESC LIMIT 100";
  $stmt = $pdo->prepare($sql);
  foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
  $stmt->execute();
  $rels = $stmt->fetchAll();
} catch (Throwable $e) {
  devlog('moderation list failed', ['err'=>$e->getMessage()]);
}

include __DIR__ . '/../includes/header.php';
?>
<main class="container max-w-1100 p-2">
  <h1 class="title">Moderación</h1>

  <div class="page-head">
    <form class="filters" method="get" action="review.php">
      <input class="input" type="search" name="q" placeholder="Buscar por título o @usuario" value="<?php echo htmlspecialchars($search); ?>" />
      <select class="input" name="status">
        <option value="pending"  <?php echo $status==='pending'?'selected':''; ?>>Pendientes</option>
        <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Aprobados</option>
        <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rechazados</option>
        <option value="all"      <?php echo $status==='all'?'selected':''; ?>>Todos</option>
      </select>
      <a class="btn" href="users.php">Gestionar usuarios</a>
      <button class="btn" type="submit">Filtrar</button>
    </form>
  </div>

  <?php if (!$rels): ?>
    <div class="empty"><p>No hay lanzamientos pendientes.</p></div>
  <?php else: ?>
    <div class="grid grid-align-start">
      <?php foreach ($rels as $r): ?>
        <?php
          // Pistas del release
          try {
            $q = $pdo->prepare("SELECT id, track_no, title, duration_ms FROM tracks WHERE release_id = ? ORDER BY track_no ASC");
            $q->execute([$r['id']]);
            $tracks = $q->fetchAll();
          } catch (Throwable $e) {
            $tracks = [];
            devlog('moderation tracks fetch failed', ['err'=>$e->getMessage(),'rid'=>$r['id']]);
          }
        ?>
        <article class="card col-12 d-grid grid-cols-cover gap-1">
          <div>
            <?php if (!empty($r['cover_path'])): ?>
              <img src="../<?php echo htmlspecialchars($r['cover_path']); ?>" alt="Portada" class="cover-120" />
            <?php else: ?>
              <div class="cover-placeholder"></div>
            <?php endif; ?>
          </div>

          <div>
            <h2 class="page-title">
              <?php echo htmlspecialchars($r['title']); ?>
              <span class="muted">por @<?php echo htmlspecialchars($r['username']); ?> · estado: <?php echo htmlspecialchars($r['status']); ?></span>
            </h2>
            <div class="muted mb-sm">
              Creado: <?php echo htmlspecialchars($r['created_at']); ?> ·
              Descargas: <?php echo $r['download_enabled'] ? 'permitidas' : 'solo streaming'; ?> ·
              <?php if ($r['genre']) echo 'Género: '.htmlspecialchars($r['genre']).' · '; ?>
              <?php if ($r['tags_csv']) echo 'Tags: '.htmlspecialchars($r['tags_csv']); ?>
            </div>

            <?php if ($tracks): ?>
              <div class="card card-elevated">
                <?php foreach ($tracks as $t): ?>
                  <div class="track-header">
                    <span class="badge">#<?php echo (int)$t['track_no']; ?></span>
                    <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                    <span class="muted">
                      <?php
                        if ($t['duration_ms']) {
                          $s = (int)round($t['duration_ms']/1000);
                          printf('(%d:%02d)', floor($s/60), $s%60);
                        } else echo '(—:—)';
                      ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty"><p>Este lanzamiento aún no tiene pistas.</p></div>
            <?php endif; ?>

            <form class="form form-inline" method="post" action="review.php?<?php echo http_build_query(['status'=>$status,'q'=>$search]); ?>">
              <input type="hidden" name="rid" value="<?php echo (int)$r['id']; ?>" />
              <div class="form-row">
                <label for="review_notes_<?php echo (int)$r['id']; ?>">Notas (opcional)</label>
                <textarea class="input" id="review_notes_<?php echo (int)$r['id']; ?>" name="review_notes" rows="2" placeholder="Motivo de rechazo, correcciones sugeridas, etc."></textarea>
              </div>
              <div class="form-actions form-actions-inline">
                <button class="btn" name="action" value="reject" type="submit">Rechazar</button>
                <button class="btn primary" name="action" value="approve" type="submit">Aprobar</button>
                <button class="btn" name="action" value="pending" type="submit">Volver a pendiente</button>
                <button class="btn" name="action" value="toggle_dl" type="submit">Alternar descargas</button>
                <button class="btn" name="action" value="delete" type="submit" onclick="return confirm('¿Seguro que deseas eliminar este lanzamiento?');">Eliminar</button>
                <a class="btn" href="../upload_tracks.php?rid=<?php echo (int)$r['id']; ?>">Editar pistas</a>
              </div>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
