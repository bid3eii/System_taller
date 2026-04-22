<?php
require 'config/db.php';

try {
    $pdo->exec("ALTER TABLE `service_orders` MODIFY COLUMN `status` enum('received','diagnosing','pending_approval','in_repair','ready','replaced','delivered','cancelled') DEFAULT 'received'");
    echo "ENUM updated successfully.\n";
    
    // Check if column exists before adding
    $stmt = $pdo->query("SHOW COLUMNS FROM `service_orders` LIKE 'replacement_serial_number'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `service_orders` ADD COLUMN `replacement_serial_number` varchar(50) DEFAULT NULL");
        echo "Column replacement_serial_number added successfully.\n";
    } else {
        echo "Column replacement_serial_number already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
