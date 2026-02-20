<?php
// modules/clients/delete.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('clients_delete', $pdo)) {
    die("Acceso denegado. No tienes permiso para eliminar clientes.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Check for dependencies (Service Orders and Warranties are in the same table)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM service_orders WHERE client_id = ?");
$stmt->execute([$id]);
$total_orders = $stmt->fetchColumn();

if ($total_orders > 0) {
    // Cannot delete, has orders
    die("No se puede eliminar este cliente porque tiene $total_orders orden(es) asociadas. Debes eliminar las órdenes primero o archivar al cliente.");
}

// Proceed to Delete
try {
    // Get client name for audit
    $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client_name = $stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    if ($stmt->execute([$id])) {
        // Audit Log
        log_audit($pdo, 'clients', $id, 'DELETE', json_encode(['name' => $client_name]), null);
        
        // Redirect with success
        // Since we don't have a flash message system in index yet, we might need to add it or just redirect
        header("Location: index.php?msg=deleted");
        exit;
    }
} catch (PDOException $e) {
    die("Error al eliminar: " . $e->getMessage());
}
?>
