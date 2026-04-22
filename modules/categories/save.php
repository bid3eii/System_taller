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
    $name = clean($_POST['name'] ?? '');
    $months = clean($_POST['default_months'] ?? 12);

    if (empty($name) || empty($months)) {
        header("Location: index.php?err=" . urlencode("El nombre y los meses son obligatorios."));
        exit;
    }

    try {
        if (!empty($id)) {
            // Update
            $stmt = $pdo->prepare("UPDATE equipment_categories SET name = ?, default_months = ? WHERE id = ?");
            $stmt->execute([$name, $months, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO equipment_categories (name, default_months) VALUES (?, ?)");
            $stmt->execute([$name, $months]);
        }
        header("Location: index.php?msg=saved");
        exit;
    } catch (Exception $e) {
        header("Location: index.php?err=" . urlencode("Error al guardar: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
