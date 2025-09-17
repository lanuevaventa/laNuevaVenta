<?php
$host = 'db'; // El nombre del servicio en docker-compose
$port = '5432';
$usuario = 'utu';
$password = '12345678';
$base_de_datos = 'mi_base_de_datos';

$conn_string = "host=$host port=$port dbname=$base_de_datos user=$usuario password=$password";
$conexion = pg_connect($conn_string);

if (!$conexion) {
    die("Error de conexión: " . pg_last_error());
}
