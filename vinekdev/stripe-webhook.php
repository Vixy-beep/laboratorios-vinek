<?php
/**
 * Stripe Webhook - Recibir eventos de Stripe sin SDK
 * Maneja: checkout.session.completed, customer.subscription.deleted, etc.
 */

require_once 'config.php';

// Obtener payload del webhook
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Log del evento recibido (para debugging)
$logFile = 'stripe-webhook-log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook recibido\n", FILE_APPEND);
file_put_contents($logFile, "Signature: $sigHeader\n", FILE_APPEND);
file_put_contents($logFile, "Payload: $payload\n\n", FILE_APPEND);

// Verificar firma del webhook (opcional pero RECOMENDADO en producción)
if (defined('STRIPE_WEBHOOK_SECRET') && !empty(STRIPE_WEBHOOK_SECRET)) {
    $verified = verifyStripeSignature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
    if (!$verified) {
        http_response_code(400);
        file_put_contents($logFile, "ERROR: Firma inválida\n\n", FILE_APPEND);
        exit('Invalid signature');
    }
}

// Decodificar evento
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid event data');
}

// Procesar según tipo de evento
switch ($event['type']) {
    case 'checkout.session.completed':
        handleCheckoutCompleted($event['data']['object'], $conn);
        break;
        
    case 'customer.subscription.created':
        handleSubscriptionCreated($event['data']['object'], $conn);
        break;
        
    case 'customer.subscription.updated':
        handleSubscriptionUpdated($event['data']['object'], $conn);
        break;
        
    case 'customer.subscription.deleted':
        handleSubscriptionDeleted($event['data']['object'], $conn);
        break;
        
    case 'invoice.payment_succeeded':
        handlePaymentSucceeded($event['data']['object'], $conn);
        break;
        
    case 'invoice.payment_failed':
        handlePaymentFailed($event['data']['object'], $conn);
        break;
        
    default:
        file_put_contents($logFile, "Evento no manejado: {$event['type']}\n\n", FILE_APPEND);
        break;
}

http_response_code(200);
echo json_encode(['status' => 'success']);

/**
 * Verificar firma del webhook de Stripe
 */
function verifyStripeSignature($payload, $sigHeader, $secret) {
    if (empty($sigHeader)) {
        return false;
    }
    
    // Extraer timestamp y firma
    $elements = explode(',', $sigHeader);
    $timestamp = null;
    $signature = null;
    
    foreach ($elements as $element) {
        list($key, $value) = explode('=', $element, 2);
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signature = $value;
        }
    }
    
    if (!$timestamp || !$signature) {
        return false;
    }
    
    // Verificar que no sea muy antiguo (tolerancia de 5 minutos)
    if (abs(time() - $timestamp) > 300) {
        return false;
    }
    
    // Calcular firma esperada
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
    
    return hash_equals($expectedSignature, $signature);
}

/**
 * Checkout completado - Actualizar usuario a plan pagado
 */
function handleCheckoutCompleted($session, $conn) {
    global $logFile;
    
    $email = $session['customer_email'] ?? '';
    $customerId = $session['customer'] ?? '';
    $subscriptionId = $session['subscription'] ?? '';
    $planId = (int)($session['metadata']['plan_id'] ?? 0);
    
    if (empty($email) || $planId <= 1) {
        file_put_contents($logFile, "ERROR: Datos incompletos en checkout.session.completed\n", FILE_APPEND);
        return;
    }
    
    // Obtener créditos del plan
    $stmt = $conn->prepare("SELECT credits_per_month FROM saas_plans WHERE id = ?");
    $stmt->bind_param("i", $planId);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    
    if (!$plan) {
        file_put_contents($logFile, "ERROR: Plan $planId no encontrado\n", FILE_APPEND);
        return;
    }
    
    // Actualizar usuario
    $stmt = $conn->prepare("
        UPDATE saas_users 
        SET plan_id = ?, 
            credits_limit = ?, 
            credits_used = 0,
            stripe_customer_id = ?, 
            stripe_subscription_id = ?,
            current_period_start = CURDATE(),
            current_period_end = DATE_ADD(CURDATE(), INTERVAL 30 DAY),
            active = TRUE
        WHERE email = ?
    ");
    $stmt->bind_param("iisss", $planId, $plan['credits_per_month'], $customerId, $subscriptionId, $email);
    
    if ($stmt->execute()) {
        file_put_contents($logFile, "SUCCESS: Usuario $email actualizado a plan $planId\n\n", FILE_APPEND);
        
        // Opcional: Enviar email de bienvenida
        sendWelcomeEmail($email, $planId, $conn);
    } else {
        file_put_contents($logFile, "ERROR: No se pudo actualizar usuario $email\n\n", FILE_APPEND);
    }
}

/**
 * Suscripción creada
 */
function handleSubscriptionCreated($subscription, $conn) {
    global $logFile;
    file_put_contents($logFile, "Suscripción creada: {$subscription['id']}\n\n", FILE_APPEND);
    // El checkout.session.completed ya maneja la actualización
}

/**
 * Suscripción actualizada (cambio de plan, renovación, etc.)
 */
function handleSubscriptionUpdated($subscription, $conn) {
    global $logFile;
    
    $subscriptionId = $subscription['id'];
    $status = $subscription['status'];
    
    file_put_contents($logFile, "Suscripción actualizada: $subscriptionId - Status: $status\n\n", FILE_APPEND);
    
    // Si la suscripción está activa, asegurar que el usuario esté activo
    if ($status === 'active') {
        $stmt = $conn->prepare("UPDATE saas_users SET active = TRUE WHERE stripe_subscription_id = ?");
        $stmt->bind_param("s", $subscriptionId);
        $stmt->execute();
    }
}

/**
 * Suscripción cancelada - Revertir a plan Free
 */
function handleSubscriptionDeleted($subscription, $conn) {
    global $logFile;
    
    $subscriptionId = $subscription['id'];
    
    $stmt = $conn->prepare("
        UPDATE saas_users 
        SET plan_id = 1, 
            credits_limit = 1, 
            credits_used = 0,
            stripe_subscription_id = NULL,
            current_period_start = CURDATE(),
            current_period_end = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        WHERE stripe_subscription_id = ?
    ");
    $stmt->bind_param("s", $subscriptionId);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        file_put_contents($logFile, "SUCCESS: Suscripción $subscriptionId cancelada, usuario revertido a Free\n\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "ERROR: No se encontró usuario con suscripción $subscriptionId\n\n", FILE_APPEND);
    }
}

/**
 * Pago exitoso (renovación mensual)
 */
function handlePaymentSucceeded($invoice, $conn) {
    global $logFile;
    
    $subscriptionId = $invoice['subscription'] ?? '';
    
    if (empty($subscriptionId)) {
        return;
    }
    
    // Resetear créditos del mes
    $stmt = $conn->prepare("
        UPDATE saas_users u
        JOIN saas_plans p ON u.plan_id = p.id
        SET u.credits_used = 0,
            u.current_period_start = CURDATE(),
            u.current_period_end = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        WHERE u.stripe_subscription_id = ?
    ");
    $stmt->bind_param("s", $subscriptionId);
    $stmt->execute();
    
    file_put_contents($logFile, "SUCCESS: Créditos reseteados para suscripción $subscriptionId\n\n", FILE_APPEND);
}

/**
 * Pago fallido
 */
function handlePaymentFailed($invoice, $conn) {
    global $logFile;
    
    $subscriptionId = $invoice['subscription'] ?? '';
    $customerEmail = $invoice['customer_email'] ?? '';
    
    file_put_contents($logFile, "WARNING: Pago fallido para suscripción $subscriptionId ($customerEmail)\n\n", FILE_APPEND);
    
    // Opcional: Enviar email de notificación al usuario
    // Opcional: Desactivar cuenta después de X intentos fallidos
}

/**
 * Enviar email de bienvenida (opcional)
 */
function sendWelcomeEmail($email, $planId, $conn) {
    $stmt = $conn->prepare("SELECT name FROM saas_plans WHERE id = ?");
    $stmt->bind_param("i", $planId);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    
    $planName = $plan['name'] ?? 'Premium';
    
    $subject = "¡Bienvenido a Jorise Security - Plan $planName!";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>🎉 ¡Bienvenido a Jorise Security!</h2>
        <p>Gracias por suscribirte al plan <strong>$planName</strong>.</p>
        <p>Ya puedes acceder a tu dashboard: <a href='" . SITE_URL . "/jorise-dashboard.html'>Ir al Dashboard</a></p>
        <p>Si tienes alguna pregunta, contáctanos en info@vineksec.online</p>
        <p>Saludos,<br>El equipo de VinekDev</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: VinekDev <" . SMTP_USER . ">\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>
