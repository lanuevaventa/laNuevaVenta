<?php
session_start();
require_once 'conexion.php';

define('GOOGLE_CLIENT_ID', '418899644432-ocvscm4moul8p6hta8oei7ss2gbbgpve.apps.googleusercontent.com'); // reemplazar

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
  exit;
}

$token = $_POST['credential'] ?? '';
if (!$token) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Falta credential']);
  exit;
}

// Llamar a tokeninfo (para entorno simple)
$endpoint = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 5
]);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if (!$response) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'No se pudo validar token: '.$curlErr]);
  exit;
}

$info = json_decode($response, true);
if (!is_array($info)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Respuesta inválida de Google']);
  exit;
}

$email = $info['email'] ?? null;
$aud   = $info['aud'] ?? null;
$exp   = isset($info['exp']) ? (int)$info['exp'] : 0;
$name  = $info['name'] ?? ($info['given_name'] ?? 'Usuario');

if (!$email || !$aud || $aud !== GOOGLE_CLIENT_ID) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Token no válido (aud/email)']);
  exit;
}
if ($exp < time()) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Token expirado']);
  exit;
}

// Separar nombre/apellido
$parts = preg_split('/\s+/', trim($name));
$nombre = $parts[0] ?? $name;
$apellido = count($parts) > 1 ? implode(' ', array_slice($parts,1)) : '';

$q = "SELECT id_usuario FROM Usuario WHERE correo = $1";
$r = pg_query_params($conexion, $q, [$email]);
$row = pg_fetch_assoc($r);

if (!$row) {
  $fecha = date('Y-m-d');
  $fakePass = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
  $ins = "INSERT INTO Usuario (nombre, apellido, correo, contrasenia, fecha_registro) VALUES ($1,$2,$3,$4,$5) RETURNING id_usuario";
  $r2 = pg_query_params($conexion, $ins, [$nombre, $apellido, $email, $fakePass, $fecha]);
  if (!$r2) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'No se pudo crear usuario']);
    exit;
  }
  $row = pg_fetch_assoc($r2);
}

$_SESSION['usuario'] = $row['id_usuario'];
echo json_encode(['ok'=>true]);
