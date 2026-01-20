<?php
// update_structure_v2.php
require_once 'config/db.php';

try {
    echo "<h2>Updating Database Structure (v2)...</h2>";

    // Add 'invoice_number' to 'service_orders'
    try {
        $pdo->exec("ALTER TABLE `service_orders` ADD COLUMN `invoice_number` VARCHAR(50) DEFAULT NULL AFTER `id`");
        echo "<p style='color: green;'>[OK] Added 'invoice_number' column to 'service_orders'.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color: orange;'>[SKIP] Column 'invoice_number' already exists.</p>";
        } else {
            echo "<p style='color: red;'>[ERROR] Failed to add column: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h3>Update Complete.</h3>";

} catch (Exception $e) {
    echo "<h1>Fatal Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
