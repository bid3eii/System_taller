<?php
// modules/admin/audit_logs.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Strict Role Check - Only SuperAdmin
if ($_SESSION['role_name'] !== 'SuperAdmin') {
    die("Acceso denegado: Se requiere privilegios de SuperAdmin.");
}

$page_title = 'Registro de Auditoría';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Fetch logs with user names
$sql = "
    SELECT al.*, u.username 
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll();

// Get total for pagination
$totalLogs = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($totalLogs / $limit);
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1><i class="ph ph-shield-check" style="color: var(--primary-500);"></i> Registro de Auditoría</h1>
        <p class="text-muted">Historial detallado de cambios críticos realizados por administradores.</p>
    </div>

    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Módulo/ID</th>
                        <th>Motivo del Cambio</th>
                        <th style="text-align: right;">Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <span class="text-sm font-medium"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="user-avatar-sm" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                        <?php echo strtoupper(substr($log['username'] ?? '?', 0, 1)); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $actionColors = ['INSERT' => 'green', 'UPDATE' => 'yellow', 'DELETE' => 'red'];
                                $color = $actionColors[$log['action']] ?? 'gray';
                                ?>
                                <span class="status-badge status-<?php echo $color; ?>" style="font-size: 0.7rem;">
                                    <?php echo $log['action']; ?>
                                </span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                <strong><?php echo htmlspecialchars($log['table_name']); ?></strong> 
                                <span style="opacity: 0.6;">#<?php echo $log['record_id']; ?></span>
                            </td>
                            <td style="max-width: 300px;">
                                <p style="margin: 0; font-size: 0.85rem; color: var(--primary-400); font-style: italic;">
                                    <?php echo htmlspecialchars($log['reason'] ?? 'N/A'); ?>
                                </p>
                            </td>
                            <td style="text-align: right;">
                                <button class="btn-icon" onclick="toggleDetails(<?php echo $log['id']; ?>)">
                                    <i class="ph ph-caret-down" id="icon-<?php echo $log['id']; ?>"></i>
                                </button>
                            </td>
                        </tr>
                        <tr id="details-<?php echo $log['id']; ?>" style="display: none; background: rgba(0,0,0,0.2);">
                            <td colspan="6" style="padding: 1.5rem;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                    <div>
                                        <h5 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: var(--danger);">Valor Anterior</h5>
                                        <pre style="background: var(--bg-card); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.8rem; overflow-x: auto; color: var(--text-secondary);"><?php 
                                            $old = json_decode($log['old_value'], true);
                                            echo $old ? json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A'; 
                                        ?></pre>
                                    </div>
                                    <div>
                                        <h5 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: var(--success);">Valor Nuevo</h5>
                                        <pre style="background: var(--bg-card); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.8rem; overflow-x: auto; color: var(--text-primary);"><?php 
                                            $new = json_decode($log['new_value'], true);
                                            echo $new ? json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'N/A'; 
                                        ?></pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="padding: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; border-top: 1px solid var(--border-color);">
                <?php if ($page > 1): ?>
                    <a href="?page=1" class="btn btn-sm btn-secondary">«</a>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn btn-sm btn-secondary">‹</a>
                <?php endif; ?>

                <?php 
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm btn-secondary">›</a>
                    <a href="?page=<?php echo $totalPages; ?>" class="btn btn-sm btn-secondary">»</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    const icon = document.getElementById('icon-' + id);
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        icon.style.transform = 'rotate(180deg)';
    } else {
        row.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}
</script>

<style>
pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
}
.btn-icon {
    transition: transform 0.2s;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
