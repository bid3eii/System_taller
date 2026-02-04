<?php
require_once 'config/db.php';

try {
    // Check if permission exists
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = 'module_new_warranty'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        // Insert permission
        $pdo->exec("INSERT INTO permissions (code, description) VALUES ('module_new_warranty', 'Acceso al módulo de Nueva Garantía')");
        echo "Inserted 'module_new_warranty'.<br>";
    } else {
        echo "'module_new_warranty' already exists.<br>";
    }
    
    // Also check for 'users', 'reports', 'settings' if they are missing?
    // Based on the user's role count (11 vs 10), it's likely just one missing.
    
    echo "Migration complete.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
