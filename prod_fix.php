<?php
/**
 * Production Migration Script - FINAL CLEANUP & RENUMBERING
 * System_Taller
 */

require_once 'config/db.php';

// TARGET RECORDS TO PRESERVE (in desirable sequence)
$targets = [2, 3627, 3628, 3629, 3630, 3631];

echo "<h1>Proceso de Limpieza y Sincronización Final</h1>";

try {
    $pdo->beginTransaction();

    // 1. BACKUP DATA
    echo "<p>Copiando datos de respaldo...</p>";
    $orders_backup = [];
    foreach ($targets as $old_id) {
        $stmt = $pdo->prepare("SELECT * FROM service_orders WHERE id = ?");
        $stmt->execute([$old_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            // Backup history
            $stmtHist = $pdo->prepare("SELECT * FROM service_order_history WHERE service_order_id = ?");
            $stmtHist->execute([$old_id]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
            
            // Backup warranty
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
        throw new Exception("No se encontraron los registros a conservar.");
    }

    // 2. CLEAR ALL DATA (Safe truncate/delete)
    echo "<p>Limpiando tablas de servicios...</p>";
    $pdo->exec("DELETE FROM service_order_history");
    $pdo->exec("DELETE FROM warranties");
    $pdo->exec("DELETE FROM service_orders");
    
    // Attempt cleanup of optional tables if they exist
    try {
        $pdo->exec("DELETE FROM service_order_signatures LIMIT 1000"); 
    } catch (Exception $e) {
        // Table probably doesn't exist, ignore
    }

    // 3. RESTORE & RENUMBER (IDs 1-6)
    echo "<p>Restaurando y re-numerando registros...</p>";
    
    // Ensure display_id column exists
    $pdo->exec("ALTER TABLE service_orders ADD COLUMN IF NOT EXISTS display_id INT NULL AFTER id");

    $new_id = 1;
    foreach ($orders_backup as $backup) {
        $old_id = $backup['original_id'];
        $data = $backup['data'];
        
        // Prepare display_id: preserve the visual number he knows
        // If it already had a display_id, use it, otherwise use original_id
        $display_val = !empty($data['display_id']) ? $data['display_id'] : $old_id;

        // Strip auto-set ID for insert
        unset($data['id']);
        $data['id'] = $new_id;
        $data['display_id'] = $display_val;

        // Build Insert
        $cols = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmtIns = $pdo->prepare("INSERT INTO service_orders ($cols) VALUES ($placeholders)");
        $stmtIns->execute($data);

        // Restore History
        foreach ($backup['history'] as $hist) {
            unset($hist['id']);
            $hist['service_order_id'] = $new_id;
            $colsH = implode(', ', array_keys($hist));
            $placeholdersH = ':' . implode(', :', array_keys($hist));
            $stmtHistRes = $pdo->prepare("INSERT INTO service_order_history ($colsH) VALUES ($placeholdersH)");
            $stmtHistRes->execute($hist);
        }

        // Restore Warranty
        if ($backup['warranty']) {
            $w = $backup['warranty'];
            unset($w['id']);
            $w['service_order_id'] = $new_id;
            $colsW = implode(', ', array_keys($w));
            $placeholdersW = ':' . implode(', :', array_keys($w));
            $stmtWRes = $pdo->prepare("INSERT INTO warranties ($colsW) VALUES ($placeholdersW)");
            $stmtWRes->execute($w);
        }

        echo "<li>Sincronizado: ID {$old_id} -> Nuevo ID {$new_id} (Visual: #{$display_val})</li>";
        $new_id++;
    }

    // 4. RESET SEQUENCES
    echo "<p>Reseteando secuencias de auto-incremento...</p>";
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 7");
    
    // Reset system sequences for entry_doc (next is 7)
    $stmtSeq = $pdo->prepare("INSERT INTO system_sequences (code, current_value) VALUES ('entry_doc', 6) ON DUPLICATE KEY UPDATE current_value = 6");
    $stmtSeq->execute();

    $pdo->commit();
    echo "<h2 style='color:green'>¡Sincronización Completada con ÉXITO!</h2>";
    echo "<p>El próximo equipo que ingrese será el <b>#000007</b>.</p>";
    echo "<p><b>IMPORTANTE:</b> Verifica que los números 3627 al 3631 sigan viéndose igual en el sistema.</p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h2 style='color:red'>Error durante la sincronización:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
