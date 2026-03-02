<?php
// modules/anexos/print.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('anexos', $pdo)) {
    die("Acceso denegado.");
}

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = intval($_GET['id']);

// Fetch Anexo and its Tools
$stmt = $pdo->prepare("SELECT a.* FROM anexos_yazaki a WHERE a.id = ?");
$stmt->execute([$id]);
$anexo = $stmt->fetch();

if (!$anexo) {
    die("Anexo no encontrado.");
}

// Mark generated on first print
if ($anexo['status'] == 'draft' && has_role(['Super Admin', 'Administrador', 'Supervisor', 'Técnico'], $pdo)) {
    $pdo->prepare("UPDATE anexos_yazaki SET status = 'generated' WHERE id = ?")->execute([$id]);
}

$stmtTools = $pdo->prepare("
    SELECT at.*, t.name as tool_name
    FROM anexo_tools at
    LEFT JOIN tools t ON at.tool_id = t.id
    WHERE at.anexo_id = ?
    ORDER BY at.row_index ASC
");
$stmtTools->execute([$id]);
$toolsArray = $stmtTools->fetchAll();

// Map tools to sequential array
$mappedTools = [];
foreach ($toolsArray as $t) {
    $mappedTools[] = $t;
}

// Chunk arrays into blocks of 15
$chunks = array_chunk($mappedTools, 15);
if (empty($chunks)) {
    $chunks = [[]]; // ensure at least one empty page renders
}

// Constants for Yazaki Format
$MAX_ROWS = 15;
$EMPRESA_PRESTADORA = "Mastertec";
$EMPRESA_RECIBE = $anexo['client_name'] ?: "YAZAKI DE NICARAGUA SA";
$DELEGACION_ADUANA = "Zona Franca Yazaki";

// Formatting dates
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');
$date_format = date('d/m/Y', strtotime($anexo['created_at']));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anexo 10 - Yazaki - #
        <?php echo $id; ?>
    </title>
    <style>
        /* Base Print Settings */
        @page {
            size: A4;
            margin: 1.5cm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
        }

        /* Header Styles */
        .anexo-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: -15px;
            margin-top: 15px;
        }

        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .header-logos img {
            max-height: 70px;
            width: auto;
        }

        .gov-logo-placeholder {
            font-size: 9pt;
            font-style: italic;
            color: #016b9b;
            font-weight: bold;
            line-height: 1.1;
            text-align: left;
        }

        .dga-title {
            text-align: center;
            color: #00aced;
            font-weight: bold;
            font-size: 13pt;
            letter-spacing: 1px;
            margin-top: 5px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .tramitante-label {
            text-align: right;
            font-weight: bold;
            font-size: 10pt;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 30px;
        }

        /* Form Fields Layout */
        .form-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: flex-end;
        }

        .form-row-single {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-end;
        }

        .field-label {
            margin-right: 10px;
        }

        .field-value {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            padding: 0 5px;
            min-height: 20px;
        }

        .field-value-fixed {
            border-bottom: 1px solid #000;
            padding: 0 5px;
            min-height: 20px;
            min-width: 250px;
        }

        /* Tools Table */
        .tools-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .tools-table th,
        .tools-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 9.5pt;
            height: 18px;
        }

        .tools-table th {
            text-align: center;
            font-weight: normal;
        }

        .tools-table .col-item {
            width: 40px;
            text-align: center;
        }

        .tools-table .col-cant {
            width: 60px;
            text-align: center;
        }

        /* Empty Box styling */
        .empty-box {
            width: 100%;
            border: 1px solid #000;
            height: 100px;
            margin-bottom: 15px;
        }

        /* Signatures Layout */
        .signatures-area {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-box {
            width: 45%;
            text-align: left;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="background:#f1f5f9; padding: 1rem; text-align: center; border-bottom: 1px solid #cbd5e1; margin-bottom: 2rem;">
        <button onclick="window.print()"
            style="background:#6366f1; color:white; border:none; padding: 0.5rem 1.5rem; border-radius: 4px; font-weight: bold; cursor:pointer;">
            Imprimir Formulario
        </button>
        <button onclick="window.close()"
            style="background:transparent; color:#475569; border:1px solid #94a3b8; padding: 0.5rem 1.5rem; border-radius: 4px; font-weight: bold; cursor:pointer; margin-left: 1rem;">
            Cerrar
        </button>
    </div>

    <?php foreach ($chunks as $pageIndex => $chunkTools): ?>
        <div class="container" style="<?php echo $pageIndex > 0 ? 'page-break-before: always; margin-top: 1.5cm;' : ''; ?>">
            <!-- HEADER SECTION -->
            <div class="header-logos">
                <div class="gov-logo-placeholder">
                    <img src="../../assets/img/logo_gobierno.png" alt="Gobierno de Reconciliación y Unidad Nacional"
                        style="max-height: 80px; width: auto; margin-top: -10px;">
                </div>
                <!-- Title in center -->
                <div class="anexo-title" style="margin-top: 0;">Anexo 10
                    <?php echo count($chunks) > 1 ? "(Pág. " . ($pageIndex + 1) . " de " . count($chunks) . ")" : ""; ?>
                </div>
                <!-- Empty space for symmetry -->
                <div style="width: 150px;"></div>
            </div>

            <div class="dga-title">DIRECCION GENERAL DE SERVICIOS ADUANEROS (DGA)</div>
            <div class="tramitante-label">Logo de Tramitante</div>

            <div class="form-title">Formulario Ingreso de Herramientas para trabajos de MTTO</div>

            <!-- FORM FIELDS SECTION -->
            <div class="form-row">
                <div style="display: flex; align-items: flex-end; width: 45%;">
                    <span class="field-label">Fecha de Ingreso</span>
                    <span class="field-value" style="text-align: center;"><?php echo $date_format; ?></span>
                </div>
                <div style="display: flex; align-items: flex-end; width: 45%;">
                    <span class="field-label">Delegación de Aduana</span>
                    <span class="field-value" style="text-align: center;"><?php echo $DELEGACION_ADUANA; ?></span>
                </div>
            </div>

            <div class="form-row-single" style="width: 60%; margin-top: 10px;">
                <span class="field-label" style="width: 220px;">Empresa Prestadora del servicio</span>
                <span class="field-value"
                    style="text-align: left; font-size: 9pt;"><?php echo $EMPRESA_PRESTADORA; ?></span>
            </div>

            <div class="form-row-single" style="width: 65%; margin-top: 10px;">
                <span class="field-label" style="width: 220px;">Empresa que recibe servicio</span>
                <span class="field-value" style="text-align: left; font-size: 10pt;"><?php echo $EMPRESA_RECIBE; ?></span>
            </div>

            <!-- TOOLS TABLE -->
            <table class="tools-table">
                <thead>
                    <tr>
                        <th class="col-item">Item</th>
                        <th class="col-cant">Cant</th>
                        <th>Descripción de Maquina y/o herramientas.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $startIndex = $pageIndex * 15;
                    $rowsToDraw = max(1, count($chunkTools));
                    for ($i = 0; $i < $rowsToDraw; $i++):
                        $cant = "";
                        $desc = "";
                        $itemNum = "";

                        if (isset($chunkTools[$i])) {
                            $cant = floatval($chunkTools[$i]['quantity']);
                            $desc = htmlspecialchars($chunkTools[$i]['tool_id'] ? $chunkTools[$i]['tool_name'] : $chunkTools[$i]['custom_description']);
                            $itemNum = $startIndex + $i + 1;
                        }
                        ?>
                        <tr>
                            <td class="col-item"><?php echo $itemNum; ?></td>
                            <td class="col-cant"><?php echo $cant; ?></td>
                            <td><?php echo $desc; ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- EMPTY BOX AREA -->
            <div class="empty-box"></div>

            <!-- BOTTOM FIELDS -->
            <div class="form-row-single" style="width: 80%;">
                <span class="field-label">Días Solicitados de permanencia</span>
                <span class="field-value"></span>
            </div>

            <!-- SIGNATURES -->
            <div class="signatures-area">
                <div class="signature-box">
                    firma y sello de solicitante
                </div>
                <div class="signature-box" style="text-align: right;">
                    firma y sello de Delegación de Aduana
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</body>

</html>
```