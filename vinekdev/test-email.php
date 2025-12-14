<?php
/**
 * TEST DE ENVÍO DE EMAILS - Debug de SMTP
 * Abre este archivo en el navegador: https://vineksec.online/test-email.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'email-sender.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Email - VinekSec</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .config-box {
            background: #f1f5f9;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .config-box h3 {
            color: #1e293b;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .config-item {
            color: #475569;
            font-size: 13px;
            margin: 5px 0;
            font-family: 'Courier New', monospace;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #334155;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .success {
            background: #dcfce7;
            border: 2px solid #22c55e;
            color: #166534;
        }
        
        .error {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
        
        .info {
            background: #dbeafe;
            border: 2px solid #3b82f6;
            color: #1e40af;
        }
        
        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Test de Email SMTP</h1>
        <p class="subtitle">Verifica que el sistema de emails funcione correctamente</p>
        
        <div class="config-box">
            <h3>📋 Configuración Actual:</h3>
            <div class="config-item">🌐 Host: <?php echo SMTP_HOST; ?></div>
            <div class="config-item">🔌 Port: <?php echo SMTP_PORT; ?></div>
            <div class="config-item">📮 User: <?php echo SMTP_USER; ?></div>
            <div class="config-item">🔒 Pass: <?php echo str_repeat('*', strlen(SMTP_PASS) - 4) . substr(SMTP_PASS, -4); ?></div>
            <div class="config-item">📤 From: <?php echo FROM_NAME; ?></div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="to_email">📧 Email de destino:</label>
                <input type="email" id="to_email" name="to_email" value="jordano-252@hotmail.com" required>
            </div>
            
            <button type="submit" name="send_test" class="btn">
                🚀 Enviar Email de Prueba
            </button>
        </form>
        
        <?php
        if (isset($_POST['send_test'])) {
            $toEmail = filter_var($_POST['to_email'], FILTER_VALIDATE_EMAIL);
            
            if (!$toEmail) {
                echo '<div class="result error">❌ Email inválido</div>';
            } else {
                echo '<div class="result info">🔄 Intentando enviar email a: <strong>' . htmlspecialchars($toEmail) . '</strong></div>';
                
                // Test 1: Email de confirmación de newsletter
                echo '<div class="result info"><strong>Test 1: Email de Newsletter</strong></div>';
                
                $testUrl = SITE_URL . '/newsletter-confirm.html?token=test-token-12345';
                $result1 = sendConfirmationEmailPro($toEmail, $testUrl);
                
                if ($result1) {
                    echo '<div class="result success">✅ Email de newsletter enviado correctamente</div>';
                } else {
                    echo '<div class="result error">❌ Error al enviar email de newsletter</div>';
                }
                
                // Test 2: Email simple de prueba
                echo '<div class="result info"><strong>Test 2: Email Simple</strong></div>';
                
                $subject = "🧪 Test de Email - VinekSec";
                $htmlContent = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                        <h1 style='color: white; margin: 0;'>🧪 Email de Prueba</h1>
                    </div>
                    <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #1e293b;'>✅ ¡Funcionó!</h2>
                        <p style='color: #475569; line-height: 1.6;'>
                            Si estás leyendo esto, significa que el sistema SMTP de VinekSec está funcionando correctamente.
                        </p>
                        <div style='background: #f1f5f9; padding: 20px; margin: 20px 0; border-radius: 8px;'>
                            <p style='margin: 0; color: #334155;'>
                                <strong>Hora del test:</strong> " . date('d/m/Y H:i:s') . "<br>
                                <strong>IP del servidor:</strong> " . $_SERVER['SERVER_ADDR'] . "<br>
                                <strong>Email destino:</strong> " . $toEmail . "
                            </p>
                        </div>
                        <p style='color: #64748b; font-size: 12px; margin-top: 20px;'>
                            Este es un email de prueba automático. No respondas a este mensaje.
                        </p>
                    </div>
                </div>
                ";
                
                $result2 = sendSMTPEmail($toEmail, $subject, $htmlContent, 'VinekSec Test');
                $result2 = sendSMTPEmail(
                    $toEmail, 
                    $subject, 
                    $htmlContent, 
                    "VinekSec",
                    true // DEBUG MODE
                );
                
                if (is_array($result2)) {
                    if ($result2['success']) {
                        echo '<div class="result success">✅ Email simple enviado correctamente</div>';
                    } else {
                        echo '<div class="result error">❌ Error: ' . htmlspecialchars($result2['error']) . '</div>';
                    }
                    echo '<div style="background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">';
                    echo '<strong>📋 Log completo:</strong><br><br>';
                    foreach ($result2['log'] as $line) {
                        echo htmlspecialchars($line) . '<br>';
                    }
                    echo '</div>';
                } else {
                    if ($result2) {
                        echo '<div class="result success">✅ Email simple enviado correctamente</div>';
                    } else {
                        echo '<div class="result error">❌ Error al enviar email simple</div>';
                    }
                }
                
                // Resumen final
                $success1 = is_array($result1) ? $result1['success'] : $result1;
                $success2 = is_array($result2) ? $result2['success'] : $result2;
                
                if ($success1 && $success2) {
                    echo '<div class="result success">
                        <strong>🎉 ¡TODO FUNCIONA!</strong><br>
                        Se enviaron 2 emails de prueba a <strong>' . htmlspecialchars($toEmail) . '</strong><br><br>
                        <strong>Pasos siguientes:</strong><br>
                        1. Revisa tu bandeja de entrada<br>
                        2. Revisa la carpeta de SPAM/Correo no deseado<br>
                        3. Si ves los emails, el sistema funciona correctamente<br>
                        4. Si no llegan en 5 minutos, revisa las credenciales SMTP en config.php
                    </div>';
                } else {
                    echo '<div class="result error">
                        <strong>❌ Error en el envío</strong><br>
                        Posibles causas:<br>
                        1. Credenciales SMTP incorrectas en config.php<br>
                        2. Puerto bloqueado por firewall<br>
                        3. Email de origen no verificado en Hostinger<br>
                        4. Límite de envío alcanzado<br><br>
                        <strong>Verifica:</strong><br>
                        - SMTP_HOST: ' . SMTP_HOST . '<br>
                        - SMTP_PORT: ' . SMTP_PORT . '<br>
                        - SMTP_USER: ' . SMTP_USER . '<br>
                        - Que la contraseña SMTP esté correcta
                    </div>';
                }
                
                // Mostrar logs de error si existen
                $errorLog = error_get_last();
                if ($errorLog) {
                    echo '<div class="result error"><strong>⚠️ Error de PHP:</strong><pre>' . print_r($errorLog, true) . '</pre></div>';
                }
            }
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center;">
            <p style="color: #64748b; font-size: 13px;">
                💡 Si los emails no llegan, contacta al soporte de Hostinger para verificar que SMTP esté habilitado
            </p>
        </div>
    </div>
</body>
</html>
