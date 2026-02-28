<?php
// modules/history/export.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check auth & permissions
if (!isset($_SESSION['user_id']) || !can_access_module('history', $pdo)) {
    die("Acceso denegado.");
}

// 1. Get Params
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

// 2. Build Query
$sql = "
    SELECT 
        so.id, so.status, so.final_cost, so.exit_date, so.entry_date, so.invoice_number, so.service_type, so.problem_reported,
        c.name as client_name, 
        e.brand, e.model, e.serial_number, e.type as equipment_type,
        u.username as delivered_by
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.authorized_by_user_id = u.id
    WHERE (so.service_type != 'warranty' OR so.problem_reported != 'Garantía Registrada')
";

$params = [];

if (!empty($status)) {
    $sql .= " AND so.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $sql .= " AND so.service_type = ?";
    $params[] = $type;
}

if (!empty($search)) {
    $term = "%$search%";
    $sql .= " AND (
        c.name LIKE ? OR 
        e.brand LIKE ? OR 
        e.model LIKE ? OR 
        e.serial_number LIKE ? OR
        so.id LIKE ? OR
        so.invoice_number LIKE ? OR
        u.username LIKE ?
    )";
    $params[] = $term; // c.name
    $params[] = $term; // e.brand
    $params[] = $term; // e.model
    $params[] = $term; // e.serial_number
    $params[] = $term; // so.id
    $params[] = $term; // so.invoice_number
    $params[] = $term; // u.username
}

$sql .= " ORDER BY so.entry_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// 3. Filename
$filename = "historial_equipos_" . date('Y-m-d_H-i') . ".xls";

// 4. Send Headers
if (ob_get_length()) ob_end_clean();
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 5. Output HTML
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!--[if gte mso 9]>
    <xml>
    <x:ExcelWorkbook>
        <x:ExcelWorksheets>
            <x:ExcelWorksheet>
                <x:Name>Historial Equipos</x:Name>
                <x:WorksheetOptions>
                    <x:DisplayGridlines/>
                </x:WorksheetOptions>
            </x:ExcelWorksheet>
        </x:ExcelWorksheets>
    </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        th { background-color: #1e293b; color: #ffffff; border: 1px solid #000000; text-align: center; vertical-align: middle; height: 30px; font-weight: bold; }
        td { border: 1px solid #e2e8f0; vertical-align: middle; text-align: center; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .type-warranty { color: #ea580c; font-weight: bold; }
        .type-service { color: #0ea5e9; font-weight: bold; }
        
        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-diagnosing { background-color: #dbeafe; color: #2563eb; }
        .status-repairing { background-color: #f3e8ff; color: #9333ea; }
        .status-ready { background-color: #dcfce7; color: #16a34a; }
        .status-delivered { background-color: #d1fae5; color: #059669; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Caso #</th>
                <th style="width: 120px;">Estado</th>
                <th style="width: 100px;">Tipo</th>
                <th style="width: 200px;">Cliente</th>
                <th style="width: 200px;">Equipo</th>
                <th style="width: 120px;">No. Serie</th>
                <th style="width: 150px;">Fecha Entrada</th>
                <th style="width: 150px;">Fecha Salida</th>
                <th style="width: 150px;">Entregado Por</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $typeClass = ($row['service_type'] === 'warranty') ? 'type-warranty' : 'type-service';
                    $typeLabel = ($row['service_type'] === 'warranty') ? 'Garantía' : 'Servicio';
                    $invoice = $row['invoice_number'] ? $row['invoice_number'] : '-';
                    $deliveredBy = $row['delivered_by'] ? $row['delivered_by'] : '-';
                    
                    // Status Labels
                    $status = $row['status'];
                    $statusLabel = $status;
                    $statusClass = '';
                    switch($status) {
                        case 'pending': $statusLabel='Pendiente'; $statusClass='status-pending'; break;
                        case 'diagnosing': $statusLabel='Diagnóstico'; $statusClass='status-diagnosing'; break;
                        case 'repairing': $statusLabel='En Reparación'; $statusClass='status-repairing'; break;
                        case 'ready': $statusLabel='Listo'; $statusClass='status-ready'; break;
                        case 'delivered': $statusLabel='Entregado'; $statusClass='status-delivered'; break;
                        case 'cancelled': $statusLabel='Cancelado'; break;
                    }
                ?>
                <tr>
                    <td class="bold">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></td>
                    <td class="<?php echo $typeClass; ?>"><?php echo $typeLabel; ?></td>
                    <td class="text-left"><?php echo htmlspecialchars($row['client_name']); ?></td>
                    <td class="text-left"><?php echo htmlspecialchars($row['brand'] . ' ' . $row['model']); ?></td>
                    <td><?php echo htmlspecialchars($row['serial_number']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['entry_date'])); ?></td>
                    <td><?php echo $row['exit_date'] ? date('d/m/Y', strtotime($row['exit_date'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($deliveredBy); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
