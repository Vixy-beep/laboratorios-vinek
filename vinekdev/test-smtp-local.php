<?php
/**
 * TEST LOCAL DE SMTP - Prueba de conexión
 * Ejecuta: php test-smtp-local.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST DE CONEXIÓN SMTP LOCAL ===\n\n";

// Configuración SMTP
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 587;
$smtp_user = 'info@vineksec.online';
$smtp_pass = 'M001ses@moises221211J0rd1@@22';
$to_email = 'jordano-252@hotmail.com';

echo "📋 Configuración:\n";
echo "   Host: $smtp_host\n";
echo "   Port: $smtp_port\n";
echo "   User: $smtp_user\n";
echo "   Pass: " . str_repeat('*', strlen($smtp_pass) - 4) . substr($smtp_pass, -4) . "\n";
echo "   Para: $to_email\n\n";

echo "🔌 Paso 1: Probando conexión al servidor SMTP...\n";
$socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);

if (!$socket) {
    die("❌ ERROR: No se pudo conectar a $smtp_host:$smtp_port\n   Error: $errstr ($errno)\n   Posible causa: Firewall bloqueando puerto 587 o servidor caído\n");
}

echo "✅ Conexión establecida correctamente\n\n";

// Leer respuesta inicial
$response = fgets($socket, 515);
echo "📥 Servidor dice: " . trim($response) . "\n\n";

// EHLO
echo "📤 Enviando EHLO...\n";
fputs($socket, "EHLO localhost\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

// Leer todas las capacidades
while ($line = fgets($socket, 515)) {
    echo "   " . trim($line) . "\n";
    if (strpos($line, ' ') === 0 || strpos($line, '-') === false) {
        break;
    }
}

// STARTTLS
echo "\n🔒 Iniciando TLS...\n";
fputs($socket, "STARTTLS\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

if (strpos($response, '220') === false) {
    die("❌ ERROR: El servidor no soporta STARTTLS\n");
}

// Habilitar encriptación TLS
if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    die("❌ ERROR: No se pudo establecer TLS\n");
}

echo "✅ TLS establecido\n\n";

// EHLO después de TLS
echo "📤 Enviando EHLO después de TLS...\n";
fputs($socket, "EHLO localhost\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

while ($line = fgets($socket, 515)) {
    echo "   " . trim($line) . "\n";
    if (strpos($line, ' ') === 0 || strpos($line, '-') === false) {
        break;
    }
}

// AUTH LOGIN
echo "\n🔐 Autenticando...\n";
fputs($socket, "AUTH LOGIN\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

// Enviar username
fputs($socket, base64_encode($smtp_user) . "\r\n");
$response = fgets($socket, 515);
echo "📥 Username: " . trim($response) . "\n";

// Enviar password
fputs($socket, base64_encode($smtp_pass) . "\r\n");
$response = fgets($socket, 515);
echo "📥 Password: " . trim($response) . "\n";

if (strpos($response, '235') === false) {
    die("❌ ERROR: Autenticación fallida. Verifica usuario y contraseña.\n   Respuesta del servidor: $response\n");
}

echo "✅ Autenticación exitosa\n\n";

// MAIL FROM
echo "📤 Configurando remitente...\n";
fputs($socket, "MAIL FROM: <info@vineksec.online>\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

if (strpos($response, '250') === false) {
    die("❌ ERROR: Remitente rechazado\n");
}

// RCPT TO
echo "\n📤 Configurando destinatario...\n";
fputs($socket, "RCPT TO: <$to_email>\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

if (strpos($response, '250') === false) {
    die("❌ ERROR: Destinatario rechazado\n");
}

// DATA
echo "\n📤 Enviando contenido del email...\n";
fputs($socket, "DATA\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

// Contenido del email
$email_content = "From: VinekSec <info@vineksec.online>\r\n";
$email_content .= "To: <$to_email>\r\n";
$email_content .= "Subject: 🧪 Test Local de SMTP - VinekSec\r\n";
$email_content .= "MIME-Version: 1.0\r\n";
$email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
$email_content .= "\r\n";
$email_content .= "<html><body style='font-family: Arial, sans-serif;'>";
$email_content .= "<h2 style='color: #667eea;'>✅ Test Local Exitoso</h2>";
$email_content .= "<p>Si estás leyendo esto, el sistema SMTP funciona correctamente desde tu máquina local.</p>";
$email_content .= "<p><strong>Hora del test:</strong> " . date('d/m/Y H:i:s') . "</p>";
$email_content .= "<p><strong>Enviado desde:</strong> " . php_uname('n') . "</p>";
$email_content .= "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 10px;'>";
$email_content .= "<h3>🎓 Vixy Mastery</h3>";
$email_content .= "<p>Aprende pentesting desde cero en <a href='https://vineksec.online/vixymastery.html'>VinekSec</a></p>";
$email_content .= "</div>";
$email_content .= "</body></html>";
$email_content .= "\r\n.\r\n";

fputs($socket, $email_content);
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

if (strpos($response, '250') === false) {
    die("❌ ERROR: Email no aceptado por el servidor\n");
}

// QUIT
echo "\n📤 Cerrando conexión...\n";
fputs($socket, "QUIT\r\n");
$response = fgets($socket, 515);
echo "📥 Respuesta: " . trim($response) . "\n";

fclose($socket);

echo "\n";
echo "==============================================\n";
echo "✅ ¡EMAIL ENVIADO EXITOSAMENTE!\n";
echo "==============================================\n";
echo "\n";
echo "📧 Revisa tu bandeja de entrada en: $to_email\n";
echo "📂 Si no aparece, revisa la carpeta de SPAM\n";
echo "⏱️ Puede tardar 1-5 minutos en llegar\n";
echo "\n";
echo "Si el email llegó, el problema NO es de configuración.\n";
echo "Si no llegó, puede ser:\n";
echo "  1. Email bloqueado por el proveedor (Hotmail/Outlook)\n";
echo "  2. Filtro antispam muy estricto\n";
echo "  3. Límite de envío alcanzado en Hostinger\n";
echo "\n";
?>
