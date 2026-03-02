<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !can_access_module('viaticos_edit', $pdo)) {
    header("Location: index.php");
    exit;
}

$viatico_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($viatico_id === 0) {
    header("Location: index.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Update header
    $p_title = trim($_POST['project_title']);
    $v_date = $_POST['date'];
    $t_amount = floatval($_POST['total_amount'] ?? 0);

    $sHeader = $pdo->prepare("UPDATE viaticos SET project_title = ?, date = ?, total_amount = ? WHERE id = ?");
    $sHeader->execute([$p_title, $v_date, $t_amount, $viatico_id]);

    // 2. Clear old matrix data (cascades to amounts automatically)
    $pdo->prepare("DELETE FROM viatico_columns WHERE viatico_id = ?")->execute([$viatico_id]);
    $pdo->prepare("DELETE FROM viatico_concepts WHERE viatico_id = ?")->execute([$viatico_id]);

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
    $_SESSION['flash_success'] = "Viático actualizado con éxito.";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "Error al actualizar: " . $e->getMessage();
    header("Location: edit.php?id=" . $viatico_id);
    exit;
}
?>