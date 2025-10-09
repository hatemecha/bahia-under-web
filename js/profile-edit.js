// Funcionalidad de edición de perfil
document.addEventListener('DOMContentLoaded', function() {
  const colorInput = document.getElementById('brand-color');
  const colorPreview = document.getElementById('color-preview');
  const colorValue = document.getElementById('color-value');
  const saveButton = document.getElementById('save-color');
  const resetButton = document.getElementById('reset-color');
  
  if (colorInput && colorPreview && colorValue) {
    // Aplicar color inicial desde data-attribute
    const initialColor = colorPreview.getAttribute('data-color');
    if (initialColor) {
      colorPreview.style.backgroundColor = initialColor;
    }
    
    // Actualizar preview cuando cambia el color
    colorInput.addEventListener('input', function() {
      const color = this.value;
      colorPreview.style.backgroundColor = color;
      colorValue.textContent = color;
    });
    
    // Guardar color
    saveButton.addEventListener('click', function() {
      const color = colorInput.value;
      
      fetch('update-brand-color.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ brand_color: color })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Aplicar el color inmediatamente
          applyBrandColor(color);
          showMessage('Color guardado correctamente', 'success');
        } else {
          showMessage('Error: ' + data.error, 'error');
        }
      })
      .catch(err => {
        console.error('Error:', err);
        showMessage('Error al guardar el color', 'error');
      });
    });
    
    // Restablecer color por defecto
    resetButton.addEventListener('click', function() {
      const defaultColor = '#a78bfa';
      colorInput.value = defaultColor;
      colorPreview.style.backgroundColor = defaultColor;
      colorValue.textContent = defaultColor;
      
      // Aplicar inmediatamente
      applyBrandColor(defaultColor);
    });
  }
  
  // Función para aplicar el color a la página
  function applyBrandColor(color) {
    document.documentElement.style.setProperty('--brand', color);
    
    // Calcular color hover (más oscuro)
    const hoverColor = adjustColor(color, -20);
    document.documentElement.style.setProperty('--brand-hover', hoverColor);
    
    // Calcular color light (más claro con transparencia)
    document.documentElement.style.setProperty('--brand-light', color + '1a');
  }
  
  // Función para ajustar brillo de color
  function adjustColor(color, amount) {
    const usePound = color[0] === '#';
    const col = usePound ? color.slice(1) : color;
    const num = parseInt(col, 16);
    let r = (num >> 16) + amount;
    let g = (num >> 8 & 0x00FF) + amount;
    let b = (num & 0x0000FF) + amount;
    r = r > 255 ? 255 : r < 0 ? 0 : r;
    g = g > 255 ? 255 : g < 0 ? 0 : g;
    b = b > 255 ? 255 : b < 0 ? 0 : b;
    return (usePound ? '#' : '') + (r << 16 | g << 8 | b).toString(16).padStart(6, '0');
  }
  
  // Función para mostrar mensajes
  function showMessage(message, type) {
    // Crear elemento de mensaje
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.textContent = message;
    
    // Insertar al inicio del main
    const main = document.querySelector('main');
    main.insertBefore(messageDiv, main.firstChild);
    
    // Remover después de 3 segundos
    setTimeout(() => {
      messageDiv.remove();
    }, 3000);
  }
});
