<?php
// list_all_tables.php - List all tables and search clients for lost records
require_once 'config/db.php';
echo "<h1>Tablas disponibles en la base de datos</h1>";
try {
    // List all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $t) {
        echo "<li>$t</li>";
    }
    echo "</ul>";

    // Search clients table for clients with our names
    echo "<h2>Clientes (Armando, Jose, Hugo)</h2>";
    $stmt = $pdo->query("SELECT * FROM clients WHERE UPPER(name) LIKE '%ARMANDO%' OR UPPER(name) LIKE '%JOSE%' OR UPPER(name) LIKE '%HUGO%' OR UPPER(name) LIKE '%BALLADAR%' ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Encontrados: " . count($rows) . "</p>";
    if ($rows) {
        $cols = array_keys($rows[0]);
        echo "<table border='1' cellpadding='4'><tr style='background:#ddd'>";
        foreach ($cols as $c) echo "<th>$c</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($r as $v) echo "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show warranty table structure if exists
    if (in_array('warranties', $tables)) {
        echo "<h2>Tabla warranties (primeras 20 filas)</h2>";
        $stmt = $pdo->query("SELECT * FROM warranties ORDER BY id DESC LIMIT 20");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Total: " . count($rows) . "</p>";
        if ($rows) {
            $cols = array_keys($rows[0]);
            echo "<table border='1' cellpadding='4'><tr style='background:#ddd'>";
            foreach ($cols as $c) echo "<th>$c</th>";
            echo "</tr>";
            foreach ($rows as $r) {
                echo "<tr>";
                foreach ($r as $v) echo "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    // Show any table that has service_order_id or client_id columns
    echo "<h2>Tablas con columna client_id</h2>";
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'client_id'");
    $tables_with_client = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>" . implode(', ', $tables_with_client) . "</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
