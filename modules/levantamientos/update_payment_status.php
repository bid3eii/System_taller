<?php
// modules/levantamientos/update_payment_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('surveys_status', $pdo)) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $payment_status = $_POST['payment_status'];

    if (!in_array($payment_status, ['pendiente', 'pagado'])) {
        die("Estado no válido");
    }

    try {
        $pdo->beginTransaction();

        // Check current status
        $stmtC = $pdo->prepare("SELECT title, client_name, payment_status, user_id FROM project_surveys WHERE id = ?");
        $stmtC->execute([$id]);
        $survey = $stmtC->fetch();

        if (!$survey) {
            throw new Exception("Levantamiento no encontrado.");
        }

        // Only process if status actually changed to pagado
        if ($payment_status === 'pagado' && $survey['payment_status'] !== 'pagado') {

            // 1. Update project_survey
            $stmt = $pdo->prepare("UPDATE project_surveys SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $id]);

            // 2. Auto-generate comision for the assigned technician (user_id)
            $tech_id = $survey['user_id'];
            if ($tech_id) {
                // Get tech name
                $stmtU = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtU->execute([$tech_id]);
                $tech_name = $stmtU->fetchColumn() ?: 'Desconocido';

                // Insert into comisiones (tabla plana)
                // Defaulting some fields because the UI didn't ask them for levantamientos,
                // but we know it's a PROYECTO
                $insertC = $pdo->prepare("
                    INSERT INTO comisiones (
                        fecha_servicio, 
                        cliente, 
                        servicio, 
                        cantidad, 
                        tipo, 
                        vendedor, 
                        caso, 
                        estado, 
                        tech_id, 
                        reference_id
                    ) VALUES (
                        CURDATE(),
                        ?,
                        ?,
                        1,
                        'PROYECTO',
                        ?,
                        ?,
                        'PENDIENTE',
                        ?,
                        ?
                    )
                ");
                $insertC->execute([
                    $survey['client_name'],
                    $survey['title'],
                    $tech_name, // vendedor
                    "Proyecto_#" . $id, // caso
                    $tech_id,
                    $id
                ]);

            }

            $_SESSION['success_message'] = "Estado de pago actualizado y comisión generada exitosamente.";
        } else {
            // Just update if it's not a transition to 'pagado' that triggers commission
            $stmt = $pdo->prepare("UPDATE project_surveys SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $id]);
            $_SESSION['success_message'] = "Estado de pago actualizado exitosamente.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header("Location: view.php?id=" . $id);
exit;
