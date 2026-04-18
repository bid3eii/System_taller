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

    // 2. Verificar tabla 'schedule_events'
    $check_table = $pdo->query("SHOW TABLES LIKE 'schedule_events'");
    $table_exists = $check_table->fetch();

    if (!$table_exists) {
        echo "Creando tabla 'schedule_events'...<br>";
        $sql_table = "CREATE TABLE `schedule_events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `tech_id` int(11) NOT NULL,
            `start_datetime` datetime NOT NULL,
            `end_datetime` datetime NOT NULL,
            `location` varchar(255) DEFAULT NULL,
            `survey_id` int(11) DEFAULT NULL,
            `service_order_id` int(11) DEFAULT NULL,
            `status` enum('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            `color` varchar(20) DEFAULT '#6366f1',
            `created_at` timestamp DEFAULT current_timestamp(),
            `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `tech_id` (`tech_id`),
            KEY `survey_id` (`survey_id`),
            KEY `service_order_id` (`service_order_id`),
            CONSTRAINT `schedule_events_ibfk_1` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `schedule_events_ibfk_2` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL,
            CONSTRAINT `schedule_events_ibfk_3` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql_table);
        echo "<b style='color:green;'>✅ Tabla 'schedule_events' creada con éxito!</b><br>";
    } else {
        echo "<b style='color:blue;'>ℹ️ La tabla 'schedule_events' ya existe.</b><br>";
    }

    // 3. Verificar columna 'survey_id' en 'viaticos'
    $check_viaticos = $pdo->query("SHOW COLUMNS FROM viaticos LIKE 'survey_id'");
    if (!$check_viaticos->fetch()) {
        echo "Agregando columna 'survey_id' a 'viaticos'...<br>";
        $pdo->exec("ALTER TABLE `viaticos` ADD COLUMN `survey_id` int(11) DEFAULT NULL AFTER `project_title`;");
        $pdo->exec("ALTER TABLE `viaticos` ADD CONSTRAINT `viaticos_survey_fk` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL;");
        $pdo->exec("ALTER TABLE `viaticos` ADD INDEX (`survey_id`);");
        echo "<b style='color:green;'>✅ Columna 'survey_id' agregada a 'viaticos'!</b><br>";
    }

    echo "<br><a href='modules/dashboard/index.php' style='padding:10px 20px; background:#3b82f6; color:white; border-radius:5px; text-decoration:none;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<b style='color:red;'>❌ Error al actualizar la base de datos:</b><br>";
    echo $e->getMessage();
    echo "<br><br><b>Sugerencia:</b> Asegúrate de que MySQL esté encendido en XAMPP e intenta ejecutar el SQL manualmente en phpMyAdmin.";
}
