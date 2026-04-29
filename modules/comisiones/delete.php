<?php
// modules/comisiones/delete.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_delete', $pdo)) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id) {
    try {
        $pdo->beginTransaction();

        // Check if exists
        $stmtC = $pdo->prepare("SELECT id FROM comisiones WHERE id = ?");
        $stmtC->execute([$id]);
        if (!$stmtC->fetchColumn()) {
            throw new Exception("Comisión no encontrada.");
        }

        // Delete (Cascades to comision_detalles based on DB schema)
        $stmt = $pdo->prepare("DELETE FROM comisiones WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $_SESSION['success'] = 'Registro de comisiones eliminado exitosamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al eliminar: ' . $e->getMessage();
    }
}

header("Location: index.php");
exit;
