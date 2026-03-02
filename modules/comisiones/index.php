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

// Fetch all comisiones
$stmt_str = "SELECT c.*, u.username as creator_name 
             FROM comisiones c 
             LEFT JOIN users u ON c.created_by = u.id 
             ORDER BY c.date DESC, c.id DESC";

$stmt = $pdo->query($stmt_str);
$comisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <a href="create.php" class="btn btn-primary" style="background: var(--primary-600);">
                    <i class="ph ph-plus"></i> Ingresar Comisión
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
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
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        ID</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Fecha</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Proyecto / Referencia</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Registrado Por</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Total</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Estado</th>
                    <th class="text-center"
                        style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                        Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($comisiones) > 0): ?>
                    <?php foreach ($comisiones as $c): ?>
                        <tr>
                            <td class="text-center" style="font-family: monospace; color: var(--text-muted);">#
                                <?php echo str_pad($c['id'], 5, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td class="text-center">
                                <?php echo date('d/m/Y', strtotime($c['date'])); ?>
                            </td>
                            <td class="text-center" style="font-weight: 500;">
                                <?php if (!empty($c['survey_id'])): ?>
                                    <a href="../levantamientos/view.php?id=<?php echo $c['survey_id']; ?>"
                                        style="color: var(--primary); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
                                        <i class="ph ph-link"></i>
                                        <?php echo htmlspecialchars($c['project_title']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($c['project_title']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge" style="background: var(--bg-card);"><i class="ph ph-user"></i>
                                    <?php echo htmlspecialchars($c['creator_name']); ?>
                                </span>
                            </td>
                            <td class="text-center" style="font-weight: 600; color: var(--success);">
                                $
                                <?php echo number_format($c['total_amount'], 2); ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $scls = 'bg-gray-500';
                                $stxt = 'Borrador';
                                if ($c['status'] == 'paid') {
                                    $scls = 'bg-green-500';
                                    $stxt = 'Pagado';
                                }
                                ?>
                                <span class="badge badge-sm"
                                    style="background: <?php echo $scls; ?>20; color: <?php echo $scls; ?>; border: 1px solid <?php echo $scls; ?>40;">
                                    <?php echo $stxt; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary"
                                        title="Ver Detalles">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                    <?php if (can_access_module('comisiones_delete', $pdo)): ?>
                                        <form action="delete.php" method="GET" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar estas comisiones? Se borrarán todos los detalles asociados permanentemente.');">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="color: var(--danger); border-color: var(--danger);" title="Eliminar">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
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