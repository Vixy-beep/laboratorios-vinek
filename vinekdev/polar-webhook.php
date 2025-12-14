<?php
/**
 * Polar.sh Webhook Handler
 * Documentación: https://docs.polar.sh/api/webhooks
 */

require_once 'config.php';

// Log de eventos
$log_file = __DIR__ . '/polar-webhook-log.txt';

// Obtener payload del webhook
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_POLAR_SIGNATURE'] ?? '';

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Webhook recibido\n", FILE_APPEND);
file_put_contents($log_file, "Signature: $signature\n", FILE_APPEND);
file_put_contents($log_file, "Payload: $payload\n\n", FILE_APPEND);

// Verificar firma del webhook (IMPORTANTE para seguridad)
if (defined('POLAR_WEBHOOK_SECRET') && !empty(POLAR_WEBHOOK_SECRET)) {
    $expectedSignature = hash_hmac('sha256', $payload, POLAR_WEBHOOK_SECRET);
    
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(401);
        file_put_contents($log_file, "ERROR: Firma inválida\n\n", FILE_APPEND);
        exit('Invalid signature');
    }
}

// Decodificar evento
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid event data');
}

// Procesar evento según tipo
switch ($event['type']) {
    
    case 'checkout.completed':
        // Pago completado exitosamente
        handleCheckoutCompleted($event['data']);
        break;
    
    case 'subscription.created':
        // Nueva suscripción creada
        handleSubscriptionCreated($event['data']);
        break;
    
    case 'subscription.updated':
        // Suscripción actualizada (upgrade/downgrade)
        handleSubscriptionUpdated($event['data']);
        break;
    
    case 'subscription.canceled':
        // Suscripción cancelada
        handleSubscriptionCanceled($event['data']);
        break;
    
    default:
        file_put_contents($log_file, "Evento no manejado: {$event['type']}\n\n", FILE_APPEND);
        break;
}

// Responder OK a Polar
http_response_code(200);
echo json_encode(['received' => true]);

/**
 * Manejar checkout completado
 */
function handleCheckoutCompleted($data) {
    global $conn, $log_file;
    
    $checkout_id = $data['id'];
    $customer_email = $data['customer_email'];
    $metadata = $data['metadata'] ?? [];
    $user_id = $metadata['user_id'] ?? null;
    $plan_id = $metadata['plan_id'] ?? null;
    
    if (!$user_id || !$plan_id) {
        file_put_contents($log_file, "ERROR: Metadata incompleto\n\n", FILE_APPEND);
        return;
    }
    
    // Actualizar transacción
    $stmt = $conn->prepare("UPDATE saas_transactions SET status = 'completed', paid_at = NOW() WHERE polar_checkout_id = ?");
    $stmt->bind_param("s", $checkout_id);
    $stmt->execute();
    
    // Actualizar plan del usuario
    $stmt = $conn->prepare("
        UPDATE saas_users 
        SET plan_id = ?, 
            credits_limit = (SELECT credits FROM saas_plans WHERE id = ?),
            credits_used = 0,
            subscription_date = NOW(),
            polar_subscription_id = ?
        WHERE id = ?
    ");
    $polar_sub_id = $data['subscription_id'] ?? null;
    $stmt->bind_param("iisi", $plan_id, $plan_id, $polar_sub_id, $user_id);
    $stmt->execute();
    
    file_put_contents($log_file, "✓ Checkout completado - Usuario $user_id actualizado a plan $plan_id\n\n", FILE_APPEND);
    
    // Enviar email de confirmación
    sendPaymentConfirmationEmail($customer_email, $metadata['plan_name'] ?? 'Premium');
}

/**
 * Manejar nueva suscripción
 */
function handleSubscriptionCreated($data) {
    global $log_file;
    file_put_contents($log_file, "✓ Suscripción creada: {$data['id']}\n\n", FILE_APPEND);
}

/**
 * Manejar actualización de suscripción
 */
function handleSubscriptionUpdated($data) {
    global $log_file;
    file_put_contents($log_file, "ℹ Suscripción actualizada: {$data['id']}\n\n", FILE_APPEND);
}

/**
 * Manejar cancelación de suscripción
 */
function handleSubscriptionCanceled($data) {
    global $conn, $log_file;
    
    $subscription_id = $data['id'];
    
    // Degradar usuario a plan Free
    $stmt = $conn->prepare("UPDATE saas_users SET plan_id = 1, credits_limit = 1 WHERE polar_subscription_id = ?");
    $stmt->bind_param("s", $subscription_id);
    $stmt->execute();
    
    file_put_contents($log_file, "✗ Suscripción cancelada: $subscription_id - Usuario degradado a Free\n\n", FILE_APPEND);
}

/**
 * Enviar email de confirmación
 */
function sendPaymentConfirmationEmail($email, $planName) {
    $subject = "¡Bienvenido a Jorise $planName!";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>✓ Suscripción Activada</h2>
        <p>Hola,</p>
        <p>Tu suscripción al plan <strong>$planName</strong> ha sido activada exitosamente.</p>
        <p>Ya puedes acceder a todas las funciones en tu dashboard:</p>
        <p><a href='" . SITE_URL . "/jorise-dashboard.html' style='background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Ir al Dashboard</a></p>
        <p>Gracias por confiar en nosotros,<br><strong>VinekSec Team</strong></p>
    </body>
    </html>
    ";
    
    $headers = "From: " . FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>
