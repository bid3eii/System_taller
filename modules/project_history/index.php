<?php
// modules/project_history/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('project_history', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Historial de Proyectos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (ps.title LIKE ? OR ps.client_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
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
        <h1>Historial de Levantamientos de Proyectos</h1>
        <p class="text-muted">Consulta de todos los levantamientos y requerimientos registrados.</p>
    </div>

    <!-- Toolbar -->
    <div class="card" style="margin-bottom: 2rem;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Bitácora General</h3>
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
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Cliente / Empresa</th>
                        <th>Título del Proyecto</th>
                        <th>Técnico Responsable</th>
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
                                    <?php
                                    $statusMaps = [
                                        'draft' => ['Borrador', 'gray'],
                                        'submitted' => ['Fidelizado/Enviado', 'blue'],
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
                                        <a href="../levantamientos/view.php?id=<?php echo $item['id']; ?>"
                                            class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;"
                                            title="Ver Detalle"><i class="ph ph-eye"></i></a>
                                        <a href="../levantamientos/print.php?id=<?php echo $item['id']; ?>" target="_blank"
                                            class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;"
                                            title="Imprimir/PDF Normal"><i class="ph ph-printer"></i></a>
                                        <?php if (can_access_module('anexos', $pdo)): ?>
                                            <a href="../anexos/create.php?survey_id=<?php echo $item['id']; ?>"
                                                class="btn btn-primary"
                                                style="padding: 0.4rem; font-size: 1rem; background: var(--primary-600);"
                                                title="Crear Anexo Yazaki (DGA)"><i class="ph ph-file-plus"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-archive" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay historial de proyectos</h3>
                                <p class="text-muted">Aún no se ha registrado ningún levantamiento en el sistema.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>