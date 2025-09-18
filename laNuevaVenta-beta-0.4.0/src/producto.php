<?php
// filepath: c:\Users\nicot\OneDrive\Desktop\Proyecto\laNuevaVenta\laNuevaVenta-beta-0.1.1-Prubeas\src\producto.php
session_start();
require_once 'conexion.php';

// Funcion para verificar si es admin
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

// Obtener todas las imágenes del producto
$query_imagenes = "SELECT * FROM ImagenProducto WHERE id_producto = $1 ORDER BY orden_imagen ASC";
$result_imagenes = pg_query_params($conexion, $query_imagenes, [$id_producto]);
$imagenes = [];
if ($result_imagenes) {
    while ($row = pg_fetch_assoc($result_imagenes)) {
        $imagenes[] = $row;
    }
}

// Si no hay imágenes en la nueva tabla, usar la imagen del campo original
if (empty($imagenes) && !empty($producto['imagen'])) {
    $imagenes[] = [
        'ruta_imagen' => $producto['imagen'],
        'es_principal' => true,
        'orden_imagen' => 1
    ];
}

// Obtener comentarios del producto
$query_comentarios = "SELECT c.*, u.nombre, u.apellido 
                     FROM Comentario c 
                     JOIN Usuario u ON c.id_usuario = u.id_usuario 
                     WHERE c.id_producto = $1 
                     ORDER BY c.fecha_comentario DESC";
$result_comentarios = pg_query_params($conexion, $query_comentarios, [$id_producto]);
$comentarios = [];
if ($result_comentarios) {
    while ($row = pg_fetch_assoc($result_comentarios)) {
        $comentarios[] = $row;
    }
}

$mensaje_carrito = '';
$tipo_mensaje = 'info';
$mensaje_comentario = '';

// Agregar comentario si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $contenido = trim($_POST['comentario'] ?? '');
        if (!empty($contenido) && strlen($contenido) <= 500) {
            $query_comentario = "INSERT INTO Comentario (id_producto, id_usuario, contenido) VALUES ($1, $2, $3)";
            $result_comentario = pg_query_params($conexion, $query_comentario, [$id_producto, $_SESSION['usuario'], $contenido]);
            
            if ($result_comentario) {
                $mensaje_comentario = "¡Comentario agregado exitosamente!";
            } else {
                $mensaje_comentario = "Error al agregar el comentario";
            }
        } else {
            $mensaje_comentario = "El comentario no puede estar vacío y debe tener máximo 500 caracteres";
        }
    } else {
        $mensaje_comentario = "Debes iniciar sesión para comentar";
    }
}

// Agregar al carrito si se envia el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_carrito'])) {
    if (isset($_SESSION['usuario'])) {
        // Inicializar carrito si no existe
        if (!isset($_SESSION['carrito'])) {
            $_SESSION['carrito'] = array();
        }
        
        $carrito = $_SESSION['carrito'];
        
        // Verificar si el producto ya esta en el carrito
        if (isset($carrito[$id_producto])) {
            $mensaje_carrito = "El producto ya está en tu carrito";
            $tipo_mensaje = 'warning';
        } else {
            // Agregar producto al carrito
            $_SESSION['carrito'][$id_producto] = array(
                'nombre' => $producto['titulo'],
                'precio' => $producto['precio'],
                'imagen' => $producto['imagen'],
                'cantidad' => 1
            );
            
            $mensaje_carrito = "¡Producto agregado al carrito exitosamente!";
            $tipo_mensaje = 'success';
        }
    } else {
        $mensaje_carrito = "Debes iniciar sesión para agregar al carrito";
        $tipo_mensaje = 'warning';
    }
}

// para mostrar datos de forma segura v
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
            <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;" method="GET" action="index.php">
                <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" name="buscar" placeholder="Buscar productos..." aria-label="Buscar" style="min-width: 0;">
                <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            <!-- iconos -->
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
                    <?php 
                    $carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
                    if ($carrito_count > 0): 
                    ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $carrito_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Contenido -->
    <main class="producto-main">
        <!-- mensaje  carrito -->
        <?php if ($mensaje_carrito): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" style="grid-column: 1 / -1;">
                <?php echo htmlspecialchars($mensaje_carrito); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- mensaje comentario -->
        <?php if ($mensaje_comentario): ?>
            <div class="alert alert-info alert-dismissible fade show" style="grid-column: 1 / -1;">
                <?php echo htmlspecialchars($mensaje_comentario); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- galeria de miniaturas - Se muestra solo si hay más de una imagen -->
        <div class="producto-galeria" id="galeriaMiniaturas" <?php echo (count($imagenes) <= 1) ? 'style="display: none;"' : ''; ?>>
            <?php foreach ($imagenes as $index => $imagen): ?>
                <img src="<?php echo mostrarDato($imagen['ruta_imagen']); ?>" 
                     class="<?php echo ($index === 0) ? 'selected' : ''; ?>" 
                     data-idx="<?php echo $index; ?>" 
                     alt="Miniatura <?php echo $index + 1; ?>" 
                     onerror="this.src='img/placeholder.png'"
                     onclick="cambiarImagenPrincipal(this)">
            <?php endforeach; ?>
        </div>
        
        <!-- imagen principal -->
        <div class="producto-imagen-principal" id="imagenPrincipal">
            <?php if (!empty($imagenes)): ?>
                <img src="<?php echo mostrarDato($imagenes[0]['ruta_imagen']); ?>" 
                     id="imgPrincipal" 
                     alt="<?php echo mostrarDato($producto['titulo']); ?>" 
                     onerror="this.src='img/placeholder.png'">
            <?php else: ?>
                <img src="img/placeholder.png" 
                     id="imgPrincipal" 
                     alt="<?php echo mostrarDato($producto['titulo']); ?>">
            <?php endif; ?>
        </div>
        
        <!-- informacion -->
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
                    <h3><i class="bi bi-chat-dots me-2"></i>Comentarios (<?php echo count($comentarios); ?>)</h3>
                    
                    <div id="listaComentarios">
                        <?php if (empty($comentarios)): ?>
                            <p class="text-muted">Sé el primero en comentar este producto.</p>
                        <?php else: ?>
                            <?php foreach ($comentarios as $comentario): ?>
                                <div class="comentario-item border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="comentario-autor">
                                            <strong class="text-primary">
                                                <i class="bi bi-person-circle me-1"></i>
                                                <?php echo htmlspecialchars($comentario['nombre'] . ' ' . $comentario['apellido']); ?>
                                            </strong>
                                        </div>
                                        <small class="text-muted">
                                            <?php 
                                            $fecha = new DateTime($comentario['fecha_comentario']);
                                            echo $fecha->format('d/m/Y H:i');
                                            ?>
                                        </small>
                                    </div>
                                    <div class="comentario-contenido">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($comentario['contenido'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($_SESSION['usuario'])): ?>
                        <hr>
                        <form method="POST" action="" class="mt-3">
                            <div class="mb-3">
                                <label for="comentario" class="form-label">Escribe tu comentario:</label>
                                <textarea name="comentario" id="comentario" class="form-control" placeholder="Comparte tu opinión sobre este producto..." required maxlength="500" rows="3"></textarea>
                                <div class="form-text">Máximo 500 caracteres</div>
                            </div>
                            <button type="submit" name="enviar_comentario" class="btn btn-primary">
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
        // Función global para cambiar imagen principal
        function cambiarImagenPrincipal(elemento) {
            // Remover clase selected de todas las miniaturas
            const miniaturas = document.querySelectorAll('.producto-galeria img');
            miniaturas.forEach(img => img.classList.remove('selected'));
            
            // Agregar clase selected a la miniatura clickeada
            elemento.classList.add('selected');
            
            // Cambiar imagen principal
            document.getElementById('imgPrincipal').src = elemento.src;
        }
        
        // Script para cambiar imagen principal al hacer clic en miniatura
        document.addEventListener('DOMContentLoaded', function() {
            const miniaturas = document.querySelectorAll('.producto-galeria img');
            const imagenPrincipal = document.getElementById('imgPrincipal');
            
            miniaturas.forEach(miniatura => {
                miniatura.addEventListener('click', function() {
                    cambiarImagenPrincipal(this);
                });
            });
        });
    </script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>