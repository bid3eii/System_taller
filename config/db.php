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
    
    // Set Timezone
    date_default_timezone_set('America/Mexico_City');
    $pdo->exec("SET time_zone = '-06:00'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
// End of file (no closing tag to prevent whitespace)

