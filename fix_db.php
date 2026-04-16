<?php
// fix_db.php
require_once 'config/db.php';

try {
    echo "<h2>Reparador de Base de Datos - Sistema Taller</h2>";
    
    // 1. Verificar si la columna ya existe
    $check = $pdo->query("SHOW COLUMNS FROM warranties LIKE 'purchase_origin'");
    $exists = $check->fetch();

    if (!$exists) {
        echo "Agregando columna 'purchase_origin' a la tabla 'warranties'...<br>";
        
        $sql = "ALTER TABLE warranties 
                ADD COLUMN purchase_origin ENUM('local', 'importada') DEFAULT 'local' 
                AFTER supplier_name";
        
        $pdo->exec($sql);
        echo "<b style='color:green;'>✅ Columna agregada con éxito!</b><br>";
    } else {
        echo "<b style='color:blue;'>ℹ️ La columna 'purchase_origin' ya existe. No se hicieron cambios.</b><br>";
    }

    echo "<br><a href='modules/dashboard/index.php' style='padding:10px 20px; background:#3b82f6; color:white; border-radius:5px; text-decoration:none;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<b style='color:red;'>❌ Error al actualizar la base de datos:</b><br>";
    echo $e->getMessage();
    echo "<br><br><b>Sugerencia:</b> Asegúrate de que MySQL esté encendido en XAMPP e intenta ejecutar el SQL manualmente en phpMyAdmin.";
}
