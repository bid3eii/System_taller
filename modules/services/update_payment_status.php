<?php
// modules/services/update_payment_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('settings', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $payment_status = $_POST['payment_status'];

    if (!in_array($payment_status, ['pendiente', 'pagado'])) {
        die("Estado no válido");
    }

    try {
        $pdo->beginTransaction();

        // Check current status
        $stmtC = $pdo->prepare("
            SELECT 
                so.payment_status, 
                so.assigned_tech_id,
                c.name as client_name,
                e.type as equipment_type,
                e.brand,
                e.model
            FROM service_orders so
            LEFT JOIN clients c ON so.client_id = c.id
            LEFT JOIN equipments e ON so.equipment_id = e.id
            WHERE so.id = ?
        ");
        $stmtC->execute([$id]);
        $order = $stmtC->fetch();

        if (!$order) {
            throw new Exception("Orden de servicio no encontrada.");
        }

        // Only process if status actually changed to pagado
        if ($payment_status === 'pagado' && $order['payment_status'] !== 'pagado') {

            // 1. Update order
            $stmt = $pdo->prepare("UPDATE service_orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $id]);

            // 2. Auto-generate comision for the assigned technician
            $tech_id = $order['assigned_tech_id'];
            if ($tech_id) {
                // Get tech name
                $stmtU = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtU->execute([$tech_id]);
                $tech_name = $stmtU->fetchColumn() ?: 'Desconocido';

                // Format service description
                $servicio_desc = $order['equipment_type'] . ' ' . $order['brand'] . ' ' . $order['model'];

                $insertC = $pdo->prepare("
                    INSERT INTO comisiones (
                        fecha_servicio, 
                        cliente, 
                        servicio, 
                        cantidad, 
                        tipo, 
                        vendedor, 
                        caso, 
                        estado, 
                        tech_id, 
                        reference_id
                    ) VALUES (
                        CURDATE(),
                        ?,
                        ?,
                        1,
                        'SERVICIO',
                        ?,
                        ?,
                        'PENDIENTE',
                        ?,
                        ?
                    )
                ");
                $insertC->execute([
                    $order['client_name'],
                    $servicio_desc,
                    $tech_name, // vendedor
                    "Servicio_#" . str_pad($id, 4, '0', STR_PAD_LEFT), // caso
                    $tech_id,
                    $id
                ]);

                // log history logic for comision
                $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
                $stmtH->execute([$id, "Comisión auto-generada para técnico por pago", $_SESSION['user_id'], get_local_datetime()]);
            }

            // Log payment status change
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$id, "Estado de pago actualizado a: " . strtoupper($payment_status), $_SESSION['user_id'], get_local_datetime()]);

            $_SESSION['success_message'] = "Estado de pago actualizado y comisión generada exitosamente.";
        } else {
            // Just update payment status
            $stmt = $pdo->prepare("UPDATE service_orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $id]);

            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$id, "Estado de pago actualizado a: " . strtoupper($payment_status), $_SESSION['user_id'], get_local_datetime()]);

            $_SESSION['success_message'] = "Estado de pago actualizado exitosamente.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header("Location: view.php?id=" . $id);
exit;
