<?php
// modules/proyectos/export_facturas.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('reporte_facturas', $pdo) && $_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 7) {
    die("Acceso denegado.");
}

$search = $_GET['search'] ?? '';
$payment = $_GET['payment'] ?? 'all';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$sql = "
    SELECT id, created_at, client_name, title, status, invoice_number, payment_status 
    FROM project_surveys 
    WHERE 1=1
";
$params = [];

if ($start) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $start;
}
if ($end) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $end;
}
if ($payment !== 'all') {
    $sql .= " AND payment_status = ?";
    $params[] = $payment;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($search) {
    $search = strtolower($search);
    $invoices = array_filter($invoices, function($inv) use ($search) {
        $text = strtolower($inv['invoice_number'] . ' ' . $inv['client_name'] . ' ' . $inv['title']);
        return strpos($text, $search) !== false;
    });
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Facturas_Proyectos_' . date('Y-m-d') . '.csv');

// Print UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['ID Proyecto', 'Fecha', 'Cliente', 'Nombre del Proyecto', 'Num. Factura', 'Estado de Pago'], ';');

foreach ($invoices as $inv) {
    $p = $inv['payment_status'] ?? 'pendiente';
    fputcsv($output, [
        $inv['id'],
        date('d/m/Y H:i', strtotime($inv['created_at'])),
        $inv['client_name'],
        $inv['title'],
        !empty($inv['invoice_number']) ? $inv['invoice_number'] : '- Sin Factura -',
        ucfirst($p)
    ], ';');
}
fclose($output);
exit;
