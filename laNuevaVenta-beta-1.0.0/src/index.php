<?php
session_start();
require_once 'conexion.php';

// verificar mensajes de error
$mensaje_error = '';
if (isset($_GET['error']) && $_GET['error'] === 'sin_permisos') {
    $mensaje_error = 'No tienes permisos de administrador para acceder a esa página';
}

// verificar admin
function esAdmin() {
    global $conexion;
    
    if (!isset($_SESSION['usuario'])) {
        return false;
    }
    
    $query = "SELECT rol FROM Usuario WHERE id_usuario = $1";
    $result = pg_query_params($conexion, $query, [$_SESSION['usuario']]);
    $usuario = pg_fetch_assoc($result);
    
    return $usuario && $usuario['rol'] === 'admin';
}

// obtener termino de busqueda si existe
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$es_busqueda = !empty($busqueda);

// Filtros por categoría y precio
$cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$precio_min = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$precio_max = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;

// Cargar categorías para el select
$categorias = [];
$cat_rs = pg_query($conexion, "SELECT id_categoria, nombre FROM Categoria ORDER BY nombre ASC");
if ($cat_rs) {
  while ($r = pg_fetch_assoc($cat_rs)) {
    $categorias[] = $r;
  }
}
// Nombre de categoría seleccionada (para incluir productos viejos con texto)
$cat_nombre_sel = null;
if ($cat > 0) {
  foreach ($categorias as $c) {
    if ((int)$c['id_categoria'] === $cat) { $cat_nombre_sel = $c['nombre']; break; }
  }
}

// Construir query dinámica
$conds = [];
$params = [];
$idx = 1;

if ($es_busqueda) {
  $conds[] = "LOWER(titulo) LIKE LOWER($$idx)";
  $params[] = '%'.$busqueda.'%';
  $idx++;
}
if ($cat > 0 && $cat_nombre_sel) {
  // Coincidir por id_categoria o por texto (compatibilidad)
  $conds[] = "(id_categoria = $$idx OR LOWER(categoria) = LOWER($$idx2))";
  $params[] = $cat; $idx++;
  $params[] = $cat_nombre_sel; $idx++; // usamos $$idx2 en reemplazo manual abajo
}
if ($precio_min !== null) {
  $conds[] = "precio >= $$idx";
  $params[] = $precio_min; $idx++;
}
if ($precio_max !== null) {
  $conds[] = "precio <= $$idx";
  $params[] = $precio_max; $idx++;
}

$where = '';
if (count($conds) > 0) {
  // Reemplazar marcador $$idx2 por el siguiente índice real
  // Tomamos la última condición y reemplazamos en línea si existe
  for ($i = 0; $i < count($conds); $i++) {
    if (strpos($conds[$i], '$$idx2') !== false) {
      $conds[$i] = str_replace('$$idx2', '$'.$idx-0, $conds[$i]); // ya incrementamos idx arriba
    }
    $conds[$i] = preg_replace_callback('/\$\$idx/', function() use (&$j){}, 1);
  }
  // Simplificar: reconstruimos condiciones con índices verdaderos
  // Recompute to avoid complexity:
  $conds = []; $params = []; $idx = 1;
  if ($es_busqueda) { $conds[] = "LOWER(titulo) LIKE LOWER($".$idx.")"; $params[] = '%'.$busqueda.'%'; $idx++; }
  if ($cat > 0 && $cat_nombre_sel) {
    $conds[] = "(id_categoria = $".$idx." OR LOWER(categoria) = LOWER($".($idx+1)."))";
    $params[] = $cat; $params[] = $cat_nombre_sel; $idx += 2;
  }
  if ($precio_min !== null) { $conds[] = "precio >= $".$idx; $params[] = $precio_min; $idx++; }
  if ($precio_max !== null) { $conds[] = "precio <= $".$idx; $params[] = $precio_max; $idx++; }

  $where = 'WHERE '.implode(' AND ', $conds);
}

// Query final (si no hay filtros ni búsqueda, limitar a 9 como antes)
$apply_limit = (!$es_busqueda && $cat === 0 && $precio_min === null && $precio_max === null);
$sql = "SELECT * FROM Producto $where ORDER BY id_producto DESC".($apply_limit ? " LIMIT 9" : "");
$result = empty($params)
  ? pg_query($conexion, $sql)
  : pg_query_params($conexion, $sql, $params);

$productos = [];
if ($result) {
  while ($row = pg_fetch_assoc($result)) {
    $productos[] = $row;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="img/lnvVioleta.png">
  <title>La Nueva Venta</title>
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <meta name="google-signin-client_id" content="YOUR_GOOGLE_CLIENT_ID">
</head>
<body data-page="home">
  <!-- Navbar -->
  <nav class="navbar fixed-top shadow-sm border-bottom">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <!-- Logo -->
      <a class="navbar-brand p-0 me-3 flex-shrink-0" href="index.php">
        <img src="img/lnvBlanco.png" alt="logo" style="height: 45px;">
      </a>
      <!-- Buscador -->
      <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;" method="GET" action="index.php">
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" 
               type="search" 
               name="buscar" 
               value="<?php echo htmlspecialchars($busqueda ?? ''); ?>"
               placeholder="Buscar productos..." 
               aria-label="Buscar" 
               style="min-width: 0;">
        <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
          <i class="bi bi-search"></i>
        </button>
      </form>
      <!-- iconos -->
      <div class="d-flex align-items-center gap-3 flex-shrink-0">
        <!-- Dropdown Usuario -->
        <div class="dropdown">
          <button class="btn btn-login-dropdown dropdown-toggle text-cream"
                  type="button"
                  id="dropdownLogin"
                  data-bs-toggle="dropdown"
                  aria-expanded="false">
            <i class="bi bi-person fs-5"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end bg-navbar" aria-labelledby="dropdownLogin">
            <?php if (isset($_SESSION['usuario'])): ?>
              <li><a class="dropdown-item" href="cuenta.php"><i class="bi bi-person-circle me-2"></i>Mi cuenta</a></li>
              <li><a class="dropdown-item" href="subirProducto.php"><i class="bi bi-plus-circle me-2"></i>Subir Producto</a></li>
              
              <?php if (esAdmin()): ?>
                <li><hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.3);"></li>
                <li><h6 class="dropdown-header text-cream" style="font-size: 0.8rem; opacity: 0.8;">ADMINISTRADOR</h6></li>
                <li><a class="dropdown-item" href="admin.php"><i class="bi bi-speedometer2 me-2"></i>Panel Admin</a></li>
                <li><a class="dropdown-item" href="db_viewer.php"><i class="bi bi-database me-2"></i>Ver Base de Datos</a></li>
              <?php endif; ?>
              
              <li><hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.3);"></li>
              <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            <?php else: ?>
              <li><a class="dropdown-item" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión</a></li>
              <li><a class="dropdown-item" href="registro.php"><i class="bi bi-person-plus me-2"></i>Registrarse</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <!-- Carrito -->
        <a href="carrito.php" class="position-relative text-cream">
          <i class="bi bi-cart fs-5"></i>
        </a>
      </div>
    </div>
  </nav>

  <!-- Mensaje de error si no tiene permisos -->
  <?php if ($mensaje_error): ?>
  <div class="alert alert-danger alert-dismissible fade show" style="margin-top: 85px; margin-bottom: 0; border-radius: 0;">
    <div class="container">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <?php echo htmlspecialchars($mensaje_error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

    <!-- Contenido principal -->
  <main class="main-no-gap">
    <div class="container">
      <!-- Filtros -->
      <form method="GET" action="index.php" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Categoría</label>
          <select name="cat" class="form-select border-violeta">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?php echo (int)$c['id_categoria']; ?>" <?php echo $cat === (int)$c['id_categoria'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Precio mín.</label>
          <input type="number" name="min" step="0.01" min="0" class="form-control border-violeta" value="<?php echo $precio_min !== null ? htmlspecialchars($precio_min) : ''; ?>">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Precio máx.</label>
          <input type="number" name="max" step="0.01" min="0" class="form-control border-violeta" value="<?php echo $precio_max !== null ? htmlspecialchars($precio_max) : ''; ?>">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
          <!-- Mantener búsqueda si existía -->
          <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
          <button type="submit" class="btn btn-violeta flex-grow-1"><i class="bi bi-funnel me-2"></i>Aplicar</button>
          <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
      </form>

      <?php if ($es_busqueda): ?>
        <!-- Información de búsqueda -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h4>Resultados de búsqueda para: <em>"<?php echo htmlspecialchars($busqueda); ?>"</em></h4>
                <p class="text-muted mb-0">
                  <?php 
                  $total_resultados = count($productos);
                  echo $total_resultados > 0 ? 
                    "Se encontraron $total_resultados producto(s)" : 
                    "No se encontraron productos"; 
                  ?>
                </p>
              </div>
              <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-2"></i>Limpiar búsqueda
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>
      
      <div class="row">
        <?php if (empty($productos)): ?>
          <div class="col-12 text-center my-5">
            <?php if ($es_busqueda): ?>
              <h3 class="text-muted">No se encontraron productos</h3>
              <p class="text-muted">No hay productos que coincidan con tu búsqueda "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"</p>
              <div class="mt-3">
                <a href="index.php" class="btn btn-outline-primary me-2">
                  <i class="bi bi-arrow-left me-2"></i>Ver todos los productos
                </a>
                <?php if (isset($_SESSION['usuario'])): ?>
                  <a href="subirProducto.php" class="btn btn-violeta">
                    <i class="bi bi-plus-circle me-2"></i>Subir Producto
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <h3 class="text-muted">No hay productos disponibles</h3>
              <p class="text-muted">¡Sé el primero en subir un producto!</p>
              <?php if (isset($_SESSION['usuario'])): ?>
                <a href="subirProducto.php" class="btn btn-violeta">
                  <i class="bi bi-plus-circle me-2"></i>Subir Producto
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php foreach ($productos as $prod): ?>
            <div class="col-lg-4 col-md-6 mb-4">
              <div class="card h-100">
                <img src="<?php echo htmlspecialchars($prod['imagen'] ?? 'img/placeholder.png'); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($prod['titulo'] ?? 'Producto'); ?>"
                     onerror="this.src='img/placeholder.png'">
                <div class="card-body d-flex flex-column">
                  <h5 class="card-title"><?php echo htmlspecialchars($prod['titulo'] ?? 'Sin título'); ?></h5>
                  <p class="card-text text-muted mb-2">
                    <small><?php echo htmlspecialchars($prod['categoria'] ?? 'Sin categoría'); ?></small>
                  </p>
                  <p class="card-text flex-grow-1">
                    <?php 
                    $descripcion = $prod['descripcion'] ?? '';
                    echo htmlspecialchars(strlen($descripcion) > 100 ? 
                         substr($descripcion, 0, 100) . '...' : $descripcion); 
                    ?>
                  </p>
                  <div class="mt-auto">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <span class="h5 text-success mb-0">
                        $<?php echo number_format($prod['precio'] ?? 0, 2); ?>
                      </span>
                      <small class="text-muted">
                        Stock: <?php echo $prod['stock'] ?? 0; ?>
                      </small>
                    </div>
                    <a href="producto.php?id=<?php echo $prod['id_producto']; ?>" 
                       class="btn btn-success w-100">
                      <i class="bi bi-eye me-2"></i>Ver Producto
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Chatbot Widget -->
  <button id="chatbotFab" class="chatbot-fab" aria-controls="chatbotPanel" aria-expanded="false" title="Ayuda">
    <i class="bi bi-robot fs-4"></i>
  </button>
  <div id="chatbotPanel" class="chatbot-panel" hidden>
    <div class="chatbot-header">
      <span>Asistente</span>
      <button id="chatbotClose" class="chatbot-close" aria-label="Cerrar">×</button>
    </div>
    <div class="chatbot-body">
      <div id="chatbotConversation" class="chatbot-conversation"></div>
      <p class="chatbot-subtitle">Preguntas sugeridas</p>
      <ul id="chatbotQuestions" class="chatbot-questions"></ul>
    </div>
  </div>

  <script>
    (function () {
      const PAGE_ID = document.body.getAttribute('data-page') || 'home';
      const QUESTIONS = {
        home: [
          '¿Cómo buscar productos?',
          '¿Cómo filtrar por categoría?',
          '¿Cómo veo los detalles de un producto?',
          '¿Cómo agrego productos al carrito?'
        ],
        producto: [
          '¿Qué métodos de pago aceptan?',
          '¿Cómo calcular el costo de envío?',
          '¿Cómo contacto al vendedor?',
          '¿Cómo agrego este producto al carrito?'
        ],
        carrito: [
          '¿Cómo aplicar un cupón?',
          '¿Cómo cambio cantidades del carrito?',
          '¿Cómo finalizo la compra?',
          '¿Cómo elimino un producto del carrito?'
        ],
        login: [
          'Olvidé mi contraseña, ¿qué hago?',
          '¿Puedo iniciar sesión con email y usuario?',
          '¿Cómo cierro sesión?'
        ],
        registro: [
          '¿Qué datos necesito para registrarme?',
          '¿Cómo verifico mi cuenta?',
          '¿Puedo registrarme con email ya usado?'
        ],
        subirProducto: [
          '¿Qué formatos de imagen aceptan?',
          '¿Cómo describir mejor mi producto?',
          '¿Cómo defino el stock y precio?'
        ],
        cuenta: [
          '¿Cómo cambio mi contraseña?',
          '¿Cómo actualizo mis datos?',
          '¿Cómo veo mis compras?'
        ],
        admin: [
          '¿Cómo ver el panel de control?',
          '¿Cómo gestionar productos?',
          '¿Cómo ver logs o la base de datos?'
        ]
      };

      const ANSWERS = {
        '¿Cómo buscar productos?': 'Usa la barra superior: escribe el término y presiona Enter o el ícono de la lupa.',
        '¿Cómo filtrar por categoría?': 'Por ahora filtras escribiendo el nombre de la categoría en la búsqueda. Próximamente filtros dedicados.',
        '¿Cómo veo los detalles de un producto?': 'Haz clic en el botón "Ver Producto" de su tarjeta.',
        '¿Cómo agrego productos al carrito?': 'Dentro del detalle del producto habrá un botón para agregarlo al carrito.',
        '¿Qué métodos de pago aceptan?': 'Actualmente se gestionan manualmente. Se mostrará la información del vendedor en el detalle.',
        '¿Cómo calcular el costo de envío?': 'El costo de envío se coordina con el vendedor después de iniciar la compra.',
        '¿Cómo contacto al vendedor?': 'Dentro del detalle del producto se mostrará información de contacto (o se añadirá pronto).',
        '¿Cómo agrego este producto al carrito?': 'Pulsa el botón verde o el específico de agregar al carrito dentro del detalle.',
        '¿Cómo aplicar un cupón?': 'La función de cupones no está activa todavía en esta versión local.',
        '¿Cómo cambio cantidades del carrito?': 'Se podrá editar la cantidad desde el carrito (campos numéricos).',
        '¿Cómo finalizo la compra?': 'Presiona el botón de finalizar/confirmar compra en el resumen (cuando esté disponible).',
        '¿Cómo elimino un producto del carrito?': 'Usa el botón eliminar en la tarjeta del producto dentro del carrito.',
        'Olvidé mi contraseña, ¿qué hago?': 'Se agregará recuperación. De momento, crea una nueva cuenta en entorno local.',
        '¿Puedo iniciar sesión con email y usuario?': 'Depende de la implementación. Normalmente se usa uno: el definido al registrarte.',
        '¿Cómo cierro sesión?': 'Abre el menú usuario y pulsa "Cerrar sesión".',
        '¿Qué datos necesito para registrarme?': 'Usuario, correo, contraseña y los campos que el formulario solicite.',
        '¿Cómo verifico mi cuenta?': 'En local no hay verificación externa. Tu cuenta se crea y queda lista.',
        '¿Puedo registrarme con email ya usado?': 'No, el sistema debería impedir correos duplicados.',
        '¿Qué formatos de imagen aceptan?': 'Generalmente JPG, PNG. Usa archivos ligeros para mejor rendimiento.',
        '¿Cómo describir mejor mi producto?': 'Sé claro: incluye características, estado, tamaño y uso principal.',
        '¿Cómo defino el stock y precio?': 'Stock es la cantidad disponible, precio el valor unitario. Ingrésalos al subir el producto.',
        '¿Cómo cambio mi contraseña?': 'Se añadirá en la sección de cuenta. De momento no está implementado.',
        '¿Cómo actualizo mis datos?': 'En “Mi cuenta” habrá opciones para editar tus datos (en desarrollo).',
        '¿Cómo veo mis compras?': 'Se añadirá historial en tu cuenta más adelante.',
        '¿Cómo ver el panel de control?': 'Si eres admin, en el menú usuario: “Panel Admin”.',
        '¿Cómo gestionar productos?': 'Desde el panel admin podrás editar/eliminar (en desarrollo).',
        '¿Cómo ver logs o la base de datos?': 'Usa la opción “Ver Base de Datos” si eres admin.'
      };

      const fab = document.getElementById('chatbotFab');
      const panel = document.getElementById('chatbotPanel');
      const closeBtn = document.getElementById('chatbotClose');
      const list = document.getElementById('chatbotQuestions');
      const convo = document.getElementById('chatbotConversation');

      // Evitar errores si algún elemento no existe
      if (!fab || !panel || !closeBtn || !list || !convo) return;

      function appendMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg ' + (type === 'user' ? 'chatbot-msg-user' : 'chatbot-msg-bot');
        div.textContent = text;
        convo.appendChild(div);
        convo.parentElement.scrollTop = convo.parentElement.scrollHeight;
      }

      function renderQuestions() {
        const qs = QUESTIONS[PAGE_ID] || QUESTIONS.home;
        list.innerHTML = qs.map(q => `<li class="chatbot-question" role="button" tabindex="0">${q}</li>`).join('');
        list.querySelectorAll('.chatbot-question').forEach(item => {
          const question = item.textContent || '';
          const click = () => {
            appendMessage(question, 'user');
            const answer = ANSWERS[question] || 'Todavía no tengo una respuesta programada para eso.';
            setTimeout(() => appendMessage(answer, 'bot'), 250);
          };
            item.addEventListener('click', click);
            item.addEventListener('keypress', e => { if (e.key === 'Enter') click(); });
        });
      }

      function open() { panel.hidden = false; fab.setAttribute('aria-expanded', 'true'); }
      function close() { panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); }

      fab.addEventListener('click', () => panel.hidden ? open() : close());
      closeBtn.addEventListener('click', close);
      document.addEventListener('click', e => {
        if (!panel.hidden && !panel.contains(e.target) && !fab.contains(e.target)) close();
      });

      renderQuestions();
    })();
  </script>

  <script src="js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>