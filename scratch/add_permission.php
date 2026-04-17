<?php
require_once 'c:/xampp/htdocs/System_taller/config/db.php';

$code = 'module_master_visit_control';
$desc = 'Control Maestro de Visitas (Supervisión de Técnicos)';

$stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
$stmt->execute([$code]);

if (!$stmt->fetch()) {
    $stmtInsert = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");
    if ($stmtInsert->execute([$code, $desc])) {
        echo "Permission created successfully.\n";
    } else {
        echo "Error creating permission.\n";
    }
} else {
    echo "Permission already exists.\n";
}
?>
