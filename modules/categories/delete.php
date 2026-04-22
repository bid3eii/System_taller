<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!has_role(['SuperAdmin', 'Administrador'], $pdo) && !can_access_module('warranties', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? clean($_POST['id']) : '';

    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM equipment_categories WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: index.php?msg=deleted");
            exit;
        } catch (Exception $e) {
            header("Location: index.php?err=" . urlencode("Error al eliminar: " . $e->getMessage()));
            exit;
        }
    }
}

header("Location: index.php");
exit;
