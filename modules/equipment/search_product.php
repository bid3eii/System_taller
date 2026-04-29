<?php
// modules/equipment/search_product.php
// AJAX endpoint to search existing products by code, brand, or model
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

$results = [];
try {
    if (empty($query)) {
        // Return latest 20 distinct products for the initial list
        $stmt = $pdo->query("
            SELECT 
                w.product_code,
                e.brand,
                w.supplier_name,
                w.sales_invoice_number,
                w.master_entry_invoice,
                w.master_entry_date,
                COUNT(*) as total_units,
                MAX(so.entry_date) as last_entry
            FROM warranties w
            JOIN service_orders so ON w.service_order_id = so.id
            JOIN equipments e ON w.equipment_id = e.id
            GROUP BY w.product_code, e.brand, w.supplier_name, w.sales_invoice_number, w.master_entry_invoice, w.master_entry_date
            ORDER BY last_entry DESC
            LIMIT 20
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Search in warranties + equipments for existing product entries
        $stmt = $pdo->prepare("
            SELECT 
                w.product_code,
                e.brand,
                w.supplier_name,
                w.sales_invoice_number,
                w.master_entry_invoice,
                w.master_entry_date,
                COUNT(*) as total_units,
                MAX(so.entry_date) as last_entry
            FROM warranties w
            JOIN service_orders so ON w.service_order_id = so.id
            JOIN equipments e ON w.equipment_id = e.id
            WHERE w.product_code LIKE ? 
               OR e.brand LIKE ?
               OR e.model LIKE ?
               OR e.submodel LIKE ?
               OR w.supplier_name LIKE ?
            GROUP BY w.product_code, e.brand, w.supplier_name, w.sales_invoice_number, w.master_entry_invoice, w.master_entry_date
            ORDER BY last_entry DESC
            LIMIT 15
        ");
        
        $like = '%' . $query . '%';
        $stmt->execute([$like, $like, $like, $like, $like]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
