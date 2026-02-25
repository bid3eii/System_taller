<?php
// find_all.php
require_once 'config/db.php';
echo "<h1>Estado de la tabla service_orders</h1>";
try {
    $stmt = $pdo->query("SELECT id, display_id, entry_doc_number, owner_name, status FROM service_orders ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Total registros: " . count($rows) . "</p>";
    echo "<table border='1'><tr><th>ID DB</th><th>Display ID</th><th>Doc #</th><th>Cliente</th><th>Estado</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['display_id']}</td>
                <td>{$row['entry_doc_number']}</td>
                <td>{$row['owner_name']}</td>
                <td>{$row['status']}</td>
              </tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
