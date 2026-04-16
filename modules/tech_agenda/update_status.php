<?php
// modules/tech_agenda/update_status.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$status = $input['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    exit;
}

try {
    // Basic verification: user can only update their own events OR is admin
    $stmtCheck = $pdo->prepare("SELECT tech_id FROM schedule_events WHERE id = ?");
    $stmtCheck->execute([$id]);
    $event = $stmtCheck->fetch();

    if (!$event) {
        throw new Exception("Visita no encontrada");
    }

    if ($event['tech_id'] != $_SESSION['user_id'] && !in_array($_SESSION['role_id'], [1, 7])) {
        throw new Exception("No tienes permiso para actualizar esta visita");
    }

    $stmt = $pdo->prepare("UPDATE schedule_events SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
