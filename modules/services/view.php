<?php
// modules/services/view.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no especificado.");
}

// Handle Success/Print Messages from Redirect
$success_msg = isset($_GET['msg']) && $_GET['msg'] === 'success' ? "Estado actualizado correctamente." : '';
$error_msg = '';
$autoPrintDiagnosis = isset($_GET['print']) && $_GET['print'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $new_status = clean($_POST['status']);
        $note = clean($_POST['note']);

        try {
            // Handle Diagnosis Fields if Status is Diagnosing
            if ($new_status === 'diagnosing') {
                $proc = clean($_POST['diagnosis_procedure'] ?? '');
                $conc = clean($_POST['diagnosis_conclusion'] ?? '');

                // Update fields
                $stmtUpd = $pdo->prepare("UPDATE service_orders SET diagnosis_procedure = ?, diagnosis_conclusion = ? WHERE id = ?");
                $stmtUpd->execute([$proc, $conc, $id]);

                // Handle Images
                if (isset($_FILES['diagnosis_images']) && !empty($_FILES['diagnosis_images']['name'][0])) {
                    $uploadDir = '../../uploads/diagnosis/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);

                    foreach ($_FILES['diagnosis_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['diagnosis_images']['error'][$key] === 0) {
                            $fileName = time() . '_' . $key . '_' . basename($_FILES['diagnosis_images']['name'][$key]);
                            $targetPath = $uploadDir . $fileName;
                            if (move_uploaded_file($tmp_name, $targetPath)) {
                                $stmtImg = $pdo->prepare("INSERT INTO diagnosis_images (service_order_id, image_path) VALUES (?, ?)");
                                $stmtImg->execute([$id, 'uploads/diagnosis/' . $fileName]);
                            }
                        }
                    }
                }

                // Set flag to auto-print after save
                $autoPrintDiagnosis = true;

                // Append diagnosis details to history note
                $note .= "\n\n[Diagnóstico]\nProcedimiento: $proc\nConclusión: $conc";
            }

            update_service_status($pdo, $id, $new_status, $note, $_SESSION['user_id']);

            // Redirect to prevent form resubmission
            $redirectUrl = "view.php?id=$id&msg=success";
            if ($autoPrintDiagnosis)
                $redirectUrl .= "&print=1";

            header("Location: $redirectUrl");
            exit;
        } catch (Exception $e) {
            $error_msg = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.name as client_name, c.phone, c.email,
        e.brand, e.model, e.submodel, e.serial_number, e.type as equipment_type
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    WHERE so.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Orden no encontrada.");
}

// Redirect if it is a warranty
if ($order['service_type'] === 'warranty') {
    header("Location: ../warranties/view.php?id=" . $order['id']);
    exit;
}

// Check Access Permissions for this Specific Order
$can_view_all = can_access_module('view_all_entries', $pdo);
if (!$can_view_all && $order['assigned_tech_id'] != $_SESSION['user_id']) {
    die("Acceso denegado. No tienes permiso para ver este caso.");
}


// Fetch History
$stmtHist = $pdo->prepare("
    SELECT h.*, u.username as user_name 
    FROM service_order_history h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.service_order_id = ?
    ORDER BY h.created_at DESC
");
$stmtHist->execute([$id]);
$history = $stmtHist->fetchAll();

$page_title = 'Detalle de Servicio ' . get_order_number($order);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Status Mapping
$statusLabels = [
    'received' => 'Recibido',
    'diagnosing' => 'En Revisión/Diagnóstico',
    'pending_approval' => 'En Espera',
    'in_repair' => 'En Reparación',
    'ready' => 'Listo',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado'
];
// View Logic
$view_mode = $_GET['view_mode'] ?? 'current';
$is_original_mode = ($view_mode === 'original');
$is_history_view = (isset($_GET['view_source']) && $_GET['view_source'] === 'history');
?>

<div class="animate-enter">
    <style>
        :root {
            --p-bg-main: #020617;
            --p-bg-card: #0f172a;
            --p-bg-input: #1e293b;
            --p-border: #334155;
            --p-text-main: #f8fafc;
            --p-text-muted: #94a3b8;
            --p-primary: #6366f1;
        }

        .view-container {
            max-width: 1400px;
            margin: 0 auto;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--p-text-main);
        }

        /* Grid Layout */
        .layout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            align-items: start;
            gap: 2rem;
        }

        .form-section {
            background: var(--p-bg-card);
            border: 1px solid var(--p-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .form-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--p-border);
            color: var(--p-primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .info-group {
            margin-bottom: 1.25rem;
        }

        .info-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--p-text-muted);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .info-value {
            font-size: 0.95rem;
            color: var(--p-text-main);
            font-weight: 500;
        }

        .info-value.highlight {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .problem-box {
            background: #020617;
            /* Darker than card */
            border: 1px solid var(--p-border);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .update-card {
            background: var(--p-bg-card);
            border: 1px solid var(--p-border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .modern-input,
        .modern-select,
        .modern-textarea {
            background: var(--p-bg-input);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--p-text-main);
            width: 100%;
            font-family: inherit;
            transition: all 0.2s;
        }

        .modern-input:focus,
        .modern-select:focus,
        .modern-textarea:focus {
            outline: none;
            border-color: var(--p-primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: #1e293b;
        }

        .btn-update {
            background: var(--p-primary);
            color: white;
            border: none;
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-update:hover {
            filter: brightness(1.1);
        }

        /* Timeline Styles */
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 2rem;
            border-left: 2px solid var(--p-border);
        }

        .timeline-item:last-child {
            border-left: none;
            padding-bottom: 0;
        }

        .timeline-icon {
            position: absolute;
            left: -9px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--p-primary);
            border: 2px solid var(--p-bg-card);
        }

        .timeline-date {
            font-size: 0.8rem;
            color: var(--p-text-muted);
        }

        .timeline-text {
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .sidebar-sticky {
            position: sticky;
            top: 2rem;
        }

        @media (max-width: 900px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Light Mode Overrides for View Page */
        body.light-mode {
            --p-bg-main: #f8fafc;
            --p-bg-card: #ffffff;
            --p-bg-input: #f1f5f9;
            --p-border: #e2e8f0;
            --p-text-main: #0f172a;
            --p-text-muted: #64748b;
        }

        body.light-mode .problem-box,
        body.light-mode .timeline-item .timeline-text+div {
            background: #f1f5f9 !important;
            color: #334155 !important;
        }

        body.light-mode .modern-input,
        body.light-mode .modern-select,
        body.light-mode .modern-textarea {
            color: #0f172a;
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
    </style>

    <div class="view-container">
        <!-- Header -->
        <div class="no-print"
            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <a href="<?php echo ($is_history_view) ? '../history/index.php' : '../services/index.php'; ?>"
                        style="color: var(--p-text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                        <i class="ph ph-arrow-left"></i> Volver
                    </a>
                    <span
                        style="font-size: 0.9rem; color: var(--p-text-muted);"><?php echo $statusLabels[$order['status']] ?? $order['status']; ?></span>
                </div>
                <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.25rem;">Caso
                    <?php echo get_order_number($order); ?></h1>

                <div style="font-size: 0.9rem; margin-top: 0.25rem; margin-bottom: 0.5rem; display: flex; gap: 1rem;">
                    <?php if ($order['diagnosis_number']): ?>
                        <span style="color: #fbbf24;">Diag:
                            #<?php echo str_pad($order['diagnosis_number'], 5, '0', STR_PAD_LEFT); ?></span>
                    <?php endif; ?>
                    <?php if ($order['repair_number']): ?>
                        <span style="color: #34d399;">Rep:
                            #<?php echo str_pad($order['repair_number'], 5, '0', STR_PAD_LEFT); ?></span>
                    <?php endif; ?>
                    <?php if ($order['exit_doc_number']): ?>
                        <span style="color: #94a3b8;">Salida:
                            #<?php echo str_pad($order['exit_doc_number'], 5, '0', STR_PAD_LEFT); ?></span>
                    <?php endif; ?>
                </div>
                <p style="color: var(--p-text-muted); font-size: 0.9rem;">Ingresado el
                    <?php echo date('d/m/Y H:i', strtotime($order['entry_date'])); ?>
                </p>
            </div>

            <div style="text-align: right;">
                <a href="../equipment/print_entry.php?id=<?php echo $id; ?>" class="btn btn-primary"
                    style="margin-bottom: 0.5rem; text-decoration: none; display: inline-flex;">
                    <i class="ph ph-printer"></i> Imprimir Hoja Entrada
                </a>

                <?php if ($order['invoice_number']): ?>
                    <div>
                        <span style="color: var(--p-text-muted); font-size: 0.9rem;">Factura:</span>
                        <strong
                            style="font-size: 1.1rem; color: var(--p-text-main);"><?php echo htmlspecialchars($order['invoice_number']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div
                style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div
                style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php
        // Original Mode Logic (Preserved)
        if (!$is_history_view) {
            $original_values = [];
            $history_asc = array_reverse($history);
            foreach ($history_asc as $h) {
                if ($h['action'] == 'updated') {
                    $note_content = str_replace('Datos editados: ', '', $h['notes']);
                    if (preg_match_all('/([^:]+): (.*?) -> /', $note_content, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $field_parts = explode(',', $match[1]);
                            $key = trim(end($field_parts));
                            $original_values[$key] = trim($match[2]);
                        }
                    }
                }
            }

            if ($is_original_mode) {
                $map = [
                    'Cliente' => 'client_name',
                    'Teléfono' => 'phone',
                    'Email' => 'email',
                    'Marca' => 'brand',
                    'Modelo' => 'model',
                    'Serie' => 'serial_number',
                    'Tipo' => 'equipment_type',
                    'Accesorios' => 'accessories_received',
                    'Problema' => 'problem_reported',
                    'Notas' => 'entry_notes'
                ];
                foreach ($map as $histKey => $dbKey) {
                    if (isset($original_values[$histKey]))
                        $order[$dbKey] = $original_values[$histKey];
                }
            }
        }
        ?>

        <div class="layout-grid">
            <!-- Left Column -->
            <div>

                <!-- 1. General Info -->
                <div class="form-section">
                    <div class="form-section-header">
                        <div style="display:flex; align-items:center; gap:0.5rem">
                            <i class="ph ph-info"></i> Información General
                        </div>
                        <!-- Toggle Mode Buttons -->
                        <?php if ($is_original_mode): ?>
                            <a href="?id=<?php echo $id; ?>&view_mode=current" class="btn btn-sm btn-primary"
                                style="font-size: 0.8rem; padding: 0.25rem 0.75rem;">
                                <i class="ph ph-eye"></i> Ver Actual
                            </a>
                        <?php elseif (!empty($original_values)): ?>
                            <a href="?id=<?php echo $id; ?>&view_mode=original" class="btn btn-sm btn-secondary"
                                style="font-size: 0.8rem; padding: 0.25rem 0.75rem; background: var(--p-bg-input); color: var(--p-text-muted); border: 1px solid var(--p-border);">
                                <i class="ph ph-clock-counter-clockwise"></i> Ver Original
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="info-grid">
                        <!-- Client -->
                        <div>
                            <h4 style="color: var(--p-primary); font-size: 0.9rem; margin-bottom: 1rem;">Cliente</h4>

                            <div class="info-group">
                                <span class="info-label">Nombre Completo</span>
                                <div class="info-value highlight"><?php echo htmlspecialchars($order['client_name']); ?>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                                <div class="info-group" style="margin-bottom: 0;">
                                    <span class="info-label">Teléfono</span>
                                    <div class="info-value"><i class="ph ph-phone"></i>
                                        <?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                                <div class="info-group" style="margin-bottom: 0;">
                                    <span class="info-label">Email</span>
                                    <div class="info-value"><i class="ph ph-envelope"></i>
                                        <?php echo htmlspecialchars($order['email']); ?></div>
                                </div>
                            </div>

                            <!-- Service Type -->
                            <div class="info-group">
                                <span class="info-label">Tipo de Servicio</span>
                                <div>
                                    <?php if ($order['service_type'] === 'warranty'): ?>
                                        <span
                                            style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.85rem; font-weight: 600; background: rgba(99, 102, 241, 0.1); color: #818cf8; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid rgba(99, 102, 241, 0.2);">
                                            <i class="ph ph-shield-check"></i> Garantía
                                        </span>
                                    <?php else: ?>
                                        <span
                                            style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.85rem; font-weight: 600; background: rgba(59, 130, 246, 0.1); color: #60a5fa; padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                            <i class="ph ph-wrench"></i> Servicio
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment -->
                        <div>
                            <h4 style="color: var(--p-primary); font-size: 0.9rem; margin-bottom: 1rem;">Equipo</h4>

                            <div class="info-group">
                                <span class="info-label">Equipo</span>
                                <div class="info-value highlight">
                                    <?php echo htmlspecialchars($order['equipment_type']); ?>
                                    <?php echo htmlspecialchars($order['brand']); ?>
                                </div>
                                <div class="info-value text-muted"><?php echo htmlspecialchars($order['model']); ?>
                                </div>
                                <?php if ($order['submodel']): ?>
                                    <div class="info-value text-muted" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($order['submodel']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Número de Serie</span>
                                <div class="info-value" style="font-family: monospace; letter-spacing: 0.05em;">
                                    <?php echo htmlspecialchars($order['serial_number']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Details -->
                <div class="form-section">
                    <div class="form-section-header">
                        <div style="display:flex; align-items:center; gap:0.5rem">
                            <i class="ph ph-clipboard-text"></i> Detalles del Servicio
                        </div>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Problema Reportado</span>
                        <div class="problem-box">
                            <?php echo nl2br(htmlspecialchars($order['problem_reported'])); ?>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-group">
                            <span class="info-label">Accesorios Recibidos</span>
                            <div class="problem-box" style="min-height: auto; background: var(--p-bg-input);">
                                <?php echo htmlspecialchars($order['accessories_received'] ?: 'Ninguno'); ?>
                            </div>
                        </div>

                        <div class="info-group">
                            <span class="info-label">Observaciones de Ingreso</span>
                            <div class="problem-box" style="min-height: auto; background: var(--p-bg-input);">
                                <?php echo nl2br(htmlspecialchars($order['entry_notes'] ?: 'Ninguna')); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div class="sidebar-sticky">
                <?php if (!$is_history_view && !$is_original_mode): ?>

                    <!-- Status Update -->
                    <?php if ($order['status'] !== 'delivered'): ?>
                        <div class="update-card" style="margin-bottom: 1.5rem; border-top: 4px solid var(--p-primary);">
                            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; color: var(--p-text-main);">
                                Actualizar Estado</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_status">

                                <div style="margin-bottom: 1rem;">
                                    <label
                                        style="display: block; font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 0.5rem;">Nuevo
                                        Estado</label>
                                    <select name="status" id="statusSelect" class="modern-select">
                                        <?php foreach ($statusLabels as $key => $label): ?>
                                            <?php if ($key !== 'delivered' && $key !== 'cancelled'): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $order['status'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <label
                                        style="display: block; font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 0.5rem;">Nota
                                        de Progreso</label>
                                    <textarea name="note" id="progressNote" class="modern-textarea" rows="3"
                                        placeholder="Ej. Se realizó cambio de repuesto..." required></textarea>
                                </div>

                                <button type="submit" class="btn-update">
                                    Guardar Cambios
                                </button>

                                <!-- Diagnosis Modal (Inside Form) -->
                                <div id="diagnosisModal"
                                    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
                                    <div
                                        style="background: var(--p-bg-card); padding: 2rem; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; border: 1px solid var(--p-border);">
                                        <h2 style="margin-top: 0; color: var(--p-primary); margin-bottom: 1.5rem;">Reporte de
                                            Diagnóstico</h2>

                                        <!-- Readonly Info -->
                                        <div
                                            style="margin-bottom: 1rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
                                            <div
                                                style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;">
                                                <span style="font-weight: bold; color: var(--p-primary);">Caso
                                                    #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <?php if ($order['diagnosis_number']): ?>
                                                    <span style="color: #fbbf24;">Diag
                                                        #<?php echo str_pad($order['diagnosis_number'], 5, '0', STR_PAD_LEFT); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($order['client_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--p-text-muted);">
                                                <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>
                                            </div>
                                            <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                                                <span style="color: var(--p-text-muted);">Falla:</span>
                                                <?php echo htmlspecialchars($order['problem_reported']); ?>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 1rem;">
                                            <label
                                                style="display: block; margin-bottom: 0.5rem; color: var(--p-text-muted); font-size: 0.85rem;">Procedimiento</label>
                                            <textarea name="diagnosis_procedure" id="diag_procedure" class="modern-textarea"
                                                rows="4" placeholder="Describe las pruebas realizadas..."></textarea>
                                        </div>

                                        <div style="margin-bottom: 1rem;">
                                            <label
                                                style="display: block; margin-bottom: 0.5rem; color: var(--p-text-muted); font-size: 0.85rem;">Conclusión
                                                / Solución</label>
                                            <textarea name="diagnosis_conclusion" id="diag_conclusion" class="modern-textarea"
                                                rows="4" placeholder="Conclusión técnica..."></textarea>
                                        </div>

                                        <div style="margin-bottom: 1.5rem;">
                                            <label
                                                style="display: block; margin-bottom: 0.5rem; color: var(--p-text-muted); font-size: 0.85rem;">Evidencia
                                                (Imágenes)</label>
                                            <input type="file" name="diagnosis_images[]" multiple accept="image/*"
                                                class="modern-input">
                                        </div>

                                        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                                            <button type="button" id="btnCancelDiag" class="btn btn-secondary"
                                                style="background: transparent; border: 1px solid var(--p-border); color: var(--p-text-main);">Cancelar</button>
                                            <button type="button" id="btnConfirmDiag" class="btn btn-primary">Guardar
                                                Diagnóstico</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Repair Modal (New) -->
                                <div id="repairModal"
                                    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
                                    <div
                                        style="background: var(--p-bg-card); padding: 2rem; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; border: 1px solid var(--p-border);">
                                        <h2 style="margin-top: 0; color: var(--p-primary); margin-bottom: 1.5rem;">Gestión de
                                            Reparación</h2>

                                        <!-- Readonly Info (Client/Device) -->
                                        <div
                                            style="margin-bottom: 1rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
                                            <div
                                                style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;">
                                                <span style="font-weight: bold; color: var(--p-primary);">Caso
                                                    #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                                <!-- Repair number is generated AFTER saving, so we don't show it here yet unless it already exists -->
                                                <?php if ($order['repair_number']): ?>
                                                    <span style="color: #34d399;">Rep
                                                        #<?php echo str_pad($order['repair_number'], 5, '0', STR_PAD_LEFT); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-weight: bold; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($order['client_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--p-text-muted);">
                                                <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>
                                            </div>
                                            <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                                                <span style="color: var(--p-text-muted);">Falla:</span>
                                                <?php echo htmlspecialchars($order['problem_reported']); ?>
                                            </div>
                                        </div>

                                        <!-- Previous Diagnosis Info (If available) -->
                                        <?php if ($order['diagnosis_conclusion']): ?>
                                            <div
                                                style="margin-bottom: 1.5rem; padding: 0.75rem; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.2); border-radius: 8px;">
                                                <label
                                                    style="display: block; font-size: 0.8rem; color: #fbbf24; margin-bottom: 0.25rem; font-weight: 600;">Diagnóstico
                                                    Previo</label>
                                                <div style="font-size: 0.9rem; color: var(--p-text-main); white-space: pre-line;">
                                                    <?php echo htmlspecialchars($order['diagnosis_conclusion']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div
                                            style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px;">
                                            <label
                                                style="display: block; font-size: 0.9rem; color: var(--p-text-muted); margin-bottom: 0.5rem; font-weight: 600;">🛠️
                                                Repuestos / Acciones</label>
                                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                <select id="partSelect" class="modern-select"
                                                    style="font-size: 0.85rem; flex: 1.5;">
                                                    <option value="">-- Seleccionar Repuesto --</option>
                                                    <option value="Pantalla">Pantalla</option>
                                                    <option value="Batería">Batería</option>
                                                    <option value="Teclado">Teclado</option>
                                                    <option value="Disco SSD">Disco SSD</option>
                                                    <option value="Memoria RAM">Memoria RAM</option>
                                                    <option value="Centro de Carga">Centro de Carga</option>
                                                    <option value="Fuente de Poder">Fuente de Poder</option>
                                                    <option value="Cargador">Cargador</option>
                                                    <option value="Mantenimiento General">Mantenimiento General</option>
                                                    <option value="Limpieza Interna">Limpieza Interna</option>
                                                    <option value="Instalación Windows">Instalación Windows</option>
                                                </select>
                                                <input type="text" id="partSN" placeholder="S/N" class="modern-input"
                                                    style="flex: 1; font-size: 0.85rem; padding: 0.5rem;">
                                                <button type="button" id="btnAddPart"
                                                    style="background: var(--p-border); color: white; border: none; padding: 0 0.75rem; border-radius: 6px; cursor: pointer; font-size: 1.2rem; height: 38px;">+</button>
                                            </div>
                                            <p style="font-size: 0.8rem; color: var(--p-text-muted); margin-top: 0.5rem;">Agrega
                                                los repuestos utilizados a la nota de reparación.</p>
                                        </div>

                                        <div style="margin-bottom: 1rem;">
                                            <label
                                                style="display: block; font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 0.5rem;">Nota
                                                de Reparación</label>
                                            <textarea id="repairNoteModal" class="modern-textarea" rows="4"
                                                placeholder="Detalles de la reparación y repuestos utilizados..."></textarea>
                                        </div>

                                        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                                            <button type="button" id="btnCancelRepair" class="btn btn-secondary"
                                                style="background: transparent; border: 1px solid var(--p-border); color: var(--p-text-main);">Cancelar</button>
                                            <button type="button" id="btnConfirmRepair" class="btn btn-primary"
                                                style="background: #a855f7; border-color: #a855f7;">Confirmar
                                                Reparación</button>
                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>

                        <script>
                                                                                               (function() {
                                                    var statusSelect = document.getElementById('statusSelect');
                                                    // Spare parts elements now in modal
                                                    var btnAddPart = document.getElementById('btnAddPart');
                                                    var partSelect = document.getElementById('partSelect');
                                                    var partSN = document.getElementById('partSN');
                                    
                                                    // Main note
                                                    var progressNote = document.getElementById('progressNote');
                                    
                                                    // Modals
                                                    var diagModal = document.getElementById('diagnosisModal');
                                                    var repairModal = document.getElementById('repairModal');
                                    
                                                    // Modal Inputs
                                                    var repairNoteModal = document.getElementById('repairNoteModal');

                                                    // Buttons
                                                    var btnConfirmDiag = document.getElementById('btnConfirmDiag');
                                                    var btnCancelDiag = document.getElementById('btnCancelDiag');
                                                    var btnConfirmRepair = document.getElementById('btnConfirmRepair');
                                                    var btnCancelRepair = document.getElementById('btnCancelRepair');

                                                    var previousStatus = statusSelect ? statusSelect.value : '';
                                                    var form = statusSelect ? statusSelect.closest('form') : null;

                                                    if(statusSelect) {
                                                        statusSelect.addEventListener('focus', function() {
                                                            previousStatus = this.value;
                                                        });

                                                        statusSelect.addEventListener('change', function() {
                                                            if (this.value === 'diagnosing') {
                                                                diagModal.style.display = 'flex';
                                                            } else if (this.value === 'in_repair') {
                                                                repairModal.style.display = 'flex';
                                                                // Pre-fill modal note if needed, or clear it
                                                                repairNoteModal.value = ""; 
                                                            } else {
                                                                diagModal.style.display = 'none';
                                                                repairModal.style.display = 'none';
                                                            }
                                                        });

                                                        // Diagnosis Modal Actions
                                                        if(btnCancelDiag) {
                                                            btnCancelDiag.addEventListener('click', function() {
                                                                diagModal.style.display = 'none';
                                                                statusSelect.value = previousStatus;
                                                            });
                                                        }
                                                        if(btnConfirmDiag) {
                                                            btnConfirmDiag.addEventListener('click', function() {
                                                                form.submit();
                                                            });
                                                        }

                                                        // Repair Modal Actions
                                                        if(btnCancelRepair) {
                                                            btnCancelRepair.addEventListener('click', function() {
                                                                repairModal.style.display = 'none';
                                                                statusSelect.value = previousStatus;
                                                            });
                                                        }
                                                        if(btnConfirmRepair) {
                                                            btnConfirmRepair.addEventListener('click', function() {
                                                                // Transfer note to main form
                                                                if(repairNoteModal.value.trim() !== "") {
                                                                    // Append or set
                                                                    progressNote.value = repairNoteModal.value;
                                                                }
                                                                form.submit();
                                                            });
                                                        }
                                                    }

                                                    // Spare Parts Logic (Inside Repair Modal)
                                                    if (btnAddPart && partSelect && repairNoteModal) {
                                                        btnAddPart.addEventListener('click', function() {
                                                            var val = partSelect.value;
                                                            var sn = partSN ? partSN.value.trim() : "";
                                                            if(val) {
                                                                var entry = "Se utiliza repuesto: " + val;
                                                                if(sn) entry += " (S/N: " + sn + ")";
                                                
                                                                repairNoteModal.value = (repairNoteModal.value ? repairNoteModal.value + "\n" : "") + entry;
                                                
                                                                // Reset inputs
                                                                partSelect.value = "";
                                                                if(partSN) partSN.value = "";
                                                            }
                                                        });
                                                    }
                                                })();
                                            </script>
                            
                                            <?php if ($autoPrintDiagnosis): ?>
                                                        <script>
                                                            document.addEventListener("DOMContentLoaded", function() {
                                                                window.location.href = 'print_diagnosis.php?id=<?php echo $id; ?>&autoprint=1';
                                                            });
                                                        </script>
                                            <?php endif; ?>

                                <?php else: ?>
                                            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                                                <i class="ph ph-check-circle" style="font-size: 1.5rem; color: #34d399;"></i>
                                                <div>
                                                    <strong style="color: #34d399; display: block;">Servicio Entregado</strong>
                                                    <span style="font-size: 0.85rem; color: var(--p-text-muted);">Proceso finalizado.</span>
                                                </div>
                                            </div>
                            
                                            <?php if (has_permission('module_re_enter_workshop', $pdo)): ?>
                                                        <div class="update-card" style="margin-bottom: 1.5rem; border-top: 4px solid var(--p-primary);">
                                                            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; color: var(--p-text-main);">
                                                                <i class="ph ph-arrow-u-down-left"></i> Reingresar a Taller
                                                            </h3>
                                                            <p style="font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 1rem;">
                                                                Tiene permisos para reabrir este caso. Seleccione el estado al que desea regresar.
                                                            </p>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="update_status">
                                        
                                                                <div style="margin-bottom: 1rem;">
                                                                    <label style="display: block; font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 0.5rem;">Nuevo Estado</label>
                                                                    <select name="status" class="modern-select">
                                                                        <option value="received">Recibido (Inicial)</option>
                                                                        <option value="diagnosing">En Diagnóstico</option>
                                                                        <option value="pending_approval">En Espera</option>
                                                                        <option value="in_repair">En Reparación</option>
                                                                        <option value="ready">Listo</option>
                                                                    </select>
                                                                </div>

                                                                <div style="margin-bottom: 1rem;">
                                                                    <label style="display: block; font-size: 0.85rem; color: var(--p-text-muted); margin-bottom: 0.5rem;">Motivo de Reingreso</label>
                                                                    <textarea name="note" class="modern-textarea" rows="2" placeholder="Ej. Cliente reporta falla recurrente..." required></textarea>
                                                                </div>

                                                                <button type="submit" class="btn-update">
                                                                    Confirmar Reingreso
                                                                </button>
                                                            </form>
                                                        </div>
                                            <?php endif; ?>
                            
                                <?php endif; ?>

                    <?php endif; ?>

                    <!-- History -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <div style="display:flex; align-items:center; gap:0.5rem">
                                <i class="ph ph-clock-counter-clockwise"></i> Historial
                            </div>
                        </div>
                        
                        <div style="padding-left: 0.5rem;">
                            <?php foreach ($history as $event): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-icon"></div>
                                            <div class="timeline-text">
                                                <?php echo $statusLabels[$event['action']] ?? $event['action']; ?>
                                            </div>
                                            <div class="timeline-date">
                                                <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?> • <?php echo htmlspecialchars($event['user_name']); ?>
                                            </div>
                                            <?php if ($event['notes']): ?>
                                                        <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--p-text-muted); background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 4px;">
                                                            <?php echo htmlspecialchars($event['notes']); ?>
                                                        </div>
                                            <?php endif; ?>
                                        </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
<?php require_once '../../includes/footer.php'; ?>
