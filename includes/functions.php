<?php
// includes/functions.php

/**
 * Clean input data
 */
function clean($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Check if user has a specific permission
 * Uses the permissions and role_permissions tables
 */
function has_permission($permission_code, $pdo)
{
    if (!isset($_SESSION['user_id']))
        return false;

    // Admins have all permissions
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)
        return true;

    $user_id = $_SESSION['user_id'];

    // Extract base module name (e.g., module_view_all_entries -> view_all_entries)
    $module_name = str_replace('module_', '', $permission_code);

    // 1. Check Custom Overrides (Real-time DB check for exceptions)
    $stmtOverride = $pdo->prepare("SELECT is_enabled FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
    $stmtOverride->execute([$user_id, $module_name]);
    $override = $stmtOverride->fetch();

    if ($override) {
        return (bool) $override['is_enabled'];
    }

    // 2. Real-time DB Check (Role Permissions)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE rp.role_id = ? AND p.code = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $permission_code]);

    if ($stmt->fetchColumn() > 0) {
        return true;
    }

    return false;
}

/**
 * Check if a user can access a specific module
 * Checks user_custom_modules first, then role defaults
 */
function can_access_module($module_name, $pdo)
{
    if (!isset($_SESSION['user_id']))
        return false;
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)
        return true;

    // 1. DB Check Overrides (Real-time DB check for exceptions)
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT is_enabled FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
    $stmt->execute([$user_id, $module_name]);
    $override = $stmt->fetch();

    if ($override) {
        return (bool) $override['is_enabled'];
    }

    // 2. DB Check Role (Real-time DB check for role defaults)
    $role_id = $_SESSION['role_id'];
    $permission_code = 'module_' . $module_name;

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

    return false;
}

/**
 * Log an audit event
 */
function log_audit($pdo, $table, $record_id, $action, $old_val_arr, $new_val_arr)
{
    $user_id = $_SESSION['user_id'] ?? null;
    $old_json = $old_val_arr ? json_encode($old_val_arr) : null;
    $new_json = $new_val_arr ? json_encode($new_val_arr) : null;
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pdo->prepare("INSERT INTO audit_logs (table_name, record_id, action, old_value, new_value, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$table, $record_id, $action, $old_json, $new_json, $user_id, $ip, get_local_datetime()]);
}

/**
 * Format currency
 */
function format_currency($amount)
{
    return 'Q' . number_format($amount, 2);
}

/**
 * Generates a formatted case number (e.g., S0001, G0001).
 * 
 * IMPORTANT ARCHITECTURAL NOTE: 
 * Public search (track.php) must ALWAYS match against 'display_id' and NEVER against 
 * 'id' to avoid collisions. Internal links use 'id'. Fallback to 'id' here is 
 * only for display of legacy records.
 */
function get_order_number($order, $padding = 4)
{
    if (!$order)
        return '-';
    
    $num = !empty($order['display_id']) ? $order['display_id'] : $order['id'];
    
    // If the display_id already starts with a letter (like 'B10927'), return it directly with #
    if (is_string($num) && preg_match('/^[a-zA-Z]/', $num)) {
        return '#' . $num;
    }

    $prefix = '';
    
    // Determine prefix based on service_type
    if (isset($order['service_type'])) {
        if ($order['service_type'] === 'warranty') {
            $prefix = 'G';
        } else {
            $prefix = 'S';
        }
    }
    
    return '#' . $prefix . str_pad($num, $padding, '0', STR_PAD_LEFT);
}

/**
 * Format date
 */
function format_date($date)
{
    return date('d/m/Y H:i', strtotime($date));
}

/**
 * Get next sequence number for a specific code (diagnosis, repair, exit_doc)
 * MUST be called within a transaction
 */
function get_next_sequence($pdo, $code)
{
    // Select for update to lock the row
    $stmt = $pdo->prepare("SELECT current_value FROM system_sequences WHERE code = ? FOR UPDATE");
    $stmt->execute([$code]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        // Create if missing (fallback)
        $pdo->prepare("INSERT INTO system_sequences (code, current_value) VALUES (?, 0)")->execute([$code]);
        $current = 0;
    }

    $next = $current + 1;
    $pdo->prepare("UPDATE system_sequences SET current_value = ? WHERE code = ?")->execute([$next, $code]);

    return $next;
}

/**
 * Update Service Order Status and Assign Numbers
 */
function update_service_status($pdo, $order_id, $new_status, $note, $user_id, $new_serial = null)
{
    // Check if we started the transaction or if the caller did
    $transactionStarted = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transactionStarted = true;
    }

    try {
        // Get current info locked
        $stmt = $pdo->prepare("SELECT equipment_id, status, diagnosis_number, repair_number, exit_doc_number FROM service_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Orden no encontrada");
        }

        // If new serial is provided, update the equipment record
        if (!empty($new_serial)) {
            $stmtUpdEquip = $pdo->prepare("UPDATE equipments SET serial_number = ? WHERE id = ?");
            $stmtUpdEquip->execute([$new_serial, $order['equipment_id']]);
            
            // Append change to note
            $note = "[EQUIPO REEMPLAZADO] Nuevo S/N: $new_serial\n" . $note;
        }

        // Updates array
        $updates = [];
        $params = [];

        $updates[] = "status = ?";
        $params[] = $new_status;

        // Check triggers for Number Assignment

        // 1. Diagnosis Number: Assign when moving to 'diagnosing' AND not yet assigned
        if ($new_status === 'diagnosing' && is_null($order['diagnosis_number'])) {
            $next_val = get_next_sequence($pdo, 'diagnosis');
            $updates[] = "diagnosis_number = ?";
            $params[] = $next_val;
        }

        // 2. Repair Number: Assign when moving to 'in_repair' AND not yet assigned
        if ($new_status === 'in_repair' && is_null($order['repair_number'])) {
            $next_val = get_next_sequence($pdo, 'repair');
            $updates[] = "repair_number = ?";
            $params[] = $next_val;
        }

        // 3. Exit Doc Number: Assign when moving to 'delivered' AND not yet assigned
        // Note: Can also be assigned manually if needed, but this ensures it's there on delivery
        if ($new_status === 'delivered' && is_null($order['exit_doc_number'])) {
            $next_val = get_next_sequence($pdo, 'exit_doc');
            $updates[] = "exit_doc_number = ?";
            $params[] = $next_val;
        }

        // Execute Update
        $params[] = $order_id;
        $sql = "UPDATE service_orders SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        // Log History
        $action_label = $new_status; // Can be mapped if needed, but keeping it simple
        $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$order_id, $new_status, $note, $user_id, get_local_datetime()]);

        if ($transactionStarted) {
            $pdo->commit();
        }
        return true;

    } catch (Exception $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Check if the user has one of the required roles
 * Accepts an array of role names or IDs
 */
function has_role($roles, $pdo)
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }

    $role_id = $_SESSION['role_id'];

    // Get the name of the user's role
    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role_name = $stmt->fetchColumn();

    if ($role_name && in_array($role_name, (array) $roles)) {
        return true;
    }

    return false;
}

