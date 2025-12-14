<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Cargar configuración
    $config = include 'config.php';
    
    // Datos del formulario
    $name = htmlspecialchars($_POST['name']);
    $company = htmlspecialchars($_POST['company']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $service = htmlspecialchars($_POST['service']);
    $budget = htmlspecialchars($_POST['budget']);
    $message = htmlspecialchars($_POST['message']);
    
    // Validar campos requeridos
    if (empty($name) || empty($email) || empty($message)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Por favor completa todos los campos requeridos.']);
        exit;
    }
    
    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Por favor ingresa un email válido.']);
        exit;
    }
    
    // Configurar PHPMailer
    require_once 'vendor/autoload.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'];
        $mail->Password = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        // Configuración del email
        $mail->setFrom($config['smtp_user'], $config['from_name']);
        $mail->addAddress($config['to_email'], 'VinekDev');
        $mail->addReplyTo($email, $name);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = "Nuevo contacto desde VinekDev - " . $name;
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(45deg, #00d4ff, #bf00ff); color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .field { margin-bottom: 15px; }
                .field strong { color: #333; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🚀 Nuevo Contacto desde VinekDev</h2>
                </div>
                <div class='content'>
                    <div class='field'><strong>Nombre:</strong> $name</div>
                    <div class='field'><strong>Empresa:</strong> $company</div>
                    <div class='field'><strong>Email:</strong> $email</div>
                    <div class='field'><strong>Teléfono:</strong> $phone</div>
                    <div class='field'><strong>Servicio de Interés:</strong> $service</div>
                    <div class='field'><strong>Presupuesto:</strong> $budget</div>
                    <div class='field'><strong>Mensaje:</strong><br>$message</div>
                </div>
                <div class='footer'>
                    <p>Enviado desde el formulario de contacto de VinekDev</p>
                    <p>Fecha: " . date('Y-m-d H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->AltBody = "
        Nuevo contacto desde VinekDev
        
        Nombre: $name
        Empresa: $company
        Email: $email
        Teléfono: $phone
        Servicio: $service
        Presupuesto: $budget
        
        Mensaje:
        $message
        
        ---
        Enviado desde VinekDev
        ";
        
        $mail->send();
        
        // Respuesta exitosa
        echo json_encode([
            'status' => 'success', 
            'message' => '¡Mensaje enviado con éxito! Te contactaremos pronto.'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error al enviar el mensaje: ' . $mail->ErrorInfo
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}
?>