<?php
require_once __DIR__ . '/includes/dev.php';
require_once __DIR__ . '/includes/init.php';
include __DIR__ . '/includes/header.php';

$event_id = (int)($_GET['id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$uid = (int)($_SESSION['uid'] ?? 0);
$canManage = in_array($role, ['mod','admin'], true);

// Obtener información del evento
$event = null;
if ($event_id > 0) {
  try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute([':id' => $event_id]);
    $event = $stmt->fetch();
  } catch (Throwable $e) {
    devlog('event.detail_failed', ['err' => $e->getMessage(), 'id' => $event_id]);
  }
}

// Si no se encuentra el evento, mostrar error 404
if (!$event) {
  http_response_code(404);
  include __DIR__ . '/error-404.html';
  exit;
}

// Los eventos se muestran para todos los usuarios
// Solo los administradores/moderadores pueden gestionar eventos cancelados

// Determinar si el evento ya pasó
$isPast = strtotime($event['event_dt']) < time();
$eventDate = date('d/m/Y', strtotime($event['event_dt']));
$eventTime = date('H:i', strtotime($event['event_dt']));
$eventDateTime = date('c', strtotime($event['event_dt'])); // ISO 8601 format

// No necesitamos extraer coordenadas ya que no usaremos la API de Google Maps
?>
<main>
  <section id="evento-detalle">
    <div class="container">
      <!-- Breadcrumb -->
      <nav class="breadcrumb">
        <a href="eventos.php">← Volver a eventos</a>
      </nav>

      <article class="event-detail">
        <!-- Header del evento -->
        <header class="event-header">
          <div class="event-flyer-large">
            <?php if (!empty($event['flyer_path'])): ?>
              <img src="<?php echo htmlspecialchars(u($event['flyer_path'])); ?>" 
                   alt="Flyer de <?php echo htmlspecialchars($event['title']); ?>" 
                   class="flyer-image">
            <?php else: ?>
              <div class="flyer-placeholder">
                <span>Sin flyer</span>
              </div>
            <?php endif; ?>
          </div>
          
          <div class="event-info">
            <h1 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h1>
            
            <div class="event-meta">
              <div class="event-date-time">
                <time datetime="<?php echo $eventDateTime; ?>" class="event-date">
                  <strong><?php echo $eventDate; ?></strong>
                  <span><?php echo $eventTime; ?></span>
                </time>
                <?php if ($isPast): ?>
                  <span class="event-status past">Evento pasado</span>
                <?php else: ?>
                  <span class="event-status upcoming">Próximo evento</span>
                <?php endif; ?>
              </div>
              
              <?php if (!empty($event['place_name'])): ?>
                <div class="event-location">
                  <h3>📍 <?php echo htmlspecialchars($event['place_name']); ?></h3>
                  <?php if (!empty($event['place_address'])): ?>
                    <p class="address"><?php echo htmlspecialchars($event['place_address']); ?></p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </header>

        <!-- Contenido del evento -->
        <div class="event-content">
          <?php if (!empty($event['description'])): ?>
            <div class="event-description">
              <h2>Descripción</h2>
              <div class="description-text">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Ubicación -->
          <?php if (!empty($event['maps_url'])): ?>
            <div class="event-location-section">
              <h2>Ubicación</h2>
              
              <div class="location-actions">
                <a href="<?php echo htmlspecialchars($event['maps_url']); ?>" 
                   target="_blank" 
                   rel="noopener" 
                   class="btn btn-primary">
                  <span>🗺️</span> Ver en Google Maps
                </a>
              </div>
            </div>
          <?php endif; ?>

          <!-- Información adicional -->
          <div class="event-additional">
            <h2>Información del evento</h2>
            <div class="info-grid">
              <div class="info-item">
                <strong>Fecha:</strong>
                <span><?php echo $eventDate; ?></span>
              </div>
              <div class="info-item">
                <strong>Hora:</strong>
                <span><?php echo $eventTime; ?></span>
              </div>
              <?php if (!empty($event['place_name'])): ?>
                <div class="info-item">
                  <strong>Lugar:</strong>
                  <span><?php echo htmlspecialchars($event['place_name']); ?></span>
                </div>
              <?php endif; ?>
              <?php if (!empty($event['place_address'])): ?>
                <div class="info-item">
                  <strong>Dirección:</strong>
                  <span><?php echo htmlspecialchars($event['place_address']); ?></span>
                </div>
              <?php endif; ?>
              <div class="info-item">
                <strong>Estado:</strong>
                <span class="status-badge status-<?php echo $event['status']; ?>">
                  <?php 
                  echo match($event['status']) {
                    'active' => 'Activo',
                    'cancelled' => 'Cancelado',
                    default => ucfirst($event['status'])
                  };
                  ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Acciones de administración -->
        <?php if ($canManage): ?>
          <div class="event-admin-actions">
            <h2>Administración</h2>
            <div class="admin-buttons">
              <a href="eventos.php" class="btn btn-secondary">
                ← Volver a eventos
              </a>
              <button class="btn btn-warning" onclick="editEvent()">
                ✏️ Editar evento
              </button>
              <?php if ($event['status'] === 'active'): ?>
                <form method="post" action="eventos.php" style="display: inline;">
                  <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                  <button type="submit" name="action" value="cancel" class="btn btn-warning">
                    ❌ Cancelar evento
                  </button>
                </form>
              <?php elseif ($event['status'] === 'cancelled'): ?>
                <form method="post" action="eventos.php" style="display: inline;">
                  <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                  <button type="submit" name="action" value="activate" class="btn btn-success">
                    ✅ Reactivar evento
                  </button>
                </form>
              <?php endif; ?>
              <form method="post" action="eventos.php" style="display: inline;">
                <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                <button type="submit" name="action" value="delete" 
                        class="btn btn-danger" 
                        onclick="return confirm('¿Estás seguro de que quieres eliminar este evento? Esta acción no se puede deshacer.');">
                  🗑️ Eliminar evento
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </article>
    </div>
  </section>
</main>

<!-- Modal de edición (solo para administradores) -->
<?php if ($canManage): ?>
  <div id="edit-event-modal" class="modal">
    <form id="edit-event-form" class="form card" method="post" action="eventos.php" enctype="multipart/form-data">
      <div class="form-header">
        <h3>Editar evento</h3>
        <button class="btn" type="button" id="close-edit-modal">Cerrar</button>
      </div>
      <input type="hidden" name="action" value="update" />
      <input type="hidden" name="id" value="<?php echo $event['id']; ?>" />
      <div class="form-row">
        <label>Título</label>
        <input class="input" type="text" name="title" required 
               value="<?php echo htmlspecialchars($event['title']); ?>" 
               placeholder="Nombre del evento" />
      </div>
      <div class="form-row form-row-2">
        <div>
          <label>Fecha y hora</label>
          <input class="input" type="datetime-local" name="event_dt" required 
                 value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_dt'])); ?>" />
        </div>
        <div>
          <label>Nombre del lugar</label>
          <input class="input" type="text" name="place_name" 
                 value="<?php echo htmlspecialchars($event['place_name'] ?? ''); ?>" 
                 placeholder="Nombre del venue" />
        </div>
      </div>
      <div class="form-row form-row-2">
        <div>
          <label>Dirección</label>
          <input class="input" type="text" name="place_address" 
                 value="<?php echo htmlspecialchars($event['place_address'] ?? ''); ?>" 
                 placeholder="Dirección" />
        </div>
        <div>
          <label>URL de Google Maps</label>
          <input class="input" type="url" name="maps_url" 
                 value="<?php echo htmlspecialchars($event['maps_url'] ?? ''); ?>" 
                 placeholder="https://maps.google.com/..." />
        </div>
      </div>
      <div class="form-row">
        <label>Nuevo flyer (JPG/PNG/WEBP, máx 5MB)</label>
        <input class="input" type="file" name="flyer_file" accept="image/jpeg,image/png,image/webp" />
        <small>Dejar vacío para mantener el flyer actual</small>
      </div>
      <div class="form-row">
        <label>Descripción</label>
        <textarea class="input" name="description" rows="3" 
                  placeholder="Info, lineup, precios, etc."><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
      </div>
      <div class="form-actions">
        <button class="btn" type="button" id="cancel-edit-modal">Cancelar</button>
        <button class="btn primary" type="submit">Guardar cambios</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<style>
/* Estilos específicos para la página de evento individual */
.event-detail {
  max-width: 1000px;
  margin: 0 auto;
}

.breadcrumb {
  margin-bottom: 2rem;
}

.breadcrumb a {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.9rem;
}

.breadcrumb a:hover {
  color: var(--accent-color);
}

.event-header {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 2rem;
  margin-bottom: 3rem;
  align-items: start;
}

.event-flyer-large {
  position: relative;
}

.flyer-image {
  width: 100%;
  height: 400px;
  object-fit: cover;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.flyer-placeholder {
  width: 100%;
  height: 400px;
  background: var(--bg-secondary);
  border: 2px dashed var(--border-color);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 1.1rem;
}

.event-info {
  padding: 1rem 0;
}

.event-title {
  font-size: 2.5rem;
  margin-bottom: 1.5rem;
  line-height: 1.2;
}

.event-meta {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.event-date-time {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.event-date {
  display: flex;
  flex-direction: column;
  font-size: 1.2rem;
}

.event-date strong {
  font-size: 1.5rem;
  color: var(--accent-color);
}

.event-status {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.9rem;
  font-weight: 500;
}

.event-status.upcoming {
  background: var(--success-color);
  color: white;
}

.event-status.past {
  background: var(--text-muted);
  color: white;
}

.event-location h3 {
  margin-bottom: 0.5rem;
  font-size: 1.3rem;
}

.address {
  color: var(--text-muted);
  font-size: 1rem;
}

.event-content {
  display: grid;
  gap: 2rem;
}

.event-description,
.event-location-section,
.event-additional {
  background: var(--bg-secondary);
  padding: 2rem;
  border-radius: 8px;
}

.event-description h2,
.event-location-section h2,
.event-additional h2 {
  margin-bottom: 1rem;
  color: var(--accent-color);
}

.description-text {
  line-height: 1.6;
  font-size: 1.1rem;
}

.location-actions {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-primary);
  border-radius: 6px;
  border: 1px solid var(--border-color);
}

.info-item strong {
  color: var(--accent-color);
}

.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
}

.status-active {
  background: var(--success-color);
  color: white;
}

.status-cancelled {
  background: var(--error-color);
  color: white;
}

.event-admin-actions {
  margin-top: 3rem;
  padding: 2rem;
  background: var(--bg-secondary);
  border-radius: 8px;
  border: 2px solid var(--warning-color);
}

.event-admin-actions h2 {
  color: var(--warning-color);
  margin-bottom: 1rem;
}

.admin-buttons {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-danger {
  background: var(--error-color);
  color: white;
}

.btn-danger:hover {
  background: #d32f2f;
}

.btn-warning {
  background: var(--warning-color);
  color: white;
}

.btn-warning:hover {
  background: #f57c00;
}

.btn-success {
  background: var(--success-color);
  color: white;
}

.btn-success:hover {
  background: #388e3c;
}

.btn-secondary {
  background: var(--text-muted);
  color: white;
}

.btn-secondary:hover {
  background: #616161;
}

/* Responsive */
@media (max-width: 768px) {
  .event-header {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .event-title {
    font-size: 2rem;
  }
  
  .event-date-time {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .admin-buttons {
    flex-direction: column;
  }
  
  .location-actions {
    flex-direction: column;
  }
}
</style>

<!-- JavaScript para funcionalidades del evento -->
<script>
// Función para editar evento (solo administradores)
<?php if ($canManage): ?>
function editEvent() {
  document.getElementById('edit-event-modal').style.display = 'block';
}

// Cerrar modal de edición
document.getElementById('close-edit-modal').addEventListener('click', () => {
  document.getElementById('edit-event-modal').style.display = 'none';
});

document.getElementById('cancel-edit-modal').addEventListener('click', () => {
  document.getElementById('edit-event-modal').style.display = 'none';
});
<?php endif; ?>

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', () => {
  // Funcionalidades básicas del evento
  console.log('Evento cargado correctamente');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
