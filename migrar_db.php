<?php
/**
 * SCRIPT DE LIMPIEZA Y SINCRONIZACIÓN (VERSIÓN FINAL)
 * Este script elimina los datos de prueba y sincroniza los contadores independientes.
 */
require_once 'config/db.php';

echo "<h2>Iniciando Limpieza y Sincronización Final...</h2>";

try {
    $pdo->beginTransaction();

    // 1. Eliminar registros de prueba (test 2, test 4, etc.)
    $testIds = [10929, 10930, 10931, 10932, 10933];
    $placeholders = implode(',', array_fill(0, count($testIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM service_orders WHERE id IN ($placeholders)");
    $stmt->execute($testIds);
    $deletedCount = $stmt->rowCount();
    echo "<p>✅ Se eliminaron $deletedCount registros de prueba.</p>";

    // 2. Sincronizar secuencia para Garantías Manuales
    // El último real fue el #0013 (ID 10928). El próximo debe ser 14.
    $pdo->prepare("INSERT IGNORE INTO system_sequences (code, current_value) VALUES ('warranty_case', 13)")->execute();
    $pdo->prepare("UPDATE system_sequences SET current_value = 13 WHERE code = 'warranty_case'")->execute();
    echo "<p>✅ Secuencia 'warranty_case' sincronizada en 13 (Próxima garantía: #0014).</p>";

    // 3. Sincronizar secuencia para Servicios Estándar
    // El último real fue el #0006 (ID 6). El próximo debe ser 7.
    $pdo->prepare("INSERT IGNORE INTO system_sequences (code, current_value) VALUES ('service_case', 6)")->execute();
    $pdo->prepare("UPDATE system_sequences SET current_value = 6 WHERE code = 'service_case'")->execute();
    echo "<p>✅ Secuencia 'service_case' sincronizada en 6 (Próximo servicio: #0007).</p>";

    $pdo->commit();
    echo "<h3 style='color:green'>Limpieza Completada con ÉXITO.</h3>";
    echo "<p>Ahora los números de caso continuarán correctamente desde donde los dejaste.</p>";
    echo "<p><strong>IMPORTANTE:</strong> Elimine este archivo (migrar_db.php) inmediatamente.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h3 style='color:red'>ERROR: " . $e->getMessage() . "</h3>";
}
?>
