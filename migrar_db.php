<?php
/**
 * SCRIPT DE MIGRACIÓN PARA HOSTING (INFINITYFREE)
 * Este script sincroniza la base de datos del hosting con los cambios locales de numeración.
 * 
 * Instrucciones:
 * 1. Sube este archivo (migrar_db.php) a la carpeta raíz de tu sistema en el hosting.
 * 2. Visítalo en tu navegador: http://tusitio.com/migrar_db.php
 * 3. Una vez veas el mensaje de éxito, ELIMINA este archivo por seguridad.
 */

require_once 'config/db.php';

echo "<h2>Actualización de Base de Datos - Sistema Taller</h2>";

try {
    $pdo->beginTransaction();

    // 1. Inicializar la secuencia de Casos Manuales
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_sequences WHERE code = 'service_case'");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $pdo->prepare("UPDATE system_sequences SET current_value = 12 WHERE code = 'service_case'")->execute();
        echo "<p style='color:green'>+ Secuencia 'service_case' actualizada a 12.</p>";
    } else {
        $pdo->prepare("INSERT INTO system_sequences (code, current_value) VALUES ('service_case', 12)")->execute();
        echo "<p style='color:green'>+ Secuencia 'service_case' creada e inicializada en 12.</p>";
    }

    // 2. Corregir el Caso #10928 si existe en el hosting
    $stmt = $pdo->prepare("UPDATE service_orders SET display_id = 13 WHERE id = 10928");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>+ El caso #10928 ahora se visualiza como #0013.</p>";
    } else {
        echo "<p style='color:orange'>i El caso #10928 no se encontró en esta base de datos (o ya estaba actualizado).</p>";
    }

    $pdo->commit();
    echo "<h3 style='color:blue'>Sincronización completada con éxito.</h3>";
    echo "<p><b>IMPORTANTE:</b> Elimina este archivo (migrar_db.php) de tu servidor ahora mismo.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h3 style='color:red'>Error durante la migración:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
