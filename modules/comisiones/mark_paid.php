<?php
// modules/comisiones/mark_paid.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_edit', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    try {
        $stmt = $pdo->prepare("UPDATE comisiones SET estado = 'PAGADA', fecha_pago = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['success'] = "Comisión marcada como pagada correctamente.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al actualizar la comisión: " . $e->getMessage();
    }
}

header("Location: index.php");
exit;
