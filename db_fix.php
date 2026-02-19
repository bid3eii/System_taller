<?php
// Script de actualización de base de datos (One-time use)
require_once 'config/db.php';

try {
    // 1. Modificar tabla de clientes para permitir campos nulos
    $pdo->exec("ALTER TABLE clients MODIFY phone VARCHAR(20) NULL, MODIFY tax_id VARCHAR(50) NULL");
    echo "<h1>SITO: Base de datos actualizada correctamente.</h1>";
    echo "<p>Los campos de Teléfono y DNI ahora son opcionales.</p>";
    echo "<p><strong>IMPORTANTE:</strong> Por seguridad, elimina este archivo (db_fix.php) del servidor después de ejecutarlo.</p>";
} catch (Exception $e) {
    echo "<h1>ERROR:</h1><p>" . $e->getMessage() . "</p>";
}
?>
