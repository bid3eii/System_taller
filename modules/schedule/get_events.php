<?php
// modules/schedule/get_events.php
header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No session']);
    exit;
}

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$tech_id = $_GET['tech_id'] ?? null;

if (!$start || !$end) {
    echo json_encode(['error' => 'Missing range']);
    exit;
}

$where = "start_datetime >= ? AND end_datetime <= ?";
$params = [$start, $end];

if ($tech_id && $tech_id !== 'all') {
    $where .= " AND tech_id = ?";
    $params[] = $tech_id;
} else if (!can_access_module('schedule_view_all', $pdo)) {
    // If not admin/authorized, only see own events
    $where .= " AND tech_id = ?";
    $params[] = $_SESSION['user_id'];
}

try {
    $sql = "SELECT se.*, u.username as tech_name, ps.title as survey_title 
            FROM schedule_events se
            LEFT JOIN users u ON se.tech_id = u.id
            LEFT JOIN project_surveys ps ON se.survey_id = ps.id
            WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    $formatted = array_map(function($ev) {
        return [
            'id' => $ev['id'],
            'title' => $ev['title'],
            'start' => $ev['start_datetime'],
            'end' => $ev['end_datetime'],
            'description' => $ev['description'],
            'color' => $ev['color'],
            'extendedProps' => [
                'tech_id' => $ev['tech_id'],
                'tech_name' => $ev['tech_name'],
                'location' => $ev['location'],
                'status' => $ev['status'],
                'survey_id' => $ev['survey_id'],
                'survey_title' => $ev['survey_title'],
                'service_order_id' => $ev['service_order_id'],
                'latitude' => $ev['latitude'],
                'longitude' => $ev['longitude']
            ]
        ];
    }, $events);

    echo json_encode($formatted);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
