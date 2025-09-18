<?php
session_start();
require_once 'conexion.php';

$mensaje = '';
$exito = false;

// Verificar admin
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

// Solo permitir usuarios logueados
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Obtener el ID del producto desde la URL
$id_producto = intval($_GET['id'] ?? 0);

if ($id_producto <= 0) {
    header('Location: cuenta.php');
    exit;
}

$id_usuario = $_SESSION['usuario'];

// Verificar que el producto pertenece al usuario logueado
$query_verificar = "SELECT p.* FROM Producto p 
                   INNER JOIN Vende v ON p.id_producto = v.id_producto 
                   WHERE p.id_producto = $1 AND v.id_usuario = $2";
$result_verificar = pg_query_params($conexion, $query_verificar, [$id_producto, $id_usuario]);
$producto = pg_fetch_assoc($result_verificar);

if (!$producto) {
    header('Location: cuenta.php');
    exit;
}

// Obtener todas las imágenes del producto
$query_imagenes = "SELECT id_imagen, id_producto, ruta_imagen, es_principal, orden_imagen FROM ImagenProducto WHERE id_producto = $1 ORDER BY orden_imagen ASC";
$result_imagenes = pg_query_params($conexion, $query_imagenes, [$id_producto]);
$imagenes_existentes = [];
if ($result_imagenes) {
    while ($row = pg_fetch_assoc($result_imagenes)) {
        $imagenes_existentes[] = $row;
    }
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $imagen_url = $producto['imagen']; // Mantener imagen actual por defecto

    // Procesar eliminación de imágenes
    $imagenes_a_eliminar = $_POST['eliminar_imagenes'] ?? [];
    
    // Debug: mostrar cuántas imágenes se van a eliminar
    if (!empty($imagenes_a_eliminar)) {
        error_log("Imágenes a eliminar: " . print_r($imagenes_a_eliminar, true));
        $mensaje = 'DEBUG: Se van a eliminar ' . count($imagenes_a_eliminar) . ' imágenes: ' . implode(', ', $imagenes_a_eliminar);
    } else {
        error_log("No hay imágenes marcadas para eliminar");
    }
    
    // Validaciones básicas
    if ($titulo === '' || $precio <= 0 || $stock < 0 || $descripcion === '' || $categoria === '') {
        $mensaje = 'Completa todos los campos correctamente.';
    } else {
        // Verificar si se subieron nuevas imágenes
        $nuevas_imagenes = [];
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
            $img_dir = 'img/productos/';
            if (!is_dir($img_dir)) {
                mkdir($img_dir, 0777, true);
            }
            
            $files = $_FILES['imagenes'];
            $total_files = count($files['name']);
            
            // Validar que no se suban más de 5 imágenes nuevas
            if ($total_files > 5) {
                $mensaje = 'No puedes subir más de 5 imágenes.';
            } else {
                // Procesar cada archivo
                for ($i = 0; $i < $total_files; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_tmp = $files['tmp_name'][$i];
                        $file_name = $files['name'][$i];
                        $file_size = $files['size'][$i];
                        
                        // Validaciones
                        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (!in_array($ext, $allowed_exts)) {
                            $mensaje = "Formato de imagen no válido para $file_name. Solo se permiten: jpg, jpeg, png, gif, webp";
                            break;
                        }
                        
                        if ($file_size > 5 * 1024 * 1024) { // 5MB
                            $mensaje = "La imagen $file_name es muy grande. Tamaño máximo: 5MB";
                            break;
                        }
                        
                        // Generar nombre único
                        $nombre_archivo = uniqid('prod_') . '.' . $ext;
                        $ruta_destino = $img_dir . $nombre_archivo;
                        
                        if (move_uploaded_file($file_tmp, $ruta_destino)) {
                            $nuevas_imagenes[] = $ruta_destino;
                        } else {
                            $mensaje = "Error al guardar la imagen $file_name";
                            break;
                        }
                    }
                }
            }
        }

        // Actualizar producto si no hay errores
        if ($mensaje === '') {
            // Iniciar transacción
            pg_query($conexion, "BEGIN");
            
            // Eliminar imágenes seleccionadas
            $archivos_eliminados = [];
            if (!empty($imagenes_a_eliminar)) {
                error_log("Iniciando eliminación de " . count($imagenes_a_eliminar) . " imágenes");
                foreach ($imagenes_a_eliminar as $id_imagen) {
                    error_log("Eliminando imagen con ID: " . $id_imagen);
                    // Obtener ruta de la imagen antes de eliminarla
                    $query_ruta = "SELECT ruta_imagen FROM ImagenProducto WHERE id_imagen = $1 AND id_producto = $2";
                    $result_ruta = pg_query_params($conexion, $query_ruta, [$id_imagen, $id_producto]);
                    if ($result_ruta && $fila_ruta = pg_fetch_assoc($result_ruta)) {
                        $archivos_eliminados[] = $fila_ruta['ruta_imagen'];
                        error_log("Archivo a eliminar: " . $fila_ruta['ruta_imagen']);
                    }
                    
                    // Eliminar de la base de datos
                    $query_eliminar = "DELETE FROM ImagenProducto WHERE id_imagen = $1 AND id_producto = $2";
                    $result_eliminar = pg_query_params($conexion, $query_eliminar, [$id_imagen, $id_producto]);
                    if (!$result_eliminar) {
                        $mensaje = 'Error al eliminar imágenes.';
                        pg_query($conexion, "ROLLBACK");
                        error_log("Error al eliminar imagen ID: " . $id_imagen);
                        break;
                    } else {
                        error_log("Imagen eliminada correctamente de BD: " . $id_imagen);
                    }
                }
            }
            
            if ($mensaje === '') {
                $query_actualizar = "UPDATE Producto 
                                   SET titulo = $1, precio = $2, descripcion = $3, 
                                       stock = $4, categoria = $5, imagen = $6
                                   WHERE id_producto = $7";
                $result_actualizar = pg_query_params($conexion, $query_actualizar, [
                    $titulo, $precio, $descripcion, $stock, $categoria, $imagen_url, $id_producto
                ]);

                if ($result_actualizar) {
                    // Insertar nuevas imágenes si las hay
                    $error_imagenes = false;
                    if (!empty($nuevas_imagenes)) {
                        // Obtener el siguiente orden de imagen
                        $query_max_orden = "SELECT COALESCE(MAX(orden_imagen), 0) + 1 as siguiente_orden FROM ImagenProducto WHERE id_producto = $1";
                        $result_max_orden = pg_query_params($conexion, $query_max_orden, [$id_producto]);
                        $siguiente_orden = pg_fetch_assoc($result_max_orden)['siguiente_orden'];
                        
                        foreach ($nuevas_imagenes as $index => $ruta_imagen) {
                            $es_principal = empty($imagenes_existentes) && $index === 0;
                            $query_imagen = "INSERT INTO ImagenProducto (id_producto, ruta_imagen, es_principal, orden_imagen) 
                                           VALUES ($1, $2, $3, $4)";
                            $result_imagen = pg_query_params($conexion, $query_imagen, [
                                $id_producto, $ruta_imagen, $es_principal ? 't' : 'f', $siguiente_orden + $index
                            ]);
                            
                            if (!$result_imagen) {
                                $error_imagenes = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$error_imagenes) {
                        pg_query($conexion, "COMMIT");
                        
                        // Eliminar archivos físicos después del commit exitoso
                        foreach ($archivos_eliminados as $archivo) {
                            if (file_exists($archivo)) {
                                unlink($archivo);
                            }
                        }
                        
                        $exito = true;
                        $mensaje = '¡Producto actualizado exitosamente!';
                        
                        // Actualizar datos del producto para mostrar en el formulario
                        $producto['titulo'] = $titulo;
                        $producto['precio'] = $precio;
                        $producto['descripcion'] = $descripcion;
                        $producto['stock'] = $stock;
                        $producto['categoria'] = $categoria;
                        $producto['imagen'] = $imagen_url;
                        
                        // Recargar imágenes existentes
                        $result_imagenes = pg_query_params($conexion, $query_imagenes, [$id_producto]);
                        $imagenes_existentes = [];
                        if ($result_imagenes) {
                            while ($row = pg_fetch_assoc($result_imagenes)) {
                                $imagenes_existentes[] = $row;
                            }
                        }
                        
                        header("refresh:2;url=cuenta.php");
                    } else {
                        pg_query($conexion, "ROLLBACK");
                        // Eliminar archivos subidos si hay error en BD
                        foreach ($nuevas_imagenes as $archivo) {
                            if (file_exists($archivo)) {
                                unlink($archivo);
                            }
                        }
                        $mensaje = 'Error al guardar las nuevas imágenes.';
                    }
                } else {
                    pg_query($conexion, "ROLLBACK");
                    // Eliminar archivos subidos si hay error
                    foreach ($nuevas_imagenes as $archivo) {
                        if (file_exists($archivo)) {
                            unlink($archivo);
                        }
                    }
                    $mensaje = 'Error al actualizar el producto.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title>Editar Producto - La Nueva Venta</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-layoutInicioSesion">
        <div class="columna-central">
            <form id="formEditarProducto" enctype="multipart/form-data" class="login-form" method="POST" action="">
                <button type="button" class="btn-volver" onclick="window.location='cuenta.php'">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="form-logo" id="formLogo">
                    <img src="img/lnvBlanco.png" alt="" id="logoPrin">
                </div>
                
                <h3 style="color: var(--cream); text-align: center; margin-bottom: 1rem;">
                    <i class="bi bi-pencil me-2"></i>Editar Producto
                </h3>
                
                <?php if ($mensaje): ?>
                    <div class="alert <?php echo $exito ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Mostrar imágenes actuales -->
                <?php if (!empty($imagenes_existentes)): ?>
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <p style="color: var(--cream); font-size: 0.9rem; margin-bottom: 0.5rem;">Imágenes actuales:</p>
                        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem;">
                            <?php foreach ($imagenes_existentes as $imagen): ?>
                                <div style="position: relative; display: inline-block;">
                                    <img src="<?php echo htmlspecialchars($imagen['ruta_imagen']); ?>" 
                                         alt="Imagen del producto" 
                                         style="max-width: 80px; max-height: 80px; border-radius: 8px; border: 2px solid var(--cream);"
                                         onerror="this.src='img/placeholder.png'">
                                    <label style="position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; 
                                                  border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; 
                                                  justify-content: center; font-size: 12px; cursor: pointer; font-weight: bold;
                                                  border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"
                                           onclick="toggleImageDelete(this, <?php echo $imagen['id_imagen']; ?>)">
                                        <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id_imagen']; ?>" 
                                               style="display: none;" id="checkbox_<?php echo $imagen['id_imagen']; ?>">
                                        ×
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: var(--cream); opacity: 0.8; font-size: 0.75rem; display: block; margin-top: 0.5rem;">
                            Haz clic en la X roja para marcar imágenes para eliminar
                        </small>
                    </div>
                <?php elseif ($producto['imagen']): ?>
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <p style="color: var(--cream); font-size: 0.9rem; margin-bottom: 0.5rem;">Imagen actual:</p>
                        <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" 
                             alt="Imagen del producto" 
                             style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 2px solid var(--cream);"
                             onerror="this.src='img/placeholder.png'">
                    </div>
                <?php endif; ?>
                
                <input type="text" class="input-form" name="titulo" 
                       placeholder="Nombre del producto" 
                       value="<?php echo htmlspecialchars($producto['titulo']); ?>" required>
                
                <input type="number" class="input-form" name="precio" 
                       placeholder="Precio" min="0" step="0.01" 
                       value="<?php echo htmlspecialchars($producto['precio']); ?>" required>
                
                <input type="number" class="input-form" name="stock" 
                       placeholder="Stock" min="0" step="1" 
                       value="<?php echo htmlspecialchars($producto['stock']); ?>" required>
                
                <input type="text" class="input-form" name="categoria" 
                       placeholder="Categoría" 
                       value="<?php echo htmlspecialchars($producto['categoria']); ?>" required>
                
                <textarea class="input-form" name="descripcion" id="descripcionProducto" 
                          placeholder="Descripción" required><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                
                <div style="margin-bottom: 1rem;">
                    <label style="color: var(--cream); font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">
                        Agregar nuevas imágenes (opcional):
                    </label>
                    <input type="file" class="input-form" name="imagenes[]" accept="image/*" multiple>
                    <small style="color: var(--cream); opacity: 0.8; font-size: 0.8rem; display: block; margin-top: 0.3rem;">
                        Puedes seleccionar hasta 5 imágenes adicionales (JPG, PNG, GIF, WebP - máx. 5MB cada una)
                    </small>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn-iniciar" style="flex: 1;">
                        <i class="bi bi-check-circle me-2"></i>Actualizar producto
                    </button>
                    <a href="producto.php?id=<?php echo $id_producto; ?>" 
                       class="btn-iniciar" 
                       style="flex: 1; text-decoration: none; text-align: center; 
                              background-color: var(--violeta-claro); color: white;">
                        <i class="bi bi-eye me-2"></i>Ver producto
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script>
        function toggleImageDelete(label, imageId) {
            const checkbox = document.getElementById('checkbox_' + imageId);
            const container = label.parentElement;
            const img = container.querySelector('img');
            
            // Toggle del checkbox
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                // Marcar para eliminar
                img.style.opacity = '0.4';
                img.style.filter = 'grayscale(100%)';
                label.style.background = '#28a745'; // Verde cuando está marcado
                label.innerHTML = '✓';
                container.style.transform = 'scale(0.95)';
                console.log('Marcando imagen para eliminar:', imageId);
            } else {
                // Desmarcar
                img.style.opacity = '1';
                img.style.filter = 'none';
                label.style.background = '#dc3545'; // Rojo normal
                label.innerHTML = '×';
                container.style.transform = 'scale(1)';
                console.log('Desmarcando imagen:', imageId);
            }
        }
        
        // Confirmación antes de enviar el formulario si hay imágenes marcadas para eliminar
        document.getElementById('formEditarProducto').addEventListener('submit', function(e) {
            const imagenesAEliminar = document.querySelectorAll('input[name="eliminar_imagenes[]"]:checked');
            console.log('Imágenes marcadas para eliminar:', imagenesAEliminar.length);
            
            if (imagenesAEliminar.length > 0) {
                const confirmar = confirm(`¿Estás seguro de que quieres eliminar ${imagenesAEliminar.length} imagen(es)? Esta acción no se puede deshacer.`);
                if (!confirmar) {
                    e.preventDefault();
                    return false;
                }
                console.log('Usuario confirmó eliminación de imágenes');
            }
        });
    </script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>