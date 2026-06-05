<?php
// modules/warranties/report_expiring.php
require_once '../../config/db.php';
safe_session_start();
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Reporte de Garantías por Vencer';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Fetch all stock items sorted so that the ones closest to expiring are at the top
$sql = "
    SELECT 
        so.id, so.entry_date, 
        w.product_code, w.sales_invoice_number, w.supplier_name,
        w.master_entry_invoice, w.master_entry_date, w.end_date, w.status, w.duration_months, w.purchase_origin,
        w.supplier_duration_months, w.supplier_end_date,
        c.name as client_name, c.id as client_id,
        e.id as equipment_id, e.category_id, e.brand, e.model, e.serial_number
    FROM service_orders so
    JOIN warranties w ON w.service_order_id = so.id
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    WHERE so.service_type = 'warranty' AND c.name = 'Bodega - Inventario'
    ORDER BY 
        CASE 
            WHEN w.supplier_end_date IS NULL OR w.supplier_end_date = '0000-00-00' THEN 1 
            ELSE 0 
        END ASC,
        w.supplier_end_date ASC, 
        so.created_at DESC
";
$stmt = $pdo->query($sql);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculations
$totalStock = count($records);
$expiredCount = 0;
$criticalCount = 0;
$warningCount = 0;
$healthyCount = 0;
$noWarrantyCount = 0;

$processedRecords = [];
$today = new DateTime();
$todayStr = $today->format('Y-m-d');

foreach ($records as $r) {
    $healthType = 'none';
    $percentage = 0;
    $daysLeft = 0;
    $barColor = '#6b7280';
    $pctText = 'N/A';
    $isExpired = false;

    if (!empty($r['supplier_end_date']) && $r['supplier_end_date'] !== '0000-00-00') {
        $endDateStr = $r['supplier_end_date'];
        $startDateStr = (!empty($r['master_entry_date']) && $r['master_entry_date'] !== '0000-00-00') ? $r['master_entry_date'] : $r['entry_date'];
        
        try {
            $endDate = new DateTime($endDateStr);
            $startDate = new DateTime($startDateStr);
            
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
            $healthType = 'expired';
            $expiredCount++;
        } else {
            $percentageVal = round($percentage);
            $pctText = $percentageVal . '%';
            if ($percentageVal > 50) {
                $barColor = '#10b981';
                $healthType = 'healthy';
                $healthyCount++;
            } elseif ($percentageVal > 25) {
                $barColor = '#eab308';
                $healthType = 'warning';
                $warningCount++;
            } else {
                $barColor = '#f97316';
                $healthType = 'critical';
                $criticalCount++;
            }
        }
    } else {
        $noWarrantyCount++;
    }

    $processedRecords[] = array_merge($r, [
        'health_type' => $healthType,
        'percentage' => $percentage,
        'days_left' => $daysLeft,
        'bar_color' => $barColor,
        'pct_text' => $pctText,
        'is_expired' => $isExpired
    ]);
}
?>

<!-- Custom Premium Styles for Report & Interactive Filters -->
<style>
.btn-filter {
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.btn-filter:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
}
.btn-filter.active[data-filter="all"] {
    background: rgba(168, 85, 247, 0.2) !important;
    color: #a855f7 !important;
    border-color: #a855f7 !important;
}
.btn-filter.active[data-filter="expired"] {
    background: rgba(239, 68, 68, 0.2) !important;
    color: #ef4444 !important;
    border-color: #ef4444 !important;
}
.btn-filter.active[data-filter="critical"] {
    background: rgba(249, 115, 22, 0.2) !important;
    color: #f97316 !important;
    border-color: #f97316 !important;
}
.btn-filter.active[data-filter="warning"] {
    background: rgba(234, 179, 8, 0.2) !important;
    color: #eab308 !important;
    border-color: #eab308 !important;
}
.btn-filter.active[data-filter="healthy"] {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #10b981 !important;
    border-color: #10b981 !important;
}
.btn-filter.active[data-filter="none"] {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--text-color) !important;
    border-color: var(--text-muted) !important;
}

@media print {
    /* Hide all system layout elements */
    header, 
    sidebar, 
    .sidebar, 
    .main-header, 
    .nav-container, 
    .card:has(#report-search), 
    .btn, 
    .ph-printer, 
    .btn-filter, 
    .no-print {
        display: none !important;
    }
    
    /* Make the body take full width and use light backgrounds for printing */
    body {
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
        margin: 0 !important;
        font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
        font-size: 10pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    /* Reset main container spacing */
    .main-content, 
    .content-wrapper, 
    main, 
    .animate-enter {
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }
    
    /* Style cards as flat boxes instead of dark floating panels */
    .card {
        background: #fff !important;
        border: 1px solid #ddd !important;
        color: #000 !important;
        box-shadow: none !important;
        margin-bottom: 1.5rem !important;
        page-break-inside: avoid;
    }
    
    /* Elegant table for print */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        color: #000 !important;
    }
    
    th {
        background-color: #f3f4f6 !important;
        color: #1f2937 !important;
        border-bottom: 2px solid #d1d5db !important;
        padding: 8px !important;
        font-size: 9pt !important;
        text-align: left !important;
    }
    
    td {
        border-bottom: 1px solid #e5e7eb !important;
        padding: 8px !important;
        font-size: 8.5pt !important;
        color: #000 !important;
    }
    
    /* Badges print black text on white outline */
    .badge {
        background: #fff !important;
        border: 1px solid #ccc !important;
        color: #000 !important;
        print-color-adjust: exact;
    }
    
    /* Show formal print header only during print */
    .print-header {
        display: block !important;
        margin-bottom: 2rem !important;
        border-bottom: 2px solid #000 !important;
        padding-bottom: 1rem !important;
    }
    
    /* Signature lines at bottom */
    .print-signatures {
        display: flex !important;
        justify-content: space-between !important;
        margin-top: 5rem !important;
        page-break-inside: avoid;
    }
    
    .signature-box {
        width: 45% !important;
        border-top: 1px solid #000 !important;
        text-align: center !important;
        padding-top: 0.5rem !important;
        font-size: 9.5pt !important;
    }
    
    /* Progress/health bars in print */
    .health-bar-wrapper {
        background: #f3f4f6 !important;
        border: 1px solid #e5e7eb !important;
    }
    
    .health-bar-fill {
        background: #374151 !important; /* dark slate for premium printable charts */
    }
    
    .health-card-container {
        border-left-width: 4px !important;
        border-left-style: solid !important;
        background: #fff !important;
        border: 1px solid #ddd !important;
    }
}

/* Hide print-only components in screen view */
.print-header,
.print-signatures {
    display: none;
}
</style>

<div class="animate-enter">
    <!-- Header Section -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1>
                <a href="database.php" style="color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="ph ph-arrow-left" style="font-size: 1.5rem; color: var(--primary-500);"></i>
                </a>
                <i class="ph ph-shield-warning" style="color: #a855f7;"></i> Reporte de Garantías por Vencer
            </h1>
            <p class="text-muted">Análisis detallado de vencimientos de garantías provistas por proveedores para productos en bodega.</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <a href="database.php" class="btn btn-secondary">
                <i class="ph ph-database"></i> Volver a Bodega
            </a>
            <button onclick="window.print()" class="btn btn-primary" style="background: #a855f7; border-color: #a855f7;">
                <i class="ph ph-printer"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <!-- Formal Header (Visible only when printing) -->
    <div class="print-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h2 style="margin: 0 0 4px 0; font-size: 1.6rem; font-weight: 700; color: #000; font-family: 'Inter', sans-serif;">SYSTEM TALLER - CONTROL DE BODEGA</h2>
                <p style="margin: 0; font-size: 0.95rem; color: #444;">Reporte Ejecutivo de Garantías de Proveedores a Vencer</p>
                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #666; font-style: italic;">Filtro aplicado: Productos actualmente en Stock (Bodega - Inventario)</p>
            </div>
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 0.9rem; font-weight: 600; color: #000;">Fecha de Emisión: <?php echo date('d/m/Y H:i'); ?></p>
                <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #444;">Auditado por: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrador'); ?></p>
            </div>
        </div>
    </div>

    <!-- Metrics Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <!-- Card 1: Total -->
        <div class="card health-card-container" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #a855f7;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(168, 85, 247, 0.1); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-package"></i>
            </div>
            <div>
                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">En Stock</span>
                <h3 style="font-size: 1.6rem; font-weight: 700; margin: 0; line-height: 1.2; color: var(--text-color);"><?php echo $totalStock; ?></h3>
            </div>
        </div>

        <!-- Card 2: Expired -->
        <div class="card health-card-container" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #ef4444;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-x-circle"></i>
            </div>
            <div>
                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Garantía Expirada</span>
                <h3 style="font-size: 1.6rem; font-weight: 700; margin: 0; line-height: 1.2; color: var(--text-color);"><?php echo $expiredCount; ?></h3>
            </div>
        </div>

        <!-- Card 3: Critical -->
        <div class="card health-card-container" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #f97316;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249, 115, 22, 0.1); color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-warning-octagon"></i>
            </div>
            <div>
                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Crítico (≤ 25%)</span>
                <h3 style="font-size: 1.6rem; font-weight: 700; margin: 0; line-height: 1.2; color: var(--text-color);"><?php echo $criticalCount; ?></h3>
            </div>
        </div>

        <!-- Card 4: Warning -->
        <div class="card health-card-container" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; border-left: 4px solid #eab308;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(234, 179, 8, 0.1); color: #eab308; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="ph ph-warning"></i>
            </div>
            <div>
                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Advertencia (25-50%)</span>
                <h3 style="font-size: 1.6rem; font-weight: 700; margin: 0; line-height: 1.2; color: var(--text-color);"><?php echo $warningCount; ?></h3>
            </div>
        </div>
    </div>

    <!-- Filters Control Panel -->
    <div class="card no-print" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <!-- Search and Actions Row -->
        <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <div style="flex: 1; min-width: 280px; position: relative; display: flex; align-items: center;">
                <input type="text" id="report-search" oninput="applyFilters()" class="form-control" placeholder="Buscar por Serie, Código de Producto, Marca, Modelo, Proveedor o Factura..." style="padding-left: 2.75rem;">
                <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; color: var(--text-muted); font-size: 1.2rem;"></i>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button onclick="clearSearch()" class="btn btn-secondary" style="padding: 10px 16px;">
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- Health Filters Row -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-right: 0.5rem; display: inline-flex; align-items: center; gap: 4px;">
                <i class="ph ph-funnel"></i> Estado Salud:
            </span>
            <button class="btn btn-filter active" data-filter="all" onclick="filterByHealth('all', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600;">
                Todos (<span id="count-all"><?php echo $totalStock; ?></span>)
            </button>
            <button class="btn btn-filter" data-filter="expired" onclick="filterByHealth('expired', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600; color: #ef4444;">
                Expirados (<span id="count-expired"><?php echo $expiredCount; ?></span>)
            </button>
            <button class="btn btn-filter" data-filter="critical" onclick="filterByHealth('critical', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600; color: #f97316;">
                Críticos (<span id="count-critical"><?php echo $criticalCount; ?></span>)
            </button>
            <button class="btn btn-filter" data-filter="warning" onclick="filterByHealth('warning', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600; color: #eab308;">
                Advertencia (<span id="count-warning"><?php echo $warningCount; ?></span>)
            </button>
            <button class="btn btn-filter" data-filter="healthy" onclick="filterByHealth('healthy', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600; color: #10b981;">
                Saludables (<span id="count-healthy"><?php echo $healthyCount; ?></span>)
            </button>
            <button class="btn btn-filter" data-filter="none" onclick="filterByHealth('none', this)" style="padding: 5px 12px; border-radius: 16px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted);">
                Sin Garantía (<span id="count-none"><?php echo $noWarrantyCount; ?></span>)
            </button>
        </div>
    </div>

    <!-- Main Data Table Card -->
    <div class="card">
        <div class="table-container">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="width: 140px; padding: 12px;">Cód. Producto</th>
                        <th style="padding: 12px;">Equipo / Serie</th>
                        <th style="padding: 12px;">Proveedor / Factura Master</th>
                        <th style="width: 150px; padding: 12px;">Fechas Compra / Reg.</th>
                        <th style="width: 220px; padding: 12px;">Garantía Proveedor</th>
                        <th class="no-print" style="width: 110px; padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($processedRecords) > 0): ?>
                        <?php foreach ($processedRecords as $r): ?>
                            <?php
                            $healthBarHtml = '';
                            $statusBadgeHtml = '';

                            if ($r['health_type'] !== 'none') {
                                $endDateStr = $r['supplier_end_date'];
                                $isExpired = $r['is_expired'];
                                $barColor = $r['bar_color'];
                                $pctText = $r['pct_text'];
                                $percentage = $r['percentage'];

                                $healthBarHtml = '
                                <div style="display: flex; flex-direction: column; gap: 4px; padding: 6px 10px; background: rgba(255,255,255,0.02); border-radius: 8px; border-left: 3px solid ' . $barColor . '; min-width: 170px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                        <div style="text-align: left;">
                                            <span style="display: block; font-size: 0.65rem; color: var(--text-muted); line-height: 1.1;">Vence:</span>
                                            <span style="font-size: 0.8rem; font-weight: 600; color: ' . ($isExpired ? '#ef4444' : 'var(--text-color)') . ';">' . date('d/m/Y', strtotime($endDateStr)) . '</span>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="health-percent-text" style="font-size: 0.62rem; font-weight: 700; color: ' . $barColor . '; background: ' . $barColor . '15; padding: 2px 6px; border-radius: 4px;">' . $pctText . '</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        <div class="health-bar-wrapper" style="flex: 1; height: 5px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden;">
                                            <div class="health-bar-fill" style="height: 100%; width: ' . $percentage . '%; background: ' . $barColor . ';"></div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-muted" style="font-size: 0.65rem; margin-top: 2px;">
                                        Duración: ' . $r['supplier_duration_months'] . ' meses
                                    </div>
                                </div>';
                            } else {
                                $healthBarHtml = '
                                <span class="badge" style="background: rgba(245, 158, 11, 0.08); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); font-weight: 600; padding: 6px 10px; border-radius: 6px; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="ph ph-warning-circle" style="font-size: 0.95rem;"></i> SIN GARANTÍA PROV.
                                </span>';
                            }
                            ?>
                            <tr class="report-row" 
                                style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;"
                                data-health="<?php echo $r['health_type']; ?>" 
                                data-search="<?php echo htmlspecialchars(strtolower($r['serial_number'] . ' ' . $r['product_code'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . $r['supplier_name'] . ' ' . $r['master_entry_invoice'])); ?>">
                                
                                <td style="padding: 12px; vertical-align: middle;">
                                    <?php if (empty(trim($r['product_code']))): ?>
                                        <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">⚠️ Sin Código</span>
                                    <?php else: ?>
                                        <span class="badge" style="font-family: monospace; font-size: 0.78rem; font-weight: 600;"><?php echo htmlspecialchars($r['product_code']); ?></span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 12px; vertical-align: middle;">
                                    <div style="font-weight: 600; color: var(--text-color); font-size: 0.9rem;"><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></div>
                                    <div class="text-xs text-muted" style="margin-top: 2px; font-family: monospace; display: flex; align-items: center; gap: 4px;">
                                        <i class="ph ph-barcode" style="font-size: 0.9rem;"></i> <?php echo htmlspecialchars($r['serial_number']); ?>
                                    </div>
                                </td>

                                <td style="padding: 12px; vertical-align: middle;">
                                    <div style="font-weight: 500; font-size: 0.85rem;">
                                        <i class="ph ph-truck" style="color: #a855f7; vertical-align: middle; margin-right: 4px;"></i>
                                        <?php echo htmlspecialchars($r['supplier_name'] ?: 'No asignado'); ?>
                                    </div>
                                    <div class="text-xs text-muted" style="margin-top: 4px; display: flex; align-items: center; gap: 4px;" title="Factura Master del Proveedor">
                                        <i class="ph ph-file-text" style="font-size: 0.85rem;"></i>
                                        <span>Factura: <?php echo htmlspecialchars($r['master_entry_invoice'] ?: 'N/A'); ?></span>
                                    </div>
                                </td>

                                <td style="padding: 12px; vertical-align: middle; font-size: 0.82rem;">
                                    <?php if (!empty($r['master_entry_date']) && $r['master_entry_date'] !== '0000-00-00'): ?>
                                        <div style="font-weight: 600; color: var(--text-color); display: flex; align-items: center; gap: 4px;" title="Fecha de compra original del proveedor">
                                            <i class="ph ph-calendar" style="color: #a855f7;"></i>
                                            <span>Prov: <?php echo date('d/m/Y', strtotime($r['master_entry_date'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-muted" style="font-style: italic;">Prov: No registrada</div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-muted" style="margin-top: 4px; display: flex; align-items: center; gap: 4px;" title="Fecha de ingreso en bodega">
                                        <i class="ph ph-clock"></i>
                                        <span>Sist: <?php echo date('d/m/Y', strtotime($r['entry_date'])); ?></span>
                                    </div>
                                </td>

                                <td style="padding: 12px; vertical-align: middle;">
                                    <?php echo $healthBarHtml; ?>
                                </td>

                                <td class="no-print" style="padding: 12px; vertical-align: middle; text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: center;">
                                        <!-- Open detailed modal from the parent database context if needed or link to detailed edit -->
                                        <a href="database.php?search=<?php echo urlencode($r['serial_number']); ?>" class="btn-icon" title="Ver en Bodega" style="color: var(--primary-500); width: 30px; height: 30px; background: rgba(var(--primary-rgb), 0.08); border-color: rgba(var(--primary-rgb), 0.2);">
                                            <i class="ph ph-eye" style="font-size: 1.05rem;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="no-results-row">
                            <td colspan="6" style="padding: 4rem; text-align: center; color: var(--text-muted);">
                                <i class="ph ph-warning-circle" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--border-color);"></i>
                                No se encontraron productos en stock.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formal Audit Signatures for Print View -->
    <div class="print-signatures">
        <div class="signature-box">
            <strong>Firma Responsable de Bodega</strong>
            <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: #555;">Control de Inventario y Activos</p>
        </div>
        <div class="signature-box">
            <strong>Firma Auditor / Supervisor</strong>
            <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: #555;">Verificación Técnica e Inventario</p>
        </div>
    </div>
</div>

<!-- Dynamic Interactivity Scripts -->
<script>
function applyFilters() {
    const searchVal = document.getElementById('report-search').value.toLowerCase().trim();
    const activeFilter = document.querySelector('.btn-filter.active').getAttribute('data-filter');
    const rows = document.querySelectorAll('.report-row');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const health = row.getAttribute('data-health');
        const searchText = row.getAttribute('data-search');
        
        const matchesSearch = !searchVal || searchText.includes(searchVal);
        const matchesHealth = activeFilter === 'all' || health === activeFilter;
        
        if (matchesSearch && matchesHealth) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Manage "no results" placeholder
    let noResultsRow = document.getElementById('no-results-row');
    if (visibleCount === 0) {
        if (!noResultsRow) {
            const tbody = document.querySelector('tbody');
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="6" style="padding: 4rem; text-align: center; color: var(--text-muted);">
                    <i class="ph ph-warning-circle" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--border-color);"></i>
                    No se encontraron registros con los filtros seleccionados.
                </td>
            `;
            tbody.appendChild(noResultsRow);
        } else {
            noResultsRow.style.display = '';
        }
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}

function filterByHealth(filterVal, btn) {
    document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function clearSearch() {
    document.getElementById('report-search').value = '';
    const allBtn = document.querySelector('.btn-filter[data-filter="all"]');
    filterByHealth('all', allBtn);
}

// Automatically reset to default filters on page load
document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
