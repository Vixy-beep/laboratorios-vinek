<?php
// Script de diagnóstico para verificar auth-clean.php
header('Content-Type: application/json');

echo json_encode([
    'archivo_existe' => file_exists('auth-clean.php'),
    'ultima_modificacion' => file_exists('auth-clean.php') ? date('Y-m-d H:i:s', filemtime('auth-clean.php')) : 'N/A',
    'lineas_con_avatar' => 0,
    'contenido_contiene_avatar' => false
]);

// Leer el archivo y buscar la palabra "avatar"
if (file_exists('auth-clean.php')) {
    $contenido = file_get_contents('auth-clean.php');
    $lineas = substr_count($contenido, 'avatar');
    $contiene = strpos($contenido, 'avatar') !== false;
    
    echo json_encode([
        'archivo_existe' => true,
        'ultima_modificacion' => date('Y-m-d H:i:s', filemtime('auth-clean.php')),
        'lineas_con_avatar' => $lineas,
        'contenido_contiene_avatar' => $contiene,
        'tamaño_bytes' => filesize('auth-clean.php')
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'error' => 'El archivo auth-clean.php NO existe en el servidor'
    ], JSON_PRETTY_PRINT);
}
?>
