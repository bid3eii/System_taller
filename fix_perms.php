<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'c:\xampp\htdocs\System_taller\config\db.php';

try {
    // 1. Check/Insert missing permissions
    $perms = ['surveys', 'surveys_add', 'surveys_edit', 'surveys_delete', 'surveys_view_all'];
    foreach ($perms as $p) {
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$p]);
        if (!$stmt->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");
            $stmtInsert->execute([$p, "Permiso: $p"]);
            echo "Inserted permission: $p\n";
        }
    }

    // 2. Grant 'surveys' and 'surveys_add' to role_id = 3 (Técnico)
    $grantToTech = ['surveys', 'surveys_add'];
    foreach ($grantToTech as $p) {
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$p]);
        $perm_id = $stmt->fetchColumn();

        if ($perm_id) {
            $stmtCheck = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 3 AND permission_id = ?");
            $stmtCheck->execute([$perm_id]);
            if (!$stmtCheck->fetch()) {
                $stmtInsertRole = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (3, ?)");
                $stmtInsertRole->execute([$perm_id]);
                echo "Granted $p (ID: $perm_id) to role 3 (Técnico)\n";
            } else {
                echo "Role 3 already has $p\n";
            }
        }
    }

    // Also grant everything to role_id = 1 (Admin) just in case
    foreach ($perms as $p) {
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$p]);
        $perm_id = $stmt->fetchColumn();

        if ($perm_id) {
            $stmtCheck = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = ?");
            $stmtCheck->execute([$perm_id]);
            if (!$stmtCheck->fetch()) {
                $stmtInsertRole = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, ?)");
                $stmtInsertRole->execute([$perm_id]);
                echo "Granted $p to Admin\n";
            }
        }
    }

} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>