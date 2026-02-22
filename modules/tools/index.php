<?php
// modules/tools/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Herramientas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR description LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

// Check if table exists to avoid ugly errors if SQL script didn't run
try {
    $stmt = $pdo->prepare("SELECT * FROM tools WHERE $where ORDER BY name ASC");
    $stmt->execute($params);
    $tools = $stmt->fetchAll();
} catch (PDOException $e) {
    $tools = [];
    $error = "Error al cargar herramientas. ¿Se ha ejecutado el script de base de datos? " . $e->getMessage();
}
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Gestión de Herramientas</h1>
        <p class="text-muted">Administra el inventario de herramientas y equipos de trabajo.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Tools Table -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Inventario de Herramientas</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" id="searchInput" name="search" class="form-control" placeholder="Buscar herramienta..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </form>
                <a href="add.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nueva Herramienta</a>
                <a href="assign.php" class="btn btn-secondary"><i class="ph ph-clipboard-text"></i> Asignar Herramientas</a>
                <a href="assignments.php" class="btn btn-secondary"><i class="ph ph-list-dashes"></i> Ver Asignaciones</a>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tools) > 0): ?>
                        <?php foreach ($tools as $tool): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 32px; height: 32px; background: var(--bg-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-500);">
                                        <i class="ph ph-wrench"></i>
                                    </div>
                                    <span class="font-medium"><?php echo htmlspecialchars($tool['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($tool['description']); ?></td>
                            <td>
                                <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);">
                                    <?php echo htmlspecialchars($tool['quantity']); ?> 
                                </span>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'available' => 'var(--success)',
                                    'assigned' => 'var(--warning)',
                                    'maintenance' => 'var(--accent)',
                                    'lost' => 'var(--danger)'
                                ];
                                $status_labels = [
                                    'available' => 'Disponible',
                                    'assigned' => 'Asignado',
                                    'maintenance' => 'Mantenimiento',
                                    'lost' => 'Extraviado'
                                ];
                                $color = $status_colors[$tool['status']] ?? 'var(--text-secondary)';
                                $label = $status_labels[$tool['status']] ?? ucfirst($tool['status']);
                                ?>
                                <span class="badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit.php?id=<?php echo $tool['id']; ?>" class="btn btn-secondary btn-icon" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                <a href="delete.php?id=<?php echo $tool['id']; ?>" class="btn btn-secondary btn-icon" style="color: var(--danger);" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar esta herramienta?');"><i class="ph ph-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-wrench" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No se encontraron herramientas</h3>
                                <p class="text-muted">Agrega herramientas al inventario para comenzar.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector('table tbody');
    const rows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase();

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            
            // Skip "No results" row if it exists (usually has colspan)
            if (row.cells.length < 2) continue;

            // Get text from Name (0) and Description (1) columns
            // Name is inside a div, so we get textContent
            const nameText = row.cells[0].textContent || row.cells[0].innerText;
            const descText = row.cells[1].textContent || row.cells[1].innerText;
            
            const text = (nameText + " " + descText).toLowerCase();
            
            if (text.indexOf(filter) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    });

    // Prevent form submission on Enter if we want purely client side, 
    // but letting it reload update URL state is also fine. 
    // Let's keep the reload as fallback but maybe prevent it if input is focused? 
    // User requested "search while writing", so the input event covers it.
});
</script>

<?php
require_once '../../includes/footer.php';
?>
