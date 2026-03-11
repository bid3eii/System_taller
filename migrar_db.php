<?php
/**
 * SCRIPT DE MIGRACIÓN PARA HOSTING (FIN DE TAREA)
 * Este script sincroniza la base de datos de producción con el nuevo sistema de numeración independiente.
 */
require_once 'config/db.php';

echo "<h2>Iniciando Sincronización de Base de Datos...</h2>";

try {
    $pdo->beginTransaction();

    // 1. Crear secuencia para Garantías Manuales
    $pdo->prepare("INSERT IGNORE INTO system_sequences (code, current_value) VALUES ('warranty_case', 15)")->execute();
    $pdo->prepare("UPDATE system_sequences SET current_value = 15 WHERE code = 'warranty_case'")->execute();
    echo "<p>✅ Secuencia 'warranty_case' sincronizada en 15.</p>";

    // 2. Sincronizar secuencia para Servicios Estándar
    $pdo->prepare("INSERT IGNORE INTO system_sequences (code, current_value) VALUES ('service_case', 7)")->execute();
    $pdo->prepare("UPDATE system_sequences SET current_value = 7 WHERE code = 'service_case'")->execute();
    echo "<p>✅ Secuencia 'service_case' sincronizada en 7.</p>";

    // 3. Corregir IDs visuales recientes para Garantías
    $pdo->prepare("UPDATE service_orders SET display_id = 13 WHERE id = 10928")->execute();
    $pdo->prepare("UPDATE service_orders SET display_id = 14 WHERE id = 10929")->execute();
    $pdo->prepare("UPDATE service_orders SET display_id = 15 WHERE id = 10930")->execute();
    echo "<p>✅ Garantías recientes (#0013, #0014, #0015) corregidas.</p>";

    // 4. Corregir ID visual reciente para Servicio Estándar
    $pdo->prepare("UPDATE service_orders SET display_id = 7 WHERE id = 10931")->execute();
    echo "<p>✅ Servicio reciente (#0007) corregido para independencia de numeración.</p>";

    $pdo->commit();
    echo "<h3 style='color:green'>Sincronización Completada con ÉXITO.</h3>";
    echo "<p><strong>IMPORTANTE:</strong> Elimine este archivo (migrar_db.php) de su servidor inmediatamente por seguridad.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h3 style='color:red'>ERROR: " . $e->getMessage() . "</h3>";
}
?>
