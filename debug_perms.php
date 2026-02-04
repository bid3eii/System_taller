<?php
require_once 'config/db.php';

// Assuming Role ID 2 is Technician, or we find it by name
$role_name = 'Técnico';
$stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
$stmt->execute([$role_name]);
$role_id = $stmt->fetchColumn();

if (!$role_id) {
    echo "Role '$role_name' not found.<br>";
    // Fallback to checking all roles or ID 2
    $role_id = 2; 
}

echo "<h1>Debug All Permission Codes</h1>";
$stmt = $pdo->query("SELECT code FROM permissions WHERE code LIKE 'module_%'");
$all_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<ul>";
foreach ($all_perms as $code) {
    echo "<li>$code</li>";
}
echo "</ul>";
?>
