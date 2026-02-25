<?php
// search_audit.php
require_once 'config/db.php';
echo "<h1>Buscando datos en Audit Logs</h1>";
try {
    // Search for actions on service_orders table
    $stmt = $pdo->query("SELECT * FROM audit_logs WHERE table_name = 'service_orders' AND (action = 'RENUMBER_SYNC' OR old_value LIKE '%ARMANDO%' OR new_value LIKE '%ARMANDO%' OR old_value LIKE '%3627%' OR new_value LIKE '%3627%') ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>ID</th><th>Record ID</th><th>Action</th><th>Old</th><th>New</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['record_id']}</td>
                <td>{$row['action']}</td>
                <td>" . htmlspecialchars($row['old_value']) . "</td>
                <td>" . htmlspecialchars($row['new_value']) . "</td>
              </tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
