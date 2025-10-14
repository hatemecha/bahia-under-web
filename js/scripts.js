/**
 * 
 * Maneja el cambio entre tema claro y oscuro con persistencia en localStorage
 * 
 */

// ================== Tema oscuro/claro ==================
/**
 * Inicializa el sistema de temas
 * Configura el tema guardado o usa el predeterminado
 * 
 * @function themeInit
 * @returns {void}
 */
(function themeInit() {
  const key = "ugb_theme";
  const root = document.documentElement;
  const saved = localStorage.getItem(key);
  
  // En móviles, forzar tema oscuro
  if (window.innerWidth <= 768) {
    root.setAttribute("data-theme", "dark");
    return;
  }
  
  // Solo permitir light o dark en el ciclo normal en desktop
  if (saved === "light" || saved === "dark")
    root.setAttribute("data-theme", saved);

  const btn = document.querySelector("[data-theme-toggle]");
  if (btn) {
    const syncBtn = () => {
      const t = root.getAttribute("data-theme") || "dark";
      btn.setAttribute("aria-pressed", String(t === "dark"));
      let themeEmoji = "🌙"; // Oscuro por defecto
      if (t === "light") themeEmoji = "☀️";
      btn.innerHTML = themeEmoji;
    };
    syncBtn();
    btn.addEventListener("click", () => {
      const curr = root.getAttribute("data-theme") || "dark";
      let next = "dark";
      if (curr === "dark") next = "light";
      else if (curr === "light") next = "dark";
      
      root.setAttribute("data-theme", next);
      localStorage.setItem(key, next);
      syncBtn();
    });
  }
})();

// ================== Tema Matrix Easter Egg ==================
/**
 * 
 * @function matrixThemeInit
 * @returns {void}
 */
(function matrixThemeInit() {
  const root = document.documentElement;
  
  document.addEventListener("keydown", (e) => {
    // Solo activar con F12
    if (e.key === "F12") {
      e.preventDefault();
      const currentTheme = root.getAttribute("data-theme");
      
      if (currentTheme === "matrix") {
        // Volver al tema anterior
        const saved = localStorage.getItem("ugb_theme") || "dark";
        root.setAttribute("data-theme", saved);
      } else {
        // Activar Matrix
        root.setAttribute("data-theme", "matrix");
      }
    }
  });
})();

// ================== Búsqueda con autocompletado ==================
/**
 * Inicializa el sistema de búsqueda con sugerencias en tiempo real
 * Proporciona autocompletado y navegación por teclado
 * 
 * @function searchInit
 * @returns {void}
 */
(function searchInit() {
  const searchForm = document.querySelector('form.search');
  const searchInput = document.querySelector('form.search input[type="search"]');
  
  if (!searchForm || !searchInput) return;
  
  let suggestionsContainer = null;
  let currentSuggestions = [];
  let selectedIndex = -1;
  let searchTimeout = null;
  
  // Crear contenedor de sugerencias
  /**
   * Crea el contenedor de sugerencias de búsqueda
   * 
   * @function createSuggestionsContainer
   * @returns {HTMLElement} Contenedor de sugerencias
   */
  function createSuggestionsContainer() {
    if (suggestionsContainer) return;
    
    suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'search-suggestions';
    suggestionsContainer.style.cssText = `
      position: absolute;
      top: calc(100% + 0.25rem);
      left: 0;
      right: 0;
      background: var(--card-bg, #fff);
      border: 1px solid var(--border-color, #ddd);
      border-radius: 0.5rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1);
      z-index: 9999;
      max-height: 400px;
      overflow-y: auto;
      display: none;
      min-width: 320px;
    `;
    
    searchForm.style.position = 'relative';
    searchForm.appendChild(suggestionsContainer);
  }
  

  /**
   * Obtiene sugerencias de búsqueda desde el servidor
   * 
   * @function fetchSuggestions
   * @param {string} query - Término de búsqueda
   * @returns {Promise<Array>} Array de sugerencias
   */
  async function fetchSuggestions(query) {
    if (query.length < 2) {
      hideSuggestions();
      return;
    }
    
    // Mostrar indicador de carga
    showLoadingState();
    
    try {
      // Detectar si estamos en un subdirectorio (como /mod/)
      const isInSubdir = window.location.pathname.includes('/mod/');
      const baseUrl = isInSubdir ? '../search-suggestions.php' : 'search-suggestions.php';
      
      const response = await fetch(`${baseUrl}?q=${encodeURIComponent(query)}`);
      const data = await response.json();
      currentSuggestions = data.suggestions || [];
      showSuggestions();
    } catch (error) {
      console.error('Error fetching suggestions:', error);
      hideSuggestions();
    }
  }
  
  // Mostrar indicador de carga
  /**
   * Muestra un indicador de carga mientras se obtienen las sugerencias
   * 
   * @function showLoading
   * @returns {void}
   */
  function showLoadingState() {
    if (!suggestionsContainer) createSuggestionsContainer();
    
    suggestionsContainer.innerHTML = `
      <div class="suggestion-loading">
        <span class="loading-spinner">🔍</span>
        <span>Buscando...</span>
      </div>
    `;
    suggestionsContainer.style.display = 'block';
  }
  
  // Mostrar sugerencias
  /**
   * Muestra las sugerencias de búsqueda o un mensaje si no hay resultados
   * 
   * @function showSuggestions
   * @returns {void}
   */
  function showSuggestions() {
    if (!suggestionsContainer) createSuggestionsContainer();
    
    if (currentSuggestions.length === 0) {
      // Mostrar mensaje de "no hay resultados" si el usuario escribió algo
      if (searchInput.value.trim().length >= 2) {
        suggestionsContainer.innerHTML = `
          <div class="suggestion-empty">
            <span class="suggestion-icon">🔍</span>
            <span class="suggestion-text">No se encontraron resultados</span>
          </div>
        `;
        suggestionsContainer.style.display = 'block';
      } else {
        hideSuggestions();
      }
      return;
    }
    
    suggestionsContainer.innerHTML = currentSuggestions.map((suggestion, index) => {
      const icon = getTypeIcon(suggestion.type);
      return `
        <div class="suggestion-item ${index === selectedIndex ? 'selected' : ''}" data-index="${index}">
          <span class="suggestion-icon">${icon}</span>
          <span class="suggestion-text">${escapeHtml(suggestion.text)}</span>
          <span class="suggestion-type">${getTypeLabel(suggestion.type)}</span>
        </div>
      `;
    }).join('');
    
    suggestionsContainer.style.display = 'block';
    selectedIndex = -1;
  }
  
  // Ocultar sugerencias
  /**
   * 
   * @function hideSuggestions
   * @returns {void}
   */
  function hideSuggestions() {
    if (suggestionsContainer) {
      suggestionsContainer.style.display = 'none';
    }
    selectedIndex = -1;
  }
  
  // Obtener icono según tipo
  /**
   * 
   * @function getTypeIcon
   * @param {string} type - Tipo de contenido (release, blog, event, user)
   * @returns {string} Emoji del icono, me da paja hacerlos.
   */
  function getTypeIcon(type) {
    const icons = {
      artist: '👤',
      release: '💿',
      blog: '📝',
      event: '📅'
    };
    return icons[type] || '🔍';
  }
  
  // Obtener etiqueta según tipo
  /**
   * Obtiene la etiqueta legible del tipo de contenido
   * 
   * @function getTypeLabel
   * @param {string} type 
   * @returns {string} 
   */
  function getTypeLabel(type) {
    const labels = {
      artist: 'Artista',
      release: 'Lanzamiento',
      blog: 'Blog',
      event: 'Evento'
    };
    return labels[type] || '';
  }
  
  // Escapar HTML
  /**
   * Escapa caracteres HTML para prevenir XSS
   * 
   * @function escapeHtml
   * @param {string} text - Texto a escapar
   * @returns {string} Texto con caracteres HTML escapados
   */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Navegar sugerencias con teclado
  /**
   * Navega por las sugerencias usando las flechas del teclado
   * 
   * @function navigateSuggestions
   * @param {string} direction - Dirección de navegación ('up' o 'down')
   * @returns {void}
   */
  function navigateSuggestions(direction) {
    if (currentSuggestions.length === 0) return;
    
    if (direction === 'down') {
      selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
    } else if (direction === 'up') {
      selectedIndex = Math.max(selectedIndex - 1, -1);
    }
    
    // Actualizar estilos
    const items = suggestionsContainer.querySelectorAll('.suggestion-item');
    items.forEach((item, index) => {
      item.classList.toggle('selected', index === selectedIndex);
    });
  }
  
  // Seleccionar sugerencia
  /**
   * Selecciona la sugerencia actual y navega a ella
   * 
   * @function selectSuggestion
   * @returns {void}
   */
  function selectSuggestion() {
    if (selectedIndex >= 0 && currentSuggestions[selectedIndex]) {
      const suggestion = currentSuggestions[selectedIndex];
      window.location.href = suggestion.url;
    } else {
      // Si no hay sugerencia seleccionada, hacer búsqueda normal
      searchForm.submit();
    }
  }
  
  // Event listeners
  let lastQuery = '';
  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    
    // Limpiar timeout anterior
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }
    
    // Si estamos borrando texto, responder más rápido
    const isDeleting = query.length < lastQuery.length;
    const delay = isDeleting ? 150 : 300;
    lastQuery = query;
    
    // Debounce: esperar antes de buscar
    searchTimeout = setTimeout(() => {
      fetchSuggestions(query);
    }, delay);
  });
  
  searchInput.addEventListener('keydown', (e) => {
    if (!suggestionsContainer || suggestionsContainer.style.display === 'none') {
      return;
    }
    
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        navigateSuggestions('down');
        break;
      case 'ArrowUp':
        e.preventDefault();
        navigateSuggestions('up');
        break;
      case 'Enter':
        e.preventDefault();
        selectSuggestion();
        break;
      case 'Escape':
        hideSuggestions();
        searchInput.blur();
        break;
    }
  });
  
        searchInput.addEventListener('focus', () => {
            // No mostrar sugerencias automáticamente al hacer focus
            // Solo se mostrarán cuando el usuario escriba
        });
  
  // Cerrar sugerencias al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (!searchForm.contains(e.target)) {
      hideSuggestions();
    }
  });
  
  // Manejar clic en sugerencias
  searchForm.addEventListener('click', (e) => {
    const suggestionItem = e.target.closest('.suggestion-item');
    if (suggestionItem) {
      const index = parseInt(suggestionItem.dataset.index);
      if (currentSuggestions[index]) {
        window.location.href = currentSuggestions[index].url;
      }
    }
  });
  
  // Estilos para las sugerencias
  // Los estilos ahora están en style.css
})();

// ================== Tema Matrix con F12 ==================
(function matrixThemeInit() {
  const root = document.documentElement;
  const matrixKey = "ugb_matrix_theme";
  let matrixActive = false;
  
  // Cargar estado del tema Matrix
  const savedMatrixState = localStorage.getItem(matrixKey);
  if (savedMatrixState === "true") {
    activateMatrixTheme();
  }
  
  function activateMatrixTheme() {
    matrixActive = true;
    root.setAttribute("data-theme", "matrix");
    localStorage.setItem(matrixKey, "true");
    
    // Agregar clase especial para efectos adicionales
    document.body.classList.add("matrix-active");
    
    // Crear efecto de lluvia de caracteres Matrix
    createMatrixRain();
    
    // Crear efecto de partículas flotantes
    createMatrixParticles();
    
    console.log("Tema Matrix activado");
  }
  
  function deactivateMatrixTheme() {
    matrixActive = false;
    root.removeAttribute("data-theme");
    document.body.classList.remove("matrix-active");
    
    // Restaurar tema guardado
    const saved = localStorage.getItem("ugb_theme");
    if (saved === "light" || saved === "dark" || saved === "matrix") {
      root.setAttribute("data-theme", saved);
    }
    
    // Limpiar efecto de lluvia
    const matrixRain = document.getElementById("matrix-rain");
    if (matrixRain) {
      matrixRain.remove();
    }
    
    // Limpiar efecto de partículas
    const matrixParticles = document.getElementById("matrix-particles");
    if (matrixParticles) {
      matrixParticles.remove();
    }
    
    localStorage.setItem(matrixKey, "false");
    console.log("Tema Matrix desactivado");
  }
  
  function createMatrixRain() {
    // Remover lluvia existente si existe
    const existingRain = document.getElementById("matrix-rain");
    if (existingRain) {
      existingRain.remove();
    }
    
    const rain = document.createElement("div");
    rain.id = "matrix-rain";
    rain.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
      overflow: hidden;
      font-family: 'Inconsolata', monospace;
      font-size: 14px;
      color: #00ff00;
      opacity: 0.3;
    `;
    
    document.body.appendChild(rain);
    
    // Crear columnas de caracteres Matrix solo en los costados
    const characters = "01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン";
    const containerWidth = window.innerWidth;
    const centerStart = containerWidth * 0.2; // 20% desde la izquierda
    const centerEnd = containerWidth * 0.8;   // 80% desde la izquierda
    const columnWidth = 20;
    
    // Crear columnas en el lado izquierdo (0% a 20%)
    const leftColumns = Math.floor(centerStart / columnWidth);
    for (let i = 0; i < leftColumns; i++) {
      const column = document.createElement("div");
      column.style.cssText = `
        position: absolute;
        top: -100px;
        left: ${i * columnWidth}px;
        width: ${columnWidth}px;
        height: 100vh;
        animation: matrixFall ${3 + Math.random() * 2}s linear infinite;
        animation-delay: ${Math.random() * 2}s;
      `;
      
      // Generar caracteres para la columna
      let columnText = "";
      for (let j = 0; j < 50; j++) {
        columnText += characters[Math.floor(Math.random() * characters.length)] + "<br>";
      }
      column.innerHTML = columnText;
      
      rain.appendChild(column);
    }
    
    // Crear columnas en el lado derecho (80% a 100%)
    const rightColumns = Math.floor((containerWidth - centerEnd) / columnWidth);
    for (let i = 0; i < rightColumns; i++) {
      const column = document.createElement("div");
      column.style.cssText = `
        position: absolute;
        top: -100px;
        left: ${centerEnd + (i * columnWidth)}px;
        width: ${columnWidth}px;
        height: 100vh;
        animation: matrixFall ${3 + Math.random() * 2}s linear infinite;
        animation-delay: ${Math.random() * 2}s;
      `;
      
      // Generar caracteres para la columna
      let columnText = "";
      for (let j = 0; j < 50; j++) {
        columnText += characters[Math.floor(Math.random() * characters.length)] + "<br>";
      }
      column.innerHTML = columnText;
      
      rain.appendChild(column);
    }
    
    // Agregar CSS para la animación
    if (!document.getElementById("matrix-rain-styles")) {
      const style = document.createElement("style");
      style.id = "matrix-rain-styles";
      style.textContent = `
        @keyframes matrixFall {
          0% { transform: translateY(-100vh); }
          100% { transform: translateY(100vh); }
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  function createMatrixParticles() {
    // Remover partículas existentes si existen
    const existingParticles = document.getElementById("matrix-particles");
    if (existingParticles) {
      existingParticles.remove();
    }
    
    const particles = document.createElement("div");
    particles.id = "matrix-particles";
    particles.className = "matrix-particles";
    document.body.appendChild(particles);
  }
  
  // Detectar F12
  document.addEventListener("keydown", (e) => {
    if (e.key === "F12") {
      e.preventDefault();
      
      if (matrixActive) {
        deactivateMatrixTheme();
      } else {
        activateMatrixTheme();
      }
    }
  });
  
  // Redimensionar la lluvia cuando cambie el tamaño de la ventana
  window.addEventListener("resize", () => {
    if (matrixActive) {
      createMatrixRain();
      createMatrixParticles();
    }
  });
  
  // Limpiar efectos al cambiar de página
  window.addEventListener("beforeunload", () => {
    if (matrixActive) {
      const matrixRain = document.getElementById("matrix-rain");
      if (matrixRain) {
        matrixRain.remove();
      }
      const matrixParticles = document.getElementById("matrix-particles");
      if (matrixParticles) {
        matrixParticles.remove();
      }
    }
  });
})();

// ================== Player Global Persistente ==================
/**
 * Inicializa el reproductor de música global
 * Maneja la cola de reproducción, controles y persistencia del estado (por ahora solo una cancion)
 * 
 * @function playerInit
 * @returns {void}
 */
(function playerInit() {
  const bar = document.getElementById("ugb-player");
  const audio = document.getElementById("ugb-audio");
  const tEl = document.getElementById("ugb-title");
  const sEl = document.getElementById("ugb-sub");
  const seek = document.getElementById("ugb-seek");
  const vol = document.getElementById("ugb-vol");
  const coverEl = document.getElementById("ugb-cover");
  const currentTimeEl = document.getElementById("ugb-current-time");
  const durationEl = document.getElementById("ugb-duration");
  const loadingEl = document.getElementById("ugb-loading");
  const playBtn = bar ? bar.querySelector('[data-act="play"]') : null;
  const prevBtn = bar ? bar.querySelector('[data-act="prev"]') : null;
  const nextBtn = bar ? bar.querySelector('[data-act="next"]') : null;
  const muteBtn = bar ? bar.querySelector('[data-act="mute"]') : null;
  const closeBtn = bar ? bar.querySelector('[data-act="close"]') : null;

  if (!bar || !audio) return;

  const STORE_KEY = "ugb_player_state_v1";

  let queue = []; // [{src,title,artist,cover,releaseId,releaseTitle}]
  let index = 0;
  let restoring = false;
  let currentQueueId = null; // para resaltar la fila actual
  let currentRow = null;

  // Función para verificar si estamos en la página de lanzamiento
  function isLanzamientoPage() {
    return window.location.pathname.includes('lanzamiento.php') || 
           window.location.href.includes('lanzamiento.php');
  }

  function saveState(pausedOverride = null) {
    const state = {
      queue,
      index,
      queueId: currentQueueId,
      currentTime: audio.currentTime || 0,
      volume: audio.volume,
      paused: pausedOverride !== null ? pausedOverride : audio.paused,
    };
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch (_e) {}
  }

  function loadState() {
    // Si ya tenemos una cola activa y reproduciendo, no sobrescribir
    if (queue.length > 0 && !audio.paused) {
      console.log('Ya hay una cola activa reproduciendo, no cargando estado');
      return;
    }

    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (!raw) {
        console.log('No hay estado guardado en localStorage');
        return;
      }
      const s = JSON.parse(raw);
      if (!Array.isArray(s.queue) || s.queue.length === 0) {
        console.log('Cola guardada vacía o inválida');
        return;
      }

      // Validar que la canción actual existe
      const savedIndex = Math.min(Math.max(0, s.index | 0), s.queue.length - 1);
      const currentTrack = s.queue[savedIndex];
      
      if (!currentTrack || !currentTrack.src) {
        console.log('Canción guardada no válida, limpiando estado');
        localStorage.removeItem(STORE_KEY);
        return;
      }

      console.log('Cargando estado del reproductor:', {
        queueLength: s.queue.length,
        currentIndex: savedIndex,
        wasPlaying: !s.paused,
        currentTime: s.currentTime
      });

      queue = s.queue;
      index = savedIndex;
      currentQueueId = s.queueId || null;

      audio.volume =
        typeof s.volume === "number" ? Math.min(1, Math.max(0, s.volume)) : 1;
      setUI(currentTrack);
      
      // Mostrar reproductor
      bar.hidden = false;
      bar.style.display = '';
      console.log('Reproductor mostrado y restaurado');
      
      restoring = true;
      audio.src = currentTrack.src;

      audio.addEventListener(
        "loadedmetadata",
        () => {
          try {
            audio.currentTime = Math.min(
              s.currentTime || 0,
              audio.duration || s.currentTime || 0
            );
          } catch (_e) {}
          // Reanudar reproducción si estaba reproduciendo antes
          if (!s.paused) {
            console.log('Intentando reanudar reproducción automáticamente...');
            audio.play().then(() => {
              console.log('Reproducción reanudada automáticamente');
              setUI(currentTrack); // Actualizar UI después de reproducir
            }).catch((error) => {
              console.log('No se pudo reanudar la reproducción automáticamente:', error);
              setUI(currentTrack); // Actualizar UI incluso si falla
            });
          } else {
            console.log('Estado guardado indica que estaba pausado, no reanudando');
          }
          restoring = false;
          markRow(); // intenta resaltar si está la vista
        },
        { once: true }
      );
    } catch (error) {
      console.error('Error al cargar estado del reproductor:', error);
    }
  }

  function setUI(track) {
    tEl.textContent = track ? track.title : "—";
    
    if (track) {
      // Extraer el ID del artista del formato "@username"
      const artistId = track.artistId || null;
      const artistName = track.artist || "";
      const releaseTitle = track.releaseTitle || "";
      const releaseId = track.releaseId || null;
      
      console.log('setUI - track data:', {
        artistId,
        artistName,
        releaseTitle,
        releaseId,
        fullTrack: track
      });
      
      // Crear enlaces para artista y álbum
      let artistLink = artistName;
      if (artistName) {
        // Buscar el artist_id en el DOM de forma simple
        let foundArtistId = null;
        
        // Buscar en todos los botones de reproducción
        const allButtons = document.querySelectorAll('[data-artist-id]');
        for (let btn of allButtons) {
          if (btn.dataset.artist === artistName) {
            foundArtistId = btn.dataset.artistId;
            break;
          }
        }
        
        // Si no encontramos, buscar por releaseId
        if (!foundArtistId && releaseId) {
          const releaseButtons = document.querySelectorAll(`[data-release-id="${releaseId}"]`);
          for (let btn of releaseButtons) {
            if (btn.dataset.artistId) {
              foundArtistId = btn.dataset.artistId;
              break;
            }
          }
        }
        
        console.log('ArtistId encontrado:', foundArtistId, 'para artista:', artistName);
        
        if (foundArtistId && foundArtistId !== "0") {
          artistLink = `<a href="perfil.php?id=${foundArtistId}" class="profile-link">${artistName}</a>`;
        }
      }
      
      let releaseLink = releaseTitle;
      if (releaseId && releaseTitle) {
        releaseLink = `<a href="lanzamiento.php?id=${releaseId}" class="profile-link">${releaseTitle}</a>`;
      }
      
      sEl.innerHTML = `${artistLink} — ${releaseLink}`;
      console.log('HTML generado para el reproductor:', sEl.innerHTML);
    } else {
      sEl.textContent = "—";
    }
    if (coverEl) {
      if (track && track.cover) {
        coverEl.src = track.cover;
        coverEl.hidden = false;
        coverEl.style.background = 'none';
      } else {
        // Mostrar placeholder cuando no hay imagen
        coverEl.hidden = false;
        coverEl.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTYiIGhlaWdodD0iNTYiIHZpZXdCb3g9IjAgMCA1NiA1NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjU2IiBoZWlnaHQ9IjU2IiBmaWxsPSIjMzMzMzMzIi8+CjxwYXRoIGQ9Ik0yOCAyMkMzMC4yMDkxIDIyIDMyIDIzLjc5MDkgMzIgMjZDMzIgMjguMjA5MSAzMC4yMDkxIDMwIDI4IDMwQzI1Ljc5MDkgMzAgMjQgMjguMjA5MSAyNCAyNkMyNCAyMy43OTA5IDI1Ljc5MDkgMjIgMjggMjJaIiBmaWxsPSIjNjY2NjY2Ii8+CjxwYXRoIGQ9Ik0yMCAzNkMzMCAzNiAzOCAzNCA0MiAzMkM0MiAzNCAzOCAzNiAyOCAzNkMyMCAzNiAyMCAzNiAyMCAzNloiIGZpbGw9IiM2NjY2NjYiLz4KPC9zdmc+';
        coverEl.style.background = 'linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%)';
      }
    }
    
    // Actualizar estado de reproducción
    const isPlaying = !audio.paused && !audio.ended && audio.readyState >= 2;
    document.body.classList.toggle("is-playing", isPlaying);
    if (playBtn) {
      playBtn.textContent = isPlaying ? "⏸" : "▶︎";
    }
    
    // Actualizar tiempo
    updateTime();
  }

  function updateTime() {
    const current = audio.currentTime || 0;
    const duration = audio.duration || 0;
    
    if (currentTimeEl) {
      currentTimeEl.textContent = formatTime(current);
    }
    if (durationEl) {
      durationEl.textContent = formatTime(duration);
    }
    
    // Actualizar barra de progreso solo si hay duración válida
    if (duration > 0 && !isNaN(duration)) {
      const progress = Math.min(100, Math.max(0, (current / duration) * 100));
      document.documentElement.style.setProperty('--progress', `${progress}%`);
      
      // Actualizar el slider de progreso
      if (seek) {
        seek.value = progress;
      }
    }
    
    // Actualizar barra de volumen
    const volume = Math.min(100, Math.max(0, (audio.volume || 0) * 100));
    document.documentElement.style.setProperty('--volume', `${volume}%`);
  }

  function formatTime(seconds) {
    if (!isFinite(seconds) || seconds < 0) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  function markRow() {
    if (!currentQueueId) return;
    if (currentRow) currentRow.classList.remove("playing");
    const scope = document.querySelector(
      `.tracks[data-queue-id="${currentQueueId}"]`
    );
    if (!scope) return;
    const btn = scope.querySelector(
      `.track-play[data-queue-id="${currentQueueId}"][data-index="${index}"]`
    );
    if (!btn) return;
    currentRow = btn.closest(".track");
    if (currentRow) currentRow.classList.add("playing");
  }

  function playIdx(i) {
    if (!queue.length) {
      hidePlayer();
      return;
    }
    
    index = (i + queue.length) % queue.length;
    const tr = queue[index];
    
    // Validar que la canción existe
    if (!tr || !tr.src) {
      console.error('Canción no válida:', tr);
      next();
      return;
    }
    
    // Si no tenemos artistId, intentar obtenerlo del releaseId
    if (!tr.artistId || tr.artistId <= 0) {
      const releaseButtons = document.querySelectorAll(`[data-release-id="${tr.releaseId}"]`);
      for (let btn of releaseButtons) {
        if (btn.dataset.artistId) {
          tr.artistId = parseInt(btn.dataset.artistId, 10);
          console.log('ArtistId obtenido del DOM:', tr.artistId);
          break;
        }
      }
    }
    
    // Pausar audio actual antes de cambiar
    audio.pause();
    audio.currentTime = 0;
    
    // Mostrar reproductor solo si no estamos en página de lanzamiento
    if (!isLanzamientoPage()) {
      bar.hidden = false;
      bar.style.display = '';
    }
    
    // Actualizar UI inmediatamente
    setUI(tr);
    showLoading();
    
    // Cargar nueva canción
    audio.src = tr.src;
    
     // Intentar reproducir
     const playPromise = audio.play();
     if (playPromise !== undefined) {
       playPromise
         .then(() => {
           hideLoading();
           setUI(tr); // Actualizar UI después de reproducir
           saveState(false);
           markRow();
           console.log('Reproduciendo:', tr.title);
         })
         .catch((error) => {
           hideLoading();
           setUI(tr); // Actualizar UI incluso si falla
           console.error('Error al reproducir:', error);
           // Intentar siguiente canción si falla
           setTimeout(() => next(), 1000);
         });
     }
    
    // Actualizar Media Session
    if ("mediaSession" in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: tr.title,
        artist: tr.artist,
        album: tr.releaseTitle || "",
        artwork: tr.cover
          ? [{ src: tr.cover, sizes: "512x512", type: "image/jpeg" }]
          : [],
      });
    }
  }

  function hidePlayer() {
    // Pausar y limpiar audio
    audio.pause();
    audio.src = '';
    audio.currentTime = 0;
    
    // Limpiar cola y estado
    queue = [];
    index = 0;
    currentQueueId = null;
    
    // Limpiar UI
    if (currentRow) currentRow.classList.remove("playing");
    currentRow = null;
    
    // Ocultar loading
    hideLoading();
    
    // Actualizar UI
    setUI(null);
    
    // Ocultar reproductor completamente
    bar.hidden = true;
    bar.style.display = 'none';
    
    // Limpiar localStorage
    localStorage.removeItem(STORE_KEY);
  }
  
  function checkPlayerVisibility() {
    // Ocultar si no hay cola o no hay audio
    if (queue.length === 0 || !audio.src) {
      if (!bar.hidden) {
        bar.hidden = true;
      }
      return;
    }
    
    // Mostrar reproductor si hay contenido (en todas las páginas)
    // Solo cambiar si el estado actual es diferente
    if (bar.hidden) {
      bar.hidden = false;
      bar.style.display = '';
    }
  }
  
  function showLoading() {
    if (loadingEl) {
      loadingEl.style.display = 'flex';
    }
  }
  
  function hideLoading() {
    if (loadingEl) {
      loadingEl.style.display = 'none';
    }
  }

  function next() {
    playIdx(index + 1);
  }
  
  function prev() {
    playIdx(index - 1);
  }

  // Controles
  if (playBtn) {
    playBtn.addEventListener("click", () => {
      console.log('Botón play/pause clickeado, estado actual:', {
        paused: audio.paused,
        queueLength: queue.length,
        currentIndex: index
      });
      
      if (audio.paused) {
        console.log('Iniciando reproducción...');
        audio
          .play()
          .then(() => {
            console.log('Reproducción iniciada exitosamente');
            saveState(false);
            setUI(queue[index]);
          })
          .catch((error) => {
            console.error('Error al iniciar reproducción:', error);
            setUI(queue[index]);
          });
      } else {
        console.log('Pausando reproducción...');
        audio.pause();
        saveState(true);
        setUI(queue[index]);
      }
    });
  }
  if (nextBtn) nextBtn.addEventListener("click", next);
  if (prevBtn) prevBtn.addEventListener("click", prev);
  
  
  // Botón de mute
  if (muteBtn) {
    muteBtn.addEventListener("click", () => {
      if (audio.muted) {
        audio.muted = false;
        muteBtn.textContent = '🔊';
      } else {
        audio.muted = true;
        muteBtn.textContent = '🔇';
      }
    });
  }

  // Botón de cerrar
  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      hidePlayer();
    });
  }
  
  

  vol.addEventListener("input", () => {
    audio.volume = parseFloat(vol.value);
    saveState();
  });
  seek.addEventListener("input", () => {
    if (!isFinite(audio.duration) || audio.duration <= 0) return;
    const pct = Math.max(0, Math.min(100, parseFloat(seek.value))) / 100;
    audio.currentTime = pct * audio.duration;
  });
  
  // Mejorar la experiencia de búsqueda
  let wasPlaying = false;
  seek.addEventListener("mousedown", () => {
    wasPlaying = !audio.paused;
    if (wasPlaying) {
      audio.pause();
    }
  });
  
  seek.addEventListener("mouseup", () => {
    if (wasPlaying) {
      audio.play().catch(() => {});
    }
  });

  audio.addEventListener("timeupdate", () => {
    if (!isFinite(audio.duration) || audio.duration <= 0) return;
    const pct = Math.max(
      0,
      Math.min(100, (audio.currentTime / audio.duration) * 100)
    );
    seek.value = String(pct | 0);
    updateTime();
    if (!restoring) saveState();
  });
  
  audio.addEventListener("loadedmetadata", () => {
    updateTime();
  });
  
  audio.addEventListener("ended", () => {
    console.log('Canción terminada, pasando a la siguiente');
    next();
  });

  // Manejar errores de audio
  audio.addEventListener("error", (e) => {
    console.error('Error de audio:', e);
    console.log('Archivo que falló:', audio.src);
    console.log('Tipo MIME:', audio.type);
    const error = audio.error;
    if (error) {
      switch (error.code) {
        case error.MEDIA_ERR_ABORTED:
          console.log('Reproducción abortada');
          break;
        case error.MEDIA_ERR_NETWORK:
          console.log('Error de red al cargar audio');
          break;
        case error.MEDIA_ERR_DECODE:
          console.log('Error al decodificar audio');
          break;
        case error.MEDIA_ERR_SRC_NOT_SUPPORTED:
          console.log('Formato de audio no soportado');
          console.log('Archivo:', audio.src);
          console.log('Tipo:', audio.type);
          break;
        default:
          console.log('Error desconocido de audio');
      }
    }
    // No intentar siguiente canción automáticamente en página de lanzamiento
    if (!isLanzamientoPage()) {
      setTimeout(() => next(), 2000);
    }
  });

  // Manejar cuando no se puede cargar el audio
  audio.addEventListener("loadstart", () => {
    console.log('Cargando audio...');
    console.log('URL del audio:', audio.src);
    console.log('Tipo MIME:', audio.type);
    showLoading();
  });

  audio.addEventListener("canplay", () => {
    console.log('Audio listo para reproducir');
    hideLoading();
  });

  audio.addEventListener("stalled", () => {
    console.log('Carga de audio estancada');
    showLoading();
  });
  
  audio.addEventListener("waiting", () => {
    console.log('Esperando datos de audio...');
    showLoading();
  });
  
   audio.addEventListener("playing", () => {
     console.log('Audio reproduciéndose');
     hideLoading();
     setUI(queue[index]); // Actualizar UI cuando empiece a reproducir
   });
   
   audio.addEventListener("pause", () => {
     console.log('Audio pausado');
     setUI(queue[index]); // Actualizar UI cuando se pause
   });

  // Media Session API
  if ("mediaSession" in navigator) {
    navigator.mediaSession.setActionHandler("play", () => {
      audio.play();
      setUI(queue[index]);
      saveState(false);
    });
    navigator.mediaSession.setActionHandler("pause", () => {
      audio.pause();
      setUI(queue[index]);
      saveState(true);
    });
    navigator.mediaSession.setActionHandler("previoustrack", prev);
    navigator.mediaSession.setActionHandler("nexttrack", next);
  }

  // Armar cola desde el DOM
  function collectQueue(queueId) {
    console.log(`Buscando elementos con selector: .tracks[data-queue-id="${queueId}"] .track-play`);
    
    const buttons = document.querySelectorAll(
      `.tracks[data-queue-id="${queueId}"] .track-play`
    );
    
    console.log(`Encontrados ${buttons.length} botones de pista`);
    
    if (buttons.length === 0) {
      console.warn(`No se encontraron botones de pista para queueId: ${queueId}`);
      // Intentar buscar de otra manera
      const alternativeButtons = document.querySelectorAll(`[data-queue-id="${queueId}"]`);
      console.log(`Botones alternativos encontrados:`, alternativeButtons);
    }
    
    const q = [];
    
    buttons.forEach((btn, i) => {
      const src = btn.dataset.src;
      const title = btn.dataset.title || `Pista ${i + 1}`;
      
      console.log(`Botón ${i}:`, {
        src: src,
        title: title,
        artist: btn.dataset.artist,
        artistId: btn.dataset.artistId,
        releaseTitle: btn.dataset.releaseTitle,
        releaseId: btn.dataset.releaseId,
        cover: btn.dataset.cover,
        dataset: btn.dataset
      });
      
      // Solo agregar si tiene src válido
      if (src && src.trim() !== '') {
        const artistId = parseInt(btn.dataset.artistId || "0", 10);
        console.log('Procesando botón:', {
          artistId: artistId,
          artistIdRaw: btn.dataset.artistId,
          artist: btn.dataset.artist
        });
        
        q.push({
          src: src.trim(),
          title: title.trim(),
          artist: (btn.dataset.artist || "").trim(),
          artistId: artistId,
          cover: (btn.dataset.cover || "").trim(),
          releaseId: parseInt(btn.dataset.releaseId || "0", 10),
          releaseTitle: (btn.dataset.releaseTitle || "").trim(),
        });
      } else {
        console.warn(`Pista ${i + 1} sin src válido, omitiendo:`, src);
      }
    });
    
    console.log(`Cola recopilada: ${q.length} canciones válidas de ${buttons.length} botones`);
    console.log('Datos de la cola:', q);
    return q;
  }

  // Click en una pista
  document.addEventListener("click", (ev) => {
    const btn = ev.target.closest(".track-play");
    if (!btn) return;
    
    const qid = btn.dataset.queueId;
    const start = parseInt(btn.dataset.index || "0", 10);
    
    if (!qid) {
      console.warn('Botón de pista sin queueId');
      return;
    }
    
    const q = collectQueue(qid);
    if (!q.length) {
      console.warn('No se encontraron canciones válidas en la cola');
      return;
    }
    
    // Validar que el índice de inicio sea válido
    const validStart = Math.max(0, Math.min(start, q.length - 1));
    
    console.log(`Reproduciendo cola ${qid} desde índice ${validStart}`);
    currentQueueId = qid;
    queue = q;
    saveState(true);
    playIdx(validStart);
  });

  // Click en "Reproducir" (play-all)
  document.addEventListener("click", (ev) => {
    const btn = ev.target.closest(".play-all");
    if (!btn) return;
    
    console.log('Botón play-all clickeado:', btn);
    
    const qid = btn.dataset.queueId;
    if (!qid) {
      console.warn('Botón play-all sin queueId');
      return;
    }
    
    console.log('Buscando cola con ID:', qid);
    const q = collectQueue(qid);
    console.log('Cola encontrada:', q);
    
    if (!q.length) {
      console.warn('No se encontraron canciones válidas para reproducir todo');
      return;
    }
    
    console.log(`Reproduciendo toda la cola ${qid} con ${q.length} canciones`);
    currentQueueId = qid;
    queue = q;
    index = 0;
    saveState(true);
    playIdx(0);
  });

  // Restaurar si había algo sonando antes
  loadState();

  // Escuchar cambios en la URL para actualizar visibilidad
  window.addEventListener('popstate', () => {
    console.log('popstate event - URL:', window.location.href);
    // Siempre restaurar estado al navegar
    setTimeout(() => loadState(), 50); // Pequeño delay para asegurar que el DOM esté listo
  });

  // Escuchar cambios en el hash también
  window.addEventListener('hashchange', () => {
    console.log('hashchange event - URL:', window.location.href);
    // Siempre restaurar estado al navegar
    setTimeout(() => loadState(), 50);
  });

  // Escuchar cambios en el DOM para detectar navegación (solo cambios de URL)
  let lastUrl = window.location.href;
  let isProcessing = false;
  
  const observer = new MutationObserver(() => {
    if (isProcessing) return; // Evitar procesamiento múltiple
    
    const currentUrl = window.location.href;
    if (currentUrl !== lastUrl) {
      isProcessing = true;
      lastUrl = currentUrl;
      
       setTimeout(() => {
         console.log('Detectado cambio de página, restaurando reproductor');
         // Siempre restaurar estado al navegar
         loadState();
         isProcessing = false;
       }, 100); // Pequeño delay para evitar bucles
    }
  });
  
  observer.observe(document.body, {
    childList: true,
    subtree: false  // Solo observar cambios directos en el body, no en toda la estructura
  });

  // También verificar visibilidad al cargar la página
  checkPlayerVisibility();

  // El reproductor se mostrará automáticamente si hay contenido

  // Mecanismo de respaldo: verificar cada 5 segundos si el reproductor debería estar activo
  // Solo si realmente hay contenido y está pausado
  setInterval(() => {
    if (queue.length > 0 && audio.src && audio.paused && !restoring) {
      // Si tenemos una cola, audio cargado pero está pausado, verificar si debería estar reproduciendo
      try {
        const raw = localStorage.getItem(STORE_KEY);
        if (raw) {
          const s = JSON.parse(raw);
          if (s && !s.paused && s.queue && s.queue.length > 0 && s.index === index) {
            console.log('Mecanismo de respaldo: reanudando reproducción');
            audio.play().catch(() => {
              console.log('No se pudo reanudar con el mecanismo de respaldo');
            });
          }
        }
      } catch (_e) {
        // Ignorar errores de parsing
      }
    }
  }, 5000);
})();

// ================== Modal de Eventos ==================
(function eventModalInit() {
  let modal, form, openBtn, closeBtn, cancelBtn, titleEl;

  function initModal() {
    modal = document.getElementById('event-modal');
    form = document.getElementById('event-form');
    openBtn = document.getElementById('open-create');
    closeBtn = document.getElementById('close-modal');
    cancelBtn = document.getElementById('cancel-modal');
    titleEl = document.getElementById('event-form-title');

    if (!modal || !form) return;

    console.log('Modal elements:', {modal, form, openBtn, closeBtn, cancelBtn, titleEl});

    function openModal(mode, data) {
      console.log('Opening modal:', mode, data);
      modal.classList.add('show');
      if (mode === 'edit') {
        titleEl.textContent = 'Editar evento';
        form.querySelector('input[name="action"]').value = 'update';
        form.querySelector('input[name="id"]').value = data.id || '';
        form.querySelector('input[name="title"]').value = data.title || '';
        form.querySelector('input[name="event_dt"]').value = data.event_dt || '';
        form.querySelector('input[name="place_name"]').value = data.place_name || '';
        form.querySelector('input[name="place_address"]').value = data.place_address || '';
        form.querySelector('input[name="maps_url"]').value = data.maps_url || '';
      } else {
        titleEl.textContent = 'Nuevo evento';
        form.querySelector('input[name="action"]').value = 'create';
        form.reset();
        form.querySelector('input[name="id"]').value = '';
      }
    }
    
    function closeModal() { 
      console.log('Closing modal');
      modal.classList.remove('show'); 
    }
    
    if (openBtn) {
      console.log('Adding click listener to open button');
      openBtn.addEventListener('click', function(e) {
        e.preventDefault();
        openModal('create', {});
      });
    } else {
      console.log('Open button not found');
    }
    
    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }
    if (cancelBtn) {
      cancelBtn.addEventListener('click', closeModal);
    }

    // Click en botones de editar
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('[data-edit-event]');
      if (!btn) return;
      e.preventDefault();
      console.log('Edit button clicked');
      const data = JSON.parse(btn.getAttribute('data-edit-event'));
      openModal('edit', data);
    });
  }

  // Inicializar cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModal);
  } else {
    initModal();
  }
})();

// ================== BLOG EDITOR ==================
(function blogEditorInit() {
  let editor, preview, splitEditor, splitPreview;
  let currentTab = 'write';

  function initEditor() {
    editor = document.getElementById('content');
    preview = document.getElementById('preview-content');
    splitEditor = document.getElementById('content-split');
    splitPreview = document.getElementById('preview-content-split');

    if (!editor) return;

    // Inicializar tabs
    initTabs();
    
    // Inicializar toolbar
    initToolbar();
    
    // Inicializar vista previa
    initPreview();
    
    // Sincronizar editores
    syncEditors();
  }

  function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const panels = document.querySelectorAll('.editor-panel');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        switchTab(tab);
      });
    });
  }

  function switchTab(tab) {
    currentTab = tab;
    
    // Actualizar botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    
    // Mostrar/ocultar paneles
    document.querySelectorAll('.editor-panel').forEach(panel => {
      panel.style.display = 'none';
    });
    
    if (tab === 'write') {
      document.getElementById('write-panel').style.display = 'block';
    } else if (tab === 'preview') {
      document.getElementById('preview-panel').style.display = 'block';
      updatePreview();
    } else if (tab === 'split') {
      document.getElementById('split-panel').style.display = 'block';
      updateSplitPreview();
    }
  }

  function initToolbar() {
    const toolbar = document.querySelector('.markdown-toolbar');
    if (!toolbar) return;

    toolbar.addEventListener('click', (e) => {
      const btn = e.target.closest('.toolbar-btn');
      if (!btn) return;

      const action = btn.dataset.action;
      const activeEditor = currentTab === 'split' ? splitEditor : editor;
      
      if (activeEditor) {
        insertMarkdown(activeEditor, action);
      }
    });
  }

  function insertMarkdown(textarea, action) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let replacement = '';

    switch (action) {
      case 'bold':
        replacement = `**${selectedText || 'texto en negrita'}**`;
        break;
      case 'italic':
        replacement = `*${selectedText || 'texto en cursiva'}*`;
        break;
      case 'heading':
        replacement = `## ${selectedText || 'Título'}`;
        break;
      case 'link':
        replacement = `[${selectedText || 'texto del enlace'}](https://ejemplo.com)`;
        break;
      case 'image':
        replacement = `![${selectedText || 'texto alternativo'}](https://ejemplo.com/imagen.jpg)`;
        break;
      case 'list':
        replacement = `- ${selectedText || 'elemento de lista'}`;
        break;
      case 'quote':
        replacement = `> ${selectedText || 'cita'}`;
        break;
      case 'code':
        if (selectedText.includes('\n')) {
          replacement = `\`\`\`\n${selectedText || 'código'}\n\`\`\``;
        } else {
          replacement = `\`${selectedText || 'código'}\``;
        }
        break;
      case 'hr':
        replacement = '---';
        break;
    }

    // Insertar el texto
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Actualizar selección
    const newStart = start + replacement.length;
    textarea.setSelectionRange(newStart, newStart);
    textarea.focus();
    
    // Actualizar vista previa
    updatePreview();
  }

  function initPreview() {
    if (editor) {
      editor.addEventListener('input', updatePreview);
    }
    if (splitEditor) {
      splitEditor.addEventListener('input', updateSplitPreview);
    }
  }

  function updatePreview() {
    if (preview && editor) {
      preview.innerHTML = renderMarkdown(editor.value);
    }
  }

  function updateSplitPreview() {
    if (splitPreview && splitEditor) {
      splitPreview.innerHTML = renderMarkdown(splitEditor.value);
    }
  }

  function syncEditors() {
    if (editor && splitEditor) {
      editor.addEventListener('input', () => {
        splitEditor.value = editor.value;
        updateSplitPreview();
      });
      
      splitEditor.addEventListener('input', () => {
        editor.value = splitEditor.value;
        updatePreview();
      });
    }
  }

  function renderMarkdown(markdown) {
    if (!markdown) return '<p class="text-muted">Escribe algo para ver la vista previa...</p>';
    
    // Headers
    markdown = markdown.replace(/^### (.*$)/gm, '<h3>$1</h3>');
    markdown = markdown.replace(/^## (.*$)/gm, '<h2>$1</h2>');
    markdown = markdown.replace(/^# (.*$)/gm, '<h1>$1</h1>');
    
    // Bold y italic
    markdown = markdown.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    markdown = markdown.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Links
    markdown = markdown.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    
    // Images
    markdown = markdown.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy" style="max-width: 100%; height: auto;">');
    
    // Code blocks
    markdown = markdown.replace(/```([^`]+)```/gs, '<pre><code>$1</code></pre>');
    markdown = markdown.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Lists
    markdown = markdown.replace(/^\- (.*$)/gm, '<li>$1</li>');
    markdown = markdown.replace(/^(\d+)\. (.*$)/gm, '<li>$2</li>');
    
    // Wrap consecutive list items in ul/ol
    markdown = markdown.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
    
    // Blockquotes
    markdown = markdown.replace(/^> (.*$)/gm, '<blockquote>$1</blockquote>');
    
    // Horizontal rules
    markdown = markdown.replace(/^---$/gm, '<hr>');
    
    // Paragraphs
    markdown = markdown.replace(/\n\n/g, '</p><p>');
    markdown = '<p>' + markdown + '</p>';
    
    // Clean up empty paragraphs
    markdown = markdown.replace(/<p><\/p>/g, '');
    
    return markdown;
  }

  // Guardar borrador
  function initSaveDraft() {
    const saveDraftBtn = document.getElementById('save-draft');
    if (saveDraftBtn) {
      saveDraftBtn.addEventListener('click', () => {
        const form = document.querySelector('.blog-editor-form');
        if (form) {
          const statusInput = form.querySelector('input[name="status"]');
          if (statusInput) {
            statusInput.value = 'draft';
          }
          form.submit();
        }
      });
    }
  }

  // Auto-guardar cada 30 segundos
  function initAutoSave() {
    if (editor) {
      setInterval(() => {
        const title = document.getElementById('title')?.value || '';
        const content = editor.value || '';
        
        if (title && content) {
          localStorage.setItem('blog_draft_title', title);
          localStorage.setItem('blog_draft_content', content);
        }
      }, 30000);
    }
  }

  // Cargar borrador guardado
  function loadDraft() {
    if (editor && !editor.value) {
      const title = localStorage.getItem('blog_draft_title');
      const content = localStorage.getItem('blog_draft_content');
      
      if (title) {
        const titleInput = document.getElementById('title');
        if (titleInput) titleInput.value = title;
      }
      
      if (content) {
        editor.value = content;
        if (splitEditor) splitEditor.value = content;
        updatePreview();
        updateSplitPreview();
      }
    }
  }

  // Inicializar cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initEditor();
      initSaveDraft();
      initAutoSave();
      loadDraft();
    });
  } else {
    initEditor();
    initSaveDraft();
    initAutoSave();
    loadDraft();
  }
})();

// ================== Menús móviles ==================
(function mobileMenusInit() {
  // Menú hamburguesa principal
  const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
  const menu = document.querySelector('.menu');
  const actions = document.querySelector('.actions');
  
  if (mobileMenuToggle && menu) {
    mobileMenuToggle.addEventListener('click', () => {
      const isExpanded = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
      
      // Toggle del botón hamburguesa
      mobileMenuToggle.setAttribute('aria-expanded', !isExpanded);
      
      // Toggle del menú
      menu.classList.toggle('open');
      
      // Cerrar actions si está abierto
      if (actions) {
        actions.classList.remove('open');
      }
    });
  }
  
  // Filtros toggle
  const filtersToggle = document.querySelector('.filters-toggle');
  const filtersForm = document.querySelector('.filters-form');
  const filtersToggleText = document.querySelector('.filters-toggle-text');
  const filtersToggleIcon = document.querySelector('.filters-toggle-icon');
  
  if (filtersToggle && filtersForm) {
    filtersToggle.addEventListener('click', () => {
      const isExpanded = filtersToggle.getAttribute('aria-expanded') === 'true';
      
      filtersToggle.setAttribute('aria-expanded', !isExpanded);
      filtersForm.classList.toggle('open');
      
      // Actualizar texto e ícono
      if (filtersToggleText && filtersToggleIcon) {
        if (!isExpanded) {
          filtersToggleText.textContent = 'Ocultar filtros';
          filtersToggleIcon.textContent = '▲';
        } else {
          filtersToggleText.textContent = 'Mostrar filtros';
          filtersToggleIcon.textContent = '▼';
        }
      }
    });
  }
  
  // Cerrar menús al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (mobileMenuToggle && menu && !mobileMenuToggle.contains(e.target) && !menu.contains(e.target)) {
      menu.classList.remove('open');
      mobileMenuToggle.setAttribute('aria-expanded', 'false');
    }
  });
  
  // Cerrar menús al hacer scroll
  window.addEventListener('scroll', () => {
    if (menu) menu.classList.remove('open');
    if (mobileMenuToggle) mobileMenuToggle.setAttribute('aria-expanded', 'false');
  });
})();

// ================== Profile Dropdown ==================
(function profileDropdownInit() {
  const profileBtn = document.querySelector('.profile-btn');
  const dropdownMenu = document.querySelector('.dropdown-menu');
  
  if (profileBtn && dropdownMenu) {
    // Toggle del dropdown al hacer click
    profileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = profileBtn.getAttribute('aria-expanded') === 'true';
      
      profileBtn.setAttribute('aria-expanded', !isExpanded);
      
      // Cerrar otros dropdowns si están abiertos
      document.querySelectorAll('.profile-btn[aria-expanded="true"]').forEach(btn => {
        if (btn !== profileBtn) {
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    });

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', (e) => {
      if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
        profileBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // Cerrar dropdown al hacer scroll
    window.addEventListener('scroll', () => {
      profileBtn.setAttribute('aria-expanded', 'false');
    });

    // Cerrar dropdown al presionar Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        profileBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // Manejar navegación con teclado
    profileBtn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        profileBtn.click();
      }
    });

    // Navegación con teclado en el dropdown
    dropdownMenu.addEventListener('keydown', (e) => {
      const items = Array.from(dropdownMenu.querySelectorAll('.dropdown-item'));
      const currentIndex = items.indexOf(e.target);
      
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          const nextIndex = (currentIndex + 1) % items.length;
          items[nextIndex].focus();
          break;
        case 'ArrowUp':
          e.preventDefault();
          const prevIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
          items[prevIndex].focus();
          break;
        case 'Home':
          e.preventDefault();
          items[0].focus();
          break;
        case 'End':
          e.preventDefault();
          items[items.length - 1].focus();
          break;
        case 'Escape':
          e.preventDefault();
          profileBtn.focus();
          profileBtn.setAttribute('aria-expanded', 'false');
          break;
      }
    });
  }
})();