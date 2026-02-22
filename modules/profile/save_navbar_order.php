<?php
// modules/profile/save_navbar_order.php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order']) && is_array($data['order'])) {
    try {
        $orderJson = json_encode($data['order']);
        $stmt = $pdo->prepare("UPDATE users SET navbar_order = ? WHERE id = ?");
        $result = $stmt->execute([$orderJson, $_SESSION['user_id']]);
        
        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
