<?php
// modules/tech_agenda/master_control.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Access Control - Permission check
if (!can_access_module('master_visit_control', $pdo)) {
    die("Acceso denegado. Se requieren permisos de administrador o acceso al módulo de Control Maestro.");
}

$page_title = 'Control Maestro de Visitas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Fetch all technicians for filter
$technicians = $pdo->query("SELECT id, username FROM users WHERE role_id IN (1,3,7) AND status = 'active' ORDER BY username ASC")->fetchAll();

// Base query
$sql = "SELECT se.*, u.username as tech_name, ps.id as ps_id, ps.title as ps_title
        FROM schedule_events se
        LEFT JOIN users u ON se.tech_id = u.id
        LEFT JOIN project_surveys ps ON se.survey_id = ps.id
        ORDER BY se.start_datetime DESC";
$visits = $pdo->query($sql)->fetchAll();

$status_map = [
    'scheduled' => ['label' => 'Programada', 'color' => 'primary'],
    'in_progress' => ['label' => 'En Proceso', 'color' => 'warning'],
    'completed' => ['label' => 'Completada', 'color' => 'success'],
    'cancelled' => ['label' => 'Cancelada', 'color' => 'danger']
];
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
            <h1 style="margin:0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="ph ph-monitor" style="color: var(--primary-500);"></i>
                Control Maestro de Visitas
            </h1>
            <p class="text-muted" style="margin: 0.5rem 0 0 2.5rem;">Supervisión global de agendas técnicas y reportes de levantamiento.</p>
        </div>
    </div>

    <!-- Stats Overview -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2.5rem;">
        <?php
        $stats = [
            'total' => count($visits),
            'completed' => count(array_filter($visits, fn($v) => $v['status'] === 'completed')),
            'pending_report' => count(array_filter($visits, fn($v) => $v['status'] === 'completed' && !$v['ps_id'])),
            'in_progress' => count(array_filter($visits, fn($v) => $v['status'] === 'in_progress'))
        ];
        ?>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(99, 102, 241, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary-500);">
                <i class="ph-bold ph-calendar" style="font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['total']; ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Visitas Totales</div>
            </div>
        </div>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(16, 185, 129, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--success);">
                <i class="ph-bold ph-check-circle" style="font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['completed']; ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Completadas</div>
            </div>
        </div>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(245, 158, 11, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--warning);">
                <i class="ph-bold ph-warning-circle" style="font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['pending_report']; ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Sin Levantamiento</div>
            </div>
        </div>
        <div class="card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1rem;">
            <div style="background: rgba(99, 102, 241, 0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary-500);">
                <i class="ph-bold ph-clock" style="font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $stats['in_progress']; ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">En Proceso</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size: 0.75rem;">Buscar</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Cliente, título...">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size: 0.75rem;">Técnico</label>
                <select id="techFilter" class="form-control">
                    <option value="all">Todos</option>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['username']); ?>"><?php echo htmlspecialchars($t['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size: 0.75rem;">Estado de Visita</label>
                <select id="statusFilter" class="form-control">
                    <option value="all">Todos</option>
                    <?php foreach ($status_map as $key => $s): ?>
                        <option value="<?php echo $key; ?>"><?php echo $s['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" style="font-size: 0.75rem;">Levantamiento</label>
                <select id="reportFilter" class="form-control">
                    <option value="all">Todos</option>
                    <option value="completed">✅ Completado</option>
                    <option value="pending">⏳ Pendiente</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="table" id="masterTable">
                <thead>
                    <tr>
                        <th style="padding-left: 2rem;">Técnico</th>
                        <th>Fecha y Hora</th>
                        <th>Cliente / Proyecto</th>
                        <th>Estado Visita</th>
                        <th>Levantamiento</th>
                        <th class="text-end" style="padding-right: 2rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $v): 
                        $s = $status_map[$v['status']];
                        $has_ps = !empty($v['ps_id']);
                    ?>
                        <tr data-tech="<?php echo htmlspecialchars($v['tech_name'] ?? 'N/A'); ?>" 
                            data-status="<?php echo $v['status']; ?>"
                            data-report="<?php echo $has_ps ? 'completed' : 'pending'; ?>">
                            <td style="padding-left: 2rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.8rem; background: var(--bg-body);">
                                        <?php echo strtoupper(substr($v['tech_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($v['tech_name'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?php echo date('d/m/Y', strtotime($v['start_datetime'])); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('H:i', strtotime($v['start_datetime'])); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($v['title']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.25rem;">
                                    <i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($v['location'] ?: 'Sin ubicación'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $s['color']; ?>">
                                    <?php echo $s['label']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($has_ps): ?>
                                    <span style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <i class="ph-bold ph-check"></i> COMPLETADO
                                    </span>
                                <?php elseif ($v['status'] === 'completed'): ?>
                                    <span style="background: rgba(245, 158, 11, 0.1); color: var(--warning); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                                        <i class="ph-bold ph-clock"></i> PENDIENTE
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" style="padding-right: 2rem;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    <a href="../schedule/index.php" class="btn-icon" title="Ver en Calendario">
                                        <i class="ph ph-calendar"></i>
                                    </a>
                                    <?php if ($has_ps): ?>
                                        <a href="../levantamientos/view.php?id=<?php echo $v['ps_id']; ?>" class="btn-icon" title="Ver Levantamiento" style="color: var(--success);">
                                            <i class="ph ph-file-text"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const techFilter = document.getElementById('techFilter');
    const statusFilter = document.getElementById('statusFilter');
    const reportFilter = document.getElementById('reportFilter');
    const rows = document.querySelectorAll('#masterTable tbody tr');

    function filterTable() {
        const q = searchInput.value.toLowerCase();
        const tech = techFilter.value;
        const status = statusFilter.value;
        const report = reportFilter.value;

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const rTech = row.dataset.tech;
            const rStatus = row.dataset.status;
            const rReport = row.dataset.report;

            const matchesSearch = text.includes(q);
            const matchesTech = tech === 'all' || rTech === tech;
            const matchesStatus = status === 'all' || rStatus === status;
            const matchesReport = report === 'all' || rReport === report;

            if (matchesSearch && matchesTech && matchesStatus && matchesReport) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    techFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);
    reportFilter.addEventListener('change', filterTable);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
