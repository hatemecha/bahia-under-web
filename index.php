<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
include __DIR__ . '/includes/header.php';

// Traer últimos lanzamientos (aprobados) con al menos 1 pista
$items = [];
if ($pdo) {
  try {
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
      WHERE r.status = 'approved'
      GROUP BY r.id, r.title, r.cover_path, r.release_date, r.download_enabled, r.artist_id, u.username, r.type, r.reviewed_at, r.created_at
      HAVING COUNT(t.id) >= 1
      ORDER BY sort_ts DESC
      LIMIT 3
    ";
    $stmt = $pdo->query($sql);
    $items = $stmt->fetchAll();
  } catch (Throwable $e) {
    devlog('home.list_failed', ['err'=>$e->getMessage()]);
    $items = [];
  }
} else {
  devlog('home.list_failed', ['err'=>'Database connection not available']);
  $items = [];
}

// Próximos eventos (activos)
$events = [];
if ($pdo) {
  try {
    $stmt = $pdo->query("SELECT id, title, event_dt, place_name, maps_url, flyer_path FROM events WHERE status = 'active' AND event_dt >= NOW() ORDER BY event_dt ASC LIMIT 4");
    $events = $stmt->fetchAll();
  } catch (Throwable $e) {
    devlog('home.events_failed', ['err'=>$e->getMessage()]);
    $events = [];
  }
} else {
  devlog('home.events_failed', ['err'=>'Database connection not available']);
  $events = [];
}
?>

<main>
  <section class="hero">
    <div class="container wrap">
      <div>
        <h1 class="title">
          Compartí tu música — escuchá y descargá <span class="brand-color">gratis</span> sin <span class="brand-color">trámites</span> ni<span class="brand-color"> suscripciones</span>.
        </h1>
        <p class="lede">Hecho por y para la escena de Bahía Blanca. Streaming, descargas, blog y agenda.</p>
        <ul class="bullets">
          <li>• Subí lanzamientos (singles, EPs, LPs)</li>
          <li>• Descargá o escuchá en tu navegador</li>
          <li>• Interactuá y hacé conexiones</li>
        </ul>
        <div class="cta">
          <?php if (in_array($_SESSION['role'] ?? 'user', ['artist', 'mod', 'admin'])): ?>
          <a class="btn primary" href="<?php echo u('upload.php'); ?>">Subir música</a>
          <?php endif; ?>
          <a class="btn" href="<?php echo u('musica.php'); ?>">Explorar lanzamientos</a>
        </div>
      </div>

      <aside class="card aside-card">
        <h3>¿Por qué?</h3>
        <div>
          <span class="tag">Gratis</span>
          <span class="tag">Sin trámites ni suscripciones</span>
          <span class="tag">Calidad</span>
          <span class="tag">Sin interrupciones</span>
        </div>
        <p class="aside-text">
          Podés subir música sin necesidad de hacer trámites ni suscripciones.
          <span class="brand-color">¿Hace falta más?</span>
        </p>
      </aside>
    </div>
  </section>

  <section id="lanzamientos">
    <div class="container">
      <div class="section-title">
        <h2>Últimos lanzamientos</h2>
        <a class="btn" href="<?php echo u('musica.php'); ?>">Ver todo</a>
      </div>

      <div class="grid releases" role="list" aria-label="Lanzamientos recientes">
  <?php if (!$items): ?>
    <div class="empty"><p>No hay lanzamientos disponibles todavía.</p></div>
  <?php else: ?>
    <?php foreach ($items as $r): ?>
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
  <?php endif; ?>
</div>

    </div>
  </section>

  <section id="eventos">
    <div class="container">
      <div class="section-title">
        <h2>Próximos eventos</h2>
        <a class="btn" href="<?php echo u('eventos.php'); ?>">Ver agenda</a>
      </div>
      <?php if (!$events): ?>
        <div class="empty"><p>No hay eventos próximos por ahora.</p></div>
      <?php else: ?>
        <div class="events" role="list" aria-label="Eventos próximos">
          <?php foreach ($events as $ev): ?>
            <article class="event-card" role="listitem">
              <a class="event-flyer<?php echo empty($ev['flyer_path']) ? ' ph' : ''; ?>" href="<?php echo u('eventos.php'); ?>">
                <?php if (!empty($ev['flyer_path'])): ?>
                  <img src="<?php echo htmlspecialchars(u($ev['flyer_path'])); ?>" alt="Flyer de <?php echo htmlspecialchars($ev['title']); ?>">
                <?php endif; ?>
              </a>
              <div>
                <h3 class="event-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                <p class="event-desc">
                  <time datetime="<?php echo htmlspecialchars($ev['event_dt']); ?>"><?php echo date('d/m/Y H:i', strtotime($ev['event_dt'])); ?></time>
                  <?php if (!empty($ev['place_name'])): ?> · <?php echo htmlspecialchars($ev['place_name']); ?><?php endif; ?>
                  <?php if (!empty($ev['maps_url'])): ?> · <a class="btn" href="<?php echo htmlspecialchars($ev['maps_url']); ?>" target="_blank" rel="noopener">Mapa</a><?php endif; ?>
                </p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
