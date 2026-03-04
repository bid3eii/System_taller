<?php
// update_comisiones_hosting.php
// Sube este archivo a la raíz de tu proyecto en el hosting y visítalo desde el navegador
// Ejemplo: https://tusitio.com/update_comisiones_hosting.php

require_once 'config/db.php';

echo "<h2>Actualización de Base de Datos - Módulo Comisiones</h2>";

try {
    $pdo->beginTransaction();

    // 1. Crear tabla comisiones si no existe
    $sql_create_table = "
    CREATE TABLE IF NOT EXISTS `comisiones` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `fecha_servicio` date NOT NULL,
      `fecha_facturacion` date DEFAULT NULL,
      `cliente` varchar(255) NOT NULL,
      `servicio` varchar(255) NOT NULL,
      `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
      `tipo` enum('SERVICIO','PROYECTO') NOT NULL,
      `lugar` varchar(255) DEFAULT NULL,
      `factura` varchar(100) DEFAULT NULL,
      `vendedor` varchar(255) NOT NULL,
      `caso` varchar(100) NOT NULL,
      `estado` enum('PENDIENTE','PAGADA') NOT NULL DEFAULT 'PENDIENTE',
      `tech_id` int(11) NOT NULL,
      `reference_id` int(11) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $pdo->exec($sql_create_table);
    echo "<p>&#10004; Tabla <b>comisiones</b> verificada/creada exitosamente.</p>";

    // 2. Insertar permisos granulares si no existen
    $permisos = [
        ['module_comisiones', 'Acceso al módulo de Comisiones'],
        ['module_comisiones_add', 'Crear Comisión Manual'],
        ['module_comisiones_edit', 'Editar Comisión'],
        ['module_comisiones_delete', 'Eliminar Comisión']
    ];

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE code = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");

    foreach ($permisos as $p) {
        $stmtCheck->execute([$p[0]]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtInsert->execute([$p[0], $p[1]]);
            echo "<p>&#10004; Permiso <b>{$p[0]}</b> agregado a la base de datos.</p>";
        } else {
            echo "<p>&#10004; Permiso <b>{$p[0]}</b> ya existía.</p>";
        }
    }

    $pdo->commit();
    echo "<h3 style='color: green;'>¡Actualización completada con éxito!</h3>";
    echo "<p>Ya puedes eliminar este archivo de tu hosting por seguridad.</p>";
    echo "<p><a href='index.php'>Volver al sistema</a></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h3 style='color: red;'>Error durante la actualización:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>