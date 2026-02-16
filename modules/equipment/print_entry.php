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
$company_address = $settings['company_address'] ?? 'Av. Principal 123, Ciudad';
$company_phone = $settings['company_phone'] ?? '(555) 123-4567';
$print_entry_text = $settings['print_entry_text'] ?? "1. No nos responsabilizamos por perdida de información en medios de almacenamiento como discos duros interno o externos al momento del ingreso o en el proceso de diagnóstico.\n2. Equipos deben ser retirados en un máximo de 30 días calendarios después de notificado trabajo finalizado. Después de este tiempo si el cliente no se presenta a retirar, autoriza a MASTERTEC a desechar el equipo.\n3. En caso de no reparar equipo, cliente deberá pagar el diagnóstico correspondiente.\n4. Para consulta del estado de su equipo favor escribenos a: soporte@mastertec.com.ni\n5. Tiempo de diagnóstico mínimo 48 horas.";

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c_contact.name as contact_name, c_contact.phone, c_contact.email, c_contact.tax_id, c_contact.address,
        COALESCE(
            NULLIF(so.owner_name, ''),
            (SELECT c_o.name 
             FROM service_orders so_o 
             JOIN clients c_o ON so_o.client_id = c_o.id 
             WHERE so_o.equipment_id = so.equipment_id 
               AND (so_o.service_type = 'warranty' OR so_o.problem_reported = 'Garantía Registrada')
             ORDER BY so_o.created_at ASC 
             LIMIT 1)
        ) as owner_name_calc,
        e.brand, e.model, e.submodel, e.serial_number, e.type as equipment_type,
        u.username as received_by
    FROM service_orders so
    JOIN clients c_contact ON so.client_id = c_contact.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON u.id = ? 
    WHERE so.id = ?
");
// Note: 'received_by' logic usually tracks who created it. 

$stmt->execute([$_SESSION['user_id'], $id]); 
$order = $stmt->fetch();

// Fallback if no specific owner found
if (!$order['owner_name_calc']) {
    $stmtFallback = $pdo->prepare("SELECT c.name FROM equipments e JOIN clients c ON e.client_id = c.id WHERE e.id = ?");
    $stmtFallback->execute([$order['equipment_id']]);
    $order['owner_name'] = $stmtFallback->fetchColumn(); // Removed fallback to contact
} else {
    $order['owner_name'] = $order['owner_name_calc'];
}

// Display Logic
$owner = trim($order['owner_name'] ?? '');
$contact = trim($order['contact_name'] ?? '');

// Primary Field (Left Col): "Cliente" should show the Equip Owner
$client_label = 'Cliente:';
$client_val = !empty($owner) ? $owner : $contact;

// Secondary Field (Right Col): "Contacto" (if different)
$show_secondary = false;
$secondary_label = 'Contacto:';
$secondary_val = $contact;

if (!empty($owner) && !empty($contact)) {
    if ($owner !== $contact) {
        $show_secondary = true;
    }
}

if (!$order) {
    die("Orden no encontrada.");
}

// Get Receiver Name (Creator)
$stmtCreator = $pdo->prepare("SELECT u.username FROM service_order_history h JOIN users u ON h.user_id = u.id WHERE h.service_order_id = ? AND h.action = 'received' ORDER BY h.created_at ASC LIMIT 1");
$stmtCreator->execute([$id]);
$creator = $stmtCreator->fetchColumn();
$received_by = $creator ? $creator : 'Taller Mastertec';

// Ensure Entry Doc Number exists
if (empty($order['entry_doc_number'])) {
    try {
        $pdo->beginTransaction();
        $next_val = get_next_sequence($pdo, 'entry_doc');
        
        $stmtUpdate = $pdo->prepare("UPDATE service_orders SET entry_doc_number = ? WHERE id = ?");
        $stmtUpdate->execute([$next_val, $id]);
        
        $pdo->commit();
        $order['entry_doc_number'] = $next_val;
    } catch (Exception $e) {
        $pdo->rollBack();
        // Continue without number if error
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --border-color: #000;
            --header-bg: #e5e5e5;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
            font-size: 13px;
        }
        
        .paper {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* ACTIONS */
        .actions {
            position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 999;
        }
        .btn { padding: 8px 16px; border-radius: 4px; cursor: pointer; border: none; font-weight: bold; text-decoration: none; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: white; color: #333; }

        /* HEADER GRID */
        .header-grid {
            display: grid;
            grid-template-columns: 25% 55% 20%; /* 3 columns */
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }
        
        .header-col {
            padding: 5px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .header-logo {
            text-align: center;
            border-right: 1px solid var(--border-color);
        }
        
        .logo-img {
            max-width: 150px;
            max-height: 50px;
            object-fit: contain;
            margin: 0 auto;
            display: block;
        }
        
        .header-center {
            text-align: center;
            border-right: 1px solid var(--border-color);
        }
        .header-center h2 { margin: 0; font-size: 14px; font-weight: 700; text-transform: uppercase; }
        .header-center h3 { margin: 2px 0; font-size: 12px; font-weight: 700; }
        .header-center p { margin: 2px 0; font-size: 10px; }

        .header-right {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .doc-box {
            border: 1px solid var(--border-color);
            padding: 5px 15px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* SECTIONS */
        .section-header {
            background: var(--header-bg);
            border: 1px solid var(--border-color);
            border-bottom: none;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px;
            font-size: 11px;
        }
        
        .section-box {
            border: 1px solid var(--border-color);
            padding: 5px 10px;
            margin-bottom: 10px;
        }
        
        /* CLIENT GRID */
        .client-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .info-row { margin-bottom: 3px; display: flex; }
        .info-label { font-weight: bold; width: 110px; text-align: right; margin-right: 10px; min-width: 110px; }
        .info-val { flex: 1; }

        /* TABLE */
        .equip-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }
        .equip-table th, .equip-table td {
            border: 1px solid var(--border-color);
            padding: 4px;
            text-align: center;
            font-size: 10px;
        }
        .equip-table th { background: var(--header-bg); font-weight: bold; text-transform: uppercase; }
        
        /* COMMENTS */
        .comments-box {
            border: 1px solid var(--border-color);
            padding: 5px;
            min-height: 40px;
            margin-bottom: 5px;
            font-size: 10px;
            line-height: 1.2;
        }

        /* SIGNATURES */
        .signatures-area {
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            margin-bottom: 0;
        }
        .sig-box {
            width: 40%;
            text-align: center;
        }
        .sig-line {
            border-bottom: 1px solid black;
            height: 40px;
            margin-bottom: 5px;
        }
        
        .bottom-section {
            margin-top: auto;
            width: 100%;
        }
        
        .legal-footer {
            margin-top: 10px;
            font-size: 11px;
            text-align: justify;
            border: 1px solid var(--border-color);
            padding: 5px;
            margin-bottom: 20px;
        }

        @media print {
            @page { margin: 0; size: auto; }
            .actions { display: none; }
            body { background: white; padding: 0; }
            .paper { 
                box-shadow: none; 
                margin: 0; 
                width: 100%; 
                height: 270mm; 
                padding: 10mm 15mm; 
                page-break-after: avoid; 
                page-break-inside: avoid;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                overflow: visible; 
            }
            body { overflow: hidden; }
        }
    </style>
</head>
<body>

    <div class="actions">
        <!-- Back button removed as requested -->
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
    </div>

    <script>
        // goBack function removed
    </script>

    <div class="paper">
        <!-- HEADER -->
        <div class="header-grid">
            <div class="header-col header-logo">
                <?php if($system_logo): ?>
                    <img src="../../assets/uploads/<?php echo $system_logo; ?>" class="logo-img">
                <?php endif; ?>
                <div style="font-weight: bold; font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($company_name); ?></div>
            </div>
            <div class="header-col header-center">
                <h2>RECEPCIÓN DE EQUIPOS</h2>
                <h3>SOPORTE TÉCNICO</h3>
                <p>Tel: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?></p>
            </div>
            <div class="header-col header-right">
                <div class="doc-box"><?php echo str_pad($order['entry_doc_number'] ?? 0, 5, '0', STR_PAD_LEFT); ?></div>
                <div style="font-size: 10px;">Doc. Entrada</div>
            </div>
        </div>

        <!-- CLIENT DATA -->
        <div class="section-header">DATOS DE CLIENTE</div>
        <div class="section-box">
            <div class="client-grid">
                <div>
                    <div class="info-row">
                        <div class="info-label">Fecha:</div>
                        <div class="info-val"><?php echo date('d/m/Y h:i:s A', strtotime($order['entry_date'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Caso #:</div>
                        <div class="info-val" style="font-weight: bold; color: #2563eb;"><?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label"><?php echo $client_label; ?></div>
                        <div class="info-val"><?php echo htmlspecialchars($client_val); ?></div>
                    </div>
                </div>
                <div>
                    <?php if($show_secondary): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo $secondary_label; ?></div>
                        <div class="info-val"><?php echo htmlspecialchars($secondary_val); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <div class="info-label">RUC/Cédula:</div>
                        <div class="info-val"><?php echo htmlspecialchars($order['tax_id'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Celular:</div>
                        <div class="info-val"><?php echo htmlspecialchars($order['phone']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Correo:</div>
                        <div class="info-val"><?php echo htmlspecialchars($order['email']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EQUIPMENT TABLE -->
        <div class="section-header">INGRESO DE EQUIPO</div>
        <table class="equip-table">
            <thead>
                <tr>
                <tr>
                    <th>MARCA</th>
                    <th>MODELO</th>
                    <th># SERIE</th>
                    <th>TIPO DE SERVICIO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($order['brand']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['model']); ?>
                        <?php if(!empty($order['submodel'])): ?>
                            <div style="font-size: 9px; color: #555;"><?php echo htmlspecialchars($order['submodel']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($order['serial_number']); ?></td>
                    <td>
                        <?php 
                            if ($order['service_type'] == 'warranty') echo 'GARANTÍA';
                            else echo 'SERVICIO';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-header">ACCESORIOS RECIBIDOS</div>
        <div class="comments-box" style="min-height: 25px;">
             <?php echo htmlspecialchars($order['accessories_received']); ?>
        </div>

        <!-- PROBLEM DESC -->
        <div class="section-header">PROBLEMA REPORTADO / SERVICIO SOLICITADO</div>
        <div class="comments-box">
            <?php echo nl2br(htmlspecialchars($order['problem_reported'])); ?>
        </div>

        <!-- NOTES -->
        <div class="section-header">OBSERVACIONES DE INGRESO</div>
        <div class="comments-box">
            <?php 
                echo $order['entry_notes'] ? nl2br(htmlspecialchars($order['entry_notes'])) : 'Ninguna';
            ?>
        </div>
        
        <div class="bottom-section">
            <div class="legal-footer">
                <?php echo nl2br(htmlspecialchars($print_entry_text)); ?>
            </div>

            <!-- SIGNATURES -->
            <div class="signatures-area">
                <div class="sig-box">
                    <?php 
                        // SNAPSHOT LOGIC: Use preserved signature if available, otherwise fallback to current user profile
                        $sigPath = $entry_order['entry_signature_path'] ?? null;
                        
                        // Fetch role (and signature fallback)
                        $stmtSig = $pdo->prepare("SELECT u.signature_path, r.name as role_name 
                                                  FROM users u 
                                                  LEFT JOIN roles r ON u.role_id = r.id 
                                                  WHERE u.username = ?");
                        $stmtSig->execute([$received_by]);
                        $userData = $stmtSig->fetch();
                        
                        // If no snapshot, use current
                        if (!$sigPath) {
                            $sigPath = $userData['signature_path'] ?? null;
                        }
                        
                        $roleName = $userData['role_name'] ?? 'Taller';
                        
                        if ($sigPath && file_exists('../../assets/uploads/signatures/' . $sigPath)):
                    ?>
                        <div style="height: 40px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: -15px; position: relative; z-index: 1; top: 15px;">
                             <img src="../../assets/uploads/signatures/<?php echo $sigPath; ?>" style="max-height: 50px; max-width: 150px;">
                        </div>
                    <?php else: ?>
                         <div style="height: 25px;"></div>
                    <?php endif; ?>
                    
                    <div class="sig-line"></div>
                    
                    <div style="font-weight: bold; margin-top: 5px; margin-bottom: 5px;">Recibí Conforme</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($received_by); ?></div>
                    <div style="font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($roleName); ?></div>
                </div>
                
                 <div class="sig-box">
                    <div style="height: 20px;"></div>
                    <div class="sig-line"></div>
                    <div style="font-weight: bold; margin-top: 5px; margin-bottom: 5px;">Entregué Conforme (Cliente)</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($order['contact_name']); ?></div>
                </div>
            </div>
        </div>

</body>
</html>
