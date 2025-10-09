<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/init.php';

$role = $_SESSION['role'] ?? 'user';
$uid  = (int)($_SESSION['uid'] ?? 0);
$canManage = in_array($role, ['mod','admin'], true);

// Verificar el estado de la sesión y permisos

// Acciones de administración (ANTES del header para evitar "headers already sent")
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['action'] ?? '';
  if ($act === 'create' || $act === 'update') {
    $title = trim($_POST['title'] ?? '');
    $event_dt = trim($_POST['event_dt'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $place_name = trim($_POST['place_name'] ?? '');
    $place_address = trim($_POST['place_address'] ?? '');
    $maps_url = trim($_POST['maps_url'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $flyer = null;

    // Subida local del flyer
    if (!empty($_FILES['flyer_file']['name']) && ($_FILES['flyer_file']['error'] === UPLOAD_ERR_OK)) {
      try {
        $tmp = $_FILES['flyer_file']['tmp_name'];
        $size = (int)$_FILES['flyer_file']['size'];
        if ($size > 5 * 1024 * 1024) { throw new RuntimeException('Archivo demasiado grande (>5MB)'); }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        $allowed = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];
        if (!isset($allowed[$mime])) { throw new RuntimeException('Formato no permitido'); }

        $ym  = date('Y/m');
        $dir = __DIR__ . '/media/flyers/' . $ym;
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }

        $base = bin2hex(random_bytes(8)) . $allowed[$mime];
        $destRel = 'media/flyers/' . $ym . '/' . $base;
        $destAbs = __DIR__ . '/' . $destRel;
        if (!move_uploaded_file($tmp, $destAbs)) { throw new RuntimeException('No se pudo guardar el flyer'); }
        $flyer = $destRel;
      } catch (Throwable $e) {
        devlog('events.flyer_upload_failed', ['err'=>$e->getMessage()]);
        $flyer = null;
      }
    }
    if ($title !== '' && $event_dt !== '') {
      try {
        if ($act === 'create') {
          $stmt = $pdo->prepare("INSERT INTO events (title, description, event_dt, location, place_name, place_address, maps_url, flyer_path, created_by) VALUES (:t,:d,:dt,:loc,:pn,:pa,:m,:f,:cb)");
          $stmt->execute([
            ':t'=>$title,
            ':d'=>($desc?:null),
            ':dt'=>$event_dt,
            ':loc'=>($location?:null),
            ':pn'=>($place_name?:null),
            ':pa'=>($place_address?:null),
            ':m'=>($maps_url?:null),
            ':f'=>($flyer?:null),
            ':cb'=>$uid?:null
          ]);
        } else { // update
          $id = (int)($_POST['id'] ?? 0);
          if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE events SET title=:t, description=:d, event_dt=:dt, location=:loc, place_name=:pn, place_address=:pa, maps_url=:m, flyer_path=COALESCE(:f, flyer_path) WHERE id=:id");
            $stmt->execute([
              ':t'=>$title,
              ':d'=>($desc?:null),
              ':dt'=>$event_dt,
              ':loc'=>($location?:null),
              ':pn'=>($place_name?:null),
              ':pa'=>($place_address?:null),
              ':m'=>($maps_url?:null),
              ':f'=>($flyer?:null),
              ':id'=>$id
            ]);
          }
        }
      } catch (Throwable $e) {
        devlog('events.create_failed', ['err'=>$e->getMessage()]);
      }
    }
  } elseif (in_array($act, ['cancel','activate','delete'], true)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      try {
        if ($act === 'delete') {
          $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
          $result = $stmt->execute([':id'=>$id]);
          devlog('events.delete_attempt', ['id'=>$id, 'result'=>$result, 'affected_rows'=>$stmt->rowCount()]);
          $redirect_param = 'deleted=1';
        } elseif ($act === 'cancel') {
          $stmt = $pdo->prepare("UPDATE events SET status='cancelled' WHERE id=:id");
          $result = $stmt->execute([':id'=>$id]);
          devlog('events.cancel_attempt', ['id'=>$id, 'result'=>$result, 'affected_rows'=>$stmt->rowCount()]);
          $redirect_param = 'cancelled=1';
        } elseif ($act === 'activate') {
          $stmt = $pdo->prepare("UPDATE events SET status='active' WHERE id=:id");
          $result = $stmt->execute([':id'=>$id]);
          devlog('events.activate_attempt', ['id'=>$id, 'result'=>$result, 'affected_rows'=>$stmt->rowCount()]);
          $redirect_param = 'activated=1';
        }
      } catch (Throwable $e) {
        devlog('events.update_failed', ['err'=>$e->getMessage(), 'id'=>$id, 'action'=>$act]);
      }
    }
  }
  // Headers para prevenir caché y forzar recarga
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');
  $redirect_url = 'eventos.php?t=' . time();
  if (isset($redirect_param)) {
    $redirect_url .= '&' . $redirect_param;
  }
  header('Location: ' . $redirect_url); 
  exit;
}

// Headers para prevenir caché en la página principal
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Incluir header después del procesamiento del formulario
include __DIR__ . '/includes/header.php';

// Verificar si hay mensajes flash de acciones completadas
$flash_message = '';
if (isset($_GET['deleted'])) {
  $flash_message = '<div class="flash-message success">✅ Evento eliminado correctamente</div>';
} elseif (isset($_GET['cancelled'])) {
  $flash_message = '<div class="flash-message warning">⚠️ Evento cancelado</div>';
} elseif (isset($_GET['activated'])) {
  $flash_message = '<div class="flash-message success">✅ Evento reactivado</div>';
}

// Listado próximo y pasado
$upcoming = [];
$past = [];
try {
  // Mostrar todos los eventos próximos (independientemente del status)
  $stmt = $pdo->query("SELECT * FROM events WHERE event_dt >= NOW() ORDER BY event_dt ASC LIMIT 50");
  $upcoming = $stmt->fetchAll();
  // Mostrar todos los eventos pasados
  $stmt2 = $pdo->query("SELECT * FROM events WHERE event_dt < NOW() ORDER BY event_dt DESC LIMIT 20");
  $past = $stmt2->fetchAll();
} catch (Throwable $e) {
  devlog('events.list_failed', ['err'=>$e->getMessage()]);
}
?>
<main>
  <section id="eventos">
    <div class="container">
      <?php if ($flash_message): ?>
        <?php echo $flash_message; ?>
      <?php endif; ?>
      
      <div class="section-title">
        <h2>Próximos eventos</h2>
        <?php if ($canManage): ?><button class="btn" id="open-create" type="button">Crear evento</button><?php endif; ?>
      </div>

      <?php if (!$upcoming): ?>
        <div class="empty"><p>No hay eventos próximos por ahora.</p></div>
      <?php else: ?>
        <div class="events" role="list" aria-label="Eventos próximos">
          <?php foreach ($upcoming as $ev): ?>
            <article class="event-card" role="listitem">
              <a class="event-flyer<?php echo empty($ev['flyer_path']) ? ' ph' : ''; ?>" href="evento.php?id=<?php echo $ev['id']; ?>">
                <?php if (!empty($ev['flyer_path'])): ?><img src="<?php echo htmlspecialchars(u($ev['flyer_path'])); ?>" alt="Flyer de <?php echo htmlspecialchars($ev['title']); ?>"><?php endif; ?>
              </a>
              <div>
                <h3 class="event-title">
                  <a href="evento.php?id=<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></a>
                  <?php if ($ev['status'] === 'cancelled'): ?>
                    <span class="event-status-badge cancelled">Cancelado</span>
                  <?php endif; ?>
                </h3>
                <p class="event-desc">
                  <time datetime="<?php echo htmlspecialchars($ev['event_dt']); ?>"><?php echo date('d/m/Y H:i', strtotime($ev['event_dt'])); ?></time>
                  <?php if (!empty($ev['place_name'])): ?> · <?php echo htmlspecialchars($ev['place_name']); ?><?php endif; ?>
                  <?php if (!empty($ev['maps_url'])): ?> · <a class="btn" href="<?php echo htmlspecialchars($ev['maps_url']); ?>" target="_blank" rel="noopener">Mapa</a><?php endif; ?>
                  · <a class="btn btn-secondary" href="evento.php?id=<?php echo $ev['id']; ?>">Ver detalles</a>
                </p>
                <?php if (!empty($ev['description'])): ?><p class="muted"><?php echo nl2br(htmlspecialchars($ev['description'])); ?></p><?php endif; ?>
                <?php if ($canManage): ?>
                  <form class="form form-inline" method="post" action="eventos.php">
                    <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>" />
                    <button class="btn" type="button" data-edit-event='{"id":<?php echo (int)$ev['id']; ?>,"title":"<?php echo htmlspecialchars($ev['title'], ENT_QUOTES); ?>","event_dt":"<?php echo date('Y-m-d\TH:i', strtotime($ev['event_dt'])); ?>","place_name":"<?php echo htmlspecialchars($ev['place_name'] ?? '', ENT_QUOTES); ?>","place_address":"<?php echo htmlspecialchars($ev['place_address'] ?? '', ENT_QUOTES); ?>","maps_url":"<?php echo htmlspecialchars($ev['maps_url'] ?? '', ENT_QUOTES); ?>"}'>Editar</button>
                    <button class="btn" name="action" value="cancel" type="submit">Marcar cancelado</button>
                    <button class="btn btn-danger" name="action" value="delete" type="submit" onclick="return confirm('¿Estás seguro de que quieres eliminar este evento? Esta acción no se puede deshacer.');">🗑️ Eliminar</button>
                  </form>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($past): ?>
        <div class="section-title section-title-spaced">
          <h2>Eventos pasados</h2>
        </div>
        <div class="events" role="list" aria-label="Eventos pasados">
          <?php foreach ($past as $ev): ?>
            <article class="event-card" role="listitem">
              <a class="event-flyer<?php echo empty($ev['flyer_path']) ? ' ph' : ''; ?>" href="evento.php?id=<?php echo $ev['id']; ?>">
                <?php if (!empty($ev['flyer_path'])): ?><img src="<?php echo htmlspecialchars(u($ev['flyer_path'])); ?>" alt="Flyer de <?php echo htmlspecialchars($ev['title']); ?>"><?php endif; ?>
              </a>
              <div>
                <h3 class="event-title">
                  <a href="evento.php?id=<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></a>
                  <?php if ($ev['status'] === 'cancelled'): ?>
                    <span class="event-status-badge cancelled">Cancelado</span>
                  <?php endif; ?>
                </h3>
                <p class="event-desc">
                  <time datetime="<?php echo htmlspecialchars($ev['event_dt']); ?>"><?php echo date('d/m/Y H:i', strtotime($ev['event_dt'])); ?></time>
                  <?php if (!empty($ev['place_name'])): ?> · <?php echo htmlspecialchars($ev['place_name']); ?><?php endif; ?>
                  <?php if (!empty($ev['maps_url'])): ?> · <a class="btn" href="<?php echo htmlspecialchars($ev['maps_url']); ?>" target="_blank" rel="noopener">Mapa</a><?php endif; ?>
                  · <a class="btn btn-secondary" href="evento.php?id=<?php echo $ev['id']; ?>">Ver detalles</a>
                </p>
                <?php if ($canManage && $ev['status'] !== 'active'): ?>
                  <form class="form form-inline" method="post" action="eventos.php">
                    <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>" />
                    <button class="btn" type="button" data-edit-event='{"id":<?php echo (int)$ev['id']; ?>,"title":"<?php echo htmlspecialchars($ev['title'], ENT_QUOTES); ?>","event_dt":"<?php echo date('Y-m-d\TH:i', strtotime($ev['event_dt'])); ?>","place_name":"<?php echo htmlspecialchars($ev['place_name'] ?? '', ENT_QUOTES); ?>","place_address":"<?php echo htmlspecialchars($ev['place_address'] ?? '', ENT_QUOTES); ?>","maps_url":"<?php echo htmlspecialchars($ev['maps_url'] ?? '', ENT_QUOTES); ?>"}'>Editar</button>
                    <button class="btn" name="action" value="activate" type="submit">Reactivar</button>
                    <button class="btn btn-danger" name="action" value="delete" type="submit" onclick="return confirm('¿Estás seguro de que quieres eliminar este evento? Esta acción no se puede deshacer.');">🗑️ Eliminar</button>
                  </form>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($canManage): ?>
        <div id="event-modal" class="modal">
          <form id="event-form" class="form card" method="post" action="eventos.php" enctype="multipart/form-data">
            <div class="form-header">
              <h3 id="event-form-title">Nuevo evento</h3>
              <button class="btn" type="button" id="close-modal">Cerrar</button>
            </div>
            <input type="hidden" name="action" value="create" />
            <input type="hidden" name="id" value="" />
            <div class="form-row">
              <label>Título</label>
              <input class="input" type="text" name="title" required placeholder="Nombre del evento" />
            </div>
            <div class="form-row form-row-2">
              <div>
                <label>Fecha y hora</label>
                <input class="input" type="datetime-local" name="event_dt" required />
              </div>
              <div>
                <label>Nombre del lugar</label>
                <input class="input" type="text" name="place_name" placeholder="Nombre del venue" />
              </div>
            </div>
            <div class="form-row form-row-2">
              <div>
                <label>Dirección</label>
                <input class="input" type="text" name="place_address" placeholder="Dirección" />
              </div>
              <div>
                <label>URL de Google Maps</label>
                <input class="input" type="url" name="maps_url" placeholder="https://maps.google.com/..." />
              </div>
            </div>
            <div class="form-row">
              <label>Flyer (JPG/PNG/WEBP, máx 5MB)</label>
              <input class="input" type="file" name="flyer_file" accept="image/jpeg,image/png,image/webp" />
            </div>
            <div class="form-row">
              <label>Descripción</label>
              <textarea class="input" name="description" rows="3" placeholder="Info, lineup, precios, etc."></textarea>
            </div>
            <div class="form-actions">
              <button class="btn" type="button" id="cancel-modal">Cancelar</button>
              <button class="btn primary" type="submit">Guardar</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<style>
/* Estilos para los enlaces de eventos */
.event-title a {
  color: var(--accent-color);
  text-decoration: none;
  transition: color 0.2s ease;
}

.event-title a:hover {
  color: var(--accent-color-hover, #0066cc);
  text-decoration: underline;
}

.btn-secondary {
  background: var(--text-muted);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.9rem;
  display: inline-block;
  transition: background-color 0.2s ease;
}

.btn-secondary:hover {
  background: #616161;
  color: white;
  text-decoration: none;
}

.event-flyer {
  display: block;
  width: 100%;
  height: 200px;
  overflow: hidden;
  border-radius: 8px;
  transition: transform 0.2s ease;
}

.event-flyer img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.event-flyer:hover {
  transform: scale(1.02);
}

.event-flyer.ph {
  background: var(--bg-secondary);
  border: 2px dashed var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.event-flyer.ph::before {
  content: "Sin flyer";
}

.event-card {
  transition: box-shadow 0.2s ease;
}

.event-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.event-status-badge {
  display: inline-block;
  padding: 0.25rem 0.5rem;
  margin-left: 0.5rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.event-status-badge.cancelled {
  background: #f44336;
  color: white;
}

/* Mensajes flash */
.flash-message {
  padding: 1rem;
  margin-bottom: 1rem;
  border-radius: 6px;
  font-weight: 500;
  animation: slideIn 0.3s ease-out;
}

.flash-message.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.flash-message.warning {
  background: #fff3cd;
  color: #856404;
  border: 1px solid #ffeaa7;
}

.flash-message.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Estilos para el modal */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
  animation: fadeIn 0.3s ease-out;
}

.modal .card {
  position: relative;
  margin: 3% auto;
  padding: 2rem;
  width: 90%;
  max-width: 600px;
  background: var(--bg-primary);
  border: 2px solid var(--accent-color);
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
  animation: slideInModal 0.3s ease-out;
  max-height: 90vh;
  overflow-y: auto;
}

.form-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.form-header h3 {
  margin: 0;
  color: var(--accent-color);
}

.form-header .btn {
  background: var(--error-color, #f44336);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.form-header .btn:hover {
  background: var(--error-color-hover, #d32f2f);
}

.form-row {
  margin-bottom: 1rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-row label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-row .input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: var(--bg-secondary);
  color: var(--text-primary);
  font-size: 1rem;
}

.form-row .input:focus {
  outline: none;
  border-color: var(--accent-color);
  box-shadow: 0 0 0 2px rgba(var(--accent-rgb), 0.2);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.form-actions .btn {
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
}

.form-actions .btn.primary {
  background: var(--accent-color);
  color: white;
}

.form-actions .btn.primary:hover {
  background: var(--accent-color-hover, #0066cc);
  transform: translateY(-1px);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideInModal {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Asegurar que el modal esté siempre visible */
.modal {
  backdrop-filter: blur(4px);
}

/* Asegurar que el modal esté por encima de cualquier reproductor */
.modal * {
  z-index: 10000;
}

@media (max-width: 768px) {
  .modal .card {
    margin: 10% auto;
    width: 95%;
    padding: 1.5rem;
  }
  
  .form-row-2 {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>

<script>
// Funcionalidad del modal de eventos
document.addEventListener('DOMContentLoaded', function() {
  // Auto-ocultar mensajes flash después de 5 segundos
  const flashMessages = document.querySelectorAll('.flash-message');
  flashMessages.forEach(function(message) {
    setTimeout(function() {
      message.style.opacity = '0';
      message.style.transform = 'translateY(-10px)';
      setTimeout(function() {
        message.remove();
      }, 300);
    }, 5000);
  });

  // Manejar botón de abrir modal de creación
  const openCreateBtn = document.getElementById('open-create');
  const modal = document.getElementById('event-modal');
  const closeModalBtn = document.getElementById('close-modal');
  const cancelModalBtn = document.getElementById('cancel-modal');
  const eventForm = document.getElementById('event-form');
  const formTitle = document.getElementById('event-form-title');

  if (openCreateBtn && modal) {
    openCreateBtn.addEventListener('click', function() {
      // Resetear formulario para creación
      eventForm.reset();
      document.querySelector('input[name="action"]').value = 'create';
      document.querySelector('input[name="id"]').value = '';
      formTitle.textContent = 'Nuevo evento';
      modal.style.display = 'block';
    });
  }

  // Cerrar modal
  if (closeModalBtn && modal) {
    closeModalBtn.addEventListener('click', function() {
      modal.style.display = 'none';
    });
  }

  if (cancelModalBtn && modal) {
    cancelModalBtn.addEventListener('click', function() {
      modal.style.display = 'none';
    });
  }

  // Cerrar modal al hacer clic fuera
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  }

  // Manejar botones de editar evento
  const editButtons = document.querySelectorAll('[data-edit-event]');
  editButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
      const eventData = JSON.parse(this.dataset.editEvent);
      
      // Llenar formulario con datos del evento
      document.querySelector('input[name="title"]').value = eventData.title || '';
      document.querySelector('input[name="event_dt"]').value = eventData.event_dt || '';
      document.querySelector('input[name="place_name"]').value = eventData.place_name || '';
      document.querySelector('input[name="place_address"]').value = eventData.place_address || '';
      document.querySelector('input[name="maps_url"]').value = eventData.maps_url || '';
      document.querySelector('textarea[name="description"]').value = eventData.description || '';
      
      // Cambiar a modo edición
      document.querySelector('input[name="action"]').value = 'update';
      document.querySelector('input[name="id"]').value = eventData.id;
      formTitle.textContent = 'Editar evento';
      
      modal.style.display = 'block';
    });
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
