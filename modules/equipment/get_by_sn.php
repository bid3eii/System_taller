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
        e.id, e.brand, e.model, e.type, e.client_id,
        c.name as client_name
    FROM equipments e
    JOIN clients c ON e.client_id = c.id
    WHERE e.serial_number = ?
    LIMIT 1
");

$stmt->execute([$sn]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($equipment) {
    echo json_encode(['found' => true, 'data' => $equipment]);
} else {
    echo json_encode(['found' => false]);
}
?>
