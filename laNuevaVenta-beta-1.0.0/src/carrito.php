<?php
session_start();
require_once 'conexion.php';

// verifica si el usuario esta logueado
if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit;
}

// verificar admin
function esAdmin() {
    global $conexion;
    if (!isset($_SESSION['usuario'])) return false;
    $query = "SELECT rol FROM Usuario WHERE id_usuario = $1";
    $result = pg_query_params($conexion, $query, [$_SESSION['usuario']]);
    $usuario = pg_fetch_assoc($result);
    return $usuario && $usuario['rol'] === 'admin';
}

// Helpers de ofertas/cupones
function oferta_activa_row($row) {
    if (!$row) return false;
    if (!$row['oferta_activa']) return false;
    $hoy = new DateTime('today');
    if (!empty($row['oferta_desde'])) {
        $desde = new DateTime($row['oferta_desde']);
        if ($hoy < $desde) return false;
    }
    if (!empty($row['oferta_hasta'])) {
        $hasta = new DateTime($row['oferta_hasta']);
        if ($hoy > $hasta) return false;
    }
    return true;
}
function precio_con_oferta($precio_base, $row) {
    if (!$row || empty($row['oferta_activa'])) return round($precio_base, 2);
    $tipo = $row['oferta_tipo'];
    $valor = (float)$row['oferta_valor'];
    if ($tipo === 'porcentaje') {
        $p = $precio_base * (1 - ($valor/100));
    } elseif ($tipo === 'fijo') {
        // descuento fijo (resta)
        $p = $precio_base - $valor;
    } else {
        // Tipo desconocido: no aplicar oferta
        $p = $precio_base;
    }
    return max(0.0, round($p, 2));
}

function get_oferta_row($conexion, $id_producto) {
    $q = "SELECT precio, oferta_activa, oferta_tipo, oferta_valor, oferta_desde, oferta_hasta 
          FROM Producto WHERE id_producto = $1";
    // Evitar warnings fatales si aún no existen las columnas
    $r = @pg_query_params($conexion, $q, [$id_producto]);
    if ($r === false) {
        // Intentar al menos obtener el precio base y asumir sin oferta
        $r2 = @pg_query_params($conexion, "SELECT precio FROM Producto WHERE id_producto = $1", [$id_producto]);
        $precio = null;
        if ($r2 !== false) {
            $tmp = pg_fetch_assoc($r2);
            if ($tmp && isset($tmp['precio'])) {
                $precio = (float)$tmp['precio'];
            }
        }
        return [
            'precio' => $precio,
            'oferta_activa' => false,
            'oferta_tipo' => null,
            'oferta_valor' => null,
            'oferta_desde' => null,
            'oferta_hasta' => null
        ];
    }
    return pg_fetch_assoc($r) ?: null;
}

function get_cupon_row($conexion, $id_producto, $codigo) {
    // Usar la tabla correcta definida en el schema: Cupon
    $q = "SELECT * FROM Cupon WHERE id_producto = $1 AND UPPER(codigo) = UPPER($2) LIMIT 1";
    $r = pg_query_params($conexion, $q, [$id_producto, $codigo]);
    return pg_fetch_assoc($r) ?: null;
}

// Validar si un cupón es válido (activo y dentro de fechas)
function cupon_valido($cupon) {
    if (!$cupon || empty($cupon['activo']) || $cupon['activo'] === 'f') {
        return false;
    }
    
    $hoy = new DateTime();
    
    if (!empty($cupon['valido_desde'])) {
        $desde = new DateTime($cupon['valido_desde']);
        if ($hoy < $desde) {
            return false; // Cupón aún no válido
        }
    }
    
    if (!empty($cupon['valido_hasta'])) {
        $hasta = new DateTime($cupon['valido_hasta']);
        if ($hoy > $hasta) {
            return false; // Cupón expirado
        }
    }
    
    return true;
}

// Calcular precio después de aplicar cupón
function precio_con_cupon($precio_base, $cupon) {
    if (!$cupon) return round($precio_base, 2);
    
    $tipo = $cupon['tipo'];
    $valor = (float)$cupon['valor'];
    
    if ($tipo === 'porcentaje') {
        $descuento = $precio_base * ($valor / 100);
    } elseif ($tipo === 'fijo') {
        $descuento = $valor;
    } else {
        return round($precio_base, 2);
    }
    
    $precio_final = $precio_base - $descuento;
    return max(0.0, round($precio_final, 2));
}

// obtener el carrito de la sesion
$carrito = (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) ? $_SESSION['carrito'] : [];
// Asegurar que la clave exista como arreglo en la sesión
if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = $carrito;
}
$mensaje = '';
$mensaje_tipo = 'success';

// Eliminar producto del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = (int)$_POST['eliminar'];
    if (isset($carrito[$id])) {
        unset($carrito[$id]);
        $_SESSION['carrito'] = $carrito;
        $mensaje = 'Producto eliminado del carrito.';
    }
}

// Aplicar cupón a un ítem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar_cupon'])) {
    $id = (int)($_POST['id_producto'] ?? 0);
    $codigo = trim($_POST['codigo_cupon'] ?? '');
    if ($id > 0 && $codigo !== '' && isset($carrito[$id])) {
        $ofertaRow = get_oferta_row($conexion, $id);
        // Precio base real del producto (por si el precio en sesión quedó desactualizado)
        $precio_base = $ofertaRow ? (float)$ofertaRow['precio'] : (float)$carrito[$id]['precio'];
        $precio_oferta = precio_con_oferta($precio_base, $ofertaRow);

        $cupon = get_cupon_row($conexion, $id, $codigo);
        if ($cupon && cupon_valido($cupon)) {
            $precio_final = precio_con_cupon($precio_oferta, $cupon);
            // Guardar meta y precio final en sesión
            $_SESSION['carrito'][$id]['precio_base'] = round($precio_base, 2);
            $_SESSION['carrito'][$id]['precio_oferta'] = round($precio_oferta, 2);
            $_SESSION['carrito'][$id]['precio'] = round($precio_final, 2); // precio efectivo por unidad
            $_SESSION['carrito'][$id]['cupon'] = [
                'codigo' => $cupon['codigo'],
                'tipo' => $cupon['tipo'],
                'valor' => (float)$cupon['valor']
            ];
            $carrito = $_SESSION['carrito'];
            $mensaje = "Cupón aplicado a {$carrito[$id]['nombre']}.";
            $mensaje_tipo = 'success';
        } else {
            $mensaje = "El cupón no es válido para este producto.";
            $mensaje_tipo = 'danger';
        }
    }
}

// Quitar cupón de un ítem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quitar_cupon'])) {
    $id = (int)($_POST['id_producto'] ?? 0);
    if ($id > 0 && isset($carrito[$id])) {
        $ofertaRow = get_oferta_row($conexion, $id);
        $precio_base = $ofertaRow ? (float)$ofertaRow['precio'] : (float)($carrito[$id]['precio_base'] ?? $carrito[$id]['precio']);
        $precio_oferta = precio_con_oferta($precio_base, $ofertaRow);

        $_SESSION['carrito'][$id]['precio_base'] = round($precio_base, 2);
        $_SESSION['carrito'][$id]['precio_oferta'] = round($precio_oferta, 2);
        $_SESSION['carrito'][$id]['precio'] = round($precio_oferta, 2);
        unset($_SESSION['carrito'][$id]['cupon']);
        $carrito = $_SESSION['carrito'];
        $mensaje = "Cupón quitado de {$carrito[$id]['nombre']}.";
        $mensaje_tipo = 'secondary';
    }
}

// Recalcular precios efectivos (oferta + cupón) en cada carga
foreach ($carrito as $id => $item) {
    $ofertaRow = get_oferta_row($conexion, (int)$id);
    $precio_base_db = $ofertaRow ? (float)$ofertaRow['precio'] : (float)$item['precio'];
    $precio_oferta = precio_con_oferta($precio_base_db, $ofertaRow);
    $precio_final = $precio_oferta;

    // Revalidar cupón si estaba aplicado
    if (!empty($item['cupon']) && is_array($item['cupon'])) {
        $cupon = get_cupon_row($conexion, (int)$id, $item['cupon']['codigo']);
        if ($cupon && cupon_valido($cupon)) {
            $precio_final = precio_con_cupon($precio_oferta, $cupon);
        } else {
            // cupón ya no válido
            unset($_SESSION['carrito'][$id]['cupon']);
            $mensaje = "Un cupón dejó de ser válido y fue quitado de {$item['nombre']}.";
            $mensaje_tipo = 'warning';
        }
    }

    $_SESSION['carrito'][$id]['precio_base'] = round($precio_base_db, 2);
    $_SESSION['carrito'][$id]['precio_oferta'] = round($precio_oferta, 2);
    $_SESSION['carrito'][$id]['precio'] = round($precio_final, 2);
}
// Asegurar que $carrito siempre sea un arreglo aunque la sesión no tenga la clave
$carrito = (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) ? $_SESSION['carrito'] : [];

// Obtener datos del usuario
$usuario_data = null;
if (isset($_SESSION['usuario'])) {
    $query = "SELECT nombre, apellido, correo FROM Usuario WHERE id_usuario = $1";
    $result = pg_query_params($conexion, $query, [$_SESSION['usuario']]);
    $usuario_data = pg_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/lnvVioleta.png">
    <title>La Nueva Venta | Carrito</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- MercadoPago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
</head>
<body data-page="carrito">
  <!-- Navbar -->
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
          <button class="btn btn-login-dropdown dropdown-toggle text-cream" type="button" id="dropdownLogin" data-bs-toggle="dropdown" aria-expanded="false">
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
          <?php 
          $carrito_count = count($carrito);
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

    <main id="carrito-main" class="container-fluid carrito-main">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $mensaje_tipo; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($carrito)): ?>
            <div class="carrito-vacio">
                <i class="bi bi-cart-x"></i>
                <h3 class="mt-3">Tu carrito está vacío</h3>
                <p class="text-muted mb-4">Agrega algunos productos para continuar</p>
                <a href="index.php" class="btn btn-violeta btn-lg">
                    <i class="bi bi-shop me-2"></i>Explorar productos
                </a>
            </div>
        <?php else: ?>
        
        <div class="row">
            <!-- Columna del resumen -->
            <div class="col-lg-4 col-md-5 mb-4 order-md-2">
                <div class="carrito-resumen card p-4">
                    <h5 class="mb-3">
                        <i class="bi bi-cart-check me-2 text-violeta"></i>
                        Resumen de compra
                    </h5>
                    
                    <div id="carritoSuma" class="mb-3">
                        <?php
                        $total = 0;
                        foreach ($carrito as $item) {
                            $subtotal = $item['precio'] * $item['cantidad'];
                            echo "<div class='carrito-suma-item'>
                                    <span class='text-muted'>".htmlspecialchars($item['nombre'])." <small>(x{$item['cantidad']})</small></span>
                                    <span class='fw-semibold'>$" . number_format($subtotal, 2) . "</span>
                                  </div>";
                            $total += $subtotal;
                        }
                        ?>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-5 fw-bold">Total:</span>
                        <span class="fs-4 fw-bold text-success">
                            $<span id="carritoTotal"><?php echo number_format($total, 2); ?></span>
                        </span>
                    </div>
                    
                    <!-- MercadoPago -->
                    <div class="mb-3">
                        <h6 class="mb-3">
                            <i class="bi bi-credit-card me-2"></i>
                            Pagar con MercadoPago
                        </h6>
                        
                        <div id="loading" class="text-center py-3" style="display: none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <small class="ms-2 text-muted">Preparando pago...</small>
                        </div>
                        
                        <div id="mercadopago-button"></div>
                    </div>
                    
                    <div class="text-center mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Pago 100% seguro con MercadoPago
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Columna de productos -->
            <div class="col-lg-8 col-md-7 order-md-1">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">
                        <i class="bi bi-bag me-2 text-violeta"></i>
                        Productos en tu carrito
                    </h4>
                    <span class="badge bg-violeta"><?php echo count($carrito); ?> items</span>
                </div>
                
                <div id="carritoListado">
                    <?php
                    foreach ($carrito as $id => $item) {
                        $precio_base = isset($item['precio_base']) ? $item['precio_base'] : $item['precio'];
                        $precio_oferta = isset($item['precio_oferta']) ? $item['precio_oferta'] : $item['precio'];
                        $tiene_oferta = $precio_oferta < $precio_base;
                        $tiene_cupon = !empty($item['cupon']);
                        $precio_unit = $item['precio']; // efectivo (oferta+cupón si aplica)
                        $subtotal = $precio_unit * $item['cantidad'];
                        echo "<div class='carrito-producto-card'>
                                <div class='card-body'>
                                    <div class='row align-items-center g-4'>
                                        <div class='col-md-2 text-center'>
                                            <img src='".htmlspecialchars($item['imagen'])."' 
                                                 class='carrito-producto-img' 
                                                 alt='" . htmlspecialchars($item['nombre']) . "'
                                                 onerror=\"this.src='img/placeholder.png'\">
                                        </div>
                                        <div class='col-md-5'>
                                            <div class='carrito-producto-nombre'>".htmlspecialchars($item['nombre'])."</div>";
                        if ($tiene_oferta) {
                            // FIX: cerrar comilla en class
                            echo "<div class='carrito-producto-precio'>
                                    <span class='text-decoration-line-through text-muted'>$".number_format($precio_base,2)."</span>
                                    <span class='ms-2 fw-semibold text-success'>$".number_format($precio_oferta,2)."</span>
                                    <span class='badge bg-success ms-2'>Oferta</span>
                                  </div>";
                        } else {
                            echo "<div class='carrito-producto-precio'>
                                    $".number_format($precio_base,2)." 
                                    <small class='text-muted fw-normal' style='font-size: 0.85rem;'>c/u</small>
                                  </div>";
                        }
                        if ($tiene_cupon) {
                            $etiqueta = ($item['cupon']['tipo']==='porcentaje' ? ($item['cupon']['valor'].'%') : ('$'.number_format($item['cupon']['valor'],2)));
                            echo "<div class='mt-1'>
                                    <span class='badge bg-primary'><i class='bi bi-ticket-perforated me-1'></i> Cupón ".htmlspecialchars(strtoupper($item['cupon']['codigo']))." (-{$etiqueta})</span>
                                  </div>";
                        }
                        echo "          <div class='carrito-producto-stock mt-2'>
                                                <i class='bi bi-check-circle-fill text-success me-1'></i>
                                                En stock · Cantidad: <strong>{$item['cantidad']}</strong>
                                            </div>
                                        </div>
                                        <div class='col-md-5 text-md-end'>
                                            <div class='carrito-producto-total mb-2'>
                                                $".number_format($subtotal, 2)."
                                            </div>
                                            <div class='d-flex flex-column flex-md-row gap-2 justify-content-md-end'>";
                        // Form cupón
                        echo "              <form method='post' class='d-flex gap-2 align-items-center'>
                                                    <input type='hidden' name='id_producto' value='".(int)$id."'>";
                        if ($tiene_cupon) {
                            echo "          <button class='btn btn-outline-secondary btn-sm' name='quitar_cupon' value='1' type='submit'>
                                                    <i class='bi bi-x-circle me-1'></i>Quitar cupón
                                                </button>";
                        } else {
                            echo "          <input class='form-control form-control-sm' style='max-width: 160px;' type='text' name='codigo_cupon' placeholder='Código cupón' required>
                                                <button class='btn btn-outline-primary btn-sm' name='aplicar_cupon' value='1' type='submit'>
                                                    <i class='bi bi-ticket-perforated me-1'></i>Aplicar
                                                </button>";
                        }
                        echo "                  </form>
                                                <form method='post' class='d-inline'>
                                                    <input type='hidden' name='eliminar' value='".(int)$id."'>
                                                    <button class='btn-eliminar-carrito' type='submit'>
                                                        <i class='bi bi-trash3 me-2'></i>
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                              </div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- JavaScript MercadoPago -->
    <script>
        <?php if (!empty($carrito) && $usuario_data): ?>
        const mp = new MercadoPago('TEST-040e2f71-d79b-43eb-a7a8-068ef3425c57'); // CAMBIAR POR TU PUBLIC KEY
        document.getElementById('loading').style.display = 'block';
        fetch('mp_pago.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                total: <?php echo isset($total) ? $total : 0; ?>,
                usuario: <?php echo json_encode($usuario_data); ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('MercadoPago Response:', data);
            document.getElementById('loading').style.display = 'none';
            if (data.id) {
                mp.checkout({
                    preference: { id: data.id },
                    render: { container: '#mercadopago-button', label: 'Pagar ahora' }
                });
            } else {
                document.getElementById('mercadopago-button').innerHTML =
                    '<div class="alert alert-danger">Error al cargar MercadoPago. Intenta nuevamente.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('loading').style.display = 'none';
            document.getElementById('mercadopago-button').innerHTML =
                '<div class="alert alert-danger">Error de conexión. Verifica tu internet.</div>';
        });
        <?php endif; ?>
    </script>
    
    <script src="js/carrito.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <!-- Chatbot Widget (idéntico formato al index) -->
  <button id="chatbotFab" class="chatbot-fab" aria-controls="chatbotPanel" aria-expanded="false" title="Ayuda">
    <i class="bi bi-robot fs-4"></i>
  </button>
  <div id="chatbotPanel" class="chatbot-panel" hidden>
    <div class="chatbot-header">
      <span>Asistente</span>
      <button id="chatbotClose" class="chatbot-close" aria-label="Cerrar">×</button>
    </div>
    <div class="chatbot-body">
      <div id="chatbotConversation" class="chatbot-conversation"></div>
      <p class="chatbot-subtitle">Preguntas sugeridas</p>
      <ul id="chatbotQuestions" class="chatbot-questions"></ul>
    </div>
  </div>

  <script>
    (function () {
      const PAGE_ID = document.body.getAttribute('data-page') || 'home';
      const QUESTIONS = {
        carrito: [
          '¿Cómo aplicar un cupón?',
          '¿Cómo quitar un cupón?',
          '¿Cómo elimino un producto del carrito?',
          '¿Cómo se calcula el total?',
          '¿Cómo finalizo la compra con MercadoPago?',
          '¿Es seguro el pago?',
          'Tengo problemas al cargar MercadoPago',
          '¿Cómo cambio la cantidad de un producto?'
        ]
      };

      const ANSWERS = {
        '¿Cómo aplicar un cupón?': 'En cada producto hay un campo "Código cupón". Ingresá el código y presioná "Aplicar". El descuento se muestra al instante.',
        '¿Cómo quitar un cupón?': 'En el mismo producto, presioná el botón "Quitar cupón" para volver al precio sin cupón.',
        '¿Cómo elimino un producto del carrito?': 'Usá el botón "Eliminar" en la tarjeta del producto dentro del carrito.',
        '¿Cómo se calcula el total?': 'El subtotal por ítem es precio unitario (con oferta y/o cupón) por cantidad. El total es la suma de todos los subtotales.',
        '¿Cómo finalizo la compra con MercadoPago?': 'En el Resumen de compra presioná el botón de MercadoPago. Se abrirá el checkout para completar el pago.',
        '¿Es seguro el pago?': 'Sí. El pago se procesa de forma segura a través de MercadoPago.',
        'Tengo problemas al cargar MercadoPago': 'Reintentá recargar la página. Si persiste, verificá tu conexión o volvé más tarde.',
        '¿Cómo cambio la cantidad de un producto?': 'Si no ves un selector de cantidad, eliminá el ítem y agregalo nuevamente con la cantidad deseada desde el producto.'
      };

      const fab = document.getElementById('chatbotFab');
      const panel = document.getElementById('chatbotPanel');
      const closeBtn = document.getElementById('chatbotClose');
      const list = document.getElementById('chatbotQuestions');
      const convo = document.getElementById('chatbotConversation');

      if (!fab || !panel || !closeBtn || !list || !convo) return;

      function appendMessage(text, type) {
        const div = document.createElement('div');
        div.className = 'chatbot-msg ' + (type === 'user' ? 'chatbot-msg-user' : 'chatbot-msg-bot');
        div.textContent = text;
        convo.appendChild(div);
        convo.parentElement.scrollTop = convo.parentElement.scrollHeight;
      }

      function renderQuestions() {
        const qs = QUESTIONS[PAGE_ID] || [];
        list.innerHTML = qs.map(q => `<li class="chatbot-question" role="button" tabindex="0">${q}</li>`).join('');
        list.querySelectorAll('.chatbot-question').forEach(item => {
          const question = item.textContent || '';
          const click = () => {
            appendMessage(question, 'user');
            const answer = ANSWERS[question] || 'Gracias por tu consulta.';
            setTimeout(() => appendMessage(answer, 'bot'), 250);
          };
          item.addEventListener('click', click);
          item.addEventListener('keypress', e => { if (e.key === 'Enter') click(); });
        });
      }

      function open() { panel.hidden = false; fab.setAttribute('aria-expanded', 'true'); }
      function close() { panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); }

      fab.addEventListener('click', () => panel.hidden ? open() : close());
      closeBtn.addEventListener('click', close);
      document.addEventListener('click', e => {
        if (!panel.hidden && !panel.contains(e.target) && !fab.contains(e.target)) close();
      });

      renderQuestions();
    })();
  </script>
</body>
</html>