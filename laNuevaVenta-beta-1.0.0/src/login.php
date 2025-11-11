<?php
// filepath: c:\Users\nicot\OneDrive\Desktop\Proyecto\laNuevaVenta\laNuevaVenta-beta-0.1.1-Prubeas\src\login.php
session_start();
require_once 'conexion.php';
$mensaje = '';
$exito = false;

// función para verificar si es admin
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

// verificar mensajes de error en URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'acceso_denegado':
            $mensaje = 'Debes iniciar sesión para acceder a esa página';
            break;
        case 'sin_permisos':
            $mensaje = 'No tienes permisos de administrador para acceder a esa página';
            break;
    }
}

define('GOOGLE_CLIENT_ID','418899644432-ocvscm4moul8p6hta8oei7ss2gbbgpve.apps.googleusercontent.com'); // reemplazar por el real

// Modo desarrollo: muestra el código 2FA en pantalla de verificación (solo pruebas locales)
if (!defined('DEV_SHOW_2FA_CODE')) {
  define('DEV_SHOW_2FA_CODE', true);
}

// Envío simple de email con mail() (requiere SMTP configurado en PHP)
function send_2fa_email(string $to, string $code): bool {
  $subject = 'Tu código de verificación - La Nueva Venta';
  $message = "Hola,\n\nTu código de verificación es: $code\nEs válido por 30 minutos.\n\nSi no fuiste tú, ignora este correo.";
  $headers = "From: La Nueva Venta <no-reply@lanuevaventa.local>\r\n".
             "Content-Type: text/plain; charset=UTF-8\r\n";
  return @mail($to, $subject, $message, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $correo = trim($_POST['email'] ?? '');
  $contrasena = $_POST['contrasena'] ?? '';

  $query = "SELECT * FROM Usuario WHERE correo = $1";
  $result = pg_query_params($conexion, $query, [$correo]);
  $usuario = pg_fetch_assoc($result);

  if ($usuario && password_verify($contrasena, $usuario['contrasenia'])) {
    // Determinar si es admin
    $rol = strtolower($usuario['rol'] ?? '');
    if ($rol === 'admin') {
      // Admin: sin 2FA
      $_SESSION['usuario'] = $usuario['id_usuario'];
      $exito = true;
      $mensaje = '¡Login exitoso! Redirigiendo...';
      header("refresh:1;url=index.php");
      exit;
    }

    // No admin: generar 2FA
    $code = (string) random_int(100000, 999999);
    $_SESSION['2fa'] = [
      'user_id' => $usuario['id_usuario'],
      'email'   => $usuario['correo'],
      'code'    => $code,
      'expires' => time() + (30 * 60), // 30 minutos
    ];

    // Enviar el código por email (si falla, igualmente va a la pantalla para poder reenviar)
    send_2fa_email($usuario['correo'], $code);

    // Redirigir a verificación
    header("Location: verify_2fa.php");
    exit;
  } else {
    $mensaje = 'Correo o contraseña incorrectos';
  }
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title>La Nueva Venta | Inicia sesión</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <meta name="google-signin-client_id" content="<?php echo GOOGLE_CLIENT_ID; ?>">
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
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" 
               type="search" 
               name="buscar" 
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
    <main class="d-flex align-items-center justify-content-center min-vh-100">
      <div class="columna-central">
        <?php if ($mensaje): ?>
          <div class="alert <?php echo $exito ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
          </div>
        <?php endif; ?>
        <form class="login-form" id="loginForm" method="POST" action="login.php" autocomplete="off">
          <button type="button" class="btn-volver" onclick="window.location='index.php'">
            <i class="bi bi-arrow-left"></i>
          </button>
          <div class="form-logo" id="formLogo"> <img src="img/lnvBlanco.png" alt="" id="logoPrin"></div>
          <input type="email" name="email" class="input-form" placeholder="Email" required>
          <input type="password" name="contrasena" class="input-form" placeholder="Contraseña" required>
          <button type="submit" class="btn-iniciar" id="btnIniciarSesion">Iniciar Sesión</button>
          <a href="#" class="link-olvide" id="linkOlvide">¿Olvidaste tu contraseña?</a>
          <a href="registro.php" class="link-registrarse" id="linkRegistro">¿No tienes una cuenta? Regístrate</a>
          <div class="separador"><span>O</span></div>
          <!-- Botón Google -->
          <div id="g_id_onload"
               data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
               data-callback="handleGoogleCredential"
               data-auto_prompt="false"></div>
          <div class="g_id_signin"
               data-type="standard"
               data-shape="pill"
               data-theme="outline"
               data-text="signin_with"
               data-size="large"
               data-logo_alignment="left"></div>
          <button type="button" class="btn-otro-metodo" id="btnOtroMetodo">Ingresa con otro método</button>
        </form>
      </div>
    </main>
    <script>
      function handleGoogleCredential(response){
        if(!response.credential){ alert('Sin credential'); return; }
        const fd = new FormData();
        fd.append('credential', response.credential);
        fetch('google_auth.php', { method:'POST', body: fd })
          .then(r => r.json())
          .then(d => {
            if(d.ok){ window.location='index.php'; }
            else { alert('Error Google: '+(d.error||'desconocido')); }
          })
          .catch(()=>alert('Fallo de red Google'));
      }
    </script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>