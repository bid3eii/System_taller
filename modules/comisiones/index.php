<?php
// modules/comisiones/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Comisiones';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$is_admin = can_access_module('comisiones_add', $pdo); // using comisiones_add as proxy for admin in this module
$user_id = $_SESSION['user_id'];

// Filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tech_filter = strval($_GET['tech_id'] ?? '');

// Build query (admin sees both sections always; status_filter only applies to tech view)
$params = [];
$where = [];

if (!$is_admin) {
    // Regular techs only see their own
    $where[] = "c.tech_id = ?";
    $params[] = $user_id;
} else if ($tech_filter !== '') {
    // Admin filtering by tech
    $where[] = "c.tech_id = ?";
    $params[] = $tech_filter;
}

if ($search) {
    // Also include service_orders and project_surveys invoice_number in search
    $where[] = "(c.cliente LIKE ? OR c.servicio LIKE ? OR c.caso LIKE ? OR c.factura LIKE ? OR so.invoice_number LIKE ? OR ps.invoice_number LIKE ?)";
    $srch = "%$search%";
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
}

// For tech view only, apply status filter
if (!$is_admin && $status_filter) {
    $where[] = "c.estado = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt_str = "SELECT c.*, u.username as tech_name,
                    so.display_id, so.service_type,
                    CASE 
                        WHEN c.tipo = 'SERVICIO' AND so.invoice_number IS NOT NULL AND so.invoice_number != '' THEN so.invoice_number
                        WHEN c.tipo = 'PROYECTO' AND ps.invoice_number IS NOT NULL AND ps.invoice_number != '' THEN ps.invoice_number
                        ELSE c.factura 
                    END as computed_factura
             FROM comisiones c 
             LEFT JOIN users u ON c.tech_id = u.id 
             LEFT JOIN service_orders so ON c.tipo = 'SERVICIO' AND c.reference_id = so.id
             LEFT JOIN project_surveys ps ON c.tipo = 'PROYECTO' AND c.reference_id = ps.id
             $where_clause
             ORDER BY c.fecha_servicio DESC, c.id DESC";

$stmt = $pdo->prepare($stmt_str);
$stmt->execute($params);
$comisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Split into two groups for admin view
$comisiones_pendientes = array_filter($comisiones, fn($c) => $c['estado'] === 'PENDIENTE');
$comisiones_listos     = array_filter($comisiones, fn($c) => $c['estado'] === 'PAGADA');

// Fetch technicians for filter (Admins only)
$technicians = [];
if ($is_admin) {
    $techStmt = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username");
    $technicians = $techStmt->fetchAll();
}

// Tech KPI metrics (only needed for tech view)
$tech_total = 0;
$tech_pending = 0;
$tech_paid = 0;
if (!$is_admin) {
    $kpi = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendiente,
        SUM(CASE WHEN estado = 'PAGADA'    THEN 1 ELSE 0 END) AS pagada
        FROM comisiones WHERE tech_id = ?");
    $kpi->execute([$user_id]);
    $kpi_row = $kpi->fetch(PDO::FETCH_ASSOC);
    $tech_total = intval($kpi_row['total'] ?? 0);
    $tech_pending = intval($kpi_row['pendiente'] ?? 0);
    $tech_paid = intval($kpi_row['pagada'] ?? 0);
}

// Check for status messages
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .table-responsive {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 1rem;
        border: 1px solid var(--border-color);
        overflow-x: auto;
    }

    .table-row-hover:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }
</style>

<?php if ($is_admin): ?>
    <div class="animate-enter">
        <div class="page-header">
            <div>
                <h1 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-coins" style="color: var(--primary-500);"></i>
                    Comisiones por Proyecto
                </h1>
                <p class="text-muted">Registro y control de pagos a técnicos</p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <?php if (can_access_module('comisiones_add', $pdo)): ?>
                        <a href="add.php" class="btn btn-primary" style="display: flex; gap: 0.5rem; align-items: center;">
                            <i class="ph ph-plus"></i> Nueva Comisión Manual
                        </a>
                <?php endif; ?>
                <?php
                // Build export URL preserving current filters
                $export_params = http_build_query(array_filter([
                    'search' => $search,
                    'status' => $status_filter,
                    'tech_id' => $tech_filter
                ]));
                $export_url = 'export.php' . ($export_params ? '?' . $export_params : '');
                ?>
                <a href="<?php echo $export_url; ?>" class="btn btn-secondary"
                   style="display: flex; gap: 0.5rem; align-items: center; border-color: rgba(16,185,129,0.4); color: #34d399;"
                   title="Exportar comisiones visibles como archivo Excel/CSV para RRHH">
                    <i class="ph ph-export"></i> Exportar para RRHH
                </a>
            </div>
        </div>

            <!-- Filters -->
            <style>
                .filter-input {
                    background: rgba(15, 23, 42, 0.6) !important;
                    border: 1px solid rgba(255, 255, 255, 0.08) !important;
                    color: #f8fafc !important;
                    height: 42px !important;
                }
                .filter-input:focus {
                    border-color: var(--primary-500) !important;
                    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
                }
                .filter-label {
                    color: #94a3b8;
                    font-size: 0.8rem;
                    font-weight: 500;
                    margin-bottom: 0.4rem;
                    letter-spacing: 0.3px;
                }
            </style>
            <div class="glass-card" style="margin-bottom: 1.5rem; padding: 1.25rem; background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.05);">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                    <div style="flex: 1; min-width: 250px;">
                        <label class="filter-label" style="display: block;">Buscar</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control filter-input"
                                placeholder="Cliente, servicio, caso, factura..."
                                value="<?php echo htmlspecialchars($search); ?>">
                            <i class="ph ph-magnifying-glass input-icon" style="color: #64748b;"></i>
                        </div>
                    </div>

                    <div style="width: 160px;">
                        <label class="filter-label" style="display: block;">Estado</label>
                        <select name="status" class="form-control filter-input">
                            <option value="">Todos</option>
                            <option value="PENDIENTE" <?php echo $status_filter === 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="PAGADA" <?php echo $status_filter === 'PAGADA' ? 'selected' : ''; ?>>Pagada</option>
                        </select>
                    </div>

                    <?php if ($is_admin): ?>
                            <div style="width: 200px;">
                                <label class="filter-label" style="display: block;">Técnico</label>
                                <select name="tech_id" class="form-control filter-input">
                                    <option value="">Todos los técnicos</option>
                                    <?php foreach ($technicians as $tech): ?>
                                            <option value="<?php echo $tech['id']; ?>" <?php echo $tech_filter == $tech['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tech['username']); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-secondary" style="height: 42px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; font-weight: 500;">
                            <i class="ph ph-funnel"></i> Filtrar
                        </button>
                        <?php if ($search || $status_filter || $tech_filter): ?>
                                <a href="index.php" class="btn btn-secondary"
                                    style="height: 42px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; font-weight: 500;">
                                    <i class="ph ph-x"></i> Limpiar
                                </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php
            // Count for quick filters
            $stmtP = $pdo->prepare("SELECT COUNT(*) FROM comisiones WHERE tipo = 'PROYECTO'");
            $stmtP->execute();
            $countProyectos = $stmtP->fetchColumn();

            $stmtS = $pdo->prepare("SELECT COUNT(*) FROM comisiones WHERE tipo = 'SERVICIO'");
            $stmtS->execute();
            $countServicios = $stmtS->fetchColumn();
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <a href="index.php?search=PROYECTO" style="text-decoration: none; display: block; background: rgba(30, 41, 59, 0.4); border: 1px solid <?php echo (strpos(strtoupper($search), 'PROYECTO') !== false) ? 'rgba(99, 102, 241, 0.5)' : 'rgba(255,255,255,0.05)'; ?>; border-radius: 12px; padding: 1.25rem; transition: all 0.2s; position: relative; overflow: hidden;" class="hover-card-filter">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); color: #818cf8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="ph ph-buildings"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Proyectos</h4>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #fff; line-height: 1.2;"><?php echo $countProyectos; ?></div>
                        </div>
                    </div>
                    <?php if (strpos(strtoupper($search), 'PROYECTO') !== false): ?>
                            <div style="position: absolute; top: 0; right: 0; background: rgba(99, 102, 241, 0.2); color: #818cf8; font-size: 0.7rem; padding: 0.2rem 0.6rem; border-bottom-left-radius: 8px; font-weight: 600;">ACTIVO</div>
                    <?php endif; ?>
                </a>

                <a href="index.php?search=SERVICIO" style="text-decoration: none; display: block; background: rgba(30, 41, 59, 0.4); border: 1px solid <?php echo (strpos(strtoupper($search), 'SERVICIO') !== false) ? 'rgba(16, 185, 129, 0.5)' : 'rgba(255,255,255,0.05)'; ?>; border-radius: 12px; padding: 1.25rem; transition: all 0.2s; position: relative; overflow: hidden;" class="hover-card-filter">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="ph ph-wrench"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Servicios</h4>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #fff; line-height: 1.2;"><?php echo $countServicios; ?></div>
                        </div>
                    </div>
                    <?php if (strpos(strtoupper($search), 'SERVICIO') !== false): ?>
                            <div style="position: absolute; top: 0; right: 0; background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.7rem; padding: 0.2rem 0.6rem; border-bottom-left-radius: 8px; font-weight: 600;">ACTIVO</div>
                    <?php endif; ?>
                </a>
            </div>
        
            <style>
                .hover-card-filter:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    border-color: rgba(255, 255, 255, 0.1) !important;
                }
                .section-header {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin: 1.75rem 0 1rem 0;
                    padding-bottom: 0.75rem;
                    border-bottom: 2px solid;
                }
                .section-header.pendiente { border-color: rgba(245,158,11,0.35); }
                .section-header.listo     { border-color: rgba(16,185,129,0.35); }
                .section-title {
                    font-size: 1rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.6px;
                }
                .section-title.pendiente { color: #fbbf24; }
                .section-title.listo     { color: #34d399; }
                .section-count {
                    font-size: 0.78rem;
                    font-weight: 700;
                    padding: 0.2rem 0.65rem;
                    border-radius: 20px;
                }
                .section-count.pendiente { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.35); }
                .section-count.listo     { background: rgba(16,185,129,0.12); color: #34d399;  border: 1px solid rgba(16,185,129,0.35); }
            </style>

            <?php if ($success_msg): ?>
                    <div class="alert alert-success"
                        style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-warning-circle"></i>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
            <?php endif; ?>

            <?php
            // Helper to render a commissions table section
            function renderComisionesSection($rows, $is_admin, $pdo) {
            ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Caso</th>
                            <th style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Fecha</th>
                            <th style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Cliente</th>
                            <th style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Servicio</th>
                            <?php if ($is_admin): ?>
                                <th style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Técnico</th>
                            <?php endif; ?>
                            <th class="text-center" style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Estado</th>
                            <?php if ($is_admin): ?>
                                <th class="text-center" style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): ?>
                            <?php foreach ($rows as $c): ?>
                                <?php
                                $c_json2 = htmlspecialchars(json_encode([
                                    'id' => $c['id'],
                                    'caso' => $c['caso'],
                                    'tipo' => $c['tipo'],
                                    'cliente' => $c['cliente'],
                                    'tech_name' => strval($c['tech_name'] ?: 'Desconocido'),
                                    'servicio' => $c['servicio'],
                                    'lugar' => strval($c['lugar'] ?: 'Sin especificar'),
                                    'vendedor' => strval($c['vendedor'] ?: 'N/A'),
                                    'fecha_servicio' => date('d/m/Y', strtotime($c['fecha_servicio'])),
                                    'factura' => strval($c['computed_factura'] ?: 'Pendiente'),
                                    'fecha_facturacion' => $c['fecha_facturacion'] ? date('d/m/Y', strtotime($c['fecha_facturacion'])) : 'Pendiente',
                                    'estado' => $c['estado'],
                                    'notas' => strval($c['notas'] ?: 'Sin observaciones.'),
                                    'reference_id' => $c['reference_id']
                                ]), ENT_QUOTES, 'UTF-8');
                                $origin_link = '#';
                                if ($c['tipo'] === 'PROYECTO' && !empty($c['reference_id']))
                                    $origin_link = "../levantamientos/view.php?id=" . $c['reference_id'];
                                 elseif ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])) {
                                     $module = ($c['service_type'] === 'warranty') ? 'warranties' : 'services';
                                     $origin_link = "../$module/view.php?num=" . urlencode(get_order_number($c));
                                 }
                                $tipoColor  = $c['tipo'] === 'PROYECTO' ? 'rgba(99,102,241,0.15)' : 'rgba(16,185,129,0.15)';
                                $tipoText   = $c['tipo'] === 'PROYECTO' ? '#818cf8' : '#34d399';
                                $tipoBorder = $c['tipo'] === 'PROYECTO' ? 'rgba(99,102,241,0.3)' : 'rgba(16,185,129,0.3)';
                                $tipoIcon   = $c['tipo'] === 'PROYECTO' ? 'ph-buildings' : 'ph-wrench';
                                $scls       = $c['estado'] === 'PAGADA' ? 'green' : 'orange';
                                ?>
                                <tr class="table-row-hover" style="cursor: pointer;" onclick="openInfoModal(<?php echo $c_json2; ?>)">
                                    <td>
                                        <a href="<?php echo htmlspecialchars($origin_link); ?>" title="Ir al Origen" onclick="event.stopPropagation();"
                                           style="color: #60a5fa; text-decoration: none; font-weight: 600; font-size: 1.05rem;">
                                            <?php 
                                            if ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])) {
                                                echo htmlspecialchars(get_order_number($c));
                                            } else {
                                                echo htmlspecialchars($c['caso']);
                                            }
                                            ?> <i class="ph ph-link-simple" style="font-size: 0.8rem;"></i>
                                        </a><br>
                                        <span class="badge" style="font-size:.70rem; margin-top:.4rem; padding:.2rem .6rem; border-radius:4px; border:1px solid <?php echo $tipoBorder; ?>; background:<?php echo $tipoColor; ?>; color:<?php echo $tipoText; ?>; display:inline-flex; align-items:center; gap:.3rem; letter-spacing:.5px;">
                                            <i class="ph <?php echo $tipoIcon; ?>" style="font-size:.85rem;"></i>
                                            <?php echo htmlspecialchars($c['tipo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($c['fecha_servicio'])); ?>
                                        <?php if ($c['fecha_facturacion']): ?>
                                            <div style="font-size:.8rem; color:var(--text-muted); margin-top:.25rem;">Fact: <?php echo date('d/m/Y', strtotime($c['fecha_facturacion'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($c['cliente']); ?></div>
                                        <?php if ($c['lugar']): ?>
                                            <div style="font-size:.8rem; color:var(--text-muted);"><i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($c['lugar']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($c['servicio']); ?>">
                                            <?php echo htmlspecialchars($c['servicio']); ?>
                                        </div>
                                        <?php if ($c['computed_factura']): ?>
                                            <div style="font-size:.8rem; color:var(--text-muted); margin-top:.25rem;"><i class="ph ph-receipt"></i> Factura: <?php echo htmlspecialchars($c['computed_factura']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:.5rem;">
                                                <i class="ph ph-user-circle" style="color:var(--text-muted); font-size:1.2rem;"></i>
                                                <span><?php echo htmlspecialchars($c['tech_name'] ?: 'Desconocido'); ?></span>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <span class="status-badge status-<?php echo $scls; ?>"><?php echo $c['estado']; ?></span>
                                    </td>
                                    <?php if ($is_admin): ?>
                                        <td class="text-center" onclick="event.stopPropagation();">
                                            <div style="display:flex; gap:.5rem; justify-content:center;">
                                                <?php if ($c['estado'] === 'PENDIENTE'): ?>
                                                    <button type="button" class="btn btn-secondary"
                                                        style="color:var(--success); border-color:var(--success);"
                                                        title="Liquidar Comisión"
                                                        onclick="openPayModal(
                                                            <?php echo $c['id']; ?>,
                                                            '<?php echo addslashes(htmlspecialchars($c['caso'])); ?>',
                                                            '<?php echo addslashes(htmlspecialchars($c['cliente'])); ?>',
                                                            '<?php echo addslashes(htmlspecialchars($c['computed_factura'] ?? '')); ?>',
                                                            '<?php echo $c['fecha_facturacion'] ? date('Y-m-d', strtotime($c['fecha_facturacion'])) : ''; ?>',
                                                            '<?php echo addslashes(htmlspecialchars($c['lugar'] ?? '')); ?>',
                                                            '<?php echo addslashes(htmlspecialchars($c['vendedor'] ?? '')); ?>'
                                                        )">
                                                        <i class="ph ph-check"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $is_admin ? '7' : '6'; ?>" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                                    <i class="ph ph-coins" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No hay registros en esta sección.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>

            <!-- ===== SECCIÓN PENDIENTES ===== -->
            <div class="section-header pendiente">
                <i class="ph ph-hourglass" style="font-size:1.3rem; color:#fbbf24;"></i>
                <span class="section-title pendiente">Pendientes</span>
                <span class="section-count pendiente"><?php echo count($comisiones_pendientes); ?></span>
            </div>
            <?php renderComisionesSection($comisiones_pendientes, $is_admin, $pdo); ?>

            <!-- ===== SECCIÓN LISTOS ===== -->
            <div class="section-header listo" style="margin-top:2.5rem;">
                <i class="ph ph-check-circle" style="font-size:1.3rem; color:#34d399;"></i>
                <span class="section-title listo">Listos / Pagados</span>
                <span class="section-count listo"><?php echo count($comisiones_listos); ?></span>
            </div>
            <?php renderComisionesSection($comisiones_listos, $is_admin, $pdo); ?>

        </div>

    <?php require_once '../../includes/footer.php'; ?>
<?php else: /* ============ TECH VIEW ============ */ ?>
    <style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .kpi-card { background: rgba(30,41,59,0.6); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; transition: transform .2s; }
    .kpi-card:hover { transform: translateY(-2px); }
    .kpi-label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
    .kpi-value { font-size: 2rem; font-weight: 800; line-height: 1; }
    .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .filter-tab { padding: 0.45rem 1.1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); color: #94a3b8; font-size: 0.85rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: all .15s; }
    .filter-tab:hover { background: rgba(255,255,255,0.07); color: #e2e8f0; }
    .filter-tab.active { background: rgba(99,102,241,0.18); border-color: rgba(99,102,241,0.45); color: #a5b4fc; }
    .filter-tab.active-green { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.4); color: #6ee7b7; }
    .filter-tab.active-orange { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.4); color: #fcd34d; }
    .com-card { background: rgba(30,41,59,0.55); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 1.25rem 1.5rem; margin-bottom: 0.85rem; display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: start; transition: all .2s; }
    .com-card:hover { background: rgba(30,41,59,0.8); border-color: rgba(255,255,255,0.1); }
    .com-card-left { display: flex; flex-direction: column; gap: 0.4rem; }
    .com-caso { font-weight: 700; font-size: 1.05rem; color: #60a5fa; text-decoration: none; }
    .com-caso:hover { color: #93c5fd; }
    .com-meta { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-top: 0.2rem; }
    .com-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 5px; letter-spacing: 0.4px; border: 1px solid; }
    .com-badge-proyecto { background: rgba(99,102,241,0.15); color: #a5b4fc; border-color: rgba(99,102,241,0.3); }
    .com-badge-servicio { background: rgba(16,185,129,0.12); color: #6ee7b7; border-color: rgba(16,185,129,0.3); }
    .com-badge-pagada { background: rgba(16,185,129,0.15); color: #34d399; border-color: rgba(16,185,129,0.35); }
    .com-badge-pendiente { background: rgba(245,158,11,0.12); color: #fbbf24; border-color: rgba(245,158,11,0.35); }
    .com-desc { font-size: 0.91rem; color: #cbd5e1; margin-top: 0.15rem; }
    .com-detail { font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 0.3rem; }
    .com-estado-col { display: flex; flex-direction: column; align-items: flex-end; gap: 0.6rem; padding-top: 0.1rem; }
    .com-date-badge { font-size: 0.75rem; color: #64748b; white-space: nowrap; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: #475569; }
    .empty-state i { font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.4; }
    </style>

    <?php
    $username_display = htmlspecialchars($_SESSION['username'] ?? 'Técnico');
    $active_tab_status = $_GET['status'] ?? '';
    ?>

    <div class="animate-enter">

        <!-- HEADER -->
        <div class="page-header" style="margin-bottom:1.75rem;">
            <div>
                <h1 style="margin-bottom:.3rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="ph ph-coins" style="color:var(--primary-500);"></i>
                    Mis Comisiones
                </h1>
                <p class="text-muted">Historial de pagos asignados a ti, <?php echo $username_display; ?></p>
            </div>
        </div>

        <?php if ($success_msg): ?>
                <div class="alert alert-success" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="ph ph-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
                </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
                <div class="alert alert-danger" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="ph ph-warning-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
        <?php endif; ?>

        <!-- KPI CARDS -->
        <div class="kpi-grid">
            <div class="kpi-card" style="border-color:rgba(99,102,241,.2);">
                <div class="kpi-label"><i class="ph ph-list-numbers"></i> Total</div>
                <div class="kpi-value" style="color:#a5b4fc;"><?php echo $tech_total; ?></div>
                <div style="font-size:.8rem;color:#64748b;">Comisiones totales</div>
            </div>
            <div class="kpi-card" style="border-color:rgba(245,158,11,.2);">
                <div class="kpi-label"><i class="ph ph-hourglass"></i> Por Cobrar</div>
                <div class="kpi-value" style="color:#fbbf24;"><?php echo $tech_pending; ?></div>
                <div style="font-size:.8rem;color:#64748b;">Estado PENDIENTE</div>
            </div>
            <div class="kpi-card" style="border-color:rgba(16,185,129,.2);">
                <div class="kpi-label"><i class="ph ph-check-circle"></i> Cobradas</div>
                <div class="kpi-value" style="color:#34d399;"><?php echo $tech_paid; ?></div>
                <div style="font-size:.8rem;color:#64748b;">Estado PAGADA</div>
            </div>
        </div>

        <!-- STATUS FILTER TABS -->
        <div class="filter-tabs">
            <a href="index.php" class="filter-tab <?php echo $active_tab_status === '' ? 'active' : ''; ?>">
                <i class="ph ph-squares-four"></i> Todas
            </a>
            <a href="index.php?status=PENDIENTE" class="filter-tab <?php echo $active_tab_status === 'PENDIENTE' ? 'active-orange' : ''; ?>">
                <i class="ph ph-hourglass"></i> Pendientes
            </a>
            <a href="index.php?status=PAGADA" class="filter-tab <?php echo $active_tab_status === 'PAGADA' ? 'active-green' : ''; ?>">
                <i class="ph ph-check-circle"></i> Pagadas
            </a>
        </div>

        <!-- COMMISSION CARDS -->
        <?php if (count($comisiones) > 0): ?>
                <?php foreach ($comisiones as $c): ?>
                    <?php
                    $origin_link = '#';
                    if ($c['tipo'] === 'PROYECTO' && !empty($c['reference_id'])) {
                        $origin_link = "../levantamientos/view.php?id=" . $c['reference_id'];
                    } elseif ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])) {
                        $module = ($c['service_type'] === 'warranty') ? 'warranties' : 'services';
                        $origin_link = "../$module/view.php?num=" . urlencode(get_order_number($c));
                    }

                    $tipo_class = $c['tipo'] === 'PROYECTO' ? 'com-badge-proyecto' : 'com-badge-servicio';
                    $tipo_icon = $c['tipo'] === 'PROYECTO' ? 'ph-buildings' : 'ph-wrench';
                    $estado_class = $c['estado'] === 'PAGADA' ? 'com-badge-pagada' : 'com-badge-pendiente';
                    $estado_icon = $c['estado'] === 'PAGADA' ? 'ph-check-circle' : 'ph-hourglass';
                    ?>
                    <div class="com-card">
                        <div class="com-card-left">
                            <div class="com-meta">
                                <a href="<?php echo htmlspecialchars($origin_link); ?>" class="com-caso" onclick="event.stopPropagation()">
                                    <?php 
                                    if ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])) {
                                        echo htmlspecialchars(get_order_number($c));
                                    } else {
                                        echo htmlspecialchars($c['caso']);
                                    }
                                    ?> <i class="ph ph-link-simple" style="font-size:.8rem;"></i>
                                </a>
                                <span class="com-badge <?php echo $tipo_class; ?>">
                                    <i class="ph <?php echo $tipo_icon; ?>"></i> <?php echo $c['tipo']; ?>
                                </span>
                            </div>

                            <div class="com-desc">
                                <?php echo htmlspecialchars($c['cliente']); ?>
                                <?php if ($c['lugar']): ?>
                                        <span style="color:#475569;"> &mdash; <i class="ph ph-map-pin" style="font-size:.85em;"></i> <?php echo htmlspecialchars($c['lugar']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="com-desc" style="color:#94a3b8;font-size:.85rem;">
                                <?php echo htmlspecialchars($c['servicio']); ?>
                            </div>

                            <div style="display:flex;gap:1.2rem;margin-top:.3rem;flex-wrap:wrap;">
                                <div class="com-detail">
                                    <i class="ph ph-calendar-blank"></i>
                                    <?php echo date('d/m/Y', strtotime($c['fecha_servicio'])); ?>
                                </div>
                                <?php if ($c['factura']): ?>
                                    <div class="com-detail">
                                        <i class="ph ph-receipt"></i>
                                        Factura: <strong style="color:#e2e8f0;"><?php echo htmlspecialchars($c['factura']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($c['fecha_facturacion']): ?>
                                    <div class="com-detail">
                                        <i class="ph ph-check-fat"></i>
                                        Facturado: <?php echo date('d/m/Y', strtotime($c['fecha_facturacion'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="com-estado-col">
                            <span class="com-badge <?php echo $estado_class; ?>" style="font-size:.8rem;padding:.35rem .8rem;">
                                <i class="ph <?php echo $estado_icon; ?>"></i>
                                <?php echo $c['estado']; ?>
                            </span>
                            <div class="com-date-badge"><?php echo date('d M Y', strtotime($c['fecha_servicio'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
        <?php else: ?>
                <div class="empty-state">
                    <i class="ph ph-coins"></i>
                    <p>No hay comisiones <?php echo $active_tab_status ? 'con estado ' . $active_tab_status : ''; ?> registradas.</p>
                </div>
        <?php endif; ?>

    </div>

    <?php require_once '../../includes/footer.php'; ?>
<?php endif; /* end admin/tech branch */ ?>

<!-- ====== INFO MODAL ====== -->
<div id="infoModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9998; justify-content:center; align-items:center;">
    <div style="background:#0f172a; border:1px solid rgba(255,255,255,0.1); border-radius:1rem; padding:2rem; width:90%; max-width:650px; box-shadow:0 25px 50px rgba(0,0,0,0.5); max-height: 90vh; overflow-y: auto;">
        
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:1rem;">
            <div>
                <h2 style="margin:0; font-size:1.4rem; color:#f1f5f9; display:flex; align-items:center; gap:0.5rem;">
                    <i class="ph ph-files"></i> <span id="infoCasoTitle"></span>
                </h2>
                <span id="infoEstadoBadge" class="status-badge" style="margin-top:0.5rem; display:inline-block;"></span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a id="infoEditBtn" href="#" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="ph ph-pencil-simple"></i> Editar / Liquidar</a>
                <button onclick="closeInfoModal()" style="background:transparent; border:none; color:#64748b; font-size:1.5rem; cursor:pointer; line-height:1;">×</button>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
            <!-- Column 1 -->
            <div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Cliente</span>
                    <strong id="infoCliente" style="color:#f1f5f9;"></strong>
                </div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Servicio</span>
                    <strong id="infoServicio" style="color:#f1f5f9;"></strong>
                </div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Lugar / Zona</span>
                    <strong id="infoLugar" style="color:#f1f5f9;"></strong>
                </div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Fecha del Servicio</span>
                    <strong id="infoFechaServicio" style="color:#f1f5f9;"></strong>
                </div>
            </div>
            <!-- Column 2 -->
            <div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Técnico Asignado</span>
                    <strong id="infoTecnico" style="color:#f1f5f9;"></strong>
                </div>
                <div style="margin-bottom:0.8rem;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Vendedor / Captador</span>
                    <strong id="infoVendedor" style="color:#f1f5f9;"></strong>
                </div>
            </div>
        </div>

        <div style="background:rgba(56, 189, 248, 0.05); border:1px solid rgba(56, 189, 248, 0.1); padding:1.25rem; border-radius:0.5rem; margin-bottom:1.5rem;">
            <h3 style="margin:0 0 1rem 0; font-size:0.9rem; color:#38bdf8; text-transform:uppercase;"><i class="ph ph-receipt"></i> Datos de Facturación</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div>
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Nº Factura</span>
                    <strong id="infoFactura" style="color:#f1f5f9;"></strong>
                </div>
                <div>
                    <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase;">Fecha Facturación</span>
                    <strong id="infoFechaFactura" style="color:#f1f5f9;"></strong>
                </div>
            </div>
        </div>

        <div>
            <span style="display:block; font-size:0.75rem; color:#64748b; text-transform:uppercase; margin-bottom:0.5rem;">Notas / Observaciones</span>
            <div id="infoNotas" style="background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); padding:1rem; border-radius:0.5rem; color:#cbd5e1; font-size:0.9rem; min-height:60px; white-space: pre-wrap;">
                
            </div>
        </div>

    </div>
</div>

<!-- ====== QUICK PAY MODAL ====== -->
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#0f172a; border:1px solid rgba(255,255,255,0.1); border-radius:1rem; padding:2rem; width:90%; max-width:520px; box-shadow:0 25px 50px rgba(0,0,0,0.5);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <div>
                <h2 style="margin:0; font-size:1.25rem; color:#f1f5f9;"><i class="ph ph-currency-circle-dollar" style="color:#10b981;"></i> Liquidar Comisión</h2>
                <p id="modalSubtitle" style="margin:0.25rem 0 0; font-size:0.85rem; color:#64748b;"></p>
            </div>
            <button onclick="closePayModal()" style="background:transparent; border:none; color:#64748b; font-size:1.5rem; cursor:pointer; line-height:1;">×</button>
        </div>

        <div style="background:rgba(245,158,11,0.07); border:1px solid rgba(245,158,11,0.25); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
            <p style="margin:0; font-size:0.85rem; color:#fbbf24;"><i class="ph ph-warning"></i> El <strong>Nº de Factura</strong> es obligatorio para poder liquidar la comisión.</p>
        </div>

        <form id="payForm" method="POST" action="save_and_pay.php" onsubmit="return validatePayForm(event)">
            <input type="hidden" name="id" id="modalId">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <label style="display:block; font-size:0.78rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:0.4rem;">Nº Factura / O.S.</label>
                    <input id="modalFactura" name="factura" type="text" placeholder="Ej. 162453"
                        style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#fff; padding:0.6rem 0.75rem; font-size:0.9rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.78rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:0.4rem;">Fecha Facturación</label>
                    <input id="modalFechaFact" name="fecha_facturacion" type="date"
                        style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#fff; padding:0.6rem 0.75rem; font-size:0.9rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.78rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:0.4rem;">Lugar / Zona</label>
                    <input id="modalLugar" name="lugar" type="text" placeholder="Taller, León, Managua..."
                        style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#fff; padding:0.6rem 0.75rem; font-size:0.9rem; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:0.78rem; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:0.4rem;">Vendedor</label>
                    <input id="modalVendedor" name="vendedor" type="text" placeholder="Nombre del vendedor"
                        style="width:100%; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#fff; padding:0.6rem 0.75rem; font-size:0.9rem; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:flex; gap:0.75rem;">
                <button type="button" onclick="closePayModal()"
                    style="flex:1; padding:0.75rem; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:transparent; color:#94a3b8; cursor:pointer; font-weight:600;">Cancelar</button>
                <button type="submit"
                    style="flex:2; padding:0.75rem; border-radius:8px; border:none; background:#10b981; color:#fff; cursor:pointer; font-weight:700; font-size:1rem;">
                    <i class="ph ph-check-circle"></i> Marcar como Pagada
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openInfoModal(c) {
    document.getElementById('infoCasoTitle').textContent = c.caso;
    
    // Status badge
    const badge = document.getElementById('infoEstadoBadge');
    badge.textContent = c.estado;
    badge.className = 'status-badge ' + (c.estado === 'PAGADA' ? 'status-green' : 'status-orange');
    
    // Fields
    document.getElementById('infoCliente').textContent = c.cliente;
    document.getElementById('infoServicio').textContent = c.servicio;
    document.getElementById('infoLugar').textContent = c.lugar;
    document.getElementById('infoFechaServicio').textContent = c.fecha_servicio;
    
    document.getElementById('infoTecnico').textContent = c.tech_name;
    document.getElementById('infoVendedor').textContent = c.vendedor;
    
    document.getElementById('infoFactura').textContent = c.factura;
    document.getElementById('infoFechaFactura').textContent = c.fecha_facturacion;
    document.getElementById('infoNotas').textContent = c.notas;
    
    // Edit Link
    document.getElementById('infoEditBtn').href = 'view.php?id=' + c.id;
    
    document.getElementById('infoModal').style.display = 'flex';
}

function closeInfoModal() {
    document.getElementById('infoModal').style.display = 'none';
}

// Close on backdrop click for both modals
document.getElementById('infoModal').addEventListener('click', function(e) {
    if (e.target === this) closeInfoModal();
});

function openPayModal(id, caso, cliente, factura, fecha, lugar, vendedor) {
    document.getElementById('modalId').value      = id;
    document.getElementById('modalSubtitle').textContent = caso + ' · ' + cliente;
    
    const inputFactura = document.getElementById('modalFactura');
    const inputFecha = document.getElementById('modalFechaFact');
    
    inputFactura.value = factura;
    inputFecha.value = fecha;
    document.getElementById('modalLugar').value    = lugar;
    document.getElementById('modalVendedor').value  = ''; // Empty by default
    
    // Block editing if the invoice is already set
    if (factura && factura.trim() !== '' && factura !== 'Pendiente') {
        inputFactura.setAttribute('readonly', true);
        inputFactura.style.backgroundColor = 'rgba(0,0,0,0.6)';
        inputFactura.style.cursor = 'not-allowed';
        
        inputFecha.setAttribute('readonly', true);
        inputFecha.style.backgroundColor = 'rgba(0,0,0,0.6)';
        inputFecha.style.cursor = 'not-allowed';
    } else {
        inputFactura.removeAttribute('readonly');
        inputFactura.style.backgroundColor = 'rgba(0,0,0,0.3)';
        inputFactura.style.cursor = 'text';
        
        inputFecha.removeAttribute('readonly');
        inputFecha.style.backgroundColor = 'rgba(0,0,0,0.3)';
        inputFecha.style.cursor = 'text';
    }
    
    const modal = document.getElementById('payModal');
    modal.style.display = 'flex';
    
    if (!factura || factura.trim() === '' || factura === 'Pendiente') {
        inputFactura.focus();
    }
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}
// Close on backdrop click
document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
});

function validatePayForm(e) {
    const factura = document.getElementById('modalFactura').value.trim();
    const input   = document.getElementById('modalFactura');
    if (!factura) {
        e.preventDefault();
        input.style.borderColor = '#ef4444';
        input.style.boxShadow   = '0 0 0 2px rgba(239,68,68,0.25)';
        input.focus();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Factura Requerida',
                text: 'Debe ingresar el Nº de Factura antes de liquidar la comisión.',
                icon: 'warning',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#6366f1'
            });
        } else {
            alert('Debe ingresar el Nº de Factura antes de liquidar la comisión.');
        }
        return false;
    }
    input.style.borderColor = '';
    input.style.boxShadow   = '';
    return true;
}
document.getElementById('modalFactura')?.addEventListener('input', function() {
    this.style.borderColor = '';
    this.style.boxShadow   = '';
});
</script>