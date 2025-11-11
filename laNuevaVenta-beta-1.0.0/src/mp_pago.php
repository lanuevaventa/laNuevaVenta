<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || empty($_SESSION['carrito'])) {
    echo json_encode(['error' => 'Sin acceso']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$total = $input['total'];
$usuario = $input['usuario'];

// Items del carrito
$items = [];
foreach ($_SESSION['carrito'] as $item) {
    $items[] = [
        'title' => $item['nombre'],
        'unit_price' => (float)$item['precio'],
        'quantity' => (int)$item['cantidad']
    ];
}

// Crear preferencia
$preference = [
    'items' => $items,
    'payer' => [
        'name' => $usuario['nombre'],
        'surname' => $usuario['apellido'] ?? '',
        'email' => $usuario['correo']
    ],
    'back_urls' => [
        'success' => 'http://localhost:8080/mp_success.php',
        'failure' => 'http://localhost:8080/mp_failure.php'
    ]
];

// Llamada a MercadoPago
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($preference),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer TEST-1611544000506469-100807-7d7d99805052c04da734cd55a73598e3-2913053310'
    ]
]);

$response = curl_exec($curl);
curl_close($curl);

echo $response;
?>