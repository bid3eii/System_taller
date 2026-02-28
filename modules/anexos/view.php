<?php
// modules/anexos/view.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('anexos', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no especificado.");
}

// Fetch Anexo
$stmt = $pdo->prepare("
    SELECT a.*, u.username as creator_name, ps.title as survey_title 
    FROM anexos_yazaki a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN project_surveys ps ON a.survey_id = ps.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$anexo = $stmt->fetch();

if (!$anexo) {
    die("Anexo no encontrado.");
}

// Fetch tools for this anexo
$stmtTools = $pdo->prepare("
    SELECT at.*, t.name as tool_name, t.description as tool_type
    FROM anexo_tools at
    LEFT JOIN tools t ON at.tool_id = t.id
    WHERE at.anexo_id = ?
    ORDER BY at.row_index ASC
");
$stmtTools->execute([$id]);
$tools = $stmtTools->fetchAll();

$page_title = 'Ver Anexo Yazaki #' . $id;
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <h1>Anexo Yazaki #<?php echo str_pad($anexo['id'], 5, '0', STR_PAD_LEFT); ?></h1>
            <p class="text-muted">Creado el <?php echo date('d/m/Y h:i A', strtotime($anexo['created_at'])); ?> por
                <?php echo htmlspecialchars($anexo['creator_name']); ?></p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php" class="btn btn-secondary">
                <i class="ph ph-arrow-left"></i> Volver
            </a>
            <a href="print.php?id=<?php echo $anexo['id']; ?>" target="_blank" class="btn btn-primary"
                style="background: var(--primary-600);">
                <i class="ph ph-printer"></i> Imprimir Formato DGA
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"
            style="background: rgba(34, 197, 94, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
            Anexo creado correctamente. Ahora puedes imprimir el formato de la aduana.
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 2rem;">
        <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;"><i
                class="ph ph-info text-muted"></i> Información General</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <span class="text-muted" style="font-size: 0.85rem; display: block;">Proyecto Vinculado</span>
                <strong>
                    <?php if ($anexo['survey_id']): ?>
                        <a href="../levantamientos/view.php?id=<?php echo $anexo['survey_id']; ?>"
                            style="color: var(--primary-500); text-decoration: none;">#<?php echo $anexo['survey_id']; ?> -
                            <?php echo htmlspecialchars($anexo['survey_title']); ?></a>
                    <?php else: ?>
                        Independiente
                    <?php endif; ?>
                </strong>
            </div>
            <div>
                <span class="text-muted" style="font-size: 0.85rem; display: block;">Empresa Receptora</span>
                <strong><?php echo htmlspecialchars($anexo['client_name']); ?></strong>
            </div>
            <div>
                <span class="text-muted" style="font-size: 0.85rem; display: block;">Estado de Generación</span>
                <span
                    class="status-badge <?php echo $anexo['status'] == 'draft' ? 'status-orange' : 'status-green'; ?>">
                    <?php echo strtoupper($anexo['status'] == 'draft' ? 'Borrador' : 'Generado'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;"><i
                class="ph ph-wrench text-muted"></i> Herramientas a Ingresar</h3>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">Fila</th>
                        <th style="width: 100px;">Cant.</th>
                        <th>Descripción (Herramienta / Artículo Manual)</th>
                        <th>Tipo en Bodega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tools) > 0): ?>
                        <?php foreach ($tools as $t): ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold;"><?php echo $t['row_index']; ?></td>
                                <td><?php echo floatval($t['quantity']); ?></td>
                                <td>
                                    <?php
                                    if ($t['tool_id']) {
                                        echo htmlspecialchars($t['tool_name']);
                                    } else {
                                        echo htmlspecialchars($t['custom_description']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($t['tool_id']): ?>
                                        <span class="status-badge status-blue"><i class="ph ph-database"></i>
                                            <?php echo htmlspecialchars($t['tool_type']); ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-gray"><i class="ph ph-pencil-simple"></i> Texto
                                            Manual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">No se agregaron herramientas a este anexo.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>