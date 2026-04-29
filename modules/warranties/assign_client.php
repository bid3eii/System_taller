<?php
// modules/warranties/assign_client.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_order_ids_str = clean($_POST['service_order_ids'] ?? '');
    
    $client_name = clean($_POST['assign_client_name'] ?? '');
    $client_tax_id = clean($_POST['assign_client_tax_id'] ?? '');
    $client_phone = clean($_POST['assign_client_phone'] ?? '');
    
    $sales_invoice = clean($_POST['sales_invoice_number'] ?? '');
    $warranty_months_array = $_POST['warranty_months'] ?? [];
    $category_id_array = $_POST['category_id'] ?? [];

    if (empty($service_order_ids_str) || empty($client_name) || empty($sales_invoice)) {
        die("Error: Faltan datos requeridos.");
    }

    $order_ids = explode(',', $service_order_ids_str);

    try {
        $pdo->beginTransaction();

        // 1. Resolve Client (Done Once for the batch)
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR (tax_id = ? AND tax_id != '') LIMIT 1");
        $stmtCheck->execute([$client_name, $client_tax_id]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $client_id = $existing['id'];
            if (!empty($client_phone) || !empty($client_tax_id)) {
                $pdo->prepare("UPDATE clients SET phone = COALESCE(NULLIF(phone,''), ?), tax_id = COALESCE(NULLIF(tax_id,''), ?) WHERE id = ?")
                    ->execute([$client_phone, $client_tax_id, $client_id]);
            }
        } else {
            $stmtNewC = $pdo->prepare("INSERT INTO clients (name, phone, tax_id, is_third_party, created_at) VALUES (?, ?, ?, 0, ?)");
            $stmtNewC->execute([$client_name, $client_phone, $client_tax_id, get_local_datetime()]);
            $client_id = $pdo->lastInsertId();
        }

        // Loop through all selected orders
        foreach($order_ids as $service_order_id) {
            $service_order_id = (int)trim($service_order_id);
            if(empty($service_order_id)) continue;

            // Resolve equipment_id from service_order
            $stmtEq = $pdo->prepare("SELECT equipment_id FROM service_orders WHERE id = ?");
            $stmtEq->execute([$service_order_id]);
            $equipment_id = $stmtEq->fetchColumn();

            if($equipment_id) {
                // Determine item-specific warranty months
                $item_warranty_months = isset($warranty_months_array[$service_order_id]) ? (int)$warranty_months_array[$service_order_id] : 12;

                // Calculate custom End Date for this specific item
                $calc_date = new DateTime();
                $calc_date->modify("+{$item_warranty_months} months");
                $end_date = $calc_date->format('Y-m-d');

                // 2. Update service_orders and equipments
                $item_category_id = isset($category_id_array[$service_order_id]) && $category_id_array[$service_order_id] !== '' ? (int)$category_id_array[$service_order_id] : null;
                $pdo->prepare("UPDATE service_orders SET client_id = ? WHERE id = ?")->execute([$client_id, $service_order_id]);
                $pdo->prepare("UPDATE equipments SET client_id = ?, category_id = ? WHERE id = ?")->execute([$client_id, $item_category_id, $equipment_id]);

                // 3. Update warranty
                $stmtW = $pdo->prepare("UPDATE warranties SET sales_invoice_number = ?, duration_months = ?, end_date = ?, status = 'active' WHERE service_order_id = ?");
                $stmtW->execute([$sales_invoice, $item_warranty_months, $end_date, $service_order_id]);

                // 4. Log History
                $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'delivered', ?, ?, ?)");
                $stmtHist->execute([$service_order_id, "Equipo vendido/asignado en lote a $client_name. Factura: $sales_invoice ($item_warranty_months meses de garantía)", $_SESSION['user_id'], get_local_datetime()]);
            }
        }

        $pdo->commit();

        // Redirect with all IDs to trigger batch printing
        header("Location: database.php?tab=sold&msg=assigned&print_cert=" . urlencode($service_order_ids_str));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al procesar la venta: " . $e->getMessage());
    }
} else {
    header("Location: database.php");
    exit;
}
