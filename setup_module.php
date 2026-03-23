<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';

try {
    $code = 'module_reporte_facturas';
    $desc = 'Reporte Facturas (Proyectos)';
    
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
    $stmt->execute([$code]);
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO permissions (code, description) VALUES ('$code', '$desc')");
        $perm_id = $pdo->lastInsertId();
        
        $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, $perm_id)");
        $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (7, $perm_id)");
        
        echo "Permission mapped successfully!\n";
    } else {
        echo "Permission already mapped.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
