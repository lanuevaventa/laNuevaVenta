//localStorage.clear();
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
          <li><a class="dropdown-item text-cream" href="subirProducto.html">Subir producto</a></li>
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

// ========== MOSTRAR PRODUCTOS EN EL CARRUSEL ==========
function renderCarrusel() {
  const productos = JSON.parse(localStorage.getItem('productos')) || [];
  const carruselInner = document.querySelector('.carousel-inner');
  const carruselIndicators = document.querySelector('.carousel-indicators');
  if (!carruselInner || !carruselIndicators) return;

  // Agrupa productos de a 3 por slide
  let slides = [];
  for (let i = 0; i < productos.length; i += 3) {
    slides.push(productos.slice(i, i + 3));
  }

  // Si no hay productos, muestra los de ejemplo
  if (slides.length === 0) {
    carruselInner.innerHTML = `
      <div class="carousel-item active">
        <div class="row g-2">
          <div class="col-4"><img src="img/lorenzo.png" class="d-block w-100" alt="..."></div>
          <div class="col-4"><img src="img/lorenzo.png" class="d-block w-100" alt="..."></div>
          <div class="col-4"><img src="img/lorenzo.png" class="d-block w-100" alt="..."></div>
        </div>
      </div>
    `;
    carruselIndicators.innerHTML = `<button type="button" data-bs-target="#carruselInicial" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>`;
    return;
  }

  // Renderiza slides
  carruselInner.innerHTML = '';
  carruselIndicators.innerHTML = '';
  slides.forEach((slide, idx) => {
    const active = idx === 0 ? 'active' : '';
    carruselIndicators.innerHTML += `<button type="button" data-bs-target="#carruselInicial" data-bs-slide-to="${idx}" class="${active}" aria-label="Slide ${idx + 1}"></button>`;
    carruselInner.innerHTML += `
      <div class="carousel-item ${active}">
        <div class="row g-2">
          ${slide.map(producto => `
            <div class="col-4">
              <div class="card h-100 producto-card" style="cursor:pointer" data-id="${producto.id}">
                <img src="${producto.imagen}" class="card-img-top" alt="${producto.nombre}">
                <div class="card-body">
                  <h5 class="card-title">${producto.nombre}</h5>
                  <p class="card-text">${producto.descripcion}</p>
                  <p class="card-text fw-bold">$${producto.precio.toFixed(2)}</p>
                </div>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  });

  // Evento para ir a producto.html al hacer click
  setTimeout(() => {
    document.querySelectorAll('.producto-card').forEach(card => {
      card.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        localStorage.setItem('productoSeleccionado', id);
        window.location.href = 'producto.html';
      });
    });
  }, 100);
}

if (document.getElementById('carruselInicial')) {
  document.addEventListener('DOMContentLoaded', renderCarrusel);
}
// ========== SUBIR PRODUCTO ==========
const formSubirProducto = document.getElementById('formSubirProducto');
if (formSubirProducto) {
  formSubirProducto.addEventListener('submit', function(event) {
    event.preventDefault();
    const nombre = document.getElementById('nombreProducto').value.trim();
    const precio = parseFloat(document.getElementById('precioProducto').value);
    const descripcion = document.getElementById('descripcionProducto').value.trim();
    const imagenInput = document.getElementById('imagenProducto');
    const errorDiv = document.getElementById('errorProducto');
    const stock = parseInt(document.getElementById('stockProducto').value, 10);

    if (isNaN(stock) || stock < 1) {
      errorDiv.textContent = 'Debes ingresar un stock válido (mayor a 0).';
      return;
    }

    if (!imagenInput.files[0]) {
      errorDiv.textContent = 'Debes seleccionar una imagen.';
      return;
    }

    // Obtener usuario logueado
    const usuario = JSON.parse(localStorage.getItem('usuarioLogueado'));
    if (!usuario || !usuario.email) {
      errorDiv.textContent = 'Debes estar logueado para subir un producto.';
      return;
    }

    // Leer la imagen como base64
    const reader = new FileReader();
    reader.onload = function(e) {
      let productos = JSON.parse(localStorage.getItem('productos')) || [];
      // ID progresivo automático
      const id = productos.length > 0 ? productos[productos.length - 1].id + 1 : 1;
      const producto = {
        id,
        nombre,
        precio,
        stock,
        descripcion,
        imagen: e.target.result, // base64
        email: usuario.email // Asocia el producto al usuario logueado
      };
      productos.push(producto);
      localStorage.setItem('productos', JSON.stringify(productos));
      alert('¡Producto subido!');
      window.location.href = 'index.html';
    };
    reader.readAsDataURL(imagenInput.files[0]);
  });
}

// ========== CARRITO ==========

// Utilidad: obtener carrito del usuario logueado
function getCarritoUsuario() {
  const usuario = JSON.parse(localStorage.getItem('usuarioLogueado'));
  if (!usuario) return [];
  const carritos = JSON.parse(localStorage.getItem('carritos')) || {};
  return carritos[usuario.email] || [];
}

// Utilidad: guardar carrito del usuario logueado
function setCarritoUsuario(carrito) {
  const usuario = JSON.parse(localStorage.getItem('usuarioLogueado'));
  if (!usuario) return;
  let carritos = JSON.parse(localStorage.getItem('carritos')) || {};
  carritos[usuario.email] = carrito;
  localStorage.setItem('carritos', JSON.stringify(carritos));
}

// Renderizar carrito
function renderCarrito() {
  const carrito = getCarritoUsuario();
  const productos = JSON.parse(localStorage.getItem('productos')) || [];
  const listado = document.getElementById('carritoListado');
  const suma = document.getElementById('carritoSuma');
  const total = document.getElementById('carritoTotal');

  if (!listado || !suma || !total) return;

  if (carrito.length === 0) {
    listado.innerHTML = `<div class="alert alert-info">No tienes productos en el carrito.</div>`;
    suma.innerHTML = '';
    total.textContent = '0.00';
    return;
  }

  // Mostrar productos
  let sumaHtml = '';
  let totalNum = 0;
  listado.innerHTML = carrito.map(id => {
    const prod = productos.find(p => p.id === id);
    if (!prod) return '';
    sumaHtml += `<div class="carrito-suma-item"><span>${prod.nombre}</span><span>$${prod.precio.toFixed(2)}</span></div>`;
    totalNum += prod.precio;
    return `
      <div class="carrito-producto-card">
        <img src="${prod.imagen}" class="carrito-producto-img" alt="${prod.nombre}">
        <div class="carrito-producto-info">
          <div class="carrito-producto-nombre">${prod.nombre}</div>
          <div class="carrito-producto-precio">$${prod.precio.toFixed(2)}</div>
          <div class="carrito-producto-stock">Stock: ${prod.stock ?? '-'}</div>
        </div>
        <button class="btn-eliminar-carrito" data-id="${prod.id}"><i class="bi bi-trash"></i></button>
      </div>
    `;
  }).join('');
  suma.innerHTML = sumaHtml;
  total.textContent = totalNum.toFixed(2);

  // Botones eliminar
  listado.querySelectorAll('.btn-eliminar-carrito').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = parseInt(this.getAttribute('data-id'));
      const nuevoCarrito = carrito.filter(pid => pid !== id);
      setCarritoUsuario(nuevoCarrito);
      renderCarrito();
    });
  });
}

// Botón comprar
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('carritoListado')) {
    renderCarrito();
    const btnComprar = document.getElementById('btnComprar');
    if (btnComprar) {
      btnComprar.addEventListener('click', function() {
        alert('¡Compra realizada con éxito!');
        setCarritoUsuario([]);
        renderCarrito();
      });
    }
  }
});

// ========== AGREGAR AL CARRITO DESDE PRODUCTO ==========

if (document.querySelector('.btn-success.w-100.mt-3')) {
  document.querySelector('.btn-success.w-100.mt-3').addEventListener('click', function() {
    const id = localStorage.getItem('productoSeleccionado');
    const usuario = JSON.parse(localStorage.getItem('usuarioLogueado'));
    if (!usuario) {
      alert('Debes iniciar sesión para agregar productos al carrito.');
      window.location.href = 'login.html';
      return;
    }
    let carritos = JSON.parse(localStorage.getItem('carritos')) || {};
    let carrito = carritos[usuario.email] || [];
    const prodId = parseInt(id);
    if (!carrito.includes(prodId)) {
      carrito.push(prodId);
      carritos[usuario.email] = carrito;
      localStorage.setItem('carritos', JSON.stringify(carritos));
      alert('Producto agregado al carrito.');
    } else {
      alert('Este producto ya está en tu carrito.');
    }
  });
}