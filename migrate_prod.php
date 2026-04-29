<?php
try {
    $pdoProd = new PDO('mysql:host=sql302.infinityfree.com;dbname=if0_41173876_system_taller;charset=utf8mb4', 'if0_41173876', 'KNLEPk9w40tci');
    $pdoProd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    try {
        $pdoProd->exec("ALTER TABLE project_surveys ADD COLUMN trabajos_revisar TEXT AFTER scope_activities;");
        echo "Added trabajos_revisar to PROD\n";
    } catch (Exception $e) {
        echo "Error trabajos_revisar PROD: " . $e->getMessage() . "\n";
    }

    try {
        $pdoProd->exec("ALTER TABLE project_surveys ADD COLUMN notas TEXT AFTER trabajos_revisar;");
        echo "Added notas to PROD\n";
    } catch (Exception $e) {
        echo "Error notas PROD: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    die("Prod Connection failed: " . $e->getMessage());
}
