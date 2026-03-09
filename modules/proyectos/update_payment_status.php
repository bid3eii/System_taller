<?php
// modules/proyectos/update_payment_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Allow admins or specific permission
if (!can_access_module('proyectos', $pdo) && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $payment_status = $_POST['payment_status'];
    $invoice_number = trim($_POST['invoice_number'] ?? '');

    if (!in_array($payment_status, ['pendiente', 'pagado'])) {
        die("Estado no válido");
    }

    try {
        $pdo->beginTransaction();

        // Check current status
        $stmtC = $pdo->prepare("
            SELECT 
                ps.payment_status, 
                ps.assigned_tech_id as tech_id,
                ps.client_name,
                ps.title as project_title,
                u.username as tech_name
            FROM project_surveys ps
            LEFT JOIN users u ON ps.assigned_tech_id = u.id
            WHERE ps.id = ?
        ");
        $stmtC->execute([$id]);
        $project = $stmtC->fetch();

        if (!$project) {
            throw new Exception("Proyecto no encontrado.");
        }

        // Only process if status actually changed to pagado
        if ($payment_status === 'pagado' && $project['payment_status'] !== 'pagado') {

            // 1. Update project and invoice
            $stmt = $pdo->prepare("UPDATE project_surveys SET payment_status = ?, invoice_number = ? WHERE id = ?");
            $stmt->execute([$payment_status, $invoice_number, $id]);

            // 2. Update commission invoice for the assigned technician
            $tech_id = $project['tech_id'];
            if ($tech_id) {
                // Update the existing PENDIENTE commission with the invoice number if provided
                if ($invoice_number) {
                    $updateC = $pdo->prepare("UPDATE comisiones SET factura = ? WHERE reference_id = ? AND tipo = 'PROYECTO'");
                    $updateC->execute([$invoice_number, $id]);
                }

                // Log audit
                log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', 'pendiente', 'pagado (Ciclo Financiero Cerrado)');
            } else {
                log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', 'pendiente', 'pagado (Sin Téc.)');
            }

            $_SESSION['success_message'] = "Finanzas del proyecto cerradas. Comisión generada exitosamente.";
        } else {
            // Just update payment status and invoice
            $stmt = $pdo->prepare("UPDATE project_surveys SET payment_status = ?, invoice_number = ? WHERE id = ?");
            $stmt->execute([$payment_status, $invoice_number, $id]);

            log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', $project['payment_status'], $payment_status);

            $_SESSION['success_message'] = "Estado de pago actualizado exitosamente.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header("Location: manage.php?id=" . $id);
exit;
