<?php
require_once 'config/db.php';

$stmt = $pdo->query("SELECT id, status, service_type, entry_date FROM service_orders ORDER BY id DESC LIMIT 50");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($orders);
echo "</pre>";
