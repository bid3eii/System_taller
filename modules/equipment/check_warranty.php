<?php
// modules/equipment/check_warranty.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

$serial = clean($_REQUEST['serial_number'] ?? '');

if (empty($serial)) {
    echo json_encode(['success' => false, 'message' => 'Número de serie vacío']);
    exit;
}

try {
    // 1. Search in EQUIPMENT table to find the equipment ID
    $stmtEq = $pdo->prepare("SELECT id, brand, model, submodel, type, client_id FROM equipments WHERE serial_number = ? LIMIT 1");
    $stmtEq->execute([$serial]);
    $equipment = $stmtEq->fetch();

    if ($equipment) {
        // Equipment found, now check for WARRANTY
        // We look for the MOST RECENT warranty record for this equipment
        $stmtW = $pdo->prepare("
            SELECT 
                w.id, w.status, w.end_date, w.supplier_name, w.sales_invoice_number, w.product_code,
                c_orig.name as original_owner_name
            FROM warranties w 
            LEFT JOIN service_orders so ON w.service_order_id = so.id
            LEFT JOIN clients c_orig ON so.client_id = c_orig.id
            WHERE w.equipment_id = ? 
            ORDER BY w.created_at DESC 
            LIMIT 1
        ");
        $stmtW->execute([$equipment['id']]);
        $warranty = $stmtW->fetch();

        // Also fetch Client Name
        $clientName = '';
        if ($equipment['client_id']) {
            $stmtC = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
            $stmtC->execute([$equipment['client_id']]);
            $client = $stmtC->fetch();
            if ($client) $clientName = $client['name'];
        }

        if ($warranty) {
            // Check status
            $status = 'valid';
            $message = 'Garantía Encontrada';

            if ($warranty['status'] === 'expired' || ($warranty['end_date'] && strtotime($warranty['end_date']) < time())) {
                $status = 'expired';
                $message = 'Garantía Expirada';
            } elseif ($warranty['status'] === 'void') {
                $status = 'void';
                $message = 'Garantía Anulada';
            }

            echo json_encode([
                'success' => true,
                'status' => $status,
                'message' => $message,
                'data' => [
                    'brand' => $equipment['brand'],
                    'model' => $equipment['model'],
                    'submodel' => $equipment['submodel'],
                    'type' => $equipment['type'],
                    'client_name' => $clientName,
                    'client_id' => $equipment['client_id'],
                    'supplier' => $warranty['supplier_name'],
                    'invoice' => $warranty['sales_invoice_number'],
                    'original_owner' => $warranty['original_owner_name']
                ]
            ]);
        } else {
            // Equipment exists, but NO warranty record (Standard Service history maybe?)
            echo json_encode([
                'success' => true,
                'status' => 'no_warranty',
                'message' => 'Equipo registrado pero sin datos de garantía',
                'data' => [
                    'brand' => $equipment['brand'],
                    'model' => $equipment['model'],
                    'submodel' => $equipment['submodel'],
                    'type' => $equipment['type'],
                    'client_name' => $clientName,
                    'client_id' => $equipment['client_id']
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'status' => 'not_found',
            'message' => 'No se encontró el número de serie'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>
