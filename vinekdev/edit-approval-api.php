<?php
/**
 * VinekSec - API de Gestión de Ediciones
 * Maneja solicitudes de edición de posts con flujo de aprobación
 */

session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'email-sender.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'author';

// Obtener acción
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

/**
 * Solicitar edición de un post publicado
 * Solo el autor puede solicitar ediciones de sus propios posts
 */
if ($action === 'requestEdit') {
    $postId = $input['post_id'] ?? null;
    $changes = $input['changes'] ?? null;
    
    if (!$postId || !$changes) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    try {
        // Verificar que el post existe y pertenece al usuario
        $stmt = $pdo->prepare("SELECT id, author_id, status, edit_status FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post no encontrado']);
            exit;
        }
        
        // Verificar propiedad (admin y super_admin pueden editar cualquier post)
        if ($post['author_id'] != $userId && !in_array($userRole, ['admin', 'super_admin'])) {
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar este post']);
            exit;
        }
        
        // Verificar que el post está publicado
        if ($post['status'] !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Solo se pueden solicitar ediciones de posts publicados']);
            exit;
        }
        
        // Verificar que no hay una solicitud pendiente
        if ($post['edit_status'] === 'edit_pending') {
            echo json_encode(['success' => false, 'message' => 'Ya hay una solicitud de edición pendiente para este post']);
            exit;
        }
        
        // Validar cambios
        $allowedFields = ['title', 'content', 'category', 'featured_image'];
        $validatedChanges = [];
        
        foreach ($changes as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                continue;
            }
            
            // Validaciones básicas
            if ($field === 'title' && (strlen($value) < 5 || strlen($value) > 200)) {
                echo json_encode(['success' => false, 'message' => 'El título debe tener entre 5 y 200 caracteres']);
                exit;
            }
            
            if ($field === 'content' && strlen($value) < 100) {
                echo json_encode(['success' => false, 'message' => 'El contenido debe tener al menos 100 caracteres']);
                exit;
            }
            
            $validatedChanges[$field] = $value;
        }
        
        if (empty($validatedChanges)) {
            echo json_encode(['success' => false, 'message' => 'No se detectaron cambios válidos']);
            exit;
        }
        
        // Si el usuario es admin o super_admin, aplicar cambios directamente
        if (in_array($userRole, ['admin', 'super_admin'])) {
            $updateFields = [];
            $updateValues = [];
            
            foreach ($validatedChanges as $field => $value) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $value;
            }
            
            $updateValues[] = $postId;
            
            $sql = "UPDATE posts SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);
            
            echo json_encode([
                'success' => true,
                'message' => 'Post actualizado directamente (permisos de administrador)',
                'direct_update' => true
            ]);
            exit;
        }
        
        // Para autores: Guardar como solicitud de edición
        $changesJson = json_encode($validatedChanges, JSON_UNESCAPED_UNICODE);
        
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET pending_changes = ?,
                edit_status = 'edit_pending',
                edit_requested_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$changesJson, $postId]);
        
        // Registrar en historial
        $stmt = $pdo->prepare("
            INSERT INTO edit_history (post_id, editor_id, action, changes_made)
            VALUES (?, ?, 'edit_requested', ?)
        ");
        $stmt->execute([$postId, $userId, $changesJson]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud de edición enviada. Un administrador la revisará pronto.'
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en requestEdit: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
    }
    exit;
}

/**
 * Listar solicitudes de edición pendientes
 * Solo para admin y super_admin
 */
if ($action === 'getPendingEdits') {
    if (!in_array($userRole, ['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT 
                p.id,
                p.title,
                p.author_id,
                u.name as author_name,
                u.email as author_email,
                u.avatar as author_avatar,
                p.content as current_content,
                p.category as current_category,
                p.featured_image as current_image,
                p.pending_changes,
                p.edit_requested_at,
                p.created_at
            FROM posts p
            INNER JOIN users u ON p.author_id = u.id
            WHERE p.edit_status = 'edit_pending'
            ORDER BY p.edit_requested_at DESC
        ");
        
        $pendingEdits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar pending_changes
        foreach ($pendingEdits as &$edit) {
            $edit['pending_changes'] = json_decode($edit['pending_changes'], true);
            
            // Calcular tiempo transcurrido
            $requestTime = strtotime($edit['edit_requested_at']);
            $now = time();
            $diff = $now - $requestTime;
            
            if ($diff < 3600) {
                $edit['time_ago'] = floor($diff / 60) . ' minutos';
            } elseif ($diff < 86400) {
                $edit['time_ago'] = floor($diff / 3600) . ' horas';
            } else {
                $edit['time_ago'] = floor($diff / 86400) . ' días';
            }
        }
        
        echo json_encode([
            'success' => true,
            'edits' => $pendingEdits,
            'count' => count($pendingEdits)
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en getPendingEdits: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener solicitudes']);
    }
    exit;
}

/**
 * Aprobar edición
 * Solo para admin y super_admin
 */
if ($action === 'approveEdit') {
    if (!in_array($userRole, ['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $postId = $input['post_id'] ?? null;
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'ID de post requerido']);
        exit;
    }
    
    try {
        // Obtener cambios pendientes
        $stmt = $pdo->prepare("SELECT pending_changes, edit_status FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post no encontrado']);
            exit;
        }
        
        if ($post['edit_status'] !== 'edit_pending') {
            echo json_encode(['success' => false, 'message' => 'No hay ediciones pendientes para este post']);
            exit;
        }
        
        $changes = json_decode($post['pending_changes'], true);
        
        if (!$changes) {
            echo json_encode(['success' => false, 'message' => 'Cambios pendientes inválidos']);
            exit;
        }
        
        // Aplicar cambios
        $pdo->beginTransaction();
        
        $updateFields = [];
        $updateValues = [];
        
        foreach ($changes as $field => $value) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;
        }
        
        $updateValues[] = $userId; // reviewer_id
        $updateValues[] = $postId;
        
        $sql = "UPDATE posts SET 
                " . implode(', ', $updateFields) . ",
                edit_status = 'live',
                pending_changes = NULL,
                edit_reviewed_by = ?,
                edit_reviewed_at = NOW(),
                rejection_reason = NULL,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Registrar en historial
        $stmt = $pdo->prepare("
            INSERT INTO edit_history (post_id, editor_id, reviewer_id, action, changes_made)
            VALUES (?, (SELECT author_id FROM posts WHERE id = ?), ?, 'edit_approved', ?)
        ");
        $stmt->execute([$postId, $postId, $userId, $post['pending_changes']]);
        
        // Obtener información del autor y post para enviar email
        $stmt = $pdo->prepare("
            SELECT p.id, p.title, p.slug, u.email, u.username 
            FROM posts p 
            JOIN users u ON p.author_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$postId]);
        $postInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->commit();
        
        // Enviar email de notificación al autor
        if ($postInfo && $postInfo['email']) {
            $postUrl = SITE_URL . '/post/' . $postInfo['slug'] . '-' . $postInfo['id'];
            sendPostApprovedEmail($postInfo['email'], $postInfo['title'], $postUrl);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Edición aprobada y aplicada correctamente'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en approveEdit: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al aprobar la edición']);
    }
    exit;
}

/**
 * Rechazar edición
 * Solo para admin y super_admin
 */
if ($action === 'rejectEdit') {
    if (!in_array($userRole, ['admin', 'super_admin'])) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $postId = $input['post_id'] ?? null;
    $reason = $input['reason'] ?? 'Sin motivo especificado';
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'ID de post requerido']);
        exit;
    }
    
    try {
        // Verificar que hay ediciones pendientes
        $stmt = $pdo->prepare("SELECT pending_changes, edit_status, author_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post || $post['edit_status'] !== 'edit_pending') {
            echo json_encode(['success' => false, 'message' => 'No hay ediciones pendientes']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Marcar como rechazado
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET edit_status = 'edit_rejected',
                rejection_reason = ?,
                edit_reviewed_by = ?,
                edit_reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $userId, $postId]);
        
        // Registrar en historial
        $stmt = $pdo->prepare("
            INSERT INTO edit_history (post_id, editor_id, reviewer_id, action, changes_made, reason)
            VALUES (?, ?, ?, 'edit_rejected', ?, ?)
        ");
        $stmt->execute([$postId, $post['author_id'], $userId, $post['pending_changes'], $reason]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Edición rechazada'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en rejectEdit: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al rechazar la edición']);
    }
    exit;
}

/**
 * Obtener historial de ediciones de un post
 */
if ($action === 'getEditHistory') {
    $postId = $_GET['post_id'] ?? null;
    
    if (!$postId) {
        echo json_encode(['success' => false, 'message' => 'ID de post requerido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                eh.*,
                u_editor.name as editor_name,
                u_reviewer.name as reviewer_name
            FROM edit_history eh
            LEFT JOIN users u_editor ON eh.editor_id = u_editor.id
            LEFT JOIN users u_reviewer ON eh.reviewer_id = u_reviewer.id
            WHERE eh.post_id = ?
            ORDER BY eh.created_at DESC
        ");
        $stmt->execute([$postId]);
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar changes_made
        foreach ($history as &$item) {
            $item['changes_made'] = json_decode($item['changes_made'], true);
        }
        
        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en getEditHistory: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener historial']);
    }
    exit;
}

// Acción no reconocida
echo json_encode(['success' => false, 'message' => 'Acción no válida']);
