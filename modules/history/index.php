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

// Fetch All Delivered Orders (Service & Warranty)
$stmt = $pdo->prepare("
    SELECT 
        so.id, so.status, so.final_cost, so.exit_date, so.invoice_number, so.service_type, so.problem_reported,
        c.name as client_name, 
        e.brand, e.model, e.serial_number, e.type,
        u.username as delivered_by
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.authorized_by_user_id = u.id
    WHERE so.status = 'delivered'
    ORDER BY so.exit_date DESC
    LIMIT 100
");
$stmt->execute();
$history = $stmt->fetchAll();

$page_title = 'Historial General';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Historial de Solicitudes</h1>
        <p class="text-muted">Registro completo de servicios y garantías entregados.</p>
    </div>

    <!-- History Table -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Entregas Realizadas</h3>
            <div class="input-group" style="width: 300px;">
                 <input type="text" id="searchInput" class="form-control" placeholder="Buscar por cliente, equipo, serie...">
                 <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable(0)">Orden # <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(1)">Factura <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(2)">Tipo <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(3)">Cliente <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(4)">Equipo <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(5)">No. Serie <i class="ph ph-caret-up-down"></i></th>
                        <th class="sortable" onclick="sortTable(6)">Fecha Salida <i class="ph ph-caret-up-down"></i></th>
                        <th>Entregado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($history) > 0): ?>
                        <?php foreach($history as $item): ?>
                        <?php 
                            $viewLink = ($item['service_type'] === 'service') ? '../services/view.php' : '../warranties/view.php';
                            $targetUrl = $viewLink . '?id=' . $item['id'] . '&view_source=history';
                        ?>
                        <tr onclick="window.location.href='<?php echo $targetUrl; ?>'" class="clickable-row">
                            <td><strong>#<?php echo str_pad($item['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <?php if($item['invoice_number']): ?>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);"><?php echo htmlspecialchars($item['invoice_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
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
                                </div>
                            </td>
                            <td>
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($item['serial_number']); ?></span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($item['exit_date'])); ?></td>
                            <td>
                                <?php if($item['delivered_by']): ?>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 24px; height: 24px; background: var(--primary-500); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
                                            <?php echo strtoupper(substr($item['delivered_by'], 0, 1)); ?>
                                        </div>
                                        <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($item['delivered_by']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-clock-counter-clockwise" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay historial disponible</h3>
                                <p class="text-muted">Los servicios y garantías entregados aparecerán aquí.</p>
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
</style>

<script>
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
</script>

<?php
require_once '../../includes/footer.php';
?>
