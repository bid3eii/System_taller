<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !can_access_module('viaticos', $pdo)) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    header("Location: index.php");
    exit;
}

try {
    // Header
    $stmtH = $pdo->prepare("SELECT v.*, u.username as creator_name FROM viaticos v LEFT JOIN users u ON v.created_by = u.id WHERE v.id = ?");
    $stmtH->execute([$id]);
    $viatico = $stmtH->fetch(PDO::FETCH_ASSOC);

    if (!$viatico) {
        header("Location: index.php");
        exit;
    }

    // Columns (Técnicos)
    $stmtCols = $pdo->prepare("SELECT * FROM viatico_columns WHERE viatico_id = ? ORDER BY display_order ASC");
    $stmtCols->execute([$id]);
    $columns = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

    // Concepts (Filas)
    $stmtRows = $pdo->prepare("SELECT * FROM viatico_concepts WHERE viatico_id = ? ORDER BY id ASC");
    $stmtRows->execute([$id]);
    $concepts = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

    // Amounts (Celdas)
    $stmtAmts = $pdo->prepare("SELECT * FROM viatico_amounts WHERE viatico_id = ?");
    $stmtAmts->execute([$id]);
    $amounts = $stmtAmts->fetchAll(PDO::FETCH_ASSOC);

    // Build the Matrix 2D Array: matrix[concept_id][column_id] = amount
    $matrix = [];
    foreach ($amounts as $amt) {
        $matrix[$amt['concept_id']][$amt['column_id']] = floatval($amt['amount']);
    }

    // Organize concepts by category for easier rendering
    $rows_by_cat = ['food' => [], 'transport' => [], 'other' => []];
    foreach ($concepts as $c) {
        $cat = $c['category'];
        if (isset($rows_by_cat[$cat])) {
            $rows_by_cat[$cat][] = $c;
        }
    }

} catch (Exception $e) {
    die("Error cargando viático: " . $e->getMessage());
}

?>
<?php
$page_title = 'Ver Viático #' . $id;
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<style>
    .viatico-grid {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-card);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .viatico-grid th,
    .viatico-grid td {
        border: 1px solid var(--border-color);
        padding: 0.75rem;
        text-align: right;
        position: relative;
    }

    .viatico-grid th {
        background: var(--bg-body);
        font-weight: 600;
        color: var(--text-main);
        text-align: center;
    }

    .viatico-grid th:first-child,
    .viatico-grid td:first-child {
        text-align: left;
        font-weight: 600;
        background: var(--bg-body);
        width: 200px;
    }

    .cat-header {
        background: rgba(var(--primary-rgb), 0.05) !important;
        color: var(--primary) !important;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .subtotal-row td {
        background: rgba(var(--secondary-rgb), 0.05);
        font-weight: bold;
        color: var(--text-main);
    }

    .grand-total-row td {
        background: rgba(var(--primary-rgb), 0.1);
        font-weight: bold;
        font-size: 1.1rem;
        color: var(--primary);
        border-top: 2px solid var(--primary);
    }

    .print-btn {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    @media print {

        .navbar,
        .sidebar,
        .print-btn,
        .header-actions {
            display: none !important;
        }

        .app-container {
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        body {
            background: white;
            color: black;
        }

        .viatico-grid th,
        .viatico-grid td {
            border-color: #ddd;
        }

        .grand-total-row td {
            -webkit-print-color-adjust: exact;
            background: #eef2ff !important;
            color: #3730a3 !important;
        }
    }
</style>
<main class="main-content">
    <div class="content-header" style="justify-content: space-between;">
        <div class="header-title">
            <h1 style="display: flex; align-items: center; gap: 0.5rem;">
                Reporte de Viático #<?php echo str_pad($viatico['id'], 5, '0', STR_PAD_LEFT); ?>
                <?php if ($viatico['status'] == 'paid'): ?>
                    <span class="badge"
                        style="background: var(--success); font-size: 0.8rem; margin-left: 1rem;">PAGADO</span>
                <?php endif; ?>
            </h1>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline"><i class="ph ph-arrow-left"></i> Volver a la Lista</a>
        </div>
    </div>

    <!-- Configuration Header Data -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.85rem; font-weight: bold; margin-bottom: 0.25rem;">
                    PROYECTO:</div>
                <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-main);">
                    <?php echo htmlspecialchars($viatico['project_title']); ?>
                </h2>
            </div>
            <div style="text-align: right;">
                <p class="text-muted" style="margin: 0;">Fecha: <strong
                        style="color: var(--text-main);"><?php echo date('d/m/Y', strtotime($viatico['date'])); ?></strong>
                </p>
                <p class="text-muted" style="margin: 0;">Creado por: <strong
                        style="color: var(--text-main);"><?php echo htmlspecialchars($viatico['creator_name']); ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Data Matrix -->
    <div style="overflow-x: auto; padding-bottom: 1rem;">
        <table class="viatico-grid">
            <thead>
                <tr>
                    <th>DETALLE</th>
                    <?php foreach ($columns as $col): ?>
                        <th><?php echo htmlspecialchars(strtoupper($col['tech_name'])); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalsByTech = array_fill_keys(array_column($columns, 'id'), 0);

                function renderSection($title, $catKey, $rows_by_cat, $columns, &$matrix, &$totalsByTech)
                {
                    if (empty($rows_by_cat[$catKey]))
                        return;

                    echo '<tr class="cat-header"><td colspan="' . (count($columns) + 1) . '">' . $title . '</td></tr>';

                    $subTotals = array_fill_keys(array_column($columns, 'id'), 0);

                    foreach ($rows_by_cat[$catKey] as $row) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars(strtoupper($row['label'])) . '</td>';

                        foreach ($columns as $col) {
                            $amount = isset($matrix[$row['id']][$col['id']]) ? $matrix[$row['id']][$col['id']] : 0;
                            $subTotals[$col['id']] += $amount;
                            $totalsByTech[$col['id']] += $amount;

                            echo '<td>' . ($amount > 0 ? number_format($amount, 2) : '-') . '</td>';
                        }
                        echo '</tr>';
                    }

                    // Subtotal Row
                    echo '<tr class="subtotal-row"><td>SUBTOTAL ' . $title . '</td>';
                    foreach ($columns as $col) {
                        echo '<td>' . number_format($subTotals[$col['id']], 2) . '</td>';
                    }
                    echo '</tr>';
                }

                renderSection('ALIMENTOS', 'food', $rows_by_cat, $columns, $matrix, $totalsByTech);
                renderSection('TRANSPORTE', 'transport', $rows_by_cat, $columns, $matrix, $totalsByTech);
                renderSection('OTROS', 'other', $rows_by_cat, $columns, $matrix, $totalsByTech);
                ?>
            </tbody>
            <tfoot>
                <tr class="grand-total-row">
                    <td>TOTAL</td>
                    <?php foreach ($columns as $col): ?>
                        <td><?php echo number_format($totalsByTech[$col['id']], 2); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Grand overall total -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 4rem;">
        <div style="font-size: 2rem; font-weight: bold; color: var(--primary);">
            TOTAL PROYECTO: $<span
                id="grandTotalDisplay"><?php echo number_format($viatico['total_amount'], 2); ?></span>
        </div>
    </div>

    <button onclick="window.print()" class="btn btn-primary print-btn" title="Descargar como PDF o Imprimir">
        <i class="ph ph-file-pdf"></i>
    </button>
</main>
<?php if (isset($_GET['pdf'])): ?>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 500);
        });
    </script>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>