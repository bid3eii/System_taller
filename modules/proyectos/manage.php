<?php
// modules/proyectos/manage.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('proyectos', $pdo) && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT ps.*, u.username as tech_name 
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE ps.id = ?
");
$stmt->execute([$id]);
$survey = $stmt->fetch();

if (!$survey) {
    die("Proyecto no encontrado.");
}

// Fetch materials
$stmt = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$materials = $stmt->fetchAll();

// Fetch active technicians for assignment
$stmt_techs = $pdo->prepare("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username ASC");
$stmt_techs->execute();
$techs = $stmt_techs->fetchAll();

// Handle tech assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_tech') {
    if (isset($_POST['tech_ids']) && is_array($_POST['tech_ids'])) {
        $valid_tech_ids = [];
        foreach ($_POST['tech_ids'] as $tid) {
            if ((int) $tid > 0) {
                $valid_tech_ids[] = (int) $tid;
            }
        }

        $tech_ids_str = implode(',', $valid_tech_ids);

        $stmt_update = $pdo->prepare("UPDATE project_surveys SET assigned_tech_ids = ? WHERE id = ?");
        if ($stmt_update->execute([$tech_ids_str, $id])) {
            $_SESSION['success_msg'] = "Personal asignado correctamente.";

            // --- Auto-generate or update commissions ---
            $stmt_p = $pdo->prepare("SELECT title, client_name FROM project_surveys WHERE id = ?");
            $stmt_p->execute([$id]);
            $proj = $stmt_p->fetch();

            $stmt_all_c = $pdo->prepare("SELECT id, tech_id, estado FROM comisiones WHERE reference_id = ? AND tipo = 'PROYECTO'");
            $stmt_all_c->execute([$id]);
            $existing_comisiones = $stmt_all_c->fetchAll(PDO::FETCH_ASSOC);

            $comisiones_map = [];
            foreach ($existing_comisiones as $c) {
                $comisiones_map[$c['tech_id']] = $c;
            }

            foreach ($valid_tech_ids as $tid) {
                if (!isset($comisiones_map[$tid])) {
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
                        $proj['client_name'],
                        $proj['title'],
                        $tech_name,
                        "Proyecto_#" . str_pad($id, 4, '0', STR_PAD_LEFT),
                        $tid,
                        $id
                    ]);
                }
            }

            foreach ($comisiones_map as $c_tech_id => $c) {
                if (!in_array($c_tech_id, $valid_tech_ids)) {
                    if ($c['estado'] === 'PENDIENTE') {
                        $pdo->prepare("DELETE FROM comisiones WHERE id = ?")->execute([$c['id']]);
                    }
                }
            }
            // -------------------------------------------------------------

            $stmt->execute([$id]);
            $survey = $stmt->fetch();
        } else {
            $_SESSION['error_msg'] = "Error al asignar personal.";
        }
    } else {
        $stmt_update = $pdo->prepare("UPDATE project_surveys SET assigned_tech_ids = NULL WHERE id = ?");
        if ($stmt_update->execute([$id])) {
            $_SESSION['success_msg'] = "Personal desasignado.";
            $pdo->prepare("DELETE FROM comisiones WHERE reference_id = ? AND tipo = 'PROYECTO' AND estado = 'PENDIENTE'")->execute([$id]);
            $stmt->execute([$id]);
            $survey = $stmt->fetch();
        } else {
            $_SESSION['error_msg'] = "Error al quitar personal.";
        }
    }
}

$page_title = 'Gestionar Proyecto #' . str_pad($id, 5, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    /* Modern Premium Redesign for View Details */
    .view-header-glass {
        background: rgba(17, 24, 39, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 1rem;
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
    }

    /* Custom Select in Action Cards */
    .modern-select {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(99, 102, 241, 0.3);
        color: var(--text-primary);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        width: 100%;
        font-size: 0.95rem;
        transition: all 0.2s;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1em;
    }

    .modern-select:hover {
        border-color: rgba(99, 102, 241, 0.6);
        background: rgba(15, 23, 42, 0.8);
    }

    .modern-select:focus {
        border-color: var(--primary-500);
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .modern-select option {
        background-color: var(--bg-card);
        color: var(--text-primary);
    }

    .view-header-title h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .view-header-title .id-text {
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .view-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .glass-card {
        background: rgba(30, 41, 59, 0.4);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .glass-card:hover {
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .glass-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .glass-card-header i {
        font-size: 1.5rem;
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .glass-card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        letter-spacing: 0.5px;
    }

    .action-card {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.8));
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 1rem;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .action-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.5), transparent);
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .modern-table th {
        background: rgba(0, 0, 0, 0.2);
        color: var(--text-muted);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        padding: 1rem;
        text-align: left;
    }

    .modern-table th:first-child {
        border-top-left-radius: 8px;
        border-bottom-left-radius: 8px;
    }

    .modern-table th:last-child {
        border-top-right-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    .modern-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(128, 128, 128, 0.1);
        color: var(--text-primary);
    }

    .modern-table tr:last-child td {
        border-bottom: none;
    }

    .modern-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    /* TinyMCE Un-reset */
    .rich-text-content {
        color: #cbd5e1;
        font-size: 0.92rem;
        line-height: 1.7;
    }

    .rich-text-content>*:first-child {
        margin-top: 0 !important;
    }

    .rich-text-content p:empty {
        display: none;
    }

    .rich-text-content p>br:only-child {
        display: none;
    }

    .rich-text-content ul {
        padding-left: 1.25rem;
        margin: 0 0 0.5rem 0;
        list-style-type: disc !important;
    }

    .rich-text-content ol {
        padding-left: 1.25rem;
        margin: 0 0 0.5rem 0;
        list-style-type: decimal !important;
    }

    .rich-text-content li {
        margin-bottom: 0.3rem;
    }

    .rich-text-content p {
        margin-top: 0;
        margin-bottom: 0.55rem;
    }

    .rich-text-content strong {
        color: #e2e8f0;
    }

    .rich-text-content h1,
    .rich-text-content h2,
    .rich-text-content h3,
    .rich-text-content h4 {
        color: #f1f5f9;
        margin-top: 1rem;
        margin-bottom: 0.3rem;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Override inline colors injected by Microsoft Word / rich-text paste */
    .rich-text-content span,
    .rich-text-content p,
    .rich-text-content li,
    .rich-text-content div {
        color: #cbd5e1 !important;
    }

    .rich-text-content strong,
    .rich-text-content b {
        color: #e2e8f0 !important;
    }

    .rich-text-content a {
        color: #93c5fd !important;
    }

    /* Strip Word-specific MsoNormal spacing */
    .MsoNormal,
    .MsoListParagraph,
    .MsoListParagraphCxSpFirst,
    .MsoListParagraphCxSpMiddle,
    .MsoListParagraphCxSpLast {
        color: #cbd5e1 !important;
        margin: 0 0 0.3rem 0 !important;
        margin-left: 0 !important;
        padding-left: 0 !important;
        text-indent: 0 !important;
        font-family: inherit !important;
        font-size: inherit !important;
        background-color: transparent !important;
    }

    /* Prevent Word content from overflowing the card */
    .rich-text-content {
        overflow: hidden;
        max-width: 100%;
    }

    .rich-text-content * {
        max-width: 100%;
        background-color: transparent !important;
    }
</style>

<div class="animate-enter" style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">
    <!-- TOP HEADER HERO -->
    <div class="view-header-glass">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <a href="index.php" class="btn btn-secondary"
                style="border-radius: 50%; width: 44px; height: 44px; padding: 0; display: flex; justify-content: center; align-items: center; background: rgba(255,255,255,0.05); border: none;">
                <i class="ph ph-arrow-left" style="font-size: 1.2rem;"></i>
            </a>
            <div class="view-header-title">
                <h1>
                    <span>Gestión de Proyecto</span> <span
                        class="id-text">#<?php echo str_pad($survey['id'], 5, '0', STR_PAD_LEFT); ?></span>

                    <?php
                    $statusMaps = [
                        'submitted' => ['Aprobación Pdte', 'blue'],
                        'approved' => ['Aprobado / Listo', 'indigo'],
                        'in_progress' => ['En Progreso', 'orange'],
                        'completed' => ['Completado', 'green']
                    ];
                    $col = $statusMaps[$survey['status']][1] ?? 'gray';
                    $lbl = $statusMaps[$survey['status']][0] ?? $survey['status'];
                    ?>
                    <span class="status-badge status-<?php echo $col; ?>"
                        style="font-size: 0.9rem; padding: 0.3rem 0.8rem;">
                        <?php echo strtoupper($lbl); ?>
                    </span>
                </h1>
                <p class="text-muted"
                    style="margin: 0.4rem 0 0 0; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-calendar-blank"></i> Registrado el
                    <?php echo date('d/m/Y \a \l\a\s h:i A', strtotime($survey['created_at'])); ?>
                </p>
            </div>
        </div>
        <div class="view-actions">
            <a href="../levantamientos/print.php?id=<?php echo $survey['id']; ?>" target="_blank"
                class="btn btn-secondary" style="white-space: nowrap; backdrop-filter: blur(5px);">
                <i class="ph ph-printer"></i> Imprimir Expediente
            </a>
        </div>
    </div>

    <!-- MAIN GRID LAYOUT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- LEFT COLUMN: Content Heavy -->
        <div style="display: flex; flex-direction: column;">

            <div class="glass-card" style="padding: 0; margin-bottom: 2rem; overflow: hidden;">
                <!-- Accordion Header (clickable) -->
                <div id="summary-header" onclick="toggleSummary()" style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary-400); display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-info" style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.1rem; line-height: 1.2;">Resumen del Expediente</h3>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">Detalles del proyecto y cliente</p>
                        </div>
                    </div>
                    <i class="ph ph-caret-down" id="summary-caret" style="font-size: 1.3rem; color: var(--text-muted); transition: transform 0.25s ease;"></i>
                </div>

                <!-- Accordion Body (hidden by default) -->
                <div id="summary-body" style="max-height: 0; overflow: hidden; transition: max-height 0.35s ease, padding 0.25s ease;">
                    <div style="padding: 0 1.5rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; gap: 1.25rem; padding-top: 1.5rem;">

                        <!-- Cliente -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-buildings" style="font-size: 1.2rem;"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Cliente / Empresa</div>
                                <div style="font-weight: 500; font-size: 1.05rem; color: var(--text-primary);"><?php echo htmlspecialchars($survey['client_name']); ?></div>
                            </div>
                        </div>

                        <!-- Proyecto -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-folder" style="font-size: 1.2rem;"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Proyecto</div>
                                <div style="font-weight: 400; font-size: 1rem; color: var(--text-primary);"><?php echo htmlspecialchars($survey['title']); ?></div>
                            </div>
                        </div>

                        <!-- Técnico -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-user-circle" style="font-size: 1.2rem;"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Técnico Responsable</div>
                                <div style="font-weight: 400; font-size: 1rem; color: var(--text-primary);"><?php echo htmlspecialchars($survey['tech_name']); ?></div>
                            </div>
                        </div>

                        <!-- Fecha -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-calendar-blank" style="font-size: 1.2rem;"></i></div>
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.2rem;">Fecha de Creación</div>
                                <div style="font-weight: 400; font-size: 1rem; color: var(--text-primary);"><?php echo date('d/m/Y \a \l\a\s h:i A', strtotime($survey['created_at'])); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($survey['general_description'])): ?>
                        <!-- Descripción -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.05);">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-article" style="font-size: 1.2rem;"></i></div>
                            <div style="flex-grow: 1;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.4rem;">Descripción del Proyecto</div>
                                <div class="rich-text-content" style="font-weight: 400; font-size: 0.95rem; color: var(--text-primary); line-height: 1.6; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.02);"><?php echo $survey['general_description']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($survey['scope_activities'])): ?>
                        <!-- Alcance -->
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                            <div style="color: var(--text-muted); margin-top: 2px;"><i class="ph ph-list-checks" style="font-size: 1.2rem;"></i></div>
                            <div style="flex-grow: 1;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.4rem;">Alcance y Actividades</div>
                                <div class="rich-text-content" style="font-weight: 400; font-size: 0.95rem; color: var(--text-primary); line-height: 1.6; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.02);"><?php echo $survey['scope_activities']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="glass-card" style="padding: 0; overflow: hidden;">
                <!-- Accordion Header (clickable) -->
                <div onclick="toggleMaterials()" style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary-400); display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-stack" style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.1rem; line-height: 1.2;">Requerimientos y Materiales</h3>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                                <?php echo count($materials); ?> ítems registrados
                            </p>
                        </div>
                    </div>
                    <i class="ph ph-caret-down" id="materials-caret" style="font-size: 1.3rem; color: var(--text-muted); transition: transform 0.25s ease;"></i>
                </div>

                <!-- Accordion Body -->
                <div id="materials-body" style="max-height: 0; overflow: hidden; transition: max-height 0.35s ease;">
                    <div style="border-top: 1px solid rgba(255,255,255,0.05);">
                        <table class="modern-table" style="margin: 0; border: none;">
                            <thead>
                                <tr>
                                    <th>Descripción del Artículo</th>
                                    <th style="width: 120px; text-align: center;">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($materials) > 0): ?>
                                    <?php foreach ($materials as $item): ?>
                                        <tr>
                                            <td style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                                <div style="font-weight: 500; color: var(--text-primary);">
                                                    <?php echo htmlspecialchars($item['item_description']); ?>
                                                </div>
                                                <?php if (!empty($item['notes'])): ?>
                                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                                                        <?php echo htmlspecialchars($item['notes']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: center;">
                                                <span style="display: inline-block; white-space: nowrap; background: rgba(52, 211, 153, 0.1); color: #34d399; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 4px; border: 1px solid rgba(52, 211, 153, 0.2); font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($item['quantity'] . ' ' . $item['unit']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted" style="padding: 2rem; border-bottom: none;">
                                            No hay materiales registrados para este proyecto.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Workflow Controls -->
        <div style="display: flex; flex-direction: column;">

            <!-- ESTADO DEL PROYECTO -->
            <div class="action-card"
                style="margin-bottom: 1.5rem; background: var(--bg-card); border: 1px solid rgba(99, 102, 241, 0.2);">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div
                        style="width: 32px; height: 32px; border-radius: 8px; background: rgba(99, 102, 241, 0.2); color: var(--primary-400); display: flex; align-items: center; justify-content: center;">
                        <i class="ph ph-kanban" style="font-size: 1.2rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Estado del Proyecto</h4>
                </div>

                <form method="POST" action="update_status.php" id="form-status"
                    style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">

                    <div>
                        <select name="status" class="modern-select">
                            <option value="draft" <?php echo $survey['status'] == 'draft' ? 'selected' : ''; ?>>
                                📝 Borrador / En Levantamiento
                            </option>
                            <option value="submitted" <?php echo $survey['status'] == 'submitted' ? 'selected' : ''; ?>>
                                📤 Fidelizado / Aprobación Pdte
                            </option>
                            <option value="approved" <?php echo $survey['status'] == 'approved' ? 'selected' : ''; ?>>
                                ✅ Aprobado / Listo para Iniciar
                            </option>
                            <option value="in_progress" <?php echo $survey['status'] == 'in_progress' ? 'selected' : ''; ?>>
                                🔧 En Progreso Operativo
                            </option>
                            <option value="completed" <?php echo $survey['status'] == 'completed' ? 'selected' : ''; ?>>
                                🏁 Completado / Entregado
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"
                        style="width: 100%; justify-content: center; padding: 0.75rem; font-weight: 600; font-size: 0.95rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);">
                        Actualizar Estado Operativo
                    </button>
                </form>
            </div>

            <!-- ESTADO FINANCIERO -->
            <div class="action-card"
                style="background: linear-gradient(145deg, rgba(16, 185, 129, 0.1), rgba(15, 23, 42, 0.8)); border-color: rgba(16, 185, 129, 0.2); margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div
                        style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center;">
                        <i class="ph ph-currency-circle-dollar" style="font-size: 1.2rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Finanzas y Comisión</h4>
                </div>

                <?php if ($survey['payment_status'] === 'pagado'): ?>
                    <!-- Already paid: show clean confirmation block -->
                    <div
                        style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.3); border-radius: 10px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div
                            style="width: 44px; height: 44px; border-radius: 50%; background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="ph ph-check-circle" style="font-size: 1.5rem; color: #34d399;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #34d399; font-size: 1rem;">Ciclo Financiero Cerrado</div>
                            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">Este proyecto ya
                                fue liquidado. Revisa el módulo de <strong>Comisiones</strong> para pagar al técnico.</div>
                            <?php if (!empty($survey['invoice_number'])): ?>
                                <div
                                    style="margin-top: 0.5rem; font-size: 0.85rem; color: #fbbf24; font-weight: 500; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="ph ph-receipt"></i> Factura:
                                    <?php echo htmlspecialchars($survey['invoice_number']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="../comisiones/index.php?search=<?php echo urlencode($survey['title']); ?>"
                        class="btn btn-secondary"
                        style="width: 100%; justify-content: center; border-color: rgba(16,185,129,0.3); color: #34d399;">
                        <i class="ph ph-arrow-square-out"></i> Ver en Comisiones
                    </a>
                <?php else: ?>
                    <form method="POST" action="update_payment_status.php" id="form-payment-status"
                        style="display: flex; flex-direction: column; gap: 1rem;">
                        <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; font-weight: 600;">Estado
                                de Pago</label>
                            <select name="payment_status" id="payment_status_select" class="modern-select"
                                style="border-color: rgba(16, 185, 129, 0.3); width: 100%;">
                                <option value="pendiente" <?php echo $survey['payment_status'] === 'pendiente' ? 'selected' : ''; ?>>⏳ Facturación Pendiente</option>
                                <option value="pagado" <?php echo $survey['payment_status'] === 'pagado' ? 'selected' : ''; ?>>✅ Liquidado / Pagado</option>
                            </select>
                        </div>
                        <div id="invoice_field_container" style="display: none;">
                            <label
                                style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; font-weight: 600;">Número
                                de Factura</label>
                            <input type="text" name="invoice_number" id="invoice_number_input" class="modern-input"
                                placeholder="Ej. F001-000234" style="border-color: rgba(16, 185, 129, 0.2); width: 100%;">
                        </div>
                        <button type="button" class="btn btn-success" id="btn-actualizar-pagos"
                            style="width: 100%; justify-content: center; padding: 0.75rem; font-weight: 600; box-shadow: 0 4px 12px rgba(16,185,129,0.25);"
                            onclick="confirmPaymentChange(event)">
                            Actualizar Pagos / Comisiones
                        </button>
                    </form>
                    <script>
                        document.getElementById('payment_status_select').addEventListener('change', function () {
                            const invoiceContainer = document.getElementById('invoice_field_container');
                            if (this.value === 'pagado') {
                                invoiceContainer.style.display = 'block';
                            } else {
                                invoiceContainer.style.display = 'none';
                            }
                        });
                        // trigger on load
                        document.getElementById('payment_status_select').dispatchEvent(new Event('change'));
                    </script>
                <?php endif; ?>

                <script>
                    function confirmPaymentChange(event) {
                        const select = document.getElementById('payment_status_select');
                        if (select && select.value === 'pagado' && '<?php echo $survey['payment_status']; ?>' !== 'pagado') {
                            event.preventDefault();
                            Swal.fire({
                                title: '¿Confirmar Liquidación?',
                                text: 'Marcar como PAGADO cerrará el ciclo comercial, guardará la factura y generará automáticamente la comisión para el técnico asignado. Esta acción no se puede deshacer de forma sencilla.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#10b981',
                                cancelButtonColor: '#64748b',
                                confirmButtonText: '<i class="ph ph-check-circle"></i> Sí, marcar como Pagado',
                                cancelButtonText: 'Cancelar',
                                background: '#1e293b',
                                color: '#fff'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Make invoice number required when paying
                                    const invoiceInput = document.getElementById('invoice_number_input');
                                    if (invoiceInput && invoiceInput.value.trim() === '') {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Número de Factura Requerido',
                                            text: 'Por favor, ingrese el número de factura para poder liquidar el proyecto y generar la comisión.',
                                            background: '#1e293b',
                                            color: '#fff'
                                        });
                                    } else {
                                        document.getElementById('form-payment-status').submit();
                                    }
                                }
                            });
                            return false;
                        }
                        return true;
                    }
                </script>

                <?php if ($survey['payment_status'] === 'pagado'): ?>
                    <div
                        style="margin-top: 1rem; padding: 0.75rem; background: rgba(0,0,0,0.2); border-radius: 6px; font-size: 0.8rem; color: #94a3b8; display: flex; gap: 0.5rem; border-left: 2px solid #10b981;">
                        <i class="ph ph-info" style="color: #34d399; font-size: 1.1rem; flex-shrink: 0;"></i>
                        <div>Proyecto pagado y ciclo financiero cerrado. Revise el módulo de Comisiones para pagarle al
                            técnico.</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MANO DE OBRA Y PERSONAL (Moved from Left Column) -->
            <div class="action-card"
                style="background: var(--bg-card); border: 1px solid rgba(255, 255, 255, 0.05); margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div
                        style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: var(--text-muted); display: flex; align-items: center; justify-content: center;">
                        <i class="ph ph-users" style="font-size: 1.2rem;"></i>
                    </div>
                    <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Personal y Tiempo Requerido</h4>
                </div>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div
                        style="background: rgba(0,0,0,0.2); padding: 0.85rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.03);">
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.2rem;">
                            Personal Asignado</div>

                        <form method="POST" style="margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                            <input type="hidden" name="action" value="assign_tech">
                            
                            <div class="tech-multiselect-wrapper" style="position: relative;">
                                <div class="form-control tech-select-trigger" onclick="toggleTechDropdown(event)">
                                    <span id="tech-select-text">Seleccionar técnicos...</span>
                                    <i class="ph ph-caret-down" id="tech-caret"></i>
                                </div>
                                
                                <div id="tech-dropdown" class="tech-dropdown-list" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; width: 100%; max-height: 250px; overflow-y: auto; z-index: 9999; background: rgb(15, 23, 42); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.4rem; box-shadow: 0 10px 20px rgba(0,0,0,0.5);">
                                    <?php
                                    $assigned_arr = array_filter(explode(',', $survey['assigned_tech_ids'] ?? ''));
                                    foreach ($techs as $t):
                                        $is_selected = in_array($t['id'], $assigned_arr);
                                    ?>
                                        <label style="display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.8rem; border-radius: 8px; cursor: pointer; color: var(--text-primary); font-size: 0.9rem; transition: background 0.15s;" onmouseover="this.style.background='rgba(99,102,241,0.15)'" onmouseout="this.style.background='transparent'">
                                            <input type="checkbox" name="tech_ids[]" value="<?php echo $t['id']; ?>" 
                                                   <?php echo $is_selected ? 'checked' : ''; ?>
                                                   onchange="updateTechSelectText()"
                                                   style="accent-color: #6366f1; width: 16px; height: 16px; flex-shrink: 0;">
                                            <i class="ph ph-user" style="color: #6366f1; font-size: 1rem;"></i>
                                            <span><?php echo htmlspecialchars($t['username']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                <i class="ph ph-check"></i> Guardar Asignaciones
                            </button>
                        </form>

                        <style>
                            .tech-select-trigger {
                                cursor: pointer;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                user-select: none;
                                transition: border-color 0.2s;
                            }
                            .tech-select-trigger:hover {
                                border-color: var(--primary-500, #6366f1);
                            }
                            .tech-select-trigger.open {
                                border-color: var(--primary-500, #6366f1);
                                box-shadow: 0 0 0 4px rgba(99,102,241,0.2);
                            }
                        </style>
                        <script>
                            function toggleTechDropdown(e) {
                                e.stopPropagation();
                                const dropdown = document.getElementById('tech-dropdown');
                                const trigger = document.querySelector('.tech-select-trigger');
                                const caret = document.getElementById('tech-caret');
                                const isOpen = dropdown.style.display !== 'none';
                                dropdown.style.display = isOpen ? 'none' : 'block';
                                trigger.classList.toggle('open', !isOpen);
                                caret.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                                caret.style.transition = 'transform 0.2s';
                            }
                            
                            document.addEventListener('click', function(e) {
                                const dropdown = document.getElementById('tech-dropdown');
                                const container = document.querySelector('.tech-multiselect-wrapper');
                                if (dropdown && container && !container.contains(e.target)) {
                                    dropdown.style.display = 'none';
                                    const trigger = document.querySelector('.tech-select-trigger');
                                    const caret = document.getElementById('tech-caret');
                                    if (trigger) trigger.classList.remove('open');
                                    if (caret) { caret.style.transform = 'rotate(0deg)'; caret.style.transition = 'transform 0.2s'; }
                                }
                            });

                            function updateTechSelectText() {
                                const checkboxes = document.querySelectorAll('input[name="tech_ids[]"]:checked');
                                const textSpan = document.getElementById('tech-select-text');
                                if (checkboxes.length === 0) {
                                    textSpan.textContent = 'Seleccionar técnicos...';
                                } else if (checkboxes.length === 1) {
                                    textSpan.textContent = checkboxes[0].parentElement.querySelector('span').textContent.trim();
                                } else {
                                    textSpan.textContent = checkboxes.length + ' técnicos seleccionados';
                                }
                            }
                            
                            document.addEventListener('DOMContentLoaded', updateTechSelectText);
                        </script>
                    </div>
                    <div
                        style="background: rgba(0,0,0,0.2); padding: 0.85rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.03);">
                        <div
                            style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.2rem;">
                            Tiempo Estimado</div>
                        <div style="font-weight: 600; color: #fbbf24; font-size: 0.95rem;">
                            <?php echo !empty($survey['estimated_time']) ? htmlspecialchars($survey['estimated_time']) : '<span class="text-muted" style="font-style: italic;">No especificado</span>'; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>



<script>
    // Summary accordion toggle
    var summaryOpen = false;
    function toggleSummary() {
        summaryOpen = !summaryOpen;
        const body = document.getElementById('summary-body');
        const caret = document.getElementById('summary-caret');
        if (summaryOpen) {
            body.style.maxHeight = body.scrollHeight + 600 + 'px';
            caret.style.transform = 'rotate(180deg)';
        } else {
            body.style.maxHeight = '0';
            caret.style.transform = 'rotate(0deg)';
        }
    }

    // Materials accordion toggle
    var materialsOpen = false;
    function toggleMaterials() {
        materialsOpen = !materialsOpen;
        const body = document.getElementById('materials-body');
        const caret = document.getElementById('materials-caret');
        if (materialsOpen) {
            body.style.maxHeight = body.scrollHeight + 400 + 'px';
            caret.style.transform = 'rotate(180deg)';
        } else {
            body.style.maxHeight = '0';
            caret.style.transform = 'rotate(0deg)';
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>