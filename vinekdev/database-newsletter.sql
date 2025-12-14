-- ============================================
-- SISTEMA DE NEWSLETTER - VinekSec
-- ============================================

-- Tabla de suscriptores del newsletter
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(64) NOT NULL,
    confirmed BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    unsubscribed_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_active (active),
    INDEX idx_confirmed (confirmed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar datos de ejemplo (opcional)
-- INSERT INTO newsletter_subscribers (email, token, confirmed, active) VALUES 
-- ('test@example.com', 'test-token-123', TRUE, TRUE);
