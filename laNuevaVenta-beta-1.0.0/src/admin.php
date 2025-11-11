<?php
session_start();
require_once 'conexion.php';
require_once 'verificar_admin.php';
verificarAdmin();

$mensaje = '';
$tipo_mensaje = 'info';
$vista_actual = $_GET['vista'] ?? 'productos';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = 'info';
$vista_actual = $_GET['vista'] ?? 'productos';

if (isset($_GET['eliminar_usuario'])) {
    $id = intval($_GET['eliminar_usuario']);
    $query_productos_usuario = "SELECT id_producto FROM Vende WHERE id_usuario = $1";
    $result_productos = pg_query_params($conexion, $query_productos_usuario, [$id]);
    while ($producto = pg_fetch_assoc($result_productos)) {
        pg_query_params($conexion, "DELETE FROM enCarrito WHERE id_producto = $1", [$producto['id_producto']]);
        pg_query_params($conexion, "DELETE FROM Vende WHERE id_producto = $1", [$producto['id_producto']]);
        pg_query_params($conexion, "DELETE FROM Producto WHERE id_producto = $1", [$producto['id_producto']]);
    }
    pg_query_params($conexion, "DELETE FROM enCarrito WHERE id_usuario = $1", [$id]);
    $result = pg_query_params($conexion, "DELETE FROM Usuario WHERE id_usuario = $1", [$id]);
    if ($result) {
        $mensaje = 'Usuario eliminado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al eliminar usuario';
        $tipo_mensaje = 'danger';
    }
}

if (isset($_GET['eliminar_producto'])) {
    $id = intval($_GET['eliminar_producto']);
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

$total_usuarios = count($usuarios);
$total_productos = count($productos);
$productos_sin_vendedor = 0;
foreach ($productos as $prod) {
    if (!$prod['nombre']) $productos_sin_vendedor++;
}

$mas_vendidos = [];
$qr_mas_vendidos = @pg_query($conexion, "
  SELECT p.id_producto, p.titulo, COALESCE(SUM(pi.cantidad),0) vendidos
  FROM Producto p
  LEFT JOIN PedidoItem pi ON pi.id_producto = p.id_producto
  GROUP BY p.id_producto
  ORDER BY vendidos DESC
  LIMIT 5
");
if ($qr_mas_vendidos) {
  while ($r = pg_fetch_assoc($qr_mas_vendidos)) $mas_vendidos[] = $r;
}

$ingresos_semana = [];
$qr_semana = @pg_query($conexion, "
  SELECT date_trunc('week', fecha_creado) AS periodo, SUM(total) AS ingresos
  FROM Pedido
  GROUP BY 1
  ORDER BY periodo DESC
  LIMIT 8
");
if ($qr_semana) while ($r = pg_fetch_assoc($qr_semana)) $ingresos_semana[] = $r;

$ingresos_mes = [];
$qr_mes = @pg_query($conexion, "
  SELECT date_trunc('month', fecha_creado) AS periodo, SUM(total) AS ingresos
  FROM Pedido
  GROUP BY 1
  ORDER BY periodo DESC
  LIMIT 6
");
if ($qr_mes) while ($r = pg_fetch_assoc($qr_mes)) $ingresos_mes[] = $r;

$usuarios_activos = [];
$qr_activos = @pg_query($conexion, "
  SELECT u.id_usuario, u.nombre, u.apellido,
         COUNT(DISTINCT pe.id_pedido) AS pedidos,
         COUNT(DISTINCT op.id_opinion) AS opiniones,
         (COUNT(DISTINCT pe.id_pedido)+COUNT(DISTINCT op.id_opinion)) AS actividad
  FROM Usuario u
  LEFT JOIN Pedido pe ON pe.id_usuario = u.id_usuario
  LEFT JOIN Opinion op ON op.id_usuario = u.id_usuario
  GROUP BY u.id_usuario
  ORDER BY actividad DESC
  LIMIT 5
");
if ($qr_activos) while ($r = pg_fetch_assoc($qr_activos)) $usuarios_activos[] = $r;

$opiniones_promedio = [];
$qr_op_prom = @pg_query($conexion, "
  SELECT p.id_producto, p.titulo,
         ROUND(AVG(op.calificacion)::numeric,2) AS promedio,
         COUNT(op.id_opinion) AS total
  FROM Producto p
  JOIN Opinion op ON op.id_producto = p.id_producto
  GROUP BY p.id_producto
  HAVING COUNT(op.id_opinion) >= 1
  ORDER BY promedio DESC, total DESC
  LIMIT 5
");
if ($qr_op_prom) while ($r = pg_fetch_assoc($qr_op_prom)) $opiniones_promedio[] = $r;

$actividad = [];
$qr_act = @pg_query($conexion, "
  SELECT id_actividad, tipo, descripcion, fecha
  FROM ActividadAdmin
  ORDER BY fecha DESC
  LIMIT 25
");
if ($qr_act) while ($r = pg_fetch_assoc($qr_act)) $actividad[] = $r;

$envios = [];
$qr_envios = @pg_query($conexion, "
  SELECT e.id_envio, e.id_pedido, e.estado, e.fecha_creado
  FROM Envio e
  ORDER BY e.fecha_creado DESC
  LIMIT 50
");
if ($qr_envios) while ($r = pg_fetch_assoc($qr_envios)) $envios[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_envio'])) {
  $id_envio = intval($_POST['id_envio'] ?? 0);
  $nuevo_estado = preg_replace('/[^a-z_]/','', strtolower($_POST['estado'] ?? ''));
  if ($id_envio && $nuevo_estado) {
    $ok = pg_query_params($conexion,
      "UPDATE Envio SET estado = $2 WHERE id_envio = $1",
      [$id_envio, $nuevo_estado]
    );
    if ($ok) {
      @pg_query_params($conexion,
        "INSERT INTO ActividadAdmin(tipo, descripcion) VALUES ($1,$2)",
        ['envio', "Estado de envío #$id_envio cambiado a $nuevo_estado"]
      );
      $mensaje = 'Estado de envío actualizado';
      $tipo_mensaje = 'success';
      $vista_actual = 'envios';
    } else {
      $mensaje = 'Error al actualizar envío';
      $tipo_mensaje = 'danger';
      $vista_actual = 'envios';
    }
    $envios = [];
    $qr_envios = @pg_query($conexion, "
      SELECT e.id_envio, e.id_pedido, e.estado, e.fecha_creado
      FROM Envio e
      ORDER BY e.fecha_creado DESC
      LIMIT 50
    ");
    if ($qr_envios) while ($r = pg_fetch_assoc($qr_envios)) $envios[] = $r;
  }
}

function img_src_or_placeholder(?string $rel): string {
  $rel = trim((string)$rel);
  $abs = $rel ? __DIR__ . '/' . $rel : '';
  if ($rel && is_file($abs)) {
    return htmlspecialchars($rel, ENT_QUOTES, 'UTF-8');
  }
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">'
       . '<rect width="100%" height="100%" fill="#f0f0f0"/>'
       . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" '
       . 'fill="#888" font-family="sans-serif" font-size="12">sin imagen</text>'
       . '</svg>';
  return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
    <link rel="stylesheet" href="css/styles_admin.css?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
  <nav class="navbar fixed-top shadow-sm border-bottom">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 flex-wrap">
      <a class="navbar-brand p-0 me-3 flex-shrink-0" href="index.php">
        <img src="img/lnvBlanco.png" alt="logo" style="height: 45px;">
      </a>
      <form class="flex-grow-1 position-relative mx-3" role="search" style="max-width: 600px; min-width: 120px;" method="GET" action="index.php">
        <input class="form-control rounded-pill ps-3 pe-5 border-violeta" type="search" name="buscar" placeholder="Buscar productos..." aria-label="Buscar" style="min-width: 0;">
        <button type="submit" class="btn position-absolute end-0 text-violeta lupa-btn" style="border: none;">
          <i class="bi bi-search"></i>
        </button>
      </form>
      <div class="d-flex align-items-center gap-3 flex-shrink-0">
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
        <a href="carrito.php" class="position-relative text-cream">
          <i class="bi bi-cart fs-5"></i>
        </a>
      </div>
    </div>
  </nav>
    <main class="admin-main">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3 col-lg-2 admin-sidebar">
                    <h4 class="text-white mb-4"><i class="bi bi-speedometer2 me-2"></i>Admin Panel</h4>
                    <a href="admin.php?vista=dashboard" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'dashboard' ? 'active' : ''; ?>">
                      <i class="bi bi-graph-up me-2"></i> Dashboard
                    </a>
                    <a href="admin.php?vista=actividad" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'actividad' ? 'active' : ''; ?>">
                      <i class="bi bi-clock-history me-2"></i> Actividad
                    </a>
                    <a href="admin.php?vista=envios" class="btn btn-dashboard w-100 <?php echo $vista_actual === 'envios' ? 'active' : ''; ?>">
                      <i class="bi bi-truck me-2"></i> Envíos
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
                <div class="col-md-9 col-lg-10 admin-content">
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($mensaje); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($vista_actual === 'dashboard'): ?>
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
                        <hr class="my-4">
                        <h4 class="mb-3"><i class="bi bi-bar-chart-line me-2"></i>Métricas Avanzadas</h4>
                        <div class="row">
                          <div class="col-md-6 mb-4">
                            <div class="card h-100">
                              <div class="card-header">Productos más vendidos</div>
                              <div class="card-body p-0">
                                <table class="table mb-0">
                                  <thead><tr><th>Producto</th><th>Vendidos</th></tr></thead>
                                  <tbody>
                                    <?php if (!$mas_vendidos): ?>
                                      <tr><td colspan="2" class="text-muted">Sin datos</td></tr>
                                    <?php else: foreach ($mas_vendidos as $mv): ?>
                                      <tr>
                                        <td><?php echo htmlspecialchars($mv['titulo']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $mv['vendidos']; ?></span></td>
                                      </tr>
                                    <?php endforeach; endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6 mb-4">
                            <div class="card h-100">
                              <div class="card-header">Usuarios más activos</div>
                              <div class="card-body p-0">
                                <table class="table mb-0">
                                  <thead><tr><th>Usuario</th><th>Pedidos</th><th>Opiniones</th></tr></thead>
                                  <tbody>
                                    <?php if (!$usuarios_activos): ?>
                                      <tr><td colspan="3" class="text-muted">Sin datos</td></tr>
                                    <?php else: foreach ($usuarios_activos as $ua): ?>
                                      <tr>
                                        <td><?php echo htmlspecialchars($ua['nombre'].' '.$ua['apellido']); ?></td>
                                        <td><?php echo $ua['pedidos']; ?></td>
                                        <td><?php echo $ua['opiniones']; ?></td>
                                      </tr>
                                    <?php endforeach; endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6 mb-4">
                            <div class="card h-100">
                              <div class="card-header">Ingresos por semana</div>
                              <div class="card-body p-0">
                                <table class="table mb-0">
                                  <thead><tr><th>Semana</th><th>Ingresos</th></tr></thead>
                                  <tbody>
                                    <?php foreach ($ingresos_semana as $w): ?>
                                      <tr>
                                        <td><?php echo date('Y-m-d', strtotime($w['periodo'])); ?></td>
                                        <td>$<?php echo number_format($w['ingresos'] ?? 0, 2); ?></td>
                                      </tr>
                                    <?php endforeach; if(!$ingresos_semana): ?>
                                      <tr><td colspan="2" class="text-muted">Sin datos</td></tr>
                                    <?php endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6 mb-4">
                            <div class="card h-100">
                              <div class="card-header">Ingresos por mes</div>
                              <div class="card-body p-0">
                                <table class="table mb-0">
                                  <thead><tr><th>Mes</th><th>Ingresos</th></tr></thead>
                                  <tbody>
                                    <?php foreach ($ingresos_mes as $m): ?>
                                      <tr>
                                        <td><?php echo date('Y-m', strtotime($m['periodo'])); ?></td>
                                        <td>$<?php echo number_format($m['ingresos'] ?? 0, 2); ?></td>
                                      </tr>
                                    <?php endforeach; if(!$ingresos_mes): ?>
                                      <tr><td colspan="2" class="text-muted">Sin datos</td></tr>
                                    <?php endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-12 mb-4">
                            <div class="card">
                              <div class="card-header">Opiniones promedio por producto</div>
                              <div class="card-body p-0">
                                <table class="table mb-0">
                                  <thead><tr><th>Producto</th><th>Promedio</th><th>Opiniones</th></tr></thead>
                                  <tbody>
                                    <?php if (!$opiniones_promedio): ?>
                                      <tr><td colspan="3" class="text-muted">Sin datos</td></tr>
                                    <?php else: foreach ($opiniones_promedio as $op): ?>
                                      <tr>
                                        <td><?php echo htmlspecialchars($op['titulo']); ?></td>
                                        <td><?php echo $op['promedio']; ?></td>
                                        <td><?php echo $op['total']; ?></td>
                                      </tr>
                                    <?php endforeach; endif; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                    <?php elseif ($vista_actual === 'actividad'): ?>
                      <h2 class="mb-4"><i class="bi bi-clock-history me-2"></i>Registro de Actividad</h2>
                      <div class="card">
                        <div class="card-body p-0">
                          <table class="table mb-0">
                            <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th></tr></thead>
                            <tbody>
                              <?php if(!$actividad): ?>
                                <tr><td colspan="3" class="text-muted">Sin actividad registrada</td></tr>
                              <?php else: foreach($actividad as $a): ?>
                                <tr>
                                  <td><?php echo date('d/m/Y H:i', strtotime($a['fecha'])); ?></td>
                                  <td><span class="badge bg-secondary"><?php echo htmlspecialchars($a['tipo']); ?></span></td>
                                  <td><?php echo htmlspecialchars($a['descripcion']); ?></td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    <?php elseif ($vista_actual === 'envios'): ?>
                      <h2 class="mb-4"><i class="bi bi-truck me-2"></i>Gestión de Envíos</h2>
                      <div class="card">
                        <div class="card-body p-0">
                          <table class="table mb-0">
                            <thead><tr><th>ID</th><th>Pedido</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
                            <tbody>
                              <?php if(!$envios): ?>
                                <tr><td colspan="5" class="text-muted">Sin envíos</td></tr>
                              <?php else: foreach($envios as $e): ?>
                                <tr>
                                  <td><?php echo $e['id_envio']; ?></td>
                                  <td>#<?php echo $e['id_pedido']; ?></td>
                                  <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($e['estado']); ?></span></td>
                                  <td><?php echo date('d/m/Y H:i', strtotime($e['fecha_creado'])); ?></td>
                                  <td>
                                    <form method="POST" class="d-flex gap-2 align-items-center">
                                      <input type="hidden" name="update_envio" value="1">
                                      <input type="hidden" name="id_envio" value="<?php echo $e['id_envio']; ?>">
                                      <select name="estado" class="form-select form-select-sm">
                                        <?php foreach (['pendiente','procesando','en_transito','entregado','cancelado'] as $st): ?>
                                          <option value="<?php echo $st; ?>" <?php if($st===$e['estado']) echo 'selected'; ?>>
                                            <?php echo $st; ?>
                                          </option>
                                        <?php endforeach; ?>
                                      </select>
                                      <button class="btn btn-sm btn-primary">Actualizar</button>
                                    </form>
                                  </td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    <?php elseif ($vista_actual === 'productos'): ?>
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
                                    <img src="<?php echo img_src_or_placeholder($prod['imagen']); ?>" 
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