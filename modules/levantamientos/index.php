<?php
// modules/levantamientos/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Levantamientos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

// Techs can only see their own surveys unless they have surveys_view_all
$can_view_all = can_access_module('surveys_view_all', $pdo);
if (!$can_view_all) {
    $where .= " AND ps.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($search) {
    if (!$can_view_all) {
        $where .= " AND (ps.title LIKE ? OR ps.client_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $where .= " AND (ps.title LIKE ? OR ps.client_name LIKE ? OR u.username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$sql = "
    SELECT 
        ps.*, 
        u.username as tech_name,
        (SELECT COUNT(*) FROM project_materials pm WHERE pm.survey_id = ps.id) as materials_count
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE $where
    ORDER BY ps.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$surveys = $stmt->fetchAll();
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Levantamientos Técnicos</h1>
        <p class="text-muted">Gestión de alcance y requerimientos de proyectos.</p>
    </div>

    <!-- Toolbar -->
    <div class="card" style="margin-bottom: 2rem;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Lista de Levantamientos</h3>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control"
                            placeholder="Buscar por cliente, título..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Buscar</button>
                    <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;" title="Limpiar"><i
                            class="ph ph-arrows-counter-clockwise"></i></a>
                </form>
                <?php if (can_access_module('surveys_add', $pdo)): ?>
                    <a href="add.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nuevo Levantamiento</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Título del Proyecto</th>
                        <th>Técnico</th>
                        <th>Materiales</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($surveys) > 0): ?>
                        <?php foreach ($surveys as $item): ?>
                            <tr>
                                <td><strong>#
                                        <?php echo str_pad($item['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </strong></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div
                                            style="width: 28px; height: 28px; background: var(--bg-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-500); font-weight: bold; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($item['client_name'], 0, 1)); ?>
                                        </div>
                                        <span class="font-medium">
                                            <?php echo htmlspecialchars($item['client_name']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;"
                                        title="<?php echo htmlspecialchars($item['title']); ?>">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item['tech_name']); ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-secondary);">
                                        <?php echo $item['materials_count']; ?> items
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusMaps = [
                                        'draft' => ['Borrador', 'gray'],
                                        'submitted' => ['Enviado', 'blue'],
                                        'approved' => ['Aprobado', 'green']
                                    ];
                                    $col = $statusMaps[$item['status']][1] ?? 'gray';
                                    $lbl = $statusMaps[$item['status']][0] ?? $item['status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $col; ?>">
                                        <?php echo strtoupper($lbl); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary"
                                            style="padding: 0.4rem; font-size: 1rem;" title="Ver Detalle"><i
                                                class="ph ph-eye"></i></a>
                                        <a href="print.php?id=<?php echo $item['id']; ?>" target="_blank"
                                            class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;"
                                            title="Imprimir/PDF"><i class="ph ph-printer"></i></a>

                                        <?php if (can_access_module('surveys_edit', $pdo) && $item['status'] !== 'approved'): ?>
                                            <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary"
                                                style="padding: 0.4rem; font-size: 1rem;" title="Editar"><i
                                                    class="ph ph-pencil-simple"></i></a>
                                        <?php endif; ?>

                                        <?php if (can_access_module('surveys_delete', $pdo)): ?>
                                            <button type="button" class="btn btn-secondary"
                                                style="padding: 0.4rem; font-size: 1rem; color: var(--danger);" title="Eliminar"
                                                onclick="openDeleteModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title'], ENT_QUOTES); ?>')"><i
                                                    class="ph ph-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-file-text" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No se encontraron levantamientos</h3>
                                <p class="text-muted">Crea un nuevo levantamiento para iniciar un proyecto.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div
        style="background: var(--bg-card); padding: 2rem; border-radius: 16px; width: 450px; max-width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); animation: modalSlideIn 0.3s ease-out;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div
                style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ph-fill ph-warning-circle" style="font-size: 2.5rem; color: var(--danger);"></i>
            </div>
            <h3 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 1.25rem;">¿Eliminar Levantamiento?
            </h3>
            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">Estás a punto de eliminar el proyecto
                <strong id="deleteSurveyName" style="color: var(--text-primary);"></strong>.
            </p>
        </div>

        <div
            style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.75rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: start; gap: 0.5rem;">
                <i class="ph ph-warning" style="color: var(--danger); font-size: 1.1rem; margin-top: 0.1rem;"></i>
                <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.4;">Se eliminarán
                    los materiales y todos los datos del proyecto. Esta acción no se puede deshacer.</p>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" onclick="closeDeleteModal()" class="btn"
                style="flex: 1; background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary);">
                Cancelar
            </button>
            <button type="button" id="confirmDeleteBtn" class="btn"
                style="flex: 1; background: var(--danger); color: white; border: none; font-weight: 600;">
                <i class="ph ph-trash"></i> Eliminar
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<script>
    let deleteSurveyId = null;

    function openDeleteModal(id, title) {
        deleteSurveyId = id;
        document.getElementById('deleteSurveyName').textContent = title;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        deleteSurveyId = null;
        document.getElementById('deleteModal').style.display = 'none';
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
        if (deleteSurveyId) {
            window.location.href = `delete.php?id=${deleteSurveyId}`;
        }
    });

    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) closeDeleteModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('deleteModal').style.display === 'flex') {
            closeDeleteModal();
        }
    });
</script>

<?php
require_once '../../includes/footer.php';
?>