<?php
$host = 'localhost';
$db_name = 'system_taller';
$username = 'root';
$password = '';

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
?>
