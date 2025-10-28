<?php
session_start();
require_once 'conexion.php';

// Funcion para verificar si es admin
function esAdmin()
{
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

// Comentarios padre (nivel 1)
$query_comentarios_padre = "SELECT c.*, u.nombre, u.apellido 
                            FROM Comentario c 
                            JOIN Usuario u ON c.id_usuario = u.id_usuario 
                            WHERE c.id_producto = $1 AND c.id_comentario_padre IS NULL
                            ORDER BY c.fecha_comentario DESC";
$result_comentarios_padre = pg_query_params($conexion, $query_comentarios_padre, [$id_producto]);
$comentarios = [];
if ($result_comentarios_padre) {
    while ($row = pg_fetch_assoc($result_comentarios_padre)) {
        $comentarios[] = $row;
    }
}

// Respuestas de comentarios (nivel 2)
$query_respuestas = "SELECT c.*, u.nombre, u.apellido 
                     FROM Comentario c 
                     JOIN Usuario u ON c.id_usuario = u.id_usuario 
                     WHERE c.id_producto = $1 AND c.id_comentario_padre IS NOT NULL
                     ORDER BY c.fecha_comentario ASC";
$result_respuestas = pg_query_params($conexion, $query_respuestas, [$id_producto]);
$respuestasPorPadre = [];
if ($result_respuestas) {
    while ($row = pg_fetch_assoc($result_respuestas)) {
        $padre = $row['id_comentario_padre'];
        if (!isset($respuestasPorPadre[$padre])) {
            $respuestasPorPadre[$padre] = [];
        }
        $respuestasPorPadre[$padre][] = $row;
    }
}

$mensaje_carrito = '';
$tipo_mensaje = 'info';
$mensaje_comentario = '';
$toast_agregado = false; // Flag para mostrar el toast al agregar al carrito

// Editar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $id_comentario = intval($_POST['id_comentario'] ?? 0);
        $contenido = trim($_POST['comentario'] ?? '');

        if ($id_comentario > 0 && !empty($contenido) && strlen($contenido) <= 500) {
            // Verificar que el comentario pertenece al usuario actual
            $query_verificar = "SELECT id_usuario FROM Comentario WHERE id_comentario = $1";
            $result_verificar = pg_query_params($conexion, $query_verificar, [$id_comentario]);
            $comentario_data = pg_fetch_assoc($result_verificar);

            if ($comentario_data && $comentario_data['id_usuario'] == $_SESSION['usuario']) {
                $query_editar = "UPDATE Comentario SET contenido = $1 WHERE id_comentario = $2";
                $result_editar = pg_query_params($conexion, $query_editar, [$contenido, $id_comentario]);

                if ($result_editar) {
                    header("Location: producto.php?id=$id_producto");
                    exit;
                } else {
                    $mensaje_comentario = "Error al editar el comentario";
                }
            } else {
                $mensaje_comentario = "No tienes permisos para editar este comentario";
            }
        } else {
            $mensaje_comentario = "El comentario no puede estar vacío y debe tener máximo 500 caracteres";
        }
    }
}

// Eliminar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $id_comentario = intval($_POST['id_comentario'] ?? 0);

        if ($id_comentario > 0) {
            // Verificar que el comentario pertenece al usuario actual
            $query_verificar = "SELECT id_usuario FROM Comentario WHERE id_comentario = $1";
            $result_verificar = pg_query_params($conexion, $query_verificar, [$id_comentario]);
            $comentario_data = pg_fetch_assoc($result_verificar);

            if ($comentario_data && $comentario_data['id_usuario'] == $_SESSION['usuario']) {
                $query_eliminar = "DELETE FROM Comentario WHERE id_comentario = $1";
                $result_eliminar = pg_query_params($conexion, $query_eliminar, [$id_comentario]);

                if ($result_eliminar) {
                    header("Location: producto.php?id=$id_producto");
                    exit;
                } else {
                    $mensaje_comentario = "Error al eliminar el comentario";
                }
            } else {
                $mensaje_comentario = "No tienes permisos para eliminar este comentario";
            }
        }
    }
}

// Agregar comentario (nuevo hilo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $contenido = trim($_POST['comentario'] ?? '');
        if (!empty($contenido) && strlen($contenido) <= 500) {
            $query_comentario = "INSERT INTO Comentario (id_producto, id_usuario, contenido) VALUES ($1, $2, $3)";
            $result_comentario = pg_query_params($conexion, $query_comentario, [$id_producto, $_SESSION['usuario'], $contenido]);

            if ($result_comentario) {
                header("Location: producto.php?id=$id_producto");
                exit;
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

// Responder a un comentario (hilo existente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_comentario'])) {
    if (isset($_SESSION['usuario'])) {
        $id_padre = intval($_POST['id_padre'] ?? 0);
        $contenido = trim($_POST['respuesta'] ?? '');
        if ($id_padre > 0 && !empty($contenido) && strlen($contenido) <= 500) {
            $query_responder = "INSERT INTO Comentario (id_producto, id_usuario, contenido, id_comentario_padre) VALUES ($1, $2, $3, $4)";
            $result_responder = pg_query_params($conexion, $query_responder, [$id_producto, $_SESSION['usuario'], $contenido, $id_padre]);
            if ($result_responder) {
                header("Location: producto.php?id=$id_producto");
                exit;
            } else {
                $mensaje_comentario = "Error al responder el comentario";
            }
        } else {
            $mensaje_comentario = "La respuesta no puede estar vacía y debe tener máximo 500 caracteres";
        }
    } else {
        $mensaje_comentario = "Debes iniciar sesión para responder";
    }
}

// Agregar al carrito si se envia el formulario (con validación de stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_carrito'])) {
    if (isset($_SESSION['usuario'])) {
        // Validación de stock actual desde DB por seguridad
        $q_stock = "SELECT stock FROM Producto WHERE id_producto = $1";
        $r_stock = pg_query_params($conexion, $q_stock, [$id_producto]);
        $row_stock = pg_fetch_assoc($r_stock);
        $stock_actual = $row_stock ? intval($row_stock['stock']) : 0;

        if ($stock_actual <= 0) {
            $mensaje_carrito = "Este producto no tiene stock disponible.";
            $tipo_mensaje = 'warning';
        } else {
            // Inicializar carrito si no existe
            if (!isset($_SESSION['carrito'])) {
                $_SESSION['carrito'] = array();
            }

            $carrito = $_SESSION['carrito'];

            // Verificar si el producto ya esta en el carrito (se maneja 1 unidad por diseño actual)
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
                $toast_agregado = true; // activar toast en la recarga/render
            }
        }
    } else {
        $mensaje_carrito = "Debes iniciar sesión para agregar al carrito";
        $tipo_mensaje = 'warning';
    }
}

// para mostrar datos de forma segura
function mostrarDato($valor, $porDefecto = 'No especificado')
{
    return htmlspecialchars($valor ?? $porDefecto);
}

// Función para calcular precio con oferta
function calcularPrecioConOferta($precio, $oferta_activa, $oferta_tipo, $oferta_valor, $oferta_desde, $oferta_hasta) {
    // Validar que la oferta esté activa
    if (!$oferta_activa || $oferta_tipo === null || $oferta_valor === null) {
        return null;
    }
    
    // Validar fechas si existen
    $ahora = new DateTime();
    if ($oferta_desde && new DateTime($oferta_desde) > $ahora) {
        return null; // Oferta aún no comenzó
    }
    if ($oferta_hasta && new DateTime($oferta_hasta) < $ahora) {
        return null; // Oferta expiró
    }
    
    // Calcular descuento
    if ($oferta_tipo === 'porcentaje') {
        $descuento = $precio * ($oferta_valor / 100);
    } else { // fijo
        $descuento = $oferta_valor;
    }
    
    $precio_final = $precio - $descuento;
    return max(0, $precio_final); // No permitir precios negativos
}

// Calcular precio con oferta si existe
$precio_original = floatval($producto['precio']);
$precio_oferta = null;
$tiene_oferta_valida = false;

if (isset($producto['oferta_activa']) && ($producto['oferta_activa'] === 't' || $producto['oferta_activa'] == 1)) {
    $precio_oferta = calcularPrecioConOferta(
        $precio_original,
        true,
        $producto['oferta_tipo'] ?? null,
        isset($producto['oferta_valor']) ? floatval($producto['oferta_valor']) : null,
        $producto['oferta_desde'] ?? null,
        $producto['oferta_hasta'] ?? null
    );
    $tiene_oferta_valida = ($precio_oferta !== null);
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
    <style>
        .comentario-acciones {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .btn-editar-comentario,
        .btn-eliminar-comentario,
        .btn-responder-comentario {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-editar-comentario:hover {
            color: var(--violeta-fuerte);
        }

        .btn-eliminar-comentario:hover {
            color: #dc3545;
        }

        .btn-responder-comentario:hover {
            color: #198754;
        }

        .form-editar-comentario,
        .form-responder-comentario {
            display: none;
            margin-top: 0.75rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .respuesta-item {
            margin-left: 2rem;
            padding-left: 1rem;
            border-left: 3px solid #eee;
        }

        /* Favoritos */
        .btn-favorito {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            padding: 0.25rem 0.5rem;
            line-height: 1;
        }

        .btn-favorito.active {
            color: #e63946;
        }

        .stock-badge {
            display: inline-block;
            padding: .25rem .6rem;
            border-radius: .5rem;
            font-size: .85rem;
        }

        .stock-ok {
            background: #e7f7ed;
            color: #198754;
        }

        .stock-out {
            background: #fde7e9;
            color: #dc3545;
        }

        .btn-agregar-carrito[disabled] {
            opacity: .7;
            cursor: not-allowed;
        }

        .precio-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0.5rem 0;
        }

        .precio-original {
            font-size: 1.2rem;
            color: #6c757d;
            text-decoration: line-through;
        }

        .precio-oferta {
            font-size: 1.8rem;
            font-weight: bold;
            color: #e63946;
        }

        .badge-oferta {
            background: linear-gradient(135deg, #e63946 0%, #d62839 100%);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(230, 57, 70, 0.3);
        }

        .producto-precio {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--violeta-fuerte);
            margin: 0.5rem 0;
        }
    </style>
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
                                <li>
                                    <hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.3);">
                                </li>
                                <li>
                                    <h6 class="dropdown-header text-cream" style="font-size: 0.8rem; opacity: 0.8;">ADMINISTRADOR</h6>
                                </li>
                                <li><a class="dropdown-item" href="admin.php"><i class="bi bi-speedometer2 me-2"></i>Panel Admin</a></li>
                                <li><a class="dropdown-item" href="db_viewer.php"><i class="bi bi-database me-2"></i>Ver Base de Datos</a></li>
                            <?php endif; ?>

                            <li>
                                <hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.3);">
                            </li>
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
        <!-- mensajes -->
        <?php if ($mensaje_carrito): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" style="grid-column: 1 / -1;">
                <?php echo htmlspecialchars($mensaje_carrito); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_comentario): ?>
            <div class="alert alert-info alert-dismissible fade show" style="grid-column: 1 / -1;">
                <?php echo htmlspecialchars($mensaje_comentario); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- galeria de miniaturas -->
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
            <div class="d-flex align-items-start justify-content-between">
                <div style="flex: 1;">
                    <div class="producto-nombre"><?php echo mostrarDato($producto['titulo']); ?></div>
                    
                    <?php if ($tiene_oferta_valida): ?>
                        <div class="precio-container">
                            <span class="precio-original">$<?php echo number_format($precio_original, 2); ?></span>
                            <span class="precio-oferta">$<?php echo number_format($precio_oferta, 2); ?></span>
                            <span class="badge-oferta">
                                <?php 
                                if ($producto['oferta_tipo'] === 'porcentaje') {
                                    echo '-' . number_format($producto['oferta_valor'], 0) . '%';
                                } else {
                                    echo '-$' . number_format($producto['oferta_valor'], 2);
                                }
                                ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="producto-precio">$<?php echo number_format($precio_original, 2); ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-favorito" id="btnFavorito" aria-label="Agregar a favoritos" title="Agregar a favoritos" onclick="tirarToast(agregarFav)">
                    <i id="iconFavorito" class="bi bi-heart"></i>
                </button>
            </div>
            
            <div class="producto-descripcion mb-2"><?php echo mostrarDato($producto['descripcion']); ?></div>

            <p class="mb-1">
                <strong>Stock:</strong>
                <?php if (intval($producto['stock']) > 0): ?>
                    <span class="stock-badge stock-ok"><?php echo intval($producto['stock']); ?> disponibles</span>
                <?php else: ?>
                    <span class="stock-badge stock-out">Sin stock</span>
                <?php endif; ?>
            </p>
            <p class="mb-1"><strong>Categoría:</strong> <?php echo mostrarDato($producto['categoria']); ?></p>
            <?php if ($producto['nombre']): ?>
                <p class="mb-2"><strong>Vendedor:</strong> <?php echo mostrarDato($producto['nombre'] . ' ' . $producto['apellido']); ?></p>
            <?php endif; ?>

            <hr>

            <form method="POST" action="">
                <button
                    type="submit"
                    name="agregar_carrito"
                    class="btn-agregar-carrito"
                    <?php echo (intval($producto['stock']) <= 0) ? 'disabled aria-disabled="true" title="Sin stock disponible"' : ''; ?>>
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
                                    <div class="comentario-contenido" id="contenido-<?php echo $comentario['id_comentario']; ?>">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($comentario['contenido'])); ?></p>
                                    </div>

                                    <div class="comentario-acciones">
                                        <?php if (isset($_SESSION['usuario'])): ?>
                                            <button class="btn-responder-comentario" onclick="mostrarFormResponder(<?php echo $comentario['id_comentario']; ?>)">
                                                <i class="bi bi-reply me-1"></i>Responder
                                            </button>
                                        <?php endif; ?>

                                        <?php if (isset($_SESSION['usuario']) && $_SESSION['usuario'] == $comentario['id_usuario']): ?>
                                            <button class="btn-editar-comentario" onclick="mostrarFormEditar(<?php echo $comentario['id_comentario']; ?>)">
                                                <i class="bi bi-pencil me-1"></i>Editar
                                            </button>
                                            <button class="btn-eliminar-comentario" onclick="confirmarEliminar(<?php echo $comentario['id_comentario']; ?>)">
                                                <i class="bi bi-trash me-1"></i>Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Formulario de edición -->
                                    <?php if (isset($_SESSION['usuario']) && $_SESSION['usuario'] == $comentario['id_usuario']): ?>
                                        <div class="form-editar-comentario" id="form-editar-<?php echo $comentario['id_comentario']; ?>">
                                            <form method="POST" action="">
                                                <input type="hidden" name="id_comentario" value="<?php echo $comentario['id_comentario']; ?>">
                                                <div class="mb-2">
                                                    <textarea name="comentario" class="form-control" required maxlength="500" rows="3"><?php echo htmlspecialchars($comentario['contenido']); ?></textarea>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="editar_comentario" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-check-lg me-1"></i>Guardar
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="ocultarFormEditar(<?php echo $comentario['id_comentario']; ?>)">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Formulario oculto para eliminar -->
                                        <form method="POST" action="" id="form-eliminar-<?php echo $comentario['id_comentario']; ?>" style="display: none;">
                                            <input type="hidden" name="id_comentario" value="<?php echo $comentario['id_comentario']; ?>">
                                            <input type="hidden" name="eliminar_comentario" value="1">
                                        </form>
                                    <?php endif; ?>

                                    <!-- Formulario de respuesta -->
                                    <?php if (isset($_SESSION['usuario'])): ?>
                                        <div class="form-responder-comentario" id="form-responder-<?php echo $comentario['id_comentario']; ?>">
                                            <form method="POST" action="">
                                                <input type="hidden" name="id_padre" value="<?php echo $comentario['id_comentario']; ?>">
                                                <div class="mb-2">
                                                    <textarea name="respuesta" class="form-control" required maxlength="500" rows="2" placeholder="Escribe tu respuesta..."></textarea>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="responder_comentario" class="btn btn-success btn-sm">
                                                        <i class="bi bi-send me-1"></i>Responder
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="ocultarFormResponder(<?php echo $comentario['id_comentario']; ?>)">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Respuestas renderizadas -->
                                    <?php if (isset($respuestasPorPadre[$comentario['id_comentario']])): ?>
                                        <div class="mt-3">
                                            <?php foreach ($respuestasPorPadre[$comentario['id_comentario']] as $respuesta): ?>
                                                <div class="respuesta-item mb-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <div>
                                                            <strong class="text-primary">
                                                                <i class="bi bi-person-circle me-1"></i>
                                                                <?php echo htmlspecialchars($respuesta['nombre'] . ' ' . $respuesta['apellido']); ?>
                                                            </strong>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php
                                                            $f2 = new DateTime($respuesta['fecha_comentario']);
                                                            echo $f2->format('d/m/Y H:i');
                                                            ?>
                                                        </small>
                                                    </div>
                                                    <div class="comentario-contenido" id="contenido-<?php echo $respuesta['id_comentario']; ?>">
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($respuesta['contenido'])); ?></p>
                                                    </div>

                                                    <?php if (isset($_SESSION['usuario']) && $_SESSION['usuario'] == $respuesta['id_usuario']): ?>
                                                        <div class="comentario-acciones">
                                                            <button class="btn-editar-comentario" onclick="mostrarFormEditar(<?php echo $respuesta['id_comentario']; ?>)">
                                                                <i class="bi bi-pencil me-1"></i>Editar
                                                            </button>
                                                            <button class="btn-eliminar-comentario" onclick="confirmarEliminar(<?php echo $respuesta['id_comentario']; ?>)">
                                                                <i class="bi bi-trash me-1"></i>Eliminar
                                                            </button>
                                                        </div>

                                                        <div class="form-editar-comentario" id="form-editar-<?php echo $respuesta['id_comentario']; ?>">
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="id_comentario" value="<?php echo $respuesta['id_comentario']; ?>">
                                                                <div class="mb-2">
                                                                    <textarea name="comentario" class="form-control" required maxlength="500" rows="2"><?php echo htmlspecialchars($respuesta['contenido']); ?></textarea>
                                                                </div>
                                                                <div class="d-flex gap-2">
                                                                    <button type="submit" name="editar_comentario" class="btn btn-primary btn-sm">
                                                                        <i class="bi bi-check-lg me-1"></i>Guardar
                                                                    </button>
                                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="ocultarFormEditar(<?php echo $respuesta['id_comentario']; ?>)">
                                                                        Cancelar
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>

                                                        <form method="POST" action="" id="form-eliminar-<?php echo $respuesta['id_comentario']; ?>" style="display: none;">
                                                            <input type="hidden" name="id_comentario" value="<?php echo $respuesta['id_comentario']; ?>">
                                                            <input type="hidden" name="eliminar_comentario" value="1">
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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
                            <a href="login.php">Inicia sesión</a> para escribir comentarios y responder.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Función global para cambiar imagen principal
        function cambiarImagenPrincipal(elemento) {
            const miniaturas = document.querySelectorAll('.producto-galeria img');
            miniaturas.forEach(img => img.classList.remove('selected'));
            elemento.classList.add('selected');
            document.getElementById('imgPrincipal').src = elemento.src;
        }

        // Mostrar/Ocultar formulario de edición
        function mostrarFormEditar(idComentario) {
            const contenido = document.getElementById('contenido-' + idComentario);
            const form = document.getElementById('form-editar-' + idComentario);
            if (contenido && form) {
                contenido.style.display = 'none';
                form.style.display = 'block';
            }
        }

        function ocultarFormEditar(idComentario) {
            const contenido = document.getElementById('contenido-' + idComentario);
            const form = document.getElementById('form-editar-' + idComentario);
            if (contenido && form) {
                contenido.style.display = 'block';
                form.style.display = 'none';
            }
        }

        // Mostrar/Ocultar formulario de respuesta
        function mostrarFormResponder(idComentario) {
            const form = document.getElementById('form-responder-' + idComentario);
            if (form) form.style.display = 'block';
        }

        function ocultarFormResponder(idComentario) {
            const form = document.getElementById('form-responder-' + idComentario);
            if (form) form.style.display = 'none';
        }

        // Confirmar eliminación
        function confirmarEliminar(idComentario) {
            if (confirm('¿Estás seguro de que deseas eliminar este comentario?')) {
                const form = document.getElementById('form-eliminar-' + idComentario);
                if (form) form.submit();
            }
        }

        // Favoritos con localStorage
        (function initFavoritos() {
            const btn = document.getElementById('btnFavorito');
            const icon = document.getElementById('iconFavorito');
            if (!btn || !icon) return;

            const productoId = <?php echo (int)$id_producto; ?>;
            const userId = <?php echo isset($_SESSION['usuario']) ? (int)$_SESSION['usuario'] : 'null'; ?>;
            const storageKey = 'lnv_favoritos_' + (userId ?? 'guest');

            function getFavs() {
                try {
                    return JSON.parse(localStorage.getItem(storageKey) || '[]');
                } catch {
                    return [];
                }
            }

            function setFavs(arr) {
                localStorage.setItem(storageKey, JSON.stringify(arr));
            }

            function isFav(id) {
                const favs = getFavs();
                return favs.includes(id);
            }

            function updateUI(active) {
                btn.classList.toggle('active', active);
                icon.classList.toggle('bi-heart', !active);
                icon.classList.toggle('bi-heart-fill', active);
            }

            // Estado inicial
            updateUI(isFav(productoId));

            btn.addEventListener('click', () => {
                const favs = getFavs();
                const idx = favs.indexOf(productoId);
                if (idx >= 0) {
                    favs.splice(idx, 1);
                    setFavs(favs);
                    updateUI(false);
                } else {
                    favs.push(productoId);
                    setFavs(favs);
                    updateUI(true);
                }
            });
        })();

        // Script para cambiar imagen principal al hacer clic en miniatura
        document.addEventListener('DOMContentLoaded', function() {
            const miniaturas = document.querySelectorAll('.producto-galeria img');
            miniaturas.forEach(miniatura => {
                miniatura.addEventListener('click', function() {
                    cambiarImagenPrincipal(this);
                });
            });
        });
    </script>

    <!-- Toast de agregado al carrito -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="agregarFav" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    Producto agregado a favoritos
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

   
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <?php if (!empty($toast_agregado)): ?>
    <script>
        // Mostrar el toast solo cuando el producto fue agregado correctamente
        document.addEventListener('DOMContentLoaded', function() {
            tirarToast('agregarFav');
        });
    </script>
    <?php endif; ?>
</body>

</html>