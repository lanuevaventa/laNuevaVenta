<?php
session_start();
require_once 'conexion.php';

// verifica si el usuario esta logueado
if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit;
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

// obtener el carrito de la sesion
$carrito = isset($_SESSION['carrito']) ? $_SESSION['carrito'] : array();
$mensaje = '';

// Eliminar producto del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = $_POST['eliminar'];
    if (isset($carrito[$id])) {
        unset($carrito[$id]);
        $_SESSION['carrito'] = $carrito;
    }
}

// procesa la compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprar'])) {
    // aca guardas la compra en la base de datos
    $_SESSION['carrito'] = array();
    $carrito = array();
    $mensaje = "¡Compra realizada con éxito!";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title>La Nueva Venta | Carrito</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
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
      <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;" method="GET" action="index.php">
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" name="buscar" placeholder="Buscar productos..." aria-label="Buscar" style="min-width: 0;">
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
          <?php 
          $carrito_count = count($carrito);
          if ($carrito_count > 0): 
          ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?php echo $carrito_count; ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </nav>
    <main id="carrito-main" class="container-fluid carrito-main" style="margin-top:85px;">
        <?php if (!empty($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
        <div class="row">
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="carrito-resumen card p-3">
                    <h5 class="mb-3">Resumen</h5>
                    <div id="carritoSuma">
                        <?php
                        $total = 0;
                        foreach ($carrito as $item) {
                            echo "<div class='carrito-suma-item'>{$item['nombre']} x{$item['cantidad']} <span>$" . number_format($item['precio'] * $item['cantidad'], 2) . "</span></div>";
                            $total += $item['precio'] * $item['cantidad'];
                        }
                        ?>
                    </div>
                    <hr>
                    <div class="fw-bold fs-5 mb-3">Total: $<span id="carritoTotal"><?php echo number_format($total, 2); ?></span></div>
                    <form method="post">
                        <button class="btn btn-violeta w-100" name="comprar" type="submit">Realizar compra</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-9 col-md-8">
                <div id="carritoListado">
                    <?php
                    foreach ($carrito as $id => $item) {
                        echo "<div class='carrito-producto-card'>
                                <img src='{$item['imagen']}' class='carrito-producto-img'>
                                <div class='carrito-producto-info'>
                                    <div class='carrito-producto-nombre'>{$item['nombre']}</div>
                                    <div class='carrito-producto-precio'>$" . number_format($item['precio'], 2) . "</div>
                                    <div class='carrito-producto-stock'>Cantidad: {$item['cantidad']}</div>
                                </div>
                                <form method='post' style='margin:0;'>
                                    <input type='hidden' name='eliminar' value='$id'>
                                    <button class='btn-eliminar-carrito' type='submit'>Eliminar</button>
                                </form>
                              </div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>
    <script src="js/carrito.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>