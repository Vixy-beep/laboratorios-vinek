<?php
// ============================================
// VIXY MASTERY API - Sistema de Ranking
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Incluir configuración de base de datos
try {
    require_once 'config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de configuración: ' . $e->getMessage()]);
    exit;
}

// Sistema de puntos por categoría
const POINTS_SYSTEM = [
    'ciberseguridad' => 5,
    'pentesting' => 15,
    'scripts' => 10,
    'tutoriales' => 30,
    'cursos' => 20,  // Bonus por cursos
    'noticias' => 3,
    'herramientas' => 10
];

// Obtener acción
$action = $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

// Router de acciones
switch ($action) {
    case 'getRanking':
        getRanking($conn);
        break;
    
    case 'getUserStats':
        getUserStats($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Obtener ranking de contribuidores
function getRanking($conn) {
    try {
        // Consulta que obtiene usuarios con rol de moderador, admin o super_admin
        // y calcula sus puntos según las categorías de sus posts publicados
        $query = "
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                u.avatar,
                u.created_at as user_since,
                COUNT(p.id) as total_posts,
                COALESCE(SUM(CASE 
                    WHEN LOWER(p.category) = 'ciberseguridad' THEN 5
                    WHEN LOWER(p.category) = 'pentesting' THEN 15
                    WHEN LOWER(p.category) = 'scripts' THEN 10
                    WHEN LOWER(p.category) = 'tutoriales' THEN 30
                    WHEN LOWER(p.category) = 'cursos' THEN 20
                    WHEN LOWER(p.category) = 'noticias' THEN 3
                    WHEN LOWER(p.category) = 'herramientas' THEN 10
                    ELSE 5
                END), 0) as total_points,
                COALESCE(SUM(CASE WHEN LOWER(p.category) = 'ciberseguridad' THEN 1 ELSE 0 END), 0) as posts_ciberseguridad,
                COALESCE(SUM(CASE WHEN LOWER(p.category) = 'pentesting' THEN 1 ELSE 0 END), 0) as posts_pentesting,
                COALESCE(SUM(CASE WHEN LOWER(p.category) = 'scripts' THEN 1 ELSE 0 END), 0) as posts_scripts,
                COALESCE(SUM(CASE WHEN LOWER(p.category) = 'tutoriales' THEN 1 ELSE 0 END), 0) as posts_tutoriales,
                COALESCE(SUM(CASE WHEN LOWER(p.category) = 'cursos' THEN 1 ELSE 0 END), 0) as posts_cursos,
                COALESCE(SUM(p.views), 0) as total_views
            FROM users u
            LEFT JOIN posts p ON u.id = p.author_id AND p.status = 'published'
            WHERE u.role IN ('admin', 'super_admin', 'moderator', 'editor', 'author')
            GROUP BY u.id, u.name, u.email, u.role, u.avatar, u.created_at
            HAVING total_posts > 0
            ORDER BY total_points DESC, total_posts DESC
            LIMIT 50
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }
        
        $ranking = [];
        $position = 1;
        
        while ($row = $result->fetch_assoc()) {
            // Determinar insignia según puntos
            $badge = getBadge($row['total_points']);
            
            $ranking[] = [
                'position' => $position++,
                'user_id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'avatar' => $row['avatar'] ?: "https://ui-avatars.com/api/?name=" . urlencode($row['name']) . "&background=6366f1&color=fff&size=200",
                'total_points' => (int)$row['total_points'],
                'total_posts' => (int)$row['total_posts'],
                'posts_by_category' => [
                    'ciberseguridad' => (int)$row['posts_ciberseguridad'],
                    'pentesting' => (int)$row['posts_pentesting'],
                    'scripts' => (int)$row['posts_scripts'],
                    'tutoriales' => (int)$row['posts_tutoriales'],
                    'cursos' => (int)$row['posts_cursos']
                ],
                'total_views' => (int)$row['total_views'],
                'badge' => $badge,
                'member_since' => $row['user_since']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'ranking' => $ranking,
            'total_contributors' => count($ranking),
            'points_system' => POINTS_SYSTEM
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error al obtener ranking: ' . $e->getMessage()
        ]);
    }
}

// Obtener estadísticas de un usuario específico
function getUserStats($conn) {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                u.avatar,
                COUNT(p.id) as total_posts,
                SUM(CASE 
                    WHEN p.category = 'ciberseguridad' THEN 5
                    WHEN p.category = 'pentesting' THEN 15
                    WHEN p.category = 'scripts' THEN 10
                    WHEN p.category = 'tutoriales' THEN 30
                    WHEN p.category = 'cursos' THEN 20
                    WHEN p.category = 'noticias' THEN 3
                    WHEN p.category = 'herramientas' THEN 10
                    ELSE 5
                END) as total_points,
                SUM(p.views) as total_views
            FROM users u
            LEFT JOIN posts p ON u.id = p.author_id AND p.status = 'published'
            WHERE u.id = ?
            GROUP BY u.id
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            $user['badge'] = getBadge($user['total_points']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Determinar insignia según puntos
function getBadge($points) {
    if ($points >= 500) {
        return [
            'name' => 'Leyenda Cibernética',
            'icon' => '👑',
            'color' => '#ffd700',
            'level' => 'legendary'
        ];
    } elseif ($points >= 300) {
        return [
            'name' => 'Maestro Hacker',
            'icon' => '🎖️',
            'color' => '#c0c0c0',
            'level' => 'master'
        ];
    } elseif ($points >= 150) {
        return [
            'name' => 'Experto en Seguridad',
            'icon' => '🛡️',
            'color' => '#cd7f32',
            'level' => 'expert'
        ];
    } elseif ($points >= 75) {
        return [
            'name' => 'Guerrero Digital',
            'icon' => '⚔️',
            'color' => '#6366f1',
            'level' => 'warrior'
        ];
    } elseif ($points >= 30) {
        return [
            'name' => 'Explorador Cibernético',
            'icon' => '🔍',
            'color' => '#8b5cf6',
            'level' => 'explorer'
        ];
    } else {
        return [
            'name' => 'Hacker Novato',
            'icon' => '🔰',
            'color' => '#10b981',
            'level' => 'novice'
        ];
    }
}

$conn->close();
?>
