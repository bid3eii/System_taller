<?php
// update_structure.php
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE service_orders ADD COLUMN service_type ENUM('service', 'warranty') DEFAULT 'service' AFTER client_id");
    echo "<h1>Base de datos actualizada</h1><p>Se ha agregado la columna 'service_type' a la tabla 'service_orders'.</p>";
    echo "<a href='modules/equipment/entry.php'>Volver a Entrada de Equipos</a>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "<h1>Aviso</h1><p>La columna ya existía. No se requieren cambios.</p>";
        echo "<a href='modules/equipment/entry.php'>Volver a Entrada de Equipos</a>";
    } else {
        echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
    }
}
