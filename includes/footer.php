<footer>
  <div class="container footer-wrap">
    <small>© <?php echo date('Y'); ?> Bahia Under — Hecho por Alex aka <span class="brand-color">hatemecha</span></small>
    <div class="links">
      <a href="<?php echo u('acerca.php'); ?>">Acerca</a>
      <a href="<?php echo u('terminos.php'); ?>">Términos</a>
      <a href="<?php echo u('contacto.php'); ?>">Contacto</a>
    </div>
  </div>
</footer>

<!-- Player global persistente -->
<div class="player-bar" id="ugb-player" hidden>
  <div class="player-left">
    <img id="ugb-cover" alt="" class="player-cover" width="56" height="56" hidden>
    <div class="player-info">
      <p class="player-title" id="ugb-title">—</p>
      <p class="player-subtitle" id="ugb-sub">—</p>
    </div>
  </div>

   <!-- TODO: Crear un reproductor de musica que se pueda reproducir una playlist, poner iconos decentes -->
  <div class="player-center">
    <div class="player-controls">
      <button type="button" class="player-btn prev" data-act="prev" aria-label="Anterior">⏮</button>
      <button type="button" class="player-btn play" data-act="play" aria-label="Reproducir/Pausar">▶︎</button>
      <button type="button" class="player-btn next" data-act="next" aria-label="Siguiente">⏭</button>
    </div>
    <div class="player-loading" id="ugb-loading" style="display: none;">
      <div class="loading-spinner"></div>
      <span>Cargando...</span>
    </div>
    <div class="player-progress">
      <span class="player-time" id="ugb-current-time">0:00</span>
      <div class="progress-bar">
        <input type="range" min="0" max="100" value="0" step="1" id="ugb-seek" aria-label="Progreso">
      </div>
      <span class="player-time" id="ugb-duration">0:00</span>
    </div>
  </div>
  
  <div class="player-right">
    <div class="volume-control">
      <button type="button" class="player-btn volume-btn" data-act="mute" aria-label="Silenciar">🔊</button>
      <div class="volume-bar">
        <input type="range" min="0" max="1" value="1" step="0.01" id="ugb-vol" aria-label="Volumen">
      </div>
    </div>
    <button type="button" class="player-btn close-btn" data-act="close" aria-label="Cerrar reproductor">✕</button>
  </div>
  
  <audio id="ugb-audio" preload="metadata"></audio>
</div>


</body>
</html>
