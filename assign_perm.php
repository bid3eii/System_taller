<?php
require_once 'config/db.php';

$role_name = 'Técnico';
$stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
$stmt->execute([$role_name]);
$role_id = $stmt->fetchColumn();

if ($role_id) {
    // Check if assigned
    $perm_code = 'module_new_warranty';
    $stmtP = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
    $stmtP->execute([$perm_code]);
    $perm_id = $stmtP->fetchColumn();

    if ($perm_id) {
        $stmtCheck = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ? AND permission_id = ?");
        $stmtCheck->execute([$role_id, $perm_id]);
        if (!$stmtCheck->fetch()) {
            echo "Permission '$perm_code' NOT assigned to Technician. Assigning now...<br>";
             $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)")
                 ->execute([$role_id, $perm_id]);
             echo "Assigned.";
        } else {
             echo "Permission '$perm_code' IS ALREADY assigned to Technician.<br>";
        }
    }
}
?>
