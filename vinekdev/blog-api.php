<?php
// ============================================
// BLOG API - Backend PHP
// ============================================

// Activar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción
ini_set('log_errors', 1);

require_once 'email-sender.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Incluir configuración de base de datos
try {
    require_once 'config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de configuración: ' . $e->getMessage()]);
    exit;
}

// Obtener datos del request (JSON o POST normal)
$requestData = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $requestData = json_decode(file_get_contents('php://input'), true);
} else {
    $requestData = $_POST;
}

// Obtener acción (primero de GET, luego de request data)
$action = $_GET['action'] ?? ($requestData['action'] ?? null);

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Rutas públicas (no requieren autenticación)
$publicActions = ['getPosts', 'getPost', 'incrementViews'];

// Acciones que requieren permisos de Admin/Super Admin
$adminActions = ['getPendingPosts', 'approvePost', 'rejectPost', 'getUsers', 'createUser', 'updateUser', 'deleteUser', 'getStats'];

// Verificar autenticación para acciones privadas
if (!in_array($action, $publicActions)) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    
    // Verificar permisos de admin para acciones administrativas
    if (in_array($action, $adminActions)) {
        $stmtCheckRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmtCheckRole->bind_param("i", $_SESSION['user_id']);
        $stmtCheckRole->execute();
        $resultCheckRole = $stmtCheckRole->get_result();
        $userCheck = $resultCheckRole->fetch_assoc();
        
        if (!$userCheck || ($userCheck['role'] !== 'admin' && $userCheck['role'] !== 'super_admin')) {
            echo json_encode(['success' => false, 'message' => '⛔ Acceso Denegado: No tienes permisos de administrador']);
            exit;
        }
    }
}

// Router de acciones
switch ($action) {
    case 'getPosts':
        getPosts($conn);
        break;
    
    case 'getPost':
        getPost($conn);
        break;
    
    case 'createPost':
        createPost($conn);
        break;
    
    case 'updatePost':
        updatePost($conn);
        break;
    
    case 'deletePost':
        deletePost($conn);
        break;
    
    case 'getStats':
        getStats($conn);
        break;
    
    case 'getUsers':
        getUsers($conn);
        break;
    
    case 'createUser':
        createUser($conn);
        break;
    
    case 'updateUser':
        updateUser($conn);
        break;
    
    case 'updateUserAvatar':
        updateUserAvatar($conn);
        break;
    
    case 'deleteUser':
        deleteUser($conn);
        break;
    
    case 'getPendingPosts':
        getPendingPosts($conn);
        break;
    
    case 'approvePost':
        approvePost($conn);
        break;
    
    case 'rejectPost':
        rejectPost($conn);
        break;
    
    case 'incrementViews':
        incrementViews($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ============================================
// FUNCIONES
// ============================================

// Generar slug amigable para URLs
function generateSlug($text) {
    // Convertir a minúsculas
    $text = mb_strtolower($text, 'UTF-8');
    
    // Reemplazar caracteres especiales del español
    $text = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
        ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
        $text
    );
    
    // Eliminar caracteres no alfanuméricos excepto espacios y guiones
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    
    // Reemplazar espacios y múltiples guiones con un solo guión
    $text = preg_replace('/[\s-]+/', '-', $text);
    
    // Eliminar guiones al inicio y final
    $text = trim($text, '-');
    
    // Limitar longitud del slug
    $text = substr($text, 0, 100);
    
    return $text;
}

// Obtener posts
function getPosts($conn) {
    $category = $_GET['category'] ?? 'all';
    $limit = $_GET['limit'] ?? 100;
    
    $sql = "SELECT p.*, u.name as author, u.email as author_email, u.role as author_role, u.avatar as author_avatar
            FROM posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.status = 'published'";
    
    if ($category !== 'all') {
        $sql .= " AND p.category = ?";
        $params = [$category];
        $types = "s";
    } else {
        $params = [];
        $types = "";
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ?";
    $params[] = (int)$limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $conn->error]);
        return;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar: ' . $stmt->error]);
        return;
    }
    
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        // Agregar slug para URLs amigables
        $row['slug'] = generateSlug($row['title']);
        $posts[] = $row;
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);
}

// Obtener un post específico
function getPost($conn) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT p.*, u.name as author, u.email as author_email, u.role as author_role, u.avatar as author_avatar
                            FROM posts p 
                            LEFT JOIN users u ON p.author_id = u.id 
                            WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($post = $result->fetch_assoc()) {
        // Agregar slug para URLs amigables
        $post['slug'] = generateSlug($post['title']);
        echo json_encode(['success' => true, 'post' => $post]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Post no encontrado']);
    }
}

// Crear post
function createPost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $title = $data['title'] ?? '';
    $slug = $data['slug'] ?? strtolower(str_replace(' ', '-', $title));
    $category = $data['category'] ?? '';
    $excerpt = $data['excerpt'] ?? '';
    $content = $data['content'] ?? '';
    $image = $data['image'] ?? $data['featured_image'] ?? '';
    $reading_time = $data['reading_time'] ?? 5;
    $author_id = $_SESSION['user_id'];
    
    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Título y contenido son requeridos']);
        return;
    }
    
    // Obtener el rol del usuario
    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->bind_param("i", $author_id);
    $stmtRole->execute();
    $resultRole = $stmtRole->get_result();
    $user = $resultRole->fetch_assoc();
    $userRole = $user['role'] ?? 'author';
    
    // Determinar el estado del post según el rol
    // Admin y super_admin publican directamente
    // Author, editor, moderator crean posts en estado "pending" para aprobación
    $postStatus = ($userRole === 'super_admin' || $userRole === 'admin') ? 'published' : 'pending';
    
    $stmt = $conn->prepare("INSERT INTO posts (author_id, title, slug, category, excerpt, content, featured_image, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $author_id, $title, $slug, $category, $excerpt, $content, $image, $postStatus);
    
    if ($stmt->execute()) {
        $message = $postStatus === 'pending' ? 'Post creado y en espera de aprobación' : 'Post creado y publicado exitosamente';
        echo json_encode(['success' => true, 'message' => $message, 'id' => $conn->insert_id, 'status' => $postStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear post: ' . $stmt->error]);
    }
}

// Actualizar post
function updatePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $title = $data['title'] ?? '';
    $slug = $data['slug'] ?? '';
    $category = $data['category'] ?? '';
    $excerpt = $data['excerpt'] ?? '';
    $content = $data['content'] ?? '';
    $featured_image = $data['image'] ?? $data['featured_image'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE posts 
                            SET title = ?, slug = ?, category = ?, excerpt = ?, content = ?, featured_image = ? 
                            WHERE id = ?");
    $stmt->bind_param("ssssssi", $title, $slug, $category, $excerpt, $content, $featured_image, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Post actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
    }
}

// Eliminar post
function deletePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Post eliminado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
    }
}

// Obtener estadísticas
function getStats($conn) {
    // Total posts
    $result = $conn->query("SELECT COUNT(*) as total FROM posts");
    $total_posts = $result->fetch_assoc()['total'];
    
    // Total vistas
    $result = $conn->query("SELECT SUM(views) as total FROM posts");
    $total_views = $result->fetch_assoc()['total'] ?? 0;
    
    // Total usuarios
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_users = $result->fetch_assoc()['total'];
    
    // Posts este mes
    $result = $conn->query("SELECT COUNT(*) as total FROM posts 
                            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $posts_this_month = $result->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_posts' => $total_posts,
            'total_views' => $total_views,
            'total_users' => $total_users,
            'posts_this_month' => $posts_this_month
        ]
    ]);
}

// Obtener usuarios
function getUsers($conn) {
    $result = $conn->query("SELECT id, name, email, role, avatar, created_at FROM users ORDER BY created_at DESC");
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
}

// Crear usuario
function createUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'author';
    $avatar = $data['avatar'] ?? null;
    
    // Si no hay avatar, generar uno con UI Avatars
    if (empty($avatar)) {
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff&size=200&bold=true';
    }
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        return;
    }
    
    // Hash de contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $password_hash, $role, $avatar);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Usuario creado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear usuario: ' . $stmt->error]);
    }
}

// Actualizar usuario
function updateUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $role = $data['role'] ?? '';
    $password = $data['password'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    if (empty($name) || empty($email) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Nombre, email y rol son requeridos']);
        return;
    }
    
    // Si se proporciona contraseña, actualizarla también
    if ($password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $password_hash, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $stmt->error]);
    }
}

// Eliminar usuario
function deleteUser($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    // No permitir eliminar super admin
    $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result();
    $user = $result->fetch_assoc();
    
    if ($user['role'] === 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar super admin']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
    }
}

// Actualizar avatar del usuario actual
function updateUserAvatar($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $avatar_url = $data['avatar'] ?? '';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    if (empty($avatar_url)) {
        echo json_encode(['success' => false, 'message' => 'URL de avatar requerida']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $avatar_url, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['user_avatar'] = $avatar_url;
        echo json_encode(['success' => true, 'message' => 'Avatar actualizado exitosamente', 'avatar' => $avatar_url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar avatar: ' . $stmt->error]);
    }
}

// Obtener posts pendientes (solo para admin/super_admin)
function getPendingPosts($conn) {
    // Verificar que el usuario sea admin o super_admin
    $user_id = $_SESSION['user_id'];
    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->bind_param("i", $user_id);
    $stmtRole->execute();
    $resultRole = $stmtRole->get_result();
    $user = $resultRole->fetch_assoc();
    $userRole = $user['role'] ?? '';
    
    if ($userRole !== 'admin' && $userRole !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT p.*, u.name as author, u.email as author_email, u.role as author_role, u.avatar as author_avatar
                            FROM posts p 
                            LEFT JOIN users u ON p.author_id = u.id 
                            WHERE p.status = 'pending'
                            ORDER BY p.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);
}

// Aprobar post (solo admin/super_admin)
function approvePost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $post_id = $data['id'] ?? null;
    
    if (!$post_id) {
        echo json_encode(['success' => false, 'message' => 'ID de post requerido']);
        return;
    }
    
    // Verificar que el usuario sea admin o super_admin
    $user_id = $_SESSION['user_id'];
    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->bind_param("i", $user_id);
    $stmtRole->execute();
    $resultRole = $stmtRole->get_result();
    $user = $resultRole->fetch_assoc();
    $userRole = $user['role'] ?? '';
    
    if ($userRole !== 'admin' && $userRole !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE posts SET status = 'published' WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    
    if ($stmt->execute()) {
        // Obtener información del post para el email
        $stmtPost = $conn->prepare("SELECT id, title, excerpt, slug, category, image FROM posts WHERE id = ?");
        $stmtPost->bind_param("i", $post_id);
        $stmtPost->execute();
        $postResult = $stmtPost->get_result();
        $postData = $postResult->fetch_assoc();
        
        if ($postData) {
            // Obtener todos los suscriptores activos y confirmados
            $stmtSubs = $conn->prepare("SELECT email FROM newsletter_subscribers WHERE confirmed = 1 AND active = 1");
            $stmtSubs->execute();
            $subsResult = $stmtSubs->get_result();
            
            $postUrl = SITE_URL . '/post/' . $postData['slug'] . '-' . $postData['id'];
            
            // Enviar email a cada suscriptor (en segundo plano)
            while ($subscriber = $subsResult->fetch_assoc()) {
                // Enviar de forma asíncrona para no bloquear la respuesta
                sendNewPostNotification($subscriber['email'], $postData['title'], $postData['excerpt'], $postUrl);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Post aprobado, publicado y notificado a suscriptores']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al aprobar post: ' . $stmt->error]);
    }
}

// Rechazar post (solo admin/super_admin)
function rejectPost($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $post_id = $data['id'] ?? null;
    $reason = $data['reason'] ?? '';
    
    if (!$post_id) {
        echo json_encode(['success' => false, 'message' => 'ID de post requerido']);
        return;
    }
    
    // Verificar que el usuario sea admin o super_admin
    $user_id = $_SESSION['user_id'];
    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->bind_param("i", $user_id);
    $stmtRole->execute();
    $resultRole = $stmtRole->get_result();
    $user = $resultRole->fetch_assoc();
    $userRole = $user['role'] ?? '';
    
    if ($userRole !== 'admin' && $userRole !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        return;
    }
    
    // Cambiar estado a draft o eliminarlo según preferencia
    $stmt = $conn->prepare("UPDATE posts SET status = 'draft' WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Post rechazado', 'reason' => $reason]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al rechazar post: ' . $stmt->error]);
    }
}

// Incrementar vistas
function incrementViews($conn) {
    $id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false]);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

$conn->close();
?>
