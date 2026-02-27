<?php
// modules/equipment/print_entry.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
$ids_str = $_GET['ids'] ?? null;

if (!$id && !$ids_str) {
    die("ID no especificado.");
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
        co.name as owner_name
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients co ON e.client_id = co.id
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
$stmtCreator = $pdo->prepare("SELECT u.username FROM service_order_history h JOIN users u ON h.user_id = u.id WHERE h.service_order_id = ? AND h.action = 'received' ORDER BY h.created_at ASC LIMIT 1");
$stmtCreator->execute([$first_order['id']]);
$received_by = $stmtCreator->fetchColumn() ?: 'Taller Mastertec';

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
        .paper { background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 15mm; display: flex; flex-direction: column; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
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
        .section-box { border: 1.5px solid var(--border-color); padding: 5px; margin-bottom: 8px; }
        .client-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .info-row { margin-bottom: 3px; display: flex; }
        .info-label { font-weight: bold; width: 80px; text-align: right; margin-right: 10px; }
        .info-label { font-weight: bold; width: 80px; text-align: right; margin-right: 10px; }
        .info-val { flex: 1; }

        .equip_table { width: 100%; border-collapse: collapse; border: 1.5px solid var(--border-color); margin-top: 8px; margin-bottom: 8px;}
        .equip_table th, .equip_table td { border: 1.5px solid var(--border-color); padding: 4px; text-align: center; font-size: 11px; }
        .equip_table th { background: #fff; font-weight: bold; }
        
        .legal-footer { font-size: 10px; text-align: justify; border: 1px solid var(--border-color); padding: 8px; margin-bottom: 10px; line-height: 1.3; }
        .signatures-area { display: flex; justify-content: space-around; margin-top: 10px; }
        .bottom-section { margin-top: auto; width: 100%; }
        .sig-box { width: 40%; text-align: center; }
        .sig-line { border-bottom: 1px solid black; height: 50px; margin-bottom: 5px; }

        @media print { .actions { display: none; } body { background: white; } .paper { box-shadow: none; margin: 0; width: 100%; padding: 10mm; } }
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
                <h2>RECEPCIÓN DE EQUIPOS</h2>
                <h3>SOPORTE TÉCNICO</h3>
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
                    <div class="info-row"><div class="info-label">Cliente:</div><div class="info-val"><?php echo htmlspecialchars(!empty($first_order['owner_name']) ? $first_order['owner_name'] : $first_order['contact_name']); ?></div></div>
                </div>
                <div>
                    <div class="info-row"><div class="info-label">Contacto:</div><div class="info-val"><?php echo htmlspecialchars($first_order['contact_name']); ?></div></div>
                    <?php $tax_label_print = ($first_order['service_type'] == 'warranty') ? 'Cédula/RUC:' : 'Cédula:'; ?>
                    <div class="info-row"><div class="info-label"><?php echo $tax_label_print; ?></div><div class="info-val"><?php echo htmlspecialchars($first_order['tax_id'] ?: '-'); ?></div></div>
                    <div class="info-row"><div class="info-label">Celular:</div><div class="info-val"><?php echo htmlspecialchars($first_order['phone']); ?></div></div>
                    <div class="info-row"><div class="info-label">Correo:</div><div class="info-val"><?php echo htmlspecialchars($first_order['email'] ?: '-'); ?></div></div>
                </div>
            </div>
        </div>

        <div class="section-header" style="margin-bottom: 0; border-bottom: none;">INGRESO DE EQUIPO</div>
        <table class="equip_table" style="margin-top: 0; margin-bottom: 8px;">
            <thead>
                <tr>
                    <th style="width: 60%;">EQUIPO</th>
                    <th style="width: 20%;"># SERIE</th>
                    <th style="width: 20%;">TIPO DE SERVICIO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td style="text-align: center; text-transform: uppercase;"><?php echo htmlspecialchars(trim($o['brand'] . ' ' . $o['model'])); ?></td>
                    <td style="text-align: center; text-transform: uppercase;"><?php echo htmlspecialchars($o['serial_number']); ?></td>
                    <td style="text-align: center;"><?php echo $o['service_type'] == 'warranty' ? 'GARANTÍA' : 'SERVICIO'; ?></td>
                </tr>
                <tr>
                    <td colspan="4" style="padding: 0; background: #fff;">
                        <div style="font-weight: bold; text-align: center; border-bottom: 1.5px solid var(--border-color); padding: 4px; font-size: 11px;">ACCESORIOS RECIBIDOS</div>
                        <div style="padding: 6px; font-size: 11px; text-align: left;">
                            <?php echo htmlspecialchars($o['accessories_received'] ?: 'N/A'); ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding: 0; background: #fff;">
                        <div style="font-weight: bold; text-align: center; border-bottom: 1.5px solid var(--border-color); padding: 4px; font-size: 11px;">PROBLEMA REPORTADO / SERVICIO SOLICITADO</div>
                        <div style="padding: 6px; font-size: 11px; text-align: left;">
                            <?php echo nl2br(htmlspecialchars($o['problem_reported'])); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="bottom-section">
            <?php if(!empty($first_order['entry_notes'])): ?>
            <div class="section-header" style="margin-bottom: 0;">OBSERVACIONES GENERALES</div>
            <div class="section-box" style="font-size: 11px; margin-bottom: 10px;"><?php echo nl2br(htmlspecialchars($first_order['entry_notes'])); ?></div>
            <?php endif; ?>

            <div class="legal-footer"><?php echo nl2br(htmlspecialchars($print_entry_text)); ?></div>

            <div class="signatures-area">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div style="font-weight: bold;">Recibí Conforme (Taller)</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($received_by); ?></div>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div style="font-weight: bold;">Entregué Conforme (Cliente)</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($first_order['contact_name']); ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
