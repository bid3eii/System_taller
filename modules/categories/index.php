<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!has_role(['SuperAdmin', 'Administrador'], $pdo) && !can_access_module('warranties', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Categorías de Equipos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM equipment_categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$error = '';
$success = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') $success = "Categoría guardada correctamente.";
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') $success = "Categoría eliminada correctamente.";
if (isset($_GET['err'])) $error = htmlspecialchars($_GET['err']);
?>

<div class="animate-enter" style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1><i class="ph ph-list-dashes" style="color: var(--primary-500);"></i> Categorías de Equipos</h1>
            <p class="text-muted">Administra las categorías y sus meses de garantía predeterminados.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="ph ph-plus-circle"></i> Nueva Categoría
        </button>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-container">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de Categoría</th>
                        <th>Garantía (Meses)</th>
                        <th style="width: 100px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categories) > 0): ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td><?php echo $cat['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                <td><span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><?php echo $cat['default_months']; ?> meses</span></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <button class="btn-icon" title="Editar" style="color: #f59e0b;" 
                                            onclick='openModal(<?php echo json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="ph ph-pencil-simple"></i>
                                        </button>
                                        <button class="btn-icon" title="Eliminar" style="color: #ef4444;" 
                                            onclick="confirmDelete(<?php echo $cat['id']; ?>)">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-muted);">No hay categorías registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -> Crear/Editar -->
<div id="categoryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 90%; max-width: 500px; padding: 2rem; position: relative; border: 1px solid var(--border-color);">
        <button onclick="document.getElementById('categoryModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 1.5rem;"><i class="ph ph-list-plus" style="color: var(--primary-500);"></i> Nueva Categoría</h3>
        
        <form method="POST" action="save.php">
            <input type="hidden" name="id" id="cat_id" value="">
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Nombre de Categoría *</label>
                <input type="text" name="name" id="cat_name" class="form-control" placeholder="Ej. UPS Básico, Baterías..." required>
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Garantía Predeterminada (Meses) *</label>
                <input type="number" name="default_months" id="cat_months" class="form-control" placeholder="Ej. 12" value="12" min="1" required>
            </div>
            
            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('categoryModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario para delete -->
<form id="deleteForm" method="POST" action="delete.php" style="display: none;">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function openModal(data = null) {
    if (data) {
        document.getElementById('modalTitle').innerHTML = '<i class="ph ph-pencil-simple" style="color: #f59e0b;"></i> Editar Categoría';
        document.getElementById('cat_id').value = data.id;
        document.getElementById('cat_name').value = data.name;
        document.getElementById('cat_months').value = data.default_months;
    } else {
        document.getElementById('modalTitle').innerHTML = '<i class="ph ph-list-plus" style="color: var(--primary-500);"></i> Nueva Categoría';
        document.getElementById('cat_id').value = '';
        document.getElementById('cat_name').value = '';
        document.getElementById('cat_months').value = '12';
    }
    document.getElementById('categoryModal').style.display = 'flex';
}

function confirmDelete(id) {
    if (confirm('¿Está seguro de eliminar esta categoría? Los equipos asociados no se eliminarán pero perderán la referencia de categoría.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
