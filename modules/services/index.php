<?php
// modules/services/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

// Check permission
if (!can_access_module('services', $pdo)) {
    die("Acceso denegado.");
}

// Handle Technician Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_tech') {
    // Verify that the one who has that module assigned can assign to technician
    // We already checked can_access_module at the top of the file
    if (true) {
        $order_id = $_POST['order_id'];
        $tech_id = !empty($_POST['tech_id']) ? $_POST['tech_id'] : null;
        
        try {
            $stmt = $pdo->prepare("UPDATE service_orders SET assigned_tech_id = ? WHERE id = ?");
            $stmt->execute([$tech_id, $order_id]);
            
            // Log history
            $tech_name = "Sin Asignar";
            if($tech_id) {
                $stmtT = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtT->execute([$tech_id]);
                $tech_name = $stmtT->fetchColumn();
            }
            
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$order_id, "Técnico asignado: " . $tech_name, $_SESSION['user_id'], get_local_datetime()]);
            
            header("Location: index.php?msg=assigned");
            exit;
        } catch (Exception $e) {
            // Error handling
        }
    }
}

// Fetch Technicians for Dropdown
$technicians = [];
try {
    $stmtTech = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active'");
    $technicians = $stmtTech->fetchAll();
} catch (Exception $e) {
    // Handle error quietly
}

// Fetch Regular Service Orders (Not Warranty)
$sql = "
    SELECT 
        so.id, so.status, so.problem_reported, so.entry_date, so.invoice_number, so.assigned_tech_id,
        c.name as client_name, c.phone,
        e.brand, e.model, e.serial_number, e.type,
        tech.username as tech_name
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users tech ON so.assigned_tech_id = tech.id
    WHERE so.service_type = 'service'
";

// Filter for Technicians
if ($_SESSION['role_id'] == 3) {
    $sql .= " AND so.assigned_tech_id = " . intval($_SESSION['user_id']);
}

$sql .= " ORDER BY 
    CASE WHEN so.status = 'delivered' THEN 1 ELSE 0 END ASC,
    so.entry_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$services = $stmt->fetchAll();

// Separate Active and Delivered Services
$activeServices = [];
$deliveredServices = [];

foreach ($services as $service) {
    if (trim(strtolower($service['status'])) == 'delivered') {
        $deliveredServices[] = $service;
    } else {
        $activeServices[] = $service;
    }
}

$page_title = 'Gestión de Servicios';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Servicios y Reparaciones</h1>
        <p class="text-muted">Gestión de órdenes de servicio estándar.</p>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='assigned'): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem; padding: 1rem; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2);">
            Técnico asignado correctamente.
        </div>
    <?php endif; ?>

    <!-- ACTIVE SERVICES TABLE -->
    <div class="card" style="margin-bottom: 2rem;">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Servicios</h3>
            <div style="display: flex; gap: 1rem;">
                <div class="input-group" style="width: 300px;">
                     <input type="text" id="searchInput" class="form-control" placeholder="Buscar por cliente, equipo, serie...">
                     <i class="ph ph-magnifying-glass input-icon"></i>
                </div>

            </div>
        </div>
        <div class="table-container">
            <table id="activeTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
                            Factura <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="1">
                            Fecha <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="2">
                            Equipo <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="3">
                            No. Serie <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th>Falla Reportada</th>
                        <th class="sortable" data-column="5">
                            Técnico <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="6">
                            Estado <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($activeServices) > 0): ?>
                        <?php foreach($activeServices as $item): ?>
                        <tr class="clickable-row" style="cursor: pointer;" onclick="window.location.href='view.php?id=<?php echo $item['id']; ?>'">
                            <td>
                                <?php if($item['invoice_number']): ?>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);"><?php echo htmlspecialchars($item['invoice_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($item['entry_date'])); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="badge"><?php echo htmlspecialchars($item['type']); ?></span>
                                    <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($item['serial_number']); ?></span>
                            </td>
                            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['problem_reported']); ?>">
                                <?php echo htmlspecialchars($item['problem_reported']); ?>
                            </td>
                            <!-- Assigned Technician -->
                            <td onclick="event.stopPropagation();">
                                <?php if($item['tech_name']): ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.5rem; background: var(--bg-hover); border-radius: 6px; font-size: 0.85rem;">
                                            <i class="ph ph-user-circle"></i> <?php echo htmlspecialchars($item['tech_name']); ?>
                                        </span>
                                        <?php if(true): ?>
                                            <button type="button" class="btn-icon" style="padding: 2px;" title="Cambiar Técnico" onclick="openAssignModal('<?php echo $item['id']; ?>', '<?php echo $item['assigned_tech_id']; ?>')">
                                                <i class="ph ph-pencil-simple" style="font-size: 0.9rem;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php if(true): ?>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="openAssignModal('<?php echo $item['id']; ?>', '<?php echo $item['assigned_tech_id']; ?>')">
                                            <i class="ph ph-user-plus"></i> Asignar
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">-</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $statusColors = [
                                        'received' => 'blue',
                                        'diagnosing' => 'yellow',
                                        'pending_approval' => 'orange',
                                        'in_repair' => 'purple',
                                        'ready' => 'green',
                                        'delivered' => 'gray',
                                        'cancelled' => 'red'
                                    ];
                                    $color = $statusColors[$item['status']] ?? 'gray';
                                    
                                    $statusLabels = [
                                        'received' => 'Recibido',
                                        'diagnosing' => 'En Revisión',
                                        'pending_approval' => 'En Espera',
                                        'in_repair' => 'En Proceso',
                                        'ready' => 'Listo',
                                        'delivered' => 'Entregado',
                                        'cancelled' => 'Cancelado'
                                    ];
                                    $label = $statusLabels[$item['status']] ?? $item['status'];
                                ?>
                                <span class="status-badge status-<?php echo $color; ?>">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-wrench" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay servicios activos</h3>
                                <p class="text-muted">Los servicios y reparaciones en curso aparecerán aquí.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DELIVERED HISTORY TABLE -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Historial de Entregados</h3>
            <div class="input-group" style="width: 250px;">
                 <input type="text" id="searchHistoryInput" class="form-control" placeholder="Buscar en historial..." style="font-size: 0.9rem;">
                 <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
                            Factura <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="1">
                            Fecha <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="2">
                            Equipo <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="3">
                            No. Serie <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th>Falla Reportada</th>
                        <th class="sortable" data-column="5">
                            Técnico <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="6">
                            Estado <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($deliveredServices) > 0): ?>
                        <?php foreach($deliveredServices as $item): ?>
                        <tr class="clickable-row" style="opacity: 0.7; cursor: pointer;" onclick="window.location.href='view.php?id=<?php echo $item['id']; ?>'">
                            <td>
                                <?php if($item['invoice_number']): ?>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);"><?php echo htmlspecialchars($item['invoice_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($item['entry_date'])); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="badge"><?php echo htmlspecialchars($item['type']); ?></span>
                                    <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($item['serial_number']); ?></span>
                            </td>
                            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['problem_reported']); ?>">
                                <?php echo htmlspecialchars($item['problem_reported']); ?>
                            </td>
                            <td>
                                <?php if($item['tech_name']): ?>
                                    <span class="text-sm"><?php echo htmlspecialchars($item['tech_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted text-sm">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-gray">Entregado</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 2rem;">
                                <p class="text-muted">No hay historial de equipos entregados.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Tech Modal -->
<div id="assignModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); padding: 1.5rem; border-radius: 12px; width: 400px; max-width: 90%; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Asignar Técnico</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="assign_tech">
            <input type="hidden" name="order_id" id="assignOrderId">
            
            <div class="form-group">
                <label>Seleccionar Técnico:</label>
                <select name="tech_id" id="assignTechId" class="form-control" style="width: 100%;">
                    <option value="">-- Sin Asignar --</option>
                    <?php foreach($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
th {
    white-space: nowrap;
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

.clickable-row {
    transition: background-color 0.2s;
}
.clickable-row:hover {
    background-color: var(--bg-hover);
}
.clickable-row:hover td {
    color: var(--text-primary);
}

/* Fix Select Arrow Positioning */
select.form-control {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px 12px;
    padding-right: 2.5rem;
}
</style>

<script>
// Search Functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('activeTable');
    if(table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
                // Skip "No data" rows
                if (row.cells.length > 1) {
                if(text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
                }
        });
    }
});

// History Search Functionality
document.getElementById('searchHistoryInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('historyTable');
    if(table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
             // Skip "No data" rows
             if (row.cells.length > 1) {
                if(text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
             }
        });
    }
});

// Modern Sorting Functionality for Both Tables
function setupTableSorting(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const sortableHeaders = table.querySelectorAll('.sortable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    let originalRows = rows.filter(row => !row.querySelector('td[colspan]'));
    let currentSortColumn = null;
    let currentSortDirection = null;
    
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
        
        originalRows = sortedRows;
        sortedRows.forEach(row => tbody.appendChild(row));
        
        sortableHeaders.forEach(header => {
            header.classList.remove('asc', 'desc');
        });
        
        const activeHeader = table.querySelector(`.sortable[data-column="${columnIndex}"]`);
        if (activeHeader) {
            activeHeader.classList.add(direction);
        }
    }
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const columnIndex = parseInt(this.dataset.column);
            
            let direction = 'asc';
            if (currentSortColumn === columnIndex) {
                if (currentSortDirection === 'asc') {
                    direction = 'desc';
                } else if (currentSortDirection === 'desc') {
                    direction = null;
                    currentSortColumn = null;
                    currentSortDirection = null;
                    sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
                    return;
                }
            }
            
            currentSortColumn = columnIndex;
            currentSortDirection = direction;
            sortTable(columnIndex, direction);
        });
    });
}

// Initialize sorting for both tables
setupTableSorting('activeTable');
setupTableSorting('historyTable');

function openAssignModal(orderId, currentTechId) {
    document.getElementById('assignOrderId').value = orderId;
    document.getElementById('assignTechId').value = currentTechId || '';
    document.getElementById('assignModal').style.display = 'flex';
}

function closeAssignModal() {
    document.getElementById('assignModal').style.display = 'none';
}

// Close on outside click
document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAssignModal();
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>
