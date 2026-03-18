<?php
$pdo = new PDO('mysql:host=localhost;dbname=system_taller;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT * FROM permissions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
