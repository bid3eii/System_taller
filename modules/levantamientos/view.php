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
    'approved' => ['Aprobado', 'green', 'ph-check-circle']
];
$sData = $statusMaps[$survey['status']] ?? ['Desconocido', 'gray', 'ph-question'];
?>

<div class="animate-enter" style="max-width: 1000px; margin: 0 auto;">

    <!-- HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem;"><i class="ph ph-arrow-left"></i></a>
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                    Levantamiento #
                    <?php echo str_pad($survey['id'], 5, '0', STR_PAD_LEFT); ?>
                    <span class="status-badge status-<?php echo $sData[1]; ?>"
                        style="font-size: 0.8rem; vertical-align: middle;">
                        <i class="ph <?php echo $sData[2]; ?>"></i>
                        <?php echo strtoupper($sData[0]); ?>
                    </span>
                </h1>
                <p class="text-muted" style="margin: 0.25rem 0 0 0;">Creado el
                    <?php echo date('d/m/Y h:i A', strtotime($survey['created_at'])); ?>
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <?php if (can_access_module('surveys_edit', $pdo) && $survey['status'] !== 'approved'): ?>
                <a href="edit.php?id=<?php echo $survey['id']; ?>" class="btn btn-secondary">
                    <i class="ph ph-pencil-simple"></i> Editar
                </a>
            <?php endif; ?>
            <a href="print.php?id=<?php echo $survey['id']; ?>" target="_blank" class="btn btn-secondary">
                <i class="ph ph-printer"></i> Imprimir Normal
            </a>
            <?php if (can_access_module('anexos', $pdo)): ?>
                <a href="../anexos/create.php?survey_id=<?php echo $survey['id']; ?>" class="btn btn-primary"
                    style="background: var(--primary-600);">
                    <i class="ph ph-file-plus"></i> Crear Anexo Yazaki
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- LEFT COLUMN: Main Info -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">

            <!-- General Info -->
            <div class="card">
                <h3
                    style="margin-top: 0; margin-bottom: 1.5rem; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    Descripción General del Proyecto
                </h3>

                <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 1rem; color: var(--primary-500);">
                    <?php echo htmlspecialchars($survey['title']); ?>
                </h2>

                <?php if ($survey['general_description']): ?>
                    <p style="color: var(--text-secondary); line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($survey['general_description'])); ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted italic">Sin descripción general proporcionada.</p>
                <?php endif; ?>
            </div>

            <!-- Scope -->
            <div class="card">
                <div
                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="ph ph-target" style="font-size: 1.25rem; color: var(--primary-500);"></i>
                    <h3 style="margin: 0; color: var(--text-primary);">Alcances del Proyecto (Implementación)</h3>
                </div>

                <div class="rich-text-content" style="color: var(--text-main); line-height: 1.6;">
                    <?php
                    if ($survey['scope_activities']) {
                        // Support for HTML from TinyMCE
                        echo $survey['scope_activities'];
                    } else {
                        echo '<p class="text-muted italic">No hay actividades definidas.</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Materials -->
            <div class="card">
                <div
                    style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i class="ph ph-list-dashes" style="font-size: 1.25rem; color: var(--success);"></i>
                    <h3 style="margin: 0; color: var(--text-primary);">Requerimientos y Materiales</h3>
                </div>

                <?php if (count($materials) > 0): ?>
                    <div class="table-container">
                        <table style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th style="padding: 0.75rem;">Descripción</th>
                                    <th style="padding: 0.75rem; text-align: center;">Cantidad</th>
                                    <th style="padding: 0.75rem;">Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $m): ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 0.75rem;">
                                            <span style="font-weight: 500;">
                                                <?php echo htmlspecialchars($m['item_description']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem; text-align: center;">
                                            <span class="badge"
                                                style="background: rgba(99, 102, 241, 0.1); color: var(--primary-500); font-size: 0.9rem;">
                                                <?php echo floatval($m['quantity']) . ' ' . htmlspecialchars($m['unit']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem; color: var(--text-muted); font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($m['notes'] ?: '-'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted italic">No hay materiales registrados para este proyecto.</p>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT COLUMN: Metadata -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">

            <!-- Details -->
            <div class="card">
                <h4
                    style="margin-top: 0; margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Detalles del Cliente</h4>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div
                            style="width: 40px; height: 40px; border-radius: 8px; background: rgba(99, 102, 241, 0.1); color: var(--primary-500); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem;">
                            <?php echo strtoupper(substr($survey['client_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);">
                                <?php echo htmlspecialchars($survey['client_name']); ?>
                            </div>
                            <div class="text-sm text-muted">Cliente</div>
                        </div>
                    </div>
                    <!-- Legacy code removed -->
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

                <h4
                    style="margin-top: 0; margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Responsable Técnico</h4>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="ph ph-user-circle" style="font-size: 2rem; color: var(--text-muted);"></i>
                    <div>
                        <div style="font-weight: 500; color: var(--text-primary);">
                            <?php echo htmlspecialchars($survey['tech_name']); ?>
                        </div>
                        <div class="text-sm text-muted">Elaborado por</div>
                    </div>
                </div>
            </div>

            <!-- Time & Resources -->
            <div class="card">
                <h4
                    style="margin-top: 0; margin-bottom: 1.25rem; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    Estimación</h4>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <div class="text-xs text-muted" style="margin-bottom: 0.25rem;">Personal Requerido</div>
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); font-weight: 500;">
                            <i class="ph ph-users" style="color: var(--primary-500);"></i>
                            <?php echo htmlspecialchars($survey['personnel_required'] ?: 'No especificado'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-muted" style="margin-bottom: 0.25rem;">Tiempo Estimado</div>
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); font-weight: 500;">
                            <i class="ph ph-clock" style="color: var(--warning);"></i>
                            <?php echo htmlspecialchars($survey['estimated_time'] ?: 'No especificado'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Actions -->
            <?php if (can_access_module('surveys_status', $pdo)): ?>
                <div class="card" style="background: rgba(99, 102, 241, 0.03); border: 1px solid rgba(99, 102, 241, 0.2);">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--text-primary); font-size: 0.95rem;">Acciones
                        de Estado</h4>

                    <form method="POST" action="update_status.php" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="hidden" name="id" value="<?php echo $survey['id']; ?>">
                        <select name="status" class="form-control" style="flex: 1; padding: 0.4rem; height: 38px;">
                            <option value="draft" <?php echo $survey['status'] == 'draft' ? 'selected' : ''; ?>>Borrador
                            </option>
                            <option value="submitted" <?php echo $survey['status'] == 'submitted' ? 'selected' : ''; ?>>
                                Fidelizado/Enviado</option>
                            <option value="approved" <?php echo $survey['status'] == 'approved' ? 'selected' : ''; ?>>Aprobado
                            </option>
                        </select>
                        <button type="submit" class="btn btn-primary"
                            style="padding: 0.4rem 1rem; height: 38px;">Guardar</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<style>
    /* Reset some default formatting for TinyMCE output */
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
</style>

<?php
require_once '../../includes/footer.php';
?>