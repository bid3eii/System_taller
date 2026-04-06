<?php
// modules/warranties/database.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Registros de Bodega';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Pagination and Search
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'stock';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

$where = "WHERE so.service_type = 'warranty'";
$params = [];

if ($tab === 'stock') {
    $where .= " AND c.name = 'Bodega - Inventario'";
} else {
    $where .= " AND c.name != 'Bodega - Inventario'";
}

if (!empty($search)) {
    $where .= " AND (e.serial_number LIKE ? OR w.product_code LIKE ? OR c.name LIKE ? OR w.sales_invoice_number LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}

// Get Total Count
$countSql = "
    SELECT COUNT(*) 
    FROM service_orders so
    JOIN warranties w ON w.service_order_id = so.id
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    $where
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get Data
$sql = "
    SELECT 
        so.id, so.entry_date, 
        w.product_code, w.sales_invoice_number, w.supplier_name,
        w.master_entry_invoice, w.master_entry_date, w.end_date, w.status, w.duration_months,
        c.name as client_name, c.id as client_id,
        e.id as equipment_id, e.brand, e.model, e.serial_number
    FROM service_orders so
    JOIN warranties w ON w.service_order_id = so.id
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    $where
    ORDER BY " . ($tab === 'sold' ? "w.end_date" : "so.created_at") . " DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1><i class="ph ph-database" style="color: var(--primary-500);"></i> Registros de Bodega</h1>
            <p class="text-muted">Consulta el historial completo de hardware registrado en bodega.</p>
        </div>
        <a href="../equipment/entry.php?type=warranty" class="btn btn-primary">
            <i class="ph ph-plus-circle"></i> Nuevo Registro
        </a>
    </div>

    <!-- Tabs -->
    <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem;">
        <a href="?tab=stock&search=<?php echo urlencode($search); ?>" style="padding: 1rem 2rem; border-bottom: 3px solid <?php echo $tab === 'stock' ? 'var(--primary-500)' : 'transparent'; ?>; color: <?php echo $tab === 'stock' ? 'var(--primary-500)' : 'var(--text-muted)'; ?>; text-decoration: none; font-weight: 600;">
            <i class="ph ph-package"></i> En Stock
        </a>
        <a href="?tab=sold&search=<?php echo urlencode($search); ?>" style="padding: 1rem 2rem; border-bottom: 3px solid <?php echo $tab === 'sold' ? 'var(--primary-500)' : 'transparent'; ?>; color: <?php echo $tab === 'sold' ? 'var(--primary-500)' : 'var(--text-muted)'; ?>; text-decoration: none; font-weight: 600;">
            <i class="ph ph-shopping-cart"></i> Vendidos / Garantías
        </a>
    </div>

    <!-- Search Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
        <form method="GET" action="database.php" style="display: flex; gap: 1rem; align-items: center;">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <div class="input-group" style="flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="Buscar por Serie, Código, Cliente o Factura..." value="<?php echo htmlspecialchars($search); ?>">
                <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
            <button type="submit" class="btn btn-secondary">Buscar</button>
        </form>
    </div>

    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cód. Producto</th>
                        <th>Cliente</th>
                        <th>Equipo / Serie</th>
                        <th>Factura Venta</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $r): 
                            $isExpired = ($r['end_date'] && strtotime($r['end_date']) < time());
                            $statusLabel = $isExpired ? 'Expirada' : (ucfirst($r['status'] == 'active' ? 'Vigente' : $r['status']));
                            $statusClass = $isExpired ? 'status-red' : ($r['status'] == 'active' ? 'status-green' : 'status-gray');

                            // Calculate visual remaining time and Health Bar
                            $warrantyBadge = '';
                            $healthBar = '';
                            if ($r['end_date'] && !$isExpired) {
                                $end = new DateTime($r['end_date']);
                                $now = new DateTime();
                                $diff = $now->diff($end);
                                $daysLeft = $diff->days;
                                
                                // Best effort to guess total duration
                                $totalDays = 365; // default 12 months
                                if (!empty($r['duration_months']) && $r['duration_months'] > 0) {
                                    $totalDays = $r['duration_months'] * 30;
                                } elseif (!empty($r['master_entry_date'])) {
                                    $st = new DateTime($r['master_entry_date']);
                                    $tDiff = $st->diff($end);
                                    if ($tDiff->days > 0) $totalDays = $tDiff->days;
                                }
                                
                                $percent = min(100, max(0, ($daysLeft / max(1, $totalDays)) * 100));
                                if ($percent > 75) {
                                    $barColor = '#10b981'; // Verde
                                } elseif ($percent > 50) {
                                    $barColor = '#eab308'; // Amarillo
                                } elseif ($percent > 25) {
                                    $barColor = '#f97316'; // Naranja
                                } else {
                                    $barColor = '#ef4444'; // Rojo
                                }

                                if ($daysLeft > 30) {
                                    $months = floor($daysLeft / 30);
                                    $detailText = 'Restan ~' . $months . ' meses';
                                } else {
                                    $detailText = 'Restan ' . $daysLeft . ' días';
                                }
                                
                                $percentRound = round($percent);
                                $healthBar = '
                                <div style="display: inline-flex; align-items: center; border: 2px solid '.$barColor.'; border-radius: 20px; height: 26px; width: 130px; background: transparent;" title="' . $detailText . ' de ' . round($totalDays/30) . ' meses totales">
                                    <div style="background: '.$barColor.'; color: #fff; border-radius: 16px 0 0 16px; padding: 0 8px; font-size: 0.75rem; font-weight: 700; height: 100%; display: flex; align-items: center; justify-content: center; min-width: 48px;">
                                        ' . $percentRound . '%
                                    </div>
                                    <div style="flex-grow: 1; padding: 3px 4px 3px 4px; height: 100%; display: flex; align-items: center; box-sizing: border-box;">
                                        <div style="height: 100%; width: '.$percent.'%; background: '.$barColor.'; border-radius: 10px; transition: 0.3s; min-width: 4px;"></div>
                                    </div>
                                </div>';
                            } elseif ($isExpired) {
                                $healthBar = '
                                <div style="display: inline-flex; align-items: center; border: 2px solid var(--danger); border-radius: 20px; height: 26px; width: 130px; background: transparent;" title="La garantía ha expirado">
                                    <div style="background: var(--danger); color: #fff; border-radius: 16px 0 0 16px; padding: 0 8px; font-size: 0.70rem; font-weight: 700; height: 100%; display: flex; align-items: center; justify-content: center; min-width: 80px;">
                                        EXPIRADA
                                    </div>
                                    <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center;">
                                         <i class="ph ph-warning-circle" style="color: var(--danger); font-size: 1.1rem;"></i>
                                    </div>
                                </div>';
                            } else {
                                $healthBar = '<span class="status-badge status-gray" style="border: 2px solid var(--text-muted); background: transparent;">N/A</span>';
                            }
                        ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td>
                                    <?php if (empty(trim($r['product_code']))): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">⚠️ Sin Código</span>
                                    <?php else: ?>
                                        <span class="badge"><?php echo htmlspecialchars($r['product_code']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tab === 'stock'): ?>
                                        <span style="color: var(--text-muted); font-style: italic;">Sin Asignar</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($r['client_name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></strong>
                                    <div class="text-xs text-muted"><?php echo htmlspecialchars($r['serial_number']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($r['sales_invoice_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php if (empty($r['end_date'])): ?>
                                        <span style="color: var(--text-muted);">N/A</span>
                                    <?php else: ?>
                                        <strong style="color: var(--text-color); font-size: 0.9rem;"><?php echo date('d/m/Y', strtotime($r['end_date'])); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tab === 'stock'): ?>
                                        <span class="status-badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">EN BODEGA</span>
                                    <?php else: ?>
                                        <?php echo $healthBar; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: flex-start;">
                                        <button class="btn-icon" onclick='openModal(<?php echo json_encode($r); ?>)' title="Ver Detalles">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                        <?php if ($tab === 'stock'): ?>
                                            <button class="btn-icon" onclick='openAssignModal(<?php echo json_encode($r); ?>)' title="Client Asignar / Vender" style="color: #10b981;">
                                                <i class="ph ph-shopping-cart"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role_name'] === 'SuperAdmin'): ?>
                                            <button class="btn-icon" onclick='openEditModal(<?php echo json_encode($r); ?>)' title="Editar Registro (SuperAdmin)" style="color: #f59e0b;">
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                <i class="ph ph-warning-circle" style="font-size: 2rem; display: block; margin-bottom: 1rem;"></i>
                                No se encontraron registros.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem; border-top: 1px solid var(--border-color);">
                <?php 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                if ($page > 1): ?>
                    <a href="?page=1&tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">«</a>
                    <a href="?page=<?php echo $page - 1; ?>&tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">›</a>
                    <a href="?page=<?php echo $totalPages; ?>&tab=<?php echo urlencode($tab); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">»</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Simple Detail Modal (Reusing existing logic/style if possible) -->
<div id="detailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 90%; max-width: 800px; padding: 2rem; position: relative; border: 1px solid var(--border-color);">
        <button onclick="document.getElementById('detailModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; margin-bottom: 2rem;"><i class="ph ph-info" style="color: var(--primary-500);"></i> Detalles del Registro</h3>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
            <div>
                <p class="text-xs text-muted mb-1">CÓDIGO PRODUCTO</p>
                <p id="m_code" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">NÚMERO DE SERIE</p>
                <p id="m_serial" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">CLIENTE</p>
                <p id="m_client" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">EQUIPO</p>
                <p id="m_equipment" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">FACTURA VENTA</p>
                <p id="m_invoice" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">FECHA VENCIMIENTO</p>
                <p id="m_end" class="font-bold">-</p>
            </div>
            <div>
                <p class="text-xs text-muted mb-1">PROVEEDOR</p>
                <p id="m_supplier" class="font-bold">-</p>
            </div>
             <div>
                <p class="text-xs text-muted mb-1">FACT. INGRESO MASTER</p>
                <p id="m_master_inv" class="font-bold">-</p>
            </div>
        </div>
        
        <div style="margin-top: 2.5rem; text-align: right;">
            <button onclick="document.getElementById('detailModal').style.display='none'" class="btn btn-secondary">Cerrar</button>
        </div>
    </div>
</div>

<!-- Assign / Sell Modal -->
<div id="assignModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 90%; max-width: 600px; padding: 2rem; position: relative; border: 1px solid var(--border-color);">
        <button onclick="document.getElementById('assignModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; margin-bottom: 2rem;"><i class="ph ph-shopping-cart" style="color: var(--primary-500);"></i> Vender / Asignar Equipo</h3>
        
        <form id="assignForm" method="POST" action="assign_client.php">
            <input type="hidden" name="service_order_id" id="assign_order_id">
            <input type="hidden" name="equipment_id" id="assign_equipment_id">
            
            <div style="margin-bottom: 1.5rem;">
                <p class="text-sm text-muted mb-1">EQUIPO A VENDER:</p>
                <p id="assign_equipment_name" class="font-bold" style="font-size: 1.1rem; color: var(--primary-400);">-</p>
                <div class="text-xs text-muted" id="assign_equipment_sn"></div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Cliente Final *</label>
                <input type="text" name="assign_client_name" class="form-control" placeholder="Nombre completo del cliente" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Cédula/RUC</label>
                    <input type="text" name="assign_client_tax_id" class="form-control" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="assign_client_phone" class="form-control" placeholder="Opcional">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">Factura de Venta *</label>
                    <input type="text" name="sales_invoice_number" class="form-control" required placeholder="Nº Factura">
                </div>
                <div class="form-group">
                    <label class="form-label">Meses de Garantía *</label>
                    <input type="number" name="warranty_months" class="form-control" required min="1" value="12">
                </div>
            </div>

            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('assignModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="ph ph-check"></i> Confirmar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Entry Modal (SuperAdmin Only) -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 90%; max-width: 650px; padding: 2rem; position: relative; border: 1px solid var(--border-color);">
        <button onclick="document.getElementById('editModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; margin-bottom: 2rem;"><i class="ph ph-pencil-simple" style="color: #f59e0b;"></i> Editar Registro <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Acceso SuperAdmin)</span></h3>
        
        <form action="update_entry.php" method="POST">
            <input type="hidden" name="service_order_id" id="edit_order_id">
            <input type="hidden" name="equipment_id" id="edit_equipment_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Código Producto</label>
                    <input type="text" name="product_code" id="edit_product_code" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" name="brand" id="edit_brand" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" name="model" id="edit_model" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" name="serial_number" id="edit_serial_number" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Factura Venta</label>
                    <input type="text" name="sales_invoice_number" id="edit_sales_invoice" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Meses de Garantía</label>
                    <input type="number" name="warranty_months" id="edit_warranty_months" class="form-control">
                </div>
            </div>

            <div style="margin-top: 2.5rem; text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background: #f59e0b; border-color: #f59e0b; color: #000;">
                    <i class="ph ph-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(data) {
    document.getElementById('m_code').innerText = data.product_code;
    document.getElementById('m_serial').innerText = data.serial_number;
    document.getElementById('m_client').innerText = data.client_name;
    document.getElementById('m_equipment').innerText = data.brand + ' ' + data.model;
    document.getElementById('m_invoice').innerText = data.sales_invoice_number;
    document.getElementById('m_end').innerText = data.end_date ? data.end_date : 'N/A';
    document.getElementById('m_supplier').innerText = data.supplier_name || 'N/A';
    document.getElementById('m_master_inv').innerText = data.master_entry_invoice || 'N/A';
    
    document.getElementById('detailModal').style.display = 'flex';
}

function openAssignModal(data) {
    document.getElementById('assign_order_id').value = data.id;
    document.getElementById('assign_equipment_id').value = data.equipment_id;
    document.getElementById('assign_equipment_name').innerText = data.brand + ' ' + data.model;
    document.getElementById('assign_equipment_sn').innerText = 'S/N: ' + data.serial_number;
    
    document.getElementById('assignForm').reset();
    document.getElementById('assignModal').style.display = 'flex';
}

function openEditModal(data) {
    document.getElementById('edit_order_id').value = data.id;
    document.getElementById('edit_equipment_id').value = data.equipment_id;
    document.getElementById('edit_product_code').value = data.product_code || '';
    document.getElementById('edit_brand').value = data.brand || '';
    document.getElementById('edit_model').value = data.model || '';
    document.getElementById('edit_serial_number').value = data.serial_number || '';
    document.getElementById('edit_sales_invoice').value = data.sales_invoice_number || '';
    document.getElementById('edit_warranty_months').value = data.duration_months || 0;
    
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
