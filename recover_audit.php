<?php
// recover_audit.php - Emergency data recovery from audit_logs
require_once 'config/db.php';
echo "<h1>Recuperación de Datos desde Auditoría</h1>";

try {
    // Step 1: Show the full current state of service_orders
    echo "<h2>Estado Actual de service_orders</h2>";
    $stmt = $pdo->query("SELECT id, display_id, entry_doc_number, owner_name, contact_name, status, service_type FROM service_orders ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Total registros actuales: " . count($rows) . "</p>";
    echo "<table border='1' cellpadding='5'>
      <tr style='background:#ddd'><th>ID DB</th><th>Display ID</th><th>Doc</th><th>Propietario</th><th>Tipo</th><th>Estado</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['display_id']}</td>
                <td>{$row['entry_doc_number']}</td>
                <td>{$row['owner_name']}</td>
                <td>{$row['service_type']}</td>
                <td>{$row['status']}</td>
              </tr>";
    }
    echo "</table>";

    // Step 2: Look in audit_logs for any entry related to ID 3627-3631 and ID 2
    echo "<h2>Búsqueda en Audit Logs (IDs 3627-3631)</h2>";
    $stmt = $pdo->query("SELECT id, record_id, action, old_value, new_value, created_at FROM audit_logs WHERE table_name = 'service_orders' AND record_id IN (2, 3627, 3628, 3629, 3630, 3631) ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Registros encontrados: " . count($rows) . "</p>";
    foreach ($rows as $row) {
        echo "<hr>";
        echo "<b>Record ID:</b> {$row['record_id']} | <b>Acción:</b> {$row['action']} | <b>Fecha:</b> {$row['created_at']}<br>";
        echo "<b>Old:</b> <pre>" . htmlspecialchars($row['old_value']) . "</pre>";
        echo "<b>New:</b> <pre>" . htmlspecialchars($row['new_value']) . "</pre>";
    }

    // Step 3: Search system_sequences 
    echo "<h2>Secuencias del Sistema</h2>";
    $stmt = $pdo->query("SELECT * FROM system_sequences");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "<p>Código: <b>{$row['code']}</b> = {$row['current_value']}</p>";
    }

    echo "<hr><p style='color:green'>Análisis completado.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
