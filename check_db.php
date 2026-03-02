<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'c:\xampp\htdocs\System_taller\config\db.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n";
    print_r($tables);

    if (in_array('role_permissions', $tables)) {
        echo "role_permissions structure:\n";
        $stmt = $pdo->query("DESCRIBE role_permissions");
        print_r($stmt->fetchAll());

        echo "Sample role_permissions data:\n";
        $stmt = $pdo->query("SELECT * FROM role_permissions LIMIT 5");
        print_r($stmt->fetchAll());
    }
} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>