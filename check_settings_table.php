<?php
require_once 'config/db.php';

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n";
    print_r($tables);

    if (in_array('site_settings', $tables)) {
        echo "\nColumns in site_settings:\n";
        $columns = $pdo->query("DESCRIBE site_settings")->fetchAll(PDO::FETCH_ASSOC);
        print_r($columns);
    } else {
        echo "\nTable 'site_settings' not found.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
