<?php
// Lee variables de entorno, con defaults seguros.
$host = getenv('DB_HOST') ?: 'localhost'; // antes: 'db'
$port = getenv('DB_PORT') ?: '5432';
$usuario = getenv('DB_USER') ?: 'utu';
$password = getenv('DB_PASSWORD') ?: '12345678';
$base_de_datos = getenv('DB_NAME') ?: 'mi_base_de_datos';

// Si el host viene como 'db' pero no estamos en Docker, usa localhost
if ($host === 'db' && !file_exists('/.dockerenv')) {
    $host = 'localhost';
}

$conn_string = "host={$host} port={$port} dbname={$base_de_datos} user={$usuario} password={$password} connect_timeout=5";

// Retry DNS/connection a few times (useful at container startup)
$intentos = 10;
$conexion = false;
for ($i = 1; $i <= $intentos; $i++) {
    $conexion = @pg_connect($conn_string);
    if ($conexion) break;

    // Si falla la resolución de 'db', espera y reintenta
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
    // No exponemos usuario/password; mostramos host/port/db para diagnóstico
    die("Error de conexión a PostgreSQL: " . htmlspecialchars($msg) . " | host={$host} port={$port} db={$base_de_datos}");
}
?>