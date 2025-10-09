<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_name('ugb_session');
  session_start();
}

if (empty($_SESSION['uid'])) { 
  header('Location: login.php'); 
  exit; 
}

$user_id = (int)$_SESSION['uid'];
$success_message = '';
$error_message = '';

// Obtener datos actuales del usuario
$user = null;
try {
  $stmt = $pdo->prepare("SELECT username, email, display_name, bio, avatar_path, links_json, brand_color FROM users WHERE id = ?");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch();
  
  if (!$user) {
    $error_message = 'Usuario no encontrado';
    devlog('profile_edit.user_not_found', [
      'uid' => $user_id,
      'session_uid' => $_SESSION['uid'] ?? 'NO_SESSION'
    ]);
  }
} catch (Throwable $e) {
  devlog('profile_edit.fetch_failed', ['err' => $e->getMessage()]);
  $error_message = 'Error al cargar los datos del perfil';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $display_name = trim($_POST['display_name'] ?? '');
  $bio = trim($_POST['bio'] ?? '');
  $web = trim($_POST['web'] ?? '');
  $instagram = trim($_POST['instagram'] ?? '');
  $youtube = trim($_POST['youtube'] ?? '');
  
  // Validaciones
  if (strlen($display_name) > 80) {
    $error_message = 'El nombre no puede tener más de 80 caracteres';
  } elseif (strlen($bio) > 500) {
    $error_message = 'La biografía no puede tener más de 500 caracteres';
  } else {
    // Validar URLs
    $urls = ['web' => $web, 'instagram' => $instagram, 'youtube' => $youtube];
    foreach ($urls as $type => $url) {
      if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        $error_message = "La URL de $type no es válida";
        break;
      }
    }
    
    if (!$error_message) {
      try {
        // Preparar links JSON
        $links = [];
        if (!empty($web)) $links['web'] = $web;
        if (!empty($instagram)) $links['ig'] = $instagram;
        if (!empty($youtube)) $links['yt'] = $youtube;
        
        $links_json = !empty($links) ? json_encode($links) : null;
        
        // Actualizar perfil
        $stmt = $pdo->prepare("
          UPDATE users 
          SET display_name = ?, bio = ?, links_json = ?, updated_at = CURRENT_TIMESTAMP 
          WHERE id = ?
        ");
        $stmt->execute([$display_name ?: null, $bio ?: null, $links_json, $user_id]);
        
        $success_message = 'Perfil actualizado correctamente';
        
        // Recargar datos del usuario
        $stmt = $pdo->prepare("SELECT username, email, display_name, bio, avatar_path, links_json, brand_color FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
      } catch (Throwable $e) {
        devlog('profile_edit.update_failed', ['err' => $e->getMessage()]);
        $error_message = 'Error al actualizar el perfil';
      }
    }
  }
}

// Procesar subida de avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
  $file = $_FILES['avatar'];
  
  // Validar tipo de archivo usando finfo para mayor seguridad
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime_type = $finfo->file($file['tmp_name']);
  $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  
  if (!in_array($mime_type, $allowed_types)) {
    $error_message = 'Solo se permiten archivos JPG, PNG, GIF y WebP';
  } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
    $error_message = 'El archivo es demasiado grande (máximo 5MB)';
  } else {
    try {
      // Crear directorio si no existe
      $avatar_dir = __DIR__ . '/media/avatars';
      if (!is_dir($avatar_dir)) {
        mkdir($avatar_dir, 0755, true);
      }
      
      // Generar nombre único
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = $user_id . '_' . time() . '.' . $extension;
      $filepath = $avatar_dir . '/' . $filename;
      
      // Mover archivo
      if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Eliminar avatar anterior si existe
        if (!empty($user['avatar_path'])) {
          $old_path = __DIR__ . '/' . $user['avatar_path'];
          if (file_exists($old_path)) {
            unlink($old_path);
          }
        }
        
        // Actualizar en base de datos
        $relative_path = 'media/avatars/' . $filename;
        $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $stmt->execute([$relative_path, $user_id]);
        
        $user['avatar_path'] = $relative_path;
        $success_message = 'Avatar actualizado correctamente';
      } else {
        $error_message = 'Error al subir el archivo';
      }
    } catch (Throwable $e) {
      devlog('profile_edit.avatar_failed', ['err' => $e->getMessage()]);
      $error_message = 'Error al actualizar el avatar';
    }
  }
}

// Decodificar links JSON
$links = [];
if ($user && !empty($user['links_json'])) {
  $links = json_decode($user['links_json'], true) ?: [];
}

include __DIR__ . '/includes/header.php';
?>

<main class="container max-w-800 p-2">
  <div class="profile-header">
    <h1 class="title">Editar perfil</h1>
    <a href="mi-perfil.php" class="btn">← Volver al perfil</a>
  </div>
  
  <?php if (!$user): ?>
    <div class="alert alert-error">No se pudo cargar la información del usuario.</div>
  <?php else: ?>
  
  <?php if ($error_message): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>
  
  <?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>
  
  <div class="profile-edit-container">
    <!-- Avatar -->
    <div class="card">
      <h3>Foto de perfil</h3>
      <div class="avatar-section">
        <div class="current-avatar">
          <?php if (!empty($user['avatar_path'])): ?>
            <img src="<?php echo htmlspecialchars(u($user['avatar_path'])); ?>" alt="Avatar actual" class="avatar-preview">
          <?php else: ?>
            <div class="avatar-placeholder large">
              <?php echo strtoupper(($user['display_name'] ?? $user['username'] ?? 'U')[0]); ?>
            </div>
          <?php endif; ?>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="avatar-form">
          <div class="form-group">
            <label for="avatar">Cambiar foto de perfil</label>
            <input type="file" id="avatar" name="avatar" accept="image/*" class="input">
            <small>Formatos: JPG, PNG, GIF, WebP. Máximo 5MB.</small>
          </div>
          <button type="submit" class="btn">Actualizar foto</button>
        </form>
      </div>
    </div>
    
    <!-- Personalización -->
    <div class="card profile-personalization">
      <h3>Personalización</h3>
      <div class="personalization-content">
        <div class="color-picker-section">
          <label for="brand-color" class="form-label">Color de marca:</label>
          <div class="color-picker-container">
            <input type="color" id="brand-color" name="brand_color" value="<?php echo htmlspecialchars($user['brand_color'] ?: '#a78bfa'); ?>" class="color-input">
            <span class="color-preview" id="color-preview" data-color="<?php echo htmlspecialchars($user['brand_color'] ?: '#a78bfa'); ?>"></span>
            <span class="color-value" id="color-value"><?php echo htmlspecialchars($user['brand_color'] ?: '#a78bfa'); ?></span>
          </div>
          <div class="color-actions">
            <button type="button" id="save-color" class="btn small primary">Guardar color</button>
            <button type="button" id="reset-color" class="btn small">Restablecer</button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Información básica -->
    <div class="card">
      <h3>Información básica</h3>
      <form method="post" class="profile-form">
        <div class="form-group">
          <label for="username">Usuario</label>
          <input type="text" id="username" value="@<?php echo htmlspecialchars($user['username']); ?>" class="input" disabled>
          <small>El nombre de usuario no se puede cambiar</small>
        </div>
        
        <div class="form-group">
          <label for="display_name">Nombre para mostrar</label>
          <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" class="input" maxlength="80">
          <small>Opcional. Si no se especifica, se usará el nombre de usuario</small>
        </div>
        
        <div class="form-group">
          <label for="bio">Biografía</label>
          <textarea id="bio" name="bio" class="input" rows="4" maxlength="500" placeholder="Cuéntanos algo sobre ti..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
          <small>Máximo 500 caracteres</small>
        </div>
        
        <button type="submit" class="btn primary">Guardar cambios</button>
      </form>
    </div>
    
    <!-- Enlaces -->
    <div class="card">
      <h3>Enlaces</h3>
      <form method="post" class="links-form">
        <div class="form-group">
          <label for="web">Sitio web</label>
          <input type="url" id="web" name="web" value="<?php echo htmlspecialchars($links['web'] ?? ''); ?>" class="input" placeholder="https://tu-sitio.com">
        </div>
        
        <div class="form-group">
          <label for="instagram">Instagram</label>
          <input type="url" id="instagram" name="instagram" value="<?php echo htmlspecialchars($links['ig'] ?? ''); ?>" class="input" placeholder="https://instagram.com/tu-usuario">
        </div>
        
        <div class="form-group">
          <label for="youtube">YouTube</label>
          <input type="url" id="youtube" name="youtube" value="<?php echo htmlspecialchars($links['yt'] ?? ''); ?>" class="input" placeholder="https://youtube.com/@tu-canal">
        </div>
        
        <button type="submit" class="btn primary">Guardar enlaces</button>
      </form>
    </div>
  </div>
</main>

<script src="js/profile-edit.js"></script>

<?php endif; // Cerrar el if (!$user) ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
