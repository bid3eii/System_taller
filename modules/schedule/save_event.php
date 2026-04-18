<?php
// modules/schedule/save_event.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No session']);
    exit;
}

if (!can_access_module('schedule_manage', $pdo)) {
    echo json_encode(['success' => false, 'message' => 'Permiso insuficiente para administrar la agenda.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$id = $input['id'] ?? null;
$title = $input['title'] ?? 'Nueva Visita';
$description = $input['description'] ?? '';
$tech_id = $input['tech_id'] ?? $_SESSION['user_id'];
$start = $input['start'];
$end = $input['end'];
$location = $input['location'] ?? '';
$status = $input['status'] ?? 'scheduled';
$color = $input['color'] ?? '#6366f1';
$survey_id = !empty($input['survey_id']) ? $input['survey_id'] : null;
$service_order_id = !empty($input['service_order_id']) ? $input['service_order_id'] : null;
$latitude = !empty($input['latitude']) ? $input['latitude'] : null;
$longitude = !empty($input['longitude']) ? $input['longitude'] : null;

try {
    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE schedule_events SET 
            title = ?, description = ?, tech_id = ?, start_datetime = ?, end_datetime = ?, 
            location = ?, status = ?, color = ?, survey_id = ?, service_order_id = ?, latitude = ?, longitude = ?
            WHERE id = ?");
        $stmt->execute([
            $title, $description, $tech_id, $start, $end, 
            $location, $status, $color, $survey_id, $service_order_id, $latitude, $longitude, $id
        ]);
        log_audit($pdo, 'schedule_events', $id, 'UPDATE', null, $input);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO schedule_events 
            (title, description, tech_id, start_datetime, end_datetime, location, status, color, survey_id, service_order_id, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $title, $description, $tech_id, $start, $end,
            $location, $status, $color, $survey_id, $service_order_id, $latitude, $longitude
        ]);
        $id = $pdo->lastInsertId();
        log_audit($pdo, 'schedule_events', $id, 'INSERT', null, $input);
    }

    echo json_encode(['success' => true, 'id' => $id]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
