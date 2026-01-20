<?php
require_once 'config/db.php';

try {
    $stmt = $pdo->query("DESCRIBE service_orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in service_orders:\n";
    print_r($columns);
    
    $stmt = $pdo->query("SELECT * FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRoles:\n";
    print_r($roles);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
