<?php
// test_insert_timezone.php - Demonstrates correct timezone handling
require_once 'config/db.php';
require_once 'includes/functions.php';

echo "<h2>Timezone Insert Test</h2>";
echo "<p><strong>Current PHP Time:</strong> " . date('Y-m-d H:i:s') . " (America/Mexico_City)</p>";
echo "<hr>";

// Test 1: WRONG WAY - Using NOW() in SQL
echo "<h3>❌ Test 1: Using NOW() (WRONG)</h3>";
$stmt = $pdo->prepare("INSERT INTO clients (name, tax_id, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute(['Test NOW()', '111', '555-0001', 'test1@test.com', 'Address 1']);
$id1 = $pdo->lastInsertId();

$stmt = $pdo->query("SELECT created_at FROM clients WHERE id = $id1");
$result1 = $stmt->fetch();
echo "<p>Inserted with NOW(): <strong style='color: red;'>" . $result1['created_at'] . "</strong></p>";
echo "<p>This is WRONG because MySQL is in UTC!</p>";
echo "<hr>";

// Test 2: CORRECT WAY - Using get_local_datetime()
echo "<h3>✅ Test 2: Using get_local_datetime() (CORRECT)</h3>";
$local_time = get_local_datetime();
echo "<p>get_local_datetime() returns: <strong>" . $local_time . "</strong></p>";

$stmt = $pdo->prepare("INSERT INTO clients (name, tax_id, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute(['Test get_local_datetime()', '222', '555-0002', 'test2@test.com', 'Address 2', $local_time]);
$id2 = $pdo->lastInsertId();

$stmt = $pdo->query("SELECT created_at FROM clients WHERE id = $id2");
$result2 = $stmt->fetch();
echo "<p>Inserted with get_local_datetime(): <strong style='color: green;'>" . $result2['created_at'] . "</strong></p>";
echo "<p>This is CORRECT - matches local time!</p>";
echo "<hr>";

// Comparison
echo "<h3>Comparison</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Method</th><th>Time Stored</th><th>Status</th></tr>";
echo "<tr><td>NOW()</td><td>" . $result1['created_at'] . "</td><td style='color: red;'>❌ Wrong (UTC)</td></tr>";
echo "<tr><td>get_local_datetime()</td><td>" . $result2['created_at'] . "</td><td style='color: green;'>✅ Correct (Local)</td></tr>";
echo "</table>";

// Cleanup
$pdo->exec("DELETE FROM clients WHERE id IN ($id1, $id2)");
echo "<p><em>Test records deleted.</em></p>";
?>
