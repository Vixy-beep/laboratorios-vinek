<?php
/**
 * Send Newsletter - Enviar notificación de nuevo post a suscriptores
 * Se llama cuando se publica un nuevo post desde el admin
 */

require_once 'config.php';
require_once 'email-sender.php'; // Sistema de emails profesional

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado como admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$postId = (int)($_POST['post_id'] ?? 0);

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de post inválido']);
    exit();
}

// Obtener información del post
$stmt = $conn->prepare("SELECT title, excerpt, category, created_at, image FROM posts WHERE id = ? AND status = 'published'");
$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Post no encontrado o no publicado']);
    exit();
}

$post = $result->fetch_assoc();

// Obtener suscriptores activos y confirmados
$subscribers = $conn->query("SELECT email, token FROM newsletter_subscribers WHERE active = TRUE AND confirmed = TRUE");

if ($subscribers->num_rows === 0) {
    echo json_encode(['success' => true, 'message' => 'No hay suscriptores activos', 'sent' => 0]);
    exit();
}

$sent = 0;
$failed = 0;
$postUrl = SITE_URL . "/post.html?id=" . $postId;

while ($subscriber = $subscribers->fetch_assoc()) {
    $unsubscribeUrl = SITE_URL . "/newsletter-api.php?action=unsubscribe&token=" . $subscriber['token'];
    
    if (sendNewPostEmailPro($subscriber['email'], $post, $postUrl, $unsubscribeUrl)) {
        $sent++;
    } else {
        $failed++;
    }
    
    // Pequeño delay para evitar límites de envío
    usleep(100000); // 0.1 segundos
}

echo json_encode([
    'success' => true,
    'message' => "Newsletter enviada: $sent exitosos, $failed fallidos",
    'sent' => $sent,
    'failed' => $failed
]);
?>
