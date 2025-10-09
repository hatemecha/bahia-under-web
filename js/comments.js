/**
 * Sistema de comentarios con paginación y respuestas anidadas
 * Maneja la carga, renderizado y interacción de comentarios en blogs y lanzamientos
 * 
 * @author Bahia Under Team
 * @version 2.2
 */

document.addEventListener('DOMContentLoaded', function() {
  console.log('=== INICIANDO SISTEMA DE COMENTARIOS ===');
  
  // Verificar que las variables estén disponibles
  if (typeof window.releaseId === 'undefined' && typeof window.blogId === 'undefined') {
    console.error('Variables JavaScript no disponibles, esperando...');
    setTimeout(function() {
      if (typeof window.releaseId !== 'undefined' || typeof window.blogId !== 'undefined') {
        initComments();
      } else {
        console.error('Variables JavaScript no disponibles después del timeout');
      }
    }, 1000);
    return;
  }
  
  initComments();
});

/**
 * Inicializa el sistema de comentarios
 * Configura elementos DOM, variables de estado y event listeners
 * 
 * @function initComments
 * @returns {void}
 */
function initComments() {
  const releaseId = window.releaseId || window.blogId;
  const commentsList = document.getElementById('comments-list');
  const commentsLoading = document.getElementById('comments-loading');
  const commentsEmpty = document.getElementById('comments-empty');
  const commentForm = document.getElementById('comment-form');
  
  console.log('Elementos encontrados:', {
    commentsList: !!commentsList,
    commentsLoading: !!commentsLoading,
    commentsEmpty: !!commentsEmpty,
    commentForm: !!commentForm
  });
  
  if (!commentsList || !commentsLoading || !commentsEmpty) {
    console.log('Elementos de comentarios no encontrados, saliendo...');
    return;
  }
  
  const targetId = window.releaseId || window.blogId;
  console.log('Variables disponibles:', {
    releaseId: window.releaseId,
    blogId: window.blogId,
    userId: window.userId,
    userRole: window.userRole,
    targetId: targetId
  });
  
  if (!targetId) {
    console.error('No se encontró ID de lanzamiento o blog, saliendo...');
    return;
  }
  
  // Flag para evitar cargas múltiples y estado de paginación
  let isLoading = false;
  let currentOffset = 0;
  let hasMoreComments = true;
  const commentsPerPage = 10;
  
  /**
   * Resetea el estado de paginación
   * Útil después de añadir o eliminar comentarios para recargar desde el inicio
   * 
   * @function resetPagination
   * @returns {void}
   */
  function resetPagination() {
    currentOffset = 0;
    hasMoreComments = true;
  }
  
  /**
   * Carga comentarios con paginación
   * Soporta carga inicial y carga adicional (append)
   * 
   * @function loadComments
   * @param {boolean} [append=false] - Si true, añade comentarios a la lista existente
   * @returns {void}
   */
  function loadComments(append = false) {
    if (isLoading) {
      console.log('Ya se están cargando comentarios, ignorando...');
      return;
    }
    
    if (!hasMoreComments && append) {
      console.log('No hay más comentarios para cargar');
      return;
    }
    
    isLoading = true;
    console.log('Cargando comentarios para ID:', targetId, 'Offset:', currentOffset);
    
    const type = window.releaseId ? 'release' : 'blog';
    
    // Timeout para evitar cargas infinitas
    const timeoutId = setTimeout(() => {
      console.error('Timeout cargando comentarios');
      isLoading = false;
      commentsLoading.innerHTML = '<p>Timeout cargando comentarios</p>';
    }, 10000); // 10 segundos
    
    fetch(`get-comments.php?type=${type}&target_id=${targetId}&offset=${currentOffset}&limit=${commentsPerPage}`)
      .then(response => {
        console.log('Respuesta del servidor:', response.status);
        clearTimeout(timeoutId);
        return response.json();
      })
      .then(data => {
        console.log('Datos de comentarios recibidos:', data);
        isLoading = false;
        commentsLoading.classList.add('hidden');
        
        if (data.success && data.comments.length > 0) {
          console.log('Mostrando comentarios:', data.comments.length);
          
          // Renderizar comentarios (append o replace según parámetro)
          if (append) {
            commentsList.insertAdjacentHTML('beforeend', renderComments(data.comments));
          } else {
            commentsList.innerHTML = renderComments(data.comments);
          }
          
          commentsList.classList.remove('hidden');
          commentsEmpty.classList.add('hidden');
          
          // Actualizar estado de paginación
          if (data.pagination) {
            currentOffset = data.pagination.next_offset || currentOffset;
            hasMoreComments = data.pagination.has_more || false;
            
            console.log('Estado de paginación actualizado:', {
              total: data.pagination.total,
              loaded: data.pagination.loaded,
              has_more: hasMoreComments,
              next_offset: currentOffset,
              offset_original: data.pagination.offset
            });
            
            // Mostrar/ocultar botón "Cargar más"
            updateLoadMoreButton(data.pagination);
          } else {
            console.log('No se recibió información de paginación en la respuesta');
          }
        } else if (!append) {
          console.log('No hay comentarios');
          commentsEmpty.classList.remove('hidden');
          commentsList.classList.add('hidden');
        }
      })
      .catch(err => {
        console.error('Error cargando comentarios:', err);
        clearTimeout(timeoutId);
        isLoading = false;
        commentsLoading.innerHTML = '<p>Error cargando comentarios</p>';
      });
  }
  
  /**
   * Renderiza una lista de comentarios en HTML
   * 
   * @function renderComments
   * @param {Array<Object>} comments - Array de objetos comentario
   * @returns {string} HTML renderizado de los comentarios
   */
  function renderComments(comments) {
    return comments.map(comment => {
      console.log('Renderizando comentario:', comment);
      const user = comment.user || {};
      const displayName = user.display_name || user.username || 'Usuario';
      const avatarPath = user.avatar_path;
      
      console.log('Datos del usuario en comentario:', {
        userId: user.id,
        username: user.username,
        displayName: displayName
      });
      
      // Asegurar que tenemos un ID válido - usar directamente comment.user_id
      const userId = comment.user_id || user.id;
      console.log('ID del usuario para enlace:', userId, 'de comment:', comment);
      
      return `
        <div class="comment" data-id="${comment.id}">
          <div class="comment-header">
            <div class="comment-author">
              ${avatarPath ? 
                `<img src="${avatarPath}" alt="${displayName}" class="comment-avatar">` :
                `<div class="comment-avatar default">${displayName.charAt(0).toUpperCase()}</div>`
              }
              <div>
                <strong><a href="perfil.php?id=${userId}" class="profile-link">${displayName}</a></strong>
                <span class="comment-date">${formatDate(comment.created_at)}</span>
              </div>
            </div>
            <div class="comment-actions">
              <button class="btn small reply-btn" data-comment-id="${comment.id}">Responder</button>
              ${canDeleteComment(comment) ? `<button class="btn small muted delete-btn" data-comment-id="${comment.id}">Eliminar</button>` : ''}
            </div>
          </div>
          <div class="comment-content">
            <p>${escapeHtml(comment.content)}</p>
          </div>
          <div class="comment-replies" id="replies-${comment.id}">
            ${comment.replies ? comment.replies.map(reply => renderComment(reply, true)).join('') : ''}
          </div>
          <div class="comment-reply-form hidden" id="reply-form-${comment.id}">
            <form class="reply-form" data-parent-id="${comment.id}">
              <textarea name="content" class="input" rows="2" placeholder="Escribe tu respuesta..." required></textarea>
              <div class="form-actions">
                <button type="submit" class="btn small primary">Responder</button>
                <button type="button" class="btn small cancel-reply-btn" data-comment-id="${comment.id}">Cancelar</button>
              </div>
            </form>
          </div>
        </div>
      `;
    }).join('');
  }
  
  /**
   * Renderiza un comentario individual en HTML
   * 
   * @function renderComment
   * @param {Object} comment - Objeto comentario con datos del usuario
   * @param {boolean} [isReply=false] - Si es una respuesta anidada
   * @returns {string} HTML del comentario individual
   */
  function renderComment(comment, isReply = false) {
    const user = comment.user || {};
    const displayName = user.display_name || user.username || 'Usuario';
    const avatarPath = user.avatar_path;
    // Obtener el ID del usuario desde comment.user_id (que viene de la BD)
    const commentUserId = comment.user_id || user.id || 0;
    
    return `
      <div class="comment ${isReply ? 'comment-reply' : ''}" data-id="${comment.id}">
        <div class="comment-header">
          <div class="comment-author">
            ${avatarPath ? 
              `<img src="${avatarPath}" alt="${displayName}" class="comment-avatar">` :
              `<div class="comment-avatar default">${displayName.charAt(0).toUpperCase()}</div>`
            }
            <div>
              <strong><a href="perfil.php?id=${commentUserId}" class="profile-link">${displayName}</a></strong>
              <span class="comment-date">${formatDate(comment.created_at)}</span>
            </div>
          </div>
          <div class="comment-actions">
            ${canDeleteComment(comment) ? `<button class="btn small muted delete-btn" data-comment-id="${comment.id}">Eliminar</button>` : ''}
          </div>
        </div>
        <div class="comment-content">
          <p>${escapeHtml(comment.content)}</p>
        </div>
      </div>
    `;
  }
  
  /**
   * Formatea una fecha en formato legible en español
   * 
   * @function formatDate
   * @param {string} dateString - Fecha en formato ISO string
   * @returns {string} Fecha formateada (ej: "7 oct 2025, 17:01")
   */
  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
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
  
  /**
   * Verifica si el usuario actual puede eliminar un comentario
   * 
   * @function canDeleteComment
   * @param {Object} comment - Objeto comentario
   * @returns {boolean} true si puede eliminar, false en caso contrario
   */
  function canDeleteComment(comment) {
    // Verificar si el usuario actual puede eliminar el comentario
    return window.userId > 0 && 
           (comment.user_id == window.userId || 
            window.userRole === 'admin' || window.userRole === 'mod');
  }
  
  // Funciones para manejar comentarios
  function replyToComment(commentId) {
    const replyForm = document.getElementById(`reply-form-${commentId}`);
    replyForm.classList.toggle('hidden');
  }
  
  function cancelReply(commentId) {
    document.getElementById(`reply-form-${commentId}`).classList.add('hidden');
  }
  
  function submitReply(event, parentId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('type', window.releaseId ? 'release' : 'blog');
    formData.append('target_id', targetId);
    formData.append('parent_id', parentId);
    formData.append('content', form.querySelector('textarea[name="content"]').value);
    
    fetch('comment-handler.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        form.reset();
        form.closest('.comment-reply-form').classList.add('hidden');
        // Resetear paginación y recargar desde el inicio
        resetPagination();
        loadComments(false); // Recargar comentarios desde el inicio
      } else {
        alert('Error: ' + data.error);
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('Error al enviar la respuesta');
    });
  }
  
  function deleteComment(commentId) {
    if (!confirm('¿Eliminar este comentario?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('type', window.releaseId ? 'release' : 'blog');
    formData.append('target_id', targetId);
    formData.append('comment_id', commentId);
    
    fetch('comment-handler.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Resetear paginación y recargar desde el inicio
        resetPagination();
        loadComments(false); // Recargar comentarios desde el inicio
      } else {
        alert('Error: ' + data.error);
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('Error al eliminar el comentario');
    });
  }
  
  // Enviar comentario principal
  if (commentForm) {
    commentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      
      // Debug: mostrar datos que se van a enviar
      console.log('Enviando comentario:', {
        action: formData.get('action'),
        type: formData.get('type'),
        target_id: formData.get('target_id'),
        content: formData.get('content')
      });
      
      fetch('comment-handler.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Respuesta del servidor:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Datos recibidos:', data);
        if (data.success) {
          this.reset();
          // Resetear paginación y recargar desde el inicio
          resetPagination();
          loadComments(false); // Recargar comentarios desde el inicio
        } else {
          alert('Error: ' + data.error);
        }
      })
      .catch(err => {
        console.error('Error:', err);
        alert('Error al enviar el comentario');
      });
    });
  }
  
  /**
   * Actualiza el botón "Cargar más" según el estado de paginación
   * Crea el botón dinámicamente si no existe
   * 
   * @function updateLoadMoreButton
   * @param {Object} pagination - Objeto con información de paginación
   * @param {number} pagination.total - Total de comentarios
   * @param {number} pagination.loaded - Comentarios cargados
   * @param {number} pagination.offset - Offset actual
   * @param {boolean} pagination.has_more - Si hay más comentarios
   * @returns {void}
   */
  function updateLoadMoreButton(pagination) {
    console.log('Actualizando botón "Cargar más":', pagination);
    
    // Buscar o crear contenedor del botón
    let loadMoreContainer = document.getElementById('load-more-comments');
    
    if (!loadMoreContainer) {
      // Crear contenedor si no existe
      loadMoreContainer = document.createElement('div');
      loadMoreContainer.id = 'load-more-comments';
      loadMoreContainer.className = 'load-more-container';
      commentsList.parentNode.insertBefore(loadMoreContainer, commentsList.nextSibling);
      console.log('Contenedor de "Cargar más" creado');
    }
    
    if (pagination.has_more) {
      const remaining = pagination.total - pagination.loaded - pagination.offset;
      loadMoreContainer.innerHTML = `
        <button class="btn load-more-btn" id="load-more-btn">
          Cargar más comentarios (${remaining} restantes)
        </button>
      `;
      console.log('Botón "Cargar más" mostrado con', remaining, 'comentarios restantes');
    } else {
      // Si no hay más, ocultar el botón
      loadMoreContainer.innerHTML = '';
      console.log('Botón "Cargar más" ocultado - no hay más comentarios');
    }
  }
  
  /**
   * Configura todos los event listeners del sistema de comentarios
   * Maneja clics en botones de responder, eliminar, cargar más y formularios
   * 
   * @function setupEventListeners
   * @returns {void}
   */
  function setupEventListeners() {
    // Botones de responder
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('reply-btn')) {
        const commentId = e.target.getAttribute('data-comment-id');
        replyToComment(commentId);
      }
    });
    
    // Botones de eliminar
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('delete-btn')) {
        const commentId = e.target.getAttribute('data-comment-id');
        deleteComment(commentId);
      }
    });
    
    // Botones de cancelar respuesta
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('cancel-reply-btn')) {
        const commentId = e.target.getAttribute('data-comment-id');
        cancelReply(commentId);
      }
    });
    
    // Botón "Cargar más"
    document.addEventListener('click', function(e) {
      if (e.target.id === 'load-more-btn') {
        e.preventDefault();
        loadComments(true); // append = true
      }
    });
    
    // Formularios de respuesta
    document.addEventListener('submit', function(e) {
      if (e.target.classList.contains('reply-form')) {
        e.preventDefault();
        const parentId = e.target.getAttribute('data-parent-id');
        submitReply(e, parentId);
      }
    });
  }
  
  // Cargar comentarios al inicio
  loadComments();
  
  // Configurar event listeners
  setupEventListeners();
}

