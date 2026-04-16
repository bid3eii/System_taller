<?php
// modules/warranties/print_certificate.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_GET['id'])) {
    die("Error: Se requiere el ID de la orden.");
}

$ids_str = clean($_GET['id']);
$ids = array_filter(array_map('intval', explode(',', $ids_str)));

if(empty($ids)) {
    die("Error: IDs inválidos.");
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Paso 1: Encontrar las facturas y clientes asociados a los IDs entregados
$stmtMeta = $pdo->prepare("
    SELECT w.sales_invoice_number, so.client_id 
    FROM warranties w
    JOIN service_orders so ON w.service_order_id = so.id
    WHERE so.id IN ($placeholders)
");
$stmtMeta->execute($ids);
$meta_rows = $stmtMeta->fetchAll();

if (empty($meta_rows)) {
    die("Registros no encontrados.");
}

$invoices = array_filter(array_unique(array_column($meta_rows, 'sales_invoice_number')));
$clients = array_filter(array_unique(array_column($meta_rows, 'client_id')));

if(empty($invoices) || empty($clients)) {
    die("Error: No se encontró número de factura válido para estos registros.");
}

$invoice_placeholders = implode(',', array_fill(0, count($invoices), '?'));
$client_placeholders = implode(',', array_fill(0, count($clients), '?'));
$params = array_merge($invoices, $clients);

// Paso 2: Traer TODOS los equipos que correspondan a esa misma factura y cliente
$stmt = $pdo->prepare("
    SELECT 
        so.id as order_id, 
        c.name as client_name,
        e.brand, e.model, e.serial_number,
        w.sales_invoice_number, w.duration_months, w.product_code
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    JOIN warranties w ON w.service_order_id = so.id
    WHERE w.sales_invoice_number IN ($invoice_placeholders) 
      AND so.client_id IN ($client_placeholders)
    ORDER BY w.sales_invoice_number ASC, e.brand ASC
");
$stmt->execute($params);
$data_items = $stmt->fetchAll();

if (empty($data_items)) {
    die("Error al recopilar los registros consolidados.");
}

$first_item = $data_items[0];

// Fetch Settings
$settings = [];
$stmtAll = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $stmtAll->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$system_logo = $settings['system_logo'] ?? '';

// Format Date correctly in Spanish
$months = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
$current_date = "Managua, " . date('d') . " de " . $months[(int)date('m') - 1] . " del " . date('Y');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Garantía - <?php echo htmlspecialchars($first_item['sales_invoice_number']); ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif; /* Typical for formal certificates */
            font-size: 14px;
            line-height: 1.5;
            color: #000;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }
        .logo-placeholder {
            /* Placeholder for the logo */
            display: flex;
            align-items: center;
        }
        .logo-placeholder h1 {
            color: #4b5563; /* Gray that looks similar to the image's logo */
            margin: 0;
            font-size: 28px;
            font-family: Arial, sans-serif;
            font-style: italic;
        }
        .logo-placeholder span {
            color: #a855f7; /* M purple */
        }
        .address {
            text-align: right;
            color: #6b7280;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .date {
            text-align: right;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            text-decoration: underline;
            margin-bottom: 40px;
        }
        .content p {
            margin: 0 0 10px 0;
            font-size: 15px;
        }
        .bold {
            font-weight: bold;
        }
        .product-details {
            margin: 15px 0;
            padding-left: 20px;
        }
        .terms {
            margin-top: 50px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
        }
        .terms ul {
            margin: 10px 0;
            padding-left: 40px;
        }
        .terms li {
            margin-bottom: 5px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 100px;
        }
        .signature-line {
            width: 250px;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        @media print {
            @page {
                size: letter;
                margin: 1.5cm;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .terms, .signatures, .product-details {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
        .header-logo {
            margin-bottom: 20px;
        }
        .header-logo img {
            max-width: 250px;
            max-height: 80px;
            object-fit: contain;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header-logo">
        <?php if($system_logo): ?>
            <img src="../../assets/uploads/<?php echo htmlspecialchars($system_logo); ?>" alt="Logo del Sistema">
        <?php endif; ?>
    </div>

    <div class="date">
        <?php echo $current_date; ?>
    </div>

    <div class="title">
        CERTIFICADO DE GARANTIA
    </div>

    <div class="content">
        <p>
            <span class="bold">Sres &nbsp; <?php echo htmlspecialchars($first_item['client_name']); ?></span><br>
            Se extiende la presente por los productos detallados a continuación, con<br>
            Factura# <span class="bold"><?php echo htmlspecialchars($first_item['sales_invoice_number']); ?></span>
        </p>

        <?php foreach($data_items as $index => $item): ?>
        <div class="product-details" style="<?php echo $index > 0 ? 'margin-top: 15px;' : ''; ?> padding-bottom: 15px; border-bottom: <?php echo $index < count($data_items) - 1 ? '1px dashed #ccc;' : 'none'; ?>">
            <p class="bold" style="margin-bottom: 3px;">
                <?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?> <?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?> 
                <?php if(!empty($item['product_code'])) echo ' - Cód: ' . htmlspecialchars($item['product_code']); ?>
            </p>
            <p style="margin: 3px 0;">GARANTIA <?php echo $item['duration_months']; ?> MESES (por desperfecto de fábrica)</p>
            <p style="margin: 3px 0;">SERIES# <?php echo htmlspecialchars($item['serial_number']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="page-break-inside: avoid; break-inside: avoid; display: table; width: 100%;">
        <div class="terms">
            <p>Si los productos y/o equipos presentan fallas durante el periodo de garantía, el proceso es el siguiente:</p>
            <ul>
                <li>El cliente deberá llevar el equipo y copia de factura a oficinas de mastertec, en donde se realizará un documento formal de ingreso del producto.</li>
                <li>Mastertec validará la garantía, abriendo un caso con el fabricante y el periodo de validación varía entre 24 a 48 horas.</li>
                <li>El fabricante informara a mastertec si es necesario reemplazar el equipo o solo se realizará el cambio de la pieza que presenta el problema.</li>
            </ul>
            <p>Todo lo mencionado anteriormente no tendrá costos adicionales al cliente durante el periodo de garantía.</p>
            <p>Dicha garantía no será válida si el equipo presenta golpes, daños por variaciones de voltaje, daños accidentales o adrede, variaciones en el clima, incendios, inundaciones, etc.</p>
            <p>Sin más a que hacer referencia, reciba saludos cordiales.</p>
        </div>

        <div class="signatures">
            <div class="signature-line">Entregué Conforme</div>
            <div class="signature-line">Recibí Conforme</div>
        </div>
    </div>

</body>
</html>
