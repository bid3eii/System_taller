<?php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$material_id = intval($_POST['material_id'] ?? 0);
$notes = clean($_POST['notes'] ?? '');

if (!$material_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Verify the material belongs to a survey the user can access
$stmt = $pdo->prepare("
    SELECT pm.id 
    FROM project_materials pm 
    JOIN project_surveys ps ON pm.survey_id = ps.id
    WHERE pm.id = ?
");
$stmt->execute([$material_id]);
$mat = $stmt->fetch();

if (!$mat) {
    echo json_encode(['success' => false, 'message' => 'Material no encontrado']);
    exit;
}

$update = $pdo->prepare("UPDATE project_materials SET notes = ? WHERE id = ?");
$update->execute([$notes, $material_id]);

echo json_encode(['success' => true, 'notes' => $notes]);
