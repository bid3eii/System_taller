<?php
// modules/anexos/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('anexos', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Anexos Yazaki';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (can_access_module('anexos', $pdo) && has_role(['Super Admin', 'Administrador'], $pdo)) {
        $delete_id = intval($_POST['delete_id']);
        $stmtD = $pdo->prepare("DELETE FROM anexos_yazaki WHERE id = ?");
        if ($stmtD->execute([$delete_id])) {
            $success = "Anexo eliminado correctamente.";
        } else {
            $error = "Error al eliminar el anexo.";
        }
    } else {
        $error = "No tienes permiso para eliminar anexos.";
    }
}

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (a.client_name LIKE ? OR u.username LIKE ? OR ps.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch Anexos
$sql = "
    SELECT 
        a.*, 
        u.username as creator_name,
        ps.title as survey_title,
        (SELECT COUNT(*) FROM anexo_tools at WHERE at.anexo_id = a.id) as tools_count
    FROM anexos_yazaki a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN project_surveys ps ON a.survey_id = ps.id
    WHERE $where
    ORDER BY a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$anexos = $stmt->fetchAll();
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1>Anexos Yazaki (Anexo 10)</h1>
            <p class="text-muted">Gestión de formularios de ingreso de herramientas a Aduana para mtto.</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-primary" style="background: var(--primary-600);">
                <i class="ph ph-plus"></i> Nuevo Anexo
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"
            style="background: rgba(34, 197, 94, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"
            style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="card" style="margin-bottom: 2rem;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Lista de Anexos</h3>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control"
                            placeholder="Buscar por proyecto, empresa, creador..."
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
                        <th>ID / Folio</th>
                        <th>Fecha Creación</th>
                        <th>Empresa Receptora</th>
                        <th>Proyecto Vinculado</th>
                        <th>Herramientas</th>
                        <th>Creado por</th>
                        <th>Estado</th>
                        <th style="min-width: 150px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($anexos) > 0): ?>
                        <?php foreach ($anexos as $item): ?>
                            <tr>
                                <td><strong>#
                                        <?php echo str_pad($item['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </strong></td>
                                <td>
                                    <?php echo date('d/m/Y h:i A', strtotime($item['created_at'])); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($item['client_name'] ?? 'YAZAKI DE NICARAGUA SA'); ?>
                                </td>
                                <td>
                                    <?php if ($item['survey_id']): ?>
                                        <a href="../levantamientos/view.php?id=<?php echo $item['survey_id']; ?>"
                                            style="color: var(--primary-500); text-decoration: none;">
                                            #
                                            <?php echo $item['survey_id']; ?> -
                                            <?php echo htmlspecialchars(substr($item['survey_title'], 0, 30)) . (strlen($item['survey_title']) > 30 ? '...' : ''); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Ninguno Independiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge status-gray">
                                        <?php echo $item['tools_count']; ?> Ítems
                                    </span></td>
                                <td>
                                    <?php echo htmlspecialchars($item['creator_name']); ?>
                                </td>
                                <td>
                                    <?php if ($item['status'] == 'draft'): ?>
                                        <span class="status-badge status-orange">Borrador</span>
                                    <?php else: ?>
                                        <span class="status-badge status-green">Generado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <?php if (has_role(['Super Admin', 'Administrador', 'Supervisor', 'Técnico'], $pdo)): ?>
                                            <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn-icon"
                                                style="color: var(--primary-500) !important;" title="Editar Anexo"><i
                                                    class="ph ph-pencil-simple"></i></a>
                                        <?php endif; ?>
                                        <a href="view.php?id=<?php echo $item['id']; ?>" class="btn-icon"
                                            title="Ver Detalles"><i class="ph ph-eye"></i></a>
                                        <a href="print.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn-icon"
                                            style="color: var(--primary-500) !important;" title="Imprimir PDF"><i
                                                class="ph ph-file-pdf"></i></a>
                                        <?php if (has_role(['Super Admin', 'Administrador'], $pdo)): ?>
                                            <form method="POST" style="display:inline;" class="form-delete">
                                                <input type="hidden" name="delete_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn-icon"
                                                    style="color: var(--danger) !important; background: transparent; border: 1px solid var(--border-color); cursor: pointer;"
                                                    title="Eliminar"><i class="ph ph-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-file-pdf" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay anexos registrados</h3>
                                <p class="text-muted">Aún no has creado ningún Anexo 10. Selecciona "Nuevo Anexo" para
                                    empezar.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Estilo del recuadro de confirmación SweetAlert2 para contraste */
    div.swal2-popup {
        border: 1px solid var(--border-color) !important;
        border-radius: 12px !important;
        box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.5) !important;
        background-color: var(--bg-secondary) !important;
    }

    h2.swal2-title {
        color: var(--text-primary) !important;
    }

    div.swal2-html-container {
        color: var(--text-secondary) !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForms = document.querySelectorAll('.form-delete');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar Anexo?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#475569',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    background: 'var(--bg-secondary)',
                    color: 'var(--text-primary)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>