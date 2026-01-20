<?php
// modules/warranties/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission (assuming 'garantias' or general access)
// For now, let's assume if they can access equipment, they might see this, or we add a new permission later.
// validation: can_access_module('garantias', $pdo) ??
// Let's check permissions table or just allow for now based on 'equipos' or 'admin'
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}


// Handle Technician Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_tech') {
    // Basic permission check: Only Admin (1) or Reception (4) or Supervisor (2) can likely assign
    if (in_array($_SESSION['role_id'], [1, 2, 4])) {
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
            
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id) VALUES (?, 'updated', ?, ?)");
            $stmtH->execute([$order_id, "Técnico asignado: " . $tech_name, $_SESSION['user_id']]);
            
            header("Location: index.php?msg=assigned");
            exit;
        } catch (Exception $e) {
            $error = "Error al asignar: " . $e->getMessage();
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

// Fetch Warranty Service Orders
// These are orders entered with service_type = 'warranty'
$sql = "
    SELECT 
        so.id, so.status, so.problem_reported, so.entry_date, so.invoice_number, so.assigned_tech_id,
        c.name as client_name, c.phone,
        e.brand, e.model, e.serial_number, e.type,
        tech.username as tech_name
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN warranties w ON so.id = w.service_order_id
    LEFT JOIN users tech ON so.assigned_tech_id = tech.id
    WHERE so.service_type = 'warranty'
      AND (w.product_code IS NULL OR w.product_code = '')
";

// Filter for Technicians: They only see their own assignments
if ($_SESSION['role_id'] == 3) {
    $sql .= " AND so.assigned_tech_id = " . intval($_SESSION['user_id']);
}

$sql .= " ORDER BY so.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$warranties = $stmt->fetchAll();

// Separate Active and Delivered Warranties
$activeWarranties = [];
$deliveredWarranties = [];

foreach ($warranties as $item) {
    if (trim(strtolower($item['status'])) == 'delivered') {
        $deliveredWarranties[] = $item;
    } else {
        $activeWarranties[] = $item;
    }
}

$page_title = 'Gestión de Garantías';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Garantías</h1>
        <p class="text-muted">Gestión de equipos ingresados por garantía.</p>
    </div>
    
    <?php if(isset($_GET['msg']) && $_GET['msg']=='assigned'): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem; padding: 1rem; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2);">
            Técnico asignado correctamente.
        </div>
    <?php endif; ?>

    <!-- ACTIVE WARRANTIES TABLE -->
    <div class="card" style="margin-bottom: 2rem;">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Garantías</h3>
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
                        <th class="sortable" onclick="sortTable(1, 'activeTable')">Factura <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(2, 'activeTable')">Fecha Ingreso <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(4, 'activeTable')">Equipo <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(5, 'activeTable')">No. Serie <i class="ph ph-caret-up-down"></i></th>
                        <th>Falla Reportada</th>
                        <!-- Technical Column -->
                        <th class="sortable" onclick="sortTable(7, 'activeTable')">Técnico <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(8, 'activeTable')">Estado <i class="ph ph-caret-up-down"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($activeWarranties) > 0): ?>
                        <?php foreach($activeWarranties as $item): ?>
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
                            <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['problem_reported']); ?>">
                                <?php echo htmlspecialchars($item['problem_reported']); ?>
                            </td>

                            <!-- Assigned Technician -->
                            <td onclick="event.stopPropagation();">
                                <?php if($item['tech_name']): ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.5rem; background: var(--bg-hover); border-radius: 6px; font-size: 0.85rem;">
                                            <i class="ph ph-user-circle"></i> <?php echo htmlspecialchars($item['tech_name']); ?>
                                        </span>
                                        <?php if(in_array($_SESSION['role_id'], [1, 2, 4])): ?>
                                            <button type="button" class="btn-icon" style="padding: 2px;" title="Cambiar Técnico" onclick="openAssignModal('<?php echo $item['id']; ?>', '<?php echo $item['assigned_tech_id']; ?>')">
                                                <i class="ph ph-pencil-simple" style="font-size: 0.9rem;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php if(in_array($_SESSION['role_id'], [1, 2, 4])): ?>
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
                                    
                                    // Translate status for display
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
                            <td colspan="10" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-shield-check" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay garantías activas</h3>
                                <p class="text-muted">Los equipos ingresados como "Garantía" aparecerán aquí.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DELIVERED WARRANTIES HISTORY TABLE -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Historial de Garantías Entregadas</h3>
            <div class="input-group" style="width: 250px;">
                 <input type="text" id="searchHistoryInput" class="form-control" placeholder="Buscar en historial..." style="font-size: 0.9rem;">
                 <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0, 'historyTable')">Factura <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(1, 'historyTable')">Fecha Ingreso <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(2, 'historyTable')">Equipo <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(3, 'historyTable')">No. Serie <i class="ph ph-caret-up-down"></i></th>
                        <th>Falla Reportada</th>
                        <th class="sortable" onclick="sortTable(7, 'historyTable')">Técnico <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(8, 'historyTable')">Estado <i class="ph ph-caret-up-down"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($deliveredWarranties) > 0): ?>
                        <?php foreach($deliveredWarranties as $item): ?>
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
                            <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($item['problem_reported']); ?>">
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
                                <p class="text-muted">No hay historial de garantías entregadas.</p>
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
.sortable {
    cursor: pointer;
    user-select: none;
}
.sortable:hover {
    background-color: var(--bg-hover);
}
.sortable I {
    font-size: 0.8rem;
    margin-left: 0.25rem;
    opacity: 0.5;
}
.clickable-row {
    transition: background-color 0.2s;
}
.clickable-row:hover {
    background-color: var(--bg-hover);
}
.clickable-row:hover td {
    color: var(--text-primary); /* Ensure text stays readable */
}
.clickable-row:hover td {
    color: var(--text-primary); /* Ensure text stays readable */
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
                // Skip rows that are message placeholders (colspan)
                if(row.cells.length > 1) {
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

// Sort Functionality
function sortTable(n, tableId) {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById(tableId);
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
