<?php
// modules/clients/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('clients', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Clientes';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php'; // Actually Navbar

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR tax_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT DISTINCT c.* 
    FROM clients c 
    JOIN service_orders so ON c.id = so.client_id 
    WHERE $where 
      AND (so.service_type != 'warranty' OR so.problem_reported != 'Garantía Registrada')
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Gestión de Clientes</h1>
        <p class="text-muted">Administra la base de datos de tus clientes.</p>
    </div>

    <!-- Clients Table -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Listado de Clientes</h3>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, DNI..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </form>
                <a href="add.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nuevo Cliente</a>
            </div>
        </div>
        <div class="table-container">
            <table id="clientsTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
                            Nombre <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="1">
                            DNI / RUC <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="2">
                            Contacto <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="3">
                            Dirección <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) > 0): ?>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 32px; height: 32px; background: var(--bg-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-500); font-weight: bold;">
                                        <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
                                    </div>
                                    <span class="font-medium"><?php echo htmlspecialchars($client['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($client['tax_id']); ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                    <span class="text-sm"><i class="ph ph-phone"></i> <?php echo htmlspecialchars($client['phone']); ?></span>
                                    <span class="text-sm text-muted"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($client['email']); ?></span>
                                </div>
                            </td>
                            <td class="text-sm"><?php echo htmlspecialchars($client['address']); ?></td>
                            <td>
                                <a href="edit.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                <a href="history.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;" title="Ver Historial"><i class="ph ph-clock-counter-clockwise"></i></a>
                                <button type="button" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem; color: var(--danger);" title="Eliminar" onclick="openDeleteModal(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name'], ENT_QUOTES); ?>')"><i class="ph ph-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-users" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No se encontraron clientes</h3>
                                <p class="text-muted">Intenta con otra búsqueda o agrega un nuevo cliente.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); padding: 2rem; border-radius: 16px; width: 450px; max-width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); animation: modalSlideIn 0.3s ease-out;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="ph-fill ph-warning-circle" style="font-size: 2.5rem; color: var(--danger);"></i>
            </div>
            <h3 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 1.25rem;">¿Eliminar Cliente?</h3>
            <p style="color: var(--text-secondary); margin: 0; line-height: 1.5;">Estás a punto de eliminar a <strong id="deleteClientName" style="color: var(--text-primary);"></strong>. Esta acción no se puede deshacer.</p>
        </div>
        
        <div style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 0.75rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: start; gap: 0.5rem;">
                <i class="ph ph-warning" style="color: var(--danger); font-size: 1.1rem; margin-top: 0.1rem;"></i>
                <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.4;">Se eliminarán todos los datos asociados a este cliente.</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 0.75rem;">
            <button type="button" onclick="closeDeleteModal()" class="btn" style="flex: 1; background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary);">
                Cancelar
            </button>
            <button type="button" id="confirmDeleteBtn" class="btn" style="flex: 1; background: var(--danger); color: white; border: none; font-weight: 600;">
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
/* Sortable Column Headers */
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.2s;
}

.sortable:hover {
    background-color: var(--bg-hover);
    color: var(--primary-500);
}

.sort-icon {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    opacity: 0.4;
    transition: all 0.2s;
}

.sortable:hover .sort-icon {
    opacity: 0.7;
}

.sortable.asc .sort-icon,
.sortable.desc .sort-icon {
    opacity: 1;
    color: var(--primary-500);
}

.sortable.asc .sort-icon::before {
    content: "\f196"; /* ph-caret-up */
}

.sortable.desc .sort-icon::before {
    content: "\f194"; /* ph-caret-down */
}
</style>

<script>
// Table Sorting Functionality
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('clientsTable');
    const sortableHeaders = document.querySelectorAll('.sortable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Store original rows (excluding "no results" row)
    let originalRows = rows.filter(row => !row.querySelector('td[colspan]'));
    let currentSortColumn = null;
    let currentSortDirection = null;
    
    // Sorting functionality
    function sortTable(columnIndex, direction) {
        const sortedRows = [...originalRows].sort((rowA, rowB) => {
            const cellA = rowA.querySelectorAll('td')[columnIndex];
            const cellB = rowB.querySelectorAll('td')[columnIndex];
            
            if (!cellA || !cellB) return 0;
            
            let textA = cellA.textContent.trim().toLowerCase();
            let textB = cellB.textContent.trim().toLowerCase();
            
            // Try to parse as numbers for numeric sorting
            const numA = parseFloat(textA);
            const numB = parseFloat(textB);
            
            if (!isNaN(numA) && !isNaN(numB)) {
                return direction === 'asc' ? numA - numB : numB - numA;
            }
            
            // Alphabetical sorting
            if (direction === 'asc') {
                return textA.localeCompare(textB, 'es');
            } else {
                return textB.localeCompare(textA, 'es');
            }
        });
        
        // Update originalRows to maintain sort order
        originalRows = sortedRows;
        
        // Re-append rows in sorted order
        sortedRows.forEach(row => tbody.appendChild(row));
        
        // Update header indicators
        sortableHeaders.forEach(header => {
            header.classList.remove('asc', 'desc');
        });
        
        const activeHeader = document.querySelector(`.sortable[data-column="${columnIndex}"]`);
        if (activeHeader) {
            activeHeader.classList.add(direction);
        }
    }
    
    // Add click handlers to sortable headers
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const columnIndex = parseInt(this.dataset.column);
            
            // Determine sort direction
            let direction = 'asc';
            if (currentSortColumn === columnIndex) {
                if (currentSortDirection === 'asc') {
                    direction = 'desc';
                } else if (currentSortDirection === 'desc') {
                    // Reset to original order
                    direction = null;
                    currentSortColumn = null;
                    currentSortDirection = null;
                    
                    // Remove all sort classes
                    sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
                    return;
                }
            }
            
            currentSortColumn = columnIndex;
            currentSortDirection = direction;
            
            sortTable(columnIndex, direction);
        });
    });
    // Search functionality
    const searchInput = document.querySelector('input[name="search"]');
    const searchForm = searchInput ? searchInput.closest('form') : null;

    if (searchInput) {
        // Prevent form submission
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
        }

        let debounceTimer;

        const triggerSearch = function(e) {
            clearTimeout(debounceTimer);
            const searchTerm = e.target.value;
            
            debounceTimer = setTimeout(() => {
                fetch(`search_clients.php?search=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        
                        // Update originalRows for sorting to work with new data
                        originalRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
                        
                        // Reset sort state
                        currentSortColumn = null;
                        currentSortDirection = null;
                        sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
                    })
                    .catch(error => console.error('Error searching:', error));
            }, 300); // 300ms debounce
        };

        searchInput.addEventListener('input', triggerSearch);
        searchInput.addEventListener('change', triggerSearch);
    }
});

// Delete Modal Functions
let deleteClientId = null;

function openDeleteModal(clientId, clientName) {
    deleteClientId = clientId;
    document.getElementById('deleteClientName').textContent = clientName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    deleteClientId = null;
    document.getElementById('deleteModal').style.display = 'none';
}

// Confirm delete
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteClientId) {
        window.location.href = `delete.php?id=${deleteClientId}`;
    }
});

// Close on outside click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('deleteModal').style.display === 'flex') {
        closeDeleteModal();
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>
