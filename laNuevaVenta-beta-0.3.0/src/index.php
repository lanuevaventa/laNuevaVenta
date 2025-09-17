<?php
session_start();
require_once 'conexion.php';

// Verificar mensajes de error
$mensaje_error = '';
if (isset($_GET['error']) && $_GET['error'] === 'sin_permisos') {
    $mensaje_error = 'No tienes permisos de administrador para acceder a esa página';
}

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

// Trae los productos de la base de datos
$result = pg_query($conexion, "SELECT * FROM Producto ORDER BY id_producto DESC LIMIT 9");
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

  <!-- Carrusel -->
  <div id="carruselInicial" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators"></div>
    <div class="carousel-inner"></div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carruselInicial" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carruselInicial" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

  <!-- Contenido principal -->
  <main>
    <div class="container">
      <div class="row">
        <?php if (empty($productos)): ?>
          <div class="col-12 text-center my-5">
            <h3 class="text-muted">No hay productos disponibles</h3>
            <p class="text-muted">¡Sé el primero en subir un producto!</p>
            <?php if (isset($_SESSION['usuario'])): ?>
              <a href="subirProducto.php" class="btn btn-violeta">
                <i class="bi bi-plus-circle me-2"></i>Subir Producto
              </a>
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

  <script src="js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>