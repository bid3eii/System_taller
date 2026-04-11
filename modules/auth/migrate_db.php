<?php
// MIGRATION SCRIPT - Adds missing columns to production database
// DELETE THIS FILE AFTER RUNNING
ini_set('session.gc_probability', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__, 2) . '/config/db.php';

echo "<h2>Migración de Base de Datos</h2><pre>";

$migrations = [
    [
        'desc' => 'Agregar columna "reason" a audit_logs',
        'check' => "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'reason'",
        'sql' => "ALTER TABLE audit_logs ADD COLUMN reason TEXT NULL AFTER action"
    ]
];

foreach ($migrations as $m) {
    echo $m['desc'] . "... ";
    try {
        $exists = $pdo->query($m['check'])->fetchColumn();
        if ($exists > 0) {
            echo "YA EXISTE ✓ (saltando)\n";
        } else {
            $pdo->exec($m['sql']);
            echo "APLICADO ✓\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n--- MIGRACIÓN COMPLETA ---\n";
echo "\nAhora puedes intentar hacer login normalmente.";
echo "\n⚠️ ELIMINA ESTE ARCHIVO después de usarlo.";
echo "</pre>";
?>
