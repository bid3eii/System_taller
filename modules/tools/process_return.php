<?php
// modules/tools/process_return.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$assignment_id = $_GET['id'] ?? null;
if (!$assignment_id) {
    echo "<script>alert('ID de asignación no válido'); window.location.href = 'assignments.php';</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get Assignment
    $stmt = $pdo->prepare("SELECT * FROM tool_assignments WHERE id = ?");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        throw new Exception("Asignación no encontrada");
    }

    if ($assignment['status'] === 'returned') {
        throw new Exception("Esta asignación ya fue devuelta");
    }

    // 2. Get Items
    $stmt = $pdo->prepare("SELECT * FROM tool_assignment_items WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        // Return quantity to tool inventory
        $stmt_update = $pdo->prepare("UPDATE tools SET quantity = quantity + ? WHERE id = ?");
        $stmt_update->execute([$item['quantity'], $item['tool_id']]);

        // Check if status needs to be reset to 'available'
        // Logic: If query returns quantity > 0, set status to available. 
        // Although if it was assigned, it means qty was 0. Now it's > 0.
        // We only change to 'available' if it is 'assigned'. 
        // If it is 'maintenance' or 'lost', we might want to keep it? 
        // User asked: "restablecerla a 'Disponible' cuando se realice una devolución".
        // So I will force it to available if it was assigned.
        
        $pdo->prepare("UPDATE tools SET status = 'available' WHERE id = ? AND status = 'assigned'")->execute([$item['tool_id']]);
    }

    // 3. Mark Assignment as Returned
    $stmt = $pdo->prepare("UPDATE tool_assignments SET status = 'returned', return_date = NOW() WHERE id = ?");
    $stmt->execute([$assignment_id]);
    
    // 4. Mark Items as Returned (optional, but good for consistency)
    $stmt = $pdo->prepare("UPDATE tool_assignment_items SET status = 'returned', return_confirmed = 1 WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);

    $pdo->commit();
    
    header('Location: assignments.php?success=returned');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: assignments.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
