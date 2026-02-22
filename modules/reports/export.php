<?php
// modules/reports/export.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

// 1. Get Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$type   = isset($_GET['type'])   ? $_GET['type']   : 'all';

// 2. Build Query
$sql = "
    SELECT 
        so.id, 
        so.entry_date, 
        c.name as client_name, 
        c.phone as client_phone,
        e.brand, 
        e.model, 
        e.type as equipment_type,
        so.service_type,
        so.status, 
        so.diagnosis_number,
        u.username as tech_name
    FROM service_orders so
    LEFT JOIN clients c ON so.client_id = c.id
    LEFT JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN users u ON so.assigned_tech_id = u.id
    WHERE 1=1
";

$params = [];

// Apply Filters
if ($type !== 'all' && !empty($type)) {
    $sql .= " AND so.service_type = ?";
    $params[] = $type;
}

if ($status !== 'all' && !empty($status)) {
    $sql .= " AND so.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $term = "%$search%";
    $sql .= " AND (
        so.id LIKE ? OR 
        c.name LIKE ? OR 
        c.phone LIKE ? OR 
        e.brand LIKE ? OR 
        e.model LIKE ?
    )";
    $params[] = $term; // id
    $params[] = $term; // name
    $params[] = $term; // phone
    $params[] = $term; // brand
    $params[] = $term; // model
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
$prefix = "reporte_general";
if ($type === 'service') {
    $prefix = "reporte_servicios";
} elseif ($type === 'warranty') {
    $prefix = "reporte_garantias";
}
$filename = $prefix . "_" . date('Y-m-d_H-i') . ".xls";

// 4. Send Headers for Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 5. Output HTML Table
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!--[if gte mso 9]>
    <xml>
    <x:ExcelWorkbook>
        <x:ExcelWorksheets>
            <x:ExcelWorksheet>
                <x:Name>Reporte</x:Name>
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
        
        /* Status Colors */
        .status-ready { background-color: #dcfce7; color: #166534; }
        .status-delivered { background-color: #f1f5f9; color: #475569; }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-process { background-color: #f3e8ff; color: #6b21a8; }
        .status-diagnosed { background-color: #dbeafe; color: #1e40af; }
        
        /* Type Colors */
        .type-warranty { color: #ea580c; font-weight: bold; }
        .type-service { color: #0ea5e9; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">ID</th>
                <th style="width: 100px;">Fecha</th>
                <th style="width: 200px;">Cliente</th>
                <th style="width: 120px;">Teléfono</th>
                <th style="width: 150px;">Equipo</th>
                <th style="width: 120px;">Marca</th>
                <th style="width: 150px;">Modelo</th>
                <th style="width: 100px;">Tipo</th>
                <th style="width: 120px;">Estado</th>
                <th style="width: 100px;">Diagnóstico</th>
                <th style="width: 150px;">Técnico</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    // Helper to map status to class
                    $s = $row['status'];
                    $sClass = '';
                    if (in_array($s, ['ready', 'approved'])) $sClass = 'status-ready';
                    elseif ($s === 'delivered') $sClass = 'status-delivered';
                    elseif (in_array($s, ['pending', 'pending_approval', 'received'])) $sClass = 'status-pending';
                    elseif ($s === 'in_repair') $sClass = 'status-process';
                    else $sClass = 'status-diagnosed'; // diagnosing

                    // Labels
                    $statusMap = [
                        'pending' => 'Pendiente', 'received' => 'Recibido', 'diagnosing' => 'Diagnosticado',
                        'pending_approval' => 'En Espera', 'approved' => 'Aprobado', 'in_repair' => 'En Proceso',
                        'ready' => 'Listo', 'delivered' => 'Entregado'
                    ];
                    $label = $statusMap[$s] ?? ucfirst($s);
                    
                    $typeClass = ($row['service_type'] === 'warranty') ? 'type-warranty' : 'type-service';
                    $typeLabel = ($row['service_type'] === 'warranty') ? 'GARANTÍA' : 'SERVICIO';
                ?>
                <tr>
                    <td class="bold">#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['entry_date'])); ?></td>
                    <td class="text-left bold"><?php echo htmlspecialchars($row['client_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['client_phone']); ?></td>
                    <td><?php echo htmlspecialchars($row['equipment_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['brand']); ?></td>
                    <td><?php echo htmlspecialchars($row['model']); ?></td>
                    <td class="<?php echo $typeClass; ?>"><?php echo $typeLabel; ?></td>
                    <td class="<?php echo $sClass; ?>"><?php echo $label; ?></td>
                    <td><?php echo !empty($row['diagnosis_number']) ? '#'.str_pad($row['diagnosis_number'], 5, '0', STR_PAD_LEFT) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($row['tech_name']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
