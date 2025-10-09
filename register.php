<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';

$errors = [];
$old = ['username'=>'','email'=>'','display_name'=>'','web'=>'','bio'=>'','is_artist'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar CSRF token
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token de seguridad inválido. Recargá la página e intentá de nuevo.';
  }
  
  $username = strtolower(sanitize_input($_POST['username'] ?? ''));
  $email    = sanitize_input($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $display  = sanitize_input($_POST['display_name'] ?? '', 80);
  $web      = sanitize_input($_POST['web'] ?? '');
  $bio      = sanitize_input($_POST['bio'] ?? '', 500);
  $isArtist = !empty($_POST['is_artist']);
  $terms    = !empty($_POST['accept_terms']);

  $old = ['username'=>$username,'email'=>$email,'display_name'=>$display,'web'=>$web,'bio'=>$bio,'is_artist'=>$isArtist?1:0];

  // Validaciones
  if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) $errors[] = 'Usuario inválido (3–30, minúsculas/números/_)';
  if (!validate_email($email))                        $errors[] = 'Email inválido';
  if (strlen($pass) < 8)                              $errors[] = 'La contraseña debe tener al menos 8 caracteres';
  if ($web && !validate_url($web))                    $errors[] = 'URL del sitio web inválida';
  if (!$terms)                                        $errors[] = 'Debés aceptar los Términos';

  // ¿Existe ya? -> redirigir a login con email prellenado
  if (!$errors) {
    try {
      $stmt = $pdo->prepare("SELECT id, email, username FROM users WHERE email = ? OR username = ? LIMIT 1");
      $stmt->execute([$email, $username]);
      if ($stmt->fetch()) {
        header('Location: login.php?prefill='.urlencode($email).'&msg=existing');
        exit;
      }
    } catch (Throwable $e) {
      devlog('register uniqueness check failed', ['err'=>$e->getMessage()]);
      $errors[] = 'Error temporal. Probá de nuevo.';
    }
  }

  if (!$errors) {
    try {
      $role = $isArtist ? 'artist' : 'user';
      $links = [];
      if ($web !== '') $links['web'] = $web;
      $linksJson = $links ? json_encode($links, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;

      $hash = password_hash($pass, PASSWORD_BCRYPT);

      $sql = "INSERT INTO users (username,email,password_hash,role,artist_verified_at,status,display_name,bio,links_json,accepted_terms_at)
              VALUES (:u,:e,:ph,:r,NULL,'active',:dn,:bio,:links,NOW())";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':u'=>$username, ':e'=>$email, ':ph'=>$hash, ':r'=>$role,
        ':dn'=>($display?:null), ':bio'=>($bio?:null), ':links'=>$linksJson
      ]);

      // Login automático
      $uid = (int)$pdo->lastInsertId();
      
      // La sesión ya fue iniciada por init.php con start_secure_session()
      // Solo regenerar el ID por seguridad después del registro
      session_regenerate_id(true);
      
      $_SESSION['uid'] = $uid;
      $_SESSION['username'] = $username;
      $_SESSION['role'] = $role;
      $_SESSION['login_time'] = time();

      // Log del registro exitoso usando el sistema optimizado
      log_user_activity('register', $uid, [
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'is_artist' => $isArtist
      ]);

      header('Location: index.php');
      exit;

    } catch (Throwable $e) {
      devlog('register insert failed', ['err'=>$e->getMessage()]);
      // Si colisiona por carrera: redirigir a login
      if ((int)$e->getCode() === 1062) {
        header('Location: login.php?prefill='.urlencode($email).'&msg=existing');
        exit;
      }
      $errors[] = 'No se pudo crear la cuenta. Intentá más tarde.';
    }
  }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container max-w-680 p-2">
  <h1 class="title">Crear cuenta</h1>
  <?php if ($errors): ?>
    <div class="card card-error">
      <strong>Revisá:</strong>
      <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="register.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-row">
      <label for="username">Usuario (para @)</label>
      <input class="input" id="username" name="username" type="text" required minlength="3" maxlength="30"
             pattern="[a-z0-9_]+" placeholder="ej: los_subs"
             value="<?php echo htmlspecialchars($old['username']); ?>" />
      <small class="help">Min 3, solo minúsculas / números / _</small>
    </div>

    <div class="form-row">
      <label for="email">Email</label>
      <input class="input" id="email" name="email" type="email" required placeholder="vos@ejemplo.com"
             value="<?php echo htmlspecialchars($old['email']); ?>" />
    </div>

    <div class="form-row">
      <label for="password">Contraseña</label>
      <input class="input" id="password" name="password" type="password" required minlength="8"
             placeholder="mínimo 8 caracteres" />
    </div>

    <div class="form-row">
      <label for="display_name">Nombre visible</label>
      <input class="input" id="display_name" name="display_name" type="text" maxlength="80"
             value="<?php echo htmlspecialchars($old['display_name']); ?>" />
    </div>

    <div class="form-row">
      <label for="web">Sitio web o link principal (opcional)</label>
      <input class="input" id="web" name="web" type="url" placeholder="https://tusitio.com o linktree"
             value="<?php echo htmlspecialchars($old['web']); ?>" />
    </div>

    <div class="form-row">
      <label for="bio">Biografía (opcional)</label>
      <textarea class="input" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($old['bio']); ?></textarea>
    </div>

    <div class="form-row">
      <label class="chk">
        <input type="checkbox" name="is_artist" value="1" <?php echo $old['is_artist']?'checked':''; ?> />
        Quiero cuenta de <strong>Artista</strong> (requiere verificación para publicar)
      </label>
    </div>

    <div class="form-row">
      <label class="chk">
        <input type="checkbox" name="accept_terms" value="1" required />
        Acepto los <a class="link" href="terminos.php" target="_blank" rel="noopener">Términos</a>
      </label>
    </div>

    <div class="form-actions">
      <button class="btn primary" type="submit">Crear cuenta</button>
      <a class="btn" href="login.php">Ya tengo cuenta</a>
    </div>
  </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
