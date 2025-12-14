<?php
// ============================================
// COMMENTS API - Sistema de Comentarios con RBAC
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once 'config.php';

// Rate limiting config
const MAX_COMMENTS_PER_HOUR = 10;
const SPAM_WINDOW_MINUTES = 60;

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Router
switch ($action) {
    case 'create':
        createComment($conn, $data);
        break;
    
    case 'getComments':
        getComments($conn);
        break;
    
    case 'delete':
        deleteComment($conn, $data);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ============================================
// CREAR COMENTARIO
// ============================================
function createComment($conn, $data) {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para comentar']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $postId = $data['post_id'] ?? null;
    $content = $data['content'] ?? '';
    $honeypot = $data['website'] ?? ''; // Campo honeypot para bots
    
    // Validaciones
    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Post ID y contenido requeridos']);
        return;
    }
    
    // Anti-bot: Honeypot
    if (!empty($honeypot)) {
        echo json_encode(['success' => false, 'message' => 'Spam detectado']);
        return;
    }
    
    // Validar longitud
    if (strlen($content) < 3 || strlen($content) > 1000) {
        echo json_encode(['success' => false, 'message' => 'El comentario debe tener entre 3 y 1000 caracteres']);
        return;
    }
    
    // Rate limiting
    if (!checkRateLimit($conn, $userId)) {
        echo json_encode(['success' => false, 'message' => 'Has alcanzado el límite de comentarios por hora. Intenta más tarde.']);
        return;
    }
    
    // Sanitización anti-XSS
    $content = sanitizeContent($content);
    
    // Insertar comentario
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, ?, 'approved')");
    $stmt->bind_param("iis", $postId, $userId, $content);
    
    if ($stmt->execute()) {
        $commentId = $conn->insert_id;
        
        // Obtener datos del comentario recién creado
        $stmt = $conn->prepare("
            SELECT c.*, u.name as author_name, u.avatar, u.role 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $comment = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Comentario publicado',
            'comment' => formatComment($comment)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al publicar comentario']);
    }
}

// ============================================
// OBTENER COMENTARIOS
// ============================================
function getComments($conn) {
    $postId = $_GET['post_id'] ?? null;
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'Post ID requerido']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT c.*, u.name as author_name, u.avatar, u.role 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? AND c.status = 'approved'
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = formatComment($row);
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'count' => count($comments)
    ]);
}

// ============================================
// ELIMINAR COMENTARIO (RBAC)
// ============================================
function deleteComment($conn, $data) {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $commentId = $data['comment_id'] ?? null;
    
    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Comment ID requerido']);
        return;
    }
    
    // Obtener info del comentario y usuario actual
    $stmt = $conn->prepare("
        SELECT c.user_id as comment_author_id, u.role as comment_author_role,
               cu.role as current_user_role
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN users cu ON cu.id = ?
        WHERE c.id = ?
    ");
    $stmt->bind_param("ii", $userId, $commentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    
    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Comentario no encontrado']);
        return;
    }
    
    // RBAC - Reglas de eliminación
    $canDelete = false;
    $currentRole = $info['current_user_role'];
    $commentAuthorId = $info['comment_author_id'];
    $commentAuthorRole = $info['comment_author_role'];
    
    // Usuario: solo sus propios comentarios
    if ($userId == $commentAuthorId) {
        $canDelete = true;
    }
    
    // Moderador: puede eliminar comentarios de usuarios normales
    if ($currentRole === 'moderator' && !in_array($commentAuthorRole, ['admin', 'super_admin', 'moderator'])) {
        $canDelete = true;
    }
    
    // Admin: puede eliminar cualquier comentario excepto de super_admin
    if ($currentRole === 'admin' && $commentAuthorRole !== 'super_admin') {
        $canDelete = true;
    }
    
    // Super Admin: puede eliminar cualquier comentario
    if ($currentRole === 'super_admin') {
        $canDelete = true;
    }
    
    if (!$canDelete) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar este comentario']);
        return;
    }
    
    // Eliminar comentario
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $commentId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comentario eliminado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar comentario']);
    }
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

// Rate limiting
function checkRateLimit($conn, $userId) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $windowStart = date('Y-m-d H:i:s', strtotime('-' . SPAM_WINDOW_MINUTES . ' minutes'));
    
    // Limpiar ventanas expiradas
    $conn->query("DELETE FROM comment_rate_limit WHERE window_start < '$windowStart'");
    
    // Verificar límite por IP
    $stmt = $conn->prepare("SELECT comment_count FROM comment_rate_limit WHERE ip_address = ? AND window_start >= ?");
    $stmt->bind_param("ss", $ip, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['comment_count'] >= MAX_COMMENTS_PER_HOUR) {
            return false;
        }
        
        // Incrementar contador
        $conn->query("UPDATE comment_rate_limit SET comment_count = comment_count + 1, last_comment = NOW() WHERE ip_address = '$ip'");
    } else {
        // Crear nuevo registro
        $stmt = $conn->prepare("INSERT INTO comment_rate_limit (ip_address, user_id, comment_count) VALUES (?, ?, 1)");
        $stmt->bind_param("si", $ip, $userId);
        $stmt->execute();
    }
    
    return true;
}

// Sanitización anti-XSS
function sanitizeContent($content) {
    // Eliminar tags HTML excepto algunos seguros
    $allowedTags = '<b><i><strong><em><code>';
    $content = strip_tags($content, $allowedTags);
    
    // Convertir entidades HTML
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    // Eliminar scripts y eventos
    $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
    $content = preg_replace('/on\w+="[^"]*"/i', '', $content);
    
    return trim($content);
}

// Formatear comentario para respuesta
function formatComment($comment) {
    $avatar = $comment['avatar'] ?: "https://ui-avatars.com/api/?name=" . urlencode($comment['author_name']) . "&background=6366f1&color=fff&size=200";
    
    return [
        'id' => $comment['id'],
        'post_id' => $comment['post_id'],
        'user_id' => $comment['user_id'],
        'author_name' => $comment['author_name'],
        'author_avatar' => $avatar,
        'author_role' => $comment['role'],
        'content' => $comment['content'],
        'created_at' => $comment['created_at'],
        'time_ago' => timeAgo($comment['created_at'])
    ];
}

// Tiempo relativo
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'hace un momento';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' minutos';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'hace ' . floor($diff / 86400) . ' días';
    
    return date('d/m/Y', $timestamp);
}
