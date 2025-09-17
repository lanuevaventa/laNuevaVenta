<?php
session_start();
require_once 'conexion.php';
require_once 'verificar_admin.php';

// Verificar que sea administrador
verificarAdmin();

$mensaje = '';
$tipo_mensaje = 'info';
$vista_actual = $_GET['vista'] ?? 'productos';

// Verificar acceso de administrador (puedes ajustar esta lógica según tu sistema)
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = 'info';
$vista_actual = $_GET['vista'] ?? 'productos';

// --- ELIMINAR USUARIO ---
if (isset($_GET['eliminar_usuario'])) {
    $id = intval($_GET['eliminar_usuario']);
    
    // Eliminar en cascada: primero los productos del usuario
    $query_productos_usuario = "SELECT id_producto FROM Vende WHERE id_usuario = $1";
    $result_productos = pg_query_params($conexion, $query_productos_usuario, [$id]);
    
    while ($producto = pg_fetch_assoc($result_productos)) {
        pg_query_params($conexion, "DELETE FROM enCarrito WHERE id_producto = $1", [$producto['id_producto']]);
        pg_query_params($conexion, "DELETE FROM Vende WHERE id_producto = $1", [$producto['id_producto']]);
        pg_query_params($conexion, "DELETE FROM Producto WHERE id_producto = $1", [$producto['id_producto']]);
    }
    
    // Eliminar carritos del usuario
    pg_query_params($conexion, "DELETE FROM enCarrito WHERE id_usuario = $1", [$id]);
    
    // Finalmente eliminar el usuario
    $result = pg_query_params($conexion, "DELETE FROM Usuario WHERE id_usuario = $1", [$id]);
    
    if ($result) {
        $mensaje = 'Usuario eliminado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al eliminar usuario';
        $tipo_mensaje = 'danger';
    }
}

// --- ELIMINAR PRODUCTO ---
if (isset($_GET['eliminar_producto'])) {
    $id = intval($_GET['eliminar_producto']);
    
    // Eliminar en cascada
    pg_query_params($conexion, "DELETE FROM enCarrito WHERE id_producto = $1", [$id]);
    pg_query_params($conexion, "DELETE FROM Vende WHERE id_producto = $1", [$id]);
    $result = pg_query_params($conexion, "DELETE FROM Producto WHERE id_producto = $1", [$id]);
    
    if ($result) {
        $mensaje = 'Producto eliminado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al eliminar producto';
        $tipo_mensaje = 'danger';
    }
}

// --- OBTENER DATOS ---
// Obtener usuarios
$query_usuarios = "SELECT u.*, COUNT(v.id_producto) as productos_subidos 
                   FROM Usuario u 
                   LEFT JOIN Vende v ON u.id_usuario = v.id_usuario 
                   GROUP BY u.id_usuario 
                   ORDER BY u.fecha_registro DESC";
$result_usuarios = pg_query($conexion, $query_usuarios);
$usuarios = [];
if ($result_usuarios) {
    while ($row = pg_fetch_assoc($result_usuarios)) {
        $usuarios[] = $row;
    }
}

// Obtener productos
$query_productos = "SELECT p.*, u.nombre, u.apellido 
                    FROM Producto p 
                    LEFT JOIN Vende v ON p.id_producto = v.id_producto 
                    LEFT JOIN Usuario u ON v.id_usuario = u.id_usuario 
                    ORDER BY p.fecha_publicacion DESC";
$result_productos = pg_query($conexion, $query_productos);
$productos = [];
if ($result_productos) {
    while ($row = pg_fetch_assoc($result_productos)) {
        $productos[] = $row;
    }
}

// Estadísticas
$total_usuarios = count($usuarios);
$total_productos = count($productos);
$productos_sin_vendedor = 0;
foreach ($productos as $prod) {
    if (!$prod['nombre']) $productos_sin_vendedor++;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title>Panel de Administración - La Nueva Venta</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .admin-main {
            min-height: calc(100vh - 80px);
        }
        .admin-sidebar {
            background: linear-gradient(135deg, var(--violeta-fuerte), var(--violeta-claro));
            min-height: calc(100vh - 120px);
            padding: 2rem 1rem;
        }
        .btn-dashboard {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-dashboard:hover, .btn-dashboard.active {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateX(5px);
        }
        .admin-content {
            padding: 2rem;
            background: #f8f9fa;
        }
        .stats-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 2px solid var(--violeta-claro);
            margin-bottom: 1.5rem;
        }
        .stats-card .card-body {
            padding: 2rem;
            text-align: center;
        }
        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--violeta-fuerte);
            margin-bottom: 0.5rem;
        }
        .stats-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .admin-table {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 2px solid var(--violeta-claro);
        }
        .admin-table .table {
            margin: 0;
        }
        .admin-table .table thead {
            background: var(--violeta-fuerte);
            color: #fff;
        }
        .admin-table .table thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
        }
        .admin-table .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #eee;
        }
        .btn-admin-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            margin: 0 0.2rem;
        }
        .producto-img-admin {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .admin-main {
                margin-top: 100px;
            }
            .admin-sidebar {
                min-height: auto;
                padding: 1rem;
            }
            .admin-content {
                padding: 1rem;
            }
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
    <main class="admin-main">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 admin-sidebar">
                    <h4 class="text-white mb-4"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</h4>
                    
                    <a href="admin.php?vista=dashboard" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-graph-up me-2"></i> Dashboard
                    </a>
                    
                    <a href="admin.php?vista=productos" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'productos' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam me-2"></i> Productos
                    </a>
                    
                    <a href="admin.php?vista=usuarios" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'usuarios' ? 'active' : ''; ?>">
                        <i class="bi bi-people me-2"></i> Usuarios
                    </a>
                    
                    <hr class="my-4" style="border-color: rgba(255,255,255,0.3);">
                    
                    <a href="index.php" class="btn btn-dashboard w-100">
                        <i class="bi bi-house me-2"></i> Volver al sitio
                    </a>
                </div>

                <!-- Contenido -->
                <div class="col-md-9 col-lg-10 admin-content">
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($vista_actual === 'dashboard'): ?>
                        <!-- DASHBOARD -->
                        <h2 class="mb-4"><i class="bi bi-graph-up me-2"></i>Dashboard General</h2>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="stats-card card">
                                    <div class="card-body">
                                        <div class="stats-number"><?php echo $total_usuarios; ?></div>
                                        <div class="stats-label">Usuarios Registrados</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card card">
                                    <div class="card-body">
                                        <div class="stats-number"><?php echo $total_productos; ?></div>
                                        <div class="stats-label">Productos Publicados</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stats-card card">
                                    <div class="card-body">
                                        <div class="stats-number"><?php echo $productos_sin_vendedor; ?></div>
                                        <div class="stats-label">Productos Sin Vendedor</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($vista_actual === 'productos'): ?>
                        <!-- GESTIÓN DE PRODUCTOS -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-box-seam me-2"></i>Gestión de Productos (<?php echo count($productos); ?>)</h2>
                            <a href="subirProducto.php" class="btn btn-success">
                                <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
                            </a>
                        </div>

                        <div class="admin-table">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imagen</th>
                                        <th>Producto</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Vendedor</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($productos)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No hay productos registrados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($productos as $prod): ?>
                                            <tr>
                                                <td><?php echo $prod['id_producto']; ?></td>
                                                <td>
                                                    <img src="<?php echo htmlspecialchars($prod['imagen']); ?>" 
                                                         class="producto-img-admin" 
                                                         alt="Producto">
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($prod['titulo']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($prod['categoria']); ?></small>
                                                </td>
                                                <td>$<?php echo number_format($prod['precio'], 2); ?></td>
                                                <td><?php echo $prod['stock']; ?></td>
                                                <td>
                                                    <?php if ($prod['nombre']): ?>
                                                        <?php echo htmlspecialchars($prod['nombre'] . ' ' . $prod['apellido']); ?>
                                                    <?php else: ?>
                                                        <span class="text-danger">Sin vendedor</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($prod['fecha_publicacion'])); ?></td>
                                                <td>
                                                    <a href="producto.php?id=<?php echo $prod['id_producto']; ?>" 
                                                       class="btn btn-outline-primary btn-admin-action">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="admin.php?vista=productos&eliminar_producto=<?php echo $prod['id_producto']; ?>" 
                                                       class="btn btn-danger btn-admin-action"
                                                       onclick="return confirm('¿Seguro que quieres eliminar este producto?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($vista_actual === 'usuarios'): ?>
                        <!-- GESTIÓN DE USUARIOS -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="bi bi-people me-2"></i>Gestión de Usuarios (<?php echo count($usuarios); ?>)</h2>
                        </div>

                        <div class="admin-table">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>Productos</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuarios)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                No hay usuarios registrados
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usuarios as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id_usuario']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['correo']); ?></td>
                                                <td><?php echo htmlspecialchars($user['telefono'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $user['productos_subidos']; ?></span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($user['fecha_registro'])); ?></td>
                                                <td>
                                                    <a href="admin.php?vista=usuarios&eliminar_usuario=<?php echo $user['id_usuario']; ?>" 
                                                       class="btn btn-danger btn-admin-action"
                                                       onclick="return confirm('¿Seguro que quieres eliminar este usuario? Se eliminarán también todos sus productos.')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>