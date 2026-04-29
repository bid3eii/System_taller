<?php
// modules/equipment/print_entry.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
$num = $_GET['num'] ?? null;
$ids_str = $_GET['ids'] ?? null;

if (!$id && $num) {
    // Strip prefix (G, S, #) and leading zeros for robust lookup
    $clean_num = ltrim(strtoupper(trim($num)), '#');
    $service_type_filter = null;
    
    if (strpos($clean_num, 'S') === 0) {
        $service_type_filter = 'service';
        $clean_num = substr($clean_num, 1);
    } elseif (strpos($clean_num, 'G') === 0) {
        $service_type_filter = 'warranty';
        $clean_num = substr($clean_num, 1);
    }
    $clean_num = ltrim($clean_num, '0') ?: '0';

    if ($service_type_filter) {
        $stmtId = $pdo->prepare("SELECT id FROM service_orders WHERE display_id = ? AND service_type = ? LIMIT 1");
        $stmtId->execute([$clean_num, $service_type_filter]);
        $id = $stmtId->fetchColumn();
        
        if (!$id && is_numeric($clean_num)) {
            $stmtIdFallback = $pdo->prepare("SELECT id FROM service_orders WHERE id = ? AND service_type = ? AND (display_id IS NULL OR display_id = '' OR display_id = '0') LIMIT 1");
            $stmtIdFallback->execute([$clean_num, $service_type_filter]);
            $id = $stmtIdFallback->fetchColumn();
        }
    } else {
        $stmtId = $pdo->prepare("SELECT id FROM service_orders WHERE display_id = ? LIMIT 1");
        $stmtId->execute([$clean_num]);
        $id = $stmtId->fetchColumn();
        
        if (!$id && is_numeric($clean_num)) {
            $stmtIdFallback = $pdo->prepare("SELECT id FROM service_orders WHERE id = ? AND (display_id IS NULL OR display_id = '' OR display_id = '0') LIMIT 1");
            $stmtIdFallback->execute([$clean_num]);
            $id = $stmtIdFallback->fetchColumn();
        }
    }
}

if (!$id && !$ids_str) {
    die("ID o Número de Caso no especificado o no encontrado.");
}

$order_ids = $ids_str ? explode(',', $ids_str) : [$id];

// Fetch Settings
$settings = [];
$stmtAll = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $stmtAll->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$system_logo = $settings['system_logo'] ?? '';
$company_name = $settings['company_name'] ?? 'SYSTEM TALLER';
$company_email = $settings['company_email'] ?? 'contacto@taller.com';
$company_address = $settings['company_address'] ?? 'Av. Principal 123, Ciudad';
$company_phone = $settings['company_phone'] ?? '(555) 123-4567';
$print_entry_text = $settings['print_entry_text'] ?? "1. No nos responsabilizamos por perdida de información...\n...";

// Fetch multiple orders
$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$stmt = $pdo->prepare("
    SELECT 
        so.*, so.display_id,
        c.name as contact_name, c.phone, c.email, c.tax_id, c.address,
        e.brand, e.model, e.submodel, e.serial_number, e.type as equipment_type,
        co.name as registered_owner_name,
        w.sales_invoice_number
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients co ON e.client_id = co.id
    LEFT JOIN warranties w ON so.id = w.service_order_id
    WHERE so.id IN ($placeholders)
    ORDER BY so.id ASC
");
$stmt->execute($order_ids);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    die("Ordenes no encontradas.");
}

$first_order = $orders[0];

// Get Creator for the first order (assuming same creator for the batch)
$stmtCreator = $pdo->prepare("SELECT u.full_name, u.username FROM service_order_history h JOIN users u ON h.user_id = u.id WHERE h.service_order_id = ? AND h.action = 'received' ORDER BY h.created_at ASC LIMIT 1");
$stmtCreator->execute([$first_order['id']]);
$creatorData = $stmtCreator->fetch();
$received_by = ($creatorData['full_name'] ?? $creatorData['username']) ?: 'Taller Mastertec';

// Ensure entry_doc_number exists and is common
$doc_number = $first_order['entry_doc_number'];
if (empty($doc_number)) {
    $doc_number = get_next_sequence($pdo, 'entry_doc');
    foreach ($orders as $o) {
        $pdo->prepare("UPDATE service_orders SET entry_doc_number = ? WHERE id = ?")->execute([$doc_number, $o['id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción <?php echo str_pad($doc_number, 5, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --border-color: #000; --header-bg: #f8fafc; }
        * { box-sizing: border-box; }
        body { font-family: 'Roboto', sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; font-size: 12px; }
        .paper { background: white; width: 216mm; min-height: 279mm; margin: 20px auto; padding: 15mm; display: block; box-shadow: 0 4px 10px rgba(0,0,0,0.1); position: relative; }
        .actions { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 999; }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; text-decoration: none; }
        .btn-primary { background: #2563eb; color: white; }
        
        .header-grid { display: grid; grid-template-columns: 25% 55% 20%; border: 1.5px solid var(--border-color); margin-bottom: 8px; }
        .header-col { padding: 4px; display: flex; flex-direction: column; justify-content: center; }
        .header-logo { text-align: center; border-right: 1.5px solid var(--border-color); }
        .logo-img { max-width: 140px; max-height: 50px; object-fit: contain; margin: 0 auto; }
        .header-center { text-align: center; border-right: 1.5px solid var(--border-color); }
        .header-center h2 { margin: 0; font-size: 15px; font-weight: 700; }
        .header-center h3 { margin: 0; font-size: 13px; font-weight: 700; }
        .header-right { text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .doc-box { border: 1.5px solid var(--border-color); padding: 5px 15px; font-size: 16px; font-weight: bold; margin-bottom: 3px; }

        .section-header { background: #fff; border: 1.5px solid var(--border-color); border-bottom: none; text-align: center; font-weight: bold; padding: 4px; font-size: 11px; margin-top: 8px; }
        .section-box { border: 1.5px solid var(--border-color); padding: 8px; margin-bottom: 8px; line-height: 1.4; }
        .client-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .info-row { margin-bottom: 3px; display: flex; }
        .info-label { font-weight: bold; width: 80px; text-align: right; margin-right: 10px; }
        .info-label { font-weight: bold; width: 80px; text-align: right; margin-right: 10px; }
        .info-val { flex: 1; }

        .equip_table { width: 100%; border-collapse: collapse; border: 1.5px solid var(--border-color); margin-top: 8px; margin-bottom: 8px; page-break-inside: auto; }
        .equip_table tr { page-break-inside: avoid; page-break-after: auto; }
        .equip_table th, .equip_table td { border: 1.5px solid var(--border-color); padding: 4px; text-align: center; font-size: 11px; }
        .equip_table th { background: #fff; font-weight: bold; }
        
        .legal-footer { font-size: 10px; text-align: justify; border: 1px solid var(--border-color); padding: 8px; margin-bottom: 15px; line-height: 1.3; page-break-inside: avoid; }
        .signatures-area { display: flex; justify-content: space-around; margin-top: 30px; page-break-inside: avoid; }
        .bottom-section { width: 100%; border-top: 0; padding-top: 5px; page-break-inside: avoid; }
        .sig-box { width: 45%; text-align: center; }
        .sig-line { border-bottom: 1.5px solid black; height: 40px; margin-bottom: 5px; }

        @media print { 
            @page { size: letter; margin: 0; }
            html, body { background: white !important; height: auto; overflow: visible; margin: 0 !important; padding: 0 !important; }
            .actions { display: none; } 
            .paper { 
                box-shadow: none; 
                margin: 0 !important; 
                width: 100% !important; 
                padding: 10mm 12mm; 
                height: auto; 
                min-height: 0;
                display: block;
                overflow: visible;
            }
            .section-box { margin-bottom: 5px; }
            .equip_table { margin-top: 5px; margin-bottom: 5px; }
            .signatures-area { margin-top: 5px; }
            .bottom-section { margin-top: auto; margin-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">Imprimir Recepción</button>
        <a href="entry.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block; padding: 0.5rem 1rem; border-radius: 4px; background: #64748b; color: white; margin-left: 10px;">Volver</a>
    </div>
    <div class="paper">
        <div class="header-grid">
            <div class="header-col header-logo">
                <?php if($system_logo): ?><img src="../../assets/uploads/<?php echo $system_logo; ?>" class="logo-img"><?php endif; ?>
                <div style="font-weight: bold; font-size: 11px; margin-top: 4px;"><?php echo htmlspecialchars($company_name); ?></div>
            </div>
            <div class="header-col header-center">
                <?php if ($first_order['client_id'] == 11): ?>
                    <h2>INGRESO DE INVENTARIO</h2>
                    <h3>CONTROL DE ALMACÉN</h3>
                <?php else: ?>
                    <h2>RECEPCIÓN DE EQUIPOS</h2>
                    <h3>SOPORTE TÉCNICO</h3>
                <?php endif; ?>
                <p style="margin:4px 0; font-size: 10px;">Tel: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?></p>
            </div>
            <div class="header-col header-right">
                <div class="doc-box"><?php echo str_pad($doc_number, 5, '0', STR_PAD_LEFT); ?></div>
                <div style="font-size: 10px; font-weight: bold;">DOC. ENTRADA</div>
            </div>
        </div>

        <div class="section-header">DATOS DEL CLIENTE</div>
        <div class="section-box">
            <div class="client-grid">
                <div>
                    <div class="info-row"><div class="info-label">Fecha:</div><div class="info-val"><?php echo date('d/m/Y h:i:s A', strtotime($first_order['entry_date'])); ?></div></div>
                    <?php if(count($orders) === 1): ?>
                        <div class="info-row"><div class="info-label">Caso #:</div><div class="info-val" style="color: #2563eb; font-weight: bold;"><?php echo get_order_number($orders[0]); ?></div></div>
                    <?php endif; ?>
                    <?php 
                        $invoice_display = !empty($first_order['invoice_number']) ? $first_order['invoice_number'] : ($first_order['sales_invoice_number'] ?? '');
                        if(!empty($invoice_display)): 
                    ?>
                        <div class="info-row"><div class="info-label">Factura:</div><div class="info-val" style="font-weight: bold;"><?php echo htmlspecialchars($invoice_display); ?></div></div>
                    <?php endif; ?>
                    <div class="info-row"><div class="info-label">Cliente:</div><div class="info-val"><?php 
                        $final_client = trim(!empty($first_order['owner_name']) ? $first_order['owner_name'] : (!empty($first_order['registered_owner_name']) ? $first_order['registered_owner_name'] : $first_order['contact_name']));
                        $contact_name = trim($first_order['contact_name'] ?? '');
                        echo htmlspecialchars($final_client); 
                    ?></div></div>
                </div>
                <div>
                    <?php if($final_client !== $contact_name && !empty($contact_name)): ?>
                    <div class="info-row"><div class="info-label">Contacto:</div><div class="info-val"><?php echo htmlspecialchars($contact_name); ?></div></div>
                    <?php endif; ?>
                    <?php $tax_label_print = ($first_order['service_type'] == 'warranty') ? 'Cédula/RUC:' : 'Cédula:'; ?>
                    <div class="info-row"><div class="info-label"><?php echo $tax_label_print; ?></div><div class="info-val"><?php echo htmlspecialchars($first_order['tax_id'] ?: '-'); ?></div></div>
                    <div class="info-row"><div class="info-label">Celular:</div><div class="info-val"><?php echo htmlspecialchars($first_order['phone']); ?></div></div>
                    <div class="info-row"><div class="info-label">Correo:</div><div class="info-val"><?php echo htmlspecialchars($first_order['email'] ?: '-'); ?></div></div>
                </div>
            </div>
        </div>

        <div class="section-header">INGRESO DE EQUIPO</div>
        <table class="equip_table" style="margin-top: 0; margin-bottom: 10px;">
            <thead>
                <tr>
                    <th style="width: 15%;"># CASO</th>
                    <th style="width: 45%;">EQUIPO</th>
                    <th style="width: 25%;"># SERIE</th>
                    <th style="width: 15%;">TIPO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td style="font-weight: bold; color: #2563eb;"><?php echo get_order_number($o); ?></td>
                    <td style="text-transform: uppercase; font-weight: 500;"><?php echo htmlspecialchars(trim($o['brand'])); ?></td>
                    <td style="text-transform: uppercase;"><?php echo htmlspecialchars($o['serial_number']); ?></td>
                    <td><?php echo $o['service_type'] == 'warranty' ? 'GARANTÍA' : 'SERVICIO'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-header">ACCESORIOS RECIBIDOS</div>
        <div class="section-box" style="margin-top: 0; margin-bottom: 10px; min-height: 30px;">
            <?php 
            $acc_list = [];
            foreach($orders as $o) {
                $prefix = (count($orders) > 1) ? "<strong>" . get_order_number($o) . ":</strong> " : "";
                $acc_list[] = $prefix . htmlspecialchars($o['accessories_received'] ?: 'NINGUNO');
            }
            echo implode("<br>", $acc_list);
            ?>
        </div>

        <div class="section-header">PROBLEMA REPORTADO / SERVICIO SOLICITADO</div>
        <div class="section-box" style="margin-top: 0; margin-bottom: 10px; min-height: 40px;">
            <?php 
            $prob_list = [];
            foreach($orders as $o) {
                $prefix = (count($orders) > 1) ? "<strong>" . get_order_number($o) . ":</strong> " : "";
                $prob_list[] = $prefix . nl2br(htmlspecialchars($o['problem_reported']));
            }
            echo implode("<br>", $prob_list);
            ?>
        </div>

        <div class="bottom-section">
            <?php if(!empty($first_order['entry_notes'])): ?>
            <div class="section-header" style="margin-bottom: 0;">OBSERVACIONES GENERALES</div>
            <div class="section-box" style="font-size: 11px; margin-bottom: 10px;"><?php echo nl2br(htmlspecialchars($first_order['entry_notes'])); ?></div>
            <?php endif; ?>

            <?php if ($first_order['client_id'] != 11): ?>
                <div class="legal-footer"><?php echo nl2br(htmlspecialchars($print_entry_text)); ?></div>
            <?php endif; ?>

            <div class="signatures-area">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div style="font-weight: bold;">Recibí Conforme (Taller)</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($received_by); ?></div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div style="font-weight: bold;">Entregué Conforme</div>
                    <div style="font-weight: bold; margin-top: 5px;"><?php 
                        echo htmlspecialchars($contact_name ?: $final_client); 
                    ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```
