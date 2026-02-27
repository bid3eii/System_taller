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

$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$where = "WHERE so.service_type = 'warranty' AND w.product_code IS NOT NULL AND w.product_code != ''";
$params = [];

if (!empty($search)) {
    $where .= " AND (e.serial_number LIKE ? OR w.product_code LIKE ? OR c.name LIKE ? OR w.sales_invoice_number LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
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
        w.master_entry_invoice, w.master_entry_date, w.end_date, w.status,
        c.name as client_name,
        e.brand, e.model, e.serial_number
    FROM service_orders so
    JOIN warranties w ON w.service_order_id = so.id
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    $where
    ORDER BY so.created_at DESC
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

    <!-- Search Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
        <form method="GET" action="database.php" style="display: flex; gap: 1rem; align-items: center;">
            <div class="input-group" style="flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="Buscar por Serie, Código, Cliente o Factura..." value="<?php echo htmlspecialchars($search); ?>">
                <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
            <button type="submit" class="btn btn-secondary">Buscar</button>
            <?php if(!empty($search)): ?>
                <a href="database.php" class="btn btn-sm btn-ghost" title="Limpiar búsqueda">&times; Limpiar</a>
            <?php endif; ?>
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
                        ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td><span class="badge"><?php echo htmlspecialchars($r['product_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($r['client_name']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></strong>
                                    <div class="text-xs text-muted"><?php echo htmlspecialchars($r['serial_number']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($r['sales_invoice_number']); ?></td>
                                <td>
                                    <?php echo $r['end_date'] ? date('d/m/Y', strtotime($r['end_date'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo strtoupper($statusLabel); ?></span>
                                </td>
                                <td>
                                    <button class="btn-icon" onclick='openModal(<?php echo json_encode($r); ?>)'>
                                        <i class="ph ph-eye"></i>
                                    </button>
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
                    <a href="?page=1&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">«</a>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">›</a>
                    <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-secondary">»</a>
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
</script>

<?php require_once '../../includes/footer.php'; ?>
