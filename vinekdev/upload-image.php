<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Configuración
$uploadDir = 'uploads/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

// Crear directorio si no existe
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Verificar que se subió un archivo
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo o hubo un error']);
    exit;
}

$file = $_FILES['image'];

// Verificar tamaño
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB']);
    exit;
}

// Verificar tipo
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP']);
    exit;
}

// Generar nombre único
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_') . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Mover archivo
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Construir URL completa
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $imageUrl = $baseUrl . '/' . $filepath;
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagen subida exitosamente',
        'url' => $imageUrl,
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo']);
}
?>
