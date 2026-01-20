<?php
// modules/equipment/exit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('equipos', $pdo)) {
    die("Acceso denegado.");
}

// Handle Delivery Action -> Logic moved to deliver_confirm.php

// Fetch "Ready" Orders
// Status 'ready' implies repairs are done and costs calculated.
$stmt = $pdo->prepare("
    SELECT 
        so.id, so.status, so.final_cost, so.invoice_number,
        c.name as client_name, 
        e.brand, e.model, e.serial_number, e.type
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    WHERE so.status IN ('ready') 
    ORDER BY so.created_at DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();

$page_title = 'Salida de Equipos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php'; // Navbar
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Salida / Entrega</h1>
        <p class="text-muted">Equipos listos para ser entregados al cliente.</p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            Equipo marcado como entregado correctamente.
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Equipos Listos para Entrega</h3>
            <div class="input-group" style="width: 300px;">
                 <input type="text" id="searchInput" class="form-control" placeholder="Buscar por cliente, equipo, serie...">
                 <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table id="readyTable">
                <thead>
                    <tr>
                        <th>Orden #</th>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>No. Serie</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <?php if($order['invoice_number']): ?>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);"><?php echo htmlspecialchars($order['invoice_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span style="padding: 0.2rem 0.5rem; background: var(--bg-hover); border-radius: 4px; font-size: 0.8rem;"><?php echo htmlspecialchars($order['type']); ?></span>
                                    <span><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($order['serial_number']); ?></span>
                            </td>
                            <td style="vertical-align: middle;">
                                <span style="color: var(--success); font-weight: 600; background: rgba(16, 185, 129, 0.1); padding: 0.25rem 0.75rem; border-radius: 20px; white-space: nowrap;">
                                    Listo
                                </span>
                            </td>
                            <td style="vertical-align: middle;">
                                <a href="deliver_confirm.php?id=<?php echo $order['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="ph ph-check"></i> Entregar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-check-circle" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay equipos pendientes de entrega</h3>
                                <p class="text-muted">Todos los equipos reparados han sido entregados o no hay reparaciones terminadas.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Delivered History Section -->
    <div style="margin-top: 3rem;">
        <div class="card">
            <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Historial de Salidas</h3>
                <div class="input-group" style="width: 300px;">
                     <input type="text" id="searchHistoryInput" class="form-control" placeholder="Buscar en historial...">
                     <i class="ph ph-magnifying-glass input-icon"></i>
                </div>
            </div>
            <div class="table-container">
                <table id="historyTable">
                    <thead>
                        <tr>
                            <th>Orden #</th>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>Equipo</th>
                            <th>No. Serie</th>
                            <th>Fecha Salida</th>
                            <th>Entregado Por</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Fetch History (Delivered Orders)
                        $stmtDelivered = $pdo->prepare("
                            SELECT 
                                so.id, so.status, so.final_cost, so.exit_date, so.invoice_number,
                                c.name as client_name, 
                                e.brand, e.model, e.serial_number, e.type,
                                u.username as delivered_by
                            FROM service_orders so
                            JOIN clients c ON so.client_id = c.id
                            JOIN equipments e ON so.equipment_id = e.id
                            LEFT JOIN users u ON so.authorized_by_user_id = u.id
                            WHERE so.status = 'delivered'
                            ORDER BY so.exit_date DESC
                            LIMIT 20
                        ");
                        $stmtDelivered->execute();
                        $deliveredOrders = $stmtDelivered->fetchAll();
                        ?>

                        <?php if(count($deliveredOrders) > 0): ?>
                            <?php foreach($deliveredOrders as $dItem): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($dItem['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <?php if($dItem['invoice_number']): ?>
                                        <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);"><?php echo htmlspecialchars($dItem['invoice_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($dItem['client_name']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <span style="padding: 0.2rem 0.5rem; background: var(--bg-hover); border-radius: 4px; font-size: 0.8rem;"><?php echo htmlspecialchars($dItem['type']); ?></span>
                                        <span><?php echo htmlspecialchars($dItem['brand'] . ' ' . $dItem['model']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-sm font-medium"><?php echo htmlspecialchars($dItem['serial_number']); ?></span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($dItem['exit_date'])); ?></td>
                                <td>
                                    <?php if($dItem['delivered_by']): ?>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 24px; height: 24px; background: var(--primary-500); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
                                                <?php echo strtoupper(substr($dItem['delivered_by'], 0, 1)); ?>
                                            </div>
                                            <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($dItem['delivered_by']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span style="color: var(--text-secondary); background: var(--bg-hover); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; white-space: nowrap;">
                                        Entregado
                                    </span>
                                </td>
                                <td>
                                    <a href="print_delivery.php?id=<?php echo $dItem['id']; ?>" class="btn-icon" title="Imprimir Comprobante" target="_blank">
                                        <i class="ph ph-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                                    No hay historial de entregas recientes.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<script>
function setupTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    if(!input) return;
    
    input.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById(tableId);
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if(text.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Initialize searches
setupTableSearch('searchInput', 'readyTable');
setupTableSearch('searchHistoryInput', 'historyTable');
</script>

<?php
require_once '../../includes/footer.php';
?>
