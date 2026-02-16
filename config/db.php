<?php
// Detect environment based on server name
$whitelist = ['127.0.0.1', '::1', 'localhost'];
$is_local = in_array($_SERVER['SERVER_NAME'], $whitelist);

if ($is_local) {
    // Local Credentials (XAMPP)
    $host = 'localhost';
    $db_name = 'system_taller';
    $username = 'root';
    $password = '';
} else {
    // Production Credentials (InfinityFree)
    $host = 'sql302.infinityfree.com';
    $db_name = 'if0_41173876_system_taller'; 
    $username = 'if0_41173876';
    $password = 'KNLEPk9w40tci';
}

// Define Base URL
if ($is_local) {
    define('BASE_URL', '/System_Taller/');
} else {
    define('BASE_URL', '/'); // Assumes app is in root of public_html/htdocs
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set PHP Timezone to Mexico City (GMT-6)
    date_default_timezone_set('America/Mexico_City');
    
    // Try to set MySQL timezone (works locally, might fail on InfinityFree)
    try {
        $pdo->exec("SET time_zone = '-06:00'");
    } catch (PDOException $e) {
        // If it fails (InfinityFree), we'll handle it in PHP
        // MySQL will use UTC, but we'll convert in queries
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to get current datetime in Mexico City timezone
function get_local_datetime() {
    return date('Y-m-d H:i:s');
}
// End of file (no closing tag to prevent whitespace)

