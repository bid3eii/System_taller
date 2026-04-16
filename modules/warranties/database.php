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
        w.master_entry_invoice, w.master_entry_date, w.end_date, w.status, w.duration_months, w.purchase_origin,
        c.name as client_name, c.id as client_id, c.tax_id, c.phone,
        e.id as equipment_id, e.brand, e.model, e.serial_number
    FROM service_orders so
    JOIN warranties w ON w.service_order_id = so.id
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    $where
    ORDER BY " . ($tab === 'sold' ? "DATE_SUB(w.end_date, INTERVAL w.duration_months MONTH) DESC, w.sales_invoice_number DESC, e.brand ASC" : "so.created_at DESC") . "
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

    <!-- Bulk Actions (Only in Stock) -->
    <?php if ($tab === 'stock'): ?>
    <div style="margin-bottom: 1rem; display: none;" id="bulk-action-container">
        <button class="btn btn-primary" onclick="openBulkAssignModal()" style="background: #10b981; border-color: #10b981;">
            <i class="ph ph-shopping-cart"></i> Vender Seleccionados (<span id="bulk-count">0</span>)
        </button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-container">
            <table style="<?php echo $tab === 'sold' ? 'table-layout: fixed; width: 100%;' : ''; ?>">
                <thead>
                    <tr>
                        <?php if ($tab === 'stock'): ?>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="selectAllItems" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>Cód. Producto</th>
                            <th>Cliente</th>
                            <th>Equipo / Serie</th>
                            <th>Origen</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        <?php else: ?>
                            <th style="width: 220px;">Cliente</th>
                            <th style="width: 100px;">Factura</th>
                            <th>Equipos</th>
                            <th style="width: 80px; text-align: center;">Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($records) > 0): ?>
                        <?php if ($tab === 'stock'): ?>
                            <?php foreach ($records as $r): ?>
                            <?php
                                $healthBar = '<span class="status-badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">EN BODEGA</span>';
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="text-align: center;">
                                    <input type="checkbox" class="bulk-cb" value="<?php echo $r['id']; ?>" data-json='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>'>
                                </td>
                                <td>
                                    <?php if (empty(trim($r['product_code']))): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">⚠️ Sin Código</span>
                                    <?php else: ?>
                                        <span class="badge"><?php echo htmlspecialchars($r['product_code']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span style="color: var(--text-muted); font-style: italic;">Sin Asignar</span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></strong>
                                    <div class="text-xs text-muted"><?php echo htmlspecialchars($r['serial_number']); ?></div>
                                </td>
                                <td>
                                    <?php if (($r['purchase_origin'] ?? 'local') === 'importada'): ?>
                                        <span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3);">
                                            <i class="ph-fill ph-airplane-tilt"></i> IMPORTADA
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">
                                            <i class="ph-fill ph-storefront"></i> LOCAL
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $healthBar; ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button class="btn-icon" data-json='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' onclick="openModalFromBtn(this)" title="Ver Detalles"><i class="ph ph-eye"></i></button>
                                        <button class="btn-icon" data-json='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' onclick="openAssignModalFromBtn(this)" title="Asignar / Vender" style="color: #10b981;"><i class="ph ph-shopping-cart"></i></button>
                                        <?php if ($_SESSION['role_name'] === 'SuperAdmin'): ?>
                                            <button class="btn-icon" data-json='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>' onclick="openEditModalFromBtn(this)" title="Editar Registro" style="color: #f59e0b;"><i class="ph ph-pencil-simple"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php
                            // === GROUPED VIEW FOR SOLD TAB ===
                            $grouped = [];
                            foreach ($records as $r) {
                                $key = ($r['sales_invoice_number'] ?: 'NO_INV_'.$r['id']) . '___' . $r['client_id'];
                                if (!isset($grouped[$key])) {
                                    $grouped[$key] = [
                                        'client_name' => $r['client_name'],
                                        'client_id' => $r['client_id'],
                                        'sales_invoice_number' => $r['sales_invoice_number'],
                                        'items' => [],
                                        'ids' => [],
                                    ];
                                }
                                $grouped[$key]['items'][] = $r;
                                $grouped[$key]['ids'][] = $r['id'];
                            }
                            ?>
                            <?php foreach ($grouped as $gkey => $group): ?>
                                <?php
                                    $all_ids = implode(',', $group['ids']);
                                    $item_count = count($group['items']);
                                    
                                    // Calculate summary stats for the group
                                    $group_expired = 0;
                                    $group_active = 0;
                                    foreach ($group['items'] as $gi) {
                                        if ($gi['end_date'] && strtotime($gi['end_date']) < time()) {
                                            $group_expired++;
                                        } elseif ($gi['status'] == 'active') {
                                            $group_active++;
                                        }
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="vertical-align: middle;"><strong style="font-size: 0.82rem; line-height: 1.2; word-wrap: break-word;"><?php echo htmlspecialchars($group['client_name']); ?></strong></td>
                                    <td style="vertical-align: middle;"><span style="color: var(--primary-500); font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($group['sales_invoice_number'] ?: 'N/A'); ?></span></td>
                                    <td style="vertical-align: middle; padding-top: 0.5rem; padding-bottom: 0.5rem;">
                                        <?php 
                                        $first = $group['items'][0];
                                        $firstExp = ($first['end_date'] && strtotime($first['end_date']) < time());
                                        
                                        // Calculate percentage for this specific item
                                        $tDays = max(1, ($first['duration_months'] ?: 12) * 30);
                                        $dLeft = max(0, (new DateTime())->diff(new DateTime($first['end_date']))->days);
                                        $firstPct = min(100, max(0, ($dLeft / $tDays) * 100));
                                        
                                        if ($firstExp) {
                                            $barColor = '#ef4444'; $pctText = 'EXP';
                                        } else {
                                            if ($firstPct > 75) $barColor = '#10b981';
                                            elseif ($firstPct > 50) $barColor = '#eab308';
                                            elseif ($firstPct > 25) $barColor = '#f97316';
                                            else $barColor = '#ef4444';
                                            $pctText = round($firstPct) . '%';
                                        }

                                        $group_uid = 'grp_' . md5($gkey);
                                        ?>
                                        <!-- First item always visible -->
                                        <div style="display: flex; flex-direction: column; gap: 4px; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid <?php echo $barColor; ?>;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="font-size: 0.78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($first['brand'] . ' ' . $first['model']); ?></div>
                                                    <div style="font-size: 0.68rem; color: var(--text-muted);"><i class="ph ph-barcode"></i> <?php echo htmlspecialchars($first['serial_number']); ?></div>
                                                </div>
                                                    <div style="text-align: right; min-width: 130px; flex-shrink: 0; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                                        <div style="margin-right: 4px;">
                                                            <div style="font-size: 0.62rem; color: var(--text-muted);"><?php echo $first['duration_months']; ?> meses</div>
                                                            <div style="font-size: 0.75rem; font-weight: 600; color: <?php echo $firstExp ? '#ef4444' : 'var(--text-color)'; ?>;"><?php echo $first['end_date'] ? date('d/m/Y', strtotime($first['end_date'])) : 'N/A'; ?></div>
                                                        </div>
                                                        <!-- Edit Assignment (Pencil) - Accessible by Admin/Reception -->
                                                        <?php if (has_permission('assign_equipment', $pdo)): ?>
                                                            <button class="btn-icon" data-json='<?php echo htmlspecialchars(json_encode($first), ENT_QUOTES, "UTF-8"); ?>' onclick="openEditAssignmentModal(this)" title="Editar Venta / Asignación" style="width: 26px; height: 26px; font-size: 0.9rem; color: #10b981; background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2);"><i class="ph ph-pencil-simple"></i></button>
                                                        <?php endif; ?>
                                                    </div>
                                            </div>
                                            <!-- Mini Bar -->
                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                                <div style="flex: 1; height: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                                    <div style="height: 100%; width: <?php echo $firstPct; ?>%; background: <?php echo $barColor; ?>;"></div>
                                                </div>
                                                <span style="font-size: 0.6rem; font-weight: 700; color: <?php echo $barColor; ?>; min-width: 25px; text-align: right;"><?php echo $pctText; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($item_count > 1): ?>
                                        <!-- Toggle to show rest -->
                                        <button onclick="document.getElementById('<?php echo $group_uid; ?>').style.display = document.getElementById('<?php echo $group_uid; ?>').style.display === 'none' ? 'flex' : 'none'; this.querySelector('.toggle-icon').classList.toggle('ph-caret-down'); this.querySelector('.toggle-icon').classList.toggle('ph-caret-up');" 
                                            style="margin-top: 4px; background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.25); color: #a78bfa; padding: 2px 8px; border-radius: 6px; cursor: pointer; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s;">
                                            <i class="ph ph-caret-down toggle-icon"></i> +<?php echo $item_count - 1; ?> equipo<?php echo ($item_count - 1) > 1 ? 's' : ''; ?> más
                                        </button>
                                        <!-- Hidden items -->
                                        <div id="<?php echo $group_uid; ?>" style="display: none; flex-direction: column; gap: 4px; margin-top: 6px;">
                                            <?php for ($gi_idx = 1; $gi_idx < $item_count; $gi_idx++): ?>
                                                <?php 
                                                $gi = $group['items'][$gi_idx];
                                                $giExp = ($gi['end_date'] && strtotime($gi['end_date']) < time()); 
                                                
                                                $tDaysGi = max(1, ($gi['duration_months'] ?: 12) * 30);
                                                $dLeftGi = max(0, (new DateTime())->diff(new DateTime($gi['end_date']))->days);
                                                $giPct = min(100, max(0, ($dLeftGi / $tDaysGi) * 100));
                                                
                                                if ($giExp) {
                                                    $giBarColor = '#ef4444'; $giPctText = 'EXP';
                                                } else {
                                                    if ($giPct > 75) $giBarColor = '#10b981';
                                                    elseif ($giPct > 50) $giBarColor = '#eab308';
                                                    elseif ($giPct > 25) $giBarColor = '#f97316';
                                                    else $giBarColor = '#ef4444';
                                                    $giPctText = round($giPct) . '%';
                                                }
                                                ?>
                                                <div style="display: flex; flex-direction: column; gap: 4px; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid <?php echo $giBarColor; ?>;">
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <div style="flex: 1; min-width: 0;">
                                                            <div style="font-size: 0.78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($gi['brand'] . ' ' . $gi['model']); ?></div>
                                                            <div style="font-size: 0.68rem; color: var(--text-muted);"><i class="ph ph-barcode"></i> <?php echo htmlspecialchars($gi['serial_number']); ?></div>
                                                        </div>
                                                        <div style="text-align: right; min-width: 130px; flex-shrink: 0; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                                            <div style="margin-right: 4px;">
                                                                <div style="font-size: 0.62rem; color: var(--text-muted);"><?php echo $gi['duration_months']; ?> meses</div>
                                                                <div style="font-size: 0.75rem; font-weight: 600; color: <?php echo $giExp ? '#ef4444' : 'var(--text-color)'; ?>;"><?php echo $gi['end_date'] ? date('d/m/Y', strtotime($gi['end_date'])) : 'N/A'; ?></div>
                                                            </div>
                                                            <!-- Edit Assignment (Pencil) - Accessible by Admin/Reception -->
                                                            <?php if (has_permission('assign_equipment', $pdo) || $_SESSION['role_name'] === 'SuperAdmin' || $_SESSION['role_name'] === 'Administrador'): ?>
                                                                <button class="btn-icon" data-json='<?php echo htmlspecialchars(json_encode($gi), ENT_QUOTES, "UTF-8"); ?>' onclick="openEditAssignmentModal(this)" title="Editar Venta / Asignación" style="width: 26px; height: 26px; font-size: 0.9rem; color: #10b981; background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2);"><i class="ph ph-pencil-simple"></i></button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <!-- Mini Bar -->
                                                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                                        <div style="flex: 1; height: 3px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                                            <div style="height: 100%; width: <?php echo $giPct; ?>%; background: <?php echo $giBarColor; ?>;"></div>
                                                        </div>
                                                        <span style="font-size: 0.6rem; font-weight: 700; color: <?php echo $giBarColor; ?>; min-width: 25px; text-align: right;"><?php echo $giPctText; ?></span>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="vertical-align: middle; text-align: center;">
                                        <div style="display: flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                            <button class="btn-icon" data-group-json='<?php echo htmlspecialchars(json_encode($group['items']), ENT_QUOTES, "UTF-8"); ?>' onclick="openGroupDetailModal(this)" title="Ver Detalles del Lote"><i class="ph ph-eye"></i></button>
                                            <a href="print_certificate.php?id=<?php echo $group['ids'][0]; ?>" target="_blank" class="btn-icon" title="Imprimir Certificado" style="color: #a855f7;"><i class="ph ph-printer"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $tab === 'stock' ? '8' : '4'; ?>" style="padding: 3rem; text-align: center; color: var(--text-muted);">
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
            <div>
                <p class="text-xs text-muted mb-1">ORIGEN DE COMPRA</p>
                <div id="m_origin_badge">-</div>
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
            <input type="hidden" name="service_order_ids" id="assign_order_ids">
            
            <div style="margin-bottom: 1.5rem;">
                <p class="text-sm text-muted mb-1">EQUIPO(S) A VENDER:</p>
                <div style="background: rgba(0,0,0,0.15); border-radius: 8px; max-height: 180px; overflow-y: auto; border: 1px solid var(--border-color);">
                    <ul id="assign_equipment_list" style="margin: 0; padding: 0; list-style: none;">
                        <!-- Dinámico -->
                    </ul>
                </div>
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

            <div style="display: grid; grid-template-columns: 1fr; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">Factura de Venta * (Aplica para todos)</label>
                    <input type="text" name="sales_invoice_number" class="form-control" required placeholder="Nº Factura">
                </div>
            </div>

            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('assignModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="ph ph-check"></i> Confirmar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Sale / Assignment Modal -->
<div id="editAssignmentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="card" style="width: 90%; max-width: 600px; padding: 2rem; position: relative; border: 1px solid var(--border-color);">
        <button onclick="document.getElementById('editAssignmentModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; margin-bottom: 2rem;"><i class="ph ph-shopping-cart" style="color: #10b981;"></i> Editar Venta / Asignación</h3>
        
        <form action="update_assignment.php" method="POST">
            <input type="hidden" name="service_order_id" id="e_assign_order_id">
            <input type="hidden" name="equipment_id" id="e_assign_equipment_id">
            
            <div style="margin-bottom: 1.5rem;">
                <p class="text-sm text-muted mb-1">EQUIPO A EDITAR:</p>
                <div style="padding: 10px 15px; background: rgba(0,0,0,0.15); border-radius: 8px; border: 1px solid var(--border-color);">
                    <div id="e_assign_equipment_name" style="font-weight: 600; font-size: 0.92rem;"></div>
                    <div id="e_assign_serial" style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;"></div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Cliente Final *</label>
                <input type="text" name="edit_client_name" id="e_assign_client_name" class="form-control" placeholder="Nombre completo del cliente" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Cédula/RUC</label>
                    <input type="text" name="edit_client_tax_id" id="e_assign_tax_id" class="form-control" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="edit_client_phone" id="e_assign_phone" class="form-control" placeholder="Opcional">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">Factura de Venta *</label>
                    <input type="text" name="sales_invoice_number" id="e_assign_invoice" class="form-control" required placeholder="Nº Factura">
                </div>
                <div class="form-group">
                    <label class="form-label">Meses de Garantía</label>
                    <input type="number" name="warranty_months" id="e_assign_months" class="form-control" required min="1">
                </div>
            </div>

            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('editAssignmentModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">
                    <i class="ph ph-floppy-disk"></i> Guardar Cambios
                </button>
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
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Equipo (Marca / Modelo)</label>
                    <input type="text" name="equipment_name" id="edit_equipment_name" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" name="serial_number" id="edit_serial_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Factura Venta</label>
                    <input type="text" name="sales_invoice_number" id="edit_sales_invoice" class="form-control">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Código Producto</label>
                    <input type="text" name="product_code" id="edit_product_code" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Origen de Compra</label>
                    <select name="purchase_origin" id="edit_purchase_origin" class="form-control">
                        <option value="local">LOCAL</option>
                        <option value="importada">IMPORTADA</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label">Meses de Garantía</label>
                <input type="number" name="warranty_months" id="edit_warranty_months" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="color: #f59e0b; font-weight: bold;">Motivo del Cambio *</label>
                <textarea name="edit_reason" class="form-control" required placeholder="Explica brevemente por qué se realiza este cambio (auditado)" style="min-height: 80px;"></textarea>
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
function openModalFromBtn(btn) {
    const data = JSON.parse(btn.dataset.json);
    document.getElementById('m_code').innerText = data.product_code || '-';
    document.getElementById('m_serial').innerText = data.serial_number || '-';
    document.getElementById('m_client').innerText = data.client_name || '-';
    document.getElementById('m_equipment').innerText = (data.brand || '') + ' ' + (data.model || '');
    document.getElementById('m_invoice').innerText = data.sales_invoice_number || 'N/A';
    document.getElementById('m_end').innerText = data.end_date ? data.end_date : 'N/A';
    document.getElementById('m_supplier').innerText = data.supplier_name || 'N/A';
    document.getElementById('m_master_inv').innerText = data.master_entry_invoice || 'N/A';
    
    // Origin Badge
    const originBadge = document.getElementById('m_origin_badge');
    if (data.purchase_origin === 'importada') {
        originBadge.innerHTML = '<span class="badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3);"><i class="ph-fill ph-airplane-tilt"></i> IMPORTADA</span>';
    } else {
        originBadge.innerHTML = '<span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);"><i class="ph-fill ph-storefront"></i> LOCAL</span>';
    }
    
    document.getElementById('detailModal').style.display = 'flex';
}

let warehouse_cart = JSON.parse(localStorage.getItem('warehouse_cart')) || {};

function saveCart() {
    localStorage.setItem('warehouse_cart', JSON.stringify(warehouse_cart));
}

function handleCheckboxChange(cb) {
    if (cb.checked) {
        warehouse_cart[cb.value] = JSON.parse(cb.dataset.json);
    } else {
        delete warehouse_cart[cb.value];
    }
    saveCart();
    updateBulkActionUI();
}

function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.bulk-cb');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
        if (source.checked) {
            warehouse_cart[cb.value] = JSON.parse(cb.dataset.json);
        } else {
            delete warehouse_cart[cb.value];
        }
    });
    saveCart();
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const keys = Object.keys(warehouse_cart);
    const container = document.getElementById('bulk-action-container');
    const countSpan = document.getElementById('bulk-count');
    
    if(keys.length > 0) {
        countSpan.innerText = keys.length;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        const selectAll = document.getElementById('selectAllItems');
        if (selectAll) selectAll.checked = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Restore checkboxes from cart state when page loads
    const checkboxes = document.querySelectorAll('.bulk-cb');
    checkboxes.forEach(cb => {
        if (warehouse_cart[cb.value]) {
            cb.checked = true;
        }
        cb.addEventListener('change', () => handleCheckboxChange(cb));
    });
    updateBulkActionUI();
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'assigned'): ?>
    // Clear cart upon successful assignment
    localStorage.removeItem('warehouse_cart');
    warehouse_cart = {};
    <?php endif; ?>
});

function openAssignModalFromBtn(btn) {
    const data = JSON.parse(btn.dataset.json);
    setupAssignModal([data]);
}

function openBulkAssignModal() {
    const dataArr = Object.values(warehouse_cart);
    if(dataArr.length === 0) return;
    setupAssignModal(dataArr);
}

function openGroupDetailModal(btn) {
    const items = JSON.parse(btn.dataset.groupJson);
    const modal = document.getElementById('detailModal');
    const content = modal.querySelector('.card');
    
    // Build a rich grouped detail view
    let html = `<button onclick="document.getElementById('detailModal').style.display='none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>`;
    html += `<h3 style="margin-top: 0; margin-bottom: 1.5rem;"><i class="ph ph-info" style="color: var(--primary-500);"></i> Detalles del Lote</h3>`;
    
    // Common info from first item
    const first = items[0];
    html += `<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">`;
    html += `<div><p class="text-xs text-muted mb-1">CLIENTE</p><p class="font-bold">${first.client_name || '-'}</p></div>`;
    html += `<div><p class="text-xs text-muted mb-1">FACTURA DE VENTA</p><p class="font-bold">${first.sales_invoice_number || '-'}</p></div>`;
    html += `<div><p class="text-xs text-muted mb-1">PROVEEDOR</p><p class="font-bold">${first.supplier_name || '-'}</p></div>`;
    html += `<div><p class="text-xs text-muted mb-1">FACT. INGRESO MASTER</p><p class="font-bold">${first.master_entry_invoice || '-'}</p></div>`;
    html += `</div>`;
    
    // Items list
    html += `<p class="text-xs text-muted mb-1">EQUIPOS (${items.length})</p>`;
    html += `<div style="display: flex; flex-direction: column; gap: 6px; max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem;">`;
    items.forEach(item => {
        const isExp = item.end_date && new Date(item.end_date) < new Date();
        const borderColor = isExp ? '#ef4444' : '#10b981';
        html += `<div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid ${borderColor};">
            <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 600; font-size: 0.9rem;">${item.brand || ''} ${item.model || ''}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-barcode"></i> S/N: ${item.serial_number || 'N/A'}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-tag"></i> Cód: ${item.product_code || 'N/A'}</div>
            </div>
            <div style="text-align: right; flex-shrink: 0;">
                <div style="font-size: 0.7rem; color: var(--text-muted);">Garantía: ${item.duration_months || 0} meses</div>
                <div style="font-size: 0.85rem; font-weight: 600; color: ${isExp ? '#ef4444' : '#10b981'};">${item.end_date ? new Date(item.end_date).toLocaleDateString('es-HN') : 'N/A'}</div>
            </div>
        </div>`;
    });
    html += `</div>`;
    
    html += `<div style="text-align: right;"><button onclick="document.getElementById('detailModal').style.display='none'" class="btn btn-secondary">Cerrar</button></div>`;
    
    content.innerHTML = html;
    modal.style.display = 'flex';
}

function setupAssignModal(dataArr) {
    const ids = dataArr.map(d => d.id).join(',');
    document.getElementById('assign_order_ids').value = ids;
    
    const list = document.getElementById('assign_equipment_list');
    list.innerHTML = '';
    dataArr.forEach((d, index) => {
        let li = document.createElement('li');
        li.style.display = 'flex';
        li.style.justifyContent = 'space-between';
        li.style.alignItems = 'center';
        li.style.padding = '12px 15px';
        li.style.transition = 'background 0.2s ease';
        
        if (index < dataArr.length - 1) {
            li.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
        }
        
        let label = document.createElement('div');
        label.style.flex = '1';
        label.style.paddingRight = '15px';
        label.innerHTML = `<div style="font-size: 0.9rem; font-weight: 600; color: var(--text-color); margin-bottom: 4px; line-height: 1.3;">${d.brand || ''} ${d.model || ''}</div>
                           <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-barcode" style="vertical-align: middle; font-size: 0.9rem;"></i> S/N: <span style="font-family: monospace;">${d.serial_number || 'N/A'}</span></div>`;
        
        let actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.flexDirection = 'column';
        actions.style.alignItems = 'center';
        actions.innerHTML = `<label style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;">Meses</label>
                             <input type="number" name="warranty_months[${d.id}]" value="${d.duration_months || 12}" min="1" style="width: 60px; padding: 6px 4px; border: 1px solid var(--border-color); border-radius: 6px; background: rgba(0,0,0,0.2); color: #10b981; font-weight: 700; text-align: center; font-size: 0.9rem; transition: border-color 0.2s;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='var(--border-color)'">`;
        
        li.appendChild(label);
        li.appendChild(actions);
        list.appendChild(li);
    });
    
    document.getElementById('assignForm').reset();
    document.getElementById('assignModal').style.display = 'flex';
}

function openEditModalFromBtn(btn) {
    const data = JSON.parse(btn.dataset.json);
    document.getElementById('edit_order_id').value = data.id;
    document.getElementById('edit_equipment_id').value = data.equipment_id;
    document.getElementById('edit_product_code').value = data.product_code || '';
    
    // Combine Brand and Model
    let equipmentName = data.brand || '';
    if (data.model && data.model.trim() !== '') {
        equipmentName += (equipmentName ? ' ' : '') + data.model;
    }
    document.getElementById('edit_equipment_name').value = equipmentName;
    
    document.getElementById('edit_serial_number').value = data.serial_number || '';
    document.getElementById('edit_sales_invoice').value = data.sales_invoice_number || '';
    document.getElementById('edit_warranty_months').value = data.duration_months || 0;
    document.getElementById('edit_purchase_origin').value = data.purchase_origin || 'local';
    
    document.getElementById('editModal').style.display = 'flex';
}

function openEditAssignmentModal(btn) {
    const data = JSON.parse(btn.dataset.json);
    document.getElementById('e_assign_order_id').value = data.id;
    document.getElementById('e_assign_equipment_id').value = data.equipment_id;
    
    document.getElementById('e_assign_equipment_name').innerText = (data.brand || '') + ' ' + (data.model || '');
    document.getElementById('e_assign_serial').innerText = 'S/N: ' + (data.serial_number || 'N/A');
    
    document.getElementById('e_assign_client_name').value = data.client_name || '';
    document.getElementById('e_assign_tax_id').value = data.tax_id || '';
    document.getElementById('e_assign_phone').value = data.phone || '';
    document.getElementById('e_assign_invoice').value = data.sales_invoice_number || '';
    document.getElementById('e_assign_months').value = data.duration_months || 12;
    
    document.getElementById('editAssignmentModal').style.display = 'flex';
}
</script>

<?php if (isset($_GET['print_cert'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.open('print_certificate.php?id=<?php echo urlencode($_GET['print_cert']); ?>', '_blank', 'width=800,height=900');
});
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
