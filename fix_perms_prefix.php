<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require 'c:\xampp\htdocs\System_taller\config\db.php';

try {
    $perms = ['surveys', 'surveys_add', 'surveys_edit', 'surveys_delete', 'surveys_view_all'];

    // First let's clean up any incorrect entries without the 'module_' prefix
    foreach ($perms as $p) {
        $stmtDel = $pdo->prepare("DELETE FROM permissions WHERE code = ?");
        $stmtDel->execute([$p]);
    }

    // Now insert the correct ones with 'module_' prefix
    foreach ($perms as $p) {
        $code = 'module_' . $p;

        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            $stmtInsert = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");
            $stmtInsert->execute([$code, "Permiso: $p"]);
            echo "Inserted correct permission: $code\n";
        }
    }

    // Grant 'surveys' and 'surveys_add' to role_id = 3 (Técnico)
    $grantToTech = ['module_surveys', 'module_surveys_add'];
    foreach ($grantToTech as $code) {
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$code]);
        $perm_id = $stmt->fetchColumn();

        if ($perm_id) {
            $stmtCheck = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 3 AND permission_id = ?");
            $stmtCheck->execute([$perm_id]);
            if (!$stmtCheck->fetch()) {
                $stmtInsertRole = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (3, ?)");
                $stmtInsertRole->execute([$perm_id]);
                echo "Granted $code (ID: $perm_id) to role 3 (Técnico)\n";
            } else {
                echo "Role 3 already has $code\n";
            }
        }
    }

    // Grant everything to role_id = 1 (Admin)
    foreach ($perms as $p) {
        $code = 'module_' . $p;
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
        $stmt->execute([$code]);
        $perm_id = $stmt->fetchColumn();

        if ($perm_id) {
            $stmtCheck = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = ?");
            $stmtCheck->execute([$perm_id]);
            if (!$stmtCheck->fetch()) {
                $stmtInsertRole = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, ?)");
                $stmtInsertRole->execute([$perm_id]);
                echo "Granted $code to role 1 (Admin)\n";
            }
        }
    }

} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>