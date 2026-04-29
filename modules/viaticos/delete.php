<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start(['gc_probability' => 0]);
}
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Check module permission
if (!can_access_module('viaticos_delete', $pdo)) {
    $_SESSION['error_msg'] = "No tienes permiso para eliminar viáticos.";
    header("Location: " . BASE_URL . "modules/viaticos/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $viatico_id = intval($_POST['id']);

    try {
        // Because we set ON DELETE CASCADE in the database schema, 
        // deleting the main record will delete the columns, concepts, and amounts.
        $stmt = $pdo->prepare("DELETE FROM viaticos WHERE id = ?");
        $stmt->execute([$viatico_id]);

        $_SESSION['success_msg'] = "El viático #$viatico_id fue eliminado exitosamente.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error al intentar eliminar: " . $e->getMessage();
    }
}

header("Location: " . BASE_URL . "modules/viaticos/index.php");
exit;
