<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

// Check Dashboard Access
if (!can_access_module('dashboard', $pdo)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

try {
    $p_start = $_GET['p_start'] ?? '';
    $p_end = $_GET['p_end'] ?? '';
    $p_tech = $_GET['p_tech'] ?? 'all';

    $pSql = "
        SELECT u.username, COUNT(so.id) as total 
        FROM service_orders so
        JOIN users u ON so.assigned_tech_id = u.id
        LEFT JOIN warranties w ON so.id = w.service_order_id
        WHERE so.status IN ('ready', 'delivered')
        AND (w.product_code IS NULL OR w.product_code = '') 
        AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
    ";
    $pParams = [];
    if (!empty($p_start)) {
        $pSql .= " AND so.exit_date >= ?";
        $pParams[] = $p_start . " 00:00:00";
    }
    if (!empty($p_end)) {
        $pSql .= " AND so.exit_date <= ?";
        $pParams[] = $p_end . " 23:59:59";
    }
    if ($p_tech !== 'all') {
        $pSql .= " AND u.id = ?";
        $pParams[] = $p_tech;
    }
    $pSql .= " GROUP BY u.id, u.username ORDER BY total DESC";
    
    $stmtProd = $pdo->prepare($pSql);
    $stmtProd->execute($pParams);
    $results = $stmtProd->fetchAll();
    
    $labels = [];
    $counts = [];
    $totalCount = 0;
    $topTechName = '---';
    $topTechCount = 0;

    foreach ($results as $index => $row) {
        $labels[] = $row['username'];
        $counts[] = (int)$row['total'];
        $totalCount += (int)$row['total'];
        
        if ($index === 0) {
            $topTechName = $row['username'];
            $topTechCount = (int)$row['total'];
        }
    }
    
    echo json_encode([
        'labels' => $labels, 
        'counts' => $counts,
        'summary' => [
            'total' => $totalCount,
            'top_tech' => $topTechName,
            'top_count' => $topTechCount
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
