<?php
// modules/proyectos/update_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Allow admins or specific permission
if (!can_access_module('proyectos', $pdo) && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = clean($_POST['id'] ?? '');
    $new_status = clean($_POST['status'] ?? '');

    $valid_statuses = ['draft', 'submitted', 'approved', 'in_progress', 'completed'];

    if ($id && in_array($new_status, $valid_statuses)) {
        // Fetch current to log it
        $stmt = $pdo->prepare("SELECT status FROM project_surveys WHERE id = ?");
        $stmt->execute([$id]);
        $old_status = $stmt->fetchColumn();

        if ($old_status !== $new_status) {
            $stmt = $pdo->prepare("UPDATE project_surveys SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $id])) {
                log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', $old_status, $new_status);
                $_SESSION['success_msg'] = "Estado del proyecto actualizado exitosamente a: " . strtoupper($new_status);
            } else {
                $_SESSION['error_msg'] = "Error al actualizar el estado del proyecto.";
            }
        }
    } else {
        $_SESSION['error_msg'] = "Estado inválido.";
    }

    header("Location: manage.php?id=" . $id);
    exit();
}
