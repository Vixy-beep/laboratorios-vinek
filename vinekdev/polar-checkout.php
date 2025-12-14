<?php
/**
 * Polar.sh Checkout - Crear sesión de pago
 * Documentación: https://docs.polar.sh/api
 */

header('Content-Type: application/json');
require_once 'config.php';

// Leer datos de la solicitud
$input = json_decode(file_get_contents('php://input'), true);
$plan_id = $input['plan_id'] ?? 0;
$email = $input['email'] ?? '';

// Validar entrada
if (!$plan_id || !$email) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Obtener información del plan
$stmt = $conn->prepare("SELECT * FROM saas_plans WHERE id = ?");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan || $plan['price'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'Plan inválido']);
    exit;
}

// Buscar o crear usuario
$stmt = $conn->prepare("SELECT * FROM saas_users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    // Crear usuario con plan Free
    $stmt = $conn->prepare("INSERT INTO saas_users (email, plan_id, credits_used, credits_limit) VALUES (?, 1, 0, 1)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user_id = $conn->insert_id;
} else {
    $user_id = $user['id'];
}

// Mapear plan_id a product_id de Polar
$polarProducts = [
    2 => POLAR_PRODUCT_PRO  // Plan Pro $39/mes
];

if (!isset($polarProducts[$plan_id])) {
    echo json_encode(['success' => false, 'error' => 'Plan no disponible']);
    exit;
}

// En lugar de API, usar Checkout Link de Polar
$productId = $polarProducts[$plan_id];
$checkoutUrl = "https://polar.sh/checkout/" . $productId . "?email=" . urlencode($email);

// Guardar checkout en BD
$stmt = $conn->prepare("INSERT INTO saas_transactions (user_id, plan_id, amount, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iid", $user_id, $plan_id, $plan['price']);
$stmt->execute();

echo json_encode([
    'success' => true,
    'checkout_url' => $checkoutUrl
]);
?>
