<?php
// ============================================
// TEST NEWSLETTER - Envío de prueba
// ============================================

require_once 'config.php';
require_once 'email-sender.php';

echo "<h1>🧪 Prueba de Newsletter - Aprobación de Post</h1>";

// 1. Verificar suscriptores
echo "<h2>📧 Suscriptores Activos</h2>";
$stmt = $conn->prepare("SELECT email, confirmed, active FROM newsletter_subscribers");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'><strong>❌ ERROR: No hay suscriptores en la base de datos</strong></p>";
    echo "<p>Para probar el newsletter, necesitas al menos un suscriptor confirmado.</p>";
    echo "<p>Ve a <a href='blog.html'>blog.html</a> y suscríbete primero.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th style='padding: 10px;'>Email</th><th style='padding: 10px;'>Confirmado</th><th style='padding: 10px;'>Activo</th></tr>";
    
    $activeCount = 0;
    while ($sub = $result->fetch_assoc()) {
        $confirmed = $sub['confirmed'] == 1 ? '✅' : '❌';
        $active = $sub['active'] == 1 ? '✅' : '❌';
        
        if ($sub['confirmed'] == 1 && $sub['active'] == 1) {
            $activeCount++;
        }
        
        echo "<tr>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($sub['email']) . "</td>";
        echo "<td style='padding: 10px;'>" . $confirmed . "</td>";
        echo "<td style='padding: 10px;'>" . $active . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Total de suscriptores activos y confirmados: $activeCount</strong></p>";
    
    if ($activeCount === 0) {
        echo "<p style='color: red;'><strong>⚠️ No hay suscriptores confirmados y activos</strong></p>";
        echo "<p>Verifica tu email y confirma la suscripción haciendo clic en el enlace que recibiste.</p>";
    }
}

// 2. Verificar posts publicados
echo "<h2>📝 Posts Publicados Recientes</h2>";
$stmt = $conn->prepare("SELECT id, title, slug, status, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'><strong>❌ No hay posts publicados</strong></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th style='padding: 10px;'>ID</th><th style='padding: 10px;'>Título</th><th style='padding: 10px;'>Estado</th><th style='padding: 10px;'>Fecha</th></tr>";
    
    while ($post = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px;'>" . $post['id'] . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($post['title']) . "</td>";
        echo "<td style='padding: 10px;'>" . $post['status'] . "</td>";
        echo "<td style='padding: 10px;'>" . $post['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Enviar email de prueba
echo "<h2>📬 Enviar Email de Prueba</h2>";

if (isset($_POST['send_test'])) {
    $testEmail = $_POST['test_email'] ?? '';
    
    if (empty($testEmail)) {
        echo "<p style='color: red;'>❌ Por favor ingresa un email</p>";
    } else {
        echo "<p>Enviando email de prueba a: <strong>$testEmail</strong></p>";
        
        $testTitle = "🔥 Nuevo Tutorial: Pentesting Avanzado con Metasploit";
        $testExcerpt = "Aprende técnicas avanzadas de pentesting y explotación con Metasploit Framework. Incluye laboratorios prácticos y ejemplos reales.";
        $testUrl = SITE_URL . "/post/pentesting-avanzado-metasploit-123";
        
        $result = sendNewPostNotification($testEmail, $testTitle, $testExcerpt, $testUrl);
        
        if ($result === true || (is_array($result) && $result['success'])) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
            echo "<h3>✅ Email enviado exitosamente!</h3>";
            echo "<p>Revisa la bandeja de entrada de <strong>$testEmail</strong></p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
            echo "<h3>❌ Error al enviar email</h3>";
            echo "<pre>" . print_r($result, true) . "</pre>";
            echo "</div>";
        }
    }
}

?>

<form method="POST" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
    <label for="test_email" style="display: block; margin-bottom: 10px; font-weight: bold;">
        Enviar email de prueba a:
    </label>
    <input 
        type="email" 
        name="test_email" 
        id="test_email" 
        placeholder="tu@email.com"
        required
        style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 300px; font-size: 16px;"
    >
    <button 
        type="submit" 
        name="send_test"
        style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-left: 10px;"
    >
        🚀 Enviar Prueba
    </button>
</form>

<hr style="margin: 40px 0;">

<h2>🔍 Diagnóstico del Sistema</h2>

<div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 20px; margin: 20px 0;">
    <h3>✅ Checklist de Configuración</h3>
    <ul style="list-style: none; padding: 0;">
        <li style="padding: 5px 0;">
            <?php
            $smtp_check = defined('SMTP_HOST') && defined('SMTP_PORT') && defined('SMTP_USER') && defined('SMTP_PASS');
            echo $smtp_check ? '✅' : '❌';
            ?> Configuración SMTP
        </li>
        <li style="padding: 5px 0;">
            <?php
            $subs = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE confirmed = 1 AND active = 1")->fetch_assoc();
            echo $subs['count'] > 0 ? '✅' : '❌';
            ?> Suscriptores activos: <?php echo $subs['count']; ?>
        </li>
        <li style="padding: 5px 0;">
            <?php
            $posts = $conn->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'")->fetch_assoc();
            echo $posts['count'] > 0 ? '✅' : '❌';
            ?> Posts publicados: <?php echo $posts['count']; ?>
        </li>
        <li style="padding: 5px 0;">
            <?php
            echo function_exists('sendNewPostNotification') ? '✅' : '❌';
            ?> Función sendNewPostNotification()
        </li>
    </ul>
</div>

<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
    <h3>💡 Cómo funciona el Newsletter</h3>
    <ol>
        <li>Usuario se suscribe en <code>blog.html</code></li>
        <li>Recibe email de confirmación</li>
        <li>Hace clic en el enlace de confirmación</li>
        <li>Cuando un admin aprueba un post en <code>admin.html</code></li>
        <li>Se ejecuta <code>approvePost()</code> en <code>blog-api.php</code></li>
        <li>Se envía email a todos los suscriptores confirmados y activos</li>
    </ol>
</div>

<p><a href="admin.html" style="color: #007bff;">← Volver al Admin Panel</a></p>
