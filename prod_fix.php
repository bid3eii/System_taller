<?php
/**
 * Production Migration Script - FINAL CLEANUP & RENUMBERING (FIXED)
 * System_Taller
 */

require_once 'config/db.php';

// TARGET RECORDS TO PRESERVE
$targets = [2, 3627, 3628, 3629, 3630, 3631];

echo "<h1>Proceso de Limpieza y Sincronización Final (V2)</h1>";

try {
    // 0. PRE-FLIGHT CHECKS & DDL (Implicitly commits/ends transactions)
    echo "<p>Verificando estructura inicial...</p>";
    $pdo->exec("ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS display_id INT NULL AFTER id");

    // 1. BACKUP DATA (In memory)
    echo "<p>Copiando datos de respaldo...</p>";
    $orders_backup = [];
    foreach ($targets as $old_id) {
        $stmt = $pdo->prepare("SELECT * FROM service_orders WHERE id = ?");
        $stmt->execute([$old_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $stmtHist = $pdo->prepare("SELECT * FROM service_order_history WHERE service_order_id = ?");
            $stmtHist->execute([$old_id]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtW = $pdo->prepare("SELECT * FROM warranties WHERE service_order_id = ?");
            $stmtW->execute([$old_id]);
            $warranty = $stmtW->fetch(PDO::FETCH_ASSOC);

            $orders_backup[] = [
                'original_id' => $old_id,
                'data' => $order,
                'history' => $history,
                'warranty' => $warranty
            ];
        }
    }

    if (empty($orders_backup)) {
        throw new Exception("No se encontraron los registros a conservar del respaldo.");
    }

    // 2. ATOMIC DATA OPERATIONS
    echo "<p>Iniciando restauración atómica...</p>";
    $pdo->beginTransaction();

    // Cleanup
    $pdo->exec("DELETE FROM service_order_history");
    $pdo->exec("DELETE FROM warranties");
    $pdo->exec("DELETE FROM service_orders");
    
    try {
        $pdo->exec("DELETE FROM service_order_signatures LIMIT 1000"); 
    } catch (Exception $e) { /* ignore missing table */ }

    // Restore & Renumber
    $new_id = 1;
    foreach ($orders_backup as $backup) {
        $data = $backup['data'];
        $display_val = !empty($data['display_id']) ? $data['display_id'] : $backup['original_id'];

        unset($data['id']);
        $data['id'] = $new_id;
        $data['display_id'] = $display_val;

        $cols = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmtIns = $pdo->prepare("INSERT INTO service_orders ($cols) VALUES ($placeholders)");
        $stmtIns->execute($data);

        foreach ($backup['history'] as $hist) {
            unset($hist['id']);
            $hist['service_order_id'] = $new_id;
            $colsH = implode(', ', array_keys($hist));
            $placeholdersH = ':' . implode(', :', array_keys($hist));
            $pdo->prepare("INSERT INTO service_order_history ($colsH) VALUES ($placeholdersH)")->execute($hist);
        }

        if ($backup['warranty']) {
            $w = $backup['warranty'];
            unset($w['id']);
            $w['service_order_id'] = $new_id;
            $colsW = implode(', ', array_keys($w));
            $placeholdersW = ':' . implode(', :', array_keys($w));
            $pdo->prepare("INSERT INTO warranties ($colsW) VALUES ($placeholdersW)")->execute($w);
        }

        echo "<li>Sincronizado: ID {$backup['original_id']} -> Nuevo ID {$new_id} (Visual: #{$display_val})</li>";
        $new_id++;
    }

    // Save Data
    $pdo->commit();
    echo "<p>Datos restaurados correctamente.</p>";

    // 3. POST-DATA DDL & SEQUENCES
    echo "<p>Reseteando secuencias finales...</p>";
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 7");
    
    $stmtSeq = $pdo->prepare("INSERT INTO system_sequences (code, current_value) VALUES ('entry_doc', 6) ON DUPLICATE KEY UPDATE current_value = 6");
    $stmtSeq->execute();

    echo "<h2 style='color:green'>¡Sincronización Completada con ÉXITO!</h2>";
    echo "<p>El próximo equipo que ingrese será el <b>#000007</b>.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h2 style='color:red'>Error durante la sincronización:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
