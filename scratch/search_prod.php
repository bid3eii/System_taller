<?php
$host = 'sql302.infinityfree.com';
$db_name = 'if0_41173876_system_taller'; 
$username = 'if0_41173876';
$password = 'KNLEPk9w40tci';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM service_orders WHERE id = 17 OR display_id = 17 OR display_id = '17' OR display_id = 'G0017' OR id = 'G0017'");
    $results = $stmt->fetchAll();
    echo "service_orders:\n";
    print_r($results);
    
    $stmt2 = $pdo->query("SELECT * FROM service_orders WHERE service_type = 'warranty' AND (display_id = 17 OR id = 17)");
    $results2 = $stmt2->fetchAll();
    echo "service_orders (warranties only):\n";
    print_r($results2);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
