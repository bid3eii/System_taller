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
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        color: #e2e8f0;
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
        font-size: 0.95rem;
        line-height: 1.7;
    }

    .rich-text-content ul {
        padding-left: 1.5rem;
        margin-bottom: 1rem;
        list-style-type: disc !important;
    }

    .rich-text-content ol {
        padding-left: 1.5rem;
        margin-bottom: 1rem;
        list-style-type: decimal !important;
    }

    .rich-text-content li {
        margin-bottom: 0.5rem;
    }

    .rich-text-content p {
        margin-bottom: 1rem;
    }

    .rich-text-content strong {
        color: #fff;
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
                    <span>Levantamiento</span> <span
                        class="id-text">#<?php echo str_pad($survey['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span class="status-badge status-<?php echo $sData[1]; ?>"
                        style="font-size: 0.75rem; vertical-align: middle; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
                        <i class="ph <?php echo $sData[2]; ?>"></i>
                        <?php echo strtoupper($sData[0]); ?>
                    </span>
                    <span class="status-badge status-<?php echo $pData[1]; ?>"
                        style="font-size: 0.75rem; vertical-align: middle; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
                        <i class="ph <?php echo $pData[2]; ?>"></i>
                        <?php echo strtoupper($pData[0]); ?>
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
            <?php if (can_access_module('comisiones', $pdo)): ?>
                <?php if ($survey['payment_status'] === 'pagado'): ?>
                    <a href="../comisiones/index.php?search=<?php echo urlencode($survey['title']); ?>"
                        class="btn btn-secondary"
                        style="border-color: var(--success); color: var(--success); white-space: nowrap; background: rgba(16, 185, 129, 0.1);"
                        title="Comisión Automática Creada">
                        <i class="ph ph-check-circle"></i> Comisión Creada
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (can_access_module('surveys_edit', $pdo) && $survey['status'] !== 'approved'): ?>
                <a href="edit.php?id=<?php echo $survey['id']; ?>" class="btn btn-secondary"
                    style="white-space: nowrap; backdrop-filter: blur(5px);">
                    <i class="ph ph-pencil-simple"></i> Editar
                </a>
            <?php endif; ?>

            <a href="print.php?id=<?php echo $survey['id']; ?>" target="_blank" class="btn btn-secondary"
                style="white-space: nowrap; backdrop-filter: blur(5px);">
                <i class="ph ph-printer"></i> Imprimir
            </a>

            <?php if (can_access_module('anexos', $pdo)): ?>
                <a href="../anexos/create.php?survey_id=<?php echo $survey['id']; ?>" class="btn btn-primary"
                    style="white-space: nowrap; background: linear-gradient(135deg, var(--primary-500), var(--primary-700)); border: none; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);">
                    <i class="ph ph-file-plus"></i> Crear Anexo Yazaki
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN GRID LAYOUT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- LEFT COLUMN: Content Heavy -->
        <div style="display: flex; flex-direction: column;">

            <!-- Overview Box -->
            <div class="glass-card">
                <div class="glass-card-header">
                    <i class="ph ph-info"></i>
                    <h3 class="glass-card-title">Descripción General</h3>
                </div>

                <h2
                    style="font-size: 1.5rem; margin-top: 0; margin-bottom: 1.25rem; color: #fff; letter-spacing: -0.5px;">
                    <?php echo htmlspecialchars($survey['title']); ?>
                </h2>

                <?php if ($survey['general_description']): ?>
                    <div style="color: #cbd5e1; line-height: 1.8; font-size: 0.95rem;">
                        <?php echo nl2br(htmlspecialchars($survey['general_description'])); ?>
                    </div>
                <?php else: ?>
                    <div
                        style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1); text-align: center; color: var(--text-muted); font-style: italic;">
                        El proyecto no cuenta con una descripción detallada provista por el técnico.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Scope Box -->
            <div class="glass-card">
                <div class="glass-card-header">
                    <i class="ph ph-target"></i>
                    <h3 class="glass-card-title">Alcances y Plan de Trabajo</h3>
                </div>

                <div class="rich-text-content">
                    <?php
                    if ($survey['scope_activities']) {
                        echo $survey['scope_activities'];
                    } else {
                        echo '<div style="padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1); text-align: center; color: var(--text-muted); font-style: italic;">No hay listado de actividades definidas en los alcances.</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Materials Box -->
            <div class="glass-card">
                <div class="glass-card-header">
                    <i class="ph ph-cube"
                        style="background: linear-gradient(135deg, var(--success), #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    <h3 class="glass-card-title">Requerimientos y Materiales</h3>
                </div>

                <?php if (count($materials) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Descripción del Artículo</th>
                                    <th style="text-align: center; width: 120px;">Cantidad</th>
                                    <th>Notas / Especificaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $m): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500; color: #f8fafc;">
                                                <?php echo htmlspecialchars($m['item_description']); ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="status-badge"
                                                style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2);">
                                                <?php echo floatval($m['quantity']) . ' ' . htmlspecialchars($m['unit']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color: #94a3b8; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($m['notes'] ?: '—'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div
                        style="padding: 2rem; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1); text-align: center;">
                        <i class="ph ph-package"
                            style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;"></i>
                        <p class="text-muted italic" style="margin: 0;">No requiere materiales técnicos o no fueron
                            agregados a este levantamiento.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT COLUMN: Meta & Controls -->
        <div style="display: flex; flex-direction: column;">

            <!-- Workflow Actions -->
            <?php if (can_access_module('surveys_status', $pdo)): ?>

                <h4
                    style="margin: 0 0 1rem 0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); padding-left: 0.5rem;">
                    Gestión y Seguimiento
                </h4>

                <div class="action-card" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div
                            style="width: 32px; height: 32px; border-radius: 8px; background: rgba(99, 102, 241, 0.2); color: var(--primary-400); display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-kanban"></i>
                        </div>
                        <h4 style="margin: 0; color: #fff; font-size: 1rem;">Estado del Proyecto</h4>
                    </div>

                    <form method="POST" action="update_status.php"
                        style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">
                        <select name="status" class="modern-select">
                            <option value="draft" <?php echo $survey['status'] == 'draft' ? 'selected' : ''; ?>>Borrador
                            </option>
                            <option value="submitted" <?php echo $survey['status'] == 'submitted' ? 'selected' : ''; ?>>
                                Fidelizado/Enviado</option>
                            <option value="approved" <?php echo $survey['status'] == 'approved' ? 'selected' : ''; ?>>
                                Aprobado/Inicio</option>
                            <option value="in_progress" <?php echo $survey['status'] == 'in_progress' ? 'selected' : ''; ?>>En
                                Progreso</option>
                            <option value="completed" <?php echo $survey['status'] == 'completed' ? 'selected' : ''; ?>>
                                Completado</option>
                        </select>
                        <button type="submit" class="btn btn-primary"
                            style="width: 100%; justify-content: center; background: rgba(99, 102, 241, 0.8); backdrop-filter: blur(4px);">
                            Actualizar Proyecto
                        </button>
                    </form>
                </div>

                <div class="action-card"
                    style="background: linear-gradient(145deg, rgba(16, 185, 129, 0.1), rgba(15, 23, 42, 0.8)); border-color: rgba(16, 185, 129, 0.2); margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div
                            style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center;">
                            <i class="ph ph-currency-circle-dollar"></i>
                        </div>
                        <h4 style="margin: 0; color: #f8fafc; font-size: 1rem;">Finanzas y Comisión</h4>
                    </div>

                    <form method="POST" action="update_payment_status.php"
                        style="display: flex; flex-direction: column; gap: 0.75rem;"
                        onsubmit="return confirm('¿Confirmas el cambio de estado de pago?\n\nMarcar como PAGADO cerrará el ciclo financiero de este Levantamiento/Proyecto y generará automáticamente la comisión para el técnico asignado.');">
                        <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">
                        <select name="payment_status" class="modern-select" <?php echo $survey['payment_status'] === 'pagado' ? 'disabled' : ''; ?>
                            style="<?php echo $survey['payment_status'] === 'pagado' ? 'opacity: 0.6;' : ''; ?>">
                            <option value="pendiente" <?php echo $survey['payment_status'] === 'pendiente' ? 'selected' : ''; ?>>Facturación Pendiente</option>
                            <option value="pagado" <?php echo $survey['payment_status'] === 'pagado' ? 'selected' : ''; ?>>
                                Cancelado / Pagado</option>
                        </select>
                        <button type="submit" class="btn"
                            style="width: 100%; justify-content: center; background: #10b981; color: #fff; border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);"
                            <?php echo $survey['payment_status'] === 'pagado' ? 'disabled' : ''; ?>>
                            <?php echo $survey['payment_status'] === 'pagado' ? 'Liquidado' : 'Procesar Pago'; ?>
                        </button>
                    </form>
                    <?php if ($survey['payment_status'] === 'pagado'): ?>
                        <div
                            style="margin-top: 1rem; padding: 0.75rem; background: rgba(0,0,0,0.2); border-radius: 6px; font-size: 0.8rem; color: #94a3b8; display: flex; gap: 0.5rem; border-left: 2px solid #10b981;">
                            <i class="ph ph-info" style="color: #34d399; font-size: 1.1rem; flex-shrink: 0;"></i>
                            <div>Este proyecto ha sido pagado y su ciclo financiero está cerrado administrativamente. Revise el
                                módulo de Módulo Comisiones.</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h4
                style="margin: 0 0 1rem 0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); padding-left: 0.5rem;">
                Expediente Operativo
            </h4>

            <div class="glass-card" style="padding: 1.25rem;">
                <div class="meta-group">

                    <!-- Client -->
                    <div class="meta-item">
                        <div class="meta-icon-box"
                            style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border-color: rgba(56, 189, 248, 0.2);">
                            <i class="ph ph-buildings"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Titular / Constructora</span>
                            <span class="meta-value"><?php echo htmlspecialchars($survey['client_name']); ?></span>
                        </div>
                    </div>

                    <!-- Tech -->
                    <div class="meta-item">
                        <div class="meta-icon-box"
                            style="background: rgba(167, 139, 250, 0.1); color: #a78bfa; border-color: rgba(167, 139, 250, 0.2);">
                            <i class="ph ph-hard-hat"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Responsable Técnico</span>
                            <span class="meta-value"><?php echo htmlspecialchars($survey['tech_name']); ?></span>
                        </div>
                    </div>

                    <div style="height: 1px; background: rgba(255,255,255,0.05); margin: 0.5rem 0;"></div>

                    <!-- Resources -->
                    <div class="meta-item">
                        <div class="meta-icon-box"
                            style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border-color: rgba(244, 63, 94, 0.2);">
                            <i class="ph ph-users-three"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Fuerza Laboral Asignada</span>
                            <span
                                class="meta-value"><?php echo htmlspecialchars($survey['personnel_required'] ?: 'N/E'); ?></span>
                        </div>
                    </div>

                    <!-- Time -->
                    <div class="meta-item">
                        <div class="meta-icon-box"
                            style="background: rgba(250, 204, 21, 0.1); color: #facc15; border-color: rgba(250, 204, 21, 0.2);">
                            <i class="ph ph-hourglass-high"></i>
                        </div>
                        <div class="meta-content">
                            <span class="meta-label">Proyección de Tiempo</span>
                            <span
                                class="meta-value"><?php echo htmlspecialchars($survey['estimated_time'] ?: 'N/E'); ?></span>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>