<?php
// modules/levantamientos/delete.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys_delete', $pdo)) {
    die("Acceso denegado.");
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        // Because of ON DELETE CASCADE, deleting the survey deletes the materials too.
        $stmt = $pdo->prepare("DELETE FROM project_surveys WHERE id = ?");
        $stmt->execute([$id]);

        // Log action
        log_audit($pdo, 'project_surveys', $id, 'DELETE', null, null);

        header("Location: index.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        die("Error deleting: " . $e->getMessage());
    }
}
?>