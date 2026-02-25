<?php
/**
 * Production Migration & Deletion Script - System_Taller
 * This script removes the unwanted Record #1 (vsvsdv) and adds the 'display_id' column.
 * 
 * INSTRUCTIONS:
 * 1. Pull changes to your hosting (to update this file).
 * 2. Access via browser: http://your-domain.com/prod_fix.php
 * 3. Delete this file immediately after.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

echo "<h1>Production Cleanup & Sync - System Taller</h1>";

try {
    $pdo->beginTransaction();

    // 1. Delete unwanted Record #1 and its associated data
    $id_to_delete = 1;

    // Check if ID 1 exists
    $stmtCheck = $pdo->prepare("SELECT id FROM service_orders WHERE id = ?");
    $stmtCheck->execute([$id_to_delete]);
    
    if ($stmtCheck->fetch()) {
        echo "Deleting Record #1 and associated data... ";
        
        // Delete from dependent tables first
        $pdo->prepare("DELETE FROM service_order_history WHERE service_order_id = ?")->execute([$id_to_delete]);
        $pdo->prepare("DELETE FROM diagnosis_images WHERE service_order_id = ?")->execute([$id_to_delete]);
        $pdo->prepare("DELETE FROM warranties WHERE service_order_id = ?")->execute([$id_to_delete]);
        
        // Delete the service order itself
        $pdo->prepare("DELETE FROM service_orders WHERE id = ?")->execute([$id_to_delete]);
        
        echo "<span style='color:green;'>SUCCESS</span><br>";
    } else {
        echo "Record #1 not found. <span style='color:blue;'>SKIPPED</span><br>";
    }

    // 2. Add display_id column if not exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'display_id'");
    if ($checkCol->rowCount() == 0) {
        echo "Adding 'display_id' column... ";
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN display_id INT NULL AFTER id");
        echo "<span style='color:green;'>SUCCESS</span><br>";
    } else {
        echo "Column 'display_id' already exists. <span style='color:blue;'>SKIPPED</span><br>";
    }

    // 3. Perform renumbering for targets if they exist
    $targets = [3627 => 3, 3628 => 4];
    foreach ($targets as $old_id => $new_id) {
        $stmtT = $pdo->prepare("SELECT id FROM service_orders WHERE id = ?");
        $stmtT->execute([$old_id]);
        if ($stmtT->fetch()) {
            echo "Renumbering #$old_id to #$new_id... ";
            
            // Final safety check for target occupancy
            $stmtOccupied = $pdo->prepare("SELECT id FROM service_orders WHERE id = ?");
            $stmtOccupied->execute([$new_id]);
            if ($stmtOccupied->fetch()) {
                echo "<span style='color:red;'>FAILED (ID $new_id already occupied)</span><br>";
                continue;
            }

            $pdo->prepare("UPDATE service_orders SET id = ?, display_id = ? WHERE id = ?")->execute([$new_id, $old_id, $old_id]);
            $pdo->prepare("UPDATE service_order_history SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);
            $pdo->prepare("UPDATE warranties SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);
            $pdo->prepare("UPDATE diagnosis_images SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);

            echo "<span style='color:green;'>SUCCESS</span><br>";
        }
    }

    // 4. Reset Auto-Increment safely
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 5");
    echo "Sequence reset to 5... <span style='color:green;'>SUCCESS</span><br>";

    $pdo->commit();

    echo "<h2>Production Synchronization Complete!</h2>";
    echo "<p style='color:red;'><strong>IMPORTANT: DELETE THIS FILE (prod_fix.php) NOW.</strong></p>";
    echo "<a href='index.php'>Return to System</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>ERROR: " . $e->getMessage() . "</h2>";
}
