<?php
// modules/schedule/delete_event.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No session']);
    exit;
}

if (!can_access_module('schedule_manage', $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Permiso insuficiente para eliminar eventos de la agenda.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

try {
    // Audit before delete
    $stmtCheck = $pdo->prepare("SELECT * FROM schedule_events WHERE id = ?");
    $stmtCheck->execute([$id]);
    $oldData = $stmtCheck->fetch();

    if ($oldData) {
        $stmt = $pdo->prepare("DELETE FROM schedule_events WHERE id = ?");
        $stmt->execute([$id]);
        log_audit($pdo, 'schedule_events', $id, 'DELETE', $oldData, null);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
