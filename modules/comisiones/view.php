<?php
// modules/comisiones/view.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones', $pdo)) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Ensure the ID exists and fetch header data
$stmt = $pdo->prepare("SELECT c.*, u.username as creator_name 
                       FROM comisiones c 
                       LEFT JOIN users u ON c.created_by = u.id 
                       WHERE c.id = ?");
$stmt->execute([$id]);
$comision = $stmt->fetch();

if (!$comision) {
    header("Location: index.php");
    exit;
}

// Since the table is now flat, we don't have cd.detalles, just one record.
$page_title = 'Ver Comisión #' . str_pad($comision['id'], 5, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

$page_title = 'Ver Comisión #' . str_pad($comision['id'], 5, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
    }

    .info-card {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }

    .matrix-wrapper {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow-x: auto;
        margin-top: 1rem;
    }

    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }

    .matrix-table th,
    .matrix-table td {
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .matrix-table th {
        background: var(--bg-surface);
        font-weight: 600;
        color: var(--text-muted);
    }

    .grand-total-box {
        background: var(--primary-900);
        color: var(--primary-100);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--primary-700);
        text-align: right;
        margin-top: 2rem;
    }
</style>

<div class="animate-enter" style="max-width: 1000px; margin: 0 auto;">

    <div class="page-header">
        <div>
            <h1 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                Comisión #
                <?php echo str_pad($comision['id'], 5, '0', STR_PAD_LEFT); ?>
            </h1>
            <p class="text-muted" style="margin: 0;">Reporte de asignación para Proyecto.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php" class="btn btn-secondary">
                <i class="ph ph-arrow-left"></i> Volver
            </a>
            <?php if ($comision['estado'] === 'PENDIENTE' && can_access_module('comisiones_edit', $pdo)): ?>
                <form action="mark_paid.php" method="POST" style="display: inline;"
                    onsubmit="return confirm('¿Confirmar que esta comisión ya fue pagada?');">
                    <input type="hidden" name="id" value="<?php echo $comision['id']; ?>">
                    <button type="submit" class="btn btn-primary"
                        style="background: var(--success); border-color: var(--success);">
                        <i class="ph ph-check-circle"></i> Marcar como Pagada
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($comision['tipo'] === 'PROYECTO' && !empty($comision['reference_id'])): ?>
                <a href="../levantamientos/view.php?id=<?php echo $comision['reference_id']; ?>" class="btn btn-primary"
                    style="background: var(--primary-600);">
                    <i class="ph ph-clipboard"></i> Ver Proyecto
                </a>
            <?php elseif ($comision['tipo'] === 'SERVICIO' && !empty($comision['reference_id'])): ?>
                <a href="../services/view.php?id=<?php echo $comision['reference_id']; ?>" class="btn btn-primary"
                    style="background: var(--primary-600);">
                    <i class="ph ph-clipboard"></i> Ver Servicio
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-card">
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">PROYECTO /
                    CASO
                </p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <?php echo htmlspecialchars($comision['caso'] ?: 'N/A'); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">FECHA DE
                    SERVICIO</p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <?php echo date('d/m/Y', strtotime($comision['fecha_servicio'])); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">TÉCNICO
                    ASIGNADO
                </p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <i class="ph ph-user"></i>
                    <?php
                    if ($comision['tech_id']) {
                        $stmtT = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                        $stmtT->execute([$comision['tech_id']]);
                        echo htmlspecialchars($stmtT->fetchColumn() ?: 'Desconocido');
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">ESTADO</p>
                <?php
                $scls = $comision['estado'] === 'PAGADA' ? 'bg-green-500' : 'bg-orange-500';
                $stxt = $comision['estado'];
                ?>
                <span class="badge"
                    style="background: <?php echo $scls; ?>20; color: <?php echo $scls; ?>; font-size: 1rem; border: 1px solid <?php echo $scls; ?>40;">
                    <?php echo $stxt; ?>
                </span>
            </div>
        </div>

        <hr style="border-color: var(--border-color); margin: 2rem 0;">
        <h3 style="margin-bottom: 1rem;">Detalles del Servicio</h3>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">TIPO</p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['tipo']); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">CLIENTE</p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['cliente']); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">LUGAR</p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['lugar'] ?: 'N/A'); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">SERVICIO
                </p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['servicio']); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">VENDEDOR
                </p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['vendedor'] ?: 'N/A'); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">FACTURA /
                    O.S.</p>
                <div style="font-size: 1rem; color: var(--text-color);">
                    <?php echo htmlspecialchars($comision['factura'] ?: 'N/A'); ?>
                </div>
            </div>
            <?php if ($comision['fecha_facturacion']): ?>
                <div>
                    <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">FECHA DE
                        FACTURACIÓN</p>
                    <div style="font-size: 1rem; color: var(--text-color);">
                        <?php echo date('d/m/Y', strtotime($comision['fecha_facturacion'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="grand-total-box">
            <p style="margin: 0; font-size: 1rem; opacity: 0.8;">CUOTA ASIGNADA</p>
            <div style="font-size: 2.5rem; font-weight: bold; margin-top: 0.5rem;">
                $
                <?php echo number_format($comision['cantidad'], 2); ?>
            </div>
        </div>



    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>