<?php
// ============================================
// AUTENTICACIÓN - Backend PHP
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar sesión persistente (30 días)
ini_set('session.cookie_lifetime', 2592000); // 30 días en segundos
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Cambiar de Strict a Lax para mejor compatibilidad

session_start();

// Incluir configuración
require_once 'config.php';

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($action) {
    case 'login':
        login($conn, $data);
        break;
    
    case 'logout':
        logout();
        break;
    
    case 'checkAuth':
        checkAuth($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ============================================
// FUNCIONES
// ============================================

// Login
function login($conn, $data) {
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email y contraseña son requeridos']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, name, email, password, role, avatar FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Verificar contraseña - Soporta tanto MD5 (legacy) como bcrypt
        $password_valid = false;
        
        // Intentar con bcrypt primero (nuevo)
        if (password_verify($password, $user['password'])) {
            $password_valid = true;
        }
        // Si falla, intentar con MD5 (legacy - para compatibilidad con datos iniciales)
        elseif ($user['password'] === md5($password)) {
            $password_valid = true;
            
            // Actualizar a bcrypt para mayor seguridad
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $new_hash, $user['id']);
            $update->execute();
        }
        
        if ($password_valid) {
            // Generar avatar si no existe
            $avatar = $user['avatar'];
            if (empty($avatar)) {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=fff&size=200&bold=true';
            }
            
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $avatar;
            $_SESSION['last_activity'] = time(); // Para tracking
            
            // Extender la cookie de sesión a 30 días
            setcookie(session_name(), session_id(), time() + 2592000, '/', '', true, true);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $avatar
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    }
}

// Logout
function logout() {
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Eliminar la cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/', '', true, true);
    }
    
    // Destruir la sesión
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente']);
}

// Verificar autenticación
function checkAuth($conn) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("SELECT id, name, email, role, avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Generar avatar si no existe
            $avatar = $user['avatar'];
            if (empty($avatar)) {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=fff&size=200&bold=true';
            }
            
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $avatar
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
    }
}

$conn->close();
?>
