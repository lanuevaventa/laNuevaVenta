<?php
// Configuración de la conexión a la base de datos
$host = 'localhost';
$usuario = 'root';
$contraseña = ''; 
$base_de_datos = 'mi_base_de_datos';

$conexion = new mysqli($host, $usuario, $contraseña, $base_de_datos);

if ($conn->connect_error) {
  die("Error de conexión: " . $conn->connect_error);
}

echo "Conexion exitosa";

?>