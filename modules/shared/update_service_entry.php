<?php
// modules/shared/update_service_entry.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

/**
 * UNIFIED SERVICE ENTRY UPDATE
 * Handles equipment and service order updates for both standard services and warranties.
 */

// Strict Permission Check
if (!can_access_module('edit_entries', $pdo)) {
    die("Acceso denegado: Se requiere privilegios de edición de entradas.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_order_id = clean($_POST['service_order_id'] ?? '');
    $equipment_id = clean($_POST['equipment_id'] ?? '');
    $module_context = clean($_POST['module_context'] ?? 'services'); // 'services' or 'warranties'
    
    $equipment_name = clean($_POST['equipment_name'] ?? '');
    $serial = clean($_POST['serial_number'] ?? '');
    $invoice = clean($_POST['invoice_number'] ?? '');
    $owner = clean($_POST['owner_name'] ?? '');
    $accessories = clean($_POST['accessories'] ?? '');
    $problem = clean($_POST['problem_reported'] ?? '');
    $edit_reason = clean($_POST['edit_reason'] ?? 'Sin motivo especificado');

    if (empty($service_order_id) || empty($equipment_id)) {
        die("Error: IDs de referencia no encontrados.");
    }

    try {
        $pdo->beginTransaction();

        // 0. Fetch OLD values for audit log
        $stmtOld = $pdo->prepare("
            SELECT e.brand, e.model, e.serial_number, so.problem_reported, so.accessories_received, so.owner_name, so.invoice_number
            FROM service_orders so
            JOIN equipments e ON so.equipment_id = e.id
            WHERE so.id = ?
        ");
        $stmtOld->execute([$service_order_id]);
        $old_data = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // 1. Update Equipment details
        // In this unified flow, we store the full name in 'brand' and may clear 'model' if it's being treated as a single descriptor
        $stmtE = $pdo->prepare("UPDATE equipments SET brand = ?, model = '', serial_number = ? WHERE id = ?");
        $stmtE->execute([$equipment_name, $serial, $equipment_id]);

        // 2. Update Service Order
        $stmtS = $pdo->prepare("UPDATE service_orders SET problem_reported = ?, accessories_received = ?, owner_name = ?, invoice_number = ? WHERE id = ?");
        $stmtS->execute([$problem, $accessories, $owner, $invoice, $service_order_id]);

        // 3. Log in Audit and History
        $new_data = [
            'brand' => $equipment_name,
            'serial_number' => $serial,
            'problem_reported' => $problem,
            'accessories_received' => $accessories,
            'owner_name' => $owner,
            'invoice_number' => $invoice
        ];
        
        $audit_path = ($module_context === 'warranties' ? 'warranties/' : 'services/') . 'service_order/equipments';
        log_audit($pdo, $audit_path, $service_order_id, 'UPDATE_MODULAR', $old_data, $new_data, $edit_reason);

        $history_notes = ($module_context === 'warranties' ? "Garantía" : "Servicio") . " editado. Motivo: " . $edit_reason;
        $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
        $stmtH->execute([$service_order_id, $history_notes, $_SESSION['user_id'], get_local_datetime()]);

        $pdo->commit();
        
        // Dynamic Redirection
        $redirect_to = ($module_context === 'warranties' ? '../warranties/index.php' : '../services/index.php');
        header("Location: $redirect_to?msg=updated");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al actualizar la entrada: " . $e->getMessage());
    }
} else {
    header("Location: ../../index.php");
    exit;
}
