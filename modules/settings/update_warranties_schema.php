<?php
require_once '../../config/db.php';

try {
    $pdo->exec("USE system_taller");
    
    // Add columns if they don't exist
    $columns = [
        'product_code' => "VARCHAR(50) DEFAULT NULL COMMENT 'Codigo'",
        'sales_invoice_number' => "VARCHAR(50) DEFAULT NULL COMMENT 'Factura de Venta'",
        'master_entry_invoice' => "VARCHAR(50) DEFAULT NULL COMMENT 'Factura de ingreso a master'",
        'master_entry_date' => "DATE DEFAULT NULL COMMENT 'Fecha que ingreso a master'"
    ];

    foreach ($columns as $col => $def) {
        // Check if column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM warranties LIKE ?");
        $stmt->execute([$col]);
        
        if (!$stmt->fetch()) {
            echo "Adding column $col...\n";
            $pdo->exec("ALTER TABLE warranties ADD COLUMN $col $def");
        } else {
            echo "Column $col already exists.\n";
        }
    }
    
    echo "Schema update completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
