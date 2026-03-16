<?php
// fix_bodega_conflict.php
require_once 'config/db.php';

echo "<h2>Reparación de Secuencias y Aislamiento de Bodega</h2>";

try {
    $pdo->beginTransaction();

    // 1. Prefix old backfilled records with 'B' (Bodega) to visually and logically isolate them
    // We identify them because their display_id is exactly their internal ID as a string
    $stmt = $pdo->prepare("UPDATE service_orders SET display_id = CONCAT('B', id) WHERE display_id = CAST(id AS CHAR)");
    $stmt->execute();
    $updatedBodega = $stmt->rowCount();
    echo "<p>✔️ $updatedBodega registros antiguos (bodega) han sido aislados con el prefijo 'B'.</p>";

    // 2. Fix the sequences to look ONLY at real new records
    // Max Warranty (ignores display_ids starting with 'B')
    $stmtG = $pdo->query("SELECT MAX(CAST(display_id AS UNSIGNED)) FROM service_orders WHERE service_type = 'warranty' AND display_id NOT LIKE 'B%'");
    $maxG = (int)$stmtG->fetchColumn();

    // Max Service (ignores display_ids starting with 'B')
    $stmtS = $pdo->query("SELECT MAX(CAST(display_id AS UNSIGNED)) FROM service_orders WHERE service_type = 'service' AND display_id NOT LIKE 'B%'");
    $maxS = (int)$stmtS->fetchColumn();

    // Update Sequences in DB
    $pdo->prepare("UPDATE system_sequences SET current_value = ? WHERE code = 'warranty_case'")->execute([$maxG]);
    $pdo->prepare("UPDATE system_sequences SET current_value = ? WHERE code = 'service_case'")->execute([$maxS]);

    echo "<p>✔️ Secuencias RESTAURADAS exitosamente.</p>";
    echo "<ul>";
    echo "<li>Próxima garantía será: <strong>G".str_pad($maxG+1, 4, '0', STR_PAD_LEFT)."</strong></li>";
    echo "<li>Próximo servicio será: <strong>S".str_pad($maxS+1, 4, '0', STR_PAD_LEFT)."</strong></li>";
    echo "</ul>";

    $pdo->commit();
    echo "<h3 style='color: green;'>¡Conflicto Resulto!</h3>";
    echo "<p>Por favor, sube este archivo al hosting y ábrelo para que se aplique el arreglo remoto.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color: red;'>Error Crítico</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "<p><a href='modules/dashboard/index.php'>Volver al sistema</a></p>";
?>
