<?php
// ============================================
// INSTALADOR AUTOMÁTICO DE BASE DE DATOS
// ============================================

echo "<h1>Instalador de Base de Datos - VinekDev Blog</h1>";
echo "<pre>";

// Configuración
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'vinekdev_blog';

// Paso 1: Conectar a MySQL
echo "📌 Paso 1: Conectando a MySQL...\n";
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}
echo "✅ Conexión exitosa a MySQL\n\n";

// Paso 2: Crear base de datos
echo "📌 Paso 2: Creando base de datos '$dbname'...\n";
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ Base de datos creada/verificada exitosamente\n\n";
} else {
    echo "⚠️ Error al crear base de datos: " . $conn->error . "\n\n";
}

// Paso 3: Seleccionar base de datos
echo "📌 Paso 3: Seleccionando base de datos...\n";
$conn->select_db($dbname);
echo "✅ Base de datos seleccionada\n\n";

// Paso 4: Leer y ejecutar SQL
echo "📌 Paso 4: Importando tablas y datos...\n";

$sqlFile = 'database.sql';
if (!file_exists($sqlFile)) {
    die("❌ Error: No se encuentra el archivo database.sql\n");
}

$sql = file_get_contents($sqlFile);

// Separar las queries
$queries = array_filter(array_map('trim', explode(';', $sql)));

$success = 0;
$errors = 0;

foreach ($queries as $query) {
    if (empty($query) || strpos($query, '--') === 0) continue;
    
    if ($conn->query($query) === TRUE) {
        $success++;
    } else {
        $errors++;
        echo "⚠️ Error en query: " . substr($query, 0, 50) . "...\n";
        echo "   Detalle: " . $conn->error . "\n";
    }
}

echo "\n📊 Resumen:\n";
echo "   ✅ Queries ejecutadas exitosamente: $success\n";
echo "   ❌ Queries con errores: $errors\n\n";

// Paso 5: Verificar tablas
echo "📌 Paso 5: Verificando tablas creadas...\n";
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "✅ Tablas encontradas: " . implode(', ', $tables) . "\n\n";

// Paso 6: Verificar datos
echo "📌 Paso 6: Verificando datos...\n";

// Contar usuarios
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$userCount = $result->fetch_assoc()['count'];
echo "   👥 Usuarios creados: $userCount\n";

// Contar posts
$result = $conn->query("SELECT COUNT(*) as count FROM posts");
$postCount = $result->fetch_assoc()['count'];
echo "   📝 Posts de ejemplo: $postCount\n\n";

// Paso 7: Mostrar credenciales
echo "📌 Paso 7: Credenciales de acceso\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔐 CREDENCIALES DE ADMINISTRADOR:\n";
echo "   Email: admin@vinekdev.com\n";
echo "   Contraseña: Admin123!\n";
echo "\n";
echo "🔐 CREDENCIALES DE AUTOR:\n";
echo "   Email: autor@vinekdev.com\n";
echo "   Contraseña: Autor123!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ INSTALACIÓN COMPLETADA EXITOSAMENTE!\n\n";
echo "📋 Próximos pasos:\n";
echo "   1. Ve a login.html para iniciar sesión\n";
echo "   2. Usa las credenciales de administrador\n";
echo "   3. Accede al panel de administración (admin.html)\n";
echo "   4. ¡Comienza a crear contenido!\n\n";

echo "💡 IMPORTANTE:\n";
echo "   - Por seguridad, elimina o renombra este archivo install.php\n";
echo "   - Cambia las contraseñas por defecto\n";
echo "   - Revisa la configuración en config.php\n\n";

$conn->close();

echo "</pre>";
echo "<hr>";
echo "<p style='text-align:center; margin-top:2rem;'>";
echo "<a href='login.html' style='display:inline-block; padding:1rem 2rem; background:#6366f1; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>🚀 Ir a Login</a> ";
echo "<a href='index.html' style='display:inline-block; padding:1rem 2rem; background:#10b981; color:white; text-decoration:none; border-radius:8px; font-weight:bold; margin-left:1rem;'>🏠 Ver Blog</a> ";
echo "<a href='admin.html' style='display:inline-block; padding:1rem 2rem; background:#f59e0b; color:white; text-decoration:none; border-radius:8px; font-weight:bold; margin-left:1rem;'>⚙️ Panel Admin</a>";
echo "</p>";
?>

echo "✨ ¡INSTALACIÓN COMPLETADA!\n\n";
echo "🚀 Próximos pasos:\n";
echo "   1. Visita el blog: <a href='blog.html' target='_blank'>blog.html</a>\n";
echo "   2. Accede al admin: <a href='login.html' target='_blank'>login.html</a>\n";
echo "   3. Cambia las credenciales por seguridad\n\n";

echo "⚠️ IMPORTANTE: Elimina este archivo (install.php) después de la instalación\n";

$conn->close();
echo "</pre>";

// Agregar botones de acceso rápido
echo "<div style='margin: 20px 0;'>";
echo "<a href='blog.html' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; text-decoration: none; border-radius: 8px; margin: 5px;'>📝 Ver Blog</a>";
echo "<a href='login.html' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; text-decoration: none; border-radius: 8px; margin: 5px;'>🔐 Panel Admin</a>";
echo "<a href='index.html' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; text-decoration: none; border-radius: 8px; margin: 5px;'>🏠 Inicio</a>";
echo "</div>";
?>
