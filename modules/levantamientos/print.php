<?php
// modules/levantamientos/print.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys', $pdo)) {
    die("Acceso denegado.");
}

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = intval($_GET['id']);

// Fetch Survey
$sql = "
    SELECT 
        ps.*, 
        u.username as tech_name
    FROM project_surveys ps
    LEFT JOIN users u ON ps.user_id = u.id
    WHERE ps.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$survey = $stmt->fetch();

if (!$survey) {
    die("Levantamiento no encontrado.");
}

// Fetch Materials
$stmtM = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
$stmtM->execute([$id]);
$materials = $stmtM->fetchAll();

// Formatting dates
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');
$date_format = strftime("%A %d de %B de %Y", strtotime($survey['created_at']));
// Fallback if strftime is deprecated/failing
if (!$date_format || strpos($date_format, '%') !== false) {
    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $timestamp = strtotime($survey['created_at']);
    $date_format = $days[date('w', $timestamp)] . ' ' . date('d', $timestamp) . ' de ' . $months[date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levantamiento #<?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?> -
        <?php echo htmlspecialchars($survey['client_name']); ?>
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* Print Core Variables */
        :root {
            --brand-main: #4f46e5;
            /* Indigo 600 */
            --brand-dark: #1e1b4b;
            /* Indigo 950 */
            --brand-accent: #818cf8;
            /* Indigo 400 */
            --text-main: #1e293b;
            /* Slate 800 */
            --text-muted: #64748b;
            /* Slate 500 */
            --border-light: #e2e8f0;
            /* Slate 200 */
            --bg-light: #f8fafc;
            /* Slate 50 */
        }

        /* Strict A4 Setup */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            background: #e2e8f0;
            /* Darker bg for screen preview */
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* The A4 "Paper" Container */
        .print-wrapper {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            /* For absolute elements like side bars */
        }

        /* Top Accent Bar */
        .accent-bar {
            height: 8mm;
            background: linear-gradient(90deg, var(--brand-dark) 0%, var(--brand-main) 50%, var(--brand-accent) 100%);
            width: 100%;
        }

        /* Inner Content Padding */
        .container {
            padding: 5mm 20mm 20mm 20mm;
        }

        /* Screen Controls - Hidden on print */
        .controls-bar {
            background: var(--brand-dark);
            width: 100%;
            padding: 1rem 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--brand-main);
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* --- Header & Branding --- */
        .doc-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 12mm;
        }

        .company-brand {
            margin-bottom: 6mm;
        }

        .company-logo {
            height: 25mm;
            /* Absolute physical size for print */
            width: auto;
            object-fit: contain;
        }

        .doc-title-block {
            text-align: center;
            width: 100%;
            padding-bottom: 6mm;
            border-bottom: 2px solid var(--border-light);
            position: relative;
        }

        /* Small decorative accent below title */
        .doc-title-block::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 50mm;
            height: 2px;
            background: var(--brand-main);
        }

        .doc-type {
            font-size: 16pt;
            font-weight: 800;
            color: var(--brand-dark);
            margin: 0 0 1mm 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .doc-number {
            font-size: 10pt;
            color: var(--brand-main);
            font-weight: 600;
            margin: 0;
            letter-spacing: 0.5px;
        }

        /* --- Meta Grid (Client/Date Info) --- */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            margin-bottom: 5mm;
            background: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 3.5mm 5mm;
        }

        .meta-col {
            display: flex;
            flex-direction: column;
            gap: 2mm;
        }

        .meta-group {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 1mm;
        }

        .meta-val {
            font-size: 10pt;
            font-weight: 500;
            color: var(--brand-dark);
            margin: 0;
        }

        /* --- Content Sections --- */
        .section {
            margin-bottom: 8mm;
        }

        .section-title {
            font-size: 11pt;
            font-weight: 800;
            color: var(--brand-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 4mm 0;
            display: flex;
            align-items: center;
            gap: 2mm;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 2mm;
        }

        /* Icon styling next to section titles if used */
        .section-icon {
            color: var(--brand-main);
            font-size: 1.2em;
        }

        .text-content {
            font-size: 9.5pt;
            color: #334155;
            text-align: justify;
            line-height: 1.6;
        }

        /* Rich Text Overrides */
        .rich-text {
            font-size: 9.5pt;
            color: #334155;
            line-height: 1.6;
        }

        .rich-text p {
            margin-top: 0;
            margin-bottom: 3mm;
        }

        .rich-text ul,
        .rich-text ol {
            margin-top: 2mm;
            margin-bottom: 4mm;
            padding-left: 8mm;
        }

        .rich-text li {
            margin-bottom: 1.5mm;
        }

        /* --- Tables --- */
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            border: 1px solid var(--border-light);
        }

        .styled-table th {
            background: var(--bg-light);
            color: var(--brand-dark);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.5pt;
            letter-spacing: 1px;
            padding: 3mm 4mm;
            text-align: left;
            border-bottom: 2px solid var(--border-light);
        }

        .styled-table td {
            padding: 3mm 4mm;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-main);
            vertical-align: top;
        }

        .styled-table tr:nth-child(even) td {
            background-color: #fcfcfd;
        }

        .styled-table th.center,
        .styled-table td.center {
            text-align: center;
        }

        /* --- Footer & Signatures --- */
        .doc-footer {
            margin-top: 15mm;
            padding-top: 6mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10mm;
            page-break-inside: avoid;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            gap: 15mm;
            padding: 0 10mm;
        }

        .sig-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .sig-line {
            width: 100%;
            border-top: 1px solid var(--brand-dark);
            margin-bottom: 2mm;
            margin-top: 15mm;
        }

        .sig-name {
            font-weight: 700;
            font-size: 9pt;
            color: var(--brand-dark);
        }

        .sig-role {
            font-size: 7.5pt;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1mm;
        }

        .legal-notice {
            font-size: 7pt;
            color: #94a3b8;
            text-align: center;
            max-width: 90%;
            border-top: 1px solid var(--border-light);
            padding-top: 4mm;
        }

        /* --- Web Print Media Query --- */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                margin: 0;
                padding: 0;
                align-items: flex-start;
            }

            .print-wrapper {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>

<body>

    <div class="controls-bar no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="ph ph-printer"></i> Imprimir Documento
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            Cerrar
        </button>
    </div>

    <div class="print-wrapper">
        <div class="accent-bar"></div>

        <div class="container">
            <!-- Header section -->
            <div class="doc-header" style="flex-direction: column; align-items: center; text-align: center; border-bottom: none; gap: 0; margin-bottom: 5mm;">
                <div class="company-brand" style="margin-bottom: 0;">
                    <img src="../../assets/img/logo.png" alt="M Technologies Logo" class="company-logo">
                </div>
                <div class="doc-title-block" style="text-align: center; width: 100%; border-bottom: 2px solid var(--border-light); padding-bottom: 3mm; margin-top: -2mm;">
                    <h2 class="doc-type">Levantamiento Operativo</h2>
                    <p class="doc-number">Referencia #<?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>

            <!-- Meta Information Grid -->
            <div class="meta-grid">
                <div class="meta-col">
                    <div class="meta-group">
                        <span class="meta-label">Titular / Constructora</span>
                        <p class="meta-val"><?php echo htmlspecialchars($survey['client_name']); ?></p>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Denominación del Proyecto</span>
                        <p class="meta-val"><?php echo htmlspecialchars($survey['title']); ?></p>
                    </div>
                </div>
                <div class="meta-col">
                    <div class="meta-group">
                        <span class="meta-label">Fecha de Emisión</span>
                        <p class="meta-val" style="text-transform: capitalize;"><?php echo $date_format; ?></p>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Asesor Comercial</span>
                        <p class="meta-val"><?php echo htmlspecialchars($survey['vendedor'] ?: 'N/A'); ?></p>
                    </div>
                    <div class="meta-group">
                        <span class="meta-label">Responsable Técnico</span>
                        <p class="meta-val"><?php echo htmlspecialchars($survey['tech_name']); ?></p>
                    </div>
                </div>
            </div>

            <!-- General Outline -->
            <div class="section">
                <h3 class="section-title">Descripción General</h3>
                <div class="text-content">
                    <?php if ($survey['general_description']): ?>
                        <p><?php echo nl2br(htmlspecialchars($survey['general_description'])); ?></p>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-style: italic;">Sin especificaciones generales.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scope / Work Plan -->
            <div class="section">
                <h3 class="section-title">Alcances y Plan de Trabajo</h3>
                <div class="rich-text">
                    <?php if ($survey['scope_activities']): ?>
                        <?php echo $survey['scope_activities']; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-style: italic;">No se han definido actividades de
                            implementación.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resources Estimation -->
            <div class="section">
                <h3 class="section-title">Logística y Estimación</h3>
                <div class="meta-grid" style="margin-bottom: 0; padding: 1.25rem;">
                    <div class="meta-col">
                        <div class="meta-group">
                            <span class="meta-label"><i class="ph ph-users"
                                    style="margin-right: 4px; font-size: 1.1em; vertical-align: middle;"></i> Fuerza
                                Laboral Requerida</span>
                            <p class="meta-val">
                                <?php echo htmlspecialchars($survey['personnel_required'] ?: 'No documentado'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="meta-col">
                        <div class="meta-group">
                            <span class="meta-label"><i class="ph ph-clock"
                                    style="margin-right: 4px; font-size: 1.1em; vertical-align: middle;"></i> Proyección
                                de Tiempo</span>
                            <p class="meta-val">
                                <?php echo htmlspecialchars($survey['estimated_time'] ?: 'No documentado'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Table -->
            <?php if (count($materials) > 0): ?>
                <div class="section" style="page-break-inside: avoid;">
                    <h3 class="section-title">Requerimientos de Material</h3>
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Descripción del Componente</th>
                                <th class="center" style="width: 15%;">Cantidad</th>
                                <th style="width: 35%;">Asignación / Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                                <tr>
                                    <td style="font-weight: 500; font-size: 9.5pt; color: var(--brand-dark);">
                                        <?php echo htmlspecialchars($m['item_description']); ?>
                                    </td>
                                    <td class="center" style="font-weight: 600;">
                                        <?php echo floatval($m['quantity']) . ' ' . htmlspecialchars($m['unit']); ?>
                                    </td>
                                    <td style="font-size: 9pt; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($m['notes'] ?: '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Footers and Signatures -->
            <div class="doc-footer" style="page-break-inside: avoid;">

                <div class="legal-notice">
                    Propiedad integral de M Technologies. Este documento representa una descripción de procesos técnicos
                    o de implementación y no constituye una cotización comercial vinculante.
                </div>
            </div>

        </div>
    </div>
</body>

</html>