<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'c:\xampp\htdocs\System_taller\config\db.php';

try {
    $stmt = $pdo->query("DESCRIBE permissions");
    print_r($stmt->fetchAll());
} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>