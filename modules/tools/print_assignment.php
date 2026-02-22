<?php
// modules/tools/print_assignment.php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no proporcionado");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tool_assignments WHERE id = ?");
    $stmt->execute([$id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        die("Asignación no encontrada");
    }

    $stmt = $pdo->prepare("
        SELECT t.name, t.description, i.quantity 
        FROM tool_assignment_items i
        JOIN tools t ON i.tool_id = t.id
        WHERE i.assignment_id = ?
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

    // Get Settings for Logo
    $logo_src = '../../assets/img/logo.png?v=' . time(); // Fallback
    try {
        $stmtSettings = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'system_logo'");
        $db_logo = $stmtSettings->fetchColumn();
        if ($db_logo && file_exists(__DIR__ . '/../../assets/uploads/' . $db_logo)) {
             $logo_src = '../../assets/uploads/' . $db_logo;
        }
    } catch (Exception $e) {
        // Table might not exist or empty, use fallback
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación #<?php echo $assignment['id']; ?></title>
    <style>
        /* Print Settings */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        /* Screen & Base Settings */
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            background-color: #525659;
            display: flex;
            justify-content: center;
        }

        .page-container {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 10mm 20mm;
            box-sizing: border-box;
            margin: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        /* Print Override */
        @media print {
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
            body { 
                background: none; 
                display: block; 
            }
            .page-container {
                width: 100%;
                margin: 0;
                padding: 10mm 15mm 20mm 15mm; 
                box-shadow: none;
                min-height: 280mm;
                display: flex;
                flex-direction: column;
            }
        }

        /* Elements */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .logo { 
            width: 220px; 
            max-height: 80px;
            object-fit: contain;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: right;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 60% 35%;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .info-row {
            margin-bottom: 6px;
            display: flex;
            align-items: baseline;
        }
        
        .info-label {
            font-weight: bold;
            width: 80px;
            flex-shrink: 0;
        }

        .info-value {
            display: flex;
            flex-grow: 1;
            border: none;
        }

        /* .value-content handles the underlining */
        .value-content, .info-value > span[style*="border-bottom"] {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            padding-left: 5px;
            min-height: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        .col-qty { width: 40px; }
        .col-desc { text-align: left; }
        .col-check { width: 40px; font-size: 8px; }

        .observations {
            margin-top: 5px;
            border: 1px solid #000;
            padding: 5px;
            min-height: 40px;
            border-radius: 4px;
        }
        
        .legal-text {
            font-size: 9px;
            margin-top: 25px;
            text-align: justify;
            color: #444;
            line-height: 1.4;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }
        .signature-box {
            text-align: center;
            width: 220px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="page-container">
        <div class="header">
            <img src="<?php echo $logo_src; ?>" alt="Master Technologies" class="logo" onerror="this.style.display='none'">
            <div class="title">ENTREGA DE HERRAMIENTAS PARA PROYECTOS</div>
        </div>

        <div class="info-grid">
            <!-- Left Info -->
            <div>
                <div class="info-row">
                    <span class="info-label">Proyecto:</span>
                    <div class="info-value">
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo htmlspecialchars($assignment['project_name']); ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label">Encargado:</span>
                    <div class="info-value">
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo htmlspecialchars($assignment['assigned_to']); ?></span>
                    </div>
                </div>
                <!-- Technicians -->
                <div class="info-row">
                    <span class="info-label">Técnicos:</span>
                    <div class="info-value">
                        <span style="margin-right: 5px; font-weight: bold;">1.</span>
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo htmlspecialchars($assignment['technician_1']); ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label">&nbsp;</span>
                    <div class="info-value">
                        <span style="margin-right: 5px; font-weight: bold;">2.</span>
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo htmlspecialchars($assignment['technician_2']); ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label">&nbsp;</span>
                    <div class="info-value">
                        <span style="margin-right: 5px; font-weight: bold;">3.</span>
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo htmlspecialchars($assignment['technician_3']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Info -->
            <div>
                <div class="info-row">
                    <span class="info-label" style="width: auto; margin-right: 10px;">Fecha entrega:</span>
                    <div class="info-value">
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo date('d/m/Y', strtotime($assignment['delivery_date'])); ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-label" style="width: auto; margin-right: 10px;">Fecha devolución:</span>
                    <div class="info-value">
                        <span style="border-bottom: 1px solid #000; flex-grow: 1; padding-left: 5px;"><?php echo $assignment['return_date'] ? date('d/m/Y', strtotime($assignment['return_date'])) : ''; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-qty">CANT</th>
                    <th class="col-desc">DESCRIPCIÓN</th>
                    <th class="col-check">ESTADO</th>
                    <th class="col-check">ENTREGUE CONFORME</th>
                    <th class="col-check">RECIBI CONFORME</th>
                    <th class="col-check">DEVOLUCION</th>
                    <th class="col-check">ESTADO</th>
                    <th class="col-check">ENTREGUE CONFORME</th>
                    <th class="col-check">RECIBI CONFORME</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $item['quantity']; ?></td>
                    <td class="col-desc"><?php echo htmlspecialchars($item['name']); ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="font-weight: bold; font-size: 10px; margin-bottom: 2px;">OBSERVACIÓN:</div>
        <div class="observations"><?php echo htmlspecialchars($assignment['observations']); ?></div>

        <div class="legal-text">
            El encargado del proyecto es responsable del cuido de las herramientas, cualquier perdida de alguna de ellas se evaluará cobro de la misma. De igual forma si sobrase material y el encargado no realiza devolución se evaluará sanción para todo el equipo de trabajo.<br>
            El encargado deberá realizar pruebas de las herramientas antes de salir de la empresa, si no lo hace y alguna de las herramientas presenta falla, la responsabilidad recae sobre el equipo de trabajo. NO APLICA, SE ASOCIA A LA CLAUSALA SIGUIENTE<br><br>
            <em>Revisar el estado de las herramientas, si alguna de las herramientas fue probada en taller y funcionaba correctamente y esta se daña en el proyecto el responsable está en la obligación de notificarlo, sino informarse del daño de la herramienta se le hará cobro de la misma al equipo de trabajo.</em>
        </div>

        <div style="font-size: 10px; margin-top: 5px; color: #666;">
            <strong>B:</strong> Bueno, <strong>R:</strong> Regular, <strong>D:</strong> Dañado
        </div>

        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">Entrega</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Recibe</div>
            </div>
        </div>
    </div>

</body>
</html>
