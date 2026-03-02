<?php 
require_once 'config/db.php'; 
$stmt = $pdo->prepare("SELECT so.id, so.display_id, so.owner_name, c.name as contact_name, co.name as registered_owner_name 
                       FROM service_orders so 
                       JOIN clients c ON so.client_id = c.id 
                       JOIN equipments e ON so.equipment_id = e.id 
                       LEFT JOIN clients co ON e.client_id = co.id 
                       WHERE so.display_id = 1 OR so.id = 1"); 
$stmt->execute(); 
$res = $stmt->fetchAll(PDO::FETCH_ASSOC); 
foreach($res as $r) { 
    echo "ID: {$r['id']} | Display: {$r['display_id']} | Owner Field: '{$r['owner_name']}' | Contact Name: '{$r['contact_name']}' | Registered Owner: '{$r['registered_owner_name']}'\n"; 
} 
?>
