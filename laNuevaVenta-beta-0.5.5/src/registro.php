<?php
session_start();
require_once 'conexion.php';
$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $apellido = trim($_POST['apellido'] ?? '');
  $correo = trim($_POST['email'] ?? '');
  $contrasena = $_POST['contrasena'] ?? '';
  $confirmar = $_POST['confirmar'] ?? '';

  if ($contrasena !== $confirmar) {
    $mensaje = 'Las contraseñas no coinciden';
  } elseif (strlen($contrasena) < 4) {
    $mensaje = 'La contraseña debe tener al menos 4 caracteres';
  } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $mensaje = 'El correo no es válido';
  } else {
    $query_check = "SELECT 1 FROM Usuario WHERE correo = $1";
    $result_check = pg_query_params($conexion, $query_check, [$correo]);
    if (pg_fetch_assoc($result_check)) {
      $mensaje = 'El correo ya está registrado';
    } else {
      $hash = password_hash($contrasena, PASSWORD_DEFAULT);
      $fecha_registro = date('Y-m-d');
      $query = "INSERT INTO Usuario (nombre, apellido, correo, contrasenia, fecha_registro) VALUES ($1, $2, $3, $4, $5)";
      $result = pg_query_params($conexion, $query, [$nombre, $apellido, $correo, $hash, $fecha_registro]);
      if ($result) {
        $exito = true;
        $mensaje = '¡Registro exitoso! Redirigiendo al login...';
        header("refresh:2;url=login.php");
      } else {
        $mensaje = 'Error al registrar usuario';
      }
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
    <title>La Nueva Venta | Registro</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
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
        </a>
      </div>
    </div>
  </nav>
    <main class="d-flex align-items-center justify-content-center min-vh-100">
      <div class="columna-central">
        <?php if ($mensaje): ?>
          <div class="alert <?php echo $exito ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
          </div>
        <?php endif; ?>
        <form class="login-form" id="loginForm" method="POST" action="registro.php" autocomplete="off">
          <button type="button" class="btn-volver" onclick="window.location='index.php'">
            <i class="bi bi-arrow-left"></i>
          </button>
          <div class="form-logo" id="formLogo"> <img src="img/lnvBlanco.png" alt="" id="logoPrin"></div>
          <input type="text" name="nombre" class="input-form" placeholder="Nombre Completo" required>
          <input type="text" name="apellido" class="input-form" placeholder="Apellido" required>
          <input type="email" name="email" class="input-form" placeholder="Email" required>
          <input type="password" name="contrasena" class="input-form" placeholder="Contraseña" required>
          <input type="password" name="confirmar" class="input-form" placeholder="Confirmar Contraseña" required>
          <button type="submit" class="btn-iniciar" id="btnRegistrarse">Registrarse</button>
          <a href="login.php" class="link-registrarse" id="linkLogin">¿Ya tienes una cuenta? Inicia sesión</a>
          <button type="button" class="btn-otro-metodo" id="btnOtroMetodo">Registrate con otro método</button>
        </form>
      </div>
    </main>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>