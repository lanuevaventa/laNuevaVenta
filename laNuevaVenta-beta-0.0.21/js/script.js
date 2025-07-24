// ========== REGISTRO ==========
const registroForm = document.getElementById('loginForm');
if (registroForm && document.getElementById('inputConfirmarContrasena')) {
  registroForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const nombre = document.getElementById('inputNombre') ? document.getElementById('inputNombre').value : '';
    const apellido = document.getElementById('inputApellido') ? document.getElementById('inputApellido').value : '';
    const email = document.getElementById('inputEmail').value;
    const contrasena = document.getElementById('inputContrasena').value;
    const confirmar = document.getElementById('inputConfirmarContrasena').value;
    const errorDiv = document.getElementById('errorContrasena');

    if (contrasena !== confirmar) {
      errorDiv.textContent = 'Las contraseñas no coinciden';
      return;
    } else {
      errorDiv.textContent = '';
    }

    let usuarios = JSON.parse(localStorage.getItem('usuarios')) || [];
    // Evitar registro duplicado por email
    if (usuarios.some(u => u.email === email)) {
      errorDiv.textContent = 'El email ya está registrado';
      return;
    }

    const usuario = { nombre, apellido, email, contrasena };
    usuarios.push(usuario);
    localStorage.setItem('usuarios', JSON.stringify(usuarios));
    localStorage.setItem('usuarioLogueado', JSON.stringify(usuario));
    window.location.href = 'index.html';
  });
}

// ========== LOGIN ==========
const loginForm = document.getElementById('loginForm');
if (loginForm && document.getElementById('btnIniciarSesion')) {
  loginForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const email = document.getElementById('inputemail').value;
    const contrasena = document.getElementById('inputContrasena').value;
    const errorDiv = document.getElementById('errorLogin') || document.createElement('div');
    let usuarios = JSON.parse(localStorage.getItem('usuarios')) || [];
    const usuario = usuarios.find(u => u.email === email && u.contrasena === contrasena);

    if (usuario) {
      localStorage.setItem('usuarioLogueado', JSON.stringify(usuario));
      window.location.href = 'index.html';
    } else {
      errorDiv.textContent = 'Email o contraseña incorrectos';
      errorDiv.style.color = 'red';
      if (!document.getElementById('errorLogin')) {
        loginForm.appendChild(errorDiv);
        errorDiv.id = 'errorLogin';
      }
    }
  });
}

// ========== DROPDOWN DINÁMICO NAVBAR ==========
document.addEventListener('DOMContentLoaded', function() {
  const dropdownLogin = document.getElementById('dropdownLogin');
  const dropdownOpciones = document.getElementById('dropdownOpciones');

  function actualizarDropdown() {
    const usuario = localStorage.getItem('usuarioLogueado');
    if (dropdownOpciones) {
      if (usuario) {
        dropdownOpciones.innerHTML = `
          <li><a class="dropdown-item text-cream" href="cuenta.html">Cuenta</a></li>
          <li><a class="dropdown-item text-cream" href="#" id="cerrarSesion">Cerrar sesión</a></li>
        `;
        const cerrarSesionBtn = document.getElementById('cerrarSesion');
        if (cerrarSesionBtn) {
          cerrarSesionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            localStorage.removeItem('usuarioLogueado');
            window.location.reload();
          });
        }
      } else {
        dropdownOpciones.innerHTML = `
          <li><a class="dropdown-item text-cream" href="login.html">Iniciar sesión</a></li>
          <li><a class="dropdown-item text-cream" href="registro.html">Registrarse</a></li>
        `;
      }
    }
  }

  // Actualiza el dropdown cada vez que se abre
  if (dropdownLogin) {
    dropdownLogin.addEventListener('click', actualizarDropdown);
  }
  // También actualiza al cargar la página
  actualizarDropdown();
});

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
// Si le sacás esto el carrusel NO FUNCIONA

