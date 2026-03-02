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

// Fetch all technicians involved and their assigned concepts/amounts
$stmtD = $pdo->prepare("SELECT cd.*, u.username as tech_name 
                        FROM comision_detalles cd
                        JOIN users u ON cd.tech_id = u.id
                        WHERE cd.comision_id = ?
                        ORDER BY u.username ASC, cd.concept ASC");
$stmtD->execute([$id]);
$detalles_raw = $stmtD->fetchAll(PDO::FETCH_ASSOC);

// Pivot Data for Display
$matrix = [];
$techs = [];
$conceptsSet = [];

foreach ($detalles_raw as $row) {
    $tId = $row['tech_id'];
    $tName = $row['tech_name'];
    $concept = $row['concept'];
    $amount = floatval($row['amount']);

    if (!isset($techs[$tId])) {
        $techs[$tId] = ['name' => $tName, 'total' => 0];
    }

    if (!in_array($concept, $conceptsSet)) {
        $conceptsSet[] = $concept;
    }

    $matrix[$concept][$tId] = $amount;
    $techs[$tId]['total'] += $amount;
}

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
            <?php if ($comision['status'] === 'draft' && can_access_module('comisiones_edit', $pdo)): ?>
                <a href="mark_paid.php?id=<?php echo $comision['id']; ?>" class="btn btn-primary" style="background: var(--success); border-color: var(--success);" onclick="return confirm('¿Confirmar que estas comisiones ya fueron pagadas a los técnicos?');">
                    <i class="ph ph-check-circle"></i> Marcar como Pagada
                </a>
            <?php endif; ?>
            <?php if (!empty($comision['survey_id'])): ?>
                <a href="../levantamientos/view.php?id=<?php echo $comision['survey_id']; ?>" class="btn btn-primary"
                    style="background: var(--primary-600);">
                    <i class="ph ph-clipboard"></i> Ver Proyecto Original
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-card">
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">PROYECTO
                </p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <?php echo htmlspecialchars($comision['project_title']); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">FECHA</p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <?php echo date('d/m/Y', strtotime($comision['date'])); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">REGISTRADO
                    POR</p>
                <div style="font-size: 1.1rem; font-weight: 600;">
                    <i class="ph ph-user"></i>
                    <?php echo htmlspecialchars($comision['creator_name']); ?>
                </div>
            </div>
            <div>
                <p class="text-muted" style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">ESTADO</p>
                <?php
                $scls = 'bg-gray-500';
                $stxt = 'Borrador';
                if ($comision['status'] == 'paid') {
                    $scls = 'bg-green-500';
                    $stxt = 'Pagado';
                }
                ?>
                <span class="badge"
                    style="background: <?php echo $scls; ?>20; color: <?php echo $scls; ?>; font-size: 1rem; border: 1px solid <?php echo $scls; ?>40;">
                    <?php echo $stxt; ?>
                </span>
            </div>
        </div>

        <hr style="border-color: var(--border-color); margin: 2rem 0;">
        <h3 style="margin-bottom: 1rem;">Desglose de Comisiones por Técnico</h3>

        <?php if (!empty($techs)): ?>
            <div class="matrix-wrapper">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 250px;">Concepto</th>
                            <?php foreach ($techs as $tId => $tData): ?>
                                <th>
                                    <i class="ph ph-user"></i>
                                    <?php echo htmlspecialchars($tData['name']); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conceptsSet as $concept): ?>
                            <tr>
                                <td style="text-align: left; font-weight: 500;">
                                    <?php echo htmlspecialchars($concept); ?>
                                </td>
                                <?php foreach ($techs as $tId => $tData): ?>
                                    <td style="color: var(--text-color);">
                                        <?php
                                        $val = isset($matrix[$concept][$tId]) ? $matrix[$concept][$tId] : 0;
                                        echo $val > 0 ? '$' . number_format($val, 2) : '<span style="color: var(--border-color);">-</span>';
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--bg-surface);">
                            <td style="text-align: right; font-weight: bold;">TOTAL POR TÉCNICO:</td>
                            <?php foreach ($techs as $tId => $tData): ?>
                                <td style="font-weight: bold; color: var(--success); font-size: 1.1rem;">
                                    $
                                    <?php echo number_format($tData['total'], 2); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" style="text-align: center; padding: 2rem;">
                <i class="ph ph-warning-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                No se encontraron detalles de comisiones registrados.
            </div>
        <?php endif; ?>

        <div class="grand-total-box">
            <p style="margin: 0; font-size: 1rem; opacity: 0.8;">GRAN TOTAL ASIGNADO</p>
            <div style="font-size: 2.5rem; font-weight: bold; margin-top: 0.5rem;">
                $
                <?php echo number_format($comision['total_amount'], 2); ?>
            </div>
            <p style="margin: 1rem 0 0 0; font-size: 0.85rem; opacity: 0.7;">
                Registrado el
                <?php echo date('d/m/Y h:i A', strtotime($comision['created_at'])); ?>
            </p>
        </div>

    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>