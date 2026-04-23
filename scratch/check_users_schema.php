<?php
require 'config/db.php';
$stmt = $pdo->query('DESCRIBE users');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
