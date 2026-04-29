<?php
// modules/reports/index.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

// Check permissions
$can_view_all = can_access_module('view_all_entries', $pdo);
$params = [];
$where_clauses = ["so.problem_reported NOT LIKE 'Garant%a Registrada'"];

if (!$can_view_all) {
    $where_clauses[] = "so.assigned_tech_id = ?";
    $params[] = $_SESSION['user_id'];
}

// Fetch all service orders with relevant details
$sql = "
    SELECT 
        so.id, 
        so.display_id,
        so.service_type,
        TRIM(so.status) as status, 
        so.entry_date, 
        so.diagnosis_number,
        so.owner_name,
        c.id as client_id,
        c.name as contact_name, 
        c.phone as client_phone,
        reg_owner.name as registered_owner_name,
        e.brand, 
        e.model, 
        e.type as equipment_type,
        e.serial_number,
        so.assigned_tech_id,
        u.full_name as tech_name_full, u.username as tech_username
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients reg_owner ON e.client_id = reg_owner.id
    LEFT JOIN users u ON so.assigned_tech_id = u.id
    WHERE " . implode(" AND ", $where_clauses) . "
    ORDER BY so.entry_date DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los datos: " . $e->getMessage());
}

// Fetch Technicians for filter
$techs = $pdo->query("SELECT u.id, u.username, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name LIKE '%técnico%' OR r.name LIKE '%tecnico%' ORDER BY u.username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group orders by client for multi-equipment delivery detection
$clientGroups = [];
foreach ($orders as $order) {
    $clientId = $order['client_id'];
    if (!isset($clientGroups[$clientId])) {
        $clientGroups[$clientId] = [];
    }
    // Only include ready/delivered orders for multi-print
    if (in_array($order['status'], ['ready', 'delivered'])) {
        $clientGroups[$clientId][] = $order['id'];
    }
}

$page_title = 'Reportes e Impresiones';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="main-content" style="padding: 2.5rem; overflow: visible;">
    <div class="page-header" style="margin-bottom: 2.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="width: 48px; height: 48px; background: rgba(var(--primary-rgb), 0.1); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                <i class="ph-fill ph-chart-bar" style="font-size: 24px; color: var(--primary);"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-size: 1.8rem; font-weight: 700; margin: 0; letter-spacing: -0.5px;">Centro de Reportes</h1>
            </div>
        </div>
        <p class="text-muted" style="margin: 0; padding-left: 4rem; opacity: 0.8;">Gestión centralizada de documentación e impresión de servicios.</p>
    </div>

    <!-- Filters & Search -->
    <div class="premium-filter-bar">
        <div class="search-input-wrapper input-group">
            <i class="ph ph-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="premium-input search-box" placeholder="Buscar por cliente, equipo, ID...">
        </div>
        
        <div class="select-wrapper">
            <select id="statusFilter" class="premium-select">
                <option value="all">Todos los Estados</option>
                <option value="pending">Pendientes</option>
                <option value="diagnosing">En Diagnóstico</option>
                <option value="in_repair">En Reparación</option>
                <option value="replaced">Reemplazados</option>
                <option value="ready">Listos</option>
                <option value="delivered">Entregados</option>
            </select>
            <i class="ph ph-caret-down select-caret"></i>
        </div>

        <div class="select-wrapper">
            <select id="typeFilter" class="premium-select">
                <option value="all">Todos los Tipos</option>
                <option value="service">Servicio Técnico</option>
                <option value="warranty">Garantía</option>
            </select>
            <i class="ph ph-caret-down select-caret"></i>
        </div>

        <div class="select-wrapper">
            <select id="techFilter" class="premium-select">
                <option value="all">Todos los Técnicos</option>
                <?php foreach($techs as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name'] ?: $t['username']); ?></option>
                <?php endforeach; ?>
            </select>
            <i class="ph ph-caret-down select-caret"></i>
        </div>

        <div class="input-group-date">
            <div class="date-input-wrapper">
                <label class="date-label">Desde</label>
                <input type="date" id="startDate" class="premium-input-date">
            </div>
            <div class="date-input-wrapper">
                <label class="date-label">Hasta</label>
                <input type="date" id="endDate" class="premium-input-date">
            </div>
        </div>

        <button onclick="exportToExcel()" class="premium-btn-success" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; white-space: nowrap;">
            <i class="ph ph-microsoft-excel-logo" style="font-size: 1.2rem;"></i>
            <span>Exportar Excel</span>
        </button>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="reportsTable">
                    <thead>
                        <tr>
                            <th class="sortable text-center" data-column="0" style="width: 80px; min-width: 80px;">
                                ID <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable text-center" data-column="1" style="width: 110px; min-width: 110px;">
                                Fecha <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-column="2" style="width: 180px; min-width: 180px;">
                                Cliente <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-column="3" style="width: 180px; min-width: 180px;">
                                Equipo <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-column="4" style="width: 100px;">
                                Tipo <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-column="5" style="width: 120px;">
                                Estado <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="sortable" data-column="7" style="width: 130px;">
                                Técnico <i class="ph ph-caret-up-down sort-icon"></i>
                            </th>
                            <th class="text-end" style="width: 110px; min-width: 110px;">Imprimir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                                <tr data-status="<?php echo strtolower($order['status']); ?>" 
                                    data-type="<?php echo strtolower($order['service_type']); ?>" 
                                    data-date="<?php echo date('Y-m-d', strtotime($order['entry_date'])); ?>"
                                    data-tech="<?php echo $order['assigned_tech_id'] ?? 'none'; ?>"
                                    data-serial="<?php echo strtolower($order['serial_number'] ?? ''); ?>">
                                    <td class="text-center"><span class="badge-tag"><?php echo get_order_number($order); ?></span></td>
                                    <td class="text-center"><?php echo date('d/m/Y', strtotime($order['entry_date'])); ?></td>
                                <td style="max-width: 180px;">
                                    <div class="fw-medium" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php 
                                            echo htmlspecialchars(!empty($order['owner_name']) ? $order['owner_name'] : 
                                                 (!empty($order['registered_owner_name']) ? $order['registered_owner_name'] : 
                                                 $order['contact_name'])); 
                                        ?>">
                                        <?php 
                                            echo htmlspecialchars(!empty($order['owner_name']) ? $order['owner_name'] : 
                                                 (!empty($order['registered_owner_name']) ? $order['registered_owner_name'] : 
                                                 $order['contact_name'])); 
                                        ?>
                                    </div>
                                    <div class="text-xs text-muted"><?php echo htmlspecialchars($order['client_phone']); ?></div>
                                </td>
                                <td style="max-width: 220px;">
                                    <div class="fw-medium" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>">
                                        <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>
                                    </div>
                                    <div class="text-xs text-muted" style="opacity: 0.7;">S/N: <?php echo htmlspecialchars($order['serial_number'] ?: '-'); ?></div>
                                </td>
                                <td>
                                    <?php if ($order['service_type'] == 'warranty'): ?>
                                        <span class="badge badge-warning">Garantía</span>
                                    <?php else: ?>
                                        <span class="badge badge-blue">Servicio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $statusLabels = [
                                        'pending' => ['Pendiente', 'warning'],
                                        'received' => ['Recibido', 'warning'],
                                        'diagnosing' => ['Diagnosticado', 'info'],
                                        'pending_approval' => ['En Espera', 'orange'],
                                        'approved' => ['Aprobado', 'primary'],
                                        'in_repair' => ['En Proceso', 'purple'],
                                        'ready' => ['Listo', 'success'],
                                        'replaced' => ['Reemplazo', 'pink'],
                                        'delivered' => ['Entregado', 'secondary'],
                                    ];
                                    $st = $statusLabels[$order['status']] ?? [$order['status'], 'secondary'];
                                    ?>
                                    <span class="status-badge status-<?php echo $st[1]; ?>">
                                        <?php echo $st[0]; ?>
                                    </span>
                                </td>
                                <td><?php echo ($order['tech_name_full'] || $order['tech_username']) ? htmlspecialchars($order['tech_name_full'] ?: $order['tech_username']) : '<span class="text-muted">-</span>'; ?></td>
                                <td class="text-end" style="padding-right: 1.5rem;">
                                    <div class="report-dropdown">
                                        <button class="premium-action-btn" onclick="toggleReportDropdown(this)">
                                            <i class="ph-fill ph-printer"></i>
                                        </button>
                                        <div class="report-dropdown-menu">
                                            <div class="dropdown-header">DOCUMENTOS</div>
                                            <a href="../equipment/print_entry.php?num=<?php echo urlencode(get_order_number($order)); ?>" class="dropdown-item">
                                                <i class="ph-fill ph-file-text" style="color: var(--primary);"></i> Hoja de Entrada
                                            </a>
                                            
                                            <?php if (!empty($order['diagnosis_number'])): ?>
                                            <a href="../services/print_diagnosis.php?num=<?php echo urlencode(get_order_number($order)); ?>" class="dropdown-item">
                                                <i class="ph-fill ph-stethoscope" style="color: #a855f7;"></i> Diagnóstico
                                            </a>
                                            <?php endif; ?>

                                            <?php if (in_array($order['status'], ['repaired', 'delivered', 'ready'])): ?>
                                            <a href="../equipment/print_delivery.php?num=<?php echo urlencode(get_order_number($order)); ?>" class="dropdown-item">
                                                <i class="ph-fill ph-check-circle" style="color: #10b981;"></i> Hoja de Salida
                                            </a>
                                            <?php endif; ?>

                                            <?php 
                                            // Show "Print All" if client has multiple ready/delivered equipment
                                            $clientId = $order['client_id'];
                                            if (isset($clientGroups[$clientId]) && count($clientGroups[$clientId]) > 1): 
                                                $allIds = implode(',', $clientGroups[$clientId]);
                                            ?>
                                            <div style="border-top: 1px solid var(--border-color); margin: 0.5rem 0;"></div>
                                            <a href="../equipment/print_delivery_multi.php?ids=<?php echo $allIds; ?>" class="dropdown-item" style="background: rgba(16, 185, 129, 0.05);">
                                                <i class="ph-fill ph-stack" style="color: #10b981;"></i> Imprimir Todos (<?php echo count($clientGroups[$clientId]); ?>)
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No se encontraron registros.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* PREMIUM UI SYSTEM */
.premium-filter-bar {
    display: grid;
    grid-template-columns: 1.5fr 1fr 0.8fr 1fr 1.5fr 1fr;
    gap: 0.75rem;
    background: rgba(var(--bg-card-rgb), 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: 18px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

@media (max-width: 1200px) {
    .premium-filter-bar {
        grid-template-columns: 1fr 1fr 1fr;
        grid-template-rows: auto auto;
    }
}

@media (max-width: 768px) {
    .premium-filter-bar {
        grid-template-columns: 1fr;
    }
}

.input-group-date {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.date-input-wrapper {
    position: relative;
    flex: 1;
}

.date-label {
    position: absolute;
    top: -8px;
    left: 10px;
    background: #1e1e2d; /* Match your card background */
    padding: 0 5px;
    font-size: 0.65rem;
    color: var(--text-muted);
    z-index: 1;
    border-radius: 4px;
}

body.light-mode .date-label {
    background: white;
}

.premium-input-date {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.6rem 0.5rem;
    color: var(--text-main);
    font-size: 0.85rem;
    transition: all 0.3s ease;
    color-scheme: dark; /* Forces white calendar icon in dark mode */
}

.premium-input-date::-webkit-calendar-picker-indicator {
    cursor: pointer;
    filter: invert(0.8) brightness(1.2); /* Makes it pop */
}

.premium-input-date:focus {
    border-color: var(--primary);
    outline: none;
    background: rgba(var(--primary-rgb), 0.05);
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-icon {
    position: absolute;
    left: 1.25rem;
    color: var(--text-muted);
    font-size: 1.2rem;
    pointer-events: none;
}

.premium-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 1rem 0.85rem 3.5rem;
    color: var(--text-main);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.premium-input:focus {
    background: rgba(var(--primary-rgb), 0.05);
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1);
    outline: none;
}

.select-wrapper {
    position: relative;
}

.premium-select {
    width: 100%;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.85rem 3rem 0.85rem 1.25rem;
    color: var(--text-main);
    font-size: 0.95rem;
    appearance: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.premium-select:focus {
    border-color: var(--primary);
    outline: none;
}

.premium-select option {
    background-color: #1a1c23; /* Dark background for options */
    color: white; /* White text for options */
}

/* Light mode overrides for select dropdowns */
body.light-mode .premium-select {
    background-color: white;
    color: var(--slate-900);
    border-color: var(--slate-300);
}

body.light-mode .premium-select option {
    background-color: white;
    color: var(--slate-900);
}

body.light-mode .premium-select:hover {
    border-color: var(--primary-500);
    background-color: var(--slate-50);
}

.select-caret {
    position: absolute;
    right: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary); /* Bright primary color for visibility */
    pointer-events: none;
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Table Enhancements */
.table-responsive {
    border-radius: 20px;
    overflow-x: auto !important;
    display: block;
    width: 100%;
    border: 1px solid var(--border-color);
    background: rgba(var(--bg-card-rgb), 0.2);
    position: relative;
    /* VERY VISIBLE SCROLLBAR FOR USER */
    scrollbar-width: auto;
    scrollbar-color: var(--primary) rgba(var(--primary-rgb), 0.1);
}

.table-responsive::-webkit-scrollbar {
    height: 12px !important; /* Thick scrollbar */
    display: block !important;
}

.table-responsive::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
    border: 3px solid transparent;
    background-clip: content-box;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: var(--primary-600);
}

.table {
    border-radius: 20px;
    width: 100% !important;
    min-width: 1000px !important; /* Reduced from 1300px since we removed columns */
    table-layout: auto !important;
}

.table thead th {
    background: rgba(255,255,255,0.02);
    padding: 1.25rem 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    white-space: nowrap;
}

.table tbody tr {
    position: relative; /* For proper z-index stacking */
}


.badge-tag {
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-light);
    border: 1px solid rgba(var(--primary-rgb), 0.2);
    padding: 4px 10px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
}

/* Action Dropdown Modern */
.premium-action-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.premium-action-btn:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 8px 15px rgba(var(--primary-rgb), 0.25);
    border-color: var(--primary);
}

/* Light mode overrides for action buttons */
body.light-mode .premium-action-btn {
    background: rgba(var(--primary-rgb), 0.05);
    border-color: var(--slate-300);
    color: var(--slate-700);
}

body.light-mode .premium-action-btn i {
    color: var(--slate-700) !important;
}

body.light-mode .premium-action-btn:hover {
    background: rgba(var(--primary-rgb), 0.15);
    color: var(--slate-900);
    transform: scale(1.1);
    box-shadow: 0 8px 15px rgba(var(--primary-rgb), 0.25);
    border-color: var(--primary) !important;
}

body.light-mode .premium-action-btn:hover i {
    color: var(--slate-900) !important;
}

.report-dropdown {
    position: relative;
    display: inline-block;
}

.report-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: rgba(var(--bg-card-rgb), 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
    min-width: 240px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 99999; /* Higher than navbar */
    padding: 0.75rem;
}

/* Default: open upward (for bottom rows) */
.report-dropdown-menu {
    bottom: calc(100% + 10px);
    animation: slideUp 0.3s ease-out;
}

/* When at top: open downward */
.report-dropdown.open-downward .report-dropdown-menu {
    bottom: auto;
    top: calc(100% + 10px);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.report-dropdown.active .report-dropdown-menu {
    display: block;
}

.dropdown-header {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--text-muted);
    padding: 0.5rem 1rem;
    letter-spacing: 1.5px;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text-main);
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.2s ease;
    font-size: 0.95rem;
    font-weight: 500;
}

.dropdown-item:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(5px);
}

.dropdown-item i {
    font-size: 1.2rem;
}

.table {
    border-radius: 20px;
}

.report-dropdown {
    position: relative;
    z-index: 1;
}

.report-dropdown.active {
    z-index: 9999;
}

/* Additional fix for table rows */
.table tbody tr {
    position: relative;
}

.table tbody td {
    position: relative;
}

/* Sortable Column Headers */
.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: all 0.2s;
}

.sortable:hover {
    background-color: var(--bg-hover);
    color: var(--primary-500);
}

.sort-icon {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    opacity: 0.4;
    transition: all 0.2s;
}

.sortable:hover .sort-icon {
    opacity: 0.7;
}

.sortable.asc .sort-icon,
.sortable.desc .sort-icon {
    opacity: 1;
    color: var(--primary-500);
}

.sortable.asc .sort-icon::before {
    content: "\f196"; /* ph-caret-up */
}

.sortable.desc .sort-icon::before {
    content: "\f194"; /* ph-caret-down */
}
</style>

<script>
// Table Sorting and Filtering Functionality
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const typeFilter = document.getElementById('typeFilter');
const techFilter = document.getElementById('techFilter'); // Added techFilter
const startDate = document.getElementById('startDate'); // Renamed from startDateInput
const endDate = document.getElementById('endDate');     // Renamed from endDateInput
const table = document.getElementById('reportsTable');
const sortableHeaders = document.querySelectorAll('.sortable');
const tbody = table.querySelector('tbody');
const rows = Array.from(tbody.querySelectorAll('tr'));

// Store original rows (excluding "no results" row)
let originalRows = rows.filter(row => !row.querySelector('td[colspan]'));
let currentSortColumn = null;
let currentSortDirection = null;

// Sorting functionality
function sortTable(columnIndex, direction) {
    const sortedRows = [...originalRows].sort((rowA, rowB) => {
        const cellA = rowA.querySelectorAll('td')[columnIndex];
        const cellB = rowB.querySelectorAll('td')[columnIndex];
        
        if (!cellA || !cellB) return 0;
        
        let textA = cellA.textContent.trim().toLowerCase();
        let textB = cellB.textContent.trim().toLowerCase();
        
        // Date parsing for DD/MM/YYYY
        const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        const matchA = textA.match(dateRegex);
        const matchB = textB.match(dateRegex);
        
        if (matchA && matchB) {
            // Convert to YYYYMMDD for easy numeric comparison
            const dateValueA = parseInt(matchA[3] + matchA[2] + matchA[1]);
            const dateValueB = parseInt(matchB[3] + matchB[2] + matchB[1]);
            return direction === 'asc' ? dateValueA - dateValueB : dateValueB - dateValueA;
        }

        // Try to parse as numbers for numeric sorting
        const numA = parseFloat(textA);
        const numB = parseFloat(textB);
        
        if (!isNaN(numA) && !isNaN(numB) && !textA.includes('/') && !textB.includes('/')) {
            return direction === 'asc' ? numA - numB : numB - numA;
        }
        
        // Alphabetical sorting
        if (direction === 'asc') {
            return textA.localeCompare(textB, 'es');
        } else {
            return textB.localeCompare(textA, 'es');
        }
    });
    
    // Update originalRows to maintain sort order
    originalRows = sortedRows;
    
    // Re-append rows in sorted order
    sortedRows.forEach(row => tbody.appendChild(row));
    
    // Update header indicators
    sortableHeaders.forEach(header => {
        header.classList.remove('asc', 'desc');
    });
    
    const activeHeader = document.querySelector(`.sortable[data-column="${columnIndex}"]`);
    if (activeHeader) {
        activeHeader.classList.add(direction);
    }
    
    // Re-apply filters after sorting
    filterTable();
}

// Add click handlers to sortable headers
sortableHeaders.forEach(header => {
    header.addEventListener('click', function() {
        const columnIndex = parseInt(this.dataset.column);
        
        // Determine sort direction
        let direction = 'asc';
        if (currentSortColumn === columnIndex) {
            if (currentSortDirection === 'asc') {
                direction = 'desc';
            } else if (currentSortDirection === 'desc') {
                // Reset to original order
                direction = null;
                currentSortColumn = null;
                currentSortDirection = null;
                
                // Remove all sort classes
                sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
                filterTable();
                return;
            }
        }
        
        currentSortColumn = columnIndex;
        currentSortDirection = direction;
        
        sortTable(columnIndex, direction);
    });
});

// Filter functionality
function filterTable() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value.toLowerCase();
    const typeValue = typeFilter.value.toLowerCase();
    const techValue = techFilter.value.toLowerCase(); // Added techValue
    const start = startDate.value;
    const end = endDate.value;

    originalRows.forEach(row => { // Use originalRows for filtering
        if(row.cells.length < 2) return;

        const rowText = row.innerText.toLowerCase();
        const rowStatus = row.getAttribute('data-status');
        const rowType = row.getAttribute('data-type');
        const rowDate = row.getAttribute('data-date'); // YYYY-MM-DD
        const rowTech = row.getAttribute('data-tech'); // Added rowTech
        const rowSerial = row.getAttribute('data-serial'); // Added rowSerial

        const matchesSearch = rowText.includes(searchTerm) || (rowSerial && rowSerial.includes(searchTerm)); // Include serial in search
        const matchesType = typeValue === 'all' || rowType === typeValue;
        const matchesTech = techValue === 'all' || rowTech === techValue; // Added tech filter

        // Date range filter
        let matchesDate = true;
        if (start && rowDate < start) matchesDate = false;
        if (end && rowDate > end) matchesDate = false;
        
        // Logical filter for status
        let matchesStatus = false;
        if (statusValue === 'all') {
            matchesStatus = true;
        } else {
            if (rowStatus === statusValue) {
                matchesStatus = true;
            }
        }

        if (matchesSearch && matchesStatus && matchesType && matchesTech && matchesDate) { // Added matchesTech
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    // Assuming updateCounter() is defined elsewhere or will be added.
    // updateCounter(); 
}

searchInput.addEventListener('input', filterTable); // Changed to 'input' for real-time
statusFilter.addEventListener('change', filterTable);
typeFilter.addEventListener('change', filterTable);
techFilter.addEventListener('change', filterTable); // Added techFilter listener
startDate.addEventListener('change', filterTable);
endDate.addEventListener('change', filterTable);

// Dropdown Handler with Smart Positioning
function toggleReportDropdown(btn) {
    const dropdown = btn.parentElement;
    const isCurrentlyActive = dropdown.classList.contains('active');
    
    // Close all other dropdowns
    document.querySelectorAll('.report-dropdown.active').forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('active', 'open-downward');
        }
    });
    
    if (isCurrentlyActive) {
        // Close this dropdown
        dropdown.classList.remove('active', 'open-downward');
    } else {
        // Open this dropdown
        dropdown.classList.add('active');
        
        // Wait for dropdown to render to get accurate measurements
        setTimeout(() => {
            const btnRect = btn.getBoundingClientRect();
            const dropdownMenu = dropdown.querySelector('.report-dropdown-menu');
            
            if (dropdownMenu) {
                const dropdownHeight = dropdownMenu.offsetHeight;
                const viewportHeight = window.innerHeight;
                
                // Calculate space available above and below
                const spaceAbove = btnRect.top - 70; // 70px for navbar
                const spaceBelow = viewportHeight - btnRect.bottom;
                
                // Choose direction based on available space
                // Prefer downward if there's enough space, otherwise upward
                if (spaceBelow >= dropdownHeight + 20) {
                    // Enough space below, open downward
                    dropdown.classList.add('open-downward');
                } else if (spaceAbove >= dropdownHeight + 20) {
                    // Not enough space below but enough above, open upward
                    dropdown.classList.remove('open-downward');
                } else {
                    // Not enough space either way, choose the one with more space
                    if (spaceBelow > spaceAbove) {
                        dropdown.classList.add('open-downward');
                    } else {
                        dropdown.classList.remove('open-downward');
                    }
                }
            }
        }, 10);
    }
    
    event.stopPropagation();
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.report-dropdown')) {
        document.querySelectorAll('.report-dropdown.active').forEach(d => {
            d.classList.remove('active', 'open-downward');
        });
    }
});
// Auto-Refresh Logic (10s)
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTbody = doc.querySelector('#reportsTable tbody');
            const currentTbody = document.querySelector('#reportsTable tbody');
            
            // We also need to update originalRows since filtering depends on it
            if(newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
                
                // Update implementation-specific variables
                // Use rows from the ACTIVE DOM (currentTbody) to prevent duplication during sort
                originalRows = Array.from(currentTbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]')); 
                
                // Re-apply current filters
                filterTable();
            }
        })
        .catch(err => console.error('Error refreshing reports:', err));
}, 10000); // 10 seconds

// Export to Excel Function
window.exportToExcel = function() {
    const search = searchInput.value;
    const status = statusFilter.value;
    const type = typeFilter.value;
    const tech = techFilter.value; // Added tech
    const start = startDate.value;
    const end = endDate.value;
    
    let url = `export.php?search=${encodeURIComponent(search)}&status=${status}&type=${type}&tech_id=${tech}&start=${start}&end=${end}`;
    window.location.href = url;
};
</script>

<style>
/* New Button Style if not already present */
.premium-btn-primary {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0 1.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.premium-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(var(--primary-rgb), 0.4);
    background: var(--primary-600);
}

.premium-btn-success {
    background: #10b981;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0 1.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.premium-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(16, 185, 129, 0.4);
    background: #059669;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
