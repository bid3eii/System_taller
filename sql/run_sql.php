<?php
require_once '../config/db.php';

try {
    $sql = file_get_contents('create_tools_tables.sql');
    $pdo->exec($sql);
    echo "Tables created successfully and data inserted.";
} catch (PDOException $e) {
    die("Error executing SQL: " . $e->getMessage());
}
?>
