<?php
// filepath: c:\Users\nicot\OneDrive\Desktop\Proyecto\laNuevaVenta\laNuevaVenta-beta-0.1.1-Prubeas\src\producto.php
session_start();
require_once 'conexion.php';

// Función para verificar si es admin
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

// Obtener el ID del producto desde la URL
$id_producto = intval($_GET['id'] ?? 0);

if ($id_producto <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del producto
$query_producto = "SELECT p.*, u.nombre, u.apellido 
                   FROM Producto p 
                   LEFT JOIN Vende v ON p.id_producto = v.id_producto 
                   LEFT JOIN Usuario u ON v.id_usuario = u.id_usuario 
                   WHERE p.id_producto = $1";
$result_producto = pg_query_params($conexion, $query_producto, [$id_producto]);
$producto = pg_fetch_assoc($result_producto);

if (!$producto) {
    header('Location: index.php');
    exit;
}

$mensaje_carrito = '';
$tipo_mensaje = 'info';

// Agregar al carrito si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_carrito'])) {
    if (isset($_SESSION['usuario'])) {
        $id_usuario = $_SESSION['usuario'];
        
        // Verificar si ya está en el carrito
        $query_check = "SELECT 1 FROM enCarrito WHERE id_producto = $1 AND id_usuario = $2";
        $result_check = pg_query_params($conexion, $query_check, [$id_producto, $id_usuario]);
        
        if (!pg_fetch_assoc($result_check)) {
            // Agregar al carrito
            $fecha_creacion = date('Y-m-d H:i:s');
            $query_carrito = "INSERT INTO enCarrito (id_producto, id_usuario, fecha_creacion) VALUES ($1, $2, $3)";
            $resultado = pg_query_params($conexion, $query_carrito, [$id_producto, $id_usuario, $fecha_creacion]);
            
            if ($resultado) {
                $mensaje_carrito = "¡Producto agregado al carrito exitosamente!";
                $tipo_mensaje = 'success';
            } else {
                $mensaje_carrito = "Error al agregar el producto al carrito";
                $tipo_mensaje = 'danger';
            }
        } else {
            $mensaje_carrito = "El producto ya está en tu carrito";
            $tipo_mensaje = 'warning';
        }
    } else {
        $mensaje_carrito = "Debes iniciar sesión para agregar al carrito";
        $tipo_mensaje = 'warning';
    }
}

// Función helper para mostrar datos de forma segura
function mostrarDato($valor, $porDefecto = 'No especificado') {
    return htmlspecialchars($valor ?? $porDefecto);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title><?php echo mostrarDato($producto['titulo']); ?> - La Nueva Venta</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar fixed-top shadow-sm border-bottom">
        <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <!-- Logo -->
            <a class="navbar-brand p-0 me-3 flex-shrink-0" href="index.php">
                <img src="img/lnvBlanco.png" alt="logo" style="height: 45px;">
            </a>
            <!-- Buscador -->
            <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;">
                <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" placeholder="Buscar..." aria-label="Buscar" style="min-width: 0;">
                <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            <!-- Íconos -->
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
    
    <!-- Contenido principal -->
    <main class="producto-main">
        <!-- Mensaje de carrito -->
        <?php if ($mensaje_carrito): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" style="grid-column: 1 / -1;">
                <?php echo htmlspecialchars($mensaje_carrito); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Galería de miniaturas -->
        <div class="producto-galeria" id="galeriaMiniaturas">
            <img src="<?php echo mostrarDato($producto['imagen']); ?>" class="selected" data-idx="0" alt="Imagen 1" onerror="this.src='img/placeholder.png'">
            <img src="<?php echo mostrarDato($producto['imagen']); ?>" data-idx="1" alt="Imagen 2" onerror="this.src='img/placeholder.png'">
            <img src="<?php echo mostrarDato($producto['imagen']); ?>" data-idx="2" alt="Imagen 3" onerror="this.src='img/placeholder.png'">
            <img src="<?php echo mostrarDato($producto['imagen']); ?>" data-idx="3" alt="Imagen 4" onerror="this.src='img/placeholder.png'">
        </div>
        
        <!-- Imagen principal -->
        <div class="producto-imagen-principal" id="imagenPrincipal">
            <img src="<?php echo mostrarDato($producto['imagen']); ?>" id="imgPrincipal" alt="<?php echo mostrarDato($producto['titulo']); ?>" onerror="this.src='img/placeholder.png'">
        </div>
        
        <!-- Información del producto -->
        <div class="producto-info" id="infoProducto">
            <div class="producto-nombre"><?php echo mostrarDato($producto['titulo']); ?></div>
            <div class="producto-precio">$<?php echo number_format($producto['precio'] ?? 0, 2); ?></div>
            <div class="producto-descripcion"><?php echo mostrarDato($producto['descripcion']); ?></div>
            
            <p><strong>Stock disponible:</strong> <?php echo mostrarDato($producto['stock']); ?> unidades</p>
            <p><strong>Categoría:</strong> <?php echo mostrarDato($producto['categoria']); ?></p>
            
            <?php if ($producto['nombre']): ?>
                <p><strong>Vendedor:</strong> <?php echo mostrarDato($producto['nombre'] . ' ' . $producto['apellido']); ?></p>
            <?php endif; ?>
            
            <hr>
            
            <form method="POST" action="">
                <button type="submit" name="agregar_carrito" class="btn-agregar-carrito">
                    <i class="bi bi-cart-plus"></i> Agregar Al Carrito
                </button>
            </form>
        </div>
        
        <!-- Sección de comentarios -->
        <div class="producto-comentarios" id="comentarios">
            <div class="card">
                <div class="card-body">
                    <h3><i class="bi bi-chat-dots me-2"></i>Comentarios</h3>
                    <div id="listaComentarios">
                        <p class="text-muted">Los comentarios estarán disponibles próximamente.</p>
                    </div>
                    
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <hr>
                        <form id="formComentario" class="mt-3">
                            <div class="mb-3">
                                <label for="inputComentario" class="form-label">Escribe tu comentario:</label>
                                <textarea id="inputComentario" class="form-control" placeholder="Comparte tu opinión sobre este producto..." required maxlength="500"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Enviar Comentario
                            </button>
                        </form>
                    <?php else: ?>
                        <hr>
                        <p class="text-muted mt-3 text-center">
                            <i class="bi bi-person-plus me-2"></i>
                            <a href="login.php">Inicia sesión</a> para escribir comentarios.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Script para cambiar imagen principal al hacer clic en miniatura
        document.addEventListener('DOMContentLoaded', function() {
            const miniaturas = document.querySelectorAll('.producto-galeria img');
            const imagenPrincipal = document.getElementById('imgPrincipal');
            
            miniaturas.forEach(miniatura => {
                miniatura.addEventListener('click', function() {
                    // Remover clase selected de todas las miniaturas
                    miniaturas.forEach(img => img.classList.remove('selected'));
                    
                    // Agregar clase selected a la miniatura clickeada
                    this.classList.add('selected');
                    
                    // Cambiar imagen principal
                    imagenPrincipal.src = this.src;
                });
            });
            
            // Manejar formulario de comentarios
            const formComentario = document.getElementById('formComentario');
            if (formComentario) {
                formComentario.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('Funcionalidad de comentarios próximamente disponible');
                });
            }
        });
    </script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>