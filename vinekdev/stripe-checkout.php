<?php
/**
 * Stripe Checkout - Crear sesión de pago sin SDK
 * Solo usa cURL para llamar a Stripe API REST
 */

// Error reporting para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/stripe-error.log');

try {
    require_once 'config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración: ' . $e->getMessage()]);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener datos del request
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit();
}

$planId = (int)($input['plan_id'] ?? 0);
$userEmail = trim($input['email'] ?? '');

// Log para debugging
error_log("Stripe Checkout Request - Plan: $planId, Email: $userEmail");

// Validar email
if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit();
}

// Validar plan
if ($planId <= 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan inválido. El plan Free no requiere pago.']);
    exit();
}

// Verificar que STRIPE_SECRET_KEY esté definida
if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe no está configurado en el servidor']);
    exit();
}

// Obtener información del plan
$stmt = $conn->prepare("SELECT id, name, price, credits, features FROM saas_plans WHERE id = ? AND active = TRUE");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en base de datos: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $planId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Plan no encontrado']);
    exit();
}

$plan = $result->fetch_assoc();
error_log("Plan encontrado: " . json_encode($plan));

// Verificar o crear usuario
$stmt = $conn->prepare("SELECT id FROM saas_users WHERE email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    // Crear usuario nuevo con plan Free temporalmente
    $stmt = $conn->prepare("INSERT INTO saas_users (email, plan_id, credits_limit, current_period_start, current_period_end) VALUES (?, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
}

// Preparar datos para Stripe Checkout Session
$lineItems = [
    [
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Jorise Security - ' . $plan['name'],
                'description' => ($plan['credits'] >= 9999 ? 'Análisis ilimitados' : $plan['credits'] . ' créditos de análisis') . ' por mes'
            ],
            'unit_amount' => (int)($plan['price'] * 100), // Centavos
            'recurring' => [
                'interval' => 'month'
            ]
        ],
        'quantity' => 1
    ]
];

$checkoutData = [
    'mode' => 'subscription',
    'success_url' => SITE_URL . '/jorise-dashboard.html?success=1&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => SITE_URL . '/jorise-pricing.html?canceled=1',
    'customer_email' => $userEmail,
    'line_items' => $lineItems,
    'payment_method_types' => ['card'],
    'billing_address_collection' => 'auto',
    'metadata' => [
        'plan_id' => $planId,
        'plan_name' => $plan['name'],
        'user_email' => $userEmail
    ]
];

// Convertir array a formato x-www-form-urlencoded para Stripe
$postFields = http_build_query(flattenStripeData($checkoutData));

// Llamar a Stripe API usando cURL
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_POSTFIELDS => $postFields
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log para debugging (opcional)
error_log("Stripe Checkout Response (HTTP $httpCode): " . $response);

if ($httpCode === 200) {
    $session = json_decode($response, true);
    
    if (isset($session['url'])) {
        // Guardar session_id temporalmente para verificación posterior
        $stmt = $conn->prepare("UPDATE saas_users SET stripe_session_id = ? WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $session['id'], $userEmail);
            $stmt->execute();
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'checkout_url' => $session['url'],
            'session_id' => $session['id']
        ]);
    } else {
        http_response_code(500);
        error_log("Stripe response sin URL: " . $response);
        echo json_encode(['error' => 'No se pudo generar URL de checkout', 'stripe_response' => $response]);
    }
} else {
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error']['message'] ?? 'Error desconocido de Stripe';
    
    error_log("Stripe API Error (HTTP $httpCode): " . $errorMessage);
    error_log("Full response: " . $response);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al procesar pago: ' . $errorMessage,
        'http_code' => $httpCode,
        'curl_error' => $curlError
    ]);
}

/**
 * Función helper para convertir array PHP a formato que Stripe acepta
 * Stripe API necesita formato: line_items[0][price_data][currency]=usd
 */
function flattenStripeData($array, $prefix = '') {
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = $prefix === '' ? $key : "{$prefix}[{$key}]";
        
        if (is_array($value)) {
            $result = array_merge($result, flattenStripeData($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    return $result;
}
?>
