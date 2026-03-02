<?php
// modules/comisiones/save.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_add', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $survey_id = !empty($_POST['survey_id']) ? intval($_POST['survey_id']) : null;
    $project_title = trim($_POST['project_title'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $created_by = $_SESSION['user_id'];
    $created_at = get_local_datetime();

    // Arrays from matrix
    $concepts = $_POST['concepts'] ?? [];
    $amounts = $_POST['amounts'] ?? []; // format: amounts[tech_id][] = array of values
    $tech_ids = $_POST['tech_ids'] ?? [];

    if (!$survey_id) {
        $_SESSION['error'] = 'Error: ID de Proyecto inválido.';
        header("Location: " . BASE_URL . "modules/comisiones/index.php");
        exit;
    }

    if (empty($tech_ids)) {
        $_SESSION['error'] = 'Debes asignar al menos un técnico.';
        header("Location: create.php?survey_id=$survey_id&title=" . urlencode($project_title));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Header
        $stmtH = $pdo->prepare("INSERT INTO comisiones (survey_id, project_title, date, total_amount, status, created_by, created_at) VALUES (?, ?, ?, ?, 'draft', ?, ?)");
        $stmtH->execute([$survey_id, $project_title, $date, $total_amount, $created_by, $created_at]);

        $comision_id = $pdo->lastInsertId();

        // 2. Insert Details
        $stmtD = $pdo->prepare("INSERT INTO comision_detalles (comision_id, tech_id, concept, amount) VALUES (?, ?, ?, ?)");

        foreach ($tech_ids as $tech_id) {
            foreach ($concepts as $index => $concept_name) {
                $concept_clean = trim($concept_name);
                if (empty($concept_clean))
                    continue;

                $amount_val = floatval($amounts[$tech_id][$index] ?? 0);

                // Only save if amount > 0
                if ($amount_val > 0) {
                    $stmtD->execute([$comision_id, $tech_id, $concept_clean, $amount_val]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['success'] = 'Comisiones guardadas correctamente.';

        // Redirect back to project/survey or to the comisiones list
        header("Location: " . BASE_URL . "modules/levantamientos/view.php?id=" . $survey_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
        header("Location: create.php?survey_id=$survey_id&title=" . urlencode($project_title));
        exit;
    }
}
