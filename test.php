<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';
$stmt = $pdo->query("SELECT id, title, invoice_number, payment_status FROM project_surveys");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
