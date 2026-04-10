<?php
// modules/proyectos/manage.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
$user_role = $_SESSION['role'] ?? $_SESSION['role_name'] ?? '';
if (!can_access_module('proyectos', $pdo) && $user_role !== 'superadmin' && $user_role !== 'admin') {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

$stmt_survey = $pdo->prepare("
    SELECT ps.*, u.username as tech_name 
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE ps.id = ?
");
$stmt_survey->execute([$id]);
$survey = $stmt_survey->fetch();

if (!$survey) {
    die("Proyecto no encontrado.");
}

// Fetch materials
$stmt_materials = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
$stmt_materials->execute([$id]);
$materials = $stmt_materials->fetchAll();

// Fetch tools assigned to this project name
$stmtTools = $pdo->prepare("
    SELECT ta.id AS assignment_id, t.name, tai.quantity, tai.status, ta.assigned_to
    FROM tool_assignments ta
    JOIN tool_assignment_items tai ON ta.id = tai.assignment_id
    JOIN tools t ON tai.tool_id = t.id
    WHERE ta.project_name = ?
");
$stmtTools->execute([$survey['title']]);
$assigned_tools = $stmtTools->fetchAll(PDO::FETCH_ASSOC);

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
                        "#P" . str_pad($id, 4, '0', STR_PAD_LEFT),
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

            $stmt_survey->execute([$id]);
            $survey = $stmt_survey->fetch();
        } else {
            $_SESSION['error_msg'] = "Error al asignar personal.";
        }
    } else {
        $stmt_update = $pdo->prepare("UPDATE project_surveys SET assigned_tech_ids = NULL WHERE id = ?");
        if ($stmt_update->execute([$id])) {
            $_SESSION['success_msg'] = "Personal desasignado.";
            $pdo->prepare("DELETE FROM comisiones WHERE reference_id = ? AND tipo = 'PROYECTO' AND estado = 'PENDIENTE'")->execute([$id]);
            $stmt_survey->execute([$id]);
            $survey = $stmt_survey->fetch();
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

    <!-- MESSAGES -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="ph ph-check-circle" style="font-size: 1.25rem;"></i>
            <span><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></span>
        </div>
    <?php elseif (isset($_SESSION['success_message'])): ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="ph ph-check-circle" style="font-size: 1.25rem;"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="ph ph-warning-circle" style="font-size: 1.25rem;"></i>
            <span><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></span>
        </div>
    <?php elseif (isset($_SESSION['error_message'])): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="ph ph-warning-circle" style="font-size: 1.25rem;"></i>
            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
        </div>
    <?php endif; ?>

    <!-- MAIN GRID LAYOUT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- LEFT COLUMN: Content Heavy -->
        <div style="display: flex; flex-direction: column;">

            <div class="glass-card" style="padding: 0; margin-bottom: 1.2rem; overflow: hidden;">
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

            <div class="glass-card" style="padding: 0; margin-bottom: 1.2rem; overflow: hidden;">
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

            <!-- Herramientas Accordion -->
            <div class="glass-card" style="padding: 0; margin-bottom: 1.2rem; overflow: hidden;">
                <div onclick="toggleTools()" style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-wrench" style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.1rem; line-height: 1.2;">Herramientas Cargadas</h3>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                                <?php echo count($assigned_tools); ?> ítems en uso
                            </p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if (count($assigned_tools) > 0): ?>
                            <?php 
                            $distinct_assignments = array_unique(array_column($assigned_tools, 'assignment_id'));
                            foreach($distinct_assignments as $a_id): ?>
                                <a href="../tools/print_assignment.php?id=<?php echo $a_id; ?>" target="_blank" onclick="event.stopPropagation();" class="btn btn-sm" style="background: rgba(255,255,255,0.05); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; padding: 0.25rem 0.5rem; text-decoration: none;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" title="Imprimir Acta #<?php echo $a_id; ?>">
                                    <i class="ph ph-printer"></i> #<?php echo str_pad($a_id, 4, '0', STR_PAD_LEFT); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <a href="../tools/assign.php?project=<?php echo urlencode($survey['title']); ?>" onclick="event.stopPropagation();" class="btn btn-sm" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 4px; padding: 0.25rem 0.5rem; text-decoration: none;" onmouseover="this.style.background='rgba(245, 158, 11, 0.25)'" onmouseout="this.style.background='rgba(245, 158, 11, 0.15)'">
                            <i class="ph ph-plus-circle"></i> Asignar
                        </a>

                        <i class="ph ph-caret-down" id="tools-caret" style="font-size: 1.3rem; color: var(--text-muted); transition: transform 0.25s ease; margin-left: 0.5rem;"></i>
                    </div>
                </div>

                <div id="tools-body" style="max-height: 0; overflow: hidden; transition: max-height 0.35s ease;">
                    <div style="border-top: 1px solid rgba(255,255,255,0.05);">
                        <table class="modern-table" style="margin: 0; border: none;">
                            <thead>
                                <tr>
                                    <th>Herramienta</th>
                                    <th>Asignado A</th>
                                    <th style="width: 120px; text-align: center;">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assigned_tools) > 0): ?>
                                    <?php foreach ($assigned_tools as $tool): ?>
                                        <tr>
                                            <td style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                                <div style="font-weight: 500; color: var(--text-primary);">
                                                    <?php echo htmlspecialchars($tool['name']); ?>
                                                </div>
                                            </td>
                                            <td style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: var(--text-muted);">
                                                <?php echo htmlspecialchars($tool['assigned_to']); ?>
                                                <br>
                                                <?php 
                                                    $statusColor = $tool['status'] === 'returned' ? '#34d399' : '#fbbf24';
                                                    $statusText = $tool['status'] === 'returned' ? 'Devuelto' : 'En Uso';
                                                ?>
                                                <span style="font-size: 0.75rem; color: <?php echo $statusColor; ?>; font-weight: 600;">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: center;">
                                                <span style="display: inline-block; white-space: nowrap; background: rgba(245, 158, 11, 0.1); color: #fbbf24; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.85rem;">
                                                    <?php echo (int)$tool['quantity']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted" style="padding: 2rem; border-bottom: none; font-size: 0.9rem;">
                                            No hay herramientas registradas para este proyecto.<br>
                                            <span style="font-size: 0.8rem; opacity: 0.7; color: var(--text-muted);">(Nota: Se consultan emparejando el nombre exacto del proyecto: "<?php echo htmlspecialchars($survey['title']); ?>")</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Personal y Tiempo Accordion -->
            <div class="glass-card" style="padding: 0; margin-bottom: 1.2rem; overflow: hidden;">
                <div onclick="togglePersonal()" style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary-400); display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-users" style="font-size: 1.4rem;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary); font-size: 1.1rem; line-height: 1.2;">Personal y Tiempo</h3>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">Asignación de recursos</p>
                        </div>
                    </div>
                    <i class="ph ph-caret-down" id="personal-caret" style="font-size: 1.3rem; color: var(--text-muted); transition: transform 0.25s ease;"></i>
                </div>

                <div id="personal-body" style="max-height: 0; overflow: hidden; transition: max-height 0.35s ease;">
                    <div style="padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; gap: 1.25rem;">
                        
                        <!-- Form Asignación -->
                        <div style="background: rgba(0,0,0,0.2); padding: 1.2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.02);">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #cbd5e1; text-transform: uppercase; margin-bottom: 0.85rem; font-weight: 700; letter-spacing: 0.5px;">
                                <i class="ph ph-user-gear" style="color: #94a3b8; font-size: 0.9rem;"></i> Selección de Personal
                            </label>

                            <form method="POST" style="margin: 0; display: flex; flex-direction: column; gap: 1rem;">
                                <input type="hidden" name="action" value="assign_tech">
                                
                                <div class="tech-multiselect-wrapper" style="position: relative;">
                                    <div class="form-control tech-select-trigger" onclick="toggleTechDropdown(event)" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255,255,255,0.1); color: #f8fafc; padding: 0.85rem 1rem; border-radius: 8px;">
                                        <span id="tech-select-text" style="font-size: 0.95rem;">Seleccionar técnicos...</span>
                                        <i class="ph ph-caret-down" id="tech-caret-dropdown"></i>
                                    </div>
                                    
                                    <div id="tech-dropdown" class="tech-dropdown-list" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; width: 100%; max-height: 250px; overflow-y: auto; z-index: 9999; background: rgb(15, 23, 42); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.7);">
                                        <?php
                                        $assigned_arr = array_filter(explode(',', $survey['assigned_tech_ids'] ?? ''));
                                        foreach ($techs as $t):
                                            $is_selected = in_array($t['id'], $assigned_arr);
                                        ?>
                                            <label style="display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.8rem; border-radius: 8px; cursor: pointer; color: #cbd5e1; font-size: 0.9rem; transition: background 0.15s;" onmouseover="this.style.background='rgba(99,102,241,0.15)'; this.style.color='#f8fafc';" onmouseout="this.style.background='transparent'; this.style.color='#cbd5e1';">
                                                <input type="checkbox" name="tech_ids[]" value="<?php echo $t['id']; ?>" 
                                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                                       onchange="updateTechSelectText()"
                                                       style="accent-color: #6366f1; width: 16px; height: 16px; flex-shrink: 0; cursor: pointer;">
                                                <i class="ph ph-user" style="color: #6366f1; font-size: 1rem;"></i>
                                                <span><?php echo htmlspecialchars($t['username']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn-save-assignment"
                                    style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.8rem; font-weight: 600; font-size: 0.9rem; border-radius: 8px; background: rgba(99, 102, 241, 0.1); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.2); cursor: pointer; transition: all 0.2s ease;">
                                    <i class="ph ph-check" style="font-size: 1rem;"></i> Guardar Asignación
                                </button>
                            </form>
                        </div>

                        <!-- Tiempo Estimado Display -->
                        <div style="background: rgba(0,0,0,0.2); padding: 1.2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.02);">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #cbd5e1; text-transform: uppercase; margin-bottom: 0.6rem; font-weight: 700; letter-spacing: 0.5px;">
                                <i class="ph ph-clock" style="color: #94a3b8; font-size: 0.9rem;"></i> Tiempo Estimado del Levantamiento
                            </label>
                            <div style="font-weight: 600; color: #fbbf24; font-size: 1rem; align-items: center; display: inline-flex; gap: 0.5rem; background: rgba(251,191,36,0.1); padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid rgba(251,191,36,0.2);">
                                <i class="ph ph-timer"></i>
                                <?php echo !empty($survey['estimated_time']) ? htmlspecialchars($survey['estimated_time']) : '<span style="color: rgba(251,191,36,0.5); font-style: italic; font-weight: 400; font-size: 0.9rem;">No especificado</span>'; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Workflow Controls -->
        <div style="display: flex; flex-direction: column;">

            <!-- ESTADO DEL PROYECTO -->
            <div class="action-card"
                style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9)); border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
                <!-- Animated background glow -->
                <div style="position: absolute; top: -50px; left: -50px; width: 100px; height: 100px; background: rgba(99, 102, 241, 0.15); border-radius: 50%; filter: blur(40px); pointer-events: none;"></div>

                <div style="display: flex; align-items: center; gap: 0.85rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05)); border: 1px solid rgba(99, 102, 241, 0.3); color: #818cf8; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 5px rgba(255,255,255,0.1);">
                        <i class="ph ph-kanban" style="font-size: 1.4rem;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: #f8fafc; font-size: 1.1rem; font-weight: 600; letter-spacing: 0.3px;">Estado del Proyecto</h4>
                        <p style="margin: 0; color: #94a3b8; font-size: 0.8rem; margin-top: 0.2rem;">Fase y progreso operativo</p>
                    </div>
                </div>

                <form method="POST" action="update_status.php" id="form-status"
                    style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">

                    <div style="background: rgba(0,0,0,0.2); padding: 1.2rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.02);">
                        <label
                            style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #cbd5e1; text-transform: uppercase; margin-bottom: 0.75rem; font-weight: 600; letter-spacing: 0.5px;">
                            <i class="ph ph-flag" style="color: #94a3b8; font-size: 1rem;"></i> Etapa Actual
                        </label>
                        <select name="status" class="modern-select"
                            style="border-color: rgba(99, 102, 241, 0.4); width: 100%; background-color: rgba(15, 23, 42, 0.8); font-size: 0.95rem; padding: 0.85rem 1rem; border-radius: 8px; color: #f8fafc; cursor: pointer; transition: border-color 0.2s ease;">
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

                    <button type="submit"
                        style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 600; font-size: 1rem; border-radius: 8px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; box-shadow: 0 4px 15px rgba(99,102,241,0.3); cursor: pointer; transition: all 0.2s ease; letter-spacing: 0.3px;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(99,102,241,0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(99,102,241,0.3)';">
                        <i class="ph ph-arrows-clockwise" style="font-size: 1.2rem;"></i> Actualizar Estado Operativo
                    </button>
                </form>
            </div>

            <!-- ESTADO FINANCIERO -->
            <div class="action-card"
                style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9)); border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
                <!-- Animated background glow -->
                <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; filter: blur(40px); pointer-events: none;"></div>
                
                <div style="display: flex; align-items: center; gap: 0.85rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 5px rgba(255,255,255,0.1);">
                        <i class="ph ph-currency-circle-dollar" style="font-size: 1.4rem;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: #f8fafc; font-size: 1.1rem; font-weight: 600; letter-spacing: 0.3px;">Finanzas y Comisión</h4>
                        <p style="margin: 0; color: #94a3b8; font-size: 0.8rem; margin-top: 0.2rem;">Gestión de pagos y liquidación</p>
                    </div>
                </div>

                <!-- Payment Status Display (Read Only) -->
                <div style="background: rgba(15, 23, 42, 0.4); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.03); margin-bottom: 1.2rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; font-weight: 700; letter-spacing: 1px;">
                        <i class="ph ph-shield-check" style="color: #6366f1;"></i> Estado de Pago Actual
                    </label>

                    <?php 
                    $p_status = $survey['payment_status'] ?? 'pendiente';
                    $status_configs = [
                        'pendiente' => ['label' => 'Facturación Pendiente', 'icon' => 'ph-clock-countdown', 'bg' => 'rgba(239, 68, 68, 0.1)', 'color' => '#fca5a5', 'border' => 'rgba(239,68,68,0.2)'],
                        'credito'   => ['label' => 'Facturado a Crédito', 'icon' => 'ph-credit-card', 'bg' => 'rgba(251, 191, 36, 0.1)', 'color' => '#fde68a', 'border' => 'rgba(251,191,36,0.2)'],
                        'pagado'    => ['label' => 'Totalmente Pagado', 'icon' => 'ph-check-circle', 'bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#6ee7b7', 'border' => 'rgba(16,185,129,0.2)'],
                        'contado'   => ['label' => 'Pagado de Contado', 'icon' => 'ph-money', 'bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#6ee7b7', 'border' => 'rgba(16,185,129,0.2)']
                    ];
                    $cfg = $status_configs[$p_status] ?? $status_configs['pendiente'];
                    ?>

                    <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem; background: <?php echo $cfg['bg']; ?>; border: 1px solid <?php echo $cfg['border']; ?>; border-radius: 10px;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; color: <?php echo $cfg['color']; ?>;">
                            <i class="ph <?php echo $cfg['icon']; ?>" style="font-size: 1.2rem;"></i>
                        </div>
                        <span style="font-weight: 700; color: <?php echo $cfg['color']; ?>; font-size: 0.95rem;"><?php echo $cfg['label']; ?></span>
                    </div>

                    <?php if (!empty($survey['invoice_number'])): ?>
                        <div style="margin-top: 1rem; padding: 0.85rem; background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #34d399; font-size: 0.85rem; font-weight: 600;">
                                <i class="ph ph-receipt"></i> Nº Factura: <?php echo htmlspecialchars($survey['invoice_number']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Button: Only way to change status is via Incentives -->
                <a href="../comisiones/index.php" 
                   style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.6rem; padding: 1rem; font-weight: 700; font-size: 0.95rem; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none; box-shadow: 0 4px 15px rgba(99,102,241,0.3); text-decoration: none; transition: all 0.2s ease; cursor: pointer;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(99,102,241,0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(99,102,241,0.3)';"
                >
                    <i class="ph ph-pencil-simple-line" style="font-size: 1.2rem;"></i> Gestionar en Panel de Incentivos
                </a>

                <p style="margin-top: 1rem; font-size: 0.75rem; color: #64748b; text-align: center; line-height: 1.4;">
                    <i class="ph ph-info"></i> El estado de pago se sincroniza automáticamente al liquidar la comisión del técnico.
                </p>
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

    // Tools accordion toggle
    var toolsOpen = false;
    function toggleTools() {
        toolsOpen = !toolsOpen;
        const body = document.getElementById('tools-body');
        const caret = document.getElementById('tools-caret');
        if (toolsOpen) {
            body.style.maxHeight = body.scrollHeight + 400 + 'px';
            caret.style.transform = 'rotate(180deg)';
        } else {
            body.style.maxHeight = '0';
            caret.style.transform = 'rotate(0deg)';
        }
    }

    // Personal accordion toggle
    var personalOpen = false;
    function togglePersonal() {
        personalOpen = !personalOpen;
        const body = document.getElementById('personal-body');
        const caret = document.getElementById('personal-caret');
        if (personalOpen) {
            body.style.maxHeight = body.scrollHeight + 500 + 'px';
            caret.style.transform = 'rotate(180deg)';
        } else {
            body.style.maxHeight = '0';
            caret.style.transform = 'rotate(0deg)';
        }
    }

    // Multi-select Tech Dropdown Logic
    function toggleTechDropdown(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('tech-dropdown');
        const trigger = document.querySelector('.tech-select-trigger');
        const caret = document.getElementById('tech-caret-dropdown');
        const isOpen = dropdown.style.display !== 'none';
        
        if (!isOpen) {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
        
        if (trigger) trigger.classList.toggle('open', !isOpen);
        if (caret) {
            caret.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
            caret.style.transition = 'transform 0.2s';
        }
    }
    
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('tech-dropdown');
        const container = document.querySelector('.tech-multiselect-wrapper');
        if (dropdown && container && !container.contains(e.target)) {
            dropdown.style.display = 'none';
            const trigger = document.querySelector('.tech-select-trigger');
            const caret = document.getElementById('tech-caret-dropdown');
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

<?php require_once '../../includes/footer.php'; ?>