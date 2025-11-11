<?php
// filepath: c:\Users\nicot\OneDrive\Desktop\Proyecto\laNuevaVenta\laNuevaVenta-beta-0.4.0\src\editarProducto.php
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

// Helpers de schema (para permitir UI siempre visible sin romper guardados)
function db_has_offer_columns($conexion) {
    $sql = "
      SELECT COUNT(*) AS c
      FROM information_schema.columns
      WHERE table_schema = 'public'
        AND table_name = 'producto'
        AND column_name IN ('oferta_activa','oferta_tipo','oferta_valor','oferta_desde','oferta_hasta')
    ";
    $r = pg_query($conexion, $sql);
    if (!$r) return false;
    $row = pg_fetch_assoc($r);
    return isset($row['c']) && (int)$row['c'] === 5;
}

function db_has_cupon_table($conexion) {
    $sql = "SELECT to_regclass('public.cupon') AS t";
    $r = pg_query($conexion, $sql);
    if (!$r) return false;
    $row = pg_fetch_assoc($r);
    return !empty($row['t']);
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

// Detectar soporte de schema
$schema_has_offers = db_has_offer_columns($conexion);
$schema_has_cupons = db_has_cupon_table($conexion);

// Obtener todas las imágenes del producto
$query_imagenes = "SELECT id_imagen, id_producto, ruta_imagen, es_principal, orden_imagen 
                   FROM ImagenProducto WHERE id_producto = $1 ORDER BY orden_imagen ASC";
$result_imagenes = pg_query_params($conexion, $query_imagenes, [$id_producto]);
$imagenes_existentes = [];
if ($result_imagenes) {
    while ($row = pg_fetch_assoc($result_imagenes)) {
        $imagenes_existentes[] = $row;
    }
}

// Cargar oferta actual (valores por defecto si no hay columnas)
$oferta_activa = false;
$oferta_tipo = '';
$oferta_valor = '';
$oferta_desde = '';
$oferta_hasta = '';

if ($schema_has_offers) {
    $oferta_activa = !empty($producto['oferta_activa']) && ($producto['oferta_activa'] === 't' || $producto['oferta_activa'] == 1);
    $oferta_tipo = $producto['oferta_tipo'] ?? '';
    $oferta_valor = isset($producto['oferta_valor']) ? floatval($producto['oferta_valor']) : '';
    $oferta_desde = $producto['oferta_desde'] ?? '';
    $oferta_hasta = $producto['oferta_hasta'] ?? '';
}

// Procesar acciones de oferta (independiente, antes de cupones)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_oferta'])) {
    if (!$schema_has_offers) {
        $mensaje = 'La gestión de ofertas no está disponible (columnas no instaladas).';
    } else {
        $accion = $_POST['accion_oferta'];
        if ($accion === 'quitar') {
            $q = "UPDATE Producto SET oferta_activa = $1, oferta_tipo = $2, oferta_valor = $3, oferta_desde = $4, oferta_hasta = $5
                  WHERE id_producto = $6";
            $r = pg_query_params($conexion, $q, [false, null, null, null, null, $id_producto]);
            if ($r) {
                $mensaje = 'Oferta quitada';
                $exito = true;
                $oferta_activa = false;
                $oferta_tipo = '';
                $oferta_valor = '';
                $oferta_desde = '';
                $oferta_hasta = '';
                $producto['oferta_activa'] = 'f';
                $producto['oferta_tipo'] = null;
                $producto['oferta_valor'] = null;
                $producto['oferta_desde'] = null;
                $producto['oferta_hasta'] = null;
            } else {
                $mensaje = 'No se pudo quitar la oferta';
            }
        } else { // guardar
            $tipo = $_POST['oferta_tipo'] ?? '';
            $valor = isset($_POST['oferta_valor']) && is_numeric($_POST['oferta_valor']) ? floatval($_POST['oferta_valor']) : null;
            $desde = !empty($_POST['oferta_desde']) ? str_replace('T', ' ', $_POST['oferta_desde']) : null;
            $hasta = !empty($_POST['oferta_hasta']) ? str_replace('T', ' ', $_POST['oferta_hasta']) : null;

            if (!in_array($tipo, ['porcentaje','fijo'], true)) $tipo = null;
            if (!is_null($valor) && $valor <= 0) $valor = null;
            if ($tipo === 'porcentaje' && !is_null($valor) && $valor > 100) $valor = 100;

            $activa = ($tipo !== null && $valor !== null);

            $q = "UPDATE Producto SET oferta_activa = $1, oferta_tipo = $2, oferta_valor = $3, oferta_desde = $4, oferta_hasta = $5
                  WHERE id_producto = $6";
            $r = pg_query_params($conexion, $q, [$activa, $tipo, $valor, $desde, $hasta, $id_producto]);

            if ($r) {
                $mensaje = $activa ? 'Oferta aplicada' : 'Oferta desactivada (faltan datos válidos)';
                $exito = true;
                $oferta_activa = $activa;
                $oferta_tipo = $tipo ?? '';
                $oferta_valor = $valor ?? '';
                $oferta_desde = $desde ?? '';
                $oferta_hasta = $hasta ?? '';
                $producto['oferta_activa'] = $activa ? 't' : 'f';
                $producto['oferta_tipo'] = $tipo;
                $producto['oferta_valor'] = $valor;
                $producto['oferta_desde'] = $desde;
                $producto['oferta_hasta'] = $hasta;
            } else {
                $mensaje = 'No se pudo guardar la oferta';
            }
        }
    }
}

// Procesar acciones de cupones (si existe la tabla)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cupon'])) {
    if ($schema_has_cupons) {
        $accion = $_POST['accion_cupon'];
        if ($accion === 'crear') {
            $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
            $tipo = $_POST['tipo'] ?? 'porcentaje'; // porcentaje|fijo
            $valor = is_numeric($_POST['valor'] ?? null) ? floatval($_POST['valor']) : 0;
            // Normalizar datetime-local (YYYY-MM-DDTHH:mm) -> 'YYYY-MM-DD HH:mm'
            $valido_desde = !empty($_POST['valido_desde']) ? str_replace('T', ' ', $_POST['valido_desde']) : null;
            $valido_hasta = !empty($_POST['valido_hasta']) ? str_replace('T', ' ', $_POST['valido_hasta']) : null;
            $activo = isset($_POST['activo']) ? 't' : 'f';

            // Validaciones detalladas
            $errores = [];
            if ($codigo === '') {
                $errores[] = 'código requerido';
            } elseif (!preg_match('/^[A-Z0-9_-]{3,32}$/', $codigo)) {
                $errores[] = 'código inválido (usa A-Z, 0-9, _ o -, 3-32 chars)';
            }
            if (!in_array($tipo, ['porcentaje','fijo'], true)) {
                $errores[] = 'tipo inválido';
            }
            if ($valor <= 0) {
                $errores[] = 'valor debe ser mayor a 0';
            }
            if ($tipo === 'porcentaje' && $valor > 100) {
                $errores[] = 'porcentaje no puede superar 100';
            }

            if (!empty($errores)) {
                $mensaje = 'Datos de cupón inválidos: ' . implode('; ', $errores);
            } else {
                $q = "INSERT INTO Cupon (id_producto, codigo, tipo, valor, valido_desde, valido_hasta, activo)
                      VALUES ($1, $2, $3, $4, $5, $6, $7)";
                $r = pg_query_params($conexion, $q, [$id_producto, $codigo, $tipo, $valor, $valido_desde, $valido_hasta, $activo]);
                if ($r) {
                    $mensaje = 'Cupón creado';
                    $exito = true;
                } else {
                    $mensaje = 'Error al crear cupón (código duplicado o esquema faltante)';
                }
            }
        } elseif ($accion === 'toggle') {
            $id_cupon = intval($_POST['id_cupon'] ?? 0);
            $nuevo = ($_POST['nuevo_estado'] ?? 't') === 't' ? 't' : 'f';
            $q = "UPDATE Cupon SET activo = $1 WHERE id_cupon = $2 AND id_producto = $3";
            $r = pg_query_params($conexion, $q, [$nuevo, $id_cupon, $id_producto]);
            $mensaje = $r ? 'Estado de cupón actualizado' : 'No se pudo actualizar el estado';
            $exito = (bool)$r;
        } elseif ($accion === 'eliminar') {
            $id_cupon = intval($_POST['id_cupon'] ?? 0);
            $q = "DELETE FROM Cupon WHERE id_cupon = $1 AND id_producto = $2";
            $r = pg_query_params($conexion, $q, [$id_cupon, $id_producto]);
            $mensaje = $r ? 'Cupón eliminado' : 'No se pudo eliminar el cupón';
            $exito = (bool)$r;
        }
    } else {
        // UI siempre visible, pero backend avisa si no hay tabla
        $mensaje = 'La gestión de cupones no está disponible (tabla Cupon no instalada).';
    }
}

// Procesar formulario de edición de producto (SIN oferta)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['accion_cupon']) && !isset($_POST['accion_oferta'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $imagen_url = $producto['imagen'];

    // Procesar eliminación de imágenes
    $imagenes_a_eliminar = $_POST['eliminar_imagenes'] ?? [];

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
                foreach ($imagenes_a_eliminar as $id_imagen) {
                    // Obtener ruta de la imagen antes de eliminarla
                    $query_ruta = "SELECT ruta_imagen FROM ImagenProducto WHERE id_imagen = $1 AND id_producto = $2";
                    $result_ruta = pg_query_params($conexion, $query_ruta, [$id_imagen, $id_producto]);
                    if ($result_ruta && $fila_ruta = pg_fetch_assoc($result_ruta)) {
                        $archivos_eliminados[] = $fila_ruta['ruta_imagen'];
                    }
                    
                    // Eliminar de la base de datos
                    $query_eliminar = "DELETE FROM ImagenProducto WHERE id_imagen = $1 AND id_producto = $2";
                    $result_eliminar = pg_query_params($conexion, $query_eliminar, [$id_imagen, $id_producto]);
                    if (!$result_eliminar) {
                        $mensaje = 'Error al eliminar imágenes.';
                        pg_query($conexion, "ROLLBACK");
                        break;
                    }
                }
            }
            
            if ($mensaje === '') {
                // Construir UPDATE solo con campos del producto (sin oferta)
                $params = [$titulo, $precio, $descripcion, $stock, $categoria, $imagen_url];
                $set_clause = "titulo = $1, precio = $2, descripcion = $3, stock = $4, categoria = $5, imagen = $6";
                $where_idx = 7;

                $query_actualizar = "UPDATE Producto SET {$set_clause} WHERE id_producto = $" . $where_idx;
                $params[] = $id_producto;

                $result_actualizar = pg_query_params($conexion, $query_actualizar, $params);

                if ($result_actualizar) {
                    // Insertar nuevas imágenes si las hay
                    $error_imagenes = false;
                    if (!empty($nuevas_imagenes)) {
                        // Obtener el siguiente orden de imagen
                        $query_max_orden = "SELECT COALESCE(MAX(orden_imagen), 0) + 1 as siguiente_orden FROM ImagenProducto WHERE id_producto = $1";
                        $result_max_orden = pg_query_params($conexion, $query_max_orden, [$id_producto]);
                        $siguiente_orden = 1;
                        if ($result_max_orden) {
                            $siguiente_orden = (int)pg_fetch_assoc($result_max_orden)['siguiente_orden'];
                        }
                        
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
                    } else {
                        pg_query($conexion, "ROLLBACK");
                        foreach ($nuevas_imagenes as $archivo) {
                            if (file_exists($archivo)) {
                                unlink($archivo);
                            }
                        }
                        $mensaje = 'Error al guardar las nuevas imágenes.';
                    }
                } else {
                    pg_query($conexion, "ROLLBACK");
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

// Listado de cupones del producto (si existe la tabla)
$cupones = [];
if ($schema_has_cupons) {
    $q_cupons = "SELECT id_cupon, codigo, tipo, valor, valido_desde, valido_hasta, activo 
                 FROM Cupon WHERE id_producto = $1 ORDER BY id_cupon DESC";
    $r_cupons = pg_query_params($conexion, $q_cupons, [$id_producto]);
    if ($r_cupons) {
        while ($row = pg_fetch_assoc($r_cupons)) {
            $cupones[] = $row;
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
                    <div class="alert <?php echo $exito ? 'alert-success' : 'alert-info'; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
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
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
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
            </form> <!-- CERRAR AQUÍ el formulario de edición de producto -->

            <!-- Oferta del producto (formulario independiente y opcional) -->
            <?php $offerDisabled = $schema_has_offers ? '' : 'disabled'; ?>
            <div class="card mb-3" style="background: rgba(255,255,255,0.06); color: var(--cream);">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="bi bi-tag me-2"></i>Oferta del producto (opcional)</h5>
                        <?php if (!$schema_has_offers): ?>
                            <span class="badge bg-warning text-dark">Campos deshabilitados</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$schema_has_offers): ?>
                        <div class="alert alert-warning mt-2 mb-3 py-2">
                            Las columnas de oferta no están instaladas. Los campos se mostrarán pero no podrás aplicar ofertas.
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="oferta_activa" name="oferta_activa" <?php echo $oferta_activa ? 'checked' : ''; ?> <?php echo $offerDisabled; ?>>
                                <label class="form-check-label" for="oferta_activa">Activa</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo de descuento</label>
                            <select class="form-select" name="oferta_tipo" <?php echo $offerDisabled; ?>>
                                <option value="" <?php echo $oferta_tipo===''?'selected':''; ?>>Sin oferta</option>
                                <option value="porcentaje" <?php echo $oferta_tipo==='porcentaje'?'selected':''; ?>>Porcentaje (%)</option>
                                <option value="fijo" <?php echo $oferta_tipo==='fijo'?'selected':''; ?>>Monto fijo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Valor</label>
                            <input type="number" class="form-control" step="0.01" min="0.01" name="oferta_valor"
                                   value="<?php echo htmlspecialchars($oferta_valor); ?>" <?php echo $offerDisabled; ?>>
                            <small class="text-muted d-block">Si es porcentaje, máx. 100</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Desde</label>
                            <input type="datetime-local" class="form-control" name="oferta_desde"
                                   value="<?php echo $oferta_desde ? date('Y-m-d\TH:i', strtotime($oferta_desde)) : ''; ?>" <?php echo $offerDisabled; ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasta</label>
                            <input type="datetime-local" class="form-control" name="oferta_hasta"
                                   value="<?php echo $oferta_hasta ? date('Y-m-d\TH:i', strtotime($oferta_hasta)) : ''; ?>" <?php echo $offerDisabled; ?>>
                        </div>
                        <div class="col-12 d-flex align-items-center gap-2 mt-2">
                            <button type="submit" class="btn btn-sm btn-primary" name="accion_oferta" value="guardar" <?php echo $offerDisabled; ?>>
                                <i class="bi bi-check2-circle me-1"></i>Aplicar oferta
                            </button>
                            <button type="submit" class="btn btn-sm btn-outline-danger" name="accion_oferta" value="quitar" <?php echo $offerDisabled; ?>>
                                <i class="bi bi-x-circle me-1"></i>Quitar oferta
                            </button>
                            <small class="text-muted ms-2">Opcional. Si tipo y valor son válidos, la oferta se activa automáticamente.</small>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Gestión de cupones -->
            <?php $cuponDisabled = $schema_has_cupons ? '' : 'disabled'; ?>
            <div class="card" style="background: rgba(255,255,255,0.06); color: var(--cream);">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0"><i class="bi bi-ticket-perforated me-2"></i>Cupones del producto (opcional)</h5>
                        <?php if (!$schema_has_cupons): ?>
                            <span class="badge bg-warning text-dark">Campos deshabilitados</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$schema_has_cupons): ?>
                        <div class="alert alert-warning mt-2 mb-3 py-2">
                            La tabla de cupones no está instalada. Puedes ver los campos, pero no podrás crear/editar cupones hasta habilitarla.
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="accion_cupon" value="crear">
                        <div class="col-md-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control"
                                   placeholder="EJ: OFERTA10"
                                   pattern="[A-Za-z0-9_-]{3,32}"
                                   title="Usa letras, números, _ o -, 3 a 32 caracteres"
                                   required <?php echo $cuponDisabled; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" <?php echo $cuponDisabled; ?>>
                                <option value="porcentaje">Porcentaje (%)</option>
                                <option value="fijo">Monto fijo</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Valor</label>
                            <input type="number" name="valor" step="0.01" min="0.01" class="form-control"
                                   required <?php echo $cuponDisabled; ?>>
                            <small class="text-muted">Si es porcentaje, máx. 100</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Desde</label>
                            <input type="datetime-local" name="valido_desde" class="form-control" <?php echo $cuponDisabled; ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasta</label>
                            <input type="datetime-local" name="valido_hasta" class="form-control" <?php echo $cuponDisabled; ?>>
                        </div>
                        <div class="col-12 d-flex align-items-center gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cupon_activo" name="activo" <?php echo $schema_has_cupons ? 'checked' : 'disabled'; ?>>
                                <label class="form-check-label" for="cupon_activo">Activo</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" <?php echo $cuponDisabled; ?>>
                                <i class="bi bi-plus-circle me-1"></i>Crear cupón
                            </button>
                        </div>
                    </form>

                    <?php if (!$schema_has_cupons): ?>
                        <p class="text-muted mb-0">No hay cupones (tabla no instalada).</p>
                    <?php else: ?>
                        <?php if (empty($cupones)): ?>
                            <p class="text-muted">No hay cupones creados para este producto.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-dark align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Tipo</th>
                                            <th>Valor</th>
                                            <th>Vigencia</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cupones as $c): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($c['codigo']); ?></span></td>
                                                <td><?php echo htmlspecialchars($c['tipo']); ?></td>
                                                <td>
                                                    <?php 
                                                    $v = number_format(floatval($c['valor']), 2);
                                                    echo $c['tipo']==='porcentaje' ? "{$v}%" : "$ {$v}";
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $vd = $c['valido_desde'] ? date('d/m/Y H:i', strtotime($c['valido_desde'])) : '—';
                                                    $vh = $c['valido_hasta'] ? date('d/m/Y H:i', strtotime($c['valido_hasta'])) : '—';
                                                    echo "{$vd} a {$vh}";
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($c['activo'] === 't'): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="accion_cupon" value="toggle">
                                                        <input type="hidden" name="id_cupon" value="<?php echo (int)$c['id_cupon']; ?>">
                                                        <input type="hidden" name="nuevo_estado" value="<?php echo $c['activo']==='t' ? 'f' : 't'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $c['activo']==='t' ? 'btn-warning' : 'btn-success'; ?>">
                                                            <?php echo $c['activo']==='t' ? 'Desactivar' : 'Activar'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar cupón?');">
                                                        <input type="hidden" name="accion_cupon" value="eliminar">
                                                        <input type="hidden" name="id_cupon" value="<?php echo (int)$c['id_cupon']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- ...existing code... -->
        </div>
    </main>

    <script>
        function toggleImageDelete(label, imageId) {
            const checkbox = document.getElementById('checkbox_' + imageId);
            const container = label.parentElement;
            const img = container.querySelector('img');
            checkbox.checked = !checkbox.checked;
            if (checkbox.checked) {
                img.style.opacity = '0.4';
                img.style.filter = 'grayscale(100%)';
                label.style.background = '#28a745';
                label.innerHTML = '✓';
                container.style.transform = 'scale(0.95)';
            } else {
                img.style.opacity = '1';
                img.style.filter = 'none';
                label.style.background = '#dc3545';
                label.innerHTML = '×';
                container.style.transform = 'scale(1)';
            }
        }
        
        document.getElementById('formEditarProducto').addEventListener('submit', function(e) {
            const imagenesAEliminar = document.querySelectorAll('input[name="eliminar_imagenes[]"]:checked');
            if (imagenesAEliminar.length > 0) {
                const confirmar = confirm(`¿Estás seguro de que quieres eliminar ${imagenesAEliminar.length} imagen(es)? Esta acción no se puede deshacer.`);
                if (!confirmar) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>