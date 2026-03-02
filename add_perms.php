<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'c:\xampp\htdocs\System_taller\config\db.php';

$missing = ['surveys_add', 'surveys_edit', 'surveys_delete', 'surveys_view_all'];
foreach ($missing as $m) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO modules (name, description) VALUES (?, ?)");
        $stmt->execute([$m, 'Permiso para ' . $m]);
        echo "Inserted/Ignored $m\n";
    } catch (\PDOException $e) {
        echo "Error $m: " . $e->getMessage() . "\n";
    }
}
?>