<?php
session_start();
require_once 'conexion.php';

// Bajar stock de los productos comprados
if (isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
    pg_query($conexion, 'BEGIN');
    
    try {
        foreach ($_SESSION['carrito'] as $id_producto => $item) {
            $cantidad = (int)$item['cantidad'];
            
            // Actualizar stock (solo si hay suficiente)
            $query = "UPDATE Producto 
                     SET stock = stock - $1 
                     WHERE id_producto = $2 AND stock >= $1";
            $result = pg_query_params($conexion, $query, [$cantidad, $id_producto]);
            
            if (!$result || pg_affected_rows($result) === 0) {
                throw new Exception("Stock insuficiente para el producto ID: $id_producto");
            }
        }
        
        pg_query($conexion, 'COMMIT');
        
        // Vaciar carrito
        unset($_SESSION['carrito']);
        
    } catch (Exception $e) {
        pg_query($conexion, 'ROLLBACK');
        error_log("Error al actualizar stock: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Exitoso - La Nueva Venta</title>
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h2 class="text-success mb-3">¡Pago Exitoso!</h2>
                        <p class="lead mb-4">Tu compra se procesó correctamente</p>
                        <p class="text-muted mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Recibirás un correo con los detalles de tu compra
                        </p>
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-violeta btn-lg">
                                <i class="bi bi-house-door me-2"></i>
                                Volver al inicio
                            </a>
                            <a href="cuenta.php" class="btn btn-outline-secondary">
                                <i class="bi bi-person me-2"></i>
                                Ver mi cuenta
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>