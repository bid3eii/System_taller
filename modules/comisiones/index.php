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

// Build query
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
    $where[] = "(c.cliente LIKE ? OR c.servicio LIKE ? OR c.caso LIKE ? OR c.factura LIKE ?)";
    $srch = "%$search%";
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
    $params[] = $srch;
}

if ($status_filter) {
    $where[] = "c.estado = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt_str = "SELECT c.*, u.username as tech_name 
             FROM comisiones c 
             LEFT JOIN users u ON c.tech_id = u.id 
             $where_clause
             ORDER BY c.fecha_servicio DESC, c.id DESC";

$stmt = $pdo->prepare($stmt_str);
$stmt->execute($params);
$comisiones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch technicians for filter (Admins only)
$technicians = [];
if ($is_admin) {
    $techStmt = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username");
    $technicians = $techStmt->fetchAll();
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
</style>

<div class="animate-enter">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-coins" style="color: var(--primary-500);"></i>
                Comisiones por Proyecto
            </h1>
            <p class="text-muted">Registro y control de pagos a técnicos</p>
        </div>
        <div>
            <?php if (can_access_module('comisiones_add', $pdo)): ?>
                <a href="add.php" class="btn btn-primary" style="display: flex; gap: 0.5rem; align-items: center;">
                    <i class="ph ph-plus"></i> Nueva Comisión Manual
                </a>
            <?php endif; ?>
        </div>
    </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label
                        style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Buscar</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Cliente, servicio, caso, factura..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </div>

                <div style="width: 150px;">
                    <label
                        style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Estado</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="PENDIENTE" <?php echo $status_filter === 'PENDIENTE' ? 'selected' : ''; ?>>
                            Pendiente</option>
                        <option value="PAGADA" <?php echo $status_filter === 'PAGADA' ? 'selected' : ''; ?>>Pagada
                        </option>
                    </select>
                </div>

                <?php if ($is_admin): ?>
                    <div style="width: 200px;">
                        <label
                            style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-muted);">Técnico</label>
                        <select name="tech_id" class="form-control">
                            <option value="">Todos los técnicos</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo $tech_filter == $tech['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <button type="submit" class="btn btn-secondary" style="height: 38px;">
                        <i class="ph ph-funnel"></i> Filtrar
                    </button>
                    <?php if ($search || $status_filter || $tech_filter): ?>
                        <a href="index.php" class="btn btn-secondary"
                            style="height: 38px; border-color: var(--danger); color: var(--danger);">
                            <i class="ph ph-x"></i> Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

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

        <div class="table-responsive">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Caso</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Fecha</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Cliente</th>
                        <th
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Servicio</th>
                        <?php if ($is_admin): ?>
                            <th
                                style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                                Técnico</th>
                        <?php endif; ?>
                        <th class="text-center"
                            style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                            Estado</th>
                        <?php if ($is_admin): ?>
                            <th class="text-center"
                                style="padding: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">
                                Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($comisiones) > 0): ?>
                        <?php foreach ($comisiones as $c): ?>
                            <tr>
                                <td>
                                    <?php
                                    // Origin link logic
                                    $origin_link = '#';
                                    if ($c['tipo'] === 'PROYECTO' && !empty($c['reference_id'])) {
                                        $origin_link = "../levantamientos/view.php?id=" . $c['reference_id'];
                                    } elseif ($c['tipo'] === 'SERVICIO' && !empty($c['reference_id'])) {
                                        $origin_link = "../services/view.php?id=" . $c['reference_id'];
                                    }
                                    ?>
                                    <a href="<?php echo htmlspecialchars($origin_link); ?>"
                                       title="Ver Origen"
                                       style="color: var(--primary-400); text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($c['caso']); ?> <i class="ph ph-link-simple" style="font-size: 0.8rem;"></i>
                                    </a>
                                    <br>
                                    <span class="badge"
                                        style="font-size: 0.70rem; margin-top: 0.25rem;"><?php echo htmlspecialchars($c['tipo']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($c['fecha_servicio'])); ?>
                                    <?php if ($c['fecha_facturacion']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            Fact: <?php echo date('d/m/Y', strtotime($c['fecha_facturacion'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($c['cliente']); ?></div>
                                    <?php if ($c['lugar']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><i class="ph ph-map-pin"></i>
                                            <?php echo htmlspecialchars($c['lugar']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                        title="<?php echo htmlspecialchars($c['servicio']); ?>">
                                        <?php echo htmlspecialchars($c['servicio']); ?>
                                    </div>
                                    <?php if ($c['factura']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;"><i
                                                class="ph ph-receipt"></i> Factura: <?php echo htmlspecialchars($c['factura']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="ph ph-user-circle" style="color: var(--text-muted); font-size: 1.2rem;"></i>
                                            <span><?php echo htmlspecialchars($c['tech_name'] ?: 'Desconocido'); ?></span>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php
                                    $scls = $c['estado'] === 'PAGADA' ? 'green' : 'orange';
                                    ?>
                                    <span class="status-badge status-<?php echo $scls; ?>">
                                        <?php echo $c['estado']; ?>
                                    </span>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td class="text-center">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <?php
                                            // Make sure we have the JSON string for the Info Modal
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
                                                'factura' => strval($c['factura'] ?: 'Pendiente'),
                                                'fecha_facturacion' => $c['fecha_facturacion'] ? date('d/m/Y', strtotime($c['fecha_facturacion'])) : 'Pendiente',
                                                'estado' => $c['estado'],
                                                'notas' => strval($c['notas'] ?: 'Sin observaciones.'),
                                                'reference_id' => $c['reference_id']
                                            ]), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button" class="btn btn-secondary"
                                                style="color: var(--primary-400); border-color: var(--primary-400);"
                                                title="Ver Detalles de la Comisión"
                                                onclick="openInfoModal(<?php echo $c_json2; ?>)">
                                                <i class="ph ph-info"></i>
                                            </button>

                                            <?php if ($c['estado'] === 'PENDIENTE'): ?>
                                                <button type="button" class="btn btn-secondary"
                                                    style="color: var(--success); border-color: var(--success);"
                                                    title="Liquidar Comisión"
                                                    onclick="openPayModal(
                                                        <?php echo $c['id']; ?>,
                                                        '<?php echo addslashes(htmlspecialchars($c['caso'])); ?>',
                                                        '<?php echo addslashes(htmlspecialchars($c['cliente'])); ?>',
                                                        '<?php echo addslashes(htmlspecialchars($c['factura'] ?? '')); ?>',
                                                        '<?php echo $c['fecha_facturacion'] ? date('Y-m-d', strtotime($c['fecha_facturacion'])) : ''; ?>',
                                                        '<?php echo addslashes(htmlspecialchars($c['lugar'] ?? '')); ?>',
                                                        '<?php echo addslashes(htmlspecialchars($c['vendedor'] ?? '')); ?>'
                                                    )">
                                                    <i class="ph ph-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (can_access_module('comisiones_delete', $pdo)): ?>
                                                <form action="delete.php" method="POST" style="display: inline;"
                                                    onsubmit="return confirm('¿Está seguro de eliminar esta comisión de forma permanente?');">
                                                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="color: var(--danger); border-color: var(--danger);" title="Eliminar">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                                <i class="ph ph-coins" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No se encontraron registros de comisiones.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php require_once '../../includes/footer.php'; ?>

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

        <div style="background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.15); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
            <p style="margin:0; font-size:0.85rem; color:#6ee7b7;"><i class="ph ph-info"></i> Completa los datos de facturación antes de marcar como Pagada. Puedes dejarlos vacíos y actualizar después.</p>
        </div>

        <form id="payForm" method="POST" action="save_and_pay.php">
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
    document.getElementById('modalFactura').value  = factura;
    document.getElementById('modalFechaFact').value = fecha;
    document.getElementById('modalLugar').value    = lugar;
    document.getElementById('modalVendedor').value  = vendedor;
    
    const modal = document.getElementById('payModal');
    modal.style.display = 'flex';
    document.getElementById('modalFactura').focus();
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}
// Close on backdrop click
document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
});
</script>