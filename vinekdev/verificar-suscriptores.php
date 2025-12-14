<?php
require_once 'config.php';

echo "<h1>🔍 Diagnóstico de Suscriptores</h1>";

// 1. Verificar estructura de la tabla
echo "<h2>📋 Estructura de la tabla newsletter_subscribers</h2>";
$result = $conn->query("DESCRIBE newsletter_subscribers");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th style='padding: 10px;'>Campo</th><th style='padding: 10px;'>Tipo</th><th style='padding: 10px;'>Nulo</th><th style='padding: 10px;'>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px;'><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. Mostrar TODOS los suscriptores sin filtros
echo "<h2>👥 TODOS los suscriptores (sin filtros)</h2>";
$stmt = $conn->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC");
if ($stmt && $stmt->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0; width: 100%;'>";
    echo "<tr>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>Email</th>";
    echo "<th style='padding: 10px;'>Nombre</th>";
    echo "<th style='padding: 10px;'>Confirmado</th>";
    echo "<th style='padding: 10px;'>Activo</th>";
    echo "<th style='padding: 10px;'>Token</th>";
    echo "<th style='padding: 10px;'>Fecha</th>";
    echo "</tr>";
    
    while ($row = $stmt->fetch_assoc()) {
        $confirmed = isset($row['confirmed']) ? ($row['confirmed'] == 1 ? '✅ SÍ' : '❌ NO') : '⚠️ NO EXISTE';
        $active = isset($row['active']) ? ($row['active'] == 1 ? '✅ SÍ' : '❌ NO') : '⚠️ NO EXISTE';
        
        echo "<tr>";
        echo "<td style='padding: 10px;'>" . $row['id'] . "</td>";
        echo "<td style='padding: 10px;'><strong>" . htmlspecialchars($row['email']) . "</strong></td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
        echo "<td style='padding: 10px;'>" . $confirmed . "</td>";
        echo "<td style='padding: 10px;'>" . $active . "</td>";
        echo "<td style='padding: 10px;'>" . substr($row['token'] ?? 'N/A', 0, 20) . "...</td>";
        echo "<td style='padding: 10px;'>" . ($row['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Total de suscriptores: " . $stmt->num_rows . "</strong></p>";
} else {
    echo "<p style='color: red;'><strong>❌ No hay ningún suscriptor en la tabla</strong></p>";
}

// 3. Contar por estados
echo "<h2>📊 Estadísticas</h2>";
$total = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers")->fetch_assoc()['count'];
echo "<p>Total suscriptores: <strong>$total</strong></p>";

// Verificar si existen las columnas
$columns = $conn->query("SHOW COLUMNS FROM newsletter_subscribers");
$hasConfirmed = false;
$hasActive = false;

while ($col = $columns->fetch_assoc()) {
    if ($col['Field'] === 'confirmed') $hasConfirmed = true;
    if ($col['Field'] === 'active') $hasActive = true;
}

if ($hasConfirmed) {
    $confirmed = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE confirmed = 1")->fetch_assoc()['count'];
    echo "<p>Suscriptores confirmados: <strong>$confirmed</strong></p>";
} else {
    echo "<p style='color: red;'>⚠️ La columna 'confirmed' NO EXISTE en la tabla</p>";
}

if ($hasActive) {
    $active = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE active = 1")->fetch_assoc()['count'];
    echo "<p>Suscriptores activos: <strong>$active</strong></p>";
} else {
    echo "<p style='color: red;'>⚠️ La columna 'active' NO EXISTE en la tabla</p>";
}

if ($hasConfirmed && $hasActive) {
    $both = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers WHERE confirmed = 1 AND active = 1")->fetch_assoc()['count'];
    echo "<p style='font-size: 20px; color: green;'>✅ Suscriptores confirmados Y activos: <strong>$both</strong></p>";
}

// 4. Verificar si falta alguna columna
echo "<h2>🔧 Verificación de Estructura</h2>";
$requiredColumns = ['id', 'email', 'name', 'token', 'confirmed', 'active', 'created_at'];
$existingColumns = [];

$columns = $conn->query("SHOW COLUMNS FROM newsletter_subscribers");
while ($col = $columns->fetch_assoc()) {
    $existingColumns[] = $col['Field'];
}

echo "<ul>";
foreach ($requiredColumns as $col) {
    if (in_array($col, $existingColumns)) {
        echo "<li style='color: green;'>✅ $col - EXISTE</li>";
    } else {
        echo "<li style='color: red;'>❌ $col - FALTA (necesita migración)</li>";
    }
}
echo "</ul>";

// 5. Si faltan columnas, ofrecer script de migración
$missingColumns = array_diff($requiredColumns, $existingColumns);
if (!empty($missingColumns)) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h3>⚠️ Faltan columnas en la tabla</h3>";
    echo "<p>Se necesita ejecutar este SQL para agregar las columnas faltantes:</p>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace; padding: 10px;'>";
    
    if (!in_array('confirmed', $existingColumns)) {
        echo "ALTER TABLE newsletter_subscribers ADD COLUMN confirmed TINYINT(1) DEFAULT 0 AFTER token;\n";
    }
    if (!in_array('active', $existingColumns)) {
        echo "ALTER TABLE newsletter_subscribers ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER confirmed;\n";
    }
    
    echo "</textarea>";
    echo "<p><strong>Copia este SQL y ejecútalo en phpMyAdmin</strong></p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='test-newsletter.php'>← Volver a Test Newsletter</a></p>";
?>
