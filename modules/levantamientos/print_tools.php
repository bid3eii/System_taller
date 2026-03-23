<?php
// modules/levantamientos/print_tools.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = intval($_GET['id']);

// Fetch Survey
$sql = "
    SELECT ps.*, u.username as tech_name
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

// Fetch Tools
$stmtT = $pdo->prepare("
    SELECT pt.*, t.name as inventory_name 
    FROM project_survey_tools pt 
    LEFT JOIN tools t ON pt.tool_id = t.id 
    WHERE pt.survey_id = ? 
    ORDER BY pt.id ASC
");
$stmtT->execute([$id]);
$tools = $stmtT->fetchAll();

$date_format = date('d/m/Y', strtotime($survey['created_at']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Herramientas - <?php echo htmlspecialchars($survey['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/variables.css">
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
            background: #fff;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            text-transform: uppercase;
        }
        .internal-badge {
            background: #333;
            color: #fff;
            padding: 0.2rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 4px;
            font-weight: bold;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .meta-item b {
            color: #666;
            margin-right: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th {
            background: #f4f4f4;
            text-align: left;
            padding: 0.75rem;
            border: 1px solid #ddd;
            font-size: 0.9rem;
        }
        td {
            padding: 0.75rem;
            border: 1px solid #ddd;
            font-size: 0.9rem;
        }
        .footer {
            margin-top: 4rem;
            display: flex;
            justify-content: space-between;
            padding-top: 2rem;
        }
        .signature {
            width: 250px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 0.5rem;
            font-size: 0.85rem;
        }
        @media print {
            .no-print { display: none; }
            body { background: #fff; color: #000; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print" style="text-align: right; margin-bottom: 1rem;">
            <button onclick="window.print()" style="padding: 0.5rem 1rem; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 4px;">Imprimir Lista</button>
            <button onclick="window.close()" style="padding: 0.5rem 1rem; cursor: pointer; background: #eee; border: 1px solid #ccc; border-radius: 4px;">Cerrar</button>
        </div>

        <div class="header">
            <div>
                <h1>LISTA DE HERRAMIENTAS</h1>
                <p style="margin: 0; font-size: 0.9rem; color: #666;">Control Logístico Interno</p>
            </div>
            <div class="internal-badge">BORRADOR / USO INTERNO</div>
        </div>

        <div class="meta-grid">
            <div class="meta-item"><b>Proyecto:</b> <?php echo htmlspecialchars($survey['title']); ?></div>
            <div class="meta-item"><b>Fecha:</b> <?php echo $date_format; ?></div>
            <div class="meta-item"><b>Cliente:</b> <?php echo htmlspecialchars($survey['client_name']); ?></div>
            <div class="meta-item"><b>Responsable:</b> <?php echo htmlspecialchars($survey['tech_name']); ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Herramienta / Equipo</th>
                    <th style="width: 10%; text-align: center;">Cant.</th>
                    <th style="width: 40%;">Notas / Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tools) > 0): ?>
                    <?php foreach ($tools as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['inventory_name'] ?: ($t['tool_name'] ?: 'N/E')); ?></td>
                            <td style="text-align: center;"><?php echo intval($t['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($t['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem; color: #999;">No hay herramientas registradas para este levantamiento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 2rem;">
            <h3>Instrucciones de Logística:</h3>
            <ul style="font-size: 0.9rem; color: #555;">
                <li>Verificar el estado de cada herramienta antes de la salida.</li>
                <li>Reportar cualquier falla o equipo faltante de inmediato.</li>
                <li>Esta lista es preliminar sujeto a cambios del responsable técnico.</li>
            </ul>
        </div>

        <div class="footer">
            <div class="signature">
                <b>Entregado por (Almacén)</b>
            </div>
            <div class="signature">
                <b>Recibido por (Responsable)</b>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Optional: Auto-trigger print
            // window.print();
        };
    </script>
</body>
</html>
