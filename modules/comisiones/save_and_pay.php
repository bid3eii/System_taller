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
    $cantidad = floatval($_POST['cantidad'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            UPDATE comisiones
            SET factura = ?,
                fecha_facturacion = ?,
                lugar = ?,
                vendedor = ?,
                cantidad = ?,
                estado = 'PAGADA',
                fecha_pago = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([
            $factura ?: null,
            $fecha_fact,
            $lugar ?: null,
            $vendedor ?: null,
            $cantidad,
            $id
        ]);

        $_SESSION['success'] = "Comisión #" . str_pad($id, 5, '0', STR_PAD_LEFT) . " marcada como PAGADA correctamente.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al actualizar: " . $e->getMessage();
    }
}

header("Location: index.php");
exit;
