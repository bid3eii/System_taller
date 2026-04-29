<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificación de sesión
if (session_status() === PHP_SESSION_NONE) {
    @session_start(['gc_probability' => 0]);
}
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Check module permission
if (!can_access_module('viaticos', $pdo)) {
    header("Location: " . BASE_URL . "modules/dashboard/index.php");
    exit;
}

$can_add = can_access_module('viaticos_add', $pdo);
$can_edit = can_access_module('viaticos_edit', $pdo);
$can_delete = can_access_module('viaticos_delete', $pdo);

// Handle status updates if permitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status' && $can_edit) {
    $v_id = intval($_POST['viatico_id']);
    $new_st = $_POST['status'];
    try {
        $st = $pdo->prepare("UPDATE viaticos SET status = ? WHERE id = ?");
        $st->execute([$new_st, $v_id]);
        $success_msg = "Estado actualizado correctamente.";
    } catch (PDOException $e) {
        $error_msg = "Error al actualizar estado.";
    }
}

// Fetch viaticos based on role
$stmt_str = "SELECT v.*, u.full_name as creator_full_name, u.username as creator_username 
             FROM viaticos v 
             LEFT JOIN users u ON v.created_by = u.id 
             ORDER BY v.date DESC, v.id DESC";

$stmt = $pdo->query($stmt_str);
$viaticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Viáticos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<main class="main-content">
    <div class="content-header"
        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div class="header-title" style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="icon-box" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                <i class="ph ph-money"></i>
            </div>
            <div>
                <h1 style="margin: 0; margin-bottom: 0.25rem;">Control de Viáticos</h1>
                <p class="text-muted" style="margin: 0;">Gestión de presupuestos de viaje y comida por proyecto.</p>
            </div>
        </div>
        <?php if ($can_add): ?>
            <div class="header-actions">
                <a href="create.php" class="btn btn-primary"
                    style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-plus"></i>
                    Nuevo Viático
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success"><i class="ph ph-check-circle"></i>
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-error"><i class="ph ph-warning-circle"></i>
            <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Listado de Viáticos</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (count($viaticos) > 0): ?>
                <div class="table-responsive">
                    <table class="table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th class="text-center">ID</th>
                                <th class="text-center">Fecha</th>
                                <th class="text-center">Proyecto</th>
                                <th class="text-center">Creado Por</th>
                                <th class="text-center">Total ($)</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($viaticos as $v): ?>
                                <tr>
                                    <td class="text-center" style="font-family: monospace; color: var(--text-muted);">#
                                        <?php echo str_pad($v['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo date('d/m/Y', strtotime($v['date'])); ?>
                                    </td>
                                    <td class="text-center" style="font-weight: 500;">
                                        <?php echo htmlspecialchars($v['project_title']); ?>
                                    </td>
                                    <td class="text-center"><span class="badge" style="background: var(--bg-card);"><i
                                                class="ph ph-user"></i>
                                            <?php echo htmlspecialchars($v['creator_full_name'] ?: $v['creator_username']); ?>
                                        </span></td>
                                    <td class="text-center" style="font-weight: 600; color: var(--success);">$
                                        <?php echo number_format($v['total_amount'], 2); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $scls = 'bg-gray-500';
                                        $stxt = 'Borrador';
                                        if ($v['status'] == 'submitted') {
                                            $scls = 'bg-blue-500';
                                            $stxt = 'Presentado';
                                        } elseif ($v['status'] == 'paid') {
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
                                            <a href="view.php?id=<?php echo $v['id']; ?>&pdf=1" class="btn btn-secondary"
                                                style="padding: 0.4rem; font-size: 1rem;" title="Descargar PDF" target="_blank">
                                                <i class="ph ph-file-pdf" style="color: var(--danger);"></i>
                                            </a>
                                            <a href="view.php?id=<?php echo $v['id']; ?>" class="btn btn-secondary"
                                                style="padding: 0.4rem; font-size: 1rem;" title="Ver Detalle">
                                                <i class="ph ph-eye"></i>
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a href="edit.php?id=<?php echo $v['id']; ?>" class="btn btn-secondary"
                                                    style="padding: 0.4rem; font-size: 1rem;" title="Editar">
                                                    <i class="ph ph-pencil-simple text-warning"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete): ?>
                                                <form action="delete.php" method="POST" style="display:inline;"
                                                    onsubmit="return confirm('¿Está seguro de eliminar este registro? Esta acción es irreversible.');">
                                                    <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="padding: 0.4rem; font-size: 1rem; color: var(--danger);"
                                                        title="Eliminar">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($can_edit): ?>
                                                <!-- Simple form to toggle status paid -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="viatico_id" value="<?php echo $v['id']; ?>">
                                                    <input type="hidden" name="status"
                                                        value="<?php echo $v['status'] == 'paid' ? 'draft' : 'paid'; ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="padding: 0.4rem; font-size: 1rem;"
                                                        title="<?php echo $v['status'] == 'paid' ? 'Marcar Borrador' : 'Marcar Pagado'; ?>">
                                                        <i class="ph ph-check-circle"
                                                            style="color: <?php echo $v['status'] == 'paid' ? 'var(--text-muted)' : 'var(--success)'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 3rem 1.5rem; text-align: center;">
                    <div class="empty-state-icon" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"><i
                            class="ph ph-money"></i></div>
                    <h3>No hay viáticos registrados</h3>
                    <p class="text-muted">Comienza creando un presupuesto para tu próximo proyecto.</p>
                    <?php if ($can_add): ?>
                        <a href="create.php" class="btn btn-primary" style="margin-top: 1rem;">Crear Viático</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once '../../includes/footer.php'; ?>