<?php
// modules/tools/delete.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    // Check if tool is used in assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tool_assignment_items WHERE tool_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        // Cannot delete, it has history
        echo "<script>alert('No se puede eliminar esta herramienta porque tiene asignaciones registradas.'); window.location.href = 'index.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM tools WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
