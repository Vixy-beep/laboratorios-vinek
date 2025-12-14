<?php
// ============================================
// POST REDIRECT - Redirige URLs antiguas a slugs
// ============================================

require_once 'config-clean.php';

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id > 0) {
    // Obtener el título del post para generar el slug
    $stmt = $conn->prepare("SELECT title FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($post = $result->fetch_assoc()) {
        // Generar slug del título
        $slug = generateSlug($post['title']);
        
        // Redirigir a la URL amigable
        header("Location: /post/{$slug}-{$post_id}", true, 301);
        exit;
    }
}

// Si no se encuentra, redirigir al blog
header("Location: /blog.html", true, 302);
exit;

function generateSlug($text) {
    // Convertir a minúsculas
    $text = strtolower($text);
    
    // Reemplazar caracteres especiales del español
    $text = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
        ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
        $text
    );
    
    // Eliminar caracteres no alfanuméricos excepto espacios y guiones
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    
    // Reemplazar espacios y múltiples guiones con un solo guión
    $text = preg_replace('/[\s-]+/', '-', $text);
    
    // Eliminar guiones al inicio y final
    $text = trim($text, '-');
    
    // Limitar longitud del slug
    $text = substr($text, 0, 100);
    
    return $text;
}

$conn->close();
?>
