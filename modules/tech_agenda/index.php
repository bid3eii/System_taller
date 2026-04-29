<?php
// modules/tech_agenda/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Access Control - Check if tech or admin
if (!can_access_module('tech_agenda', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Mi Agenda Técnica';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$is_admin = in_array($_SESSION['role_id'], [1, 7]);

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming'; // 'today', 'upcoming', 'history'

$where = "se.tech_id = ?";
$params = [$user_id];

if ($is_admin && isset($_GET['tech_id'])) {
    $where = "se.tech_id = ?";
    $params = [$_GET['tech_id']];
}

$order_dir = "ASC";
switch ($filter) {
    case 'today':
        $where .= " AND DATE(se.start_datetime) = CURDATE()";
        break;
    case 'history':
        $where .= " AND se.status IN ('completed', 'cancelled')";
        $order_dir = "DESC";
        break;
    default: // upcoming (now acts as all pending/overdue)
        $where .= " AND se.status NOT IN ('completed', 'cancelled')";
        break;
}

// Fetch Events
$sql = "SELECT se.*, ps.title as survey_title, ps.client_name as survey_client
        FROM schedule_events se
        LEFT JOIN project_surveys ps ON se.survey_id = ps.id
        WHERE $where
        ORDER BY se.start_datetime $order_dir";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// Status Mapping
$status_map = [
    'scheduled' => ['label' => 'Programado', 'color' => 'var(--primary-500)', 'icon' => 'ph-calendar'],
    'in_progress' => ['label' => 'En Proceso', 'color' => 'var(--warning)', 'icon' => 'ph-clock'],
    'completed' => ['label' => 'Completada', 'color' => 'var(--success)', 'icon' => 'ph-check-circle'],
    'cancelled' => ['label' => 'Cancelada', 'color' => 'var(--danger)', 'icon' => 'ph-x-circle']
];
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem;">
        <div>
            <h1 style="margin:0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="ph ph-map-trifold" style="color: var(--primary-500);"></i>
                Mi Agenda Técnica
            </h1>
            <p class="text-muted" style="margin: 0.5rem 0 0 2.5rem;">Gestiona tus visitas y levantamientos asignados.</p>
        </div>
        
        <div style="display: flex; gap: 0.5rem; background: var(--bg-card); padding: 0.4rem; border-radius: 12px; border: 1px solid var(--border-color);">
            <a href="?filter=today" class="btn <?php echo $filter == 'today' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.8rem; border:none;">Hoy</a>
            <a href="?filter=upcoming" class="btn <?php echo $filter == 'upcoming' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.8rem; border:none;">Próximas</a>
            <a href="?filter=history" class="btn <?php echo $filter == 'history' ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 1rem; font-size: 0.8rem; border:none;">Historial</a>
        </div>
    </div>

    <!-- Visits Container -->
    <div style="max-width: 900px; margin: 0 auto;">
        <?php if (empty($visits)): ?>
            <div class="card text-center" style="padding: 4rem 2rem; border-style: dashed; border-color: var(--border-color); background: transparent;">
                <i class="ph ph-calendar-blank" style="font-size: 4rem; color: var(--text-secondary); opacity: 0.2; margin-bottom: 1.5rem;"></i>
                <h3>No hay visitas en esta categoría</h3>
                <p class="text-muted">¡Tómate un respiro o revisa el calendario general!</p>
                <a href="../schedule/index.php" class="btn btn-secondary" style="margin-top: 1rem;">Ver Calendario Completo</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php foreach ($visits as $v): 
                    $s = $status_map[$v['status']];
                    $isToday = date('Y-m-d', strtotime($v['start_datetime'])) == date('Y-m-d');
                ?>
                    <div class="card" style="padding: 0; overflow: hidden; position: relative; border-left: 5px solid <?php echo $s['color']; ?>;">
                        <div style="padding: 1.5rem; display: flex; gap: 1.5rem; align-items: flex-start;">
                            <!-- Time Section -->
                            <div style="min-width: 100px; text-align: center; border-right: 1px solid var(--border-color); padding-right: 1.5rem;">
                                <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);"><?php echo date('H:i', strtotime($v['start_datetime'])); ?></div>
                                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); margin-top: 0.2rem;">
                                    <?php echo date('d M', strtotime($v['start_datetime'])); ?>
                                </div>
                                <?php if ($isToday): ?>
                                    <span style="background: var(--primary- glow); color: var(--primary-500); font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 0.5rem;">HOY</span>
                                <?php endif; ?>
                            </div>

                            <!-- Content Section -->
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <h3 style="margin:0; font-size: 1.2rem; color: var(--text-primary);"><?php echo htmlspecialchars($v['title']); ?></h3>
                                    <span style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 700; color: <?php echo $s['color']; ?>; background: rgba(255,255,255,0.03); padding: 4px 10px; border-radius: 20px; border: 1px solid <?php echo $s['color']; ?>; opacity: 0.8;">
                                        <i class="ph <?php echo $s['icon']; ?>"></i> <?php echo strtoupper($s['label']); ?>
                                    </span>
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                                    <?php if ($v['location']): ?>
                                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="ph ph-map-pin" style="color: var(--danger);"></i>
                                            <?php echo htmlspecialchars($v['location']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($v['survey_id']): ?>
                                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="ph ph-briefcase" style="color: var(--primary-500);"></i>
                                            Proyecto: <?php echo htmlspecialchars($v['survey_client']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($v['description'])): ?>
                                    <p style="margin: 1rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary); opacity: 0.8; line-height: 1.4;">
                                        <?php echo nl2br(htmlspecialchars($v['description'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div style="background: rgba(255,255,255,0.02); padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color);">
                            <div style="display: flex; gap: 0.75rem;">
                                <?php if ($v['location']): ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($v['location']); ?>" target="_blank" class="btn btn-secondary" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); height: 38px;">
                                        <i class="ph ph-navigation-arrow"></i> GPS
                                    </a>
                                <?php endif; ?>
                                <?php if ($v['survey_id']): ?>
                                    <a href="../levantamientos/view.php?id=<?php echo $v['survey_id']; ?>" class="btn btn-secondary" style="height: 38px;">
                                        <i class="ph ph-clipboard"></i> Ver Levantamiento
                                    </a>
                                <?php elseif ($v['status'] == 'completed'): ?>
                                    <a href="../levantamientos/add.php?event_id=<?php echo $v['id']; ?>" class="btn btn-primary" style="height: 38px; background: var(--primary-500); border: none;">
                                        <i class="ph ph-plus-circle"></i> Hacer Levantamiento
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <?php if ($v['status'] == 'scheduled'): ?>
                                    <button onclick="updateStatus(<?php echo $v['id']; ?>, 'in_progress')" class="btn btn-primary" style="height: 38px; background: var(--warning); border-color: var(--warning);">
                                        <i class="ph ph-play"></i> Iniciar Visita
                                    </button>
                                <?php elseif ($v['status'] == 'in_progress'): ?>
                                    <button onclick="updateStatus(<?php echo $v['id']; ?>, 'completed')" class="btn btn-primary" style="height: 38px; background: var(--success); border-color: var(--success);">
                                        <i class="ph ph-check"></i> Finalizar
                                    </button>
                                <?php endif; ?>
                                
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateStatus(id, status) {
    Swal.fire({
        title: '¿Confirmar actualización?',
        text: `Cambiar estado a: ${status === 'in_progress' ? 'En Proceso' : 'Completado'}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--primary-500)',
        confirmButtonText: 'Sí, actualizar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: status })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (status === 'completed') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Visita Finalizada!',
                            text: '¿Deseas redactar el Levantamiento Técnico ahora?',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, crear levantamiento',
                            cancelButtonText: 'No, después',
                            confirmButtonColor: 'var(--success)',
                        }).then((res) => {
                            if (res.isConfirmed) {
                                window.location.href = `../levantamientos/add.php?event_id=${id}`;
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        location.reload();
                    }
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

</script>

<?php require_once '../../includes/footer.php'; ?>
