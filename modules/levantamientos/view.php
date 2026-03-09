<?php
// modules/levantamientos/view.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys', $pdo)) {
    die("Acceso denegado.");
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Fetch Survey
$sql = "
    SELECT 
        ps.*, 
        u.username as tech_name
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE ps.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$survey = $stmt->fetch();

if (!$survey) {
    die("Levantamiento no encontrado.");
}

// Security: If tech doesn't have view_all, only allow if they own it
if (!can_access_module('surveys_view_all', $pdo) && $survey['user_id'] != $_SESSION['user_id']) {
    die("Acceso denegado a este levantamiento.");
}

// Check if Comisiones exist for this survey
$stmtC = $pdo->prepare("SELECT id FROM comisiones WHERE reference_id = ? AND tipo = 'PROYECTO' LIMIT 1");
$stmtC->execute([$survey['id']]);
$existingComision = $stmtC->fetchColumn();

// Fetch Materials
$stmtM = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
$stmtM->execute([$id]);
$materials = $stmtM->fetchAll();

// Fetch Audit Logs (Bitácora)
$stmtAudit = $pdo->prepare("
    SELECT a.*, u.username as action_user 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE a.table_name = 'project_surveys' AND a.record_id = ? 
    ORDER BY a.created_at DESC
");
$stmtAudit->execute([$id]);
$audit_logs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Detalles del Levantamiento';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Status styling
$statusMaps = [
    'draft' => ['Borrador', 'gray', 'ph-file-dashed'],
    'submitted' => ['Fidelizado/Enviado', 'blue', 'ph-paper-plane-right'],
    'approved' => ['Aprobado/Inicio', 'indigo', 'ph-check-circle'],
    'in_progress' => ['En Progreso', 'warning', 'ph-spinner'],
    'completed' => ['Completado', 'success', 'ph-check-square-offset']
];
$sData = $statusMaps[$survey['status']] ?? ['Desconocido', 'gray', 'ph-question'];

$paymentMaps = [
    'pendiente' => ['Pendiente', 'gray', 'ph-clock'],
    'pagado' => ['Pagado', 'success', 'ph-money']
];
$pData = $paymentMaps[$survey['payment_status']] ?? ['Desconocido', 'gray', 'ph-question'];
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

    .view-header-title h1 {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
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
        border-radius: 0.875rem;
        padding: 1.1rem 1.35rem;
        margin-bottom: 1.1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .glass-card:hover {
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .glass-card-header {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        margin-bottom: 0.9rem;
        padding-bottom: 0.7rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .glass-card-header i {
        font-size: 1.2rem;
        background: linear-gradient(135deg, var(--primary-400), var(--primary-600));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .glass-card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #f1f5f9;
        letter-spacing: 0.5px;
    }

    .meta-group {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .meta-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .meta-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary-400);
        border: 1px solid rgba(99, 102, 241, 0.2);
        flex-shrink: 0;
    }

    .meta-content .meta-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
        display: block;
    }

    .meta-content .meta-value {
        font-size: 1.05rem;
        font-weight: 500;
        color: #fff;
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
        font-size: 0.75rem;
        letter-spacing: 1px;
        padding: 0.65rem 0.85rem;
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
        padding: 0.6rem 0.85rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        color: #e2e8f0;
        font-size: 0.9rem;
    }

    .modern-table tr:last-child td {
        border-bottom: none;
    }

    .modern-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .badge-outline {
        border: 1px solid currentColor;
        background: transparent;
    }

    /* Custom Select in Action Cards */
    .modern-select {
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        flex: 1;
        min-height: 42px;
    }

    .modern-select:focus {
        border-color: var(--primary-500);
        outline: none;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
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
    }

    /* Prevent Word content from overflowing the card */
    .rich-text-content {
        overflow: hidden;
        max-width: 100%;
    }

    .rich-text-content * {
        max-width: 100%;
    }


    .card-project-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.85rem;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--primary-300);
        margin-left: auto;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

<div class="animate-enter" style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">

    <!-- TOP HEADER HERO -->
    <div class="view-header-glass" style="padding: 1.25rem 1.75rem;">
        <!-- Left: back + title + date -->
        <div style="display: flex; align-items: center; gap: 1.25rem; min-width: 0;">
            <a href="index.php" class="btn btn-secondary"
                style="border-radius: 50%; width: 38px; height: 38px; padding: 0; display: flex; justify-content: center; align-items: center; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); flex-shrink: 0;">
                <i class="ph ph-arrow-left" style="font-size: 1.1rem;"></i>
            </a>
            <div style="min-width: 0;">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <h1 style="font-size: 1.4rem; font-weight: 700; margin: 0; color: #fff; white-space: nowrap;">
                        Levantamiento <span
                            class="id-text">#<?php echo str_pad($survey['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </h1>
                    <span class="status-badge status-<?php echo $sData[1]; ?>" style="font-size: 0.72rem;">
                        <i class="ph <?php echo $sData[2]; ?>"></i> <?php echo strtoupper($sData[0]); ?>
                    </span>
                    <span class="status-badge status-<?php echo $pData[1]; ?>" style="font-size: 0.72rem;">
                        <i class="ph <?php echo $pData[2]; ?>"></i> <?php echo strtoupper($pData[0]); ?>
                    </span>
                </div>
                <p class="text-muted"
                    style="margin: 0.3rem 0 0 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="ph ph-calendar-blank"></i>
                    Registrado el <?php echo date('d/m/Y \a \l\a\s h:i A', strtotime($survey['created_at'])); ?>
                </p>
            </div>
        </div>

        <!-- Right: action buttons -->
        <div class="view-actions" style="gap: 0.6rem; flex-shrink: 0;">
            <?php if (can_access_module('comisiones', $pdo) && $survey['payment_status'] === 'pagado'): ?>
                <a href="../comisiones/index.php?search=<?php echo urlencode($survey['title']); ?>"
                    class="btn btn-secondary"
                    style="border-color: var(--success); color: var(--success); white-space: nowrap; background: rgba(16,185,129,0.1); font-size: 0.875rem;">
                    <i class="ph ph-check-circle"></i> Comisión
                </a>
            <?php endif; ?>

            <?php if (can_access_module('surveys_edit', $pdo) && in_array($survey['status'], ['draft', 'submitted']) && $survey['payment_status'] !== 'pagado'): ?>
                <a href="edit.php?id=<?php echo $survey['id']; ?>" class="btn btn-secondary"
                    style="white-space: nowrap; font-size: 0.875rem;">
                    <i class="ph ph-pencil-simple"></i> Editar
                </a>
            <?php endif; ?>

            <a href="print.php?id=<?php echo $survey['id']; ?>" target="_blank" class="btn btn-secondary"
                style="white-space: nowrap; font-size: 0.875rem;">
                <i class="ph ph-printer"></i> Imprimir
            </a>

            <?php if (can_access_module('anexos', $pdo)): ?>
                <a href="../anexos/create.php?survey_id=<?php echo $survey['id']; ?>" class="btn btn-primary"
                    style="white-space: nowrap; font-size: 0.875rem; background: linear-gradient(135deg, var(--primary-500), var(--primary-700)); border: none; box-shadow: 0 4px 12px rgba(99,102,241,0.35);">
                    <i class="ph ph-file-plus"></i> Crear Anexo
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- STATS STRIP -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">

        <div class="glass-card"
            style="margin-bottom: 0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 40px; height: 40px; border-radius: 10px; background: rgba(56,189,248,0.1); border: 1px solid rgba(56,189,248,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph ph-buildings" style="font-size: 1.3rem; color: #38bdf8;"></i>
            </div>
            <div style="min-width: 0;">
                <div
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 0.2rem;">
                    Cliente</div>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                    title="<?php echo htmlspecialchars($survey['client_name']); ?>">
                    <?php echo htmlspecialchars($survey['client_name']); ?>
                </div>
            </div>
        </div>

        <div class="glass-card"
            style="margin-bottom: 0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 40px; height: 40px; border-radius: 10px; background: rgba(167,139,250,0.1); border: 1px solid rgba(167,139,250,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph ph-hard-hat" style="font-size: 1.3rem; color: #a78bfa;"></i>
            </div>
            <div style="min-width: 0;">
                <div
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 0.2rem;">
                    Técnico</div>
                <div
                    style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo htmlspecialchars($survey['tech_name']); ?>
                </div>
            </div>
        </div>

        <div class="glass-card"
            style="margin-bottom: 0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 40px; height: 40px; border-radius: 10px; background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph ph-users-three" style="font-size: 1.3rem; color: #f43f5e;"></i>
            </div>
            <div style="min-width: 0;">
                <div
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 0.2rem;">
                    Personal</div>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
                    <?php echo htmlspecialchars($survey['personnel_required'] ?: 'No especificado'); ?>
                </div>
            </div>
        </div>

        <div class="glass-card"
            style="margin-bottom: 0; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem;">
            <div
                style="width: 40px; height: 40px; border-radius: 10px; background: rgba(250,204,21,0.1); border: 1px solid rgba(250,204,21,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="ph ph-hourglass-high" style="font-size: 1.3rem; color: #facc15;"></i>
            </div>
            <div style="min-width: 0;">
                <div
                    style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 0.2rem;">
                    Tiempo Est.</div>
                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
                    <?php echo htmlspecialchars($survey['estimated_time'] ?: 'N/E'); ?>
                </div>
            </div>
        </div>

    </div>

    <!-- MAIN 2-COLUMN CONTENT GRID -->
    <div
        style="display: grid; grid-template-columns: 1fr <?php echo count($audit_logs) > 0 ? '340px' : '0'; ?>; gap: 2rem; align-items: start;">

        <!-- LEFT: All Content Sections -->
        <div style="display: flex; flex-direction: column; min-width: 0;">

            <!-- Description -->
            <?php if ($survey['general_description']): ?>
                <div class="glass-card">
                    <div class="glass-card-header">
                        <i class="ph ph-info"></i>
                        <h3 class="glass-card-title">Descripción General</h3>
                        <span class="card-project-title">
                            <i class="ph ph-folder-open"
                                style="-webkit-text-fill-color: initial; background: none; font-size: 0.85rem;"></i>
                            <?php echo htmlspecialchars($survey['title']); ?>
                        </span>
                    </div>
                    <div
                        style="border-left: 3px solid rgba(99,102,241,0.35); padding-left: 1rem; color: #cbd5e1; line-height: 1.8; font-size: 0.95rem;">
                        <?php echo nl2br(htmlspecialchars($survey['general_description'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2-col grid: Alcances + Materiales side by side -->
            <div style="display: grid; grid-template-columns: 7fr 3fr; gap: 1rem; align-items: start;">

                <!-- Scope Box -->
                <?php if ($survey['scope_activities']): ?>
                    <div class="glass-card" style="margin-bottom: 0;">
                        <div class="glass-card-header">
                            <i class="ph ph-target"></i>
                            <h3 class="glass-card-title">Alcances</h3>
                        </div>
                        <div class="rich-text-content">
                            <?php echo $survey['scope_activities']; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="glass-card" style="margin-bottom: 0;">
                        <div class="glass-card-header">
                            <i class="ph ph-target"></i>
                            <h3 class="glass-card-title">Alcances</h3>
                        </div>
                        <p class="text-muted italic" style="margin:0; font-size:0.9rem;">Sin alcances definidos.</p>
                    </div>
                <?php endif; ?>

                <!-- Materials Box -->
                <div class="glass-card" style="margin-bottom: 0;">
                    <div class="glass-card-header">
                        <i class="ph ph-cube"
                            style="background: linear-gradient(135deg, var(--success), #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                        <h3 class="glass-card-title">Materiales</h3>
                        <span
                            style="margin-left: auto; background: rgba(52,211,153,0.1); color: #34d399; border: 1px solid rgba(52,211,153,0.2); border-radius: 20px; padding: 0.2rem 0.6rem; font-size: 0.78rem; font-weight: 600;">
                            <?php echo count($materials); ?> items
                        </span>
                    </div>

                    <!-- Compact Material List (Option A) -->
                    <?php if (count($materials) > 0): ?>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <?php foreach ($materials as $index => $m): ?>
                                <div
                                    style="display: flex; align-items: flex-start; justify-content: space-between; padding: 0.5rem 0.35rem; border-bottom: 1px solid rgba(255,255,255,0.04); gap: 0.75rem; <?php echo $index === count($materials) - 1 ? 'border-bottom: none;' : ''; ?>">
                                    <div style="display: flex; align-items: flex-start; gap: 0.6rem; min-width: 0; flex: 1;">
                                        <span
                                            style="min-width: 20px; height: 20px; border-radius: 50%; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.2); color: var(--primary-400); font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <div style="min-width: 0;">
                                            <div
                                                style="font-weight: 500; color: #f1f5f9; font-size: 0.875rem; line-height: 1.3;">
                                                <?php echo htmlspecialchars($m['item_description']); ?>
                                            </div>
                                            <div class="inline-note-cell" data-material-id="<?php echo $m['id']; ?>"
                                                data-notes="<?php echo htmlspecialchars($m['notes'] ?? ''); ?>"
                                                title="Clic para agregar nota"
                                                style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.15rem; border-radius: 4px; border: 1px solid transparent; padding: 0.1rem 0.25rem; transition: all 0.15s; max-width: 100%;">
                                                <?php if ($m['notes']): ?>
                                                    <i class="ph ph-note"
                                                        style="color: #64748b; font-size: 0.72rem; flex-shrink: 0;"></i>
                                                    <span class="note-display"
                                                        style="color: #94a3b8; font-size: 0.78rem;"><?php echo htmlspecialchars($m['notes']); ?></span>
                                                <?php else: ?>
                                                    <span class="note-display"
                                                        style="color: #3f4f63; font-size: 0.76rem; font-style: italic;">+
                                                        nota</span>
                                                <?php endif; ?>
                                                <i class="ph ph-pencil-simple"
                                                    style="color: #3f4f63; font-size: 0.68rem; opacity: 0; transition: opacity 0.15s;"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <span
                                        style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2); border-radius: 999px; padding: 0.12rem 0.55rem; font-size: 0.75rem; font-weight: 600; white-space: nowrap; flex-shrink: 0; margin-top: 2px;">
                                        <?php echo floatval($m['quantity']) . ' ' . htmlspecialchars($m['unit']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted italic"
                            style="margin:0; font-size:0.9rem; text-align:center; padding: 1rem 0;">Sin materiales
                            registrados.</p>
                    <?php endif; ?>

                    <style>
                        .inline-note-cell:hover {
                            background: rgba(255, 255, 255, 0.04) !important;
                            border-color: rgba(255, 255, 255, 0.08) !important;
                        }

                        .inline-note-cell:hover .ph-pencil-simple {
                            opacity: 1 !important;
                        }

                        .inline-note-input {
                            background: rgba(0, 0, 0, 0.3);
                            border: 1px solid var(--primary-500);
                            border-radius: 4px;
                            color: #fff;
                            padding: 0.2rem 0.4rem;
                            font-size: 0.8rem;
                            width: 200px;
                            max-width: 100%;
                            outline: none;
                            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
                        }
                    </style>
                    <script>
                        document.querySelectorAll('.inline-note-cell').forEach(function (cell) {
                            cell.addEventListener('click', function () {
                                if (cell.querySelector('.inline-note-input')) return;
                                const matId = cell.dataset.materialId;
                                const currentNote = cell.dataset.notes;
                                const input = document.createElement('input');
                                input.type = 'text'; input.className = 'inline-note-input';
                                input.value = currentNote; input.placeholder = 'Agregar nota...';
                                cell.innerHTML = ''; cell.appendChild(input); input.focus(); input.select();
                                function saveNote() {
                                    const newNote = input.value.trim();
                                    fetch('save_material_note.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'material_id=' + matId + '&notes=' + encodeURIComponent(newNote) })
                                        .then(r => r.json()).then(function (data) {
                                            cell.dataset.notes = newNote;
                                            const esc = s => s.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                            cell.innerHTML = newNote
                                                ? `<i class="ph ph-note" style="color:#64748b;font-size:0.72rem;flex-shrink:0;"></i><span class="note-display" style="color:#94a3b8;font-size:0.78rem;">${esc(newNote)}</span><i class="ph ph-pencil-simple" style="color:#3f4f63;font-size:0.68rem;opacity:0;transition:opacity 0.15s;"></i>`
                                                : `<span class="note-display" style="color:#3f4f63;font-size:0.76rem;font-style:italic;">+ nota</span><i class="ph ph-pencil-simple" style="color:#3f4f63;font-size:0.68rem;opacity:0;transition:opacity 0.15s;"></i>`;
                                            if (data.success) { cell.style.borderColor = 'rgba(52,211,153,0.4)'; setTimeout(() => { cell.style.borderColor = 'transparent'; }, 900); }
                                        });
                                }
                                input.addEventListener('blur', saveNote);
                                input.addEventListener('keydown', function (e) {
                                    if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
                                    if (e.key === 'Escape') {
                                        cell.innerHTML = currentNote
                                            ? `<i class="ph ph-note" style="color:#64748b;font-size:0.72rem;flex-shrink:0;"></i><span class="note-display" style="color:#94a3b8;font-size:0.78rem;">${currentNote}</span><i class="ph ph-pencil-simple" style="color:#3f4f63;font-size:0.68rem;opacity:0;transition:opacity 0.15s;"></i>`
                                            : `<span class="note-display" style="color:#3f4f63;font-size:0.76rem;font-style:italic;">+ nota</span><i class="ph ph-pencil-simple" style="color:#3f4f63;font-size:0.68rem;opacity:0;transition:opacity 0.15s;"></i>`;
                                    }
                                });
                            });
                        });
                    </script>
                </div>

            </div><!-- end 2-col grid -->

        </div><!-- end left column -->
        <!-- RIGHT: Audit Log Timeline -->
        <?php if (count($audit_logs) > 0): ?>
            <div style="display: flex; flex-direction: column; position: sticky; top: 1rem; align-self: start;">
                <div class="glass-card" style="margin-bottom: 0;">
                    <div class="glass-card-header">
                        <i class="ph ph-clock-clockwise"></i>
                        <h3 class="glass-card-title">Bitácora</h3>
                        <span
                            style="margin-left: auto; background: rgba(99,102,241,0.1); color: var(--primary-400); border: 1px solid rgba(99,102,241,0.2); border-radius: 20px; padding: 0.2rem 0.6rem; font-size: 0.8rem;">
                            <?php echo count($audit_logs); ?>
                        </span>
                    </div>

                    <div
                        style="position: relative; border-left: 2px solid rgba(99,102,241,0.2); padding-left: 1.25rem; margin-left: 0.5rem; max-height: 600px; overflow-y: auto;">
                        <?php foreach ($audit_logs as $index => $log): ?>
                            <div
                                style="position: relative; margin-bottom: <?php echo ($index === count($audit_logs) - 1) ? '0' : '1.5rem'; ?>;">
                                <!-- Timeline Dot -->
                                <div
                                    style="position: absolute; left: -1.38rem; width: 10px; height: 10px; background: var(--primary-500); border-radius: 50%; top: 5px; box-shadow: 0 0 0 3px rgba(99,102,241,0.2);">
                                </div>

                                <div style="font-size: 0.82rem; color: #64748b; margin-bottom: 0.3rem;">
                                    <i class="ph ph-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </div>

                                <div
                                    style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem; margin-bottom: 0.2rem;">
                                    <?php
                                    if ($log['action'] === 'EDICION')
                                        echo '<i class="ph ph-pencil-simple" style="color: var(--warning);"></i> Editado';
                                    elseif ($log['action'] === 'UPDATE STATUS')
                                        echo '<i class="ph ph-arrows-clockwise" style="color: var(--info);"></i> Cambio Estado';
                                    else
                                        echo htmlspecialchars($log['action']);
                                    ?>
                                </div>
                                <div style="font-size: 0.82rem; color: #94a3b8;">
                                    por <span
                                        style="color: var(--primary-300);"><?php echo htmlspecialchars($log['action_user'] ?: 'Sistema'); ?></span>
                                </div>

                                <?php if ($log['action'] === 'EDICION' && !empty($log['new_value'])): ?>
                                    <?php
                                    $old_val = json_decode($log['old_value'], true);
                                    $new_val = json_decode($log['new_value'], true);
                                    $changes = [];
                                    $fields_to_check = [
                                        'title' => 'Título',
                                        'client_name' => 'Cliente',
                                        'general_description' => 'Descripción',
                                        'estimated_time' => 'Tiempo',
                                        'personnel_required' => 'Personal',
                                        'scope_activities' => 'Alcances'
                                    ];
                                    foreach ($fields_to_check as $key => $label) {
                                        if (isset($old_val[$key]) && isset($new_val[$key]) && $old_val[$key] !== $new_val[$key]) {
                                            $changes[] = $label;
                                        }
                                    }
                                    if (isset($old_val['materials']) && isset($new_val['materials'])) {
                                        if (json_encode($old_val['materials']) !== json_encode($new_val['materials'])) {
                                            $changes[] = 'Materiales';
                                        }
                                    }
                                    ?>
                                    <?php if (!empty($changes)): ?>
                                        <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                            <?php foreach (array_unique($changes) as $change): ?>
                                                <span
                                                    style="background: rgba(255,255,255,0.05); padding: 0.15rem 0.5rem; border-radius: 4px; color: #e2e8f0; border: 1px solid rgba(255,255,255,0.1); font-size: 0.75rem;"><?php echo $change; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($log['action'] === 'UPDATE STATUS'): ?>
                                    <div
                                        style="margin-top: 0.4rem; font-size: 0.82rem; color: #cbd5e1; padding: 0.35rem 0.6rem; border-radius: 5px; background: rgba(0,0,0,0.2); border-left: 2px solid var(--info);">
                                        → <strong style="color: #fff;"><?php echo htmlspecialchars($log['new_value']); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- end main grid -->

</div><!-- end main container -->

<?php require_once '../../includes/footer.php'; ?>