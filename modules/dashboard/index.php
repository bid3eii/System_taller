<?php
// modules/dashboard/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check Dashboard Access
if (!can_access_module('dashboard', $pdo)) {
    die("Acceso denegado al Dashboard.");
}

$page_title = 'Dashboard';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// --- ROLE CONTEXT ---
$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];

$is_admin = ($role_id == 1);
$is_reception = ($role_id == 4);
$is_tech = ($role_id == 3);
$is_warehouse = ($role_id == 2);

// KPI Variables Initialization
$kpi1_val = 0;
$kpi1_label = '';
$kpi1_icon = '';
$kpi1_bg = '';
$kpi1_color = '';
$kpi2_val = 0;
$kpi2_label = '';
$kpi2_icon = '';
$kpi2_bg = '';
$kpi2_color = '';
$kpi3_val = 0;
$kpi3_label = '';
$kpi3_icon = '';
$kpi3_bg = '';
$kpi3_color = '';
$kpi4_val = 0;
$kpi4_label = '';
$kpi4_icon = '';
$kpi4_bg = '';
$kpi4_color = '';

$chartLabels = [];
$chartCounts = [];
$weeklyLabels = [];
$weeklyCounts = [];
$recentItems = []; // Generic items for table
$recentType = 'services'; // 'services' or 'tools'

// --- DATA FETCHING LOGIC ---

// --- DATA FETCHING LOGIC ---

if ($is_warehouse) {
    // --- WAREHOUSE VIEW ---
    $recentType = 'tools';

    // KPI 1: Herramientas Disponibles
    $stmt = $pdo->query("SELECT COUNT(*) FROM tools WHERE status = 'available'");
    $kpi1_val = $stmt->fetchColumn();
    $kpi1_label = "Herramientas Disponibles";
    $kpi1_icon = "ph-wrench";
    $kpi1_color = "var(--primary-500)";
    $kpi1_bg = "rgba(99, 102, 241, 0.1)";

    // KPI 2: Herramientas Prestadas
    $stmt = $pdo->query("SELECT COUNT(*) FROM tools WHERE status = 'assigned'");
    $kpi2_val = $stmt->fetchColumn();
    $kpi2_label = "Herramientas Prestadas";
    $kpi2_icon = "ph-hand-pointing";
    $kpi2_color = "var(--warning)";
    $kpi2_bg = "rgba(234, 179, 8, 0.1)";

    // KPI 3: Total Herramientas
    $stmt = $pdo->query("SELECT COUNT(*) FROM tools");
    $kpi3_val = $stmt->fetchColumn();
    $kpi3_label = "Total Herramientas";
    $kpi3_icon = "ph-toolbox";
    $kpi3_color = "var(--success)";
    $kpi3_bg = "rgba(34, 197, 94, 0.1)";

    // Chart: Tool Status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM tools GROUP BY status");
    $statusData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Recent: Last Tool Assignments
    $stmt = $pdo->query("
        SELECT ta.id, ta.assignment_date as date, u.username as user_name, t.name as item_name, ta.status
        FROM tool_assignments ta
        JOIN users u ON ta.user_id = u.id
        JOIN tool_assignment_items tai ON ta.id = tai.assignment_id
        JOIN tools t ON tai.tool_id = t.id
        ORDER BY ta.assignment_date DESC LIMIT 12
    ");
    $recentItems = $stmt->fetchAll();

} else {
    // --- WORKSHOP VIEW (Admin, Reception, Tech) ---
    $recentType = 'services';
    $can_view_all = has_permission('module_view_all_entries', $pdo);

    // KPIs Logic
    // Active Services
    $stmt = $pdo->query("SELECT COUNT(*) FROM service_orders WHERE service_type = 'service' AND status NOT IN ('delivered', 'cancelled')");
    $active_services = $stmt->fetchColumn();

    // Active Warranties (Repairs)
    $awSql = "
        SELECT COUNT(*) 
        FROM service_orders so 
        LEFT JOIN warranties w ON so.id = w.service_order_id 
        WHERE so.service_type = 'warranty' 
        AND (w.product_code IS NULL OR w.product_code = '') AND so.problem_reported != 'Garantía Registrada'
        AND so.status NOT IN ('delivered', 'cancelled')
    ";
    if (!$can_view_all)
        $awSql .= " AND so.assigned_tech_id = " . intval($user_id);
    $stmt = $pdo->query($awSql);
    $active_warranties = $stmt->fetchColumn();

    // Stats for Display
    if (!$can_view_all) {
        // Users without view_all_entries only see their assigned stats
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.assigned_tech_id = ? 
            AND so.status NOT IN ('delivered', 'cancelled')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND so.problem_reported != 'Garantía Registrada'
        ");
        $stmt->execute([$user_id]);
        $kpi1_val = $stmt->fetchColumn();
        $kpi1_label = "Mis Equipos";
        $kpi1_icon = "ph-user-focus";
    } else {
        $kpi1_val = $active_services;
        $kpi1_label = "Servicios en Taller";
        $kpi1_icon = "ph-wrench";
    }
    $kpi1_color = "var(--primary-500)";
    $kpi1_bg = "rgba(99, 102, 241, 0.1)";

    $kpi2_val = $active_warranties;
    $kpi2_label = "Garantías en Taller";
    $kpi2_icon = "ph-shield-warning";
    $kpi2_color = "var(--warning)";
    $kpi2_bg = "rgba(234, 179, 8, 0.1)";

    $k3Sql = "
        SELECT COUNT(*) 
        FROM service_orders so 
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE so.status = 'ready'
        AND (w.product_code IS NULL OR w.product_code = '') 
        AND so.problem_reported != 'Garantía Registrada'
    ";
    if (!$can_view_all)
        $k3Sql .= " AND so.assigned_tech_id = " . intval($user_id);
    $stmt = $pdo->query($k3Sql);
    $kpi3_val = $stmt->fetchColumn();
    $kpi3_label = "Listos para Entrega";
    $kpi3_icon = "ph-package";
    $kpi3_color = "var(--success)";
    $kpi3_bg = "rgba(34, 197, 94, 0.1)";

    $k4Sql = "
        SELECT COUNT(*) 
        FROM service_orders so 
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE so.status = 'delivered'
        AND (w.product_code IS NULL OR w.product_code = '') 
        AND so.problem_reported != 'Garantía Registrada'
    ";
    if (!$can_view_all)
        $k4Sql .= " AND so.assigned_tech_id = " . intval($user_id);
    $stmt = $pdo->query($k4Sql);
    $kpi4_val = $stmt->fetchColumn();
    $kpi4_label = "Total Entregados";
    $kpi4_icon = "ph-check-circle";
    $kpi4_color = "var(--purple-500)";
    $kpi4_bg = "rgba(168, 85, 247, 0.1)";

    // Status Distribution Chart
    $statusSql = "
        SELECT so.status, COUNT(*) as count 
        FROM service_orders so
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE so.status NOT IN ('delivered', 'cancelled')
        AND (w.product_code IS NULL OR w.product_code = '') 
        AND so.problem_reported != 'Garantía Registrada'
    ";
    if (!$can_view_all) {
        // Users without view_all_entries only see their assigned orders
        $statusSql .= " AND so.assigned_tech_id = " . intval($user_id);
    }
    $statusSql .= " GROUP BY so.status";
    $statusData = $pdo->query($statusSql)->fetchAll(PDO::FETCH_KEY_PAIR);

    // Recent Activity Table
    // Techs ALWAYS see only their assigned orders
    // For Admin/Reception, 'view_all_entries' permission controls visibility
    $recentSql = "
        SELECT so.id, so.entry_date, so.status, so.service_type, so.display_id, c.name as client_name, e.brand, e.model
        FROM service_orders so
        LEFT JOIN clients c ON so.client_id = c.id
        LEFT JOIN equipments e ON so.equipment_id = e.id
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE (w.product_code IS NULL OR w.product_code = '') 
        AND so.problem_reported != 'Garantía Registrada'
    ";

    if (!$can_view_all) {
        $recentSql .= " AND so.assigned_tech_id = " . intval($user_id);
    }

    $recentSql .= " ORDER BY so.entry_date DESC LIMIT 12";
    $recentItems = $pdo->query($recentSql)->fetchAll();
}


// --- COMPILE CHART DATA (Universal Logic) ---

// Status Chart (Doughnut)
if ($is_warehouse) {
    // Tool Status Mapping
    $labelsMap = [
        'available' => 'Disponible',
        'assigned' => 'Prestado',
        'maintenance' => 'Mantenimiento',
        'lost' => 'Perdido/Baja'
    ];
} else {
    // Service Status Mapping
    $labelsMap = [
        'received' => 'Recibido',
        'diagnosing' => 'Diagnóstico',
        'pending_approval' => 'En Espera',
        'in_repair' => 'Reparación',
        'ready' => 'Listo',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado'
    ];
}

foreach ($labelsMap as $key => $label) {
    if (isset($statusData[$key])) {
        $chartLabels[] = $label;
        $chartCounts[] = $statusData[$key];
    }
}

// Weekly Activity (Last 7 Days)
// Only for Services (Admin, Tech, Reception)
// Warehouse doesn't need this graph or logic is different?
// Let's keep it for Services views. Warehouse can show Empty or hide.
if (!$is_warehouse) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        $wSql = "
            SELECT COUNT(*) 
            FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE DATE(so.entry_date) = ?
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND so.problem_reported != 'Garantía Registrada'
        ";
        if (!$can_view_all) {
            $wSql .= " AND so.assigned_tech_id = " . intval($user_id);
        }

        $stmtDaily = $pdo->prepare($wSql);
        $stmtDaily->execute([$date]);
        $weeklyLabels[] = date('d/m', strtotime($date));
        $weeklyCounts[] = $stmtDaily->fetchColumn();
    }
}

?>

<!-- CHART.JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <p class="text-muted">
            <?php
            if ($is_reception)
                echo 'Gestión de clientes y recepción de equipos.';
            elseif ($is_warehouse)
                echo 'Gestión de inventario y herramientas.';
            elseif ($is_tech)
                echo 'Resumen de asignaciones y reparaciones.';
            else
                echo 'Resumen general de las operaciones del taller.';
            ?>
        </p>
    </div>

    <!-- KPIS GRID -->
    <div class="stats-grid">
        <!-- Cards -->
        <?php
        $cards = [
            [$kpi1_val, $kpi1_label, $kpi1_icon, $kpi1_color, $kpi1_bg],
            [$kpi2_val, $kpi2_label, $kpi2_icon, $kpi2_color, $kpi2_bg],
            [$kpi3_val, $kpi3_label, $kpi3_icon, $kpi3_color, $kpi3_bg],
            [$kpi4_val, $kpi4_label, $kpi4_icon, $kpi4_color, $kpi4_bg]
        ];

        foreach ($cards as $card):
            list($val, $lbl, $icon, $col, $bg) = $card;
            if (empty($lbl))
                continue;

            // Determine URL based on Label
            $cardUrl = '#'; // Default
            $isClickable = true;

            switch ($lbl) {
                case 'Servicios en Taller':
                case 'Equipos en Taller': // Fallback
                    $cardUrl = '../services/index.php?status=active'; // Assuming filter support or just index
                    break;
                case 'Garantías en Taller':
                    $cardUrl = '../warranties/index.php';
                    break;
                case 'Listos para Entrega':
                    $cardUrl = '../equipment/exit.php'; // Or delivery module
                    break;
                case 'Total Entregados':
                    $cardUrl = '../history/index.php';
                    break;
                case 'Mis Asignaciones':
                case 'Equipos Asignados':
                    $cardUrl = '../services/index.php'; // Tech view
                    break;
                default:
                    $isClickable = false;
            }
            ?>
            <?php if ($isClickable): ?>
                <a href="<?php echo $cardUrl; ?>" class="card stat-card"
                    style="text-decoration: none; color: inherit; display: block; transition: transform 0.2s;">
                <?php else: ?>
                    <div class="card stat-card">
                    <?php endif; ?>
                    <div style="height: 100%; display: flex; flex-direction: column; justify-content: center;">
                        <div class="stat-icon" style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                            <i class="ph <?php echo $icon; ?>"></i>
                        </div>
                        <div class="stat-value" style="color: var(--text-main);"><?php echo $val; ?></div>
                        <div class="stat-label" style="color: var(--text-muted);"><?php echo $lbl; ?></div>
                    </div>
                    <?php if ($isClickable): ?>
                </a>
            <?php else: ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- CHARTS ROW -->
<!-- CHARTS ROW -->
<?php if ($is_warehouse): ?>
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
        <!-- Status Chart (Everyone sees this or similar) -->
        <div class="card" style="min-height: 400px;">
            <h3 class="mb-4">Estado de Reparaciones</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Weekly Chart (Reception & Admin only) -->
        <?php if ($is_reception || $is_admin): ?>
            <div class="card" style="min-height: 400px;">
                <h3 class="mb-4">Ingresos de la Semana</h3>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($is_warehouse): ?>
    <!-- Warehouse Charts Row (Single Chart?) -->
    <div style="margin-bottom: 2rem;">
        <div class="card" style="min-height: 400px; max-width: 600px; margin: 0 auto;">
            <h3 class="mb-4">Estado del Inventario</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="statusChart"></canvas> <!-- Reusing statusChart ID -->
            </div>
        </div>
    </div>
<?php endif; ?>


<!-- RECENT ACTIVITY & QUICK ACTIONS -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

    <!-- Recent Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0;">
                <?php echo $recentType == 'tools' ? 'Últimos Préstamos' : 'Actividad Reciente (Servicios y Garantías)'; ?>
            </h3>
            <a href="<?php echo $recentType == 'tools' ? '../tools/assignments.php' : '../services/index.php'; ?>"
                class="btn btn-sm btn-secondary">Ver Todo</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="padding: 0.75rem;">Fecha</th>
                        <?php if ($recentType == 'tools'): ?>
                            <th style="padding: 0.75rem;">Usuario</th>
                            <th style="padding: 0.75rem;">Herramienta</th>
                            <th style="padding: 0.75rem;">Estado</th>
                        <?php else: ?>
                            <th style="padding: 0.75rem;">Tipo</th>
                            <th style="padding: 0.75rem;">Cliente</th>
                            <th style="padding: 0.75rem;">Equipo</th>
                            <th style="padding: 0.75rem;">Estado</th>
                            <th style="padding: 0.75rem; width: 1%; text-align: right;"></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentItems) > 0): ?>
                        <?php foreach ($recentItems as $item): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem;">
                                    <?php
                                    $d = $recentType == 'tools' ? $item['date'] : $item['entry_date'];
                                    echo date('d/m', strtotime($d));
                                    ?>
                                </td>

                                <?php if ($recentType == 'tools'): ?>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($item['user_name']); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td style="padding: 0.75rem;">
                                        <span class="badge"><?php echo ucfirst($item['status']); ?></span>
                                    </td>
                                <?php else: ?>
                                    <td style="padding: 0.75rem;">
                                        <?php if (isset($item['service_type']) && $item['service_type'] == 'warranty'): ?>
                                            <span
                                                style="background: rgba(249, 115, 22, 0.1); color: #f97316; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">GARANTÍA</span>
                                        <?php else: ?>
                                            <span
                                                style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">SERVICIO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($item['client_name']); ?></td>
                                    <td style="padding: 0.75rem;">
                                        <span
                                            class="text-sm text-muted"><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php
                                        $s = $item['status'];
                                        $col = 'gray'; // default
                                        $label2 = $s; // default
                            
                                        $statusMapDA = [
                                            'received' => ['Recibido', 'blue'],
                                            'diagnosing' => ['Diagnóstico', 'yellow'],
                                            'in_repair' => ['Reparación', 'purple'],
                                            'ready' => ['Listo', 'green'],
                                            'delivered' => ['Entregado', 'gray'],
                                            'pending_approval' => ['En Espera', 'orange'],
                                            'cancelled' => ['Cancelado', 'red']
                                        ];

                                        if (isset($statusMapDA[$s])) {
                                            $label2 = $statusMapDA[$s][0];
                                            $col = $statusMapDA[$s][1];
                                        } else {
                                            $label2 = ucfirst($s);
                                        }
                                        ?>
                                        <span
                                            class="status-badge status-<?php echo $col; ?>"><?php echo strtoupper($label2); ?></span>
                                    </td>
                                    <td style="padding: 0.75rem; width: 1%; white-space: nowrap; text-align: right;">
                                        <a href="../services/view.php?id=<?php echo $item['id']; ?>" class="btn-icon">
                                            <i class="ph ph-caret-right"></i>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 2rem; color: var(--text-secondary);">
                                Sin actividad reciente.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column Wrapper -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">

        <?php if ($is_tech || $is_admin || $is_reception): ?>
            <!-- Status Chart (Tech, Admin, Reception) -->
            <div class="card">
                <h3 class="mb-4">Estado de Reparaciones</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_admin || $is_reception): ?>
            <!-- Weekly Chart (Admin, Reception) -->
            <div class="card">
                <h3 class="mb-4">Ingresos de la Semana</h3>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card">
            <h3 class="mb-4">Accesos Rápidos</h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">

                <?php if (!$is_tech && !$is_warehouse): ?>
                    <a href="../clients/add.php" class="btn btn-secondary w-full"
                        style="justify-content: flex-start; padding: 1rem;">
                        <div
                            style="background: rgba(34, 197, 94, 0.2); padding: 8px; border-radius: 8px; margin-right: 0.5rem;">
                            <i class="ph ph-user-plus" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Nuevo Cliente</div>
                            <div class="text-xs text-muted">Agregar cliente</div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($is_warehouse): ?>
                    <a href="../tools/add.php" class="btn btn-secondary w-full"
                        style="justify-content: flex-start; padding: 1rem;">
                        <div
                            style="background: rgba(99, 102, 241, 0.2); padding: 8px; border-radius: 8px; margin-right: 0.5rem;">
                            <i class="ph ph-plus" style="color: var(--primary-500);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Nueva Herramienta</div>
                            <div class="text-xs text-muted">Registrar item</div>
                        </div>
                    </a>
                    <a href="../tools/assign.php" class="btn btn-secondary w-full"
                        style="justify-content: flex-start; padding: 1rem;">
                        <div
                            style="background: rgba(234, 179, 8, 0.2); padding: 8px; border-radius: 8px; margin-right: 0.5rem;">
                            <i class="ph ph-hand-giving" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Asignar / Prestar</div>
                            <div class="text-xs text-muted">Registrar salida</div>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (!$is_warehouse && $is_tech): ?>
                    <a href="../services/index.php" class="btn btn-secondary w-full"
                        style="justify-content: flex-start; padding: 1rem;">
                        <div
                            style="background: rgba(234, 179, 8, 0.2); padding: 8px; border-radius: 8px; margin-right: 0.5rem;">
                            <i class="ph ph-list-checks" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Mis Asignaciones</div>
                            <div class="text-xs text-muted">Ver órdenes</div>
                        </div>
                    </a>

                    <a href="#" class="btn btn-secondary w-full"
                        style="justify-content: flex-start; padding: 1rem;">
                        <div
                            style="background: rgba(99, 102, 241, 0.2); padding: 8px; border-radius: 8px; margin-right: 0.5rem;">
                            <i class="ph ph-map-pin" style="color: var(--primary-500);"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Levantamientos</div>
                            <div class="text-xs text-muted">Garantías técnicas</div>
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<script>
    // Theme Colors
    const isLight = document.body.classList.contains('light-mode');
    const textColor = isLight ? '#475569' : '#cbd5e1';
    const gridColor = isLight ? '#e2e8f0' : 'rgba(255, 255, 255, 0.1)';

    // Status Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($chartCounts); ?>,
                backgroundColor: [
                    '#3b82f6', // Blue
                    '#eab308', // Yellow
                    '#f97316', // Orange
                    '#a855f7', // Purple
                    '#22c55e', // Green
                    '#ef4444'  // Red
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: textColor }
                }
            },
            cutout: '70%'
        }
    });

    <?php if (!$is_warehouse && ($is_reception || $is_admin)): ?>
        // Weekly Chart
        const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctxWeekly, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weeklyLabels); ?>,
                datasets: [{
                    label: 'Equipos Recibidos',
                    data: <?php echo json_encode($weeklyCounts); ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor, precision: 0 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    <?php endif; ?>
</script>

<?php
require_once '../../includes/footer.php';
?>