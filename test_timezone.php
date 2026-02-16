<?php
// test_timezone.php - Diagnostic script
require_once 'config/db.php';

echo "<h2>Timezone Diagnostic Report</h2>";
echo "<p>Current local time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Timezone: " . date_default_timezone_get() . "</p>";
echo "<hr>";

// Check MySQL timezone
$stmt = $pdo->query("SELECT @@session.time_zone as session_tz, @@global.time_zone as global_tz, NOW() as mysql_now");
$tz_info = $stmt->fetch();
echo "<h3>MySQL Timezone Info</h3>";
echo "<p>Session timezone: " . $tz_info['session_tz'] . "</p>";
echo "<p>Global timezone: " . $tz_info['global_tz'] . "</p>";
echo "<p>MySQL NOW(): " . $tz_info['mysql_now'] . "</p>";
echo "<hr>";

// Check table schema for clients table
$stmt = $pdo->query("SHOW CREATE TABLE clients");
$schema = $stmt->fetch();
echo "<h3>Clients Table Schema</h3>";
echo "<pre>" . htmlspecialchars($schema['Create Table']) . "</pre>";
echo "<hr>";

// Test get_local_datetime()
require_once 'config/db.php';
$local_time = get_local_datetime();
echo "<h3>get_local_datetime() Test</h3>";
echo "<p>Result: " . $local_time . "</p>";
echo "<hr>";

// Check most recent client
$stmt = $pdo->query("SELECT id, name, created_at FROM clients ORDER BY id DESC LIMIT 1");
$client = $stmt->fetch();
if ($client) {
    echo "<h3>Most Recent Client</h3>";
    echo "<p>ID: " . $client['id'] . "</p>";
    echo "<p>Name: " . htmlspecialchars($client['name']) . "</p>";
    echo "<p>created_at (from DB): " . $client['created_at'] . "</p>";
}
?>
