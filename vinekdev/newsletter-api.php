<?php
/**
 * Newsletter API - Sistema de suscripción de emails
 * Endpoints: subscribe, confirm, unsubscribe, send
 */

// Suprimir TODOS los errores para asegurar JSON limpio
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier buffer previo
if (ob_get_level()) ob_end_clean();
ob_start();

require_once 'config.php';
require_once 'email-sender.php'; // Sistema de emails profesional

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function para enviar JSON y terminar limpiamente
function sendJSON($data) {
    echo json_encode($data);
    ob_end_flush();
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'subscribe':
        subscribeNewsletter();
        break;
    case 'confirm':
        confirmSubscription();
        break;
    case 'unsubscribe':
        unsubscribeNewsletter();
        break;
    case 'count':
        getSubscribersCount();
        break;
    default:
        sendJSON(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Suscribir nuevo email a newsletter
 */
function subscribeNewsletter() {
    global $conn;
    
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        sendJSON(['success' => false, 'message' => 'Email inválido']);
    }
    
    // Generar token único para confirmación
    $token = bin2hex(random_bytes(32));
    
    // Verificar si el email ya existe
    $stmt = $conn->prepare("SELECT id, confirmed, active FROM newsletter_subscribers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $subscriber = $result->fetch_assoc();
        
        if ($subscriber['active'] && $subscriber['confirmed']) {
            sendJSON(['success' => false, 'message' => 'Este email ya está suscrito']);
        }
        
        // Reactivar suscripción si estaba inactiva
        $stmt = $conn->prepare("UPDATE newsletter_subscribers SET token = ?, active = TRUE, subscribed_at = NOW() WHERE email = ?");
        $stmt->bind_param("ss", $token, $email);
    } else {
        // Nuevo suscriptor
        $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, token) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $token);
    }
    
    if ($stmt->execute()) {
        // Enviar email de confirmación profesional
        $confirmUrl = SITE_URL . "/newsletter-confirm.html?token=" . $token;
        $sent = sendConfirmationEmailPro($email, $confirmUrl);
        
        sendJSON([
            'success' => true,
            'message' => '✅ Revisa tu email para confirmar tu suscripción',
            'email_sent' => $sent
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Error al registrar la suscripción']);
    }
}

/**
 * Confirmar suscripción via token
 */
function confirmSubscription() {
    global $conn;
    
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        sendJSON(['success' => false, 'message' => 'Token inválido']);
    }
    
    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET confirmed = TRUE, confirmed_at = NOW() WHERE token = ? AND active = TRUE");
    $stmt->bind_param("s", $token);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        sendJSON([
            'success' => true,
            'message' => '¡Suscripción confirmada! Ahora recibirás nuestras novedades'
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Token inválido o ya confirmado']);
    }
}

/**
 * Cancelar suscripción
 */
function unsubscribeNewsletter() {
    global $conn;
    
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        sendJSON(['success' => false, 'message' => 'Token inválido']);
    }
    
    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET active = FALSE, unsubscribed_at = NOW() WHERE token = ?");
    $stmt->bind_param("s", $token);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        sendJSON([
            'success' => true,
            'message' => 'Te has dado de baja de la newsletter'
        ]);
    } else {
        sendJSON(['success' => false, 'message' => 'Token inválido']);
    }
}

/**
 * Obtener cantidad de suscriptores
 */
function getSubscribersCount() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM newsletter_subscribers WHERE active = TRUE AND confirmed = TRUE");
    $row = $result->fetch_assoc();
    
    sendJSON([
        'success' => true,
        'count' => (int)$row['total']
    ]);
}
?>
