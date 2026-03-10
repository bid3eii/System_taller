<?php
// modules/services/print_diagnosis.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no especificado.");
}

// Fetch Settings
$settings = [];
$stmtAll = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $stmtAll->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$system_logo = $settings['system_logo'] ?? '';
$company_name = $settings['company_name'] ?? 'SYSTEM TALLER';
$company_email = $settings['company_email'] ?? 'contacto@taller.com';
$company_phone = $settings['company_phone'] ?? '(555) 123-4567';

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*, so.display_id,
        c.name as client_name, c.phone, c.email,
        e.brand, e.model, e.serial_number, e.type as equipment_type,
        u_auth.username as authorized_by_name
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u_auth ON so.authorized_by_user_id = u_auth.id
    WHERE so.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Orden no encontrada.");
}

// Fetch User who elaborated the diagnosis (From History) and the date
$stmtHistory = $pdo->prepare("
    SELECT u.username, r.name as role_name, h.created_at
    FROM service_order_history h
    JOIN users u ON h.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE h.service_order_id = ? AND (h.action = 'diagnosing' OR h.notes LIKE '%[Diagnóstico]%')
    ORDER BY h.created_at DESC
    LIMIT 1
");
$stmtHistory->execute([$id]);
$diagnosis_author = $stmtHistory->fetch();

$elaborated_by = $diagnosis_author['username'] ?? 'Desconocido'; 
$elaborated_role = $diagnosis_author['role_name'] ?? 'Técnico';
$diagnosis_date = $diagnosis_author['created_at'] ?? $order['entry_date']; 

// Fetch Diagnosis Images
$stmtImages = $pdo->prepare("SELECT image_path FROM diagnosis_images WHERE service_order_id = ? ORDER BY id ASC");
$stmtImages->execute([$id]);
$diagnosis_imgs = $stmtImages->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico <?php echo get_order_number($order); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --text-color: #000000;
            --bg-page: #f0f0f0;
        }
        
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-page);
            font-family: 'Roboto', sans-serif;
            color: var(--text-color);
            font-size: 14px;
        }
        
        /* PAPER PREVIEW ON SCREEN */
        .page-container {
            position: relative;
            width: 216mm;
            min-height: 279mm;
            margin: 20px auto;
            background: white;
            padding: 2cm 1.5cm;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        /* ACTIONS (BUTTONS) */
        .actions {
            position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 999;
        }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: white; color: #333; border: 1px solid #ccc; }

        /* TABLE STRUCTURE FOR PRINT REPEATING HEADERS */
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: auto; }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .header-logo img {
            max-height: 60px;
        }
        
        .header-info {
            text-align: right;
            font-size: 12px;
        }
        .header-info h2 { margin: 0; font-size: 14px; color: #666; font-weight: bold; }
        .header-info p { margin: 2px 0; }

        /* DOC TITLE */
        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .client-subtitle {
            font-size: 16px;
            margin-top: 5px;
            color: #444;
        }

        .date-line {
            text-align: right;
            margin-bottom: 10px;
            font-size: 13px;
        }

        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 Columns */
            gap: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 500;
        }
        .full-width {
            grid-column: 1 / -1;
        }

        .diagnosis-text {
            white-space: pre-wrap;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 10pt;
            text-align: center; /* Centered Text */
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 5px; /* Reduced margin */
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            color: #333;
            text-align: center; /* Centered Title */
            page-break-after: avoid;
            break-after: avoid;
        }

        .text-content {
            font-size: 13px;
            line-height: 1.5;
            text-align: justify; /* Justified Content */
            text-justify: inter-word;
            margin-bottom: 15px;
            white-space: pre-line; /* Keeps line breaks but allows better justification than pre-wrap for this purpose */
            orphans: 3;
            widows: 3;
        }

        /* IMAGES GRID */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        .image-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            background: #fdfdfd;
            text-align: center;
            padding: 5px;
        }
        .image-card img {
            max-width: 100%;
            max-height: 250px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        /* SIGNATURE SECTION (FLOWS NATURALLY) */
        .signature-section {
            margin-top: auto; /* Push to bottom */
            padding-top: 2cm;
            padding-bottom: 1cm;
            page-break-inside: avoid;
        }

        .elaborated-by-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .keep-together {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .diagnosis-section {
            margin-bottom: 20px;
        }

        /* PRINT STYLES */
        @media print {
            html, body {
                background: white !important;
                height: auto !important; 
                margin: 0 !important;
                padding: 0 !important;
            }
            @page {
                size: letter;
                margin: 5mm; 
            }
            .actions {
                display: none !important;
            }
            .page-container {
                margin: 0 !important;
                box-shadow: none !important;
                width: 100% !important;
                min-height: 260mm !important; 
                padding: 1cm 1.5cm 2cm 1.5cm !important; 
                border: none !important;
                position: relative !important;
                display: flex;
                flex-direction: column;
            }
            
            /* Ensure background colors print */
            .info-grid {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="actions">
        <button onclick="goBack()" class="btn btn-secondary">Volver</button>
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
    </div>

    <script>
        function goBack() {
             if (document.referrer.includes('/reports/')) {
                window.location.href = '../reports/index.php';
            } else {
                window.location.href = 'view.php?id=<?php echo $id; ?>';
            }
        }
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('autoprint')) {
                window.print();
            }
        });
    </script>

    <div class="page-container">
        <table>
            <thead>
                <tr>
                    <td>
                        <div class="header-content">
                            <div class="header-logo">
                                <?php if($system_logo): ?>
                                    <img src="../../assets/uploads/<?php echo $system_logo; ?>" alt="Logo">
                                <?php else: ?>
                                    <h2><?php echo htmlspecialchars($company_name); ?></h2>
                                <?php endif; ?>
                            </div>
                            <div class="header-info">
                                <h2>SOPORTE TÉCNICO</h2>
                                <p>Telf: <?php echo htmlspecialchars($company_phone); ?></p>
                                <p><?php echo htmlspecialchars($company_email); ?></p>
                            </div>
                        </div>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="date-line">
                            <?php
                            $months = [
                                'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                                'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                                'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                                'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
                            ];
                            ?>
                            Fecha: <?php echo date('d', strtotime($diagnosis_date)) . ' de ' . $months[date('F', strtotime($diagnosis_date))] . ' de ' . date('Y', strtotime($diagnosis_date)); ?>
                        </div>

                        <div class="doc-title">
                            REPORTE DE DIAGNÓSTICO
                            <?php 
                                $final_client_name = !empty($order['owner_name']) ? $order['owner_name'] : $order['client_name'];
                            ?>
                            <div class="client-subtitle"><?php echo htmlspecialchars($final_client_name); ?></div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Tipo:</span>
                                <span class="info-value"><?php echo ($order['service_type'] == 'warranty_service') ? 'Garantía' : 'Servicio'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Caso:</span>
                                <span class="info-value" style="color: #2563eb;"><?php echo get_order_number($order); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Diagnóstico:</span>
                                <span class="info-value"><?php echo $order['diagnosis_number']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Factura:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['invoice_number'] ?: '---'); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Equipo:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['brand']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Serie:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['serial_number']); ?></span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Falla Reportada:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['problem_reported']); ?></span>
                            </div>
                        </div>

                        <!-- Diagnosis Details Moved Up -->
                        <div class="diagnosis-section">
                            <div class="section-title">Procedimiento:</div>
                            <div class="text-content">
                                <?php echo $order['diagnosis_procedure'] ? htmlspecialchars($order['diagnosis_procedure']) : 'No registrado.'; ?>
                            </div>
                        </div>

                        <div class="diagnosis-section">
                            <div class="section-title">Conclusión/Solución:</div>
                            <div class="text-content">
                                <?php echo $order['diagnosis_conclusion'] ? htmlspecialchars($order['diagnosis_conclusion']) : 'No registrado.'; ?>
                            </div>
                        </div>

                        <?php if (count($diagnosis_imgs) > 0): ?>
                        <div class="diagnosis-section" style="page-break-before: always;">
                            <div class="section-title" style="text-align: center; font-size: 1.4rem; padding-bottom: 10px; border-bottom: 2px solid #eee; margin-bottom: 20px;">
                                Imágenes de Evidencia
                            </div>
                            <div class="images-grid">
                                <?php foreach ($diagnosis_imgs as $img): ?>
                                    <div class="image-card">
                                        <img src="../../<?php echo htmlspecialchars($img['image_path']); ?>" alt="Evidencia">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

        </table>
        
        <!-- Signature Section (Flows naturally, not absolute) -->
        <div class="signature-section">
            <div class="elaborated-by-label">Elaborado por:</div>
            <div style="font-size: 14px; font-weight: bold; margin-top: 5px;"><?php echo htmlspecialchars($elaborated_by); ?></div>
            <div style="font-size: 13px; color: #555;"><?php echo htmlspecialchars($elaborated_role); ?> - <?php echo htmlspecialchars($company_name); ?></div>
        </div>

    </div>

</body>
</html>
