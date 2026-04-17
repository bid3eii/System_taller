<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE schedule_events");
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
