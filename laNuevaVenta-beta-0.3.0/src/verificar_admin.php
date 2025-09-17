<?php
// filepath: c:\Users\nicot\OneDrive\Desktop\Proyecto\laNuevaVenta\laNuevaVenta-beta-0.1.1-Prubeas\src\verificar_admin.php
require_once 'conexion.php';

// Solo iniciar sesión si no está ya activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAdmin() {
    global $conexion;
    
    // Verificar si el usuario está logueado
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php?error=acceso_denegado');
        exit;
    }
    
    // Obtener información del usuario
    $query = "SELECT rol FROM Usuario WHERE id_usuario = $1";
    $result = pg_query_params($conexion, $query, [$_SESSION['usuario']]);
    $usuario = pg_fetch_assoc($result);
    
    // Verificar si es admin
    if (!$usuario || $usuario['rol'] !== 'admin') {
        // Redirigir con mensaje de error
        header('Location: index.php?error=sin_permisos');
        exit;
    }
    
    return true;
}

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
?>