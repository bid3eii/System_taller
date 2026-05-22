<?php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$stmt = $pdo->query("
    SELECT so.id, e.brand, e.serial_number
    FROM service_orders so
    JOIN equipments e ON so.equipment_id = e.id
    JOIN clients c ON so.client_id = c.id
    WHERE so.service_type = 'warranty' AND c.name = 'Bodega - Inventario'
    LIMIT 2
");
$items = $stmt->fetchAll();

if (empty($items)) {
    echo "ERROR: No hay items en stock para probar\n";
    exit;
}

$ids_arr = array_column($items, 'id');
$ids_str = implode(',', $ids_arr);

echo "Items encontrados:\n";
foreach ($items as $i) {
    echo "  ID: {$i['id']} | {$i['brand']} | S/N: {$i['serial_number']}\n";
}

echo "\n--- Simulando validacion del backend ---\n";
echo "service_order_ids = '$ids_str'\n";
$client_name   = 'Cliente Test Debug';
$sales_invoice = 'DEBUG-' . date('YmdHis');
echo "client_name      = '$client_name'\n";
echo "sales_invoice    = '$sales_invoice'\n";

$clean_ids    = clean($ids_str);
$clean_client = clean($client_name);
$clean_inv    = clean($sales_invoice);

echo "\n--- Despues de clean() ---\n";
echo "clean ids    = '$clean_ids'\n";
echo "clean client = '$clean_client'\n";
echo "clean inv    = '$clean_inv'\n";

if (empty($clean_ids) || empty($clean_client) || empty($clean_inv)) {
    echo "\nRESULTADO: FALLA - campo requerido vacio\n";
} else {
    echo "\nRESULTADO: PASA - formulario llegaría correctamente al backend\n";
    
    // Also check the IDs parse correctly
    $parsed_ids = array_filter(array_map('intval', explode(',', $clean_ids)));
    echo "IDs parseados: " . implode(', ', $parsed_ids) . "\n";
    echo "Cuenta de IDs: " . count($parsed_ids) . "\n";
}
