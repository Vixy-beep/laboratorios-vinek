-- ============================================
-- SISTEMA DE APROBACIÓN DE EDICIONES
-- ============================================
-- Agregar columnas para gestionar ediciones pendientes de posts

ALTER TABLE posts
ADD COLUMN IF NOT EXISTS pending_changes LONGTEXT DEFAULT NULL COMMENT 'JSON con cambios pendientes de aprobación',
ADD COLUMN IF NOT EXISTS edit_status ENUM('live', 'edit_pending', 'edit_rejected') DEFAULT 'live' COMMENT 'Estado de edición del post',
ADD COLUMN IF NOT EXISTS edit_requested_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha de solicitud de edición',
ADD COLUMN IF NOT EXISTS edit_reviewed_by INT NULL DEFAULT NULL COMMENT 'ID del admin que revisó',
ADD COLUMN IF NOT EXISTS edit_reviewed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Fecha de revisión',
ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) DEFAULT NULL COMMENT 'Motivo de rechazo de edición';

-- Índices para mejorar performance
CREATE INDEX IF NOT EXISTS idx_edit_status ON posts(edit_status);
CREATE INDEX IF NOT EXISTS idx_edit_requested_at ON posts(edit_requested_at);

-- Vista para facilitar consultas de ediciones pendientes
CREATE OR REPLACE VIEW pending_edits AS
SELECT 
    p.id,
    p.title,
    p.author_id,
    u.name as author_name,
    u.email as author_email,
    p.pending_changes,
    p.edit_requested_at,
    p.created_at,
    p.category
FROM posts p
INNER JOIN users u ON p.author_id = u.id
WHERE p.edit_status = 'edit_pending'
ORDER BY p.edit_requested_at DESC;

-- Tabla de historial de ediciones (opcional pero recomendado)
CREATE TABLE IF NOT EXISTS edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    editor_id INT NOT NULL,
    reviewer_id INT NULL,
    action ENUM('edit_requested', 'edit_approved', 'edit_rejected') NOT NULL,
    changes_made LONGTEXT NULL COMMENT 'JSON con los cambios realizados',
    reason VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_post_id (post_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
