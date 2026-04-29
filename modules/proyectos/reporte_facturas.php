<?php
// modules/proyectos/reporte_facturas.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permissions
if (!can_access_module('reporte_facturas', $pdo) && $_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 7) {
    die("Acceso denegado.");
}

// Fetch orders
$sql = "
    SELECT id, created_at, client_name, title, status, invoice_number, payment_status 
    FROM project_surveys 
    ORDER BY created_at DESC
";
$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Reporte de Facturas de Proyectos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="main-content" style="padding: 2.5rem; overflow: visible;">
    <div class="page-header" style="margin-bottom: 2.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="width: 48px; height: 48px; background: rgba(var(--primary-rgb), 0.1); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                <i class="ph-fill ph-receipt" style="font-size: 24px; color: var(--primary);"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-size: 1.8rem; font-weight: 700; margin: 0; letter-spacing: -0.5px;">Reporte de Facturas</h1>
            </div>
        </div>
        <p class="text-muted" style="margin: 0; padding-left: 4rem; opacity: 0.8;">Historial de facturación de Proyectos y Levantamientos.</p>
    </div>

    <!-- Filters & Search -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-body" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
            <div class="input-group" style="flex: 1; min-width: 250px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por cliente, proyecto, factura...">
            </div>
            
            <div style="width: 200px;">
                <select id="paymentFilter" class="form-control">
                    <option value="all">Estado de Pago (Todos)</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="credito">Crédito</option>
                    <option value="pagado">Pagado</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem; flex: 1; min-width: 280px; align-items: stretch;">
                <input type="date" id="startDate" class="form-control" title="Desde">
                <input type="date" id="endDate" class="form-control" title="Hasta">
            </div>

            <button onclick="exportToExcel()" class="btn btn-success" style="white-space: nowrap; padding: 0.5rem 1.25rem;">
                <i class="ph ph-microsoft-excel-logo"></i> Exportar
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="invoicesTable">
                    <thead>
                        <tr>
                            <th>Nº Factura</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Proyecto</th>
                            <th>Estado Proyecto</th>
                            <th>Pago</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr data-payment="<?php echo strtolower($inv['payment_status'] ?? 'pendiente'); ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($inv['created_at'])); ?>">
                                <td>
                                    <span style="font-weight: 600; color: var(--primary);">
                                        <?php echo !empty($inv['invoice_number']) ? htmlspecialchars($inv['invoice_number']) : '<span style="color:var(--text-muted); font-weight:normal; font-style:italic;">- Sin Factura -</span>'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($inv['title']); ?></td>
                                <td>
                                    <?php
                                    $s = $inv['status'];
                                    if ($s == 'draft') echo '<span class="badge" style="background: rgba(100,116,139,0.1); color: #94a3b8;">Borrador</span>';
                                    elseif ($s == 'approved') echo '<span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Aprobado</span>';
                                    elseif ($s == 'completed') echo '<span class="badge" style="background: rgba(59,130,246,0.1); color: #3b82f6;">Completado</span>';
                                    else echo '<span class="badge bg-secondary">'.$s.'</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $p = $inv['payment_status'] ?? 'pendiente';
                                    if ($p == 'pagado') {
                                        echo '<span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;"><i class="ph-bold ph-check-circle"></i> Pagado</span>';
                                    } elseif ($p == 'credito') {
                                        echo '<span class="badge" style="background: rgba(59,130,246,0.1); color: #3b82f6;"><i class="ph-bold ph-clock"></i> Crédito</span>';
                                    } else {
                                        echo '<span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;"><i class="ph-bold ph-warning-circle"></i> Pendiente</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-end">
                                    <a href="manage.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary-light); border: 1px solid rgba(var(--primary-rgb), 0.2); white-space: nowrap; text-decoration: none;" onmouseover="this.style.background='rgba(var(--primary-rgb), 0.2)'" onmouseout="this.style.background='rgba(var(--primary-rgb), 0.1)'">
                                        Ver <i class="ph-bold ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">No se encontraron facturas registradas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const paymentFilter = document.getElementById('paymentFilter');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const table = document.getElementById('invoicesTable');
const rows = Array.from(table.querySelectorAll('tbody tr:not(.no-results-row)'));

function filterTable() {
    const search = searchInput.value.toLowerCase();
    const payment = paymentFilter.value.toLowerCase();
    const start = startDate.value;
    const end = endDate.value;

    let visibleCount = 0;

    rows.forEach(row => {
        if (row.cells.length < 2) return;
        const text = row.innerText.toLowerCase();
        const rp = row.getAttribute('data-payment');
        const rd = row.getAttribute('data-date');

        let matchSearch = text.includes(search);
        let matchPay = (payment === 'all') || (rp === payment);
        let matchDate = true;
        if (start && rd < start) matchDate = false;
        if (end && rd > end) matchDate = false;

        if (matchSearch && matchPay && matchDate) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}

searchInput.addEventListener('input', filterTable);
paymentFilter.addEventListener('change', filterTable);
startDate.addEventListener('change', filterTable);
endDate.addEventListener('change', filterTable);

function exportToExcel() {
    const search = encodeURIComponent(searchInput.value);
    const payment = encodeURIComponent(paymentFilter.value);
    const start = encodeURIComponent(startDate.value);
    const end = encodeURIComponent(endDate.value);
    window.location.href = `export_facturas.php?search=${search}&payment=${payment}&start=${start}&end=${end}`;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
