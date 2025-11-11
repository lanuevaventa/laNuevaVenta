<?php
session_start();
require_once 'conexion.php';

// Verifica si el usuario esta logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Obtener datos actuales del usuario
$id_usuario = $_SESSION['usuario'];
$query = "SELECT * FROM Usuario WHERE id_usuario = $1";
$result = pg_query_params($conexion, $query, [$id_usuario]);
$usuario = pg_fetch_assoc($result);

$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $foto_perfil = trim($_POST['foto_perfil'] ?? '');
    $rol = trim($_POST['rol'] ?? 'usuario');
    $fecha_registro = $usuario['fecha_registro']; // No se modifica
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $repetir_contrasena = $_POST['repetir_contrasena'] ?? '';

    // Validaciones basicas
    if ($nueva_contrasena !== '' && $nueva_contrasena !== $repetir_contrasena) {
        $mensaje = 'Las contraseñas nuevas no coinciden.';
    } else {
        // Si se cambia la contraseña
        if ($nueva_contrasena !== '') {
            $hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        } else {
            $hash = $usuario['contrasenia'];
        }
        $update = "UPDATE Usuario SET nombre=$1, apellido=$2, correo=$3, contrasenia=$4, telefono=$5, foto_perfil=$6, rol=$7 WHERE id_usuario=$8";
        $params = [$nombre, $apellido, $correo, $hash, $telefono, $foto_perfil, $rol, $id_usuario];
        $res = pg_query_params($conexion, $update, $params);
        if ($res) {
            $mensaje = 'Datos actualizados correctamente.';
            $exito = true;
            // Refresca los datos del usuario
            $result = pg_query_params($conexion, $query, [$id_usuario]);
            $usuario = pg_fetch_assoc($result);
        } else {
            $mensaje = 'Error al actualizar los datos.';
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
  <title>La Nueva Venta | Modificar Cuenta</title>
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            <li><a class="dropdown-item" href="cuenta.php"><i class="bi bi-person-circle me-2"></i>Mi cuenta</a></li>
            <li><a class="dropdown-item" href="subirProducto.php"><i class="bi bi-plus-circle me-2"></i>Subir Producto</a></li>
            <li><hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.3);"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
          </ul>
        </div>
        <!-- Carrito -->
        <a href="carrito.php" class="position-relative text-cream">
          <i class="bi bi-cart fs-5"></i>
        </a>
      </div>
    </div>
  </nav>
  <main class="main-layoutInicioSesion">
    <div class="columna-central">
      <?php if ($mensaje): ?>
        <div class="alert <?php echo $exito ? 'alert-success' : 'alert-danger'; ?> mt-4">
          <?php echo htmlspecialchars($mensaje); ?>
        </div>
      <?php endif; ?>
      <form class="login-form" id="formModificarCuenta" method="POST" autocomplete="off">
        <button type="button" class="btn-volver" onclick="window.location='cuenta.php'">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div class="form-logo" id="formLogo">
          <img src="img/lnvBlanco.png" alt="" id="logoPrin">
        </div>
        <input type="text" name="nombre" class="input-form" placeholder="Nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required />
        <input type="text" name="apellido" class="input-form" placeholder="Apellido" value="<?php echo htmlspecialchars($usuario['apellido']); ?>" />
        <input type="email" name="correo" class="input-form" placeholder="Correo" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required />
        <input type="text" name="telefono" class="input-form" placeholder="Teléfono" value="<?php echo $usuario['telefono'] ? htmlspecialchars($usuario['telefono']) : ''; ?>" />
        <input type="text" name="foto_perfil" class="input-form" placeholder="URL Foto de Perfil" value="<?php echo $usuario['foto_perfil'] ? htmlspecialchars($usuario['foto_perfil']) : ''; ?>" />
        <input type="text" name="rol" class="input-form" placeholder="Rol" value="<?php echo htmlspecialchars($usuario['rol']); ?>" readonly />
        <input type="text" class="input-form" placeholder="Fecha de Registro" value="<?php echo htmlspecialchars($usuario['fecha_registro']); ?>" readonly />
        <input type="password" name="nueva_contrasena" class="input-form" placeholder="Nueva Contraseña (opcional)" />
        <input type="password" name="repetir_contrasena" class="input-form" placeholder="Repetir Nueva Contraseña" />
        <button type="submit" class="btn-iniciar" id="btnGuardarCambios">Guardar Cambios</button>
      </form>
    </div>
  </main>
  <script src="js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
