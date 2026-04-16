<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !can_access_module('viaticos_add', $pdo)) {
    header("Location: index.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert header
    $p_title = trim($_POST['project_title']);
    $survey_id = !empty($_POST['survey_id']) && $_POST['survey_id'] !== 'other' ? intval($_POST['survey_id']) : null;
    $v_date = $_POST['date'];
    $t_amount = floatval($_POST['total_amount'] ?? 0);
    $c_by = $_SESSION['user_id'];
    $c_at = get_local_datetime();

    $sHeader = $pdo->prepare("INSERT INTO viaticos (project_title, survey_id, date, total_amount, created_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'draft', ?)");
    $sHeader->execute([$p_title, $survey_id, $v_date, $t_amount, $c_by, $c_at]);
    $viatico_id = $pdo->lastInsertId();

    if (isset($_POST['techs']) && is_array($_POST['techs'])) {
        // Map tech_index to new viatico_column ID
        $techIdMap = [];
        $sCol = $pdo->prepare("INSERT INTO viatico_columns (viatico_id, tech_id, tech_name, display_order) VALUES (?, ?, ?, ?)");

        foreach ($_POST['techs'] as $tech_index => $tech_data) {
            $t_id = $tech_data['id'];
            $t_name = $tech_data['name'];
            $sCol->execute([$viatico_id, $t_id, $t_name, $tech_index]);
            $techIdMap[$tech_index] = $pdo->lastInsertId();
        }

        // 2. Insert concepts and amounts
        if (isset($_POST['amounts']) && is_array($_POST['amounts'])) {
            $sCon = $pdo->prepare("INSERT INTO viatico_concepts (viatico_id, type, category, label) VALUES (?, ?, ?, ?)");
            $sAmt = $pdo->prepare("INSERT INTO viatico_amounts (viatico_id, concept_id, column_id, amount) VALUES (?, ?, ?, ?)");

            foreach ($_POST['amounts'] as $type => $categories) {
                foreach ($categories as $cat => $labels) {
                    foreach ($labels as $label => $tech_amounts) {
                        // Create the concept row
                        $sCon->execute([$viatico_id, $type, $cat, $label]);
                        $concept_id = $pdo->lastInsertId();

                        // Create the amount cells for each tech that has an entry
                        foreach ($tech_amounts as $tech_index => $amount) {
                            $val = floatval($amount);
                            if ($val > 0 || $val === 0.0) { // Check valid insertion
                                if (isset($techIdMap[$tech_index])) {
                                    $col_id = $techIdMap[$tech_index];
                                    $sAmt->execute([$viatico_id, $concept_id, $col_id, $val]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    $_SESSION['flash_success'] = "Viático registrado con éxito.";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "Error al guardar: " . $e->getMessage();
    header("Location: create.php");
    exit;
}
?>