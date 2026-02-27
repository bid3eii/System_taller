<?php
// Comprehensive Migration Script for Production Database
require_once 'config/db.php';

header('Content-Type: text/plain');

function addColumnIfNotExists($pdo, $table, $column, $definition) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "✅ Column '$column' added to table '$table'.\n";
        } else {
            echo "ℹ️ Column '$column' already exists in table '$table'.\n";
        }
    } catch (Exception $e) {
        echo "❌ Error adding '$column' to '$table': " . $e->getMessage() . "\n";
    }
}

echo "--- Starting Database Migration ---\n";

// 1. Equipments table migrations
try {
    $pdo->exec("ALTER TABLE equipments MODIFY brand VARCHAR(500)");
    echo "✅ Column 'brand' in table 'equipments' updated to VARCHAR(500).\n";
} catch (Exception $e) {
    echo "ℹ️ Note for 'brand' update: " . $e->getMessage() . "\n";
}

// 2. Clients table migrations
addColumnIfNotExists($pdo, 'clients', 'is_third_party', "TINYINT(1) DEFAULT 0");

// 3. Service Orders table migrations (just in case)
addColumnIfNotExists($pdo, 'service_orders', 'service_type', "ENUM('service', 'warranty') DEFAULT 'service'");

// 4. Users table migrations (for navbar order)
addColumnIfNotExists($pdo, 'users', 'navbar_order', "TEXT DEFAULT NULL");

echo "--- Migration Finished ---\n";
?>
