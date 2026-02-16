<?php
// diagnose_production.php - Upload this to InfinityFree to diagnose timezone issues
require_once 'config/db.php';

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
h3 { color: #555; margin-top: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #007bff; color: white; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
pre { background: #f4f4f4; padding: 10px; border-left: 3px solid #007bff; overflow-x: auto; }
</style>";

echo "<h2>🔍 Diagnóstico de Zona Horaria - Producción</h2>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<hr>";

// 1. PHP Timezone Info
echo "<h3>1️⃣ Configuración PHP</h3>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>date_default_timezone_get()</td><td>" . date_default_timezone_get() . "</td></tr>";
echo "<tr><td>date('Y-m-d H:i:s')</td><td class='success'>" . date('Y-m-d H:i:s') . "</td></tr>";
echo "<tr><td>get_local_datetime()</td><td class='success'>" . get_local_datetime() . "</td></tr>";
echo "<tr><td>gmdate('Y-m-d H:i:s') [UTC]</td><td>" . gmdate('Y-m-d H:i:s') . "</td></tr>";
$offset = (new DateTime())->getOffset() / 3600;
echo "<tr><td>Offset from UTC</td><td>" . $offset . " hours</td></tr>";
echo "</table>";

// 2. MySQL Timezone Info
echo "<h3>2️⃣ Configuración MySQL</h3>";
try {
    $stmt = $pdo->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as utc_now, @@session.time_zone as session_tz, @@global.time_zone as global_tz");
    $result = $stmt->fetch();
    
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>NOW()</td><td class='warning'>" . $result['mysql_now'] . "</td></tr>";
    echo "<tr><td>UTC_TIMESTAMP()</td><td>" . $result['utc_now'] . "</td></tr>";
    echo "<tr><td>@@session.time_zone</td><td>" . $result['session_tz'] . "</td></tr>";
    echo "<tr><td>@@global.time_zone</td><td>" . $result['global_tz'] . "</td></tr>";
    echo "</table>";
    
    // Calculate difference
    $mysql_time = strtotime($result['mysql_now']);
    $php_time = time();
    $diff_hours = ($php_time - $mysql_time) / 3600;
    
    if (abs($diff_hours) > 0.1) {
        echo "<p class='error'>⚠️ MySQL NOW() difiere de PHP por " . round($diff_hours, 2) . " horas</p>";
    } else {
        echo "<p class='success'>✅ MySQL y PHP están sincronizados</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

// 3. Database Schema Check
echo "<h3>3️⃣ Esquema de Tabla 'clients'</h3>";
try {
    $stmt = $pdo->query("SHOW CREATE TABLE clients");
    $result = $stmt->fetch();
    
    echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    
    // Check for DEFAULT CURRENT_TIMESTAMP
    if (strpos($result['Create Table'], 'DEFAULT CURRENT_TIMESTAMP') !== false) {
        echo "<p class='error'>❌ PROBLEMA: La tabla todavía tiene DEFAULT CURRENT_TIMESTAMP</p>";
        echo "<p>Esto significa que la migración NO se ejecutó correctamente.</p>";
    } else {
        echo "<p class='success'>✅ La tabla NO tiene DEFAULT CURRENT_TIMESTAMP</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

// 4. Test Insert
echo "<h3>4️⃣ Prueba de Inserción</h3>";
try {
    $test_time = get_local_datetime();
    echo "<p><strong>Hora que PHP va a insertar:</strong> <span class='success'>" . $test_time . "</span></p>";
    
    $stmt = $pdo->prepare("INSERT INTO clients (name, tax_id, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['TEST DIAGNOSTIC', '999999', '555-9999', 'test@diagnostic.com', 'Test Address', $test_time]);
    $test_id = $pdo->lastInsertId();
    
    echo "<p class='success'>✅ Cliente de prueba insertado con ID: " . $test_id . "</p>";
    
    // Read back what was stored
    $stmt = $pdo->query("SELECT created_at FROM clients WHERE id = $test_id");
    $stored = $stmt->fetch();
    
    echo "<p><strong>Hora almacenada en DB:</strong> <span class='warning'>" . $stored['created_at'] . "</span></p>";
    
    if ($stored['created_at'] === $test_time) {
        echo "<p class='success'>✅ PERFECTO: La hora se guardó correctamente</p>";
    } else {
        echo "<p class='error'>❌ PROBLEMA: La hora almacenada NO coincide con la enviada</p>";
        echo "<p>Diferencia: " . (strtotime($stored['created_at']) - strtotime($test_time)) . " segundos</p>";
    }
    
    // Cleanup
    $pdo->exec("DELETE FROM clients WHERE id = $test_id");
    echo "<p><em>Registro de prueba eliminado.</em></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

// 5. Recent Clients
echo "<h3>5️⃣ Últimos 3 Clientes</h3>";
try {
    $stmt = $pdo->query("SELECT id, name, created_at FROM clients ORDER BY id DESC LIMIT 3");
    $clients = $stmt->fetchAll();
    
    if (count($clients) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>created_at</th><th>Hace cuánto</th></tr>";
        
        foreach ($clients as $client) {
            $created = strtotime($client['created_at']);
            $now = time();
            $diff_minutes = round(($now - $created) / 60);
            
            echo "<tr>";
            echo "<td>" . $client['id'] . "</td>";
            echo "<td>" . htmlspecialchars($client['name']) . "</td>";
            echo "<td>" . $client['created_at'] . "</td>";
            echo "<td>" . $diff_minutes . " minutos</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>📋 Conclusión</h3>";
echo "<p>Si ves errores arriba, copia TODA esta página y envíala para análisis.</p>";
?>
