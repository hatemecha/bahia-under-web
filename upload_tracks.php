<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';
$role = $_SESSION['role'] ?? 'user';
$uid  = $_SESSION['uid']  ?? null;
$allowedRoles = ['artist','mod','admin'];
if (!$uid || !in_array($role, $allowedRoles, true)) {
  http_response_code(403); exit('No tenés permisos para subir.');
}

$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
if ($rid <= 0) { http_response_code(400); exit('Falta release.'); }

// Cargar release y verificar ownership (si es artist)
$stmt = $pdo->prepare("SELECT id, artist_id, title, type, status, download_enabled FROM releases WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$rid]);
$rel = $stmt->fetch();
if (!$rel) { http_response_code(404); exit('Lanzamiento no encontrado.'); }
if ($role === 'artist' && (int)$rel['artist_id'] !== (int)$uid) {
  http_response_code(403); exit('No podés editar este lanzamiento.');
}

$errors = [];
// getID3 (si está instalado)
if (is_file(__DIR__.'/vendor/autoload.php')) {
  require_once __DIR__.'/vendor/autoload.php';
}

function ensure_dir($p){ if(!is_dir($p)) @mkdir($p,0775,true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar CSRF token
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token de seguridad inválido. Recargá la página e intentá de nuevo.';
  }
  
  $title = trim($_POST['title'] ?? '');
  $trackNo = isset($_POST['track_no']) ? (int)$_POST['track_no'] : 0;
  $lyrics = trim($_POST['lyrics'] ?? '');

  if ($title === '') $errors[] = 'Título de pista obligatorio';

  if (empty($_FILES['audio']['tmp_name'])) {
    $errors[] = 'Seleccioná un archivo de audio';
  } else {
    $err  = $_FILES['audio']['error'];
    $size = (int)$_FILES['audio']['size'];
    if ($err !== UPLOAD_ERR_OK || $size <= 0) {
      $errors[] = 'Error al subir el audio';
    } else {
      // Usar validación mejorada de archivos de audio
      $allowed_audio_types = ['audio/mpeg','audio/mp3','audio/x-flac','audio/flac','audio/wav','audio/x-wav'];
      if (!validate_uploaded_file($_FILES['audio'], $allowed_audio_types, 50*1024*1024)) { // 50MB para audio
        $errors[] = 'Formato no soportado o archivo inválido. Usá MP3, WAV o FLAC válidos.';
      } else {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($_FILES['audio']['tmp_name']);
      }
    }
  }

  // track_no automático si no vino
  if (!$errors && $trackNo <= 0) {
    $q = $pdo->prepare("SELECT COALESCE(MAX(track_no),0)+1 AS nextn FROM tracks WHERE release_id = :rid");
    $q->execute([':rid'=>$rid]);
    $trackNo = (int)$q->fetchColumn();
    if ($trackNo <= 0) $trackNo = 1;
  }

  if (!$errors) {
    try {
      // Destino
      $mediaRoot = __DIR__.'/media';
      $audioDir  = $mediaRoot.'/audio/'.$rel['artist_id'].'/'.$rel['id'];
      ensure_dir($audioDir);

      // Nombre archivo seguro
      $origName = $_FILES['audio']['name'];
      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
      if ($mime === 'audio/mpeg' || $mime === 'audio/mp3') $ext = 'mp3';
      if (in_array($mime, ['audio/flac','audio/x-flac'], true)) $ext = 'flac';
      if (in_array($mime, ['audio/wav','audio/x-wav'], true)) $ext = 'wav';

      // Usar función segura para generar nombre de archivo
      $safe_filename = generate_safe_filename($origName, 'track_' . $trackNo);
      $destRel = "media/audio/{$rel['artist_id']}/{$rel['id']}/{$safe_filename}";
      $destAbs = __DIR__.'/'.$destRel;

      if (!move_uploaded_file($_FILES['audio']['tmp_name'], $destAbs)) {
        throw new RuntimeException('move_uploaded_file failed');
      }

      // Duración (getID3 si está)
      $durationMs = null;
      if (class_exists('getID3')) {
        try {
          $getID3 = new getID3;
          $info = $getID3->analyze($destAbs);
          if (!empty($info['playtime_seconds'])) {
            $durationMs = (int)round($info['playtime_seconds'] * 1000);
          }
        } catch (Throwable $e) {
          devlog('getid3.analyze_failed', ['err'=>$e->getMessage()]);
        }
      }

      // Insert pista
      $ins = $pdo->prepare("
        INSERT INTO tracks (release_id, track_no, title, audio_path, audio_mime, duration_ms, lyrics)
        VALUES (:rid,:no,:title,:path,:mime,:dur,:lyrics)
      ");
      $ins->execute([
        ':rid'=>$rel['id'], ':no'=>$trackNo, ':title'=>$title,
        ':path'=>$destRel, ':mime'=>$mime, ':dur'=>$durationMs, ':lyrics'=>($lyrics?:null)
      ]);

      header('Location: upload_tracks.php?rid='.$rel['id']);
      exit;

    } catch (Throwable $e) {
      devlog('upload.add_track_failed', ['err'=>$e->getMessage()]);
      $errors[] = 'No se pudo guardar la pista.';
    }
  }
}

// Listar pistas ya subidas
$list = $pdo->prepare("SELECT id, track_no, title, audio_path, duration_ms FROM tracks WHERE release_id = :rid ORDER BY track_no ASC");
$list->execute([':rid'=>$rid]);
$tracks = $list->fetchAll();

// Derivar tipo según cantidad (para mostrar)
$cnt = count($tracks);
$derivedType = $cnt === 1 ? 'single' : ($cnt >= 6 ? 'album' : ($cnt >= 2 ? 'ep' : $rel['type']));
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container max-w-860 p-2">
  <h1 class="title">Pistas — <?php echo htmlspecialchars($rel['title']); ?></h1>
  <p class="lede">Paso 2 — subí cada pista. Este lanzamiento se mostrará cuando un moderador lo apruebe.</p>

  <?php if ($errors): ?>
    <div class="card card-error">
      <strong>Revisá:</strong>
      <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <div class="card mb-1">
    <strong>Estado:</strong>
    <span class="badge"><?php echo htmlspecialchars($rel['status']); ?></span>
    <span class="badge">Descargas: <?php echo $rel['download_enabled'] ? 'Permitidas' : 'Solo streaming'; ?></span>
    <span class="badge">Tipo derivado: <?php echo $derivedType; ?></span>
  </div>

  <section class="section-mb">
    <h2 class="page-title">Pistas existentes</h2>
    <?php if (!$tracks): ?>
      <div class="empty"><p>Todavía no subiste pistas.</p></div>
    <?php else: ?>
      <div class="grid" role="list" aria-label="Pistas">
        <?php foreach ($tracks as $t): ?>
          <article class="card col-12 d-grid grid-cols-4-auto gap-sm items-center" role="listitem">
            <div class="badge">#<?php echo (int)$t['track_no']; ?></div>
            <div>
              <strong><?php echo htmlspecialchars($t['title']); ?></strong>
              <div class="muted">
                <?php
                  if ($t['duration_ms']) {
                    $sec = (int)round($t['duration_ms']/1000);
                    printf('%d:%02d', floor($sec/60), $sec%60);
                  } else {
                    echo '—:—';
                  }
                ?>
              </div>
            </div>
            <a class="btn" href="<?php echo htmlspecialchars($t['audio_path']); ?>" target="_blank" rel="noopener">Ver archivo</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <h2 class="page-title">Agregar nueva pista</h2>
  <form class="form" method="post" action="upload_tracks.php?rid=<?php echo (int)$rid; ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-row">
      <label for="title">Título</label>
      <input class="input" id="title" name="title" type="text" required maxlength="140" />
    </div>

    <div class="form-row-inline">
      <div class="form-row form-row-flex-1">
        <label for="track_no">Nº de pista (opcional)</label>
        <input class="input" id="track_no" name="track_no" type="number" min="1" step="1" />
      </div>
      <div class="form-row form-row-flex-2">
        <label for="audio">Archivo de audio (MP3/WAV/FLAC)</label>
        <input class="input" id="audio" name="audio" type="file" required accept=".mp3,.wav,.flac,audio/*" />
      </div>
    </div>

    <div class="form-row">
      <label for="lyrics">Letra (opcional)</label>
      <textarea class="input" id="lyrics" name="lyrics" rows="3"></textarea>
    </div>

    <div class="form-actions">
      <button class="btn primary" type="submit">Subir pista</button>
      <a class="btn" href="index.php">Salir</a>
    </div>
  </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
