<?php
require_once 'config/db.php';

echo "<pre>";

try {
    $pdo->exec("ALTER TABLE project_surveys ADD COLUMN trabajos_revisar TEXT AFTER scope_activities;");
    echo "Added trabajos_revisar to DB successfully.\n";
} catch (Exception $e) {
    echo "Error trabajos_revisar: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE project_surveys ADD COLUMN notas TEXT AFTER trabajos_revisar;");
    echo "Added notas to DB successfully.\n";
} catch (Exception $e) {
    echo "Error notas: " . $e->getMessage() . "\n";
}

echo "</pre>";
