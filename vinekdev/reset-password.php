<?php
header('Content-Type: application/json');
require_once 'config-clean.php';

// Crear nuevo hash para la contraseña "Admin123!"
$newPassword = 'Admin123!';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Actualizar el usuario admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@vinekdev.com'");
$stmt->bind_param("s", $hash);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente',
        'email' => 'admin@vinekdev.com',
        'new_password' => 'Admin123!',
        'hash' => $hash
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar contraseña',
        'error' => $stmt->error
    ]);
}

$conn->close();
?>
