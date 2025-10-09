<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';
$role = $_SESSION['role'] ?? 'user';
$uid  = $_SESSION['uid']  ?? null;

$allowedRoles = ['artist','mod','admin'];
if (!$uid || !in_array($role, $allowedRoles, true)) {
  http_response_code(403);
  exit('No tenés permisos para subir música.');
}

$errors = [];
$okmsg  = '';

function slugify($s) {
  $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
  $s = strtolower(preg_replace('/[^a-z0-9]+/','-', $s));
  return trim($s, '-');
}
function ensure_dir($path) { if (!is_dir($path)) @mkdir($path, 0775, true); }
function image_to_jpg($srcPath, $dstPath, $maxW) {
  // Validación de seguridad: verificar que el archivo existe y es legible
  if (!is_file($srcPath) || !is_readable($srcPath)) {
    devlog('image_to_jpg: file not readable', ['path' => $srcPath]);
    return false;
  }
  
  // Obtener información de la imagen SIN suprimir errores
  $info = getimagesize($srcPath);
  if (!$info) {
    devlog('image_to_jpg: getimagesize failed', ['path' => $srcPath]);
    return false;
  }
  
  [$w, $h, $type] = $info;
  
  // Validación de seguridad: solo permitir tipos específicos
  if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
    devlog('image_to_jpg: invalid image type', ['type' => $type]);
    return false;
  }
  
  // Validación de seguridad: verificar dimensiones razonables
  if ($w <= 0 || $h <= 0 || $w > 10000 || $h > 10000) {
    devlog('image_to_jpg: invalid dimensions', ['width' => $w, 'height' => $h]);
    return false;
  }
  
  // Crear imagen desde el archivo según su tipo
  try {
    switch ($type) {
      case IMAGETYPE_JPEG:
        $im = @imagecreatefromjpeg($srcPath);
        break;
      case IMAGETYPE_PNG:
        $im = @imagecreatefrompng($srcPath);
        if ($im) {
          // Convertir paleta a true color y manejar transparencia
          imagepalettetotruecolor($im);
          imagealphablending($im, true);
          imagesavealpha($im, false);
        }
        break;
      default:
        return false;
    }
  } catch (Throwable $e) {
    devlog('image_to_jpg: failed to create image', ['error' => $e->getMessage()]);
    return false;
  }
  
  if (!$im) {
    devlog('image_to_jpg: imagecreatefrom failed');
    return false;
  }

  // Calcular nuevas dimensiones
  if ($w > $maxW) {
    $ratio = $maxW / $w;
    $nw = (int)($w * $ratio);
    $nh = (int)($h * $ratio);
  } else {
    $nw = $w;
    $nh = $h;
  }
  
  // Validar dimensiones calculadas
  if ($nw <= 0 || $nh <= 0) {
    imagedestroy($im);
    return false;
  }
  
  // Crear imagen de destino
  $dst = imagecreatetruecolor($nw, $nh);
  if (!$dst) {
    imagedestroy($im);
    devlog('image_to_jpg: imagecreatetruecolor failed');
    return false;
  }
  
  // Fondo blanco para imágenes con transparencia
  $white = imagecolorallocate($dst, 255, 255, 255);
  imagefill($dst, 0, 0, $white);
  
  // Redimensionar imagen
  $resampleOk = imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
  if (!$resampleOk) {
    imagedestroy($im);
    imagedestroy($dst);
    devlog('image_to_jpg: imagecopyresampled failed');
    return false;
  }
  
  // Guardar como JPEG con calidad 88 (buen balance calidad/tamaño)
  $ok = imagejpeg($dst, $dstPath, 88);
  
  // Liberar memoria
  imagedestroy($im);
  imagedestroy($dst);
  
  // Validación post-procesamiento: verificar que el archivo se creó correctamente
  if ($ok && is_file($dstPath)) {
    // Re-validar la imagen generada para asegurar que es válida
    $finalInfo = @getimagesize($dstPath);
    if (!$finalInfo) {
      @unlink($dstPath); // Eliminar archivo corrupto
      devlog('image_to_jpg: output validation failed');
      return false;
    }
    return true;
  }
  
  return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar CSRF token
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token de seguridad inválido. Recargá la página e intentá de nuevo.';
  }
  
  $title   = trim($_POST['title'] ?? '');
  $rtype   = $_POST['type'] === 'album' ? 'album' : 'single'; // EP se calcula luego
  $genre   = trim($_POST['genre'] ?? '');
  $tags    = trim($_POST['tags'] ?? '');
  $desc    = trim($_POST['description'] ?? '');
  $rdate   = trim($_POST['release_date'] ?? '');
  $dl      = !empty($_POST['download_enabled']) ? 1 : 0;

  if ($title === '') $errors[] = 'Título es obligatorio';

  // Validar carátula (opcional pero recomendable) con validación mejorada
  $coverOk = false; $coverTmp = null; $coverMime = null;
  if (!empty($_FILES['cover']['tmp_name'])) {
    $coverTmp = $_FILES['cover']['tmp_name'];
    $coverErr = $_FILES['cover']['error'];
    
    if ($coverErr === UPLOAD_ERR_OK) {
      // Validación completa con validate_uploaded_file() que incluye:
      // - Verificación de MIME type real (no basado en extensión)
      // - Verificación con getimagesize() para confirmar que es imagen válida
      // - Validación de dimensiones máximas
      // - Protección contra ataques de memoria
      $allowed_image_types = ['image/jpeg', 'image/png'];
      if (validate_uploaded_file($_FILES['cover'], $allowed_image_types, 5*1024*1024)) {
        // Obtener MIME type validado
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $coverMime = $fi->file($coverTmp);
        
        // Validación adicional: verificar que la imagen es procesable con GD
        $image_info = @getimagesize($coverTmp);
        if ($image_info && in_array($image_info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
          $coverOk = true;
        } else {
          $errors[] = 'La imagen no es procesable. Use JPG o PNG válido.';
        }
      } else {
        $errors[] = 'La carátula debe ser JPG o PNG válido (máx 5MB, máx 5000x5000px)';
      }
    } else if ($coverErr !== UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Error al subir carátula';
    }
  }

  if (!$errors) {
    try {
      // Insert preliminar para obtener ID
      $stmt = $pdo->prepare("
        INSERT INTO releases (artist_id, title, slug, type, description, genre, tags_csv, release_date, download_enabled, status)
        VALUES (:aid, :title, '', :type, :desc, :genre, :tags, :rdate, :dl, 'pending_review')
      ");
      $stmt->execute([
        ':aid'=>$uid, ':title'=>$title, ':type'=>$rtype, ':desc'=>($desc?:null),
        ':genre'=>($genre?:null), ':tags'=>($tags?:null),
        ':rdate'=>($rdate !== '' ? $rdate : null), ':dl'=>$dl
      ]);
      $rid = (int)$pdo->lastInsertId();

      // Slug único con ID
      $base = slugify($title);
      if ($base === '') $base = 'lanzamiento';
      $slug = $base.'-'.$rid;
      $upd = $pdo->prepare("UPDATE releases SET slug = :s WHERE id = :id");
      $upd->execute([':s'=>$slug, ':id'=>$rid]);

      // Guardar carátula si vino
      // --- Guardar carátula (con fallback si no hay GD) ---
if ($coverOk) {
  $mediaRoot = __DIR__.'/media';
  $coversDir = $mediaRoot.'/covers';
  ensure_dir($coversDir);

  $gdAvail = function_exists('imagecreatetruecolor') && (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng'));

  if ($gdAvail) {
    // 1) Generamos orig (JPG), 1000 y 300
    $orig = $coversDir."/{$rid}_orig.jpg";
    if (!image_to_jpg($coverTmp, $orig, 3000)) {
      throw new RuntimeException('cover: gd-convert-failed');
    }
    $c1000 = $coversDir."/{$rid}_1000.jpg";
    $c0300 = $coversDir."/{$rid}_300.jpg";
    if (!image_to_jpg($orig, $c1000, 1000) || !image_to_jpg($orig, $c0300, 300)) {
      throw new RuntimeException('cover: resize-failed');
    }
    $set = $pdo->prepare("UPDATE releases SET cover_path = :p WHERE id = :id");
    $set->execute([':p'=>"media/covers/{$rid}_1000.jpg", ':id'=>$rid]);
  } else {
    // 2) Fallback: sin GD, guardamos el archivo tal cual y lo usamos como cover
    $ext = ($coverMime === 'image/png') ? 'png' : 'jpg';
    $dst = $coversDir."/{$rid}_orig.{$ext}";
    if (!move_uploaded_file($coverTmp, $dst)) {
      throw new RuntimeException('cover: move-failed');
    }
    $set = $pdo->prepare("UPDATE releases SET cover_path = :p WHERE id = :id");
    $set->execute([':p'=>"media/covers/{$rid}_orig.{$ext}", ':id'=>$rid]);
  }
}


      header('Location: upload_tracks.php?rid='.$rid);
      exit;

    } catch (Throwable $e) {
      devlog('upload.create_release_failed', ['err'=>$e->getMessage()]);
      $errors[] = 'No se pudo crear el lanzamiento.';
    }
  }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container max-w-760 p-2">
  <h1 class="title">Subir música</h1>
  <p class="lede">Paso 1 — datos del lanzamiento. Después cargás las pistas.</p>

  <?php if ($errors): ?>
    <div class="card card-error">
      <strong>Revisá:</strong>
      <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="upload.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-row">
      <label for="title">Título</label>
      <input class="input" id="title" name="title" type="text" required maxlength="140" />
    </div>

    <div class="form-row">
      <label for="type">Tipo</label>
      <select class="input" id="type" name="type">
        <option value="single">Single</option>
        <option value="album">Álbum / EP</option>
      </select>
      <small class="help">La categoría final (single/EP/álbum) se ajusta por # de pistas.</small>
    </div>

    <div class="form-row">
      <label for="release_date">Fecha de publicación (opcional)</label>
      <input class="input" id="release_date" name="release_date" type="date" />
    </div>

    <div class="form-row">
      <label for="genre">Género (opcional)</label>
      <input class="input" id="genre" name="genre" type="text" maxlength="60" placeholder="punk, stoner, etc." />
    </div>

    <div class="form-row">
      <label for="tags">Tags (opcional, separados por coma)</label>
      <input class="input" id="tags" name="tags" type="text" placeholder="pretencioso, diy, ..." />
    </div>

    <div class="form-row">
      <label for="description">Descripción / créditos (opcional)</label>
      <textarea class="input" id="description" name="description" rows="4"></textarea>
    </div>

    <div class="form-row">
      <label for="cover">Carátula (JPG/PNG, máx 5MB)</label>
      <input class="input" id="cover" name="cover" type="file" accept=".jpg,.jpeg,.png" />
      <small class="help">Se generan tamaños 1000px y 300px automáticamente.</small>
    </div>

    <div class="form-row">
      <label class="chk">
        <input type="checkbox" name="download_enabled" value="1" checked />
        Permitir descargas del lanzamiento (gratis)
      </label>
      <small class="help">Si desmarcás, será “solo streaming”.</small>
    </div>

    <div class="form-actions">
      <button class="btn primary" type="submit">Guardar y continuar</button>
    </div>
  </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
