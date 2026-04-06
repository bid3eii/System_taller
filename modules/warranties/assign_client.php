<?php
// modules/warranties/assign_client.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_order_id = clean($_POST['service_order_id'] ?? '');
    $equipment_id = clean($_POST['equipment_id'] ?? '');
    
    $client_name = clean($_POST['assign_client_name'] ?? '');
    $client_tax_id = clean($_POST['assign_client_tax_id'] ?? '');
    $client_phone = clean($_POST['assign_client_phone'] ?? '');
    
    $sales_invoice = clean($_POST['sales_invoice_number'] ?? '');
    $warranty_months = (int)clean($_POST['warranty_months'] ?? 12);

    if (empty($service_order_id) || empty($equipment_id) || empty($client_name) || empty($sales_invoice)) {
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
            // Actualizar posibles datos faltantes (teléfono/cédula si antes no los tenía)
            if (!empty($client_phone) || !empty($client_tax_id)) {
                $pdo->prepare("UPDATE clients SET phone = COALESCE(NULLIF(phone,''), ?), tax_id = COALESCE(NULLIF(tax_id,''), ?) WHERE id = ?")
                    ->execute([$client_phone, $client_tax_id, $client_id]);
            }
        } else {
            // New Client
            $stmtNewC = $pdo->prepare("INSERT INTO clients (name, phone, tax_id, is_third_party, created_at) VALUES (?, ?, ?, 0, ?)");
            $stmtNewC->execute([$client_name, $client_phone, $client_tax_id, get_local_datetime()]);
            $client_id = $pdo->lastInsertId();
        }

        // 2. Update service_orders and equipments
        $pdo->prepare("UPDATE service_orders SET client_id = ? WHERE id = ?")->execute([$client_id, $service_order_id]);
        $pdo->prepare("UPDATE equipments SET client_id = ? WHERE id = ?")->execute([$client_id, $equipment_id]);

        // 3. Update warranty
        $calc_date = new DateTime();
        $calc_date->modify("+{$warranty_months} months");
        $end_date = $calc_date->format('Y-m-d');

        $stmtW = $pdo->prepare("UPDATE warranties SET sales_invoice_number = ?, duration_months = ?, end_date = ?, status = 'active' WHERE service_order_id = ?");
        $stmtW->execute([$sales_invoice, $warranty_months, $end_date, $service_order_id]);

        // 4. Log History
        $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'delivered', ?, ?, ?)");
        $stmtHist->execute([$service_order_id, "Equipo vendido/asignado a $client_name. Factura: $sales_invoice", $_SESSION['user_id'], get_local_datetime()]);

        $pdo->commit();

        header("Location: database.php?tab=sold&msg=assigned");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al procesar la venta: " . $e->getMessage());
    }
} else {
    header("Location: database.php");
    exit;
}
