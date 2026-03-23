<?php
// modules/proyectos/update_invoice_number.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Only allow superadmin
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    die("Acceso denegado. Solo superadmin puede editar la factura una vez cerrada.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $new_invoice = trim($_POST['invoice_number'] ?? '');

    if (empty($new_invoice)) {
        $_SESSION['error_message'] = "El número de factura no puede estar vacío.";
        header("Location: manage.php?id=" . $id);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT invoice_number FROM project_surveys WHERE id = ?");
        $stmt->execute([$id]);
        $old_invoice = $stmt->fetchColumn();

        if ($old_invoice !== $new_invoice) {
            // Update in project surveys
            $update = $pdo->prepare("UPDATE project_surveys SET invoice_number = ? WHERE id = ?");
            $update->execute([$new_invoice, $id]);

            // Update in comisiones (only for this project)
            // It searches for references matching this project and updates the bill number
            $updateC = $pdo->prepare("UPDATE comisiones SET factura = ? WHERE reference_id = ? AND tipo = 'PROYECTO'");
            $updateC->execute([$new_invoice, $id]);

            log_audit($pdo, $id, 'project_surveys', 'UPDATE INVOICE', $old_invoice, $new_invoice);
            $_SESSION['success_message'] = "Número de factura actualizado correctamente en el proyecto y en las comisiones asociadas.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error al actualizar la factura: " . $e->getMessage();
    }
}

header("Location: manage.php?id=" . $id);
exit;
