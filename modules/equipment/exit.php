<?php
// modules/equipment/exit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('equipment', $pdo) && !can_access_module('equipment_exit', $pdo)) {
    die("Acceso denegado.");
}

// Handle Delivery Action -> Logic moved to deliver_confirm.php

// Fetch "Ready" Orders
// Status 'ready' implies repairs are done and costs calculated.
$stmt = $pdo->prepare("
    SELECT 
        so.id, so.status, so.final_cost, so.invoice_number,
        c.id as client_id,
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

// Group orders by client for multi-equipment delivery detection
$clientGroups = [];
foreach ($orders as $order) {
    $clientId = $order['client_id'];
    if (!isset($clientGroups[$clientId])) {
        $clientGroups[$clientId] = [];
    }
    $clientGroups[$clientId][] = $order['id'];
}

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

    <?php 
    // Show multi-equipment print options
    $multiClientGroups = array_filter($clientGroups, function($ids) { return count($ids) > 1; });
    if (!empty($multiClientGroups)): 
    ?>
        <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem;">
                <div style="background: rgba(16, 185, 129, 0.15); padding: 0.75rem; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="ph-fill ph-stack" style="font-size: 1.75rem; color: #10b981;"></i>
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #10b981; font-size: 1.1rem; font-weight: 700;">Entregas Múltiples Disponibles</h4>
                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5;">Haz clic en un cliente para imprimir todos sus equipos en una sola hoja de entrega</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                <?php 
                foreach ($multiClientGroups as $clientId => $orderIds):
                    // Get client name and equipment details
                    $clientName = 'Cliente Desconocido';
                    $equipmentList = [];
                    
                    foreach ($orders as $order) {
                        if ($order['client_id'] == $clientId) {
                            $clientName = $order['client_name'];
                            $equipmentList[] = $order['brand'] . ' ' . $order['model'];
                        }
                    }
                    
                    $allIds = implode(',', $orderIds);
                    $equipmentCount = count($orderIds);
                ?>
                    <div style="display: block; padding: 1rem; background: white; border: 2px solid rgba(16, 185, 129, 0.2); border-radius: 10px; transition: all 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                        
                        <!-- Header -->
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0;">
                                <i class="ph-fill ph-user-circle" style="color: #10b981; font-size: 1.25rem; flex-shrink: 0;"></i>
                                <span style="font-weight: 600; font-size: 0.95rem; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($clientName); ?>">
                                    <?php echo htmlspecialchars($clientName); ?>
                                </span>
                            </div>
                            <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; white-space: nowrap;">
                                <?php echo $equipmentCount; ?> equipo<?php echo $equipmentCount > 1 ? 's' : ''; ?>
                            </span>
                        </div>
                        
                        <!-- Equipment List -->
                        <div style="margin-bottom: 0.75rem;">
                            <?php foreach (array_slice($equipmentList, 0, 3) as $index => $equipment): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; font-size: 0.85rem; color: #6b7280;">
                                    <i class="ph ph-laptop" style="color: #6b7280; font-size: 0.9rem;"></i>
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($equipment); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($equipmentList) > 3): ?>
                                <div style="font-size: 0.8rem; color: #9ca3af; font-style: italic; margin-top: 0.25rem;">
                                    +<?php echo count($equipmentList) - 3; ?> más...
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Button -->
                        <a href="deliver_confirm_multi.php?ids=<?php echo $allIds; ?>" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.6rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 6px; color: white; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s;"
                           onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 4px 8px rgba(16, 185, 129, 0.3)';"
                           onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                            <i class="ph-fill ph-check-circle"></i>
                            <span>Entregar Todos</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
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
                            <td><strong><?php echo get_order_number($order); ?></strong></td>
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
                                <td><strong><?php echo get_order_number($dItem); ?></strong></td>
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
                                    <a href="print_delivery.php?id=<?php echo $dItem['id']; ?>" class="btn-icon" title="Imprimir Comprobante">
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
