<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/security.php';

$errors = [];
$notice = (isset($_GET['msg']) && $_GET['msg']==='existing')
          ? 'Ya existe una cuenta. Ingresá para continuar.' // TODO: Deberia hacer que si la cuenta ya existe, le avise pero lo loguee automaticamente.
          : '';

function ip_to_bin(?string $ip): ?string {
  if (!$ip) return null;
  $bin = @inet_pton($ip);
  return $bin === false ? null : $bin;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar CSRF token
  if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token de seguridad inválido. Recargá la página e intentá de nuevo.';
  }
  
  $login = sanitize_input($_POST['login'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $remember = !empty($_POST['remember']);

  if ($login !== '' && $login[0] === '@') $login = substr($login,1);

  if (!$errors) {
    try {
      // Usar el nuevo sistema de autenticación
      $user = authenticate_user($login, $pass);
      
      // Iniciar sesión segura
      start_secure_session();
      
      // Regenerar ID de sesión
      session_regenerate_id(true);
      
      // Establecer datos de sesión
      $_SESSION['uid'] = (int)$user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['login_time'] = time();
      
      // Log del login exitoso usando el sistema optimizado
      log_user_activity('login', $user['id'], [
        'username' => $user['username'],
        'role' => $user['role']
      ]);

      // Token "recordarme" si se solicita
      if ($remember) {
        try {
          create_remember_token($user['id']);
        } catch (Throwable $e) {
          devlog('remember token creation failed', ['err'=>$e->getMessage(), 'user'=>$user['id']]);
        }
      }

      header('Location: index.php');
      exit;
      
    } catch (Exception $e) {
      $errors[] = $e->getMessage();
      
      // Log del intento fallido
      log_security_event('login_failed', [
        'login' => $login,
        'error' => $e->getMessage()
      ]);
    } catch (Throwable $e) {
      devlog('login system error', ['err'=>$e->getMessage()]);
      $errors[] = 'Error del sistema. Intenta más tarde.';
    }
  }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>
<main class="container max-w-520 p-2">
  <h1 class="title">Ingresar</h1>

  <?php if ($notice): ?>
    <div class="card card-info">
      <?php echo htmlspecialchars($notice); ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="card card-error">
      <strong>Revisá:</strong>
      <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <form class="form" method="post" action="login.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <div class="form-row">
      <label for="login">Email o @usuario</label>
      <input class="input" id="login" name="login" type="text" required
             placeholder="vos@ejemplo.com o @tuusuario"
             value="<?php
               echo isset($_GET['prefill']) ? htmlspecialchars($_GET['prefill'])
                    : (isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '');
             ?>" />
    </div>

    <div class="form-row">
      <label for="password">Contraseña</label>
      <input class="input" id="password" name="password" type="password" required minlength="8" />
    </div>

    <div class="form-row-inline">
      <label class="chk">
        <input type="checkbox" name="remember" value="1" <?php echo !empty($_POST['remember'])?'checked':''; ?> />
        Recordarme en este equipo
      </label>
      <a class="link" href="register.php">Crear cuenta</a>
    </div>

    <div class="form-actions">
      <button class="btn primary" type="submit">Ingresar</button>
    </div>
  </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
