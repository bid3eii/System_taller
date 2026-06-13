<?php
// modules/warranties/history.php
require_once '../../config/db.php';
safe_session_start();
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Historial de Garantías Emitidas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Calculate Metrics
$totalWarranties = $pdo->query("SELECT COUNT(*) FROM service_orders so JOIN clients c ON so.client_id = c.id WHERE so.service_type = 'warranty'")->fetchColumn();
$todayWarranties = $pdo->query("SELECT COUNT(*) FROM service_orders so JOIN clients c ON so.client_id = c.id WHERE so.service_type = 'warranty' AND DATE(so.entry_date) = CURDATE()")->fetchColumn();
$stockWarranties = $pdo->query("SELECT COUNT(*) FROM service_orders so JOIN clients c ON so.client_id = c.id WHERE so.service_type = 'warranty' AND c.name = 'Bodega - Inventario'")->fetchColumn();
$soldWarranties = $pdo->query("SELECT COUNT(*) FROM service_orders so JOIN clients c ON so.client_id = c.id WHERE so.service_type = 'warranty' AND c.name != 'Bodega - Inventario'")->fetchColumn();

// Pagination and Filters
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'all';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

$where = "WHERE so.service_type = 'warranty'";
$params = [];

if ($tab === 'stock') {
    $where .= " AND c.name = 'Bodega - Inventario'";
} elseif ($tab === 'sold') {
    $where .= " AND c.name != 'Bodega - Inventario'";
}

if (!empty($search)) {
    $where .= " AND (
        e.serial_number LIKE ? 
        OR w.product_code LIKE ? 
        OR c.name LIKE ? 
        OR w.sales_invoice_number LIKE ? 
        OR e.brand LIKE ? 
        OR e.model LIKE ? 
        OR w.supplier_name LIKE ?
        OR EXISTS (
            SELECT 1 FROM service_order_history h3 
            JOIN users u3 ON h3.user_id = u3.id 
            WHERE h3.service_order_id = so.id 
              AND (u3.username LIKE ? OR u3.full_name LIKE ?)
        )
    )";
    $likeSearch = "%$search%";
    array_push($params, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
}

// Get Count
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
        so.id, so.entry_date, so.display_id,
        w.product_code, w.sales_invoice_number, w.supplier_name,
        w.master_entry_invoice, w.master_entry_date, w.end_date, w.status, w.duration_months, w.purchase_origin,
        w.supplier_duration_months, w.supplier_end_date,
        c.name as client_name, c.id as client_id, c.tax_id, c.phone,
        e.id as equipment_id, e.category_id, e.brand, e.model, e.serial_number,
        
        -- Creador (action = 'received')
        (SELECT u1.full_name FROM service_order_history h1 JOIN users u1 ON h1.user_id = u1.id WHERE h1.service_order_id = so.id AND h1.action = 'received' ORDER BY h1.created_at ASC LIMIT 1) as creator_name,
        (SELECT u1.username FROM service_order_history h1 JOIN users u1 ON h1.user_id = u1.id WHERE h1.service_order_id = so.id AND h1.action = 'received' ORDER BY h1.created_at ASC LIMIT 1) as creator_username,
        
        -- Asignador / Entregador (action = 'delivered')
        (SELECT u2.full_name FROM service_order_history h2 JOIN users u2 ON h2.user_id = u2.id WHERE h2.service_order_id = so.id AND h2.action = 'delivered' ORDER BY h2.created_at DESC LIMIT 1) as deliverer_name,
        (SELECT u2.username FROM service_order_history h2 JOIN users u2 ON h2.user_id = u2.id WHERE h2.service_order_id = so.id AND h2.action = 'delivered' ORDER BY h2.created_at DESC LIMIT 1) as deliverer_username
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
            <h1><i class="ph ph-clock-counter-clockwise" style="color: var(--primary-500);"></i> Historial de Garantías Emitidas</h1>
            <p class="text-muted">Consulta y audita el ciclo de vida completo de cada garantía, quién la emitió y quién la asignó.</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <a href="database.php" class="btn btn-secondary">
                <i class="ph ph-arrow-left"></i> Registros de Bodega
            </a>
            <a href="../equipment/entry.php?type=warranty" class="btn btn-primary">
                <i class="ph ph-plus-circle"></i> Nuevo Registro
            </a>
        </div>
    </div>

    <!-- Metrics Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Card 1: Total -->
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--primary-500); background: rgba(255, 255, 255, 0.02);">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(124, 58, 237, 0.1); color: var(--primary-500); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-clock-counter-clockwise"></i>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Emitidas</p>
                <h3 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-color);"><?php echo $totalWarranties; ?></h3>
            </div>
        </div>
        <!-- Card 2: Emitidas Hoy -->
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #10b981; background: rgba(255, 255, 255, 0.02);">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-calendar-check"></i>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Emitidas Hoy</p>
                <h3 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-color);"><?php echo $todayWarranties; ?></h3>
            </div>
        </div>
        <!-- Card 3: En Stock -->
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #f59e0b; background: rgba(255, 255, 255, 0.02);">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-package"></i>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">En Stock</p>
                <h3 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-color);"><?php echo $stockWarranties; ?></h3>
            </div>
        </div>
        <!-- Card 4: Asignadas/Vendidas -->
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #a855f7; background: rgba(255, 255, 255, 0.02);">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(168, 85, 247, 0.1); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-shopping-cart"></i>
            </div>
            <div>
                <p class="text-xs text-muted" style="margin: 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Asignadas / Vendidas</p>
                <h3 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-color);"><?php echo $soldWarranties; ?></h3>
            </div>
        </div>
    </div>

    <!-- Tabs/Filters -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 1rem;">
            <a href="?tab=all&search=<?php echo urlencode($search); ?>" style="padding: 1rem 1.5rem; border-bottom: 3px solid <?php echo $tab === 'all' ? 'var(--primary-500)' : 'transparent'; ?>; color: <?php echo $tab === 'all' ? 'var(--primary-500)' : 'var(--text-muted)'; ?>; text-decoration: none; font-weight: 600;">
                <i class="ph ph-list"></i> Todos
            </a>
            <a href="?tab=stock&search=<?php echo urlencode($search); ?>" style="padding: 1rem 1.5rem; border-bottom: 3px solid <?php echo $tab === 'stock' ? 'var(--primary-500)' : 'transparent'; ?>; color: <?php echo $tab === 'stock' ? 'var(--primary-500)' : 'var(--text-muted)'; ?>; text-decoration: none; font-weight: 600;">
                <i class="ph ph-package"></i> En Stock
            </a>
            <a href="?tab=sold&search=<?php echo urlencode($search); ?>" style="padding: 1rem 1.5rem; border-bottom: 3px solid <?php echo $tab === 'sold' ? 'var(--primary-500)' : 'transparent'; ?>; color: <?php echo $tab === 'sold' ? 'var(--primary-500)' : 'var(--text-muted)'; ?>; text-decoration: none; font-weight: 600;">
                <i class="ph ph-shopping-cart"></i> Vendidos / Asignados
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
        <form method="GET" action="history.php" style="display: flex; gap: 1rem; align-items: center;">
            <input type="hidden" name="tab" value="<?php echo esc($tab); ?>">
            <div class="input-group" style="flex: 1; position: relative; display: flex; align-items: center;">
                <input type="text" id="history-search-input" name="search" class="form-control" placeholder="Buscar por Serie, Código, Equipo, Proveedor, Cliente o Usuario..." value="<?php echo esc($search); ?>">
                <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
            <button type="submit" class="btn btn-secondary">Buscar</button>
            <?php if (!empty($search)): ?>
                <a href="?tab=<?php echo esc($tab); ?>" class="btn btn-icon" title="Limpiar Búsqueda" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2);"><i class="ph ph-x"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="card">
        <div class="table-container">
            <table style="width: 100%; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 130px;"># Caso / S/N</th>
                        <th style="width: 180px;">Equipo</th>
                        <th style="width: 180px;">Cliente</th>
                        <th style="width: 120px;">Fecha Registro</th>
                        <th style="width: 170px;">Emitido Por</th>
                        <th style="width: 170px;">Vendido Por</th>
                        <th style="width: 160px;">Garantía Cliente</th>
                        <th style="width: 160px;">Garantía Prov.</th>
                        <th style="width: 120px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="history-table-body">
                    <?php if (count($records) > 0): ?>
                        <?php foreach ($records as $r): ?>
                            <?php
                            // Calculate Client Warranty Health
                            $clientHealth = '';
                            if ($r['client_name'] === 'Bodega - Inventario') {
                                $clientHealth = '<span class="text-muted text-xs">—</span>';
                            } else {
                                if (!empty($r['end_date']) && $r['end_date'] !== '0000-00-00') {
                                    $endDateStr = $r['end_date'];
                                    $startDateStr = $r['entry_date']; 
                                    try {
                                        $endDate = new DateTime($endDateStr);
                                        $startDate = new DateTime($startDateStr);
                                        $today = new DateTime();
                                        if ($startDate < $endDate) {
                                            $totalDays = max(1, $startDate->diff($endDate)->days);
                                            if ($today > $endDate) {
                                                $daysLeft = 0;
                                            } else {
                                                $daysLeft = max(0, $today->diff($endDate)->days);
                                            }
                                            $percentage = min(100, max(0, ($daysLeft / $totalDays) * 100));
                                        } else {
                                            $percentage = 0;
                                            $daysLeft = 0;
                                        }
                                    } catch (Exception $e) {
                                        $percentage = 0;
                                        $daysLeft = 0;
                                    }
                                    $isExpired = (strtotime($endDateStr) < time());
                                    if ($isExpired) {
                                        $barColor = '#ef4444';
                                        $pctText = 'EXP';
                                    } else {
                                        if ($percentage > 75) $barColor = '#10b981';
                                        elseif ($percentage > 50) $barColor = '#eab308';
                                        elseif ($percentage > 25) $barColor = '#f97316';
                                        else $barColor = '#ef4444';
                                        $pctText = round($percentage) . '%';
                                    }
                                    
                                    $clientHealth = '
                                    <div style="display: flex; flex-direction: column; gap: 4px; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid ' . $barColor . '; min-width: 130px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                            <div style="text-align: left;">
                                                <span style="display: block; font-size: 0.65rem; color: var(--text-muted); line-height: 1.1;">Vence:</span>
                                                <span style="font-size: 0.78rem; font-weight: 600; color: ' . ($isExpired ? '#ef4444' : 'var(--text-color)') . ';">' . date('d/m/Y', strtotime($endDateStr)) . '</span>
                                            </div>
                                            <div>
                                                <span style="font-size: 0.6rem; font-weight: 700; color: ' . $barColor . '; background: ' . $barColor . '15; padding: 2px 6px; border-radius: 4px;">' . $pctText . '</span>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                            <div style="flex: 1; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                                <div style="height: 100%; width: ' . $percentage . '%; background: ' . $barColor . ';"></div>
                                            </div>
                                        </div>
                                        <div class="text-xs text-muted" style="font-size: 0.65rem; margin-top: 2px;">' . ($r['duration_months'] ?: 0) . ' meses</div>
                                    </div>';
                                } else {
                                    $clientHealth = '<span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.7rem;">SIN GARANTÍA</span>';
                                }
                            }

                            // Calculate Supplier Warranty Health
                            $supplierHealth = '';
                            if (!empty($r['supplier_end_date']) && $r['supplier_end_date'] !== '0000-00-00') {
                                $supEndDateStr = $r['supplier_end_date'];
                                $supStartDateStr = (!empty($r['master_entry_date']) && $r['master_entry_date'] !== '0000-00-00') ? $r['master_entry_date'] : $r['entry_date'];
                                try {
                                    $supEndDate = new DateTime($supEndDateStr);
                                    $supStartDate = new DateTime($supStartDateStr);
                                    $today = new DateTime();
                                    if ($supStartDate < $supEndDate) {
                                        $supTotalDays = max(1, $supStartDate->diff($supEndDate)->days);
                                        if ($today > $supEndDate) {
                                            $supDaysLeft = 0;
                                        } else {
                                            $supDaysLeft = max(0, $today->diff($supEndDate)->days);
                                        }
                                        $supPercentage = min(100, max(0, ($supDaysLeft / $supTotalDays) * 100));
                                    } else {
                                        $supPercentage = 0;
                                        $supDaysLeft = 0;
                                    }
                                } catch (Exception $e) {
                                    $supPercentage = 0;
                                    $supDaysLeft = 0;
                                }
                                $isSupExpired = (strtotime($supEndDateStr) < time());
                                if ($isSupExpired) {
                                    $supBarColor = '#ef4444';
                                    $supPctText = 'EXP';
                                } else {
                                    if ($supPercentage > 75) $supBarColor = '#10b981';
                                    elseif ($supPercentage > 50) $supBarColor = '#eab308';
                                    elseif ($supPercentage > 25) $supBarColor = '#f97316';
                                    else $supBarColor = '#ef4444';
                                    $supPctText = round($supPercentage) . '%';
                                }
                                
                                $supplierHealth = '
                                <div style="display: flex; flex-direction: column; gap: 4px; padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 8px; border-left: 3px solid ' . $supBarColor . '; min-width: 130px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                        <div style="text-align: left;">
                                            <span style="display: block; font-size: 0.65rem; color: var(--text-muted); line-height: 1.1;">Vence:</span>
                                            <span style="font-size: 0.78rem; font-weight: 600; color: ' . ($isSupExpired ? '#ef4444' : 'var(--text-color)') . ';">' . date('d/m/Y', strtotime($supEndDateStr)) . '</span>
                                        </div>
                                        <div>
                                            <span style="font-size: 0.6rem; font-weight: 700; color: ' . $supBarColor . '; background: ' . $supBarColor . '15; padding: 2px 6px; border-radius: 4px;">' . $supPctText . '</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        <div style="flex: 1; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                            <div style="height: 100%; width: ' . $supPercentage . '%; background: ' . $supBarColor . ';"></div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-muted" style="font-size: 0.65rem; margin-top: 2px;">' . ($r['supplier_duration_months'] ?: 0) . ' meses</div>
                                </div>';
                            } else {
                                $supplierHealth = '<span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.7rem;">SIN GARANTÍA</span>';
                            }
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);" class="history-row">
                                <td>
                                    <strong style="color: var(--primary-500); font-size: 0.85rem; display: block;">#<?php echo esc($r['display_id'] ?: $r['id']); ?></strong>
                                    <span class="text-xs text-muted" style="font-family: monospace; font-size: 0.72rem; word-break: break-all;"><i class="ph ph-barcode"></i> <?php echo esc($r['serial_number']); ?></span>
                                    <?php if (!empty(trim($r['product_code']))): ?>
                                        <div style="margin-top: 2px;"><span class="badge" style="font-size: 0.62rem; padding: 1px 4px;"><?php echo esc($r['product_code']); ?></span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 0.85rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc($r['brand'] . ' ' . $r['model']); ?>"><?php echo esc($r['brand'] . ' ' . $r['model']); ?></strong>
                                    <span class="text-xs text-muted">
                                        <?php if (($r['purchase_origin'] ?? 'local') === 'importada'): ?>
                                            <span style="color: #a855f7;"><i class="ph-fill ph-airplane-tilt"></i> Importado</span>
                                        <?php else: ?>
                                            <span style="color: #3b82f6;"><i class="ph-fill ph-storefront"></i> Local</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($r['client_name'] === 'Bodega - Inventario'): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.72rem; font-weight: 600;">
                                            <i class="ph ph-package"></i> EN STOCK
                                        </span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-direction: column; overflow: hidden;">
                                            <strong style="font-size: 0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc($r['client_name']); ?>"><?php echo esc($r['client_name']); ?></strong>
                                            <?php if (!empty($r['sales_invoice_number'])): ?>
                                                <span class="text-xs" style="color: var(--primary-500); font-weight: 600; margin-top: 2px;">
                                                    <i class="ph ph-receipt"></i> Factura: <?php echo esc($r['sales_invoice_number']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.8rem;">
                                        <i class="ph ph-calendar-blank" style="color: var(--primary-500); vertical-align: middle;"></i> 
                                        <?php echo date('d/m/Y', strtotime($r['entry_date'])); ?>
                                    </div>
                                    <?php if (!empty($r['master_entry_date']) && $r['master_entry_date'] !== '0000-00-00'): ?>
                                        <div class="text-xs text-muted" style="margin-top: 2px;" title="Fecha Compra Proveedor">
                                            <i class="ph ph-truck" style="color: #a855f7;"></i> <?php echo date('d/m/Y', strtotime($r['master_entry_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <!-- Creator -->
                                <td>
                                    <?php if (!empty($r['creator_username'])): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; border: 1px solid rgba(16, 185, 129, 0.2); flex-shrink: 0;">
                                                <?php echo strtoupper(substr($r['creator_name'] ?: $r['creator_username'], 0, 1)); ?>
                                            </div>
                                            <div style="overflow: hidden;">
                                                <span style="font-weight: 600; font-size: 0.8rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-color);" title="<?php echo esc($r['creator_name'] ?: $r['creator_username']); ?>"><?php echo esc($r['creator_name'] ?: $r['creator_username']); ?></span>
                                                <span class="text-xs text-muted" style="font-family: monospace; font-size: 0.65rem;">@<?php echo esc($r['creator_username']); ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-xs"><i class="ph ph-info" style="vertical-align: middle;"></i> N/D</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Deliverer -->
                                <td>
                                    <?php if ($r['client_name'] === 'Bodega - Inventario'): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.08); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.18); font-size: 0.68rem; font-weight: 500;">
                                            <i class="ph ph-clock"></i> Pendiente venta
                                        </span>
                                    <?php elseif (!empty($r['deliverer_username'])): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(168, 85, 247, 0.1); color: #a855f7; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; border: 1px solid rgba(168, 85, 247, 0.2); flex-shrink: 0;">
                                                <?php echo strtoupper(substr($r['deliverer_name'] ?: $r['deliverer_username'], 0, 1)); ?>
                                            </div>
                                            <div style="overflow: hidden;">
                                                <span style="font-weight: 600; font-size: 0.8rem; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-color);" title="<?php echo esc($r['deliverer_name'] ?: $r['deliverer_username']); ?>"><?php echo esc($r['deliverer_name'] ?: $r['deliverer_username']); ?></span>
                                                <span class="text-xs text-muted" style="font-family: monospace; font-size: 0.65rem;">@<?php echo esc($r['deliverer_username']); ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted text-xs"><i class="ph ph-info" style="vertical-align: middle;"></i> N/D</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $clientHealth; ?></td>
                                <td><?php echo $supplierHealth; ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; justify-content: center; align-items: center;">
                                        <a href="view.php?id=<?php echo $r['id']; ?>" class="btn-icon" title="Ver Detalles del Caso" style="color: var(--text-color);"><i class="ph ph-eye"></i></a>
                                        <a href="../equipment/print_entry.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-icon" title="Imprimir Hoja de Entrada" style="color: #3b82f6;"><i class="ph ph-printer"></i></a>
                                        <?php if ($r['client_name'] !== 'Bodega - Inventario'): ?>
                                            <a href="print_certificate.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-icon" title="Imprimir Certificado de Garantía" style="color: #a855f7;"><i class="ph ph-certificate"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="padding: 4rem; text-align: center; color: var(--text-muted);">
                                <i class="ph ph-warning-circle" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--text-muted);"></i>
                                No se encontraron registros de garantías emitidas.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem;">
                <p class="text-sm text-muted" style="margin: 0;">
                    Mostrando registros <strong><?php echo $offset + 1; ?></strong> - <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> de <strong><?php echo $totalRecords; ?></strong>
                </p>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;"><i class="ph ph-caret-left"></i> Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>" class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem;"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Siguiente <i class="ph ph-caret-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('history-search-input');
    const tableRows = document.querySelectorAll('.history-row');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            if (val === '') {
                tableRows.forEach(row => row.style.display = '');
                return;
            }
            tableRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if (text.includes(val)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
