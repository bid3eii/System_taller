<?php
require_once 'config/db.php';

$sn = '4KHY64';

echo "--- DIAGNOSTICO PARA SERIE: $sn ---\n";

try {
    $stmt = $pdo->prepare("SELECT e.id, e.client_id, c.name as client_name FROM equipments e JOIN clients c ON e.client_id = c.id WHERE e.serial_number = ?");
    $stmt->execute([$sn]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($eq) {
        echo "Equipo ID: " . $eq['id'] . "\n";
        echo "Cliente Asociado (Tabla equipments/clients): " . $eq['client_name'] . " (ID: " . $eq['client_id'] . ")\n";

        echo "\n--- Historial de Ordenes (service_orders) ---\n";
        $stmt2 = $pdo->prepare("SELECT id, owner_name, created_at FROM service_orders WHERE equipment_id = ? ORDER BY created_at DESC");
        $stmt2->execute([$eq['id']]);
        $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if ($orders) {
            foreach ($orders as $o) {
                echo "Orden #" . $o['id'] . " | Dueño: [" . $o['owner_name'] . "] | Fecha: " . $o['created_at'] . "\n";
            }
        } else {
            echo "No se encontraron órdenes de servicio.\n";
        }

        echo "\n--- Historial de Garantias (warranties) ---\n";
        $stmt3 = $pdo->prepare("SELECT w.id, w.service_order_id, so.owner_name as so_owner FROM warranties w LEFT JOIN service_orders so ON w.service_order_id = so.id WHERE w.equipment_id = ?");
        $stmt3->execute([$eq['id']]);
        $warranties = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        if ($warranties) {
            foreach ($warranties as $w) {
                 echo "Garantía #" . $w['id'] . " | SO Ref: " . $w['service_order_id'] . " | Dueño en SO: [" . $w['so_owner'] . "]\n";
            }
        } else {
            echo "No se encontraron registros de garantía.\n";
        }

    } else {
        echo "Equipo NO encontrado.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
