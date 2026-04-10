<?php
// modules/comisiones/save_and_pay.php
// Saves billing fields and marks a commission as paid in one step
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('comisiones_edit', $pdo)) {
    $_SESSION['error'] = "Acceso denegado.";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $factura = clean($_POST['factura'] ?? '');
    $fecha_fact = !empty($_POST['fecha_facturacion']) ? $_POST['fecha_facturacion'] : null;
    $lugar = clean($_POST['lugar'] ?? '');
    $vendedor = clean($_POST['vendedor'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Fetch current commission details to check type and reference
        $stmt_ref = $pdo->prepare("SELECT reference_id, tipo FROM comisiones WHERE id = ?");
        $stmt_ref->execute([$id]);
        $com_info = $stmt_ref->fetch();

        // 2. Update the main commission record
        $stmt = $pdo->prepare("
            UPDATE comisiones
            SET factura = ?,
                fecha_facturacion = ?,
                lugar = ?,
                vendedor = ?,
                estado = 'PAGADA',
                fecha_pago = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([
            $factura ?: null,
            $fecha_fact,
            $lugar ?: null,
            $vendedor ?: null,
            $id
        ]);

        // 3. Automated Sync with Project
        if ($com_info && $com_info['tipo'] === 'PROYECTO' && !empty($com_info['reference_id'])) {
            $ref_id = $com_info['reference_id'];
            
            // Mark the project as paid in its own table
            $upd_ps = $pdo->prepare("UPDATE project_surveys SET payment_status = 'pagado', invoice_number = ? WHERE id = ?");
            $upd_ps->execute([$factura, $ref_id]);
            
            // Sincronizar datos a otros técnicos asignados al mismo proyecto (pero no liquidarlos, solo llenar sus datos de factura)
            $sync_c = $pdo->prepare("
                UPDATE comisiones 
                SET factura = ?, 
                    fecha_facturacion = ?, 
                    lugar = ?, 
                    vendedor = ? 
                WHERE reference_id = ? AND tipo = 'PROYECTO' AND id != ? AND estado != 'PAGADA'
            ");
            $sync_c->execute([$factura, $fecha_fact, $lugar, $vendedor, $ref_id, $id]);
            
            log_audit($pdo, $ref_id, 'project_surveys', 'PAYMENT LIQUIDATED', 'pendiente', 'pagado (Desde Comisiones)');
        }

        $pdo->commit();
        $_SESSION['success'] = "Incentivo #" . str_pad($id, 5, '0', STR_PAD_LEFT) . " liquidado correctamente y sincronizado con el proyecto.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Error al actualizar: " . $e->getMessage();
    }
}

header("Location: index.php");
exit;
