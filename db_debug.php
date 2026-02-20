<?php
// Standalone DB Debug
$host = 'localhost';
$db_name = 'system_taller';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $debug = [
        'roles' => $pdo->query("SELECT * FROM roles")->fetchAll(),
        'permissions' => $pdo->query("SELECT * FROM permissions")->fetchAll(),
        'role_permissions' => $pdo->query("SELECT rp.role_id, p.code, p.description FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id")->fetchAll(),
        'users_summary' => $pdo->query("SELECT role_id, COUNT(*) as count FROM users GROUP BY role_id")->fetchAll()
    ];

    file_put_contents('db_debug_dump.json', json_encode($debug, JSON_PRETTY_PRINT));
    echo "Dump created in db_debug_dump.json\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
