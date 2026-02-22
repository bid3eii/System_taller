<?php
// modules/equipment/print_delivery_multi.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$ids = $_GET['ids'] ?? null;
if (!$ids) {
    die("IDs no especificados.");
}

// Parse IDs
$idArray = array_map('intval', explode(',', $ids));
if (empty($idArray)) {
    die("IDs inválidos.");
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
$print_footer_text = $settings['print_footer_text'] ?? 'Declaración de Conformidad: El cliente declara recibir el equipo a su entera satisfacción...';

// Fetch All Orders
$placeholders = implode(',', array_fill(0, count($idArray), '?'));
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c_contact.name as contact_name, c_contact.phone, c_contact.email, c_contact.tax_id, c_contact.address,
        c_contact.id as client_id,
        (SELECT c_o.name 
         FROM service_orders so_o 
         JOIN clients c_o ON so_o.client_id = c_o.id 
         WHERE so_o.equipment_id = so.equipment_id 
           AND (so_o.service_type = 'warranty' OR so_o.problem_reported = 'Garantía Registrada')
         ORDER BY so_o.created_at ASC 
         LIMIT 1) as owner_name,
        e.brand, e.model, e.serial_number, e.type as equipment_type, e.submodel,
        u.username as delivered_by
    FROM service_orders so
    JOIN clients c_contact ON so.client_id = c_contact.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.authorized_by_user_id = u.id
    WHERE so.id IN ($placeholders)
    ORDER BY so.id ASC
");
$stmt->execute($idArray);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    die("No se encontraron órdenes.");
}

// Validate all orders belong to same client
$firstClientId = $orders[0]['client_id'];
foreach ($orders as $order) {
    if ($order['client_id'] != $firstClientId) {
        die("Error: Todos los equipos deben pertenecer al mismo cliente.");
    }
}

// Use first order for client data
$clientData = $orders[0];

// Fallback for owner_name
if (!$clientData['owner_name']) {
    $stmtFallback = $pdo->prepare("SELECT c.name FROM equipments e JOIN clients c ON e.client_id = c.id WHERE e.id = ?");
    $stmtFallback->execute([$clientData['equipment_id']]);
    $clientData['owner_name'] = $stmtFallback->fetchColumn() ?: $clientData['contact_name'];
}

// Fetch history and notes for each order
$equipmentData = [];
foreach ($orders as $order) {
    $stmtHistory = $pdo->prepare("SELECT action, notes FROM service_order_history WHERE service_order_id = ? ORDER BY created_at ASC");
    $stmtHistory->execute([$order['id']]);
    $allHistory = $stmtHistory->fetchAll();
    
    $diagnosisNotesArr = [];
    $repairNotesArr = [];
    
    if (!empty($order['diagnosis_notes'])) $diagnosisNotesArr[] = $order['diagnosis_notes'];
    if (!empty($order['work_done'])) $repairNotesArr[] = $order['work_done'];
    
    foreach ($allHistory as $h) {
        if (($h['action'] === 'diagnosing' || $h['action'] === 'pending_approval') && !empty($h['notes'])) {
            if (!in_array($h['notes'], $diagnosisNotesArr)) {
                 $diagnosisNotesArr[] = $h['notes'];
            }
        }
        if (($h['action'] === 'in_repair' || $h['action'] === 'ready') && !empty($h['notes'])) {
             if (!in_array($h['notes'], $repairNotesArr)) {
                 $repairNotesArr[] = $h['notes'];
            }
        }
    }
    
    $equipmentData[] = [
        'order' => $order,
        'diagnosis_notes' => implode("\n", $diagnosisNotesArr),
        'repair_notes' => implode("\n", $repairNotesArr)
    ];
}

// Get receiver info from last order's delivery note
$lastOrder = end($orders);
$stmtLastHistory = $pdo->prepare("SELECT notes FROM service_order_history WHERE service_order_id = ? AND action = 'delivered' ORDER BY created_at DESC LIMIT 1");
$stmtLastHistory->execute([$lastOrder['id']]);
$fullDeliveryNote = $stmtLastHistory->fetchColumn();

$receiverName = $clientData['contact_name']; 
$deliveryComments = '';
$receiverId = '';

if ($fullDeliveryNote) {
    if (preg_match('/Entregado a: (.*?) \((.*?)\)\. Notas: (.*)/', $fullDeliveryNote, $matches)) {
        $receiverName = $matches[1];
        $receiverId = $matches[2];
        $deliveryComments = $matches[3];
    } elseif (preg_match('/Entregado a: (.*?) \((.*?)\)/', $fullDeliveryNote, $matches)) {
        $receiverName = $matches[1];
        $receiverId = $matches[2];
    }
}

// Use first order's exit_doc_number or generate new one
$exit_doc_number = $orders[0]['exit_doc_number'];
if (empty($exit_doc_number)) {
    try {
        $pdo->beginTransaction();
        $next_val = get_next_sequence($pdo, 'exit_doc');
        
        $stmtUpdate = $pdo->prepare("UPDATE service_orders SET exit_doc_number = ? WHERE id = ?");
        $stmtUpdate->execute([$next_val, $orders[0]['id']]);
        
        $pdo->commit();
        $exit_doc_number = $next_val;
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrega Múltiple</title>
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
            grid-template-columns: 25% 55% 20%;
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
            font-size: 9px;
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
            @page { margin: 5mm; size: auto; }
            .actions { display: none; }
            body { background: white; padding: 0; }
            .paper { 
                box-shadow: none; 
                margin: 0; 
                width: 100%; 
                min-height: 280mm; 
                padding: 10mm 15mm 20mm 15mm; 
                page-break-after: avoid; 
                page-break-inside: avoid;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                overflow: visible; 
            }
            a[href]:after { content: none !important; }
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
             // Check if we came from reports module specifically
            if (document.referrer.includes('/reports/')) {
                window.location.href = '../reports/index.php';
            } else {
                // For all other cases (including coming from confirm page), go to exit list
                window.location.href = 'exit.php';
            }
        }
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
                <h2>ENTREGA DE EQUIPOS</h2>
                <h3>SOPORTE TÉCNICO</h3>
                <p>Tel: <?php echo htmlspecialchars($company_phone); ?> | Email: <?php echo htmlspecialchars($company_email); ?></p>
            </div>
            <div class="header-col header-right">
                <div class="doc-box"><?php echo str_pad($exit_doc_number ?? 0, 5, '0', STR_PAD_LEFT); ?></div>
                <div style="font-size: 10px;">Doc. Salida</div>
            </div>
        </div>

        <!-- CLIENT DATA -->
        <div class="section-header">DATOS DE CLIENTE</div>
        <div class="section-box">
            <div class="client-grid">
                <div>
                    <div class="info-row">
                        <div class="info-label">Fecha:</div>
                        <div class="info-val"><?php echo date('d/m/Y h:i:s A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Cliente:</div>
                        <div class="info-val"><?php echo htmlspecialchars($clientData['owner_name']); ?></div>
                    </div>
                </div>
                <div>
                    <div class="info-row">
                        <div class="info-label">Contacto:</div>
                        <div class="info-val"><?php echo htmlspecialchars($clientData['contact_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RUC/Cédula:</div>
                        <div class="info-val"><?php echo htmlspecialchars($clientData['tax_id'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Celular:</div>
                        <div class="info-val"><?php echo htmlspecialchars($clientData['phone']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Correo:</div>
                        <div class="info-val"><?php echo htmlspecialchars($clientData['email']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EQUIPMENT TABLE -->
        <div class="section-header">SALIDA DE <?php echo count($orders); ?> EQUIPO(S)</div>
        <table class="equip-table">
            <thead>
                <tr>
                    <th>INGRESO</th>
                    <th>MARCA</th>
                    <th>MODELO</th>
                    <th># SERIE</th>
                    <th>CASO #</th>
                    <th>DIAG #</th>
                    <th>REP #</th>
                    <th>DESCRIPCIÓN</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipmentData as $item): 
                    $order = $item['order'];
                ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($order['entry_date'])); ?></td>
                    <td><?php echo htmlspecialchars($order['brand']); ?></td>
                    <td><?php echo htmlspecialchars($order['model']); ?></td>
                    <td><?php echo htmlspecialchars($order['serial_number']); ?></td>
                    <td style="font-weight: bold; color: #2563eb;"><?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo $order['diagnosis_number'] ? str_pad($order['diagnosis_number'], 5, '0', STR_PAD_LEFT) : '-'; ?></td>
                    <td><?php echo $order['repair_number'] ? str_pad($order['repair_number'], 5, '0', STR_PAD_LEFT) : '-'; ?></td>
                    <td style="font-size: 8px; text-align: left; padding: 2px 4px;">
                        <?php echo htmlspecialchars(substr($order['problem_reported'], 0, 50)); ?>
                        <?php if ($item['diagnosis_notes']): ?>
                            <br><strong>Diag:</strong> <?php echo htmlspecialchars(substr($item['diagnosis_notes'], 0, 40)); ?>
                        <?php endif; ?>
                        <?php if ($item['repair_notes']): ?>
                            <br><strong>Rep:</strong> <?php echo htmlspecialchars(substr($item['repair_notes'], 0, 40)); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="bottom-section">
            <!-- COMENTARIOS -->
            <div class="section-header">COMENTARIOS</div>
            <div class="comments-box">
                <?php 
                    if ($deliveryComments) {
                        echo nl2br(htmlspecialchars($deliveryComments));
                    } else {
                        echo "&nbsp;";
                    }
                ?>
            </div>
            <div class="legal-footer">
                <?php 
                    $footer_txt = htmlspecialchars($print_footer_text);
                    $footer_txt = str_replace('Declaración de Conformidad:', '<strong>Declaración de Conformidad:</strong>', $footer_txt);
                    echo nl2br($footer_txt); 
                ?>
            </div>

            <!-- SIGNATURES -->
            <div class="signatures-area">
                <div class="sig-box">
                    <!-- TALLER Signature -->
                    
                    <?php 
                        $deliveredBy = $clientData['delivered_by'] ?? 'Taller Mastertec';
                        
                        // SNAPSHOT LOGIC
                        $sigPath = $clientData['exit_signature_path'] ?? null;
                        
                        // Fetch role (and signature fallback)
                        $stmtSig = $pdo->prepare("SELECT u.signature_path, r.name as role_name 
                                                  FROM users u 
                                                  LEFT JOIN roles r ON u.role_id = r.id 
                                                  WHERE u.username = ?");
                        $stmtSig->execute([$deliveredBy]);
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

                    <div style="font-weight: bold; margin-top: 5px; margin-bottom: 5px;">Entrega Conforme</div>
                    <div style="font-size: 10px;"><?php echo htmlspecialchars($deliveredBy); ?></div>
                    <div style="font-size: 10px; font-weight: bold;"><?php echo htmlspecialchars($roleName); ?></div>
                </div>
                
                 <div class="sig-box">
                    <div style="height: 20px;"></div>
                    <div class="sig-line"></div>
                    <div style="font-weight: bold; margin-top: 5px; margin-bottom: 5px;">Recibe Conforme</div>
                    <div style="text-align: center; margin-top: 5px;">
                        <?php echo htmlspecialchars($receiverName); ?>
                    </div>
                     <div style="text-align: center; margin-top: 5px;">
                        <?php echo htmlspecialchars($receiverId); ?>
                    </div>
                </div>
            </div>
        </div>

</body>
</html>
