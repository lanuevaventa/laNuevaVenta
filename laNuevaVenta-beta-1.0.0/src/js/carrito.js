// ========== JAVASCRIPT PARA EL CARRITO ==========

// Espera a que el DOM esté completamente cargado antes de ejecutar el código
document.addEventListener('DOMContentLoaded', function() {
  
  // Busca TODOS los botones que tienen la clase 'btn-eliminar-carrito' en la página
  document.querySelectorAll('.btn-eliminar-carrito').forEach(btn => {
    
    // Agrega un evento 'click' a cada botón encontrado
    btn.addEventListener('click', function(e) {
      
      // Previene el comportamiento por defecto del botón (envío inmediato del formulario)
      e.preventDefault();
      
      // Muestra una ventana de confirmación al usuario
      // Si el usuario presiona "Aceptar", confirm() devuelve true
      if (confirm('¿Eliminar este producto del carrito?')) {
        
        // Busca el formulario más cercano que contiene este botón y lo envía
        // .closest() busca hacia arriba en el DOM hasta encontrar un elemento 'form'
        this.closest('form').submit();
      }
      // Si el usuario presiona "Cancelar", no pasa nada
    });
  });
});

// EXPLICACIÓN DE FUNCIONAMIENTO:
// 1. Cuando se carga la página, este script busca todos los botones de eliminar
// 2. A cada botón le asigna una función que se ejecuta al hacer clic
// 3. Cuando el usuario hace clic, se muestra una confirmación
// 4. Si confirma, el formulario que contiene el botón se envía al servidor
// 5. El servidor PHP procesa la eliminación del producto del carrito