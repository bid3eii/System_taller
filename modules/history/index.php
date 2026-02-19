<?php
// modules/history/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

// Check permission
if (!can_access_module('history', $pdo)) {
    die("Acceso denegado.");
}

// 1. Get Filters
$filterStatus = isset($_GET['status']) ? clean($_GET['status']) : '';
$filterType = isset($_GET['type']) ? clean($_GET['type']) : '';

// 2. Build Query
$sql = "
    SELECT 
        so.id, so.status, so.final_cost, so.exit_date, so.invoice_number, so.service_type, so.problem_reported,
        c.name as client_name, 
        e.brand, e.model, e.serial_number, e.type,
        u.username as delivered_by
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.authorized_by_user_id = u.id
    WHERE (so.service_type != 'warranty' OR so.problem_reported != 'Garantía Registrada')
";

$params = [];

if (!empty($filterStatus)) {
    $sql .= " AND so.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterType)) {
    $sql .= " AND so.service_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY so.entry_date DESC LIMIT 200"; // Show recent 200 by default

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

$page_title = 'Historial General';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Historial de Solicitudes</h1>
        <p class="text-muted">Registro completo de servicios y garantías.</p>
    </div>

    <!-- History Table -->
    <!-- History Table -->
        
        <!-- Premium Filter Bar -->
        <div class="premium-filter-bar">
            <!-- Search -->
            <div class="search-input-wrapper">
                <i class="ph ph-magnifying-glass search-icon"></i>
                <input type="text" id="searchInput" class="premium-input" placeholder="Buscar por cliente, equipo, serie...">
            </div>

            <!-- Filter Status -->
            <div class="select-wrapper">
                <form id="filterFormStatus" method="GET" style="margin:0; width:100%;">
                    <?php if(!empty($filterType)) { echo '<input type="hidden" name="type" value="'.$filterType.'">'; } ?>
                    <select name="status" class="premium-select" onchange="this.form.submit()">
                        <option value="">Todos los Estados</option>
                        <option value="pending" <?php echo $filterStatus == 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                        <option value="diagnosing" <?php echo $filterStatus == 'diagnosing' ? 'selected' : ''; ?>>En Diagnóstico</option>
                        <option value="in_repair" <?php echo $filterStatus == 'in_repair' ? 'selected' : ''; ?>>En Reparación</option>
                        <option value="ready" <?php echo $filterStatus == 'ready' ? 'selected' : ''; ?>>Listos</option>
                        <option value="delivered" <?php echo $filterStatus == 'delivered' ? 'selected' : ''; ?>>Entregados</option>
                    </select>
                </form>
                <i class="ph ph-caret-down select-caret"></i>
            </div>

            <!-- Filter Type -->
            <div class="select-wrapper">
                 <form id="filterFormType" method="GET" style="margin:0; width:100%;">
                    <?php if(!empty($filterStatus)) { echo '<input type="hidden" name="status" value="'.$filterStatus.'">'; } ?>
                    <select name="type" class="premium-select" onchange="this.form.submit()">
                        <option value="">Todos los Tipos</option>
                        <option value="service" <?php echo $filterType == 'service' ? 'selected' : ''; ?>>Solo Servicios</option>
                        <option value="warranty" <?php echo $filterType == 'warranty' ? 'selected' : ''; ?>>Solo Garantías</option>
                    </select>
                </form>
                <i class="ph ph-caret-down select-caret"></i>
            </div>
            
            <!-- Export -->
            <button onclick="exportToExcel()" class="btn btn-primary" style="width: 100%;">
                <i class="ph ph-microsoft-excel-logo" style="margin-right: 0.5rem; font-size: 1.2rem;"></i> Exportar
            </button>
        </div>

        <div style="padding: 0 1rem 1rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Listado General</h3>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0)">Orden # <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(1)">Estado <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(2)">Tipo <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(3)">Cliente <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(4)">Equipo <i class="ph ph-caret-up-down"></i></th>
                        <th>Fecha Salida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($history) > 0): ?>
                        <?php foreach($history as $item): ?>
                        <?php 
                            $viewLink = ($item['service_type'] === 'service') ? '../services/view.php' : '../warranties/view.php';
                            $targetUrl = $viewLink . '?id=' . $item['id'] . '&view_source=history';
                            
                            // Status Badge Logic
                            $status = $item['status'];
                            $badgeClass = 'badge-gray'; // default
                            $statusLabel = 'Desconocido';
                            
                            switch($status) {
                                case 'pending': $badgeClass='badge-warning'; $statusLabel='Pendiente'; break;
                                case 'diagnosing': $badgeClass='badge-blue'; $statusLabel='Diagnóstico'; break;
                                case 'in_repair': $badgeClass='badge-purple'; $statusLabel='En Reparación'; break;
                                case 'ready': $badgeClass='badge-success'; $statusLabel='Listo'; break;
                                case 'delivered': $badgeClass='badge-green'; $statusLabel='Entregado'; break;
                                case 'cancelled': $badgeClass='badge-red'; $statusLabel='Cancelado'; break;
                            }
                        ?>
                        <tr onclick="window.location.href='<?php echo $targetUrl; ?>'" class="clickable-row">
                            <td><strong>#<?php echo str_pad($item['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span>
                            </td>
                            <td>
                                <?php if($item['service_type'] === 'warranty'): ?>
                                    <span class="badge badge-purple">Garantía</span>
                                <?php else: ?>
                                    <span class="badge badge-blue">Servicio</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['client_name']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span style="padding: 0.2rem 0.5rem; background: var(--bg-hover); border-radius: 4px; font-size: 0.8rem;"><?php echo htmlspecialchars($item['type']); ?></span>
                                    <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                    <span class="text-sm text-muted">(<?php echo htmlspecialchars($item['serial_number']); ?>)</span>
                                </div>
                            </td>
                            <td>
                                <?php if($item['exit_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($item['exit_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-magnifying-glass" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">Sin resultados</h3>
                                <p class="text-muted">Intenta cambiar los filtros de búsqueda.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover {
    background-color: var(--bg-hover);
}
.sortable i {
    font-size: 0.8rem;
    margin-left: 0.25rem;
    opacity: 0.5;
}
.clickable-row {
    cursor: pointer;
    transition: background-color 0.2s;
}
.clickable-row:hover {
    background-color: var(--bg-hover, #f8fafc);
}

/* PREMIUM UI SYSTEM */
.premium-filter-bar {
    display: grid;
    grid-template-columns: 1fr 200px 200px 150px; /* Search, Status, Type, Export */
    gap: 1rem;
    background: rgba(var(--bg-card-rgb), 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: 18px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 1.25rem;
    color: var(--text-muted);
    font-size: 1.2rem;
    pointer-events: none;
}

.premium-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 1rem 0.85rem 3.5rem;
    color: var(--text-main);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.premium-input:focus {
    background: rgba(var(--primary-rgb), 0.05);
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1);
    outline: none;
}

.select-wrapper {
    position: relative;
}

.premium-select {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 1.25rem;
    color: var(--text-main);
    font-size: 0.95rem;
    appearance: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.premium-select:focus {
    border-color: var(--primary);
    outline: none;
}

.premium-select option {
    background-color: #1a1c23; /* Dark background for options */
    color: white; /* White text for options */
}

/* Light mode overrides */
body.light-mode .premium-select {
    background-color: white;
    color: var(--slate-900);
    border-color: var(--slate-300);
}

body.light-mode .premium-select option {
    background-color: white;
    color: var(--slate-900);
}

.select-caret {
    position: absolute;
    right: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
}

.btn-primary {
    border-radius: 12px;
    padding: 0 1.5rem;
    font-weight: 600;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
// Export Function
function exportToExcel() {
    const search = document.getElementById('searchInput').value;
    const status = document.querySelector('select[name="status"]').value;
    const type = document.querySelector('select[name="type"]').value;
    
    // Build URL with all params
    let url = 'export.php?search=' + encodeURIComponent(search);
    if(status) url += '&status=' + encodeURIComponent(status);
    if(type) url += '&type=' + encodeURIComponent(type);
    
    window.location.href = url;
}

// Search Functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
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

// Sort Functionality
function sortTable(n) {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById("historyTable");
  switching = true;
  dir = "asc"; 
  
  while (switching) {
    switching = false;
    rows = table.rows;
    for (i = 1; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      x = rows[i].getElementsByTagName("TD")[n];
      y = rows[i + 1].getElementsByTagName("TD")[n];
      
      let xContent = x.innerText.toLowerCase();
      let yContent = y.innerText.toLowerCase();

      if (dir == "asc") {
        if (xContent > yContent) {
          shouldSwitch = true;
          break;
        }
      } else if (dir == "desc") {
        if (xContent < yContent) {
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      switchcount ++;      
    } else {
      if (switchcount == 0 && dir == "asc") {
        dir = "desc";
        switching = true;
      }
    }
  }
}

// Auto-Refresh Logic (10s)
setInterval(function() {
    // Only refresh if not valid interaction
    // Or just simple refresh of tbody
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTbody = doc.querySelector('#historyTable tbody');
            const currentTbody = document.querySelector('#historyTable tbody');
            
            if(newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
                
                // Re-apply search filter if user typed something
                const searchInput = document.getElementById('searchInput');
                if(searchInput && searchInput.value.length > 0) {
                    const event = new Event('keyup');
                    searchInput.dispatchEvent(event);
                }
            }
        })
        .catch(err => console.error('Error refreshing table:', err));
}, 10000); // 10 seconds
</script>

<?php
require_once '../../includes/footer.php';
?>
