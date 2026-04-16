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
    $invoice_number = isset($_POST['invoice_number']) ? clean($_POST['invoice_number']) : null;

    if (!in_array($payment_status, ['pendiente', 'credito', 'contado', 'pagado'])) {
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
            $stmt = $pdo->prepare("UPDATE service_orders SET payment_status = ?, invoice_number = ? WHERE id = ?");
            $stmt->execute([$payment_status, $invoice_number, $id]);

            // 2. Update commission invoice for the assigned technician
            $tech_id = $order['assigned_tech_id'];
            if ($tech_id) {
                // Update the existing PENDIENTE commission with the invoice number, billing date and mark as PAGADA
                if ($invoice_number) {
                    $updateC = $pdo->prepare("
                        UPDATE comisiones
                        SET factura = ?,
                            fecha_facturacion = CURDATE(),
                            estado = 'PAGADA',
                            fecha_pago = CURDATE()
                        WHERE reference_id = ? AND tipo = 'SERVICIO'
                    ");
                    $updateC->execute([$invoice_number, $id]);
                }

                $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
                $stmtH->execute([$id, "Ciclo financiero cerrado. Factura registrada en comisión.", $_SESSION['user_id'], get_local_datetime()]);
            }

            // Log payment status change
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$id, "Estado de pago actualizado a: " . strtoupper($payment_status), $_SESSION['user_id'], get_local_datetime()]);

            $_SESSION['success_message'] = "Estado de pago actualizado y comisión generada exitosamente.";
        } else {
            // Just update payment status (and invoice if provided)
            $stmt = $pdo->prepare("UPDATE service_orders SET payment_status = ?, invoice_number = COALESCE(?, invoice_number) WHERE id = ?");
            $stmt->execute([$payment_status, $invoice_number, $id]);

            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$id, "Estado de pago actualizado a: " . strtoupper($payment_status), $_SESSION['user_id'], get_local_datetime()]);

            $_SESSION['success_message'] = "Estado de pago actualizado exitosamente.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error al procesar: " . $e->getMessage();
    }
}

// Redirect back to manage.php
$redirect_base = file_exists(__DIR__ . '/manage.php') ? 'manage.php' : 'view.php';
$msg = isset($_SESSION['error_message']) ? 'error' : 'success';
header("Location: " . $redirect_base . "?id=" . $id . "&msg=" . $msg);
exit;
