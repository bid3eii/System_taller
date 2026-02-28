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
    <title>Levantamiento #
        <?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?> -
        <?php echo htmlspecialchars($survey['client_name']); ?>
    </title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 1rem;
        }

        .logo {
            font-size: 24pt;
            font-weight: 900;
            color: #6366f1;
            letter-spacing: -1px;
            display: flex;
            align-items: center;
        }

        .logo span {
            color: #333;
            margin-left: 0.5rem;
            font-weight: 300;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            font-size: 10.5pt;
        }

        .meta-info div p {
            margin: 0.2rem 0;
        }

        .title {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 11pt;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .content {
            margin-bottom: 2rem;
            text-align: justify;
        }

        .content p {
            margin-top: 0;
        }

        ul,
        ol {
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
            padding-left: 2rem;
        }

        li {
            margin-bottom: 0.3rem;
        }

        .materials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            margin-bottom: 2rem;
        }

        .materials-table th,
        .materials-table td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
            font-size: 10.5pt;
        }

        .materials-table th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
        }

        .text-center {
            text-align: center;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .container {
                padding: 0;
            }
        }

        /* TinyMCE Fixes for Print */
        .rich-text-content * {
            font-family: 'Arial', sans-serif !important;
            font-size: 11pt !important;
            color: #333 !important;
            background: transparent !important;
        }
    </style>
</head>

<body>

    <div class="no-print"
        style="background:#f1f5f9; padding: 1rem; text-align: center; border-bottom: 1px solid #cbd5e1; margin-bottom: 2rem;">
        <button onclick="window.print()"
            style="background:#6366f1; color:white; border:none; padding: 0.5rem 1.5rem; border-radius: 4px; font-weight: bold; cursor:pointer;">
            Imprimir Documento
        </button>
        <button onclick="window.close()"
            style="background:transparent; color:#475569; border:1px solid #94a3b8; padding: 0.5rem 1.5rem; border-radius: 4px; font-weight: bold; cursor:pointer; margin-left: 1rem;">
            Cerrar
        </button>
    </div>

    <div class="container">

        <div class="header">
            <div class="logo">
                M<span>TECHNOLOGIES</span>
            </div>
        </div>

        <div class="meta-info">
            <div>
                <p><strong>Cliente:</strong>
                    <?php echo htmlspecialchars($survey['client_name']); ?> (Consulta:
                    <?php echo htmlspecialchars($survey['title']); ?>)
                </p>
            </div>
        </div>

        <div class="meta-info">
            <div>
                <p><strong>Elaborado por:</strong>
                    <?php echo htmlspecialchars($survey['tech_name']); ?>
                </p>
            </div>
            <div>
                <p><strong>Fecha:</strong>
                    <?php echo $date_format; ?>
                </p>
            </div>
        </div>

        <div class="title">DESCRIPCIÓN GENERAL DEL PROYECTO</div>
        <div class="content">
            <?php if ($survey['general_description']): ?>
                <p>
                    <?php echo nl2br(htmlspecialchars($survey['general_description'])); ?>
                </p>
            <?php else: ?>
                <p><em>No especificada.</em></p>
            <?php endif; ?>
        </div>

        <div class="title">ALCANCES DEL PROYECTO: IMPLEMENTACIÓN O SERVICIO</div>
        <div class="content rich-text-content">
            <?php if ($survey['scope_activities']): ?>
                <?php echo $survey['scope_activities']; ?>
            <?php else: ?>
                <p><em>No se han definido actividades específicas.</em></p>
            <?php endif; ?>
        </div>

        <div class="title">ESTIMACIÓN DE TIEMPO Y RECURSOS</div>
        <div class="content">
            <ul>
                <li><strong>Personal Requerido:</strong>
                    <?php echo htmlspecialchars($survey['personnel_required'] ?: 'No especificado'); ?>
                </li>
                <li><strong>Tiempo Estimado:</strong>
                    <?php echo htmlspecialchars($survey['estimated_time'] ?: 'No especificado'); ?>
                </li>
            </ul>
        </div>

        <?php if (count($materials) > 0): ?>
            <div class="title" style="page-break-before: auto;">REQUERIMIENTOS Y MATERIALES</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Descripción del Ítem</th>
                        <th style="width: 15%;" class="text-center">Cantidad</th>
                        <th style="width: 35%;">Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $m): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($m['item_description']); ?>
                            </td>
                            <td class="text-center">
                                <?php echo floatval($m['quantity']) . ' ' . htmlspecialchars($m['unit']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($m['notes'] ?: '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div
            style="margin-top: 4rem; text-align: center; border-top: 1px solid #ddd; padding-top: 2rem; color: #64748b; font-size: 9pt;">
            <p>Este documento es una descripción técnica y no constituye una cotización formal a menos que se indique lo
                contrario.</p>
        </div>

    </div>
</body>

</html>