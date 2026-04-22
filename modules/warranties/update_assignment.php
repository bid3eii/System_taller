<?php
// modules/warranties/update_assignment.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_order_id = (int)($_POST['service_order_id'] ?? 0);
    $equipment_id = (int)($_POST['equipment_id'] ?? 0);
    
    $client_name = clean($_POST['edit_client_name'] ?? '');
    $client_tax_id = clean($_POST['edit_client_tax_id'] ?? '');
    $client_phone = clean($_POST['edit_client_phone'] ?? '');
    
    $sales_invoice = clean($_POST['sales_invoice_number'] ?? '');
    $warranty_months = (int)($_POST['warranty_months'] ?? 12);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $return_to = clean($_POST['return_to'] ?? 'database.php');

    if (empty($service_order_id) || empty($client_name) || empty($sales_invoice)) {
        die("Error: Faltan datos requeridos.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Resolve Client
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR (tax_id = ? AND tax_id != '') LIMIT 1");
        $stmtCheck->execute([$client_name, $client_tax_id]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $client_id = $existing['id'];
            // Update existing client info if provided (safely)
            if (!empty($client_phone) || !empty($client_tax_id)) {
                $pdo->prepare("UPDATE clients SET phone = COALESCE(NULLIF(phone,''), ?), tax_id = COALESCE(NULLIF(tax_id,''), ?) WHERE id = ?")
                    ->execute([$client_phone, $client_tax_id, $client_id]);
            }
        } else {
            $stmtNewC = $pdo->prepare("INSERT INTO clients (name, phone, tax_id, is_third_party, created_at) VALUES (?, ?, ?, 0, ?)");
            $stmtNewC->execute([$client_name, $client_phone, $client_tax_id, get_local_datetime()]);
            $client_id = $pdo->lastInsertId();
        }

        // 2. Fetch current warranty dates for recalculation
        $stmtW_orig = $pdo->prepare("SELECT start_date, master_entry_date, created_at FROM warranties WHERE service_order_id = ?");
        $stmtW_orig->execute([$service_order_id]);
        $currW = $stmtW_orig->fetch();

        // Recalculate end_date based on months
        // Try to find the original start of the warranty
        $base_date_str = (!empty($currW['start_date']) && $currW['start_date'] != '0000-00-00') ? $currW['start_date'] : 
                        ((!empty($currW['master_entry_date']) && $currW['master_entry_date'] != '0000-00-00') ? $currW['master_entry_date'] : 
                        ((!empty($currW['created_at'])) ? date('Y-m-d', strtotime($currW['created_at'])) : date('Y-m-d')));
        
        $base_date = new DateTime($base_date_str);
        $base_date->modify("+{$warranty_months} months");
        $end_date = $base_date->format('Y-m-d');

        // 3. Update service_orders and equipments
        $pdo->prepare("UPDATE service_orders SET client_id = ? WHERE id = ?")->execute([$client_id, $service_order_id]);
        $pdo->prepare("UPDATE equipments SET client_id = ?, category_id = ? WHERE id = ?")->execute([$client_id, $category_id, $equipment_id]);

        // 4. Update warranty
        $stmtW = $pdo->prepare("UPDATE warranties SET sales_invoice_number = ?, duration_months = ?, end_date = ? WHERE service_order_id = ?");
        $stmtW->execute([$sales_invoice, $warranty_months, $end_date, $service_order_id]);

        // 5. Log History
        $hist_notes = "Asignación/Venta editada. Cliente: $client_name. Factura: $sales_invoice. Garantía: $warranty_months meses.";
        $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
        $stmtHist->execute([$service_order_id, $hist_notes, $_SESSION['user_id'], get_local_datetime()]);

        $pdo->commit();
        header("Location: " . $return_to . "?tab=sold&msg=updated");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error al actualizar la asignación: " . $e->getMessage());
    }
} else {
    header("Location: database.php");
    exit;
}
