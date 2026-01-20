<?php
// includes/functions.php

/**
 * Clean input data
 */
function clean($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Check if user has a specific permission
 * Uses the permissions and role_permissions tables
 */
function has_permission($permission_code, $pdo) {
    if (!isset($_SESSION['user_id'])) return false;
    
    // Admins have all permissions
    if ($_SESSION['role_name'] === 'Administrador') return true;
    
    // Check if permission is cached in session to avoid DB calls
    if (isset($_SESSION['permissions']) && in_array($permission_code, $_SESSION['permissions'])) {
        return true;
    }

    // Since we don't query on every call, we load permissions at login.
    // However, if we need strict realtime checking, we would query here.
    return false;
}

/**
 * Check if a user can access a specific module
 * Checks user_custom_modules first, then role defaults
 */
function can_access_module($module_name, $pdo) {
    if (!isset($_SESSION['user_id'])) return false;
    if ($_SESSION['role_name'] === 'Administrador') return true;

    $user_id = $_SESSION['user_id'];
    
    // Check User Custom Overrides
    $stmt = $pdo->prepare("SELECT is_enabled FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
    $stmt->execute([$user_id, $module_name]);
    $override = $stmt->fetch();

    if ($override) {
        return (bool)$override['is_enabled'];
    }

    // Check Role Permissions in DB
    $permission_code = 'module_' . $module_name;
    
    // Get role_id
    $role_id = $_SESSION['role_id'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE rp.role_id = ? AND p.code = ?
    ");
    $stmt->execute([$role_id, $permission_code]);
    
    if ($stmt->fetchColumn() > 0) {
        return true;
    }

    // Default to FALSE if no permission found (Closed by default)
    return false;
}

/**
 * Log an audit event
 */
function log_audit($pdo, $table, $record_id, $action, $old_val_arr, $new_val_arr) {
    $user_id = $_SESSION['user_id'] ?? null;
    $old_json = $old_val_arr ? json_encode($old_val_arr) : null;
    $new_json = $new_val_arr ? json_encode($new_val_arr) : null;
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("INSERT INTO audit_logs (table_name, record_id, action, old_value, new_value, user_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$table, $record_id, $action, $old_json, $new_json, $user_id, $ip]);
}

/**
 * Format currency
 */
function format_currency($amount) {
    return 'Q' . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date) {
    return date('d/m/Y H:i', strtotime($date));
}
?>
