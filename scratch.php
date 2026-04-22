<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM equipments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
