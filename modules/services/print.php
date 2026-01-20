<?php
// modules/services/print.php
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

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.name as client_name, c.phone, c.email, c.tax_id,
        e.brand, e.model, e.serial_number, e.type as equipment_type,
        u.username as created_by
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.user_id = u.id
    WHERE so.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Orden no encontrada.");
}

// Status Mapping
$statusLabels = [
    'received' => 'Recibido',
    'diagnosing' => 'En Revisión',
    'pending_approval' => 'En Espera',
    'in_repair' => 'En Proceso',
    'ready' => 'Listo',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        :root {
            --primary: #0f172a;
            --secondary: #64748b;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            min-height: 250mm; /* A4 height approx */
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .company-info p {
            margin: 5px 0 0;
            color: var(--secondary);
            font-size: 12px;
        }
        .order-meta {
            text-align: right;
        }
        .order-number {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        .order-date {
            margin-top: 5px;
            color: var(--secondary);
        }

        /* Sections */
        .section {
            margin-bottom: 30px;
        }
        .section-header {
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            color: var(--secondary);
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .info-group {
            margin-bottom: 8px;
            display: flex;
        }
        .info-label {
            font-weight: 600;
            width: 100px;
            flex-shrink: 0;
            color: #475569;
        }
        .info-value {
            font-weight: 400;
        }

        /* Service Box */
        .service-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 15px;
        }
        .service-row {
            margin-bottom: 10px;
        }
        .service-label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: #334155;
        }
        .service-content {
            white-space: pre-wrap;
            color: #000;
        }

        /* Signatures */
        .signatures-area {
            margin-top: auto; /* Push to bottom */
            padding-bottom: 30px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .signature-block {
            flex: 1;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-bottom: 10px;
            height: 1px;
        }
        .signature-name {
            font-weight: 600;
            font-size: 14px;
        }
        .signature-role {
            font-size: 11px;
            color: var(--secondary);
        }

        /* Footer */
        .footer {
            margin-top: 0;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid var(--border);
            padding-top: 20px;
        }

        /* Actions */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: #0f172a; color: white; }
        .btn-primary:hover { background: #1e293b; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }

        @media print {
            @page {
                margin: 0;
                size: auto;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            .page-container {
                box-shadow: none;
                margin: 0;
                padding: 40px 40px 3cm 40px;
                width: 100%;
                max-width: none;
                min-height: 100vh;
            }
            .action-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
            <span>← Volver</span>
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <span>Imprimir Orden</span>
        </button>
    </div>

    <?php
// Fetch Settings
$settings = [];
$stmtAll = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $stmtAll->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$system_logo = $settings['system_logo'] ?? '';
$company_name = $settings['company_name'] ?? 'SYSTEM TALLER';
$company_address = $settings['company_address'] ?? 'Av. Principal 123, Ciudad';
$company_phone = $settings['company_phone'] ?? '(555) 123-4567';
$print_footer_text = $settings['print_footer_text'] ?? 'La empresa no se hace responsable por equipos no retirados después de 30 días de la fecha de notificación.';
?>

    <div class="page-container">
        <!-- Header -->
        <header class="header">
            <div class="brand-section">
                <?php if($system_logo && file_exists("../../assets/uploads/" . $system_logo)): ?>
                    <img src="../../assets/uploads/<?php echo $system_logo; ?>" style="max-width: 200px; max-height: 80px; margin-bottom: 10px; object-fit: contain;" alt="Logo">
                <?php endif; ?>
                <div>
                   <h1><?php echo htmlspecialchars($company_name); ?></h1>
                   <p style="font-size: 0.8rem; color: #64748b;">Orden de Servicio</p>
                   <p style="font-size: 0.8rem; margin: 0; color: #64748b;"><?php echo htmlspecialchars($company_address); ?></p>
                   <p style="font-size: 0.8rem; margin: 0; color: #64748b;"><?php echo htmlspecialchars($company_phone); ?></p>
                </div>
            </div>
            <div class="doc-meta">
                <h2>ORDEN #<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></h2>
                <p><?php echo date('d/m/Y'); ?></p>
            </div>
        </header>

        <!-- Client & Equipment Info -->
        <div class="section">
            <div class="grid-2">
                <!-- Client -->
                <div>
                    <div class="section-header">INFORMACIÓN DEL CLIENTE</div>
                    <div class="info-group">
                        <span class="info-label">Cliente:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['client_name']); ?></span>
                    </div>
                    <?php if($order['tax_id']): ?>
                    <div class="info-group">
                        <span class="info-label">ID/RUC:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['tax_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-group">
                        <span class="info-label">Teléfono:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                </div>

                <!-- Equipment -->
                <div>
                    <div class="section-header">DATOS DEL EQUIPO</div>
                    <div class="info-group">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['equipment_type']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Marca:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['brand']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Modelo:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['model']); ?></span>
                    </div>
                    <div class="info-group">
                        <span class="info-label">Serie (SN):</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['serial_number']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Details -->
        <div class="section">
            <div class="section-header">DETALLES DEL SERVICIO</div>
            <div class="service-box">
                <div class="grid-2">
                    <div class="service-row">
                        <span class="service-label">Problema Reportado:</span>
                        <div class="service-content"><?php echo htmlspecialchars($order['problem_reported']); ?></div>
                    </div>
                    <div class="service-row">
                        <span class="service-label">Accesorios Recibidos:</span>
                         <div class="service-content"><?php echo htmlspecialchars($order['accessories_received'] ?: 'Ninguno'); ?></div>
                    </div>
                </div>

                <?php if($order['entry_notes']): ?>
                <div class="service-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border);">
                    <span class="service-label">Notas de Ingreso:</span>
                    <div class="service-content"><?php echo nl2br(htmlspecialchars($order['entry_notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures-area">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name">
                    <?php echo htmlspecialchars($order['created_by'] ?? 'Taller'); ?>
                </div>
                <div class="signature-role">RECIBIDO POR (TALLER)</div>
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name">
                    <?php echo htmlspecialchars($order['client_name']); ?>
                </div>
                <div class="signature-role">Firma del Cliente</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Condiciones de Servicio:</strong> <?php echo htmlspecialchars($print_footer_text); ?></p>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
