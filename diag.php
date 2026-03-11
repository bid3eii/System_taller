<?php
// diag.php - simple diagnostics to see real PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostics Running</h1>";

try {
    require_once 'config/db.php';
    echo "<p style='color:green'>DB Config loaded successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>DB Config Error: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color:red'>DB Config Fatal Error: " . $e->getMessage() . "</p>";
}

try {
    require_once 'includes/functions.php';
    echo "<p style='color:green'>Functions loaded successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Functions Error: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color:red'>Functions Fatal Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Session Test</h2>";
session_start();
echo "<p>Session started</p>";

echo "<h2>File Includes Test</h2>";
$files_to_test = [
    'modules/auth/login.php',
];

foreach ($files_to_test as $file) {
    if (file_exists($file)) {
        echo "<p>File $file exists.</p>";
    } else {
        echo "<p style='color:red'>File $file DOES NOT EXIST.</p>";
    }
}

echo "<h2>Execution Test</h2>";
echo "<p>Loading login.php directly...</p>";
try {
    require_once 'modules/auth/login.php';
} catch (Throwable $t) {
    echo "<div style='color:red; border:1px solid red; padding:10px;'>";
    echo "<b>Fatal Error caught in login.php:</b><br>";
    echo $t->getMessage() . "<br>";
    echo "In " . $t->getFile() . " on line " . $t->getLine();
    echo "</div>";
}
?>
