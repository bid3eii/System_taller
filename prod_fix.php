<?php
/**
 * Production Migration Script - System_Taller
 * This script adds the missing 'display_id' column and cleans up order sequences.
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your public_html or htdocs directory.
 * 2. Access it via browser: http://your-domain.com/prod_fix.php
 * 3. Delete this file immediately after use.
 */

// Error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

echo "<h1>Production Sync - System Taller</h1>";

try {
    // 1. Add display_id column if not exists
    $check = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'display_id'");
    if ($check->rowCount() == 0) {
        echo "Adding 'display_id' column... ";
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN display_id INT NULL AFTER id");
        echo "<span style='color:green;'>SUCCESS</span><br>";
    } else {
        echo "Column 'display_id' already exists. <span style='color:blue;'>SKIPPED</span><br>";
    }

    // 2. Perform renumbering and display_id preservation
    // This part is specific to the user's request: renumber 3627->3, 3628->4
    // We only do this if these specific IDs exist as high numbers
    
    $pdo->beginTransaction();

    $reassigned = false;
    
    $targets = [
        3627 => 3,
        3628 => 4
    ];

    foreach ($targets as $old_id => $new_id) {
        $stmt = $pdo->prepare("SELECT id FROM service_orders WHERE id = ?");
        $stmt->execute([$old_id]);
        if ($stmt->fetch()) {
            echo "Renumbering #$old_id to #$new_id... ";
            
            // Check if $new_id is occupied
            $stmtN = $pdo->prepare("SELECT id FROM service_orders WHERE id = ?");
            $stmtN->execute([$new_id]);
            if ($stmtN->fetch()) {
                echo "<span style='color:red;'>FAILED (ID $new_id already occupied)</span><br>";
                continue;
            }

            // Update Main Order
            $pdo->prepare("UPDATE service_orders SET id = ?, display_id = ? WHERE id = ?")->execute([$new_id, $old_id, $old_id]);
            
            // Update History
            $pdo->prepare("UPDATE service_order_history SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);
            
            // Update Warranties
            $pdo->prepare("UPDATE warranties SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);
            
            // Update Diagnosis Images
            $pdo->prepare("UPDATE diagnosis_images SET service_order_id = ? WHERE service_order_id = ?")->execute([$new_id, $old_id]);

            echo "<span style='color:green;'>SUCCESS</span><br>";
            $reassigned = true;
        }
    }

    // 3. Reset Auto-Increment safely
    if ($reassigned) {
        echo "Resetting AUTO_INCREMENT to 5... ";
        $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 5");
        echo "<span style='color:green;'>SUCCESS</span><br>";
    }

    $pdo->commit();

    echo "<h2>Migration Complete!</h2>";
    echo "<p style='color:red;'><strong>IMPORTANT: PLEASE DELETE THIS FILE (prod_fix.php) FROM THE SERVER NOW.</strong></p>";
    echo "<a href='index.php'>Go to Dashboard</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>ERROR: " . $e->getMessage() . "</h2>";
}
