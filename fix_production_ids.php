<?php
// fix_production_ids.php
require_once 'config/db.php';

echo "<h2>Actualización Masiva y Sincronización de Base de Datos</h2>";

try {
    $pdo->beginTransaction();

    // 1. Backfill display_ids
    $stmt = $pdo->prepare("UPDATE service_orders SET display_id = id WHERE display_id IS NULL OR display_id = ''");
    $stmt->execute();
    $updatedCount = $stmt->rowCount();
    echo "<p>✔️ <strong>$updatedCount</strong> órdenes de servicio antiguas han recibido su display_id.</p>";

    // 2. Synchronize sequences to prevent new cases starting from 1
    // Get max ID for warranties
    $stmtG = $pdo->query("SELECT MAX(CAST(display_id AS UNSIGNED)) FROM service_orders WHERE service_type = 'warranty'");
    $maxG = (int)$stmtG->fetchColumn();
    // Get max ID for services
    $stmtS = $pdo->query("SELECT MAX(CAST(display_id AS UNSIGNED)) FROM service_orders WHERE service_type = 'service'");
    $maxS = (int)$stmtS->fetchColumn();

    // Ensure sequences table has rows
    $pdo->exec("INSERT IGNORE INTO system_sequences (code, current_value) VALUES ('warranty_case', 0), ('service_case', 0)");

    // Update sequences
    $pdo->prepare("UPDATE system_sequences SET current_value = ? WHERE code = 'warranty_case'")->execute([$maxG]);
    $pdo->prepare("UPDATE system_sequences SET current_value = ? WHERE code = 'service_case'")->execute([$maxS]);

    echo "<p>✔️ Secuencias sincronizadas. Próxima garantía: <strong>G".str_pad($maxG+1, 4, '0', STR_PAD_LEFT)."</strong>. Próximo servicio: <strong>S".str_pad($maxS+1, 4, '0', STR_PAD_LEFT)."</strong>.</p>";

    $pdo->commit();
    echo "<h3 style='color: green;'>¡Todo actualizado correctamente!</h3>";
    echo "<p>Ya puedes eliminar este archivo (fix_production_ids.php) del servidor.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h3 style='color: red;'>Error Crítico</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "<p><a href='modules/dashboard/index.php'>Volver al sistema</a></p>";
?>
