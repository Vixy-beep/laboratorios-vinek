<?php
/**
 * Stripe Checkout - Versión simplificada
 * Crea sesión de pago usando cURL directo
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Cargar configuración
require_once 'config.php';

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Método no permitido']));
}

// Obtener datos
$input = json_decode(file_get_contents('php://input'), true);
$planId = (int)($input['plan_id'] ?? 0);
$userEmail = trim($input['email'] ?? '');

// Validaciones básicas
if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(['error' => 'Email inválido']));
}

if ($planId <= 1) {
    http_response_code(400);
    die(json_encode(['error' => 'Plan inválido']));
}

// Verificar Stripe configurado
if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
    http_response_code(500);
    die(json_encode(['error' => 'Stripe no configurado']));
}

// Obtener plan de BD
$stmt = $conn->prepare("SELECT id, name, price, credits FROM saas_plans WHERE id = ? AND active = TRUE");
$stmt->bind_param("i", $planId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['error' => 'Plan no encontrado']));
}

$plan = $result->fetch_assoc();

// Crear o verificar usuario
$stmt = $conn->prepare("SELECT id FROM saas_users WHERE email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    $stmt = $conn->prepare("INSERT INTO saas_users (email, plan_id, credits_limit) VALUES (?, 1, 1)");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
}

// Construir request para Stripe en formato form-urlencoded
$priceInCents = (int)($plan['price'] * 100);
$productDescription = ($plan['credits'] >= 9999 ? 'Análisis ilimitados' : $plan['credits'] . ' análisis') . ' por mes';

$postData = [
    'mode' => 'subscription',
    'success_url' => SITE_URL . '/jorise-payment-success.html?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => SITE_URL . '/jorise-pricing.html?canceled=1',
    'customer_email' => $userEmail,
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Jorise Security - ' . $plan['name'],
    'line_items[0][price_data][product_data][description]' => $productDescription,
    'line_items[0][price_data][unit_amount]' => $priceInCents,
    'line_items[0][price_data][recurring][interval]' => 'month',
    'line_items[0][quantity]' => 1,
    'payment_method_types[0]' => 'card',
    'billing_address_collection' => 'auto',
    'metadata[plan_id]' => $planId,
    'metadata[plan_name]' => $plan['name'],
    'metadata[user_email]' => $userEmail
];

// Llamar a Stripe API
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_POSTFIELDS => http_build_query($postData)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Procesar respuesta
if ($httpCode === 200) {
    $session = json_decode($response, true);
    
    if (isset($session['url'])) {
        // Guardar session ID
        $stmt = $conn->prepare("UPDATE saas_users SET stripe_session_id = ? WHERE email = ?");
        $stmt->bind_param("ss", $session['id'], $userEmail);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'checkout_url' => $session['url'],
            'session_id' => $session['id']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'No se pudo generar URL de checkout',
            'debug' => $session
        ]);
    }
} else {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? 'Error desconocido';
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de Stripe: ' . $errorMsg,
        'http_code' => $httpCode,
        'curl_error' => $curlError
    ]);
}
?>
