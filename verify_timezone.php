<?php
require_once 'config/db.php';

// Mostrar hora actual
echo "<h2>Verificación de Zona Horaria</h2>";
echo "<p><strong>Hora actual del sistema (PHP):</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Zona horaria configurada:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>get_local_datetime():</strong> " . get_local_datetime() . "</p>";
echo "<hr>";

// Verificar MySQL
$stmt = $pdo->query("SELECT NOW() as mysql_now, @@session.time_zone as tz");
$result = $stmt->fetch();
echo "<h3>MySQL Info</h3>";
echo "<p><strong>MySQL NOW():</strong> " . $result['mysql_now'] . " (probablemente UTC)</p>";
echo "<p><strong>MySQL timezone:</strong> " . $result['tz'] . "</p>";
echo "<hr>";

// Mostrar últimos 3 clientes
echo "<h3>Últimos 3 Clientes Registrados</h3>";
$stmt = $pdo->query("SELECT id, name, created_at FROM clients ORDER BY id DESC LIMIT 3");
$clients = $stmt->fetchAll();

if (count($clients) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>created_at</th><th>Diferencia con hora actual</th></tr>";
    
    foreach ($clients as $client) {
        $created = strtotime($client['created_at']);
        $now = time();
        $diff_hours = round(($now - $created) / 3600, 1);
        
        echo "<tr>";
        echo "<td>" . $client['id'] . "</td>";
        echo "<td>" . htmlspecialchars($client['name']) . "</td>";
        echo "<td>" . $client['created_at'] . "</td>";
        echo "<td>Hace " . $diff_hours . " horas</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay clientes registrados.</p>";
}

echo "<hr>";
echo "<h3>Instrucciones</h3>";
echo "<ol>";
echo "<li>Ve a <a href='modules/clients/add.php'>Agregar Cliente</a></li>";
echo "<li>Crea un nuevo cliente de prueba</li>";
echo "<li>Regresa a esta página y verifica que la hora sea correcta</li>";
echo "</ol>";
?>
