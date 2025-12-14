<?php
// Generar hashes correctos
$passwords = [
    'admin123' => 'info@vineksec.online',
    'demo123' => 'demo@vinekdev.com'
];

echo "=== HASHES GENERADOS ===\n\n";

foreach ($passwords as $password => $email) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    echo "Usuario: $email\n";
    echo "Contraseña: $password\n";
    echo "Hash: $hash\n";
    echo "SQL: UPDATE saas_users SET password = '$hash' WHERE email = '$email';\n";
    echo "\n---\n\n";
}
?>
