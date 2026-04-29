<?php
// modules/admin/audit_logs.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Permission Check
if (!can_access_module('audit_logs', $pdo)) {
    die("Acceso denegado: No tienes permisos suficientes para ver los logs de auditoría.");
}

$page_title = 'Monitoreo de Auditoría';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Stats for Header
$todayStart = date('Y-m-d 00:00:00');
try {
    $statsSql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN action = 'INSERT' THEN 1 ELSE 0 END) as inserts,
            SUM(CASE WHEN action = 'UPDATE' THEN 1 ELSE 0 END) as updates,
            SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END) as deletes
        FROM audit_logs 
        WHERE created_at >= '$todayStart'
    ";
    $stats = $pdo->query($statsSql)->fetch();
} catch (Exception $e) {
    $stats = ['inserts' => 0, 'updates' => 0, 'deletes' => 0];
}

// Fetch logs
$sql = "
    SELECT al.*, u.username 
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll();

$totalLogs = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$totalPages = ceil($totalLogs / $limit);
?>

<style>
/* AUDIT REDESIGN - THEME SCOPE */
.audit-reset-scope {
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 5rem;
}

/* Dashboard Header */
.audit-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card);
    padding: 2.5rem;
    border-radius: 24px;
    margin-bottom: 2.5rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-lg);
}

.audit-dashboard-header h1 {
    font-size: 1.8rem;
    margin: 0 0 0.5rem 0;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--text-main);
}

.audit-dashboard-header p {
    color: var(--text-muted);
    margin: 0;
}

.stats-grid-compact {
    display: flex;
    gap: 1.5rem;
}

.stat-bubble {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem 1.75rem;
    background: var(--bg-hover);
    border-radius: 18px;
    border: 1px solid var(--border-color);
    min-width: 120px;
}

.bubble-val {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--bubble-color);
    line-height: 1;
}

.bubble-lbl {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-top: 0.5rem;
    font-weight: 600;
}

/* Timeline Components */
.audit-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    position: relative;
    padding-left: 1rem;
}

.audit-card-wrapper {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    box-shadow: var(--shadow-sm);
}

.audit-card-wrapper:hover {
    border-color: var(--primary-500);
    transform: translateX(8px);
    box-shadow: var(--shadow-md);
}

.audit-card-wrapper.is-expanded {
    background: var(--bg-card);
    border-color: var(--primary-500);
    box-shadow: var(--shadow-xl);
}

.audit-card-main {
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    cursor: pointer;
}

.card-indicator {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
}

.action-insert .card-indicator { background: #10b981; }
.action-update .card-indicator { background: #f59e0b; }
.action-delete .card-indicator { background: #ef4444; }

.card-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: var(--bg-hover);
    flex-shrink: 0;
}

.action-insert .card-icon { color: #10b981; }
.action-update .card-icon { color: #f59e0b; }
.action-delete .card-icon { color: #ef4444; }

.card-content-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 2.5fr 3.5fr 1.5fr;
    align-items: center;
    gap: 2.5rem;
}

.log-action-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.15rem;
    color: var(--text-main);
    font-weight: 700;
}

.log-meta {
    display: flex;
    gap: 1.25rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.grid-reason {
    border-left: 1px solid var(--border-color);
    padding-left: 2rem;
}

.reason-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 700;
}

.reason-text {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-main);
    font-style: italic;
    line-height: 1.5;
    opacity: 0.9;
}

.btn-expand-card {
    background: var(--bg-hover);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
    width: 100%;
}

.btn-expand-card i { transition: transform 0.3s; }

.btn-expand-card:hover {
    border-color: var(--primary-500);
    color: var(--primary-500);
}

.btn-expand-card.active {
    background: var(--primary-500);
    color: #fff;
    border-color: var(--primary-600);
}

/* Expansion Content */
.audit-card-details {
    border-top: 1px solid var(--border-color);
    background: var(--bg-body);
}

.details-inner {
    padding: 2.5rem;
}

.details-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-color);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-weight: 800;
    color: var(--primary-500);
    text-transform: uppercase;
    font-size: 0.9rem;
}

.log-id-badge {
    background: var(--bg-hover);
    padding: 0.4rem 1rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-muted);
    border: 1px solid var(--border-color);
}

/* Pagination Section */
.audit-pagination {
    margin-top: 4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 2.5rem;
    border-top: 1px solid var(--border-color);
}

.page-link {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--bg-card);
    padding: 0.9rem 1.75rem;
    border-radius: 14px;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
    font-weight: 700;
    color: var(--text-main);
    text-decoration: none;
}

.page-link:hover {
    background: var(--primary-500);
    color: #fff;
    border-color: var(--primary-600);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.page-numbers {
    display: flex;
    gap: 0.75rem;
}

.num-link {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    transition: all 0.2s;
    color: var(--text-main);
    text-decoration: none;
    font-weight: 700;
}

.num-link:hover {
    border-color: var(--primary-500);
    color: var(--primary-500);
}

.num-link.is-active {
    background: var(--primary-500);
    color: #fff;
    border-color: var(--primary-600);
    box-shadow: 0 0 20px var(--primary-glow);
}

/* Visual Diff Enhancements Wrapper */
.diff-view-container {
    background: var(--bg-hover);
    padding: 1.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow-x: auto; /* Prevent table overflow */
}

.audit-diff-table {
    width: 100% !important;
    border-collapse: separate;
    border-spacing: 0 8px;
}

.audit-diff-table th {
    text-align: left;
    padding: 1rem;
    color: var(--text-muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 700;
}

.audit-diff-table td {
    padding: 1.25rem;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}

.audit-diff-table td:first-child { border-radius: 12px 0 0 12px; }
.audit-diff-table td:last-child { border-radius: 0 12px 12px 0; }

.diff-deleted { 
    background: rgba(239, 68, 68, 0.05) !important; 
    color: #ef4444 !important;
    text-decoration: line-through;
}

.diff-added { 
    background: rgba(16, 185, 129, 0.05) !important; 
    color: #10b981 !important;
    font-weight: 600;
}

.diff-label {
    background: var(--bg-body) !important;
    border-color: var(--border-color) !important;
}

.diff-null {
    opacity: 0.5;
    font-style: italic;
}

/* Mobile Adjustments */
@media (max-width: 992px) {
    .card-content-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .grid-reason {
        border-left: none;
        padding-left: 0;
        border-top: 1px solid var(--border-color);
        padding-top: 1rem;
    }
    .audit-dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 2rem;
    }
}
</style>

<div class="animate-enter audit-reset-scope">
    <!-- Header Summary Section -->
    <div class="audit-dashboard-header">
        <div class="header-content">
            <h1><i class="ph-bold ph-shield-checkered" style="color: var(--primary-500);"></i> Monitoreo de Seguridad</h1>
            <p>Historial centralizado de modificaciones críticas del sistema.</p>
        </div>
        <div class="stats-grid-compact">
            <div class="stat-bubble" style="--bubble-color: #10b981;">
                <span class="bubble-val"><?php echo $stats['inserts'] ?? 0; ?></span>
                <span class="bubble-lbl">Altas Hoy</span>
            </div>
            <div class="stat-bubble" style="--bubble-color: #f59e0b;">
                <span class="bubble-val"><?php echo $stats['updates'] ?? 0; ?></span>
                <span class="bubble-lbl">Cambios</span>
            </div>
            <div class="stat-bubble" style="--bubble-color: #ef4444;">
                <span class="bubble-val"><?php echo $stats['deletes'] ?? 0; ?></span>
                <span class="bubble-lbl">Bajas</span>
            </div>
        </div>
    </div>

    <!-- The Timeline -->
    <div class="audit-timeline">
        <?php if (empty($logs)): ?>
            <div class="empty-state" style="padding: 5rem; text-align: center; color: var(--text-muted);">
                <i class="ph ph-tray" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                <p>No hay registros de auditoría para mostrar.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($logs as $log): ?>
            <?php 
                $actionIcon = 'ph-file-plus';
                $actionClass = 'action-insert';
                if($log['action'] == 'UPDATE') { $actionIcon = 'ph-pencil-simple'; $actionClass = 'action-update'; }
                if($log['action'] == 'DELETE') { $actionIcon = 'ph-trash'; $actionClass = 'action-delete'; }
            ?>
            <div class="audit-card-wrapper <?php echo $actionClass; ?>" id="log-card-<?php echo $log['id']; ?>">
                <div class="audit-card-main" onclick="toggleAuditCard(<?php echo $log['id']; ?>)">
                    <div class="card-indicator"></div>
                    <div class="card-icon">
                        <i class="ph-bold <?php echo $actionIcon; ?>"></i>
                    </div>
                    
                    <div class="card-content-grid">
                        <div class="grid-primary">
                            <h3 class="log-action-title"><?php echo $log['action']; ?> en <?php echo htmlspecialchars($log['table_name']); ?></h3>
                            <div class="log-meta">
                                <span class="meta-item"><i class="ph ph-user"></i> <?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></span>
                                <span class="meta-item"><i class="ph ph-calendar"></i> <?php echo date('d M, Y H:i', strtotime($log['created_at'])); ?></span>
                                <span class="meta-item"><i class="ph ph-hash"></i> ID: <?php echo $log['record_id']; ?></span>
                            </div>
                        </div>
                        
                        <div class="grid-reason">
                            <span class="reason-label">Motivo:</span>
                            <p class="reason-text"><?php echo htmlspecialchars($log['reason'] ?? 'Sin motivo registrado'); ?></p>
                        </div>
                        
                        <div class="grid-toggle">
                            <button class="btn-expand-card">
                                <span>Ver Cambios</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Expanded Detail Section -->
                <div class="audit-card-details" style="display: none;">
                    <div class="details-inner">
                        <div class="details-header-bar">
                            <div class="header-left">
                                <i class="ph-bold ph-magnifying-glass-plus"></i>
                                <span>Análisis Técnico Forense</span>
                            </div>
                            <span class="log-id-badge">LOG #<?php echo $log['id']; ?></span>
                        </div>
                        
                        <div class="diff-view-container">
                            <?php echo render_audit_diff($log['old_value'], $log['new_value']); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modern Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="audit-pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="page-link"><i class="ph ph-arrow-left"></i> Anteriores</a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php for($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="num-link <?php echo $i == $page ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="page-link">Siguientes <i class="ph ph-arrow-right"></i></a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleAuditCard(id) {
    const card = document.getElementById('log-card-' + id);
    if (!card) return;

    const details = card.querySelector('.audit-card-details');
    const btn = card.querySelector('.btn-expand-card');
    const caret = btn.querySelector('i');
    
    const isOpening = details.style.display === 'none';

    if (isOpening) {
        details.style.display = 'block';
        card.classList.add('is-expanded');
        btn.classList.add('active');
        caret.style.transform = 'rotate(180deg)';
    } else {
        details.style.display = 'none';
        card.classList.remove('is-expanded');
        btn.classList.remove('active');
        caret.style.transform = 'rotate(0deg)';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
