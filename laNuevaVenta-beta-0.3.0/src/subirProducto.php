<?php
session_start();
require_once 'conexion.php';

$mensaje = '';
$exito = false;

// Función para verificar si es admin
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

// Solo permitir si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo = trim($_POST['titulo'] ?? '');
  $precio = floatval($_POST['precio'] ?? 0);
  $stock = intval($_POST['stock'] ?? 0);
  $descripcion = trim($_POST['descripcion'] ?? '');
  $categoria = trim($_POST['categoria'] ?? '');
  $fecha_publicacion = date('Y-m-d H:i:s');
  $imagen_url = '';
  $id_usuario = $_SESSION['usuario']; // Obtener ID del usuario logueado

  // Validaciones básicas
  if ($titulo === '' || $precio <= 0 || $stock < 1 || $descripcion === '') {
    $mensaje = 'Completa todos los campos correctamente.';
  } elseif (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    $mensaje = 'Debes subir una imagen válida.';
  } else {
    // Guardar imagen
    $img_dir = 'img/productos/';
    if (!is_dir($img_dir)) {
      mkdir($img_dir, 0777, true);
    }
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $nombre_archivo = uniqid('prod_') . '.' . $ext;
    $ruta_destino = $img_dir . $nombre_archivo;

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
      $imagen_url = $ruta_destino;

      // Iniciar transacción para insertar producto y relación
      pg_query($conexion, "BEGIN");
      
      try {
        // 1. Insertar el producto y obtener su ID
        $query_producto = "INSERT INTO Producto (titulo, precio, descripcion, stock, categoria, fecha_publicacion, imagen)
                          VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id_producto";
        $result_producto = pg_query_params($conexion, $query_producto, [
          $titulo, $precio, $descripcion, $stock, $categoria, $fecha_publicacion, $imagen_url
        ]);
        
        if (!$result_producto) {
          throw new Exception('Error al insertar producto');
        }
        
        $row = pg_fetch_assoc($result_producto);
        $id_producto = $row['id_producto'];
        
        // 2. Insertar la relación en la tabla Vende
        $query_vende = "INSERT INTO Vende (id_producto, id_usuario) VALUES ($1, $2)";
        $result_vende = pg_query_params($conexion, $query_vende, [$id_producto, $id_usuario]);
        
        if (!$result_vende) {
          throw new Exception('Error al relacionar producto con usuario');
        }
        
        // Confirmar transacción
        pg_query($conexion, "COMMIT");
        
        $exito = true;
        $mensaje = '¡Producto subido exitosamente!';
        header("refresh:2;url=cuenta.php");
        
      } catch (Exception $e) {
        // Revertir transacción en caso de error
        pg_query($conexion, "ROLLBACK");
        $mensaje = 'Error al guardar el producto: ' . $e->getMessage();
      }
    } else {
      $mensaje = 'Error al guardar la imagen.';
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="img/lnvVioleta.png">
  <title>Subir Producto - La Nueva Venta</title>
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar fixed-top shadow-sm border-bottom">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <!-- Logo -->
      <a class="navbar-brand p-0 me-3 flex-shrink-0" href="index.php">
        <img src="img/lnvBlanco.png" alt="logo" style="height: 45px;">
      </a>
      <!-- Buscador -->
      <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;">
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" placeholder="Buscar..." aria-label="Buscar" style="min-width: 0;">
        <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
          <i class="bi bi-search"></i>
        </button>
      </form>
      <!-- Íconos -->
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

  <!-- Main Content -->
  <main class="main-layoutInicioSesion">
    <div class="columna-central">
      <form id="formSubirProducto" enctype="multipart/form-data" class="login-form" method="POST" action="subirProducto.php">
        <button type="button" class="btn-volver" onclick="window.location='index.php'">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div class="form-logo" id="formLogo">
          <img src="img/lnvBlanco.png" alt="" id="logoPrin">
        </div>
        <?php if ($mensaje): ?>
          <div class="alert <?php echo $exito ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
          </div>
        <?php endif; ?>
        <input type="text" class="input-form" name="titulo" placeholder="Nombre del producto" required>
        <input type="number" class="input-form" name="precio" placeholder="Precio" min="0" step="0.01" required>
        <input type="number" class="input-form" name="stock" placeholder="Stock" min="1" step="1" required>
        <input type="text" class="input-form" name="categoria" placeholder="Categoría" required>
        <textarea class="input-form" name="descripcion" id="descripcionProducto" placeholder="Descripción" required></textarea>
        <input type="file" class="input-form" name="imagen" accept="image/*" required>
        <button type="submit" class="btn-iniciar">Subir producto</button>
      </form>
    </div>
  </main>

  <script src="js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>