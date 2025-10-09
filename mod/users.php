<?php
require_once __DIR__ . '/../includes/init.php';

// Verificar autenticación y autorización
require_role(['mod', 'admin']);

$role = $_SESSION['role'] ?? 'user';
$uid  = (int)($_SESSION['uid'] ?? 0);

// Acciones POST: cambiar rol, cambiar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $targetId   = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
  $act        = $_POST['action'] ?? '';
  $roleValue  = $_POST['role_value']   ?? '';
  $stateValue = $_POST['status_value'] ?? '';

  // No permitir que un mod/admin se edite a sí mismo por accidente
  if ($targetId > 0 && $targetId !== $uid) {
    try {
      if ($act === 'set_role' && in_array($roleValue, ['admin','mod','artist','user'], true)) {
        // Solo un admin puede ascender/destituir a admin
        if ($role !== 'admin' && $roleValue === 'admin') {
          throw new RuntimeException('Solo admin puede asignar rol admin');
        }
        $stmt = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
        $stmt->execute(['r'=>$roleValue, 'id'=>$targetId]);
        
        // Log de cambio de rol exitoso
        log_admin_activity('role_change', $targetId, [
          'new_role' => $roleValue,
          'admin_role' => $role
        ]);
      } elseif ($act === 'set_status' && in_array($stateValue, ['active','banned','pending'], true)) {
        $stmt = $pdo->prepare("UPDATE users SET status = :s WHERE id = :id");
        $stmt->execute(['s'=>$stateValue, 'id'=>$targetId]);
        
        // Log de cambio de estado exitoso
        log_admin_activity('status_change', $targetId, [
          'new_status' => $stateValue,
          'admin_role' => $role
        ]);
      }
    } catch (Throwable $e) {
      log_system_error('user_management', $e->getMessage(), [
        'target_id' => $targetId,
        'action' => $act,
        'admin_id' => $uid
      ]);
    }
  }
  // Redirigir conservando filtros
  $qs = $_GET; unset($qs['uid']);
  header('Location: users.php'.(empty($qs)?'':'?'.http_build_query($qs))); exit;
}

// Filtros de listado
$q       = trim($_GET['q'] ?? '');
$fRole   = $_GET['role']   ?? 'all';
$fStatus = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$users = [];
$total = 0;
try {
  $where = [];
  $params = [];
  if ($q !== '') {
    $where[] = '(username LIKE :q OR email LIKE :q OR display_name LIKE :q)';
    $params[':q'] = '%'.$q.'%';
  }
  if ($fRole !== 'all')   { $where[] = 'role = :role';     $params[':role']   = $fRole; }
  if ($fStatus !== 'all') { $where[] = 'status = :status'; $params[':status'] = $fStatus; }

  $sqlBase = 'FROM users'.($where ? (' WHERE '.implode(' AND ',$where)) : '');

  // Total
  $stmtC = $pdo->prepare('SELECT COUNT(*) '.$sqlBase);
  foreach ($params as $k=>$v) $stmtC->bindValue($k,$v);
  $stmtC->execute();
  $total = (int)$stmtC->fetchColumn();

  // Página
  $stmt = $pdo->prepare('SELECT id, username, email, role, status, display_name, created_at '.$sqlBase.' ORDER BY created_at DESC LIMIT :lim OFFSET :off');
  foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
  $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
  $stmt->execute();
  $users = $stmt->fetchAll();
} catch (Throwable $e) {
  devlog('users list failed', ['err'=>$e->getMessage()]);
  $users = [];
}

$maxPage = max(1, (int)ceil($total / $perPage));

include __DIR__ . '/../includes/header.php';
?>
<main class="container max-w-1100 p-2">
  <h1 class="title">Usuarios</h1>

  <div class="page-head">
    <div class="filters">
      <div class="filters-header">
        <h2 class="filters-title">Filtros</h2>
        <button class="filters-toggle" type="button" aria-expanded="false">
          <span class="filters-toggle-text">Mostrar filtros</span>
          <span class="filters-toggle-icon">▼</span>
        </button>
      </div>
      <form class="filters-form" method="get" action="users.php">
        <div class="filter-group">
          <label for="search-users">Buscar</label>
          <input class="input" id="search-users" type="search" name="q" placeholder="Buscar por usuario, email o nombre" value="<?php echo htmlspecialchars($q); ?>" />
        </div>
        
        <div class="filter-group">
          <label for="role-filter">Rol</label>
          <select class="input" id="role-filter" name="role">
            <option value="all"    <?php echo $fRole==='all'?'selected':''; ?>>Todos los roles</option>
            <option value="admin"  <?php echo $fRole==='admin'?'selected':''; ?>>Admin</option>
            <option value="mod"    <?php echo $fRole==='mod'?'selected':''; ?>>Moderador</option>
            <option value="artist" <?php echo $fRole==='artist'?'selected':''; ?>>Artista</option>
            <option value="user"   <?php echo $fRole==='user'?'selected':''; ?>>Usuario</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label for="status-filter">Estado</label>
          <select class="input" id="status-filter" name="status">
            <option value="all"     <?php echo $fStatus==='all'?'selected':''; ?>>Todos los estados</option>
            <option value="active"  <?php echo $fStatus==='active'?'selected':''; ?>>Activo</option>
            <option value="pending" <?php echo $fStatus==='pending'?'selected':''; ?>>Pendiente</option>
            <option value="banned"  <?php echo $fStatus==='banned'?'selected':''; ?>>Baneado</option>
          </select>
        </div>

        <div class="filter-actions">
          <button class="btn" type="submit">Filtrar</button>
          <a class="btn" href="review.php">Volver a moderación</a>
        </div>
      </form>
    </div>
  </div>

  <?php if (!$users): ?>
    <div class="empty"><p>No hay usuarios con esos filtros.</p></div>
  <?php else: ?>
    <div class="users-grid">
      <?php foreach ($users as $u): ?>
        <article class="user-card">
          <div class="user-info">
            <h2 class="user-title">
              @<?php echo htmlspecialchars($u['username']); ?>
            </h2>
            <p class="user-email"><?php echo htmlspecialchars($u['email']); ?></p>
            <div class="user-meta">
              <span class="user-badge role-<?php echo htmlspecialchars($u['role']); ?>"><?php echo htmlspecialchars($u['role']); ?></span>
              <span class="user-badge status-<?php echo htmlspecialchars($u['status']); ?>"><?php echo htmlspecialchars($u['status']); ?></span>
              <span class="user-date"><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></span>
            </div>
          </div>

          <div class="user-actions">
            <form class="user-form" method="post" action="users.php?<?php echo http_build_query(['q'=>$q,'role'=>$fRole,'status'=>$fStatus,'p'=>$page]); ?>">
              <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>" />

              <div class="form-group">
                <label for="role-<?php echo (int)$u['id']; ?>">Rol</label>
                <select class="input" id="role-<?php echo (int)$u['id']; ?>" name="role_value" aria-label="Nuevo rol">
                  <option value="user">Usuario</option>
                  <option value="artist">Artista</option>
                  <option value="mod">Moderador</option>
                  <?php if ($role==='admin'): ?><option value="admin">Admin</option><?php endif; ?>
                </select>
                <button class="btn small" name="action" value="set_role" type="submit">Cambiar rol</button>
              </div>

              <div class="form-group">
                <label for="status-<?php echo (int)$u['id']; ?>">Estado</label>
                <select class="input" id="status-<?php echo (int)$u['id']; ?>" name="status_value" aria-label="Nuevo estado">
                  <option value="active">Activo</option>
                  <option value="pending">Pendiente</option>
                  <option value="banned">Baneado</option>
                </select>
                <button class="btn small" name="action" value="set_status" type="submit">Cambiar estado</button>
              </div>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($maxPage > 1): ?>
      <nav class="pagination" aria-label="Paginación">
        <?php $qs = $_GET; unset($qs['p']); $base = 'users.php?'.http_build_query($qs); ?>
        <?php if ($page > 1): ?><a class="btn" href="<?php echo $base.'&p='.($page-1); ?>">&larr; Anterior</a><?php endif; ?>
        <span class="muted">Página <?php echo $page; ?> de <?php echo $maxPage; ?></span>
        <?php if ($page < $maxPage): ?><a class="btn" href="<?php echo $base.'&p='.($page+1); ?>">Siguiente &rarr;</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
