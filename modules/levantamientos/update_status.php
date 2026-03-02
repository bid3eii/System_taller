<?php
// modules/levantamientos/update_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys_status', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'];

    // Validate status
    if (!in_array($status, ['draft', 'submitted', 'approved'])) {
        die("Estado inválido.");
    }

    try {
        $stmt = $pdo->prepare("UPDATE project_surveys SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Log action
        log_audit($pdo, 'project_surveys', $id, 'UPDATE STATUS', null, $status);

        header("Location: view.php?id=$id&msg=status_updated");
        exit;
    } catch (PDOException $e) {
        die("Error updating status: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
?>