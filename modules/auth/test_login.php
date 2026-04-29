<?php
// DIAGNOSTIC FILE - DELETE AFTER DEBUGGING
ini_set('session.gc_probability', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico del Sistema</h2>";
echo "<pre>";

// Step 1: DB Connection
echo "1. Conectando a BD... ";
try {
    require_once dirname(__DIR__, 2) . '/config/db.php';
    echo "OK ✓\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    die("</pre>");
}

// Step 2: Session
echo "2. Iniciando sesión... ";
try {
    @session_start(['gc_probability' => 0]);
    echo "OK ✓\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 3: Functions
echo "3. Cargando funciones... ";
try {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
    echo "OK ✓\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 4: Query users table
echo "4. Consultando tabla users... ";
try {
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.status = 'active' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    echo "OK ✓ (Usuario encontrado: " . ($user['username'] ?? 'ninguno') . ")\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 5: Check role_permissions table
echo "5. Consultando tabla role_permissions... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM role_permissions");
    echo "OK ✓ (" . $stmt->fetchColumn() . " registros)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 6: Check permissions table
echo "6. Consultando tabla permissions... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
    echo "OK ✓ (" . $stmt->fetchColumn() . " registros)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 7: Check user_custom_modules table
echo "7. Consultando tabla user_custom_modules... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_custom_modules");
    echo "OK ✓ (" . $stmt->fetchColumn() . " registros)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 8: Check audit_logs table
echo "8. Consultando tabla audit_logs... ";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    echo "OK ✓ (" . $stmt->fetchColumn() . " registros)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 9: Test log_audit function
echo "9. Probando función log_audit... ";
try {
    $_SESSION['user_id'] = $user['id'] ?? 1;
    log_audit($pdo, 'users', 1, 'UPDATE', null, ['event' => 'test_diagnostic']);
    echo "OK ✓\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Step 10: Check BASE_URL
echo "10. BASE_URL = '" . (defined('BASE_URL') ? BASE_URL : 'NO DEFINIDO') . "'\n";

// Step 11: PHP Version
echo "11. PHP Version = " . phpversion() . "\n";

// Step 12: Session save path
echo "12. Session save path = " . session_save_path() . "\n";
echo "    session.gc_probability = " . ini_get('session.gc_probability') . "\n";

echo "\n--- FIN DEL DIAGNÓSTICO ---\n";
echo "</pre>";
?>
