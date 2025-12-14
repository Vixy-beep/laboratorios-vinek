<?php
// ============================================
// UPLOAD DE AVATARES - MEJORADO
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Incluir configuración de base de datos
require_once 'config-clean.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Manejar GET - obtener avatar actual
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'avatar' => $user['avatar']
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener avatar'
        ]);
    }
    exit;
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que se subió un archivo
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen']);
    exit;
}

$file = $_FILES['image'];

// Validar tamaño (máximo 2MB)
$maxSize = 2 * 1024 * 1024; // 2 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'La imagen es muy grande. Máximo 2 MB.']);
    exit;
}

// Validar tipo de archivo
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP.']);
    exit;
}

// Crear directorio de avatares si no existe
$uploadDir = __DIR__ . '/uploads/avatars/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generar nombre único para el archivo
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
$uploadPath = $uploadDir . $fileName;

// Redimensionar y optimizar imagen
try {
    $targetSize = 200; // 200x200 pixels
    
    // Cargar imagen según tipo
    if ($fileType === 'image/jpeg' || $fileType === 'image/jpg') {
        $sourceImage = imagecreatefromjpeg($file['tmp_name']);
    } elseif ($fileType === 'image/png') {
        $sourceImage = imagecreatefrompng($file['tmp_name']);
    } elseif ($fileType === 'image/gif') {
        $sourceImage = imagecreatefromgif($file['tmp_name']);
    } elseif ($fileType === 'image/webp') {
        $sourceImage = imagecreatefromwebp($file['tmp_name']);
    } else {
        $sourceImage = null;
    }
    
    if (!$sourceImage) {
        echo json_encode(['success' => false, 'message' => 'Error al procesar la imagen']);
        exit;
    }
    
    // Obtener dimensiones originales
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    
    // Calcular dimensiones para crop cuadrado
    $size = min($width, $height);
    $x = ($width - $size) / 2;
    $y = ($height - $size) / 2;
    
    // Crear imagen cuadrada
    $squareImage = imagecreatetruecolor($size, $size);
    
    // Preservar transparencia para PNG
    if ($fileType === 'image/png') {
        imagealphablending($squareImage, false);
        imagesavealpha($squareImage, true);
        $transparent = imagecolorallocatealpha($squareImage, 0, 0, 0, 127);
        imagefill($squareImage, 0, 0, $transparent);
    }
    
    imagecopy($squareImage, $sourceImage, 0, 0, $x, $y, $size, $size);
    
    // Redimensionar a 200x200
    $finalImage = imagecreatetruecolor($targetSize, $targetSize);
    
    if ($fileType === 'image/png') {
        imagealphablending($finalImage, false);
        imagesavealpha($finalImage, true);
        $transparent = imagecolorallocatealpha($finalImage, 0, 0, 0, 127);
        imagefill($finalImage, 0, 0, $transparent);
    }
    
    imagecopyresampled($finalImage, $squareImage, 0, 0, 0, 0, $targetSize, $targetSize, $size, $size);
    
    // Guardar imagen optimizada
    if ($fileType === 'image/jpeg' || $fileType === 'image/jpg') {
        $saved = imagejpeg($finalImage, $uploadPath, 90);
    } elseif ($fileType === 'image/png') {
        $saved = imagepng($finalImage, $uploadPath, 9);
    } elseif ($fileType === 'image/gif') {
        $saved = imagegif($finalImage, $uploadPath);
    } elseif ($fileType === 'image/webp') {
        $saved = imagewebp($finalImage, $uploadPath, 90);
    } else {
        $saved = false;
    }
    
    // Liberar memoria
    imagedestroy($sourceImage);
    imagedestroy($squareImage);
    imagedestroy($finalImage);
    
    if (!$saved) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar imagen: ' . $e->getMessage()]);
    exit;
}

// Actualizar base de datos
try {
    // Obtener avatar anterior para eliminarlo
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldAvatarRow = $result->fetch_assoc();
    $oldAvatar = $oldAvatarRow ? $oldAvatarRow['avatar'] : null;
    
    // Actualizar con nuevo avatar
    $avatarUrl = 'uploads/avatars/' . $fileName;
    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $avatarUrl, $userId);
    $stmt->execute();
    
    // Actualizar sesión
    $_SESSION['user_avatar'] = $avatarUrl;
    
    // Eliminar avatar anterior si existe y no es el default
    if ($oldAvatar && strpos($oldAvatar, 'dicebear.com') === false && strpos($oldAvatar, 'ui-avatars.com') === false && strpos($oldAvatar, 'uploads/avatars/') !== false) {
        $oldFilepath = __DIR__ . '/' . $oldAvatar;
        if (file_exists($oldFilepath)) {
            @unlink($oldFilepath);
        }
    }
    
    // Generar URL pública
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $dirname = dirname($_SERVER['SCRIPT_NAME']);
    if ($dirname !== '/') {
        $baseUrl .= $dirname;
    }
    $fileUrl = $baseUrl . '/uploads/avatars/' . $fileName;
    
    echo json_encode([
        'success' => true,
        'message' => 'Avatar actualizado correctamente',
        'url' => $fileUrl,
        'path' => $avatarUrl,
        'filename' => $fileName
    ]);
    
} catch (Exception $e) {
    // Si falla la DB, eliminar archivo subido
    @unlink($uploadPath);
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la base de datos: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
