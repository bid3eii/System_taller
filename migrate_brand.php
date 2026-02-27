<?php
// One-time migration: Increase brand column for full equipment names
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE equipments MODIFY brand VARCHAR(500)");
    echo "OK: brand column altered to VARCHAR(500)";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage();
}
