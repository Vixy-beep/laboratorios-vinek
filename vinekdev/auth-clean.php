<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once 'config-clean.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

switch ($action) {
    case 'login':
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email y contraseña requeridos']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id, name, email, password, role, avatar FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Generar avatar si no existe
                $avatar = $user['avatar'];
                if (empty($avatar)) {
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=fff&size=200&bold=true';
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = $avatar;
                
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
        break;
    
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
        break;
    
    case 'register':
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        // Validaciones
        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
            exit;
        }
        
        if (strlen($name) < 3) {
            echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 3 caracteres']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email inválido']);
            exit;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }
        
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Este email ya está registrado']);
            exit;
        }
        
        // Crear usuario
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=6366f1&color=fff&size=200&bold=true';
        $role = 'user'; // Rol por defecto para usuarios públicos
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $hashedPassword, $role, $avatar);
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            
            // Auto-login después del registro
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_avatar'] = $avatar;
            
            echo json_encode([
                'success' => true,
                'message' => '¡Cuenta creada exitosamente!',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'avatar' => $avatar
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear la cuenta']);
        }
        break;
    
    case 'checkAuth':
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
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

$conn->close();
?>
