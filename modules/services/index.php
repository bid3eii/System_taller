<?php
// modules/services/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

// Check permission
if (!can_access_module('services', $pdo)) {
    die("Acceso denegado.");
}

// Handle Technician Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_tech') {
    // Verify that the one who has that module assigned can assign to technician
    // We already checked can_access_module at the top of the file
    if (can_access_module('assign_equipment', $pdo)) {
        $order_id = $_POST['order_id'];
        $tech_id = !empty($_POST['tech_id']) ? $_POST['tech_id'] : null;

        try {
            $stmt = $pdo->prepare("UPDATE service_orders SET assigned_tech_id = ? WHERE id = ?");
            $stmt->execute([$tech_id, $order_id]);

            // Log history and commission logic
            $tech_name = "Sin Asignar";
            if ($tech_id) {
                $stmtT = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmtT->execute([$tech_id]);
                $tech_name = $stmtT->fetchColumn();

                // --- Auto-generate or update commission ---
                $stmt_com = $pdo->prepare("SELECT id FROM comisiones WHERE reference_id = ? AND tipo = 'SERVICIO'");
                $stmt_com->execute([$order_id]);
                $comision = $stmt_com->fetch();

                $stmt_s = $pdo->prepare("
                    SELECT c.name as client_name, e.brand, e.model, so.id, so.display_id, so.service_type, so.payment_status, so.invoice_number
                    FROM service_orders so
                    LEFT JOIN clients c ON so.client_id = c.id
                    LEFT JOIN equipments e ON so.equipment_id = e.id
                    WHERE so.id = ?
                ");
                $stmt_s->execute([$order_id]);
                $serv = $stmt_s->fetch();
                $servicio_desc = trim($serv['brand'] . ' ' . $serv['model']);

                if ($comision) {
                    $stmt_up_c = $pdo->prepare("UPDATE comisiones SET tech_id = ?, vendedor = ? WHERE id = ?");
                    $stmt_up_c->execute([$tech_id, $tech_name, $comision['id']]);
                } else {
                    $initial_status = 'PENDIENTE';
                    $initial_invoice = null;
                    $initial_date = null;
                    
                    if ($serv['payment_status'] === 'pagado' && !empty($serv['invoice_number'])) {
                        $initial_status = 'PAGADA';
                        $initial_invoice = $serv['invoice_number'];
                        $initial_date = date('Y-m-d'); // CURDATE()
                    }

                    $insertC = $pdo->prepare("
                        INSERT INTO comisiones (
                            fecha_servicio, cliente, servicio, cantidad, tipo, vendedor, caso, estado, tech_id, reference_id, factura, fecha_facturacion, fecha_pago
                        ) VALUES (
                            CURDATE(), ?, ?, 1, 'SERVICIO', ?, ?, ?, ?, ?, ?, ?, ?
                        )
                    ");
                    $insertC->execute([
                        $serv['client_name'],
                        $servicio_desc,
                        $tech_name,
                        get_order_number($serv),
                        $initial_status,
                        $tech_id,
                        $order_id,
                        $initial_invoice,
                        $initial_date,
                        $initial_date // fecha_pago = fecha_facturacion
                    ]);
                }
                // ------------------------------------------
            }

            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
            $stmtH->execute([$order_id, "Técnico asignado: " . $tech_name, $_SESSION['user_id'], get_local_datetime()]);

            header("Location: index.php?msg=assigned");
            exit;
        } catch (Exception $e) {
            // Error handling
        }
    }
}

// Fetch Technicians for Dropdown
$technicians = [];
try {
    $stmtTech = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active'");
    $technicians = $stmtTech->fetchAll();
} catch (Exception $e) {
    // Handle error quietly
}

// Permission and Search
$can_view_all = has_permission('module_view_all_entries', $pdo);
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$limit = 50;

// Pagination for Active Services
$page_act = isset($_GET['page_act']) ? (int)$_GET['page_act'] : 1;
if ($page_act < 1) $page_act = 1;
$offset_act = ($page_act - 1) * $limit;

// Pagination for Delivered Services (History)
$page_del = isset($_GET['page_del']) ? (int)$_GET['page_del'] : 1;
if ($page_del < 1) $page_del = 1;
$offset_del = ($page_del - 1) * $limit;

// 1. Fetch Active Services
$whereActive = "WHERE so.service_type = 'service' AND so.status != 'delivered'";
$paramsActive = [];

if (!$can_view_all) {
    $whereActive .= " AND so.assigned_tech_id = ?";
    $paramsActive[] = $_SESSION['user_id'];
}

if ($search) {
    $whereActive .= " AND (c.name LIKE ? OR e.model LIKE ? OR e.serial_number LIKE ? OR so.display_id LIKE ? OR so.owner_name LIKE ?)";
    $srch = "%$search%";
    $paramsActive = array_merge($paramsActive, [$srch, $srch, $srch, $srch, $srch]);
}

// Get Total Count for Active
$countActStmt = $pdo->prepare("SELECT COUNT(*) FROM service_orders so LEFT JOIN clients c ON so.client_id = c.id LEFT JOIN equipments e ON so.equipment_id = e.id $whereActive");
$countActStmt->execute($paramsActive);
$totalActive = $countActStmt->fetchColumn();
$totalPages_act = ceil($totalActive / $limit);
$sqlActive = "
    SELECT 
        so.id, so.status, so.problem_reported, so.entry_date, so.invoice_number, so.assigned_tech_id, so.display_id, so.owner_name, so.payment_status, so.service_type,
        c.name as contact_name, c.phone,
        reg_owner.name as registered_owner_name,
        e.brand, e.model, e.serial_number, e.type,
        tech.username as tech_name
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients reg_owner ON e.client_id = reg_owner.id
    LEFT JOIN users tech ON so.assigned_tech_id = tech.id
    $whereActive
    ORDER BY so.entry_date DESC
    LIMIT $limit OFFSET $offset_act
";
$stmtActive = $pdo->prepare($sqlActive);
$stmtActive->execute($paramsActive);
$activeServices = $stmtActive->fetchAll();

// 2. Fetch Delivered Services with Pagination
$whereDelivered = "WHERE so.service_type = 'service' AND so.status = 'delivered'";
$paramsDelivered = [];

if (!$can_view_all) {
    $whereDelivered .= " AND so.assigned_tech_id = ?";
    $paramsDelivered[] = $_SESSION['user_id'];
}

if ($search) {
    $whereDelivered .= " AND (c.name LIKE ? OR e.model LIKE ? OR e.serial_number LIKE ? OR so.display_id LIKE ? OR so.owner_name LIKE ?)";
    $srch = "%$search%";
    $paramsDelivered = array_merge($paramsDelivered, [$srch, $srch, $srch, $srch, $srch]);
}

// Get Total Count for Delivered
$countDelStmt = $pdo->prepare("SELECT COUNT(*) FROM service_orders so LEFT JOIN clients c ON so.client_id = c.id LEFT JOIN equipments e ON so.equipment_id = e.id $whereDelivered");
$countDelStmt->execute($paramsDelivered);
$totalDelivered = $countDelStmt->fetchColumn();
$totalPages_del = ceil($totalDelivered / $limit);

$sqlDelivered = "
    SELECT 
        so.id, so.status, so.problem_reported, so.entry_date, so.invoice_number, so.assigned_tech_id, so.display_id, so.owner_name, so.payment_status,
        c.name as contact_name, c.phone,
        reg_owner.name as registered_owner_name,
        e.brand, e.model, e.serial_number, e.type,
        tech.username as tech_name
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients reg_owner ON e.client_id = reg_owner.id
    LEFT JOIN users tech ON so.assigned_tech_id = tech.id
    $whereDelivered
    ORDER BY so.entry_date DESC
    LIMIT $limit OFFSET $offset_del
";

$stmtDelivered = $pdo->prepare($sqlDelivered);
$stmtDelivered->execute($paramsDelivered);
$deliveredServices = $stmtDelivered->fetchAll();

$page_title = 'Gestión de Servicios';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Servicios y Reparaciones</h1>
        <p class="text-muted">Gestión de órdenes de servicio estándar.</p>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'assigned'): ?>
        <div class="alert alert-success"
            style="margin-bottom: 1rem; padding: 1rem; border-radius: 8px; background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2);">
            Técnico asignado correctamente.
        </div>
    <?php endif; ?>

    <!-- ACTIVE SERVICES TABLE -->
    <div class="card" style="margin-bottom: 2rem;">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Servicios</h3>
            <div style="display: flex; gap: 1rem;">
                <div class="input-group" style="width: 300px;">
                    <input type="text" id="searchInput" class="form-control"
                        placeholder="Buscar por cliente, equipo, serie...">
                    <i class="ph ph-magnifying-glass input-icon"></i>
                </div>

            </div>
        </div>
        <div class="table-container">
            <table id="activeTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
                            Caso # <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="1">
                            Fecha <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="cliente">
                            Cliente <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="2">
                            Equipo <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="3">
                            No. Serie <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="5">
                            Técnico <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="6">
                            Estado <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <?php if (can_access_module('manage_services', $pdo)): ?>
                        <th>Finanzas</th>
                        <?php endif; ?>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activeServices) > 0): ?>
                        <?php foreach ($activeServices as $item): ?>
                            <tr class="clickable-row" style="cursor: pointer;"
                                onclick="window.location.href='view.php?num=<?php echo urlencode(get_order_number($item)); ?>'">
                                <td>
                                    <strong><?php echo get_order_number($item); ?></strong>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($item['entry_date'])); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars(!empty($item['owner_name']) ? $item['owner_name'] :
                                        (!empty($item['registered_owner_name']) ? $item['registered_owner_name'] :
                                            $item['contact_name']));
                                    ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="text-sm font-medium"><?php echo htmlspecialchars($item['serial_number']); ?></span>
                                </td>
                                <!-- Assigned Technician -->
                                <td onclick="event.stopPropagation();">
                                    <?php if ($item['tech_name']): ?>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span
                                                style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.5rem; background: var(--bg-hover); border-radius: 6px; font-size: 0.85rem;">
                                                <i class="ph ph-user-circle"></i>
                                                <?php echo htmlspecialchars($item['tech_name']); ?>
                                            </span>
                                            <?php if (can_access_module('assign_equipment', $pdo)): ?>
                                                <button type="button" class="btn-icon" style="padding: 2px;" title="Cambiar Técnico"
                                                    onclick="openAssignModal('<?php echo $item['id']; ?>', '<?php echo $item['assigned_tech_id']; ?>')">
                                                    <i class="ph ph-pencil-simple" style="font-size: 0.9rem;"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if (can_access_module('assign_equipment', $pdo)): ?>
                                            <button type="button" class="btn btn-sm btn-secondary"
                                                onclick="openAssignModal('<?php echo $item['id']; ?>', '<?php echo $item['assigned_tech_id']; ?>')">
                                                <i class="ph ph-user-plus"></i> Asignar
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted text-sm">-</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'received' => 'blue',
                                        'diagnosing' => 'yellow',
                                        'pending_approval' => 'orange',
                                        'in_repair' => 'purple',
                                        'ready' => 'green',
                                        'delivered' => 'gray',
                                        'cancelled' => 'red'
                                    ];
                                    $color = $statusColors[$item['status']] ?? 'gray';

                                    $statusLabels = [
                                        'received' => 'Recibido',
                                        'diagnosing' => 'En Revisión',
                                        'pending_approval' => 'En Espera',
                                        'in_repair' => 'En Proceso',
                                        'ready' => 'Listo',
                                        'delivered' => 'Entregado',
                                        'cancelled' => 'Cancelado'
                                    ];
                                    $label = $statusLabels[$item['status']] ?? $item['status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $color; ?>">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <?php if (can_access_module('manage_services', $pdo)): ?>
                                <td>
                                    <?php
                                    $paymentMaps = [
                                        'pendiente' => ['Pendiente', 'gray'],
                                        'pagado' => ['Pagado', 'green']
                                    ];
                                    $pCol = $paymentMaps[$item['payment_status']][1] ?? 'gray';
                                    $pLbl = $paymentMaps[$item['payment_status']][0] ?? $item['payment_status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $pCol; ?>">
                                        <?php echo strtoupper($pLbl); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td style="text-align: right;" onclick="event.stopPropagation();">
                                    <?php if (can_access_module('manage_services', $pdo)): ?>
                                        <a href="manage.php?id=<?php echo $item['id']; ?>" class="btn btn-primary"
                                            style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                                            <i class="ph ph-gear"></i> Gestionar
                                        </a>
                                    <?php else: ?>
                                        <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary"
                                            style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                                            <i class="ph ph-eye"></i> Ver Detalle
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-wrench" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay servicios activos</h3>
                                <p class="text-muted">Los servicios y reparaciones en curso aparecerán aquí.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination for Active Services -->
        <?php if ($totalPages_act > 1): ?>
            <div style="padding: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card); border-radius: 0 0 12px 12px;">
                <?php 
                $start = max(1, $page_act - 2);
                $end = min($totalPages_act, $page_act + 2);
                $qStr = "&search=".urlencode($search)."&page_del=".$page_del;
                
                if ($page_act > 1): ?>
                    <a href="?page_act=1<?php echo $qStr; ?>" class="btn btn-sm btn-secondary">«</a>
                    <a href="?page_act=<?php echo $page_act - 1; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page_act=<?php echo $i; ?><?php echo $qStr; ?>" class="btn btn-sm <?php echo $i == $page_act ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $i == $page_act ? 'pointer-events: none;' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page_act < $totalPages_act): ?>
                    <a href="?page_act=<?php echo $page_act + 1; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">›</a>
                    <a href="?page_act=<?php echo $totalPages_act; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">»</a>
                <?php endif; ?>
            </div>
            <div style="text-align: center; padding-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted); background: var(--bg-card);">
                Mostrando <?php echo count($activeServices); ?> de <?php echo $totalActive; ?> servicios activos
            </div>
        <?php endif; ?>
    </div>

    <!-- DELIVERED HISTORY TABLE -->
    <div class="card">
        <div
            style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Historial de Entregados</h3>
            <div class="input-group" style="width: 250px;">
                <input type="text" id="searchHistoryInput" class="form-control" placeholder="Buscar en historial..."
                    style="font-size: 0.9rem;">
                <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
                            Caso # <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="1">
                            Fecha <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="cliente">
                            Cliente <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="2">
                            Equipo <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="3">
                            No. Serie <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th>Falla Reportada</th>
                        <th class="sortable" data-column="5">
                            Técnico <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <th class="sortable" data-column="6">
                            Estado <i class="ph ph-caret-up-down sort-icon"></i>
                        </th>
                        <?php if (can_access_module('manage_services', $pdo)): ?>
                        <th>Finanzas</th>
                        <?php endif; ?>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($deliveredServices) > 0): ?>
                        <?php foreach ($deliveredServices as $item): ?>
                            <tr class="clickable-row" style="opacity: 0.7; cursor: pointer;"
                                onclick="window.location.href='view.php?num=<?php echo urlencode(get_order_number($item)); ?>'">
                                <td>
                                    <strong><?php echo get_order_number($item); ?></strong>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($item['entry_date'])); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars(!empty($item['owner_name']) ? $item['owner_name'] :
                                        (!empty($item['registered_owner_name']) ? $item['registered_owner_name'] :
                                            $item['contact_name']));
                                    ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="text-sm font-medium"><?php echo htmlspecialchars($item['serial_number']); ?></span>
                                </td>
                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                    title="<?php echo htmlspecialchars($item['problem_reported']); ?>">
                                    <?php echo htmlspecialchars($item['problem_reported']); ?>
                                </td>
                                <td>
                                    <?php if ($item['tech_name']): ?>
                                        <span class="text-sm"><?php echo htmlspecialchars($item['tech_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-gray">Entregado</span>
                                </td>
                                <?php if (can_access_module('manage_services', $pdo)): ?>
                                <td>
                                    <?php
                                    $paymentMaps = [
                                        'pendiente' => ['Pendiente', 'gray'],
                                        'pagado' => ['Pagado', 'green']
                                    ];
                                    $pCol = $paymentMaps[$item['payment_status']][1] ?? 'gray';
                                    $pLbl = $paymentMaps[$item['payment_status']][0] ?? $item['payment_status'];
                                    ?>
                                    <span class="status-badge status-<?php echo $pCol; ?>">
                                        <?php echo strtoupper($pLbl); ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td style="text-align: right;" onclick="event.stopPropagation();">
                                    <a href="manage.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary"
                                        style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                                        <i class="ph ph-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 2rem;">
                                <p class="text-muted">No hay historial de equipos entregados.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI for History -->
        <?php if ($totalPages_del > 1): ?>
            <div style="padding: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; border-top: 1px solid var(--border-color); background: var(--bg-card);">
                <?php 
                $start = max(1, $page_del - 2);
                $end = min($totalPages_del, $page_del + 2);
                $qStr = "&search=".urlencode($search)."&page_act=".$page_act;
                
                if ($page_del > 1): ?>
                    <a href="?page_del=1<?php echo $qStr; ?>" class="btn btn-sm btn-secondary">«</a>
                    <a href="?page_del=<?php echo $page_del - 1; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">‹</a>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page_del=<?php echo $i; ?><?php echo $qStr; ?>" class="btn btn-sm <?php echo $i == $page_del ? 'btn-primary' : 'btn-secondary'; ?>" style="<?php echo $i == $page_del ? 'pointer-events: none;' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page_del < $totalPages_del): ?>
                    <a href="?page_del=<?php echo $page_del + 1; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">›</a>
                    <a href="?page_del=<?php echo $totalPages_del; ?><?php echo $qStr; ?>" class="btn btn-sm btn-secondary">»</a>
                <?php endif; ?>
            </div>
            <div style="text-align: center; padding-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted); background: var(--bg-card);">
                Mostrando <?php echo count($deliveredServices); ?> de <?php echo $totalDelivered; ?> servicios entregados
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Tech Modal -->
<div id="assignModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
    <div
        style="background: var(--bg-card); padding: 1.5rem; border-radius: 12px; width: 400px; max-width: 90%; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Asignar Técnico</h3>

        <form method="POST">
            <input type="hidden" name="action" value="assign_tech">
            <input type="hidden" name="order_id" id="assignOrderId">

            <div class="form-group">
                <label>Seleccionar Técnico:</label>
                <select name="tech_id" id="assignTechId" class="form-control" style="width: 100%;">
                    <option value="">-- Sin Asignar --</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
    th {
        white-space: nowrap;
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
        content: "\f196";
        /* ph-caret-up */
    }

    .sortable.desc .sort-icon::before {
        content: "\f194";
        /* ph-caret-down */
    }

    .clickable-row {
        transition: background-color 0.2s;
    }

    .clickable-row:hover {
        background-color: var(--bg-hover);
    }

    .clickable-row:hover td {
        color: var(--text-primary);
    }

    /* Fix Select Arrow Positioning */
    select.form-control {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 16px 12px;
        padding-right: 2.5rem;
    }
</style>

<script>
    // Search Functionality
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('activeTable');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                // Skip "No data" rows
                if (row.cells.length > 1) {
                    if (text.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
    });

    // History Search Functionality
    document.getElementById('searchHistoryInput').addEventListener('keyup', function () {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('historyTable');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                // Skip "No data" rows
                if (row.cells.length > 1) {
                    if (text.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
    });

    // Modern Sorting Functionality for Both Tables
    function setupTableSorting(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const sortableHeaders = table.querySelectorAll('.sortable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        let originalRows = rows.filter(row => !row.querySelector('td[colspan]'));
        let currentSortColumn = null;
        let currentSortDirection = null;

        function sortTable(columnIndex, direction) {
            const sortedRows = [...originalRows].sort((rowA, rowB) => {
                const cellA = rowA.querySelectorAll('td')[columnIndex];
                const cellB = rowB.querySelectorAll('td')[columnIndex];

                if (!cellA || !cellB) return 0;

                let textA = cellA.textContent.trim().toLowerCase();
                let textB = cellB.textContent.trim().toLowerCase();

                // Try to parse as numbers for numeric sorting
                const numA = parseFloat(textA);
                const numB = parseFloat(textB);

                if (!isNaN(numA) && !isNaN(numB)) {
                    return direction === 'asc' ? numA - numB : numB - numA;
                }

                // Alphabetical sorting
                if (direction === 'asc') {
                    return textA.localeCompare(textB, 'es');
                } else {
                    return textB.localeCompare(textA, 'es');
                }
            });

            originalRows = sortedRows;
            sortedRows.forEach(row => tbody.appendChild(row));

            sortableHeaders.forEach(header => {
                header.classList.remove('asc', 'desc');
            });

            const activeHeader = table.querySelector(`.sortable[data-column="${columnIndex}"]`);
            if (activeHeader) {
                activeHeader.classList.add(direction);
            }
        }

        sortableHeaders.forEach(header => {
            header.addEventListener('click', function () {
                const columnIndex = parseInt(this.dataset.column);

                let direction = 'asc';
                if (currentSortColumn === columnIndex) {
                    if (currentSortDirection === 'asc') {
                        direction = 'desc';
                    } else if (currentSortDirection === 'desc') {
                        direction = null;
                        currentSortColumn = null;
                        currentSortDirection = null;
                        sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
                        return;
                    }
                }

                currentSortColumn = columnIndex;
                currentSortDirection = direction;
                sortTable(columnIndex, direction);
            });
        });
    }

    // Initialize sorting for both tables
    setupTableSorting('activeTable');
    setupTableSorting('historyTable');

    function openAssignModal(orderId, currentTechId) {
        document.getElementById('assignOrderId').value = orderId;
        document.getElementById('assignTechId').value = currentTechId || '';
        document.getElementById('assignModal').style.display = 'flex';
    }

    function closeAssignModal() {
        document.getElementById('assignModal').style.display = 'none';
    }

    // Close on outside click
    document.getElementById('assignModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeAssignModal();
        }
    });
</script>

<?php
require_once '../../includes/footer.php';
?>