<?php
// modules/proyectos/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission (only admins/superadmins will have this later, but for now let's just use a dedicated 'proyectos' or 'admin' only check)
if (!can_access_module('proyectos', $pdo) && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden gestionar proyectos.");
}

$page_title = 'Gestión de Proyectos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "1=1"; 
$params = [];

if ($search) {
    $where .= " AND (ps.title LIKE ? OR ps.client_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where .= " AND ps.status = ?";
    $params[] = $status_filter;
}

$sql = "
    SELECT 
        ps.*, 
        u.username as tech_name,
        (SELECT COUNT(*) FROM project_materials pm WHERE pm.survey_id = ps.id) as materials_count
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE $where
    ORDER BY FIELD(ps.status, 'submitted', 'draft', 'in_progress', 'approved', 'completed'), ps.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$surveys = $stmt->fetchAll();
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1>Gestión Estratégica de Proyectos</h1>
            <p class="text-muted">Aprobación de levantamientos, finanzas y control de ejecución.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php"
                class="btn <?php echo empty($status_filter) ? 'btn-primary' : 'btn-secondary'; ?>">Todos</a>
            <a href="index.php?status=submitted"
                class="btn <?php echo $status_filter === 'submitted' ? 'btn-primary' : 'btn-secondary'; ?>">Por
                Aprobar</a>
            <a href="index.php?status=in_progress"
                class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-secondary'; ?>">En
                Curso</a>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="card" style="margin-bottom: 2rem;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Lista de Proyectos</h3>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php if ($status_filter): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <?php endif; ?>
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control"
                            placeholder="Buscar por cliente, título, técnico..."
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
                        <th>ID / Fecha</th>
                        <th>Cliente / Proyecto</th>
                        <th>Técnico</th>
                        <th>Estado Operativo</th>
                        <th>Estado Finanzas</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($surveys) > 0): ?>
                        <?php foreach ($surveys as $item): ?>
                            <tr>
                                <td>
                                    <strong>#
                                        <?php echo str_pad($item['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </strong><br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div
                                            style="width: 32px; height: 32px; background: rgba(56, 189, 248, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #38bdf8; font-weight: bold;">
                                            <?php echo strtoupper(substr($item['client_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #fff; margin-bottom: 0.2rem;">
                                                <?php echo htmlspecialchars($item['client_name']); ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #94a3b8; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                                title="<?php echo htmlspecialchars($item['title']); ?>">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="ph ph-user" style="color: var(--text-muted);"></i>
                                        <?php echo htmlspecialchars($item['tech_name']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusMaps = [
                                        'draft' => ['Borrador', 'gray'],
                                        'submitted' => ['Aprobación Pdte', 'blue'],
                                        'approved' => ['Aprobado / Listo', 'indigo'],
                                        'in_progress' => ['En Progreso', 'orange'],
                                        'completed' => ['Completado', 'green']
                                    ];
                                    $col = $statusMaps[$item['status']][1] ?? 'gray';
                                    $lbl = $statusMaps[$item['status']][0] ?? $item['status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $col; ?>">
                                        <?php echo strtoupper($lbl); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $paymentMaps = [
                                        'pendiente' => ['Pendiente Fact.', 'gray'],
                                        'pagado' => ['Pagado', 'green']
                                    ];
                                    $pCol = $paymentMaps[$item['payment_status']][1] ?? 'gray';
                                    $pLbl = $paymentMaps[$item['payment_status']][0] ?? $item['payment_status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $pCol; ?>">
                                        <?php echo strtoupper($pLbl); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="manage.php?id=<?php echo $item['id']; ?>" class="btn btn-primary"
                                        style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                                        <i class="ph ph-gear"></i> Gestionar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 4rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary); opacity: 0.5;">
                                    <i class="ph ph-kanban" style="font-size: 4rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem; color: #fff;">No hay proyectos para mostrar</h3>
                                <p class="text-muted">No se encontraron proyectos activos o pendientes de revisión.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>