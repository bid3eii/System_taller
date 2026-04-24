<?php
// modules/proyectos/update_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Allow admins or specific permission
$user_role = $_SESSION['role'] ?? $_SESSION['role_name'] ?? '';
$is_admin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) || $user_role === 'admin';
if (!can_access_module('proyectos', $pdo) && !$is_admin) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = clean($_POST['id'] ?? '');
    $new_status = clean($_POST['status'] ?? '');

    $valid_statuses = ['draft', 'submitted', 'approved', 'in_progress', 'completed'];

    if ($id && in_array($new_status, $valid_statuses)) {
        // Fetch current to log it
        $stmt = $pdo->prepare("SELECT status, payment_status, invoice_number FROM project_surveys WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_status = $current['status'];

        $needs_wipe = ($new_status === 'draft' && ($current['payment_status'] !== 'pendiente' || !empty($current['invoice_number'])));

        if ($old_status !== $new_status || $needs_wipe) {
            try {
                $pdo->beginTransaction();
                
                if ($new_status === 'draft') {
                    // Reset status and wipe financial data when reverting to draft
                    $stmt_update = $pdo->prepare("UPDATE project_surveys SET status = ?, payment_status = 'pendiente', invoice_number = NULL WHERE id = ?");
                    $stmt_update->execute([$new_status, $id]);

                    // Unlink invoice from any generated commissions (keeps them as PENDIENTE)
                    $stmtC = $pdo->prepare("UPDATE comisiones SET factura = NULL WHERE reference_id = ? AND tipo = 'PROYECTO'");
                    $stmtC->execute([$id]);
                } else {
                    $stmt_update = $pdo->prepare("UPDATE project_surveys SET status = ? WHERE id = ?");
                    $stmt_update->execute([$new_status, $id]);
                }

                if ($old_status !== $new_status) {
                    log_audit($pdo, 'project_surveys', $id, 'UPDATE', ['status' => $old_status], ['status' => $new_status], 'Cambio de estado operativo');
                }
                
                $pdo->commit();
                $_SESSION['success_msg'] = "Estado del proyecto actualizado exitosamente a: " . strtoupper($new_status);
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_msg'] = "Error al actualizar el estado: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_msg'] = "Estado inválido.";
    }

    header("Location: manage.php?id=" . $id);
    exit();
}
