<?php
require_once 'c:\xampp\htdocs\System_taller\config\db.php';
try {
    // Drop table if debugging/re-running (optional, uncomment if needed)
    // $pdo->exec("DROP TABLE IF EXISTS equipment_categories");
    
    // Create categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS equipment_categories (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            default_months INT(11) NOT NULL DEFAULT 12,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "Table equipment_categories created or already exists.\n";
    
    // Add category_id to equipments
    try {
        $pdo->exec("ALTER TABLE equipments ADD COLUMN category_id INT(11) NULL DEFAULT NULL");
        echo "Column category_id added to equipments.\n";
        
        $pdo->exec("ALTER TABLE equipments ADD CONSTRAINT fk_equipments_category FOREIGN KEY (category_id) REFERENCES equipment_categories(id) ON DELETE SET NULL");
        echo "Foreign key added.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Column category_id already exists.\n";
        } else {
            echo "Error altering table: " . $e->getMessage() . "\n";
        }
    }
    
    // Insert some defaults
    $stmt = $pdo->query("SELECT COUNT(*) FROM equipment_categories");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO equipment_categories (name, default_months) VALUES ('UPS Básico', 12), ('Baterías', 6), ('Inversores', 24)");
        echo "Default data inserted.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
