<?php
// modules/dashboard/index.php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

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

$is_admin = in_array($role_id, [1, 7]);
$is_reception = ($role_id == 4);
$is_tech = ($role_id == 3);
$is_warehouse = ($role_id == 5);

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

// Handle Sorting for Technician Column
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'asc' : 'desc';
$nextOrder = ($order == 'asc') ? 'desc' : 'asc';
$sortIcon = ($order == 'asc') ? 'ph-caret-up' : 'ph-caret-down';

// --- DATA FETCHING LOGIC ---

// --- DATA FETCHING LOGIC ---

if ($is_warehouse) {
    // --- WAREHOUSE VIEW (REGISTRO DE BODEGA) ---
    $recentType = 'warranties';

    // KPI 1: Registros de Hoy
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM warranties w JOIN service_orders so ON w.service_order_id = so.id WHERE DATE(so.entry_date) = ?");
    $stmt->execute([$today]);
    $kpi1_val = $stmt->fetchColumn();
    $kpi1_label = "Registros de Hoy";
    $kpi1_icon = "ph-calendar-plus";

    // KPI 2: En Stock (Bodega)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM warranties w 
        JOIN service_orders so ON w.service_order_id = so.id 
        JOIN clients c ON so.client_id = c.id 
        WHERE c.name = 'Bodega - Inventario'
    ");
    $kpi2_val = $stmt->fetchColumn();
    $kpi2_label = "En Stock";
    $kpi2_icon = "ph-package";

    // KPI 3: Vendidos / Garantías
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM warranties w 
        JOIN service_orders so ON w.service_order_id = so.id 
        JOIN clients c ON so.client_id = c.id 
        WHERE c.name != 'Bodega - Inventario'
    ");
    $kpi3_val = $stmt->fetchColumn();
    $kpi3_label = "Vendidos / Garantías";
    $kpi3_icon = "ph-shopping-cart";

    // Chart: Warranty Status Data (Overall Vigentes vs Expired)
    $stmt = $pdo->query("SELECT SUM(CASE WHEN status = 'active' AND (end_date >= CURDATE() OR end_date IS NULL) THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'expired' OR end_date < CURDATE() THEN 1 ELSE 0 END) as expired FROM warranties");
    $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
    $whActiveCount = $statusData['active'] ?? 0;
    $whExpiredCount = $statusData['expired'] ?? 0;
    
    $chartData = [
        'active' => $whActiveCount,
        'expired' => $whExpiredCount
    ];

    // Recent: Last Warranty Entries
    $stmt = $pdo->query("
        SELECT 
            w.product_code, 
            w.purchase_origin,
            c.name as client_name, 
            e.brand, e.model, e.serial_number,
            so.entry_date as date
        FROM warranties w
        JOIN service_orders so ON w.service_order_id = so.id
        JOIN clients c ON so.client_id = c.id
        JOIN equipments e ON so.equipment_id = e.id
        ORDER BY so.entry_date DESC
        LIMIT 10
    ");
    $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        WHERE so.service_type = 'warranty' 
        AND so.status NOT IN ('delivered', 'cancelled')
        AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
    ";
    if (!$can_view_all)
        $awSql .= " AND so.assigned_tech_id = " . intval($user_id);
    $stmt = $pdo->query($awSql);
    $active_warranties = $stmt->fetchColumn();

    // Stats for Display
    if (!$can_view_all) {
        // --- TECH NEW KPIs ---
        // KPI 1: Pendientes de Diagnóstico
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.assigned_tech_id = ? 
            AND so.status IN ('received', 'diagnosing', 'pending_approval')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ");
        $stmt->execute([$user_id]);
        $kpi1_val = $stmt->fetchColumn();
        $kpi1_label = "Pendientes Diagnóstico";
        $kpi1_icon = "ph-warning-circle";
        $kpi1_color = "var(--warning)";
        $kpi1_bg = "rgba(234, 179, 8, 0.1)";
    } else {
        $kpi1_val = $active_services;
        $kpi1_label = "Servicios en Taller";
        $kpi1_icon = "ph-wrench";
        $kpi1_color = "var(--primary-500)";
        $kpi1_bg = "rgba(99, 102, 241, 0.1)";
    }
    $kpi1_color = "var(--primary-500)";
    $kpi1_bg = "rgba(99, 102, 241, 0.1)";

    if (!$can_view_all) {
        // KPI 2: En Reparación
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.assigned_tech_id = ? AND so.status IN ('in_repair', 'ready')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ");
        $stmt->execute([$user_id]);
        $kpi2_val = $stmt->fetchColumn();
        $kpi2_label = "En Reparación (Activos)";
        $kpi2_icon = "ph-tools";
        $kpi2_color = "var(--primary-500)";
        $kpi2_bg = "rgba(99, 102, 241, 0.1)";

        // KPI 3: Listos esta semana (Productividad)
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.assigned_tech_id = ? AND so.status IN ('ready', 'delivered') AND so.exit_date >= ?
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ");
        $stmt->execute([$user_id, $startOfWeek]);
        $kpi3_val = $stmt->fetchColumn();
        $kpi3_label = "Completos esta semana";
        $kpi3_icon = "ph-trend-up";
        $kpi3_color = "var(--success)";
        $kpi3_bg = "rgba(34, 197, 94, 0.1)";

        // KPI 4: Reingresos / Garantías Activas (Prioridad)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM service_orders so
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.assigned_tech_id = ? AND so.service_type = 'warranty' AND so.status NOT IN ('delivered', 'cancelled')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ");
        $stmt->execute([$user_id]);
        $kpi4_val = $stmt->fetchColumn();
        $kpi4_label = "Garantías Activas (Urgentes)";
        $kpi4_icon = "ph-warning-octagon";
        $kpi4_color = "var(--danger)";
        $kpi4_bg = "rgba(239, 68, 68, 0.1)";
    } else {
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
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ";
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
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ";
        $stmt = $pdo->query($k4Sql);
        $kpi4_val = $stmt->fetchColumn();
        $kpi4_label = "Total Entregados";
        $kpi4_icon = "ph-check-circle";
        $kpi4_color = "var(--purple-500)";
        $kpi4_bg = "rgba(168, 85, 247, 0.1)";
    }

    // Status Distribution Chart
    $statusSql = "
        SELECT so.status, COUNT(*) as count 
        FROM service_orders so
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE so.status NOT IN ('delivered', 'cancelled')
        AND (w.product_code IS NULL OR w.product_code = '') 
        AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
    ";
    if (!$can_view_all) {
        // Users without view_all_entries only see their assigned orders
        $statusSql .= " AND so.assigned_tech_id = " . intval($user_id);
    }
    $statusSql .= " GROUP BY so.status";
    $statusData = $pdo->query($statusSql)->fetchAll(PDO::FETCH_KEY_PAIR);

    // Recent Activity Table / Tech Pipeline
    // Techs ALWAYS see only their assigned orders
    // For Admin/Reception, 'view_all_entries' permission controls visibility
    // --- PAGINATION LOGIC ---
    $itemsPerPage = 12;
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;

    // Count Total (excluding delivered)
    $countSql = "
        SELECT COUNT(*) 
        FROM service_orders so
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE (w.product_code IS NULL OR w.product_code = '') 
        AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        AND so.status != 'delivered'
    ";
    if (!$can_view_all) {
        $countSql .= " AND so.assigned_tech_id = " . intval($user_id);
    }
    $totalRecords = $pdo->query($countSql)->fetchColumn();
    $totalPages = ceil($totalRecords / $itemsPerPage);

    $recentSql = "
        SELECT 
            so.id, so.entry_date, so.status, so.service_type, so.display_id, so.owner_name, 
            c.name as client_name, 
            reg_owner.name as registered_owner_name,
            e.brand, e.model, 
            tech.username as tech_name,
            DATEDIFF(NOW(), so.entry_date) as days_in_shop
        FROM service_orders so
        LEFT JOIN clients c ON so.client_id = c.id
        LEFT JOIN equipments e ON so.equipment_id = e.id
        LEFT JOIN clients reg_owner ON e.client_id = reg_owner.id
        LEFT JOIN warranties w ON so.id = w.service_order_id
        LEFT JOIN users tech ON so.assigned_tech_id = tech.id
        WHERE (w.product_code IS NULL OR w.product_code = '') 
        AND so.problem_reported NOT LIKE 'Garant%a Registrada'
        AND so.status != 'delivered'
    ";

    if (!$can_view_all) {
        $recentSql .= " AND so.assigned_tech_id = " . intval($user_id);
    }

    // Apply Sorting
    if ($sort == 'tech') {
        $recentSql .= " ORDER BY tech_name " . ($order == 'asc' ? 'ASC' : 'DESC');
    } else {
        $recentSql .= " ORDER BY so.entry_date " . ($order == 'asc' ? 'ASC' : 'DESC');
    }

    $recentSql .= " LIMIT $itemsPerPage OFFSET $offset";

    $recentItems = $pdo->query($recentSql)->fetchAll();

    // --- NEW: FETCH TECH AGENDA ---
    $upcomingVisits = [];
    if ($is_tech) {
        $stmtVisits = $pdo->prepare("
            SELECT se.*, ps.title as survey_title 
            FROM schedule_events se
            LEFT JOIN project_surveys ps ON se.survey_id = ps.id
            WHERE se.tech_id = ? 
            AND se.start_datetime >= CURDATE()
            ORDER BY se.start_datetime ASC
            LIMIT 5
        ");
        $stmtVisits->execute([$user_id]);
        $upcomingVisits = $stmtVisits->fetchAll();
    }
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
// Warehouse doesn't need this graph
if (!$is_warehouse) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        if ($is_tech) {
            // For Techs: Chart shows "Equipos Completados" (Productivity)
            $wSql = "
                SELECT COUNT(*) 
                FROM service_orders so
                LEFT JOIN warranties w ON so.id = w.service_order_id
                WHERE DATE(so.exit_date) = ? AND so.status IN ('ready', 'delivered')
                AND so.assigned_tech_id = ?
                AND (w.product_code IS NULL OR w.product_code = '') 
                AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
            ";
            $stmtDaily = $pdo->prepare($wSql);
            $stmtDaily->execute([$date, $user_id]);
        } else {
            // For Admin/Rec: Chart shows "Ingresos de la Semana"
            $wSql = "
                SELECT COUNT(*) 
                FROM service_orders so
                LEFT JOIN warranties w ON so.id = w.service_order_id
                WHERE DATE(so.entry_date) = ?
                AND (w.product_code IS NULL OR w.product_code = '') 
                AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
            ";
            if (!$can_view_all) {
                $wSql .= " AND so.assigned_tech_id = " . intval($user_id);
            }
            $stmtDaily = $pdo->prepare($wSql);
            $stmtDaily->execute([$date]);
        }

        $weeklyLabels[] = date('d/m', strtotime($date));
        $weeklyCounts[] = $stmtDaily->fetchColumn();
    }
}

    // Technician Workload Chart (Admin / Reception only)
    $techLabels = [];
    $techCounts = [];
    if ($is_admin || $is_reception) {
        $tSql = "
            SELECT u.username, COUNT(so.id) as total 
            FROM service_orders so
            JOIN users u ON so.assigned_tech_id = u.id
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.status NOT IN ('delivered', 'cancelled', 'ready')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
            GROUP BY u.id, u.username
            ORDER BY total DESC
        ";
        $techData = $pdo->query($tSql)->fetchAll();
        foreach ($techData as $row) {
            $techLabels[] = $row['username'];
            $techCounts[] = (int)$row['total'];
        }

        // Default Load (Current Month)
        $p_start = '';
        $p_end = '';
        $p_tech = 'all';

        // Fetch techs for filter dropdown
        $techs_for_filter = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 ORDER BY username ASC")->fetchAll();

        $prodLabels = [];
        $prodCounts = [];
        $pSql = "
            SELECT u.username, COUNT(so.id) as total 
            FROM service_orders so
            JOIN users u ON so.assigned_tech_id = u.id
            LEFT JOIN warranties w ON so.id = w.service_order_id
            WHERE so.status IN ('ready', 'delivered')
            AND (w.product_code IS NULL OR w.product_code = '') 
            AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
        ";
        $pParams = [];
        if (!empty($p_start)) {
            $pSql .= " AND so.exit_date >= ?";
            $pParams[] = $p_start . " 00:00:00";
        }
        if (!empty($p_end)) {
            $pSql .= " AND so.exit_date <= ?";
            $pParams[] = $p_end . " 23:59:59";
        }
        if ($p_tech !== 'all') {
            $pSql .= " AND u.id = ?";
            $pParams[] = $p_tech;
        }
        $pSql .= " GROUP BY u.id, u.username ORDER BY total DESC";
        
        $stmtProd = $pdo->prepare($pSql);
        $stmtProd->execute($pParams);
        $prodData = $stmtProd->fetchAll();

        foreach ($prodData as $row) {
            $prodLabels[] = $row['username'];
            $prodCounts[] = (int)$row['total'];
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
    <?php if ($is_warehouse): ?>
        <?php include 'warehouse_ui.php'; ?>
    <?php else: ?>
        <div class="stats-grid">
            <!-- Cards -->
            <?php
            $cards = [
                [$kpi1_val, $kpi1_label, $kpi1_icon, $kpi1_color, $kpi1_bg, 'card-premium-blue'],
                [$kpi2_val, $kpi2_label, $kpi2_icon, $kpi2_color, $kpi2_bg, 'card-premium-orange'],
                [$kpi3_val, $kpi3_label, $kpi3_icon, $kpi3_color, $kpi3_bg, 'card-premium-green'],
                [$kpi4_val, $kpi4_label, $kpi4_icon, $kpi4_color, $kpi4_bg, 'card-premium-purple']
            ];

            foreach ($cards as $card):
                list($val, $lbl, $icon, $col, $bg, $premiumClass) = $card;
                if (empty($lbl))
                    continue;

                // Determine URL based on Label
                $cardUrl = '#'; // Default
                $isClickable = true;

                switch ($lbl) {
                    case 'Servicios en Taller':
                    case 'Equipos en Taller':
                    case 'Pendientes Diagnóstico':
                        $cardUrl = '../services/index.php?status=active';
                        break;
                    case 'Garantías en Taller':
                    case 'En Reparación (Activos)':
                    case 'Garantías Activas (Urgentes)':
                        $cardUrl = '../warranties/index.php';
                        break;
                    case 'Listos para Entrega':
                    case 'Completos esta semana':
                        $cardUrl = '../equipment/exit.php';
                        break;
                    case 'Total Entregados':
                        $cardUrl = '../history/index.php';
                        break;
                    default:
                        $isClickable = false;
                }
                ?>
                <?php if ($isClickable): ?>
                    <a href="<?php echo $cardUrl; ?>" class="card stat-card <?php echo $premiumClass; ?>"
                        style="text-decoration: none; color: inherit; display: block;">
                    <?php else: ?>
                        <div class="card stat-card <?php echo $premiumClass; ?>">
                        <?php endif; ?>
                        
                        <div class="stat-icon" style="background: <?php echo $bg; ?>; color: <?php echo $col; ?>;">
                            <i class="ph <?php echo $icon; ?>"></i>
                        </div>
                        <div class="stat-value"><?php echo $val; ?></div>
                        <div class="stat-label"><?php echo $lbl; ?></div>
                        
                        <?php if ($isClickable): ?>
                    </a>
                <?php else: ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <!-- TECH QUICK ACTIONS -->
    <?php if (!$is_warehouse): ?>
        <?php if ($is_tech): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <a href="../services/index.php" class="card btn btn-secondary" style="margin: 0; padding: 1.25rem; flex-direction: row; justify-content: flex-start; background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div style="background: rgba(234, 179, 8, 0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-right: 1rem;">
                        <i class="ph ph-list-checks" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">Mis Asignaciones</div>
                        <div class="text-sm text-muted">Ver y buscar órdenes</div>
                    </div>
                </a>

                <a href="../levantamientos/index.php" class="card btn btn-secondary" style="margin: 0; padding: 1.25rem; flex-direction: row; justify-content: flex-start; background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div style="background: rgba(99, 102, 241, 0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-right: 1rem;">
                        <i class="ph ph-clipboard" style="color: var(--primary-500); font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">Levantamientos</div>
                        <div class="text-sm text-muted">Requerimientos de proyectos</div>
                    </div>
                </a>
            </div>

            <!-- TECH AGENDA WIDGET -->
            <?php if (!empty($upcomingVisits)): ?>
                <div class="card mb-6" style="border-left: 4px solid var(--primary-500); background: linear-gradient(to right, rgba(99, 102, 241, 0.05), var(--bg-card));">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="ph-fill ph-calendar-check" style="color: var(--primary-500);"></i>
                            Mi Agenda: Próximas Visitas
                        </h3>
                        <a href="../tech_agenda/index.php" class="btn btn-sm btn-secondary">Ver Mi Hoja de Ruta</a>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                        <?php foreach ($upcomingVisits as $visit): ?>
                            <div style="background: rgba(255, 255, 255, 0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color); display: flex; gap: 1rem; align-items: center;">
                                <div style="background: var(--primary-600); color: white; padding: 0.5rem; border-radius: 10px; min-width: 60px; text-align: center;">
                                    <div style="font-size: 0.7rem; font-weight: 700; opacity: 0.8;"><?php echo strtoupper(date('M', strtotime($visit['start_datetime']))); ?></div>
                                    <div style="font-size: 1.25rem; font-weight: 800;"><?php echo date('d', strtotime($visit['start_datetime'])); ?></div>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 1rem; color: var(--text-primary);"><?php echo htmlspecialchars($visit['title']); ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
                                        <i class="ph ph-clock"></i> <?php echo date('h:i A', strtotime($visit['start_datetime'])); ?>
                                        <?php if (!empty($visit['location'])): ?>
                                            <span style="opacity: 0.5;">•</span>
                                            <i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($visit['location']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="../tech_agenda/index.php" class="btn-icon" title="Ver Hoja de Ruta"><i class="ph ph-arrow-right"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-6 text-center" style="padding: 2rem; border-style: dashed; border-color: var(--border-color); background: transparent;">
                    <i class="ph ph-calendar-blank" style="font-size: 3rem; color: var(--text-secondary); opacity: 0.3; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-secondary);">No tienes visitas programadas</h3>
                    <p class="text-muted">¡Buen trabajo! No hay visitas para los próximos días.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <!-- ADMIN / RECEPTION QUICK ACTIONS -->
    <?php if ($is_admin || $is_reception): ?>
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <a href="../clients/add.php" class="card btn btn-secondary"
                style="margin: 0; padding: 1.25rem; flex-direction: row; justify-content: flex-start; background: var(--bg-card); border: 1px solid var(--border-color);">
                <div
                    style="background: rgba(34, 197, 94, 0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-right: 1rem;">
                    <i class="ph ph-user-plus" style="color: var(--success); font-size: 1.5rem;"></i>
                </div>
                <div style="text-align: left;">
                    <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">Nuevo Cliente</div>
                    <div class="text-sm text-muted">Registro rápido</div>
                </div>
            </a>

            <a href="../equipment/entry.php" class="card btn btn-secondary"
                style="margin: 0; padding: 1.25rem; flex-direction: row; justify-content: flex-start; background: var(--bg-card); border: 1px solid var(--border-color);">
                <div
                    style="background: rgba(99, 102, 241, 0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-right: 1rem;">
                    <i class="ph ph-file-plus" style="color: var(--primary-500); font-size: 1.5rem;"></i>
                </div>
                <div style="text-align: left;">
                    <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">Nueva Orden</div>
                    <div class="text-sm text-muted">Ingresar equipo</div>
                </div>
            </a>

            <?php if ($is_admin): ?>
                <a href="../tools/index.php" class="card btn btn-secondary"
                    style="margin: 0; padding: 1.25rem; flex-direction: row; justify-content: flex-start; background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div
                        style="background: rgba(234, 179, 8, 0.2); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; margin-right: 1rem;">
                        <i class="ph ph-toolbox" style="color: var(--warning); font-size: 1.5rem;"></i>
                    </div>
                    <div style="text-align: left;">
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 1.1rem;">Inventario</div>
                        <div class="text-sm text-muted">Control de herramientas</div>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- RECENT ACTIVITY & QUICK ACTIONS -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">

        <!-- Recent Table / Pipeline -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-fill ph-clock-counter-clockwise" style="color: var(--primary);"></i>
                    <?php echo $recentType == 'tools' ? 'Últimos Préstamos' : 'Actividad Reciente'; ?>
                </h3>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="<?php echo $recentType == 'tools' ? '../tools/assignments.php' : '../services/index.php'; ?>"
                        class="btn btn-sm btn-secondary">Ver Todo</a>
                </div>
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
                                <th style="padding: 0.75rem;">
                                    <a href="?sort=tech&order=<?php echo $nextOrder; ?>"
                                        style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 0.3rem;">
                                        Técnico
                                        <?php if ($sort == 'tech'): ?>
                                            <i class="ph <?php echo $sortIcon; ?>"
                                                style="font-size: 0.8rem; color: var(--primary);"></i>
                                        <?php else: ?>
                                            <i class="ph ph-caret-up-down" style="font-size: 0.8rem; opacity: 0.3;"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
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
                                        <td style="padding: 0.75rem;">
                                            <?php
                                            echo htmlspecialchars(!empty($item['owner_name']) ? $item['owner_name'] :
                                                (!empty($item['registered_owner_name']) ? $item['registered_owner_name'] :
                                                    $item['client_name']));
                                            ?>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <span
                                                class="text-sm text-muted"><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">
                                                <i class="ph ph-user-circle" style="color: var(--slate-400);"></i>
                                                <span
                                                    style="color: var(--text-secondary);"><?php echo htmlspecialchars($item['tech_name'] ?? '---'); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <?php
                                            $s = $item['status'];
                                            $col = 'gray'; // default
                                            $label2 = $s; // default
                                            $is_delayed = isset($item['days_in_shop']) && $item['days_in_shop'] > 7 && !in_array($s, ['ready', 'delivered', 'cancelled']);

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
                                            <div style="display: flex; gap: 0.6rem; align-items: center; white-space: nowrap;">
                                                <span class="status-badge status-<?php echo $col; ?>"><?php echo strtoupper($label2); ?></span>
                                                
                                                <?php if ($is_delayed && ($is_admin || $is_reception)): ?>
                                                    <div class="premium-tooltip-wrapper">
                                                        <span class="alert-pulse" style="color: var(--danger); font-size: 1.1rem; display: flex; align-items: center; cursor: help;">
                                                            <i class="ph-fill ph-warning-circle"></i>
                                                        </span>
                                                        <div class="premium-tooltip">
                                                            Lleva <strong style="color: var(--danger);"><?php echo $item['days_in_shop']; ?> días</strong> en taller
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem; width: 1%; white-space: nowrap; text-align: right;">
                                            <?php 
                                            $module = ($item['service_type'] == 'warranty') ? 'warranties' : 'services';
                                            ?>
                                            <a href="../<?php echo $module; ?>/view.php?num=<?php echo urlencode(get_order_number($item)); ?>" class="btn-icon">
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

            <!-- Pagination Controls -->
            <?php if ($recentType != 'tools' && $totalPages >= 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding: 0.5rem 0; border-top: 1px solid var(--border-color);">
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                        Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?> 
                        <span style="margin-left: 0.5rem; opacity: 0.5;">(Total: <?php echo $totalRecords; ?> registros)</span>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?><?php echo $sort != 'date' ? '&sort='.$sort : ''; ?><?php echo $order != 'desc' ? '&order='.$order : ''; ?>" 
                                   class="btn btn-sm btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="ph ph-arrow-left"></i> Anterior
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled style="opacity: 0.5; padding: 0.4rem 0.8rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="ph ph-arrow-left"></i> Anterior
                                </button>
                            <?php endif; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?><?php echo $sort != 'date' ? '&sort='.$sort : ''; ?><?php echo $order != 'desc' ? '&order='.$order : ''; ?>" 
                                   class="btn btn-sm btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;">
                                    Siguiente <i class="ph ph-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled style="opacity: 0.5; padding: 0.4rem 0.8rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;">
                                    Siguiente <i class="ph ph-arrow-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

            <?php if ($is_admin || $is_reception || $is_tech): ?>
                <!-- Weekly Chart -->
                <div class="card">
                    <h3 class="mb-4"><?php echo $is_tech ? 'Mi Rendimiento (Completados)' : 'Ingresos de la Semana'; ?></h3>
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($is_admin || $is_reception): ?>
                <!-- Tech Workload Chart -->
                <div class="card">
                    <h3 class="mb-4">Carga por Técnico (Equipos Activos)</h3>
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="techWorkloadChart"></canvas>
                    </div>
                </div>

                <!-- Tech Productivity Chart -->
                <div class="card" style="position: relative; overflow: visible;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0;">Productividad por Técnico (Equipos Hechos)</h3>
                        <div style="position: relative;">
                            <button id="prodFilterBtn" onclick="toggleProdFilter(event)" class="btn btn-sm btn-secondary" title="Filtrar por Fecha" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem;">
                                <i class="ph ph-funnel" id="prodFilterIcon"></i>
                                <span id="prodFilterBadge" style="display: none; font-size: 0.75rem; font-weight: 600; color: var(--primary-500);">Filtrado</span>
                            </button>

                            <!-- Dropdown de Filtro (Absoluto al botón) -->
                            <div id="prodFilterModal" class="filter-dropdown-card animate-pop" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                    <h4 style="margin: 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="ph ph-funnel" style="color: var(--primary-500);"></i> Filtros
                                    </h4>
                                    <button onclick="toggleProdFilter(event)" class="btn-icon" style="width: 24px; height: 24px;"><i class="ph ph-x"></i></button>
                                </div>
                                <div id="prodFilterForm">
                                    <div style="margin-bottom: 1rem;">
                                        <label style="display: block; margin-bottom: 0.4rem; font-size: 0.85rem; font-weight: 600; opacity: 0.9;">Técnico</label>
                                        <select id="f_tech" class="form-control" style="width: 100%; font-size: 0.9rem; padding: 0.5rem; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: white;">
                                            <option value="all" style="background: #0f172a; color: white;">Todos los Técnicos</option>
                                            <?php foreach ($techs_for_filter as $tf): ?>
                                                <option value="<?php echo $tf['id']; ?>" style="background: #0f172a; color: white;">
                                                    <?php echo htmlspecialchars($tf['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                            <div>
                                                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.8rem; font-weight: 600; opacity: 0.9;">Desde</label>
                                                <input type="date" id="f_start" class="form-control" style="width: 100%; font-size: 0.8rem; padding: 0.5rem; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);">
                                            </div>
                                            <div>
                                                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.8rem; font-weight: 600; opacity: 0.9;">Hasta</label>
                                                <input type="date" id="f_end" class="form-control" style="width: 100%; font-size: 0.8rem; padding: 0.5rem; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);">
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.75rem;">
                                        <button onclick="applyProductivityFilter()" class="btn btn-primary" style="flex: 2; padding: 0.6rem; font-size: 0.9rem; border-radius: 10px; font-weight: 600;">Aplicar Filtro</button>
                                        <button onclick="clearProductivityFilter()" class="btn btn-secondary" style="flex: 1; padding: 0.6rem; font-size: 0.9rem; border-radius: 10px;">Limpiar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="prodActiveFilters" style="display: none; margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-secondary); flex-direction: column; gap: 0.25rem;">
                        <!-- JS Dynamic Content -->
                    </div>
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="techProductivityChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; // End !$is_warehouse check ?>

<script>
    // Theme Colors
    const isLight = document.body.classList.contains('light-mode');
    const textColor = isLight ? '#475569' : '#cbd5e1';
    const gridColor = isLight ? '#e2e8f0' : 'rgba(255, 255, 255, 0.1)';

    // Status Chart
    <?php if (!$is_warehouse): ?>
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
    <?php endif; ?>

    <?php if (!$is_warehouse): ?>
    // Weekly Chart (Total entries or Tech productivity)
    <?php if ($is_reception || $is_admin || $is_tech): ?>
    const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
    new Chart(ctxWeekly, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklyLabels); ?>,
            datasets: [{
                label: '<?php echo $is_tech ? "Equipos Listos" : "Equipos Recibidos"; ?>',
                data: <?php echo json_encode($weeklyCounts); ?>,
                backgroundColor: '<?php echo $is_tech ? "#22c55e" : "#6366f1"; ?>',
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

    // Tech Workload Chart (Admin/Reception only)
    <?php if ($is_reception || $is_admin): ?>
    const ctxTech = document.getElementById('techWorkloadChart').getContext('2d');
    
    // Create Gradient
    const techGradient = ctxTech.createLinearGradient(0, 0, 400, 0);
    techGradient.addColorStop(0, '#f97316'); // Orange-500
    techGradient.addColorStop(1, '#fb923c'); // Orange-400

    new Chart(ctxTech, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($techLabels); ?>,
            datasets: [{
                label: 'Equipos Asignados',
                data: <?php echo json_encode($techCounts); ?>,
                backgroundColor: techGradient,
                hoverBackgroundColor: '#ea580c', // Orange-600
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 32
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { left: 10, right: 30 }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { 
                        color: gridColor,
                        drawBorder: false
                    },
                    ticks: { 
                        color: textColor, 
                        precision: 0,
                        font: { family: "'Inter', sans-serif", size: 11 }
                    }
                },
                y: {
                    grid: { display: false },
                    ticks: { 
                        color: textColor,
                        font: { 
                            family: "'Inter', sans-serif", 
                            size: 13,
                            weight: '500'
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)', // Slate-900
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return ` ${context.raw} equipos activos`;
                        }
                    }
                }
            }
        }
    });

    <?php endif; ?>
    <?php endif; // End !$is_warehouse ?>
</script>

<style>
.filter-dropdown-card {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.75rem;
    z-index: 100;
    width: 320px; /* Increased width to fix overflow */
    background: #0f172a; /* Darker background */
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(12px);
}
.filter-dropdown-card h4, 
.filter-dropdown-card label {
    color: white !important;
}

.filter-dropdown-card input[type="date"],
.filter-dropdown-card select {
    color: white !important;
    color-scheme: dark;
}

.animate-pop {
    animation: modalPop 0.2s ease-out;
}
@keyframes modalPop {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<script>
function toggleProdFilter(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('prodFilterModal');
    const isVisible = dropdown.style.display === 'block';
    dropdown.style.display = isVisible ? 'none' : 'block';
}

// Close when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('prodFilterModal');
    if (dropdown && dropdown.style.display === 'block') {
        if (!dropdown.contains(e.target) && e.target.id !== 'prodFilterBtn') {
            dropdown.style.display = 'none';
        }
    }
});

// Logic for AJAX Productivity Chart
let productivityChart = null;

function initProductivityChart(labels, counts) {
    const ctxProd = document.getElementById('techProductivityChart').getContext('2d');
    
    // Get colors from body class directly to be safe
    const isL = document.body.classList.contains('light-mode');
    const tColor = isL ? '#475569' : '#cbd5e1';
    const gColor = isL ? '#e2e8f0' : 'rgba(255, 255, 255, 0.1)';

    const prodGradient = ctxProd.createLinearGradient(0, 0, 400, 0);
    prodGradient.addColorStop(0, '#22c55e');
    prodGradient.addColorStop(1, '#4ade80');

    if (productivityChart) productivityChart.destroy();

    productivityChart = new Chart(ctxProd, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Equipos Hechos',
                data: counts,
                backgroundColor: prodGradient,
                hoverBackgroundColor: '#16a34a',
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 32
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 30 } },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: gColor, drawBorder: false },
                    ticks: { color: tColor, precision: 0, font: { family: "'Inter', sans-serif", size: 11 } }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: tColor, font: { family: "'Inter', sans-serif", size: 13, weight: '500' } }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) { return ` ${context.raw} equipos hechos`; }
                    }
                }
            }
        }
    });
}

function applyProductivityFilter() {
    const start = document.getElementById('f_start').value;
    const end = document.getElementById('f_end').value;
    const tech = document.getElementById('f_tech').value;
    const techName = document.getElementById('f_tech').options[document.getElementById('f_tech').selectedIndex].text;
    const btn = document.querySelector('[onclick="applyProductivityFilter()"]');
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-circle-notch animate-spin"></i> Filtrando...';
    btn.disabled = true;

    // Use dedicated AJAX file
    const url = `ajax_productivity.php?p_start=${start}&p_end=${end}&p_tech=${tech}`;

    fetch(url)
        .then(async res => {
            const isJson = res.headers.get('content-type')?.includes('application/json');
            const data = isJson ? await res.json() : null;

            if (!res.ok) {
                throw new Error(data?.error || `HTTP Error ${res.status}`);
            }
            return data;
        })
        .then(data => {
            if (!data) throw new Error("No data received");
            initProductivityChart(data.labels, data.counts);
            
            // Update UI
            document.getElementById('prodFilterIcon').style.color = 'var(--primary-500)';
            document.getElementById('prodFilterBadge').style.display = 'inline';
            
            let info = '';
            if (start || end) {
                const sF = start ? start.split('-').reverse().join('/') : 'Inicio';
                const eF = end ? end.split('-').reverse().join('/') : 'Fin';
                info += `<div style="display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-calendar"></i> Rango: <strong>${sF} - ${eF}</strong></div>`;
            }
            if (tech !== 'all') {
                info += `<div style="display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-user"></i> Técnico: <strong>${techName}</strong></div>`;
            }
            
            const infoDiv = document.getElementById('prodActiveFilters');
            infoDiv.innerHTML = info;
            infoDiv.style.display = info ? 'flex' : 'none';
            
            document.getElementById('prodFilterModal').style.display = 'none';
        })
        .catch(err => {
            console.error(err);
            alert("Error: " + err.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function clearProductivityFilter() {
    document.getElementById('f_start').value = '';
    document.getElementById('f_end').value = '';
    document.getElementById('f_tech').value = 'all';
    
    applyProductivityFilter();
    
    // Reset UI
    document.getElementById('prodFilterIcon').style.color = '';
    document.getElementById('prodFilterBadge').style.display = 'none';
    document.getElementById('prodActiveFilters').style.display = 'none';
}

// Initial Load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('techProductivityChart')) {
        initProductivityChart(<?php echo json_encode($prodLabels); ?>, <?php echo json_encode($prodCounts); ?>);
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>
