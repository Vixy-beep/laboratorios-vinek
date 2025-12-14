<?php
/**
 * Enviar email profesional usando SMTP de Hostinger
 * Conexión SMTP real con autenticación
 */

require_once 'config.php';

/**
 * Enviar email usando SMTP real con socket - VERSION DEBUG
 */
function sendSMTPEmail($to, $subject, $htmlMessage, $fromName = 'VinekDev', $debug = false) {
    
    $smtp_host = 'smtp.hostinger.com';
    $smtp_port = 465; // SSL directo
    $smtp_user = SMTP_USER;
    $smtp_pass = SMTP_PASS;
    $from_email = SMTP_USER;
    
    $log = [];
    
    // Conectar con SSL directo
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $log[] = "Conectando a ssl://{$smtp_host}:{$smtp_port}...";
    
    $socket = stream_socket_client(
        "ssl://{$smtp_host}:{$smtp_port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        $error = "No se pudo conectar: $errstr ($errno)";
        $log[] = "❌ ERROR: $error";
        if ($debug) {
            return ['success' => false, 'error' => $error, 'log' => $log];
        }
        error_log("SMTP Error: " . $error);
        return false;
    }
    
    $log[] = "✅ Conectado exitosamente";
    
    // Leer respuesta inicial del servidor
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    // EHLO
    $log[] = "ENVIANDO: EHLO " . $_SERVER['SERVER_NAME'];
    fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
    
    // Leer todas las líneas de respuesta EHLO hasta que no haya más líneas con guion
    do {
        $smtp_response = fgets($socket, 515);
        $log[] = "SERVER: " . trim($smtp_response);
    } while (substr($smtp_response, 3, 1) === '-'); // Continuar mientras la posición 3 sea un guion
    
    // AUTH LOGIN
    $log[] = "ENVIANDO: AUTH LOGIN";
    fputs($socket, "AUTH LOGIN\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    $log[] = "ENVIANDO: usuario (base64)";
    fputs($socket, base64_encode($smtp_user) . "\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    $log[] = "ENVIANDO: contraseña (base64)";
    fputs($socket, base64_encode($smtp_pass) . "\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    if (strpos($smtp_response, '235') === false) {
        $error = "Autenticación fallida: " . trim($smtp_response);
        $log[] = "❌ ERROR: $error";
        fclose($socket);
        if ($debug) {
            return ['success' => false, 'error' => $error, 'log' => $log];
        }
        error_log("SMTP Error: " . $error);
        return false;
    }
    
    $log[] = "✅ Autenticación exitosa";
    
    // MAIL FROM
    $log[] = "ENVIANDO: MAIL FROM <$from_email>";
    fputs($socket, "MAIL FROM: <$from_email>\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    // RCPT TO
    $log[] = "ENVIANDO: RCPT TO <$to>";
    fputs($socket, "RCPT TO: <$to>\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    // DATA
    $log[] = "ENVIANDO: DATA";
    fputs($socket, "DATA\r\n");
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    // Headers del email
    $email_content = "From: $fromName <$from_email>\r\n";
    $email_content .= "To: <$to>\r\n";
    $email_content .= "Subject: $subject\r\n";
    $email_content .= "MIME-Version: 1.0\r\n";
    $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email_content .= "Reply-To: $from_email\r\n";
    $email_content .= "X-Mailer: VinekDev Mailer\r\n";
    $email_content .= "\r\n";
    $email_content .= $htmlMessage;
    $email_content .= "\r\n.\r\n";
    
    // Enviar contenido
    $log[] = "ENVIANDO: Contenido del email...";
    fputs($socket, $email_content);
    $smtp_response = fgets($socket, 515);
    $log[] = "SERVER: " . trim($smtp_response);
    
    // QUIT
    $log[] = "ENVIANDO: QUIT";
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    $log[] = "✅ Email enviado exitosamente";
    
    if ($debug) {
        return ['success' => true, 'log' => $log];
    }
    
    return true;
}

/**
 * Template de email profesional base
 */
function getEmailTemplate($content, $title = 'VinekDev Newsletter') {
    return "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$title</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
        $content
    </body>
    </html>
    ";
}

/**
 * Email de confirmación de suscripción
 */
function sendConfirmationEmailPro($to, $confirmUrl) {
    $subject = "✅ Confirma tu suscripción - VinekSec & Vixy Mastery";
    
    $htmlMessage = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #1e293b; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.4);">
                    
                    <!-- Header con gradiente -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 50px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 36px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.3); letter-spacing: -0.5px;">
                                🚀 VinekSec
                            </h1>
                            <p style="color: rgba(255,255,255,0.95); margin: 12px 0 0 0; font-size: 16px; font-weight: 500; letter-spacing: 0.5px;">
                                Ciberseguridad • Pentesting • Hacking Ético
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 50px 40px; background-color: #1e293b;">
                            <h2 style="color: #f1f5f9; margin: 0 0 24px 0; font-size: 28px; font-weight: 700;">
                                ¡Bienvenido a nuestra comunidad! 🎉
                            </h2>
                            
                            <p style="color: #cbd5e1; font-size: 16px; line-height: 1.8; margin: 0 0 30px 0;">
                                Gracias por suscribirte al <strong style="color: #e2e8f0;">Newsletter de VinekSec</strong>. Estás a un paso de recibir contenido exclusivo sobre ciberseguridad, pentesting y hacking ético.
                            </p>
                            
                            <!-- Lista de beneficios -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e3a8a 0%, #312e81 100%); border-left: 4px solid #667eea; border-radius: 12px; padding: 28px; margin: 30px 0;">
                                <tr>
                                    <td>
                                        <p style="color: #e0e7ff; font-size: 16px; font-weight: 700; margin: 0 0 20px 0;">
                                            📬 Recibirás contenido sobre:
                                        </p>
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #ddd6fe; font-size: 15px; line-height: 1.7;">🛡️ Artículos de ciberseguridad y pentesting</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #ddd6fe; font-size: 15px; line-height: 1.7;">💻 Tutoriales técnicos avanzados paso a paso</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #ddd6fe; font-size: 15px; line-height: 1.7;">🔥 Últimas herramientas y vulnerabilidades</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #ddd6fe; font-size: 15px; line-height: 1.7;">📚 Recursos y cheatsheets exclusivos</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #ddd6fe; font-size: 15px; line-height: 1.7;">🎓 <strong style="color: #fbbf24;">Acceso anticipado a laboratorios y cursos</strong></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Banner Vixy Mastery -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 16px; margin: 35px 0; overflow: hidden; box-shadow: 0 8px 20px rgba(245, 87, 108, 0.3);">
                                <tr>
                                    <td style="padding: 40px 35px; text-align: center;">
                                        <h3 style="color: #ffffff; margin: 0 0 16px 0; font-size: 26px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.2); letter-spacing: -0.3px;">
                                            🎓 Nuevo: Vixy Mastery
                                        </h3>
                                        <p style="color: rgba(255,255,255,0.98); margin: 0 0 24px 0; font-size: 17px; line-height: 1.6;">
                                            Aprende pentesting desde cero hasta nivel avanzado con <strong>laboratorios prácticos</strong>, certificaciones reconocidas y acceso de por vida
                                        </p>
                                        <table cellpadding="0" cellspacing="0" align="center">
                                            <tr>
                                                <td style="background-color: #ffffff; border-radius: 10px; padding: 16px 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                                    <a href="https://vineksec.online/vixymastery.html" style="color: #f5576c; text-decoration: none; font-weight: 800; font-size: 16px; display: block;">
                                                        🚀 Explorar Curso Ahora
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #cbd5e1; font-size: 17px; line-height: 1.7; margin: 35px 0 28px 0; font-weight: 600;">
                                Para confirmar tu suscripción, haz clic en el botón:
                            </p>
                            
                            <!-- Botón de confirmación -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 15px 0;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; padding: 18px 48px; box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);">
                                                    <a href="' . $confirmUrl . '" style="color: #ffffff; text-decoration: none; font-weight: 800; font-size: 17px; display: block; letter-spacing: 0.3px;">
                                                        ✅ Confirmar Suscripción
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 30px 0 8px 0; text-align: center;">
                                Si no puedes hacer clic en el botón, copia y pega este enlace:
                            </p>
                            <p style="color: #667eea; font-size: 13px; word-break: break-all; margin: 0; text-align: center;">
                                ' . $confirmUrl . '
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 35px; text-align: center; border-top: 1px solid #334155;">
                            <p style="color: #94a3b8; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                <strong style="color: #e2e8f0; font-size: 18px;">VinekSec</strong>
                            </p>
                            <p style="color: #64748b; font-size: 13px; margin: 0;">
                                Forjando el futuro digital
                            </p>
                            <p style="color: #475569; font-size: 12px; margin: 12px 0 0 0;">
                                © 2025 VinekDev. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return sendSMTPEmail($to, $subject, $htmlMessage, 'VinekSec');
}

/**
 * Email de notificación cuando se aprueba una edición de post
 */
function sendPostApprovedEmail($to, $postTitle, $postUrl) {
    $subject = "✅ Tu edición fue aprobada - " . $postTitle;
    
    $htmlMessage = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #1e293b; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.4);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 50px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                                ¡Edición Aprobada!
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 50px 40px; background-color: #1e293b;">
                            <h2 style="color: #f1f5f9; margin: 0 0 24px 0; font-size: 24px; font-weight: 700;">
                                Excelentes noticias 🎉
                            </h2>
                            
                            <p style="color: #cbd5e1; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                                Tu edición del artículo <strong style="color: #e2e8f0;">"' . htmlspecialchars($postTitle) . '"</strong> ha sido revisada y aprobada por el equipo de administración.
                            </p>
                            
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e3a8a 0%, #312e81 100%); border-left: 4px solid #10b981; border-radius: 12px; padding: 28px; margin: 30px 0;">
                                <tr>
                                    <td>
                                        <p style="color: #e0e7ff; font-size: 15px; margin: 0; line-height: 1.8;">
                                            ✨ Los cambios ya están <strong style="color: #a5f3fc;">publicados en vivo</strong><br>
                                            🌐 Tu artículo está disponible para todos los lectores<br>
                                            📊 Puedes ver las estadísticas en tu panel de autor
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botón -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; padding: 18px 48px; box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);">
                                                    <a href="' . $postUrl . '" style="color: #ffffff; text-decoration: none; font-weight: 800; font-size: 17px; display: block;">
                                                        📖 Ver Artículo Publicado
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #94a3b8; font-size: 14px; line-height: 1.7; margin: 30px 0 0 0; padding: 20px; background-color: #0f172a; border-radius: 8px;">
                                💡 <strong style="color: #cbd5e1;">Tip:</strong> Comparte tu artículo en redes sociales para llegar a más lectores interesados en ciberseguridad.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 35px; text-align: center; border-top: 1px solid #334155;">
                            <p style="color: #94a3b8; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                <strong style="color: #e2e8f0; font-size: 18px;">VinekSec</strong>
                            </p>
                            <p style="color: #64748b; font-size: 13px; margin: 0;">
                                Forjando el futuro digital
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return sendSMTPEmail($to, $subject, $htmlMessage, 'VinekSec');
}

/**
 * Email de notificación de nuevo post para suscriptores
 */
function sendNewPostNotification($to, $postTitle, $postExcerpt, $postUrl) {
    $subject = "🔥 Nuevo artículo: " . $postTitle;
    
    $htmlMessage = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #0f172a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #1e293b; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.4);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); padding: 50px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 15px;">🔥</div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 32px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                                ¡Nuevo Artículo!
                            </h1>
                            <p style="color: rgba(255,255,255,0.95); margin: 12px 0 0 0; font-size: 16px;">
                                Contenido fresco en VinekSec
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 50px 40px; background-color: #1e293b;">
                            <h2 style="color: #f1f5f9; margin: 0 0 20px 0; font-size: 26px; font-weight: 700; line-height: 1.3;">
                                ' . htmlspecialchars($postTitle) . '
                            </h2>
                            
                            <p style="color: #cbd5e1; font-size: 16px; line-height: 1.8; margin: 0 0 32px 0;">
                                ' . htmlspecialchars($postExcerpt) . '
                            </p>
                            
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #1e3a8a 0%, #312e81 100%); border-left: 4px solid #f59e0b; border-radius: 12px; padding: 28px; margin: 30px 0;">
                                <tr>
                                    <td>
                                        <p style="color: #e0e7ff; font-size: 15px; margin: 0; line-height: 1.8;">
                                            📖 Aprende técnicas avanzadas de pentesting<br>
                                            🛡️ Mejora tus habilidades en ciberseguridad<br>
                                            💡 Contenido práctico y aplicable
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botón -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; padding: 18px 48px; box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);">
                                                    <a href="' . $postUrl . '" style="color: #ffffff; text-decoration: none; font-weight: 800; font-size: 17px; display: block;">
                                                        📖 Leer Artículo Completo
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Banner Vixy -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; margin: 40px 0 30px 0; overflow: hidden;">
                                <tr>
                                    <td style="padding: 30px; text-align: center;">
                                        <p style="color: #ffffff; margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">
                                            ¿Quieres más contenido premium?
                                        </p>
                                        <h3 style="color: #ffffff; margin: 0 0 12px 0; font-size: 22px; font-weight: 800;">
                                            🎓 Vixy Mastery
                                        </h3>
                                        <p style="color: rgba(255,255,255,0.95); margin: 0 0 18px 0; font-size: 15px;">
                                            Laboratorios prácticos y cursos avanzados
                                        </p>
                                        <a href="https://vineksec.online/vixymastery.html" style="display: inline-block; background: #ffffff; color: #f5576c; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px;">
                                            Explorar Ahora
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 30px; text-align: center; border-top: 1px solid #334155;">
                            <p style="color: #94a3b8; font-size: 15px; margin: 0 0 12px 0; font-weight: 600;">
                                <strong style="color: #e2e8f0; font-size: 18px;">VinekSec</strong>
                            </p>
                            <p style="color: #64748b; font-size: 13px; margin: 0 0 15px 0;">
                                Forjando el futuro digital
                            </p>
                            <p style="color: #475569; font-size: 12px; margin: 0;">
                                Recibiste este email porque estás suscrito al newsletter de VinekSec
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    
    return sendSMTPEmail($to, $subject, $htmlMessage, 'VinekSec');
}

/**
 * Email de nuevo post (para newsletter)
 */
function sendNewPostEmailPro($to, $post, $postUrl, $unsubscribeUrl) {
    $title = htmlspecialchars($post['title']);
    $excerpt = htmlspecialchars($post['excerpt']);
    $category = htmlspecialchars($post['category']);
    $created = $post['created_at'];
    
    // Formatear fecha
    $dateObj = new DateTime($created);
    $day = $dateObj->format('d');
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');
    $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $formattedDate = $day . ' de ' . $months[(int)$month] . ' de ' . $year;
    
    $image = $post['image'] ? SITE_URL . '/' . $post['image'] : '';
    
    $subject = "🔥 Nuevo Post: " . $title;
    
    $content = "
    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;'>
        <div style='max-width: 560px; margin: 0 auto;'>
            
            <!-- Post Card -->
            <div style='background: #1e293b; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.4);'>
                
                <!-- Post Image -->
                <div style='position: relative; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 280px; overflow: hidden;'>
                    " . ($image ? "<img src='$image' style='width: 100%; height: 100%; object-fit: cover;' alt='$title'>" : "") . "
                    <div style='position: absolute; top: 20px; left: 20px; background: rgba(255,255,255,0.95); padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; color: #667eea; text-transform: uppercase;'>
                        $category
                    </div>
                </div>
                
                <!-- Post Content -->
                <div style='padding: 30px;'>
                    <div style='color: #94a3b8; font-size: 13px; margin-bottom: 15px;'>
                        📅 $formattedDate
                    </div>
                    
                    <h2 style='color: white; font-size: 24px; font-weight: 700; margin: 0 0 15px 0; line-height: 1.3;'>
                        $title
                    </h2>
                    
                    <p style='color: #cbd5e1; font-size: 15px; line-height: 1.6; margin: 0 0 25px 0;'>
                        $excerpt
                    </p>
                    
                    <div style='text-align: center;'>
                        <a href='$postUrl' style='display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 15px;'>
                            Leer Artículo Completo →
                        </a>
                    </div>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div style='background: #0f172a; border-radius: 20px; margin-top: 20px; padding: 30px; text-align: center;'>
                <p style='margin: 0 0 10px 0;'>
                    <strong style='background: linear-gradient(135deg, #00d4ff 0%, #bf00ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px;'>VinekDev</strong>
                </p>
                <p style='color: #94a3b8; font-size: 13px; margin: 0 0 20px 0;'>
                    Forjando el futuro digital<br>
                    Ciberseguridad • Pentesting • Desarrollo
                </p>
                <div style='margin-top: 20px;'>
                    <a href='" . SITE_URL . "' style='color: #667eea; text-decoration: none; font-size: 13px; margin: 0 10px;'>Inicio</a>
                    <a href='" . SITE_URL . "/blog.html' style='color: #667eea; text-decoration: none; font-size: 13px; margin: 0 10px;'>Blog</a>
                    <a href='$unsubscribeUrl' style='color: #667eea; text-decoration: none; font-size: 13px; margin: 0 10px;'>Desuscribirse</a>
                </div>
                <p style='color: #64748b; margin: 20px 0 0 0; font-size: 11px;'>
                    © " . date('Y') . " VinekDev. Todos los derechos reservados.
                </p>
            </div>
            
        </div>
    </div>
    ";
    
    $html = getEmailTemplate($content, $title . ' - VinekDev');
    
    return sendSMTPEmail($to, $subject, $html, 'VinekDev');
}
?>
