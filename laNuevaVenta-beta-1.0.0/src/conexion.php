<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$usuario = getenv('DB_USER') ?: 'utu';
$password = getenv('DB_PASSWORD') ?: '12345678';
$base_de_datos = getenv('DB_NAME') ?: 'mi_base_de_datos';

if ($host === 'db' && !file_exists('/.dockerenv')) {
    $host = 'localhost';
}

$conn_string = "host={$host} port={$port} dbname={$base_de_datos} user={$usuario} password={$password} connect_timeout=5";

$intentos = 10;
$conexion = false;
for ($i = 1; $i <= $intentos; $i++) {
    $conexion = @pg_connect($conn_string);
    if ($conexion) break;
    if ($host === 'db' && function_exists('gethostbyname') && gethostbyname('db') === 'db') {
        sleep(1);
        continue;
    }
    sleep(1);
}

if (!$conexion) {
    $err = error_get_last();
    $msg = $err && isset($err['message']) ? $err['message'] : 'No se pudo conectar a la base de datos';
    http_response_code(500);
    die("Error de conexión a PostgreSQL: " . htmlspecialchars($msg) . " | host={$host} port={$port} db={$base_de_datos}");
}
?>