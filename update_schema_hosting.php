<?php
// update_schema_hosting.php
// Archivo para actualizar la tabla service_orders en el hosting
// Sube este archivo a public_html/ o a la carpeta principal de tu ERP y ejecutalo desde el navegador.

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Requerir el archivo de conexión a la BD
require_once 'config/db.php';

echo "<div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: auto;'>";
echo "<h2 style='color: #2b6cb0;'>Actualizando Estructura de service_orders...</h2>";

try {
    $updated = false;

    // payment_status
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'payment_status'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN payment_status enum('pendiente','pagado') NOT NULL DEFAULT 'pendiente' AFTER status");
        echo "<p>✅ Columna <b>'payment_status'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'payment_status' ya existe.</p>";
    }

    // invoice_number
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'invoice_number'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN invoice_number varchar(50) DEFAULT NULL AFTER exit_doc_number");
        echo "<p>✅ Columna <b>'invoice_number'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'invoice_number' ya existe.</p>";
    }

    // owner_name
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'owner_name'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN owner_name varchar(255) DEFAULT NULL");
        echo "<p>✅ Columna <b>'owner_name'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'owner_name' ya existe.</p>";
    }

    // contact_name
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'contact_name'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN contact_name varchar(100) DEFAULT NULL");
        echo "<p>✅ Columna <b>'contact_name'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'contact_name' ya existe.</p>";
    }

    // service_type
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'service_type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN service_type enum('service','warranty') DEFAULT 'service'");
        echo "<p>✅ Columna <b>'service_type'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'service_type' ya existe.</p>";
    }

    // diagnosis_procedure
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'diagnosis_procedure'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN diagnosis_procedure text DEFAULT NULL");
        echo "<p>✅ Columna <b>'diagnosis_procedure'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'diagnosis_procedure' ya existe.</p>";
    }

    // diagnosis_conclusion
    $stmt = $pdo->query("SHOW COLUMNS FROM service_orders LIKE 'diagnosis_conclusion'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE service_orders ADD COLUMN diagnosis_conclusion text DEFAULT NULL");
        echo "<p>✅ Columna <b>'diagnosis_conclusion'</b> agregada.</p>";
        $updated = true;
    } else {
        echo "<p style='color: #718096;'>✓ La columna 'diagnosis_conclusion' ya existe.</p>";
    }

    echo "<hr>";
    if (!$updated) {
        echo "<h3 style='color: #38a169;'>¡Completado! La tabla ya estaba 100% actualizada.</h3>";
    } else {
        echo "<h3 style='color: #38a169;'>¡Actualización completada con éxito!</h3>";
    }

    echo "<p style='font-size: 14px; color: #e53e3e;'><strong>Atención:</strong> Por seguridad, por favor ELIMINA este archivo de tu servidor ('update_schema_hosting.php') después de confirmar que los listados y el sistema de recepción del taller funcionan correctamente.</p>";

} catch (PDOException $e) {
    echo "<div style='background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; border-left: 5px solid #ef4444;'>";
    echo "<h3>Error Inesperado de Base de Datos:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</div>";
?>