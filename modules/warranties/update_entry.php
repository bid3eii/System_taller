<?php
// modules/warranties/update_entry.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Strict Role Check - Only SuperAdmin
if ($_SESSION['role_name'] !== 'SuperAdmin') {
    die("Acceso denegado: Se requiere privilegios de SuperAdmin.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_order_id = clean($_POST['service_order_id'] ?? '');
    $equipment_id = clean($_POST['equipment_id'] ?? '');
    
    $product_code = clean($_POST['product_code'] ?? '');
    $brand = clean($_POST['brand'] ?? '');
    $model = clean($_POST['model'] ?? '');
    $serial = clean($_POST['serial_number'] ?? '');
    $sales_invoice = clean($_POST['sales_invoice_number'] ?? '');
    $warranty_months = (int)clean($_POST['warranty_months'] ?? 0);

    if (empty($service_order_id) || empty($equipment_id)) {
        die("Error: IDs de referencia no encontrados.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Update Equipment details
        $stmtE = $pdo->prepare("UPDATE equipments SET brand = ?, model = ?, serial_number = ? WHERE id = ?");
        $stmtE->execute([$brand, $model, $serial, $equipment_id]);

        // 2. Fetch current warranty dates for recalculation
        $stmtW_orig = $pdo->prepare("SELECT start_date, master_entry_date FROM warranties WHERE service_order_id = ?");
        $stmtW_orig->execute([$service_order_id]);
        $currW = $stmtW_orig->fetch();

        // Recalculate end_date based on months
        $end_date = null;
        if ($warranty_months > 0) {
            // Use existing start_date, or master_entry_date, or today if everything is empty
            $base_date_str = (!empty($currW['start_date']) && $currW['start_date'] != '0000-00-00') ? $currW['start_date'] : 
                            ((!empty($currW['master_entry_date']) && $currW['master_entry_date'] != '0000-00-00') ? $currW['master_entry_date'] : date('Y-m-d'));
            
            $base_date = new DateTime($base_date_str);
            $base_date->modify("+{$warranty_months} months");
            $end_date = $base_date->format('Y-m-d');
        }

        // 3. Update Warranty Record
        $stmtW = $pdo->prepare("UPDATE warranties SET product_code = ?, sales_invoice_number = ?, duration_months = ?, end_date = ? WHERE service_order_id = ?");
        $stmtW->execute([$product_code, $sales_invoice, $warranty_months, $end_date, $service_order_id]);

        // 4. Log in History
        $stmtLog = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
        $history_notes = "Registro editado por SuperAdmin. Cambios realizados en Equipamiento/Garantía.";
        $stmtLog->execute([$service_order_id, $history_notes, $_SESSION['user_id'], get_local_datetime()]);

        $pdo->commit();
        header("Location: database.php?msg=updated");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al actualizar el registro: " . $e->getMessage());
    }
} else {
    header("Location: database.php");
    exit;
}
