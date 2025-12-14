<?php
/**
 * Jorise SaaS API - Sistema de análisis con modelo freemium
 * Endpoints: register, check_credits, analyze, upgrade, get_history
 */

// Suprimir TODOS los errores para asegurar JSON limpio
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier buffer previo
if (ob_get_level()) ob_end_clean();
ob_start();

require_once 'config.php';
require_once 'jorise-engine.php';

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

// Obtener datos desde JSON o form-data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        registerUser();
        break;
    case 'login':
        loginUser();
        break;
    case 'check_credits':
        checkCredits();
        break;
    case 'analyze':
        performAnalysis();
        break;
    case 'get_plans':
        getPlans();
        break;
    case 'get_history':
        getAnalysisHistory();
        break;
    case 'get_stats':
        getUserStats();
        break;
    case 'admin_add_credits':
        adminAddCredits();
        break;
    case 'admin_get_users':
        adminGetUsers();
        break;
    case 'admin_toggle_user':
        adminToggleUser();
        break;
    default:
        sendJSON(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Registrar nuevo usuario (plan Free por defecto)
 */
function registerUser() {
    global $conn, $input;
    
    $email = filter_var($input['email'] ?? $_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? $_POST['password'] ?? '';
    $name = trim($input['name'] ?? $_POST['name'] ?? '');
    
    if (!$email) {
        sendJSON(['success' => false, 'error' => 'Email inválido']);
    }
    
    if (empty($password) || strlen($password) < 6) {
        sendJSON(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
    }
    
    // Verificar si ya existe
    $stmt = $conn->prepare("SELECT id FROM saas_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        sendJSON([
            'success' => false,
            'error' => 'Este email ya está registrado. Inicia sesión con tu contraseña.',
            'already_exists' => true
        ]);
    }
    
    // Hashear contraseña
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Crear nuevo usuario con plan Free
    $stmt = $conn->prepare("INSERT INTO saas_users (email, password, name, plan_id, credits_limit, current_period_start, current_period_end) VALUES (?, ?, ?, 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))");
    $stmt->bind_param("sss", $email, $hashedPassword, $name);
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        sendJSON([
            'success' => true,
            'message' => 'Cuenta creada exitosamente',
            'user_id' => $userId,
            'email' => $email
        ]);
    } else {
        sendJSON(['success' => false, 'error' => 'Error al registrar usuario. Intenta nuevamente.']);
    }
}

/**
 * Login de usuario con validación de contraseña
 */
function loginUser() {
    global $conn, $input;
    
    $email = filter_var($input['email'] ?? $_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? $_POST['password'] ?? '';
    
    if (!$email || empty($password)) {
        sendJSON(['success' => false, 'error' => 'Email y contraseña son requeridos']);
    }
    
    // Buscar usuario
    $stmt = $conn->prepare("SELECT id, email, password, name, active FROM saas_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Email no registrado. Crea una cuenta primero.']);
    }
    
    $user = $result->fetch_assoc();
    
    // Verificar si está activo
    if (!$user['active']) {
        sendJSON(['success' => false, 'error' => 'Tu cuenta ha sido desactivada. Contacta soporte.']);
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        sendJSON(['success' => false, 'error' => 'Contraseña incorrecta. Intenta nuevamente.']);
    }
    
    // Login exitoso
    sendJSON([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user_id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name']
    ]);
}

/**
 * Verificar créditos disponibles
 */
function checkCredits() {
    global $conn;
    
    $email = filter_var($_POST['email'] ?? $_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        sendJSON(['success' => false, 'message' => 'Email inválido']);
    }
    
    $stmt = $conn->prepare("
        SELECT u.id, u.plan_id, u.credits_used, u.credits_limit, u.current_period_end, p.name as plan_name, p.price
        FROM saas_users u
        JOIN saas_plans p ON u.plan_id = p.id
        WHERE u.email = ? AND u.active = TRUE
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'message' => 'Usuario no encontrado']);
    }
    
    $user = $result->fetch_assoc();
    $creditsRemaining = $user['credits_limit'] - $user['credits_used'];
    
    // Verificar si el período mensual expiró (resetear créditos)
    if (strtotime($user['current_period_end']) < time()) {
        $stmt = $conn->prepare("UPDATE saas_users SET credits_used = 0, current_period_start = CURDATE(), current_period_end = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $creditsRemaining = $user['credits_limit'];
    }
    
    sendJSON([
        'success' => true,
        'user_id' => $user['id'],
        'plan' => $user['plan_name'],
        'plan_price' => (float)$user['price'],
        'credits_used' => $user['credits_used'],
        'credits_limit' => $user['credits_limit'],
        'credits_remaining' => $creditsRemaining,
        'period_end' => $user['current_period_end']
    ]);
}

/**
 * Realizar análisis (consumir crédito)
 */
function performAnalysis() {
    global $conn;
    
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $analysisType = $_POST['analysis_type'] ?? 'file'; // file, url, network, sandbox
    $fileName = $_POST['file_name'] ?? null;
    $fileHash = $_POST['file_hash'] ?? null;
    $url = $_POST['url'] ?? null;
    
    if (!$email) {
        sendJSON(['success' => false, 'message' => 'Email inválido']);
    }
    
    // Verificar créditos
    $stmt = $conn->prepare("SELECT id, credits_used, credits_limit FROM saas_users WHERE email = ? AND active = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'message' => 'Usuario no encontrado. Regístrate primero.']);
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['credits_used'] >= $user['credits_limit']) {
        $creditsUsed = $user['credits_used'];
        $creditsLimit = $user['credits_limit'];
        sendJSON([
            'success' => false,
            'message' => "Has usado todos tus créditos ($creditsUsed/$creditsLimit). Actualiza a PRO para continuar analizando.",
            'upgrade_required' => true,
            'credits_info' => [
                'used' => $creditsUsed,
                'limit' => $creditsLimit,
                'remaining' => 0
            ]
        ]);
    }
    
    // ★★★ ANÁLISIS REAL CON MOTOR JORISE ★★★
    $engine = new JoriseEngine();
    $analysisResult = null;
    
    // Determinar tipo de análisis
    if ($analysisType === 'url' && $url) {
        // Validar formato de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            sendJSON(['success' => false, 'message' => 'URL inválida. Usa el formato: https://ejemplo.com']);
        }
        
        // Análisis de URL
        try {
            $analysisResult = $engine->analyzeURL($url);
        } catch (Exception $e) {
            sendJSON([
                'success' => false, 
                'message' => 'Error al analizar la URL. Verifica que el sitio esté accesible.',
                'error_detail' => 'Connection timeout or DNS error'
            ]);
        }
        
    } elseif ($analysisType === 'file' && isset($_FILES['file'])) {
        // Análisis de archivo subido
        $uploadedFile = $_FILES['file'];
        $tempPath = $uploadedFile['tmp_name'];
        $originalName = $uploadedFile['name'];
        
        if (file_exists($tempPath)) {
            $analysisResult = $engine->analyzeFile($tempPath, $originalName);
            $fileHash = $analysisResult['hashes']['sha256'];
        } else {
            sendJSON(['success' => false, 'message' => 'Archivo no encontrado']);
        }
        
    } else {
        sendJSON(['success' => false, 'message' => 'Tipo de análisis o datos inválidos']);
    }
    
    if (!$analysisResult) {
        sendJSON(['success' => false, 'message' => 'Error al realizar análisis']);
    }
    
    // Guardar en historial
    $resultJson = json_encode($analysisResult);
    $threatLevel = $analysisResult['threat_level'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO saas_analyses (user_id, analysis_type, file_name, file_hash, url, result, threat_level, credits_consumed) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("issssss", $user['id'], $analysisType, $fileName, $fileHash, $url, $resultJson, $threatLevel);
    $stmt->execute();
    
    // Decrementar crédito
    $stmt = $conn->prepare("UPDATE saas_users SET credits_used = credits_used + 1 WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    
    sendJSON([
        'success' => true,
        'message' => 'Análisis completado',
        'result' => $analysisResult,
        'credits_remaining' => $user['credits_limit'] - $user['credits_used'] - 1
    ]);
}

/**
 * Obtener planes disponibles
 */
function getPlans() {
    global $conn;
    
    $result = $conn->query("SELECT id, name, price, credits_per_month, features FROM saas_plans WHERE active = TRUE ORDER BY price ASC");
    
    $plans = [];
    while ($row = $result->fetch_assoc()) {
        $row['features'] = json_decode($row['features'], true);
        $row['price'] = (float)$row['price'];
        $plans[] = $row;
    }
    
    sendJSON(['success' => true, 'plans' => $plans]);
}

/**
 * Obtener historial de análisis del usuario
 */
function getAnalysisHistory() {
    global $conn;
    
    $email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $limit = (int)($_GET['limit'] ?? 20);
    
    if (!$email) {
        sendJSON(['success' => false, 'message' => 'Email inválido']);
    }
    
    $stmt = $conn->prepare("
        SELECT h.id, h.analysis_type, h.file_name, h.url, h.threat_level, h.created_at, h.result
        FROM saas_analyses h
        JOIN saas_users u ON h.user_id = u.id
        WHERE u.email = ?
        ORDER BY h.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("si", $email, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $row['result'] = json_decode($row['result'], true);
        $history[] = $row;
    }
    
    sendJSON(['success' => true, 'history' => $history]);
}

/**
 * Obtener estadísticas del usuario
 */
function getUserStats() {
    global $conn;
    
    $email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        sendJSON(['success' => false, 'message' => 'Email inválido']);
    }
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_analyses,
            SUM(CASE WHEN threat_level = 'clean' THEN 1 ELSE 0 END) as clean,
            SUM(CASE WHEN threat_level = 'suspicious' THEN 1 ELSE 0 END) as suspicious,
            SUM(CASE WHEN threat_level = 'malicious' THEN 1 ELSE 0 END) as malicious
        FROM saas_analyses h
        JOIN saas_users u ON h.user_id = u.id
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    sendJSON(['success' => true, 'stats' => $stats]);
}

/**
 * ========================================
 * FUNCIONES DE SUPER ADMIN
 * ========================================
 */

/**
 * Verificar si usuario es admin
 */
function isAdmin($email) {
    // Lista de emails de super admins
    $admins = [
        'info@vineksec.online',
        'admin@vineksec.online',
        'tu-email@ejemplo.com' // Agregar tu email aquí
    ];
    
    return in_array(strtolower($email), array_map('strtolower', $admins));
}

/**
 * Agregar créditos extra a un usuario (SOLO ADMIN)
 */
function adminAddCredits() {
    global $conn;
    
    $adminEmail = filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $targetEmail = filter_var($_POST['target_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $creditsToAdd = (int)($_POST['credits'] ?? 0);
    
    if (!$adminEmail || !isAdmin($adminEmail)) {
        sendJSON(['success' => false, 'message' => 'Acceso denegado. Solo administradores.']);
    }
    
    if (!$targetEmail) {
        sendJSON(['success' => false, 'message' => 'Email de usuario objetivo inválido']);
    }
    
    if ($creditsToAdd <= 0 || $creditsToAdd > 1000) {
        sendJSON(['success' => false, 'message' => 'Cantidad de créditos inválida (1-1000)']);
    }
    
    // Obtener usuario objetivo
    $stmt = $conn->prepare("SELECT id, email, credits_limit, plan_id FROM saas_users WHERE email = ?");
    $stmt->bind_param("s", $targetEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'message' => 'Usuario no encontrado']);
    }
    
    $user = $result->fetch_assoc();
    $newLimit = $user['credits_limit'] + $creditsToAdd;
    
    // Actualizar límite de créditos
    $stmt = $conn->prepare("UPDATE saas_users SET credits_limit = ? WHERE id = ?");
    $stmt->bind_param("ii", $newLimit, $user['id']);
    $stmt->execute();
    
    sendJSON([
        'success' => true,
        'message' => "Se agregaron $creditsToAdd créditos a {$user['email']}",
        'user_email' => $user['email'],
        'credits_added' => $creditsToAdd,
        'old_limit' => $user['credits_limit'],
        'new_limit' => $newLimit
    ]);
}

/**
 * Obtener lista de todos los usuarios (SOLO ADMIN)
 */
function adminGetUsers() {
    global $conn;
    
    $adminEmail = filter_var($_GET['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (!$adminEmail || !isAdmin($adminEmail)) {
        sendJSON(['success' => false, 'message' => 'Acceso denegado']);
    }
    
    $query = "
        SELECT 
            u.id,
            u.email,
            u.name,
            u.created_at,
            u.active,
            u.credits_used,
            u.credits_limit,
            u.current_period_end,
            p.name as plan_name,
            p.price as plan_price,
            (SELECT COUNT(*) FROM saas_analyses WHERE user_id = u.id) as total_analyses
        FROM saas_users u
        JOIN saas_plans p ON u.plan_id = p.id
        ORDER BY u.created_at DESC
    ";
    
    $result = $conn->query($query);
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['price'] = (float)$row['plan_price'];
        $users[] = $row;
    }
    
    sendJSON([
        'success' => true,
        'total_users' => count($users),
        'users' => $users
    ]);
}

/**
 * Activar/desactivar usuario (SOLO ADMIN)
 */
function adminToggleUser() {
    global $conn;
    
    $adminEmail = filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $targetEmail = filter_var($_POST['target_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $active = isset($_POST['active']) ? (bool)$_POST['active'] : null;
    
    if (!$adminEmail || !isAdmin($adminEmail)) {
        sendJSON(['success' => false, 'message' => 'Acceso denegado']);
    }
    
    if (!$targetEmail || $active === null) {
        sendJSON(['success' => false, 'message' => 'Parámetros inválidos']);
    }
    
    // Obtener usuario
    $stmt = $conn->prepare("SELECT id, email FROM saas_users WHERE email = ?");
    $stmt->bind_param("s", $targetEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'message' => 'Usuario no encontrado']);
    }
    
    $user = $result->fetch_assoc();
    
    // Actualizar estado
    $activeInt = $active ? 1 : 0;
    $stmt = $conn->prepare("UPDATE saas_users SET active = ? WHERE id = ?");
    $stmt->bind_param("ii", $activeInt, $user['id']);
    $stmt->execute();
    
    sendJSON([
        'success' => true,
        'message' => $user['email'] . ($active ? ' activado' : ' desactivado'),
        'user_email' => $user['email'],
        'new_status' => $active
    ]);
}
?>
