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
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) return true;
    
    // 1. Check Session Cache (Primary)
    if (isset($_SESSION['permissions_codes']) && in_array($permission_code, $_SESSION['permissions_codes'])) {
        return true;
    }

    // 2. Real-time DB Check (Fallback)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE rp.role_id = ? AND p.code = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $permission_code]);
    
    if ($stmt->fetchColumn() > 0) {
        // Update cache for current request cycle
        if (!isset($_SESSION['permissions_codes'])) $_SESSION['permissions_codes'] = [];
        if (!in_array($permission_code, $_SESSION['permissions_codes'])) {
            $_SESSION['permissions_codes'][] = $permission_code;
        }
        return true;
    }

    return false;
}

/**
 * Check if a user can access a specific module
 * Checks user_custom_modules first, then role defaults
 */
function can_access_module($module_name, $pdo) {
    if (!isset($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) return true;

    // 1. Check Session Cache for Custom Overrides
    if (isset($_SESSION['module_overrides']) && isset($_SESSION['module_overrides'][$module_name])) {
        return (bool)$_SESSION['module_overrides'][$module_name];
    }
    
    // 2. Check Session Cache for Role Permissions
    $permission_code = 'module_' . $module_name;
    if (isset($_SESSION['permissions_codes']) && in_array($permission_code, $_SESSION['permissions_codes'])) {
        return true;
    }

    // 3. Fallback to DB if not in session (or just logged in)
    $user_id = $_SESSION['user_id'];
    
    // DB Check Overrides
    $stmt = $pdo->prepare("SELECT is_enabled FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
    $stmt->execute([$user_id, $module_name]);
    $override = $stmt->fetch();

    if ($override) {
        // Update cache for this request
        if (!isset($_SESSION['module_overrides'])) $_SESSION['module_overrides'] = [];
        $_SESSION['module_overrides'][$module_name] = $override['is_enabled'];
        return (bool)$override['is_enabled'];
    }

    // DB Check Role
    $role_id = $_SESSION['role_id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM role_permissions rp 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE rp.role_id = ? AND p.code = ?
    ");
    $stmt->execute([$role_id, $permission_code]);
    
    if ($stmt->fetchColumn() > 0) {
        // Update cache
        if (!isset($_SESSION['permissions_codes'])) $_SESSION['permissions_codes'] = [];
        if (!in_array($permission_code, $_SESSION['permissions_codes'])) $_SESSION['permissions_codes'][] = $permission_code;
        return true;
    }

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

    $stmt = $pdo->prepare("INSERT INTO audit_logs (table_name, record_id, action, old_value, new_value, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$table, $record_id, $action, $old_json, $new_json, $user_id, $ip, get_local_datetime()]);
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

/**
 * Get next sequence number for a specific code (diagnosis, repair, exit_doc)
 * MUST be called within a transaction
 */
function get_next_sequence($pdo, $code) {
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
function update_service_status($pdo, $order_id, $new_status, $note, $user_id) {
    // Check if we started the transaction or if the caller did
    $transactionStarted = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transactionStarted = true;
    }
    
    try {
        // Get current info locked
        $stmt = $pdo->prepare("SELECT status, diagnosis_number, repair_number, exit_doc_number FROM service_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Orden no encontrada");
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
        $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id) VALUES (?, ?, ?, ?)")
            ->execute([$order_id, $new_status, $note, $user_id]);
        
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

