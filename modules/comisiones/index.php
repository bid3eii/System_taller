<?php
// modules/comisiones/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Comisiones';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$is_admin = can_access_module('comisiones_add', $pdo); // using comisiones_add as proxy for admin in this module
$user_id = $_SESSION['user_id'];

// Filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tech_filter = strval($_GET['tech_id'] ?? '');

// Build query
$params = [];
$where = [];

if (!$is_admin) {
    // Regular techs only see their own
    $where[] = "c.tech_id = ?";
    $params[] = $user_id;
} else if ($tech_filter !== '') {
    // Admin filtering by tech
    $where[] = "c.tech_id = ?";
    $params[] = $tech_filter;
}

if ($search) {
    $where[] = "(c.cliente LIKE ? OR c.servicio LIKE ? OR c.caso LIKE ? OR c.factura LIKE ?)";
    $srch = "%$search%";
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
}

if ($status_filter) {
    $where[] = "c.estado = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt_str = "SELECT c.*, u.username as tech_name 
             FROM comisiones c 
             LEFT JOIN users u ON c.tech_id = u.id 
             $where_clause
             ORDER BY c.fecha_servicio DESC, c.id DESC";

$stmt = $pdo->prepare($stmt_str);
$stmt->execute($params);
$comisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch technicians for filter (Admins only)
$technicians = [];
if ($is_admin) {
    $techStmt = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username");
    $technicians = $techStmt->fetchAll();
}

// Check for status messages
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .table-responsive {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 1rem;
        border: 1px solid var(--border-color);
        overflow-x: auto;
    }
</style>

<div class="animate-enter">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-coins" style="color: var(--primary-500);"></i>
                Comisiones por Proyecto
            </h1>
            <p class="text-muted">Registro y control de pagos a técnicos</p>
        </div>
        <div>
            <?php if (can_access_module('comisiones_add', $pdo)): ?>
                <a href="add.php" class="btn btn-primary" style="display: flex; gap: 0.5rem; align-items: center;">
                    <i class="ph ph-plus"></i> Nueva Comisión Manual
                </a>
            <?php endif; ?>
        </div>
    </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label
                        style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Buscar</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Cliente, servicio, caso, factura..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </div>

                <div style="width: 150px;">
                    <label
                        style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Estado</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="PENDIENTE" <?php echo $status_filter === 'PENDIENTE' ? 'selected' : ''; ?>>
                            Pendiente</option>
                        <option value="PAGADA" <?php echo $status_filter === 'PAGADA' ? 'selected' : ''; ?>>Pagada
                        </option>
                    </select>
                </div>

                <?php if ($is_admin): ?>
                    <div style="width: 200px;">
                        <label
                            style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Técnico</label>
                        <select name="tech_id" class="form-control">
                            <option value="">Todos los técnicos</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo $tech_filter == $tech['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <button type="submit" class="btn btn-secondary" style="height: 38px;">
                        <i class="ph ph-funnel"></i> Filtrar
                    </button>
                    <?php if ($search || $status_filter || $tech_filter): ?>
                        <a href="index.php" class="btn btn-secondary"
                            style="height: 38px; border-color: var(--danger); color: var(--danger);">
                            <i class="ph ph-x"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"
                style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-check-circle"></i>
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-warning-circle"></i>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Caso</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Fecha</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Cliente</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Servicio</th>
                        <?php if ($is_admin): ?>
                            <th
                                style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                                Técnico</th>
                        <?php endif; ?>
                        <th class="text-center"
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Cuota</th>
                        <th class="text-center"
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Estado</th>
                        <?php if ($is_admin): ?>
                            <th class="text-center"
                                style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                                Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($comisiones) > 0): ?>
                        <?php foreach ($comisiones as $c): ?>
                            <tr>
                                <td>
                                    <?php if ($c['tipo'] === 'PROYECTO' && !empty($c['reference_id'])): ?>
                                        <a href="../levantamientos/view.php?id=<?php echo $c['reference_id']; ?>"
                                            style="color: var(--primary-500); text-decoration: none; font-weight: 500;">
                                            <?php echo htmlspecialchars($c['caso']); ?>
                                        </a>
                                    <?php elseif ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])): ?>
                                        <a href="../services/view.php?id=<?php echo $c['reference_id']; ?>"
                                            style="color: var(--primary-500); text-decoration: none; font-weight: 500;">
                                            <?php echo htmlspecialchars($c['caso']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($c['caso']); ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <span class="badge"
                                        style="font-size: 0.70rem; margin-top: 0.25rem;"><?php echo htmlspecialchars($c['tipo']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($c['fecha_servicio'])); ?>
                                    <?php if ($c['fecha_facturacion']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            Fact: <?php echo date('d/m/Y', strtotime($c['fecha_facturacion'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($c['cliente']); ?></div>
                                    <?php if ($c['lugar']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><i class="ph ph-map-pin"></i>
                                            <?php echo htmlspecialchars($c['lugar']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($c['servicio']); ?>">
                                        <?php echo htmlspecialchars($c['servicio']); ?>
                                    </div>
                                    <?php if ($c['factura']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;"><i
                                                class="ph ph-receipt"></i> Factura: <?php echo htmlspecialchars($c['factura']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="ph ph-user-circle" style="color: var(--text-muted); font-size: 1.2rem;"></i>
                                            <span><?php echo htmlspecialchars($c['tech_name'] ?: 'Desconocido'); ?></span>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center" style="font-weight: 600; color: var(--text-main);">
                                    <?php echo $c['cantidad']; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $scls = $c['estado'] === 'PAGADA' ? 'green' : 'orange';
                                    ?>
                                    <span class="status-badge status-<?php echo $scls; ?>">
                                        <?php echo $c['estado']; ?>
                                    </span>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td class="text-center">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <?php if ($c['estado'] === 'PENDIENTE'): ?>
                                                <form action="mark_paid.php" method="POST" style="display: inline;"
                                                    onsubmit="return confirm('¿Marcar esta comisión como PAGADA?');">
                                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="color: var(--success); border-color: var(--success);"
                                                        title="Marcar como Pagada">
                                                        <i class="ph ph-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (can_access_module('comisiones_delete', $pdo)): ?>
                                                <form action="delete.php" method="POST" style="display: inline;"
                                                    onsubmit="return confirm('¿Está seguro de eliminar esta comisión de forma permanente?');">
                                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="color: var(--danger); border-color: var(--danger);" title="Eliminar">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                                <i class="ph ph-coins" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No se encontraron registros de comisiones.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php require_once '../../includes/footer.php'; ?>