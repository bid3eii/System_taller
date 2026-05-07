<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT * FROM service_orders WHERE id = 17 OR display_id = 17 OR display_id = '17' OR display_id = 'G0017' OR id = 'G0017'");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);

$stmt = $pdo->query("SELECT * FROM warranties WHERE id = 17 OR display_id = '17'");
if ($stmt) {
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
