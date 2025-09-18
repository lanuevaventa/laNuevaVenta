<?php
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$usuario = getenv('DB_USER') ?: 'utu';
$password = getenv('DB_PASSWORD') ?: '12345678';
$base_de_datos = getenv('DB_NAME') ?: 'mi_base_de_datos';

$conn_string = "host=$host port=$port dbname=$base_de_datos user=$usuario password=$password";
$conexion = pg_connect($conn_string);

if (!$conexion) {
    die("Error de conexión: " . pg_last_error());
}
