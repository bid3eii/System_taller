<?php
require_once __DIR__ . '/../../config/db.php';

echo "=== Permissions with 'edit' ===\n";
$r = $pdo->query("SELECT id, code, description FROM permissions WHERE code LIKE '%edit%'");
print_r($r->fetchAll());

echo "\n=== Role Permissions for 'module_edit_entries' ===\n";
$r = $pdo->query("SELECT rp.role_id, r.name as role_name, p.code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.id 
    JOIN roles r ON rp.role_id = r.id
    WHERE p.code = 'module_edit_entries'");
print_r($r->fetchAll());

echo "\n=== User Custom Module Overrides for 'edit_entries' ===\n";
$r = $pdo->query("SELECT ucm.user_id, u.username, ucm.module_name, ucm.is_enabled 
    FROM user_custom_modules ucm 
    JOIN users u ON ucm.user_id = u.id
    WHERE ucm.module_name = 'edit_entries'");
print_r($r->fetchAll());

echo "\n=== All Roles ===\n";
$r = $pdo->query("SELECT id, name FROM roles ORDER BY id");
print_r($r->fetchAll());
