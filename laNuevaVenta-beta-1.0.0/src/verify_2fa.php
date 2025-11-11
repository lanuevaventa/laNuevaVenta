<?php
session_start();

// Mostrar código en modo dev (útil en local si mail() no está configurado)
if (!defined('DEV_SHOW_2FA_CODE')) {
  define('DEV_SHOW_2FA_CODE', true);
}

// Si no hay desafío 2FA, volver a login
if (!isset($_SESSION['2fa'])) {
  header('Location: login.php');
  exit;
}

$twofa   = $_SESSION['2fa'];
$mensaje = '';

// Reenviar código (regenera y renueva expiración)
if (isset($_GET['resend'])) {
  $code = (string) random_int(100000, 999999);
  $_SESSION['2fa']['code'] = $code;
  $_SESSION['2fa']['expires'] = time() + (30 * 60);

  // Reenviar con mail()
  $subject = 'Tu código de verificación - La Nueva Venta';
  $messageBody = "Hola,\n\nTu nuevo código de verificación es: $code\nEs válido por 30 minutos.\n\nSi no fuiste tú, ignora este correo.";
  $headers = "From: La Nueva Venta <no-reply@lanuevaventa.local>\r\n".
             "Content-Type: text/plain; charset=UTF-8\r\n";
  @mail($twofa['email'], $subject, $messageBody, $headers);

  $mensaje = 'Se envió un nuevo código. Revisa tu bandeja de entrada o SPAM.';
  $twofa = $_SESSION['2fa'];
}

// Validar código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $codigo = trim($_POST['codigo'] ?? '');
  if ($twofa['expires'] < time()) {
    $mensaje = 'El código expiró. Solicítalo nuevamente.';
  } elseif ($codigo === $twofa['code']) {
    $_SESSION['usuario'] = $twofa['user_id']; // activar sesión
    unset($_SESSION['2fa']);
    header('Location: index.php');
    exit;
  } else {
    $mensaje = 'Código incorrecto. Intenta nuevamente.';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="img/lnvVioleta.png">
  <title>Verificación en dos pasos</title>
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar fixed-top shadow-sm border-bottom">
    <div class="container-fluid d-flex align-items-center justify-content-between">
      <a class="navbar-brand p-0 me-3" href="index.php">
        <img src="img/lnvBlanco.png" alt="logo" style="height:45px;">
      </a>
    </div>
  </nav>

  <main class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="columna-central" style="max-width:520px;">
      <?php if ($mensaje): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
      <?php else: ?>
        <div class="alert alert-info">
          Enviamos un código a: <strong><?php echo htmlspecialchars($twofa['email']); ?></strong>. Es válido por 30 minutos.
        </div>
      <?php endif; ?>

      <?php if (DEV_SHOW_2FA_CODE): ?>
        <div class="alert alert-warning">
          Modo desarrollo: tu código es <strong><?php echo htmlspecialchars($twofa['code']); ?></strong>
        </div>
      <?php endif; ?>

      <form class="login-form" method="POST" action="verify_2fa.php" autocomplete="off">
        <button type="button" class="btn-volver" onclick="window.location='login.php'">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div class="form-logo" id="formLogo">
          <img src="img/lnvBlanco.png" alt="" id="logoPrin">
        </div>

        <input type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
               name="codigo" class="input-form" placeholder="Código de 6 dígitos" required>

        <button type="submit" class="btn-iniciar">Verificar</button>

        <div class="mt-3 text-center">
          <a class="link-registrarse" href="verify_2fa.php?resend=1">Reenviar código</a>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
