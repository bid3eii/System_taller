<?php
// modules/comisiones/mark_paid.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission (using edit to keep simple)
if (!can_access_module('comisiones_edit', $pdo)) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    try {
        $stmtC = $pdo->prepare("SELECT id, status FROM comisiones WHERE id = ?");
        $stmtC->execute([$id]);
        $comision = $stmtC->fetch();

        if (!$comision) {
            throw new Exception("Comisión no encontrada.");
        }

        if ($comision['status'] === 'paid') {
            $_SESSION['success'] = 'La comisión ya estaba marcada como pagada.';
        } else {
            $stmt = $pdo->prepare("UPDATE comisiones SET status = 'paid' WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Estado de comisión actualizado a Pagado.';
        }

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Redirect back to view
header("Location: view.php?id=" . $id);
exit;
