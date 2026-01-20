<?php
// modules/equipment/create_test_data.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verify path to DB
$dbPath = '../../config/db.php';
if (!file_exists($dbPath)) {
    die("Error: Archivo de BD no encontrado en $dbPath <br> Current Dir: " . __DIR__);
}

require_once $dbPath;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Generando Datos de Prueba...</h2>";

    // 1. Create a dummy client
    $stmt = $pdo->prepare("INSERT INTO clients (name, tax_id, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    try {
        $stmt->execute(['Cliente Test Vencido', '00000000', '555-0000', 'test@vencido.com', 'Calle Falsa 123']);
        $clientId = $pdo->lastInsertId();
        echo "✅ Created Client ID: $clientId<br>";
    } catch (PDOException $e) {
        // If dup, find it
        echo "⚠️ Client creation skipped (maybe exists): " . $e->getMessage() . "<br>";
        $stmtFind = $pdo->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
        $stmtFind->execute(['Cliente Test Vencido']);
        $row = $stmtFind->fetch();
        if ($row) {
            $clientId = $row['id'];
            echo "ℹ️ Using existing Client ID: $clientId<br>";
        } else {
            die("❌ Critical: Could not create or find client.<br>");
        }
    }

    // 2. Create 5 Equipments and Warranties
    for ($i = 1; $i <= 5; $i++) {
        $serial = "EXP00$i";
        
        // Check if exists
        $check = $pdo->prepare("SELECT id FROM equipments WHERE serial_number = ?");
        $check->execute([$serial]);
        if($check->fetch()) {
            echo "ℹ️ Skipping Equipment $serial (already exists)<br>";
            continue;
        }

        // Insert Equipment
        $stmtEq = $pdo->prepare("INSERT INTO equipments (client_id, type, brand, model, serial_number) VALUES (?, ?, ?, ?, ?)");
        $stmtEq->execute([$clientId, 'Laptop', 'Dell', "Latitude V$i", $serial]);
        $eqId = $pdo->lastInsertId();

        // Insert Warranty (Expired)
        // Set end_date to last year
        $endDate = date('Y-m-d', strtotime('-1 year'));
        $stmtW = $pdo->prepare("INSERT INTO warranties (equipment_id, status, start_date, end_date, supplier_name, sales_invoice_number, product_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtW->execute([
            $eqId, 
            'expired', 
            date('Y-m-d', strtotime('-2 years')), 
            $endDate, 
            'Proveedor Test S.A.', 
            "INV-EXP-$i", 
            "PROD-00$i"
        ]);

        echo "✅ Created Equipment $serial with Expired Warranty (End Date: $endDate)<br>";
    }

    echo "<h3>Done! Try searching for serial numbers: EXP001, EXP002, EXP003, EXP004, EXP005</h3>";

} catch (PDOException $e) {
    echo "<h1>❌ Error General de BD: " . $e->getMessage() . "</h1>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Exception $e) {
    echo "<h1>❌ Error General: " . $e->getMessage() . "</h1>";
}
?>
