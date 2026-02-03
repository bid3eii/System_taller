<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (in_array('navbar_order', $columns)) {
    echo "Column navbar_order exists.";
} else {
    echo "Column navbar_order does NOT exist.";
    // Try to add it
    $pdo->exec("ALTER TABLE users ADD COLUMN navbar_order TEXT DEFAULT NULL");
    echo " -> Created column navbar_order.";
}
?>
