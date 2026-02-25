<?php
// check_cleanup_targets.php
// http://omnidesk.free.nf/check_cleanup_targets.php
require_once 'config/db.php';

$targets = [2, 3627, 3628, 3629, 3630, 3631];

echo "<h1>Confirmación de Datos a Conservar</h1>";
echo "<p>Solo estos 6 registros se mantendrán. Todo lo demás será borrado.</p>";

try {
    $in = implode(',', $targets);
    $stmt = $pdo->query("SELECT id, display_id, entry_doc_number, problem_reported, entry_date, client_id, (SELECT name FROM clients WHERE id = so.client_id) as client_name FROM service_orders so WHERE id IN ($in) ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='10'>
          <tr style='background:#eee'><th>ID DB</th><th>Visual ID</th><th>Doc. Entrada</th><th>Cliente</th><th>Problema</th><th>Ingreso</th></tr>";
    
    foreach ($rows as $row) {
        echo "<tr>
                <td><b>{$row['id']}</b></td>
                <td>" . ($row['display_id'] ?? 'NULL') . "</td>
                <td>" . ($row['entry_doc_number'] ?? 'NULL') . "</td>
                <td>{$row['client_name']}</td>
                <td>{$row['problem_reported']}</td>
                <td>{$row['entry_date']}</td>
              </tr>";
    }
    echo "</table>";

    echo "<p style='color:green'><b>Si esta lista es correcta, procederé a actualizar prod_fix.php para realizar la limpieza final.</b></p>";
    echo "<p>El resultado final será que el próximo equipo en entrar sea el <b>#000007</b>.</p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
