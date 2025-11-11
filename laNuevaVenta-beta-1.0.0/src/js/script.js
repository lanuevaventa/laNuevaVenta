// ========== DROPDOWN DINÁMICO NAVBAR ==========
document.addEventListener('DOMContentLoaded', function() {
});

function tirarToast(id) {
  const toastEl = document.getElementById(id);
  const toast = new bootstrap.Toast(toastEl);
  toast.show();
}

function ajustarMarginCarrusel() {
  const navbar = document.querySelector('.navbar');
  const carrusel = document.getElementById('carruselInicial');
  if (navbar && carrusel) {
    const navbarHeight = navbar.offsetHeight;
    carrusel.style.marginTop = `${navbarHeight}px`;
  }
}
document.addEventListener('DOMContentLoaded', ajustarMarginCarrusel);
window.addEventListener('resize', ajustarMarginCarrusel);

document.addEventListener('DOMContentLoaded', function() {
  const miniaturas = document.querySelectorAll('.producto-galeria img');
  const imagenPrincipal = document.getElementById('imgPrincipal');
  
  if (miniaturas.length > 0 && imagenPrincipal) {
    miniaturas.forEach(img => {
      img.addEventListener('click', function() {
        miniaturas.forEach(i => i.classList.remove('selected'));
        this.classList.add('selected');
        imagenPrincipal.src = this.src;
        imagenPrincipal.style.opacity = '0.7';
        setTimeout(() => {
          imagenPrincipal.style.opacity = '1';
        }, 150);
      });
    });
  }
});

// ========== PRODUCTO - VALIDACIÓN DE COMENTARIOS ==========
document.addEventListener('DOMContentLoaded', function() {
  const formComentario = document.getElementById('formComentario');
  const inputComentario = document.getElementById('inputComentario');
  
  if (formComentario && inputComentario) {
    formComentario.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const comentario = inputComentario.value.trim();
      
      if (comentario.length < 5) {
        mostrarAlerta('El comentario debe tener al menos 5 caracteres.', 'warning');
        return;
      }
      
      if (comentario.length > 500) {
        mostrarAlerta('El comentario no puede exceder 500 caracteres.', 'warning');
        return;
      }
      mostrarAlerta('Funcionalidad de comentarios disponible próximamente.', 'info');
      inputComentario.value = '';
    });
    
    inputComentario.addEventListener('input', function() {
      const count = this.value.length;
      const maxLength = 500;
      
      let contador = document.getElementById('contador-caracteres');
      if (!contador) {
        contador = document.createElement('small');
        contador.id = 'contador-caracteres';
        contador.className = 'form-text';
        this.parentNode.appendChild(contador);
      }
      
      contador.textContent = `${count}/${maxLength} caracteres`;
      contador.style.color = count > maxLength ? '#dc3545' : '#6c757d';
    });
  }
});

// ========== PRODUCTO - EFECTOS VISUALES ==========
document.addEventListener('DOMContentLoaded', function() {
  const btnAgregar = document.querySelector('.btn-agregar-carrito');
  if (btnAgregar) {
    btnAgregar.addEventListener('mouseenter', function() {
      this.innerHTML = '<i class="bi bi-cart-plus me-2"></i>¡Agregar Ahora!';
    });
    
    btnAgregar.addEventListener('mouseleave', function() {
      this.innerHTML = '<i class="bi bi-cart-plus"></i> Agregar Al Carrito';
    });
  }
  const elementos = document.querySelectorAll('.producto-info, .producto-imagen-principal, .producto-comentarios');
  
  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });
  
  elementos.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
});

function mostrarAlerta(mensaje, tipo = 'info') {
  const alerta = document.createElement('div');
  alerta.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
  alerta.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
  alerta.innerHTML = `
    ${mensaje}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  
  document.body.appendChild(alerta);
  
  setTimeout(() => {
    if (alerta.parentNode) {
      alerta.remove();
    }
  }, 5000);
}

