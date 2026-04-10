<?php
// modules/proyectos/update_payment_status.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Allow admins or specific permission
if (!can_access_module('proyectos', $pdo) && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $payment_status = $_POST['payment_status'];
    $invoice_number = trim($_POST['invoice_number'] ?? '');

    if (!in_array($payment_status, ['pendiente', 'credito', 'contado', 'pagado'])) {
        die("Estado no válido");
    }

    try {
        $pdo->beginTransaction();

        // Check current status
        $stmtC = $pdo->prepare("
            SELECT 
                ps.payment_status, 
                ps.assigned_tech_ids,
                ps.client_name,
                ps.title as project_title,
                ps.vendedor
            FROM project_surveys ps
            WHERE ps.id = ?
        ");
        $stmtC->execute([$id]);
        $project = $stmtC->fetch();

        if (!$project) {
            throw new Exception("Proyecto no encontrado.");
        }

        // 1. Update project status
        $stmt = $pdo->prepare("UPDATE project_surveys SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $id]);

        // 2. Manage Commissions (Creation only)
        $tech_ids_str = $project['assigned_tech_ids'] ?? '';
        $tech_ids = array_filter(explode(',', $tech_ids_str));
        
        if (!empty($tech_ids)) {
            // Fetch current commissions for this project to avoid duplicates
            $stmt_all_c = $pdo->prepare("SELECT tech_id FROM comisiones WHERE reference_id = ? AND tipo = 'PROYECTO'");
            $stmt_all_c->execute([$id]);
            $existing_tech_ids = $stmt_all_c->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tech_ids as $tid) {
                if (!in_array($tid, $existing_tech_ids)) {
                    // Create missing commission as PENDIENTE
                    $stmt_t = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt_t->execute([$tid]);
                    $tech_name = $stmt_t->fetchColumn() ?: 'Desconocido';

                    $insertC = $pdo->prepare("
                        INSERT INTO comisiones (
                            fecha_servicio, cliente, servicio, cantidad, tipo, vendedor, caso, estado, tech_id, reference_id
                        ) VALUES (
                            CURDATE(), ?, ?, 1, 'PROYECTO', ?, ?, 'PENDIENTE', ?, ?
                        )
                    ");
                    $insertC->execute([
                        $project['client_name'],
                        $project['project_title'],
                        $project['vendedor'] ?? 'Oficina',
                        "#P" . str_pad($id, 4, '0', STR_PAD_LEFT),
                        $tid,
                        $id
                    ]);
                }
            }

            log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', $project['payment_status'], $payment_status);
            $_SESSION['success_msg'] = "Estado de pago actualizado correctamente.";
        } else {
            log_audit($pdo, $id, 'project_surveys', 'UPDATE STATUS', $project['payment_status'], $payment_status);
            $_SESSION['success_msg'] = "Estado actualizado (Sin técnicos asignados).";
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
}

header("Location: manage.php?id=" . $id);
exit;
