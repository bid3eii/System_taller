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

$page_title = 'Flujo de Auditoría Premium';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Pagination
$limit = 20; // Lower limit per page for cards
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Stats for Header
$todayStart = date('Y-m-d 00:00:00');
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

<div class="animate-enter audit-reset-scope">
    <!-- Header Summary Section -->
    <div class="audit-dashboard-header">
        <div class="header-content">
            <h1><i class="ph-bold ph-shield-checkered" style="color: var(--primary-500);"></i> Monitoreo de Seguridad</h1>
            <p>Historial centralizado de modificaciones críticas del sistema.</p>
        </div>
        <div class="stats-grid-compact">
            <div class="stat-bubble" style="--bubble-color: var(--emerald-500);">
                <span class="bubble-val"><?php echo $stats['inserts'] ?? 0; ?></span>
                <span class="bubble-lbl">Altas Hoy</span>
            </div>
            <div class="stat-bubble" style="--bubble-color: var(--warning-500);">
                <span class="bubble-val"><?php echo $stats['updates'] ?? 0; ?></span>
                <span class="bubble-lbl">Cambios</span>
            </div>
            <div class="stat-bubble" style="--bubble-color: var(--danger-500);">
                <span class="bubble-val"><?php echo $stats['deletes'] ?? 0; ?></span>
                <span class="bubble-lbl">Bajas</span>
            </div>
        </div>
    </div>

    <!-- The Timeline -->
    <div class="audit-timeline">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="ph ph-tray"></i>
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
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php for($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="num-link <?php echo $i == $page ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="page-link">Siguientes <i class="ph ph-arrow-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
/**
 * Logic to expand audit cards without relying on table structures.
 */
function toggleAuditCard(id) {
    const card = document.getElementById('log-card-' + id);
    if (!card) return;

    const details = card.querySelector('.audit-card-details');
    const btn = card.querySelector('.btn-expand-card');
    const caret = btn.querySelector('i');
    
    const isOpening = details.style.display === 'none';

    // Optional: Switch off others
    if (isOpening) {
        document.querySelectorAll('.audit-card-wrapper').forEach(c => {
            if (c.id !== 'log-card-' + id) {
                c.classList.remove('is-expanded');
                const d = c.querySelector('.audit-card-details');
                const b = c.querySelector('.btn-expand-card');
                const i = b.querySelector('i');
                if (d) d.style.display = 'none';
                if (b) b.classList.remove('active');
                if (i) i.style.transform = 'rotate(0deg)';
            }
        });
    }

    if (isOpening) {
        details.style.display = 'block';
        card.classList.add('is-expanded');
        btn.classList.add('active');
        caret.style.transform = 'rotate(180deg)';
        // Smooth scroll to view
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        details.style.display = 'none';
        card.classList.remove('is-expanded');
        btn.classList.remove('active');
        caret.style.transform = 'rotate(0deg)';
    }
}
</script>

<style>
/* AUDIT REDESIGN - THEME SCOPE */
:root {
    --audit-card-bg: rgba(30, 41, 59, 0.4);
    --audit-border: rgba(255, 255, 255, 0.08);
    --audit-glow: 0 0 20px rgba(99, 102, 241, 0.2);
}

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
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.8));
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 3rem;
    border: 1px solid var(--audit-border);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.audit-dashboard-header h1 {
    font-size: 1.8rem;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
}

.audit-dashboard-header p {
    color: var(--slate-400);
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
    padding: 0.8rem 1.5rem;
    background: rgba(0,0,0,0.2);
    border-radius: 14px;
    border: 1px solid var(--audit-border);
    min-width: 100px;
}

.bubble-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--bubble-color);
    line-height: 1;
}

.bubble-lbl {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--slate-500);
    margin-top: 0.4rem;
}

/* Timeline Components */
.audit-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    position: relative;
    padding-left: 2rem;
}

.audit-timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 1rem;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary-500), transparent);
    opacity: 0.3;
}

.audit-card-wrapper {
    background: var(--audit-card-bg);
    border: 1px solid var(--audit-border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    backdrop-filter: blur(10px);
}

.audit-card-wrapper:hover {
    border-color: rgba(255,255,255,0.2);
    transform: translateX(10px);
    background: rgba(30, 41, 59, 0.6);
}

.audit-card-wrapper.is-expanded {
    background: rgba(15, 23, 42, 0.9);
    border-color: var(--primary-500);
    box-shadow: 0 0 40px rgba(0,0,0,0.4);
}

.audit-card-main {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    cursor: pointer;
}

.card-indicator {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.action-insert .card-indicator { background: var(--emerald-500); }
.action-update .card-indicator { background: var(--warning-500); }
.action-delete .card-indicator { background: var(--danger-500); }

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: rgba(0,0,0,0.2);
}

.action-insert .card-icon { color: var(--emerald-400); }
.action-update .card-icon { color: var(--warning-400); }
.action-delete .card-icon { color: var(--danger-400); }

.card-content-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 2fr 3fr 1fr;
    align-items: center;
    gap: 2rem;
}

.log-action-title {
    margin: 0 0 0.4rem 0;
    font-size: 1.1rem;
    color: #fff;
    font-weight: 600;
}

.log-meta {
    display: flex;
    gap: 1.2rem;
    font-size: 0.75rem;
    color: var(--slate-400);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.grid-reason {
    border-left: 1px solid var(--audit-border);
    padding-left: 1.5rem;
}

.reason-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--slate-500);
    letter-spacing: 0.05em;
    display: block;
    margin-bottom: 0.3rem;
}

.reason-text {
    margin: 0;
    font-size: 0.85rem;
    color: var(--slate-300);
    font-style: italic;
    line-height: 1.4;
}

.btn-expand-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--audit-border);
    color: var(--slate-400);
    padding: 0.6rem 1rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-size: 0.8rem;
    transition: all 0.3s;
    width: 100%;
}

.btn-expand-card i { transition: transform 0.4s; }

.btn-expand-card.active {
    background: var(--primary-500);
    color: #fff;
    border-color: var(--primary-600);
}

/* Expansion Content */
.audit-card-details {
    border-top: 1px solid var(--audit-border);
    background: rgba(0,0,0,0.2);
}

.details-inner {
    padding: 2rem;
}

.details-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--audit-border);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    font-weight: 700;
    color: var(--primary-400);
}

.log-id-badge {
    background: rgba(255,255,255,0.05);
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-family: monospace;
    color: var(--slate-500);
}

/* Pagination Section */
.audit-pagination {
    margin-top: 4rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 2rem;
    border-top: 1px solid var(--audit-border);
}

.page-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--audit-card-bg);
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    border: 1px solid var(--audit-border);
    transition: all 0.3s;
    font-weight: 600;
}

.page-link:hover {
    background: var(--primary-500);
    color: #fff;
    transform: translateY(-2px);
}

.page-numbers {
    display: flex;
    gap: 0.8rem;
}

.num-link {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--audit-border);
    transition: all 0.3s;
}

.num-link.is-active {
    background: var(--primary-500);
    color: #fff;
    border-color: var(--primary-400);
    box-shadow: 0 0 15px var(--primary-glow);
}

/* Empty State */
.empty-state {
    padding: 5rem;
    text-align: center;
    color: var(--slate-500);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

/* Visual Diff Enhancements Wrapper */
.diff-view-container {
    background: #000;
    padding: 1.5rem;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.05);
}

.audit-diff-table {
    width: 100% !important;
}

.diff-deleted { 
    background: rgba(239, 68, 68, 0.2) !important; 
    color: #fca5a5 !important;
    border-left: 4px solid #ef4444 !important;
}

.diff-added { 
    background: rgba(16, 185, 129, 0.2) !important; 
    color: #6ee7b7 !important;
    border-left: 4px solid #10b981 !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
