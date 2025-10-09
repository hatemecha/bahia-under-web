<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
include __DIR__ . '/includes/header.php';

// Leer y validar filtros
$q       = sanitize_search_query($_GET['q'] ?? '');
$type    = validate_order_param($_GET['type'] ?? 'all', ['all' => true, 'single' => true, 'ep' => true, 'album' => true]);
$genre   = sanitize_input($_GET['genre'] ?? '');
$dl      = validate_order_param($_GET['dl'] ?? 'all', ['all' => true, 'yes' => true, 'no' => true]);
$order   = validate_order_param($_GET['orden'] ?? 'recent', ['recent' => true, 'oldest' => true, 'title' => true, 'tracks' => true]);
[$page, $perPage] = validate_pagination($_GET['p'] ?? 1, 9);
$offset  = ($page - 1) * $perPage;

// Para mantener valores en el form
function sel($a,$b){ return $a===$b?'selected':''; }

// Opciones de género (distintos)
$genres = [];
try {
  $gq = $pdo->query("SELECT DISTINCT genre FROM releases WHERE status = 'approved' AND genre IS NOT NULL AND genre <> '' ORDER BY genre ASC");
  $genres = $gq->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  devlog('music.genres_failed', ['err'=>$e->getMessage()]);
  $genres = [];
}

// Construir filtros dinámicos
$where   = ["r.status = 'approved'"];
$paramsW = [];
$having  = ["COUNT(t.id) >= 1"]; // sólo con pistas

if ($q !== '') {
  $where[] = "(r.title LIKE :q OR u.username LIKE :q)";
  $paramsW[':q'] = '%'.$q.'%';
}
if ($genre !== '') {
  $where[] = "r.genre = :genre";
  $paramsW[':genre'] = $genre;
}
if ($dl === 'yes')   { $where[] = "r.download_enabled = 1"; }
if ($dl === 'no')    { $where[] = "r.download_enabled = 0"; }

if ($type === 'single') { $having[] = "COUNT(t.id) = 1"; }
if ($type === 'ep')     { $having[] = "COUNT(t.id) BETWEEN 2 AND 5"; }
if ($type === 'album')  { $having[] = "COUNT(t.id) >= 6"; }

// Orden - Validación estricta para prevenir inyección SQL
$allowedOrders = [
  'recent' => 'sort_ts DESC',
  'oldest' => 'sort_ts ASC', 
  'title' => 'r.title ASC',
  'tracks' => 'track_count DESC, sort_ts DESC'
];
$orderSql = $allowedOrders[$order] ?? $allowedOrders['recent'];

// Query principal
$list = [];
$total = 0;

try {
  // Total (subquery por el HAVING)
  $sqlCount = "
    SELECT COUNT(*) AS c FROM (
      SELECT r.id
      FROM releases r
      JOIN users u ON u.id = r.artist_id
      LEFT JOIN tracks t ON t.release_id = r.id
      WHERE ".implode(' AND ', $where)."
      GROUP BY r.id
      HAVING ".implode(' AND ', $having)."
    ) x
  ";
  $stmtC = $pdo->prepare($sqlCount);
  foreach ($paramsW as $k=>$v) $stmtC->bindValue($k,$v);
  $stmtC->execute();
  $total = (int)$stmtC->fetchColumn();

  // Lista paginada
  $sql = "
    SELECT
      r.id, r.title, r.cover_path, r.release_date, r.download_enabled, r.artist_id,
      u.username,
      COUNT(t.id) AS track_count,
      CASE
        WHEN COUNT(t.id) = 1 THEN 'single'
        WHEN COUNT(t.id) BETWEEN 2 AND 5 THEN 'ep'
        WHEN COUNT(t.id) >= 6 THEN 'album'
        ELSE r.type
      END AS type_derived,
      COALESCE(r.reviewed_at, r.created_at) AS sort_ts
    FROM releases r
    JOIN users u ON u.id = r.artist_id
    LEFT JOIN tracks t ON t.release_id = r.id
    WHERE ".implode(' AND ', $where)."
    GROUP BY r.id, r.title, r.cover_path, r.release_date, r.download_enabled, r.artist_id, u.username, r.type, r.reviewed_at, r.created_at
    HAVING ".implode(' AND ', $having)."
    ORDER BY {$orderSql}
    LIMIT :lim OFFSET :off
  ";
  $stmt = $pdo->prepare($sql);
  foreach ($paramsW as $k=>$v) $stmt->bindValue($k,$v);
  $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
  $stmt->execute();
  $list = $stmt->fetchAll();
} catch (Throwable $e) {
  devlog('music.list_failed', ['err'=>$e->getMessage()]);
  $list = [];
  $total = 0;
}

$maxPage = max(1, (int)ceil($total / $perPage));
$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
?>

<main class="container p-2">
  <div class="page-head">
    <h1 class="page-title">Música</h1>
    <div class="filters">
      <div class="filters-header">
        <h2 class="filters-title">Filtros</h2>
        <button class="filters-toggle" type="button" aria-expanded="false">
          <span class="filters-toggle-text">Mostrar filtros</span>
          <span class="filters-toggle-icon">▼</span>
        </button>
      </div>
      <form class="filters-form" method="get" action="<?php echo htmlspecialchars(basename(__FILE__)); ?>">
        <div class="filter-group">
          <label for="search-input">Buscar</label>
          <input class="input" id="search-input" type="search" name="q" placeholder="Buscar por título o @usuario" value="<?php echo htmlspecialchars($q); ?>" />
        </div>

        <div class="filter-group">
          <label for="type-select">Tipo</label>
          <select class="input" id="type-select" name="type" aria-label="Tipo">
            <option value="all"   <?php echo sel($type,'all'); ?>>Todos</option>
            <option value="single"<?php echo sel($type,'single'); ?>>Single</option>
            <option value="ep"    <?php echo sel($type,'ep'); ?>>EP</option>
            <option value="album" <?php echo sel($type,'album'); ?>>Álbum</option>
          </select>
        </div>

        <div class="filter-group">
          <label for="genre-select">Género</label>
          <select class="input" id="genre-select" name="genre" aria-label="Género">
            <option value="" <?php echo $genre===''?'selected':''; ?>>Todos los géneros</option>
            <?php foreach ($genres as $g): ?>
              <option value="<?php echo htmlspecialchars($g); ?>" <?php echo sel($genre,$g); ?>>
                <?php echo htmlspecialchars($g); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label for="dl-select">Descargas</label>
          <select class="input" id="dl-select" name="dl" aria-label="Descargas">
            <option value="all" <?php echo sel($dl,'all'); ?>>Streaming + Descargas</option>
            <option value="yes" <?php echo sel($dl,'yes'); ?>>Solo descargables</option>
            <option value="no"  <?php echo sel($dl,'no');  ?>>Solo streaming</option>
          </select>
        </div>

        <div class="filter-group">
          <label for="order-select">Ordenar por</label>
          <select class="input" id="order-select" name="orden" aria-label="Orden">
            <option value="recent" <?php echo sel($order,'recent'); ?>>Más recientes</option>
            <option value="oldest" <?php echo sel($order,'oldest'); ?>>Más antiguos</option>
            <option value="title"  <?php echo sel($order,'title');  ?>>A → Z</option>
            <option value="tracks" <?php echo sel($order,'tracks'); ?>>Más pistas</option>
          </select>
        </div>

        <button class="btn" type="submit">Filtrar</button>
      </form>
    </div>
  </div>

  <?php if (!$list): ?>
    <div class="empty"><p>No hay lanzamientos con esos filtros.</p></div>
  <?php else: ?>
    <div class="grid releases blog-grid" role="list" aria-label="Lanzamientos">
      <?php foreach ($list as $r): ?>
<article class="card release-card col-4" role="listitem" data-artist-id="<?php echo (int)$r['artist_id']; ?>" data-release-id="<?php echo (int)$r['id']; ?>">
  <a class="cover" href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$r['id']; ?>">
    <?php if (!empty($r['cover_path'])): ?>
      <img src="<?php echo htmlspecialchars(u($r['cover_path'])); ?>" alt="Portada de <?php echo htmlspecialchars($r['title']); ?>">
    <?php endif; ?>
  </a>
  <div class="release-info">
    <h3 class="release-title">
      <a href="<?php echo u('lanzamiento.php'); ?>?id=<?php echo (int)$r['id']; ?>">
        <?php echo htmlspecialchars($r['title']); ?>
      </a>
    </h3>
    <div class="meta">
      <span><a href="perfil.php?id=<?php echo (int)$r['artist_id']; ?>" class="profile-link">@<?php echo htmlspecialchars($r['username']); ?></a></span>
      · <span><?php echo htmlspecialchars($r['type_derived']); ?></span>
      <?php if (!empty($r['release_date'])): ?>
        · <time datetime="<?php echo htmlspecialchars($r['release_date']); ?>">
            <?php echo date('d/m/Y', strtotime($r['release_date'])); ?>
          </time>
      <?php endif; ?>
    </div>
    <div class="actions">
      <span class="chip"><?php echo (int)$r['track_count']; ?> pistas</span>
      <?php if ((int)$r['download_enabled'] === 1): ?>
        <span class="badge">⬇︎ Descargable</span>
      <?php else: ?>
        <span class="badge muted">Solo streaming</span>
      <?php endif; ?>
    </div>
  </div>
</article>


      <?php endforeach; ?>
    </div>

    <?php if ($maxPage > 1): ?>
      <nav class="pagination" aria-label="Paginación">
        <?php
          // construir query sin p
          $qs = $_GET; unset($qs['p']);
          $base = htmlspecialchars(basename(__FILE__)).'?'.http_build_query($qs);
        ?>
        <?php if ($page > 1): ?>
          <a class="btn" href="<?php echo $base.'&p='.($page-1); ?>">&larr; Anterior</a>
        <?php endif; ?>
        <span class="muted">Página <?php echo $page; ?> de <?php echo $maxPage; ?></span>
        <?php if ($page < $maxPage): ?>
          <a class="btn" href="<?php echo $base.'&p='.($page+1); ?>">Siguiente &rarr;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
