// ========== JAVASCRIPT PARA EL CARRITO ==========
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-eliminar-carrito').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      if (confirm('¿Eliminar este producto del carrito?')) {
        this.closest('form').submit();
      }
    });
  });
});
