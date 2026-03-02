<?php
// modules/equipment/get_by_sn.php
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['sn'])) {
    echo json_encode(['found' => false]);
    exit;
}

$sn = $_GET['sn'];

$stmt = $pdo->prepare("
    SELECT 
        e.id, e.brand, e.model, e.submodel, e.type, e.client_id,
        c.name as client_name,
        (SELECT owner_name FROM service_orders WHERE equipment_id = e.id AND owner_name != '' ORDER BY created_at DESC LIMIT 1) as owner_name
    FROM equipments e
    JOIN clients c ON e.client_id = c.id
    WHERE e.serial_number = ?
    LIMIT 1
");

$stmt->execute([$sn]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

// Send data as is, frontend will handle priority
if ($equipment) {
    echo json_encode(['found' => true, 'data' => $equipment]);
} else {
    echo json_encode(['found' => false]);
}
?>
