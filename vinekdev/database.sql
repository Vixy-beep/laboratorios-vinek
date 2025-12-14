-- ============================================
-- BASE DE DATOS VINEKDEV BLOG - HOSTINGER
-- ============================================

-- IMPORTANTE: Este SQL es para Hostinger
-- La base de datos u185516159_vinekdev_blog debe existir previamente
-- Créala desde el panel de Hostinger antes de importar este archivo

-- Usar la base de datos de Hostinger
USE u185516159_vinekdev_blog;

-- ============================================
-- TABLA: users
-- ============================================
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'author') DEFAULT 'author',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA: posts
-- ============================================
CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content TEXT NOT NULL,
    featured_image VARCHAR(500),
    category VARCHAR(100),
    author_id INT NOT NULL,
    views INT DEFAULT 0,
    status ENUM('draft', 'published') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_author (author_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATOS INICIALES
-- ============================================

-- Usuario administrador principal
-- Email: admin@vinekdev.com
-- Password: Admin123!
INSERT INTO users (email, password, name, role) VALUES 
('admin@vinekdev.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin VinekDev', 'super_admin');

-- Usuario autor de ejemplo
-- Email: autor@vinekdev.com  
-- Password: Autor123!
INSERT INTO users (email, password, name, role) VALUES 
('autor@vinekdev.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Autor VinekDev', 'author');

-- Posts de ejemplo
INSERT INTO posts (title, slug, excerpt, content, featured_image, category, author_id, status, views) VALUES
(
    'Introducción al Pentesting: Guía Completa 2024',
    'introduccion-pentesting-2024',
    'Descubre las metodologías, herramientas y técnicas esenciales del pentesting moderno. Aprende a realizar pruebas de penetración de manera ética y profesional.',
    '<h2>¿Qué es el Pentesting?</h2>
    <p>El Penetration Testing o Pentesting es una práctica de ciberseguridad que simula ataques reales a sistemas informáticos para identificar vulnerabilidades antes de que los atacantes maliciosos las exploten.</p>
    
    <h3>Fases del Pentesting</h3>
    <ol>
        <li><strong>Reconocimiento:</strong> Recopilación de información sobre el objetivo</li>
        <li><strong>Escaneo:</strong> Identificación de puertos, servicios y vulnerabilidades</li>
        <li><strong>Explotación:</strong> Aprovechamiento de las vulnerabilidades encontradas</li>
        <li><strong>Post-explotación:</strong> Mantener acceso y escalar privilegios</li>
        <li><strong>Reporte:</strong> Documentación detallada de hallazgos y recomendaciones</li>
    </ol>
    
    <h3>Herramientas Esenciales</h3>
    <ul>
        <li><strong>Nmap</strong> - Escaneo de redes y detección de servicios</li>
        <li><strong>Burp Suite</strong> - Testing de aplicaciones web</li>
        <li><strong>Metasploit</strong> - Framework de explotación</li>
        <li><strong>Wireshark</strong> - Análisis de tráfico de red</li>
        <li><strong>Nikto</strong> - Escáner de vulnerabilidades web</li>
    </ul>
    
    <h3>Certificaciones Recomendadas</h3>
    <p>Para profesionalizarte en pentesting, considera obtener certificaciones como:</p>
    <ul>
        <li>CEH (Certified Ethical Hacker)</li>
        <li>OSCP (Offensive Security Certified Professional)</li>
        <li>GPEN (GIAC Penetration Tester)</li>
    </ul>',
    'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1200&q=80',
    'Pentesting',
    1,
    'published',
    156
),

(
    'WAF: La Primera Línea de Defensa en Ciberseguridad',
    'waf-primera-linea-defensa',
    'Aprende cómo un Web Application Firewall protege tus aplicaciones de amenazas críticas como SQL Injection, XSS y ataques DDoS.',
    '<h2>¿Qué es un WAF?</h2>
    <p>Un Web Application Firewall (WAF) es una solución de seguridad que monitorea, filtra y bloquea el tráfico HTTP/HTTPS malicioso entre una aplicación web y el Internet.</p>
    
    <h3>Tipos de WAF</h3>
    <ul>
        <li><strong>Network-based WAF:</strong> Implementado en hardware, ofrece baja latencia</li>
        <li><strong>Host-based WAF:</strong> Integrado en la aplicación, altamente personalizable</li>
        <li><strong>Cloud-based WAF:</strong> Servicio administrado, fácil de implementar y escalar</li>
    </ul>
    
    <h3>Protección OWASP Top 10</h3>
    <p>Los WAF modernos protegen contra las amenazas más críticas:</p>
    <ol>
        <li>SQL Injection</li>
        <li>Cross-Site Scripting (XSS)</li>
        <li>Cross-Site Request Forgery (CSRF)</li>
        <li>Remote File Inclusion (RFI)</li>
        <li>Local File Inclusion (LFI)</li>
        <li>DDoS attacks</li>
    </ol>
    
    <h3>Mejores Prácticas</h3>
    <ul>
        <li>Actualizar reglas regularmente</li>
        <li>Monitorear logs y alertas</li>
        <li>Combinar con otras capas de seguridad</li>
        <li>Realizar pruebas de penetración periódicas</li>
    </ul>',
    'https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=1200&q=80',
    'Ciberseguridad',
    1,
    'published',
    243
),

(
    'Curso: Fundamentos de Forense Digital',
    'curso-forense-digital-fundamentos',
    'Programa completo de 8 semanas para dominar las técnicas de investigación forense digital. Aprende a recolectar, preservar y analizar evidencia digital.',
    '<h2>Programa del Curso</h2>
    
    <h3>Módulo 1: Introducción al DFIR</h3>
    <p>Fundamentos de Digital Forensics and Incident Response:</p>
    <ul>
        <li>Conceptos básicos de forense digital</li>
        <li>Cadena de custodia</li>
        <li>Metodologías de investigación</li>
        <li>Marco legal y ético</li>
    </ul>
    
    <h3>Módulo 2: Adquisición de Evidencia</h3>
    <p>Técnicas profesionales de recolección:</p>
    <ul>
        <li>Disk imaging con FTK Imager</li>
        <li>Memory acquisition</li>
        <li>Preservación de evidencia volátil</li>
        <li>Documentación forense</li>
    </ul>
    
    <h3>Módulo 3: Análisis de Sistemas</h3>
    <p>Investigación profunda de sistemas operativos:</p>
    <ul>
        <li>Windows Registry forensics</li>
        <li>Linux forensics y análisis de logs</li>
        <li>Análisis de artefactos del sistema</li>
        <li>Timeline analysis</li>
    </ul>
    
    <h3>Módulo 4: Network Forensics</h3>
    <p>Análisis de tráfico y detección de intrusiones:</p>
    <ul>
        <li>Wireshark y análisis de PCAP</li>
        <li>Log analysis (Firewall, IDS/IPS)</li>
        <li>Detección de malware en red</li>
        <li>Incident response procedures</li>
    </ul>
    
    <div style="background: rgba(99,102,241,0.1); padding: 1.5rem; border-left: 4px solid #6366f1; margin: 2rem 0;">
        <p><strong>📅 Duración:</strong> 8 semanas</p>
        <p><strong>🎓 Certificación:</strong> Incluida al completar el curso</p>
        <p><strong>💻 Modalidad:</strong> 100% online con labs prácticos</p>
        <p><strong>🔧 Herramientas:</strong> FTK, Autopsy, Volatility, Wireshark</p>
    </div>',
    'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
    'Cursos',
    1,
    'published',
    89
),

(
    'Tutorial: Configuración de Laboratorio de Pentesting',
    'tutorial-laboratorio-pentesting',
    'Guía paso a paso para crear tu propio laboratorio de pentesting usando VirtualBox, Kali Linux y máquinas vulnerables.',
    '<h2>Requisitos del Sistema</h2>
    <p>Antes de comenzar, asegúrate de tener:</p>
    <ul>
        <li>PC con mínimo 8GB RAM (16GB recomendado)</li>
        <li>50GB de espacio en disco</li>
        <li>VirtualBox instalado</li>
        <li>Conexión a Internet para descargas</li>
    </ul>
    
    <h3>Paso 1: Instalar VirtualBox</h3>
    <p>Descarga e instala VirtualBox desde el sitio oficial. Es gratuito y compatible con Windows, macOS y Linux.</p>
    
    <h3>Paso 2: Descargar Kali Linux</h3>
    <p>Obtén la imagen de VirtualBox de Kali Linux desde kali.org. Viene preconfigurada con todas las herramientas necesarias.</p>
    
    <h3>Paso 3: Configurar Red Aislada</h3>
    <p>Crea una red interna en VirtualBox para aislar tus máquinas de prueba:</p>
    <pre><code>- Red NAT para Internet
- Red Interna para laboratorio
- Host-Only para acceso desde el host</code></pre>
    
    <h3>Paso 4: Descargar Máquinas Vulnerables</h3>
    <p>Plataformas recomendadas:</p>
    <ul>
        <li><strong>VulnHub:</strong> Máquinas gratuitas para práctica</li>
        <li><strong>HackTheBox:</strong> Laboratorios online</li>
        <li><strong>TryHackMe:</strong> Rutas de aprendizaje guiadas</li>
    </ul>
    
    <h3>Buenas Prácticas</h3>
    <ul>
        <li>Nunca uses herramientas de pentesting en sistemas sin autorización</li>
        <li>Mantén tu laboratorio aislado de producción</li>
        <li>Documenta todos tus hallazgos</li>
        <li>Practica en entornos legales y éticos</li>
    </ul>',
    'https://images.unsplash.com/photo-1629654297299-c8506221ca97?auto=format&fit=crop&w=1200&q=80',
    'Tutoriales',
    2,
    'published',
    127
);

-- ============================================
-- CONFIRMACIÓN
-- ============================================
SELECT '✓ Base de datos creada exitosamente!' as status;
SELECT CONCAT('✓ ', COUNT(*), ' usuarios creados') as users FROM users;
SELECT CONCAT('✓ ', COUNT(*), ' posts de ejemplo creados') as posts FROM posts;