<?php
session_start();
require_once 'conexion.php';

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

if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit;
}

$id_usuario = $_SESSION['usuario'];

$query_usuario = "SELECT * FROM Usuario WHERE id_usuario = $1";
$result_usuario = pg_query_params($conexion, $query_usuario, [$id_usuario]);
$usuario = pg_fetch_assoc($result_usuario);

$query_productos = "SELECT p.* FROM Producto p 
                   INNER JOIN Vende v ON p.id_producto = v.id_producto 
                   WHERE v.id_usuario = $1 
                   ORDER BY p.fecha_publicacion DESC";
$result_productos = pg_query_params($conexion, $query_productos, [$id_usuario]);
$productos = [];
if ($result_productos) {
  while ($row = pg_fetch_assoc($result_productos)) {
    $productos[] = $row;
  }
}

if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
  $id_producto = intval($_GET['eliminar']);
  $query_check = "SELECT 1 FROM Vende WHERE id_producto = $1 AND id_usuario = $2";
  $result_check = pg_query_params($conexion, $query_check, [$id_producto, $id_usuario]);
  if (pg_fetch_assoc($result_check)) {
    pg_query_params($conexion, "DELETE FROM Vende WHERE id_producto = $1", [$id_producto]);
    pg_query_params($conexion, "DELETE FROM Producto WHERE id_producto = $1", [$id_producto]);
    header('Location: cuenta.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="img/lnvVioleta.png">
  <title>Mi Cuenta - La Nueva Venta</title>
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body data-page="cuenta">
  <nav class="navbar fixed-top shadow-sm border-bottom">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <a class="navbar-brand p-0 me-3 flex-shrink-0" href="index.php">
        <img src="img/lnvBlanco.png" alt="logo" style="height: 45px;">
      </a>
      <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;" method="GET" action="index.php">
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" name="buscar" placeholder="Buscar productos..." aria-label="Buscar" style="min-width: 0;">
        <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
          <i class="bi bi-search"></i>
        </button>
      </form>
      <div class="d-flex align-items-center gap-3 flex-shrink-0">
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
        <a href="carrito.php" class="position-relative text-cream">
          <i class="bi bi-cart fs-5"></i>
        </a>
      </div>
    </div>
  </nav>
  <main style="margin-top: 85px; padding: 2rem 0;">
    <div class="container">
      <section class="row">
        <div class="col-12">
          <h1 class="text-center mb-4 text-violeta">Mi Cuenta</h1>
          <div class="card mb-4">
            <div class="card-body">
              <h5 class="card-title text-violeta">
                <i class="bi bi-person-circle me-2"></i>Información Personal
              </h5>
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre'] ?? 'No especificado'); ?></p>
                  <p><strong>Apellido:</strong> <?php echo htmlspecialchars($usuario['apellido'] ?? 'No especificado'); ?></p>
                  <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario['correo'] ?? 'No especificado'); ?></p>
                </div>
                <div class="col-md-6">
                  <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($usuario['telefono'] ?? 'No especificado'); ?></p>
                  <p><strong>Fecha de registro:</strong> <?php echo htmlspecialchars($usuario['fecha_registro'] ?? 'No especificado'); ?></p>
                  <?php if (isset($usuario['rol']) && $usuario['rol'] === 'admin'): ?>
                    <p><strong>Rol:</strong> <span class="badge bg-success">Administrador</span></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="text-center mb-4">
            <a href="subirProducto.php" class="btn btn-success me-2">
              <i class="bi bi-plus-circle me-2"></i>Subir Producto
            </a>
            <a href="modificarCuenta.php" class="btn btn-outline-primary">
              <i class="bi bi-pencil me-2"></i>Modificar Cuenta
            </a>
          </div>
          <h2 class="mb-4 text-violeta">
            <i class="bi bi-box-seam me-2"></i>Mis productos subidos 
            <span class="badge bg-primary"><?php echo count($productos); ?></span>
          </h2>
          <div class="row g-3">
            <?php if (empty($productos)): ?>
              <div class="col-12">
                <div class="alert alert-info text-center">
                  <i class="bi bi-info-circle me-2"></i>
                  No has subido productos aún. <a href="subirProducto.php" class="alert-link">¡Sube tu primer producto!</a>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($productos as $prod): ?>
                <div class="col-lg-4 col-md-6">
                  <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($prod['imagen'] ?? 'img/placeholder.png'); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($prod['titulo']); ?>"
                         style="height: 200px; object-fit: cover;"
                         onerror="this.src='img/placeholder.png'">
                    <div class="card-body d-flex flex-column">
                      <h5 class="card-title"><?php echo htmlspecialchars($prod['titulo']); ?></h5>
                      <p class="card-text flex-grow-1">
                        <?php 
                        $descripcion = $prod['descripcion'] ?? '';
                        echo htmlspecialchars(strlen($descripcion) > 100 ? 
                             substr($descripcion, 0, 100) . '...' : $descripcion); 
                        ?>
                      </p>
                      <div class="mb-3">
                        <p class="card-text fw-bold text-success mb-1">
                          $<?php echo number_format($prod['precio'], 2); ?>
                        </p>
                        <div class="d-flex justify-content-between">
                          <small class="text-muted">Stock: <?php echo htmlspecialchars($prod['stock']); ?></small>
                          <small class="text-muted"><?php echo htmlspecialchars($prod['categoria']); ?></small>
                        </div>
                      </div>
                      <div class="d-flex gap-2 mt-auto">
                        <a href="producto.php?id=<?php echo $prod['id_producto']; ?>" 
                           class="btn btn-outline-info btn-sm flex-fill">
                          <i class="bi bi-eye"></i> Ver
                        </a>
                        <a href="editarProducto.php?id=<?php echo $prod['id_producto']; ?>" 
                           class="btn btn-outline-primary btn-sm flex-fill">
                          <i class="bi bi-pencil"></i> Editar
                        </a>
                        <a href="cuenta.php?eliminar=<?php echo $prod['id_producto']; ?>" 
                           class="btn btn-danger btn-sm flex-fill" 
                           onclick="return confirm('¿Seguro que quieres eliminar este producto?')">
                          <i class="bi bi-trash"></i> Eliminar
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </main>
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
  <script src="js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      const PAGE_ID = document.body.getAttribute('data-page') || 'home';
      const QUESTIONS = {
        cuenta: [
          '¿Cómo actualizo mis datos?',
          '¿Cómo cambio mi contraseña?',
          '¿Cómo subo un producto?',
          '¿Cómo edito un producto?',
          '¿Cómo elimino un producto?',
          '¿Dónde veo mis productos?',
          '¿Cómo cierro sesión?',
          'Soy admin, ¿dónde está el panel?'
        ]
      };
      const ANSWERS = {
        '¿Cómo actualizo mis datos?': 'Usá el botón "Modificar Cuenta" para editar tu información personal.',
        '¿Cómo cambio mi contraseña?': 'Ingresá a "Modificar Cuenta". Si no ves la opción, pronto estará disponible.',
        '¿Cómo subo un producto?': 'Presioná "Subir Producto" y completá el formulario con título, descripción, precio e imagen.',
        '¿Cómo edito un producto?': 'En la lista de tus productos, usá el botón "Editar" del producto que querés modificar.',
        '¿Cómo elimino un producto?': 'Usá el botón "Eliminar" del producto. Se pedirá confirmación antes de borrarlo.',
        '¿Dónde veo mis productos?': 'En esta página, debajo de tu información personal, se listan todos tus productos subidos.',
        '¿Cómo cierro sesión?': 'Abrí el menú de usuario (ícono de persona) y elegí "Cerrar sesión".',
        'Soy admin, ¿dónde está el panel?': 'Abrí el menú de usuario y seleccioná "Panel Admin".'
      };
      const fab = document.getElementById('chatbotFab');
      const panel = document.getElementById('chatbotPanel');
      const closeBtn = document.getElementById('chatbotClose');
      const list = document.getElementById('chatbotQuestions');
      const convo = document.getElementById('chatbotConversation');
      if (!fab || !panel || !closeBtn || !list || !convo) return;
      function appendMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg ' + (type === 'user' ? 'chatbot-msg-user' : 'chatbot-msg-bot');
        div.textContent = text;
        convo.appendChild(div);
        convo.parentElement.scrollTop = convo.parentElement.scrollHeight;
      }
      function renderQuestions() {
        const qs = QUESTIONS[PAGE_ID] || [];
        list.innerHTML = qs.map(q => `<li class="chatbot-question" role="button" tabindex="0">${q}</li>`).join('');
        list.querySelectorAll('.chatbot-question').forEach(item => {
          const question = item.textContent || '';
          const click = () => {
            appendMessage(question, 'user');
            const answer = ANSWERS[question] || 'Gracias por tu consulta.';
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
</body>
</html>