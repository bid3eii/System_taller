<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$debug = [
    'session' => $_SESSION,
    'permissions_check' => []
];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $role_id = $_SESSION['role_id'] ?? null;
    
    // Check role details
    if ($role_id) {
        $stmtRole = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmtRole->execute([$role_id]);
        $debug['role_info'] = $stmtRole->fetch(PDO::FETCH_ASSOC);
        
        // Check permissions for this role
        $stmtPerms = $pdo->prepare("
            SELECT p.code, p.description 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ?
        ");
        $stmtPerms->execute([$role_id]);
        $debug['role_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check some specific modules
    $modules_to_test = ['dashboard', 'clients', 'equipment', 'services', 'warranties', 'new_warranty'];
    foreach ($modules_to_test as $mod) {
        $debug['permissions_check'][$mod] = [
            'can_access' => can_access_module($mod, $pdo),
            'permission_code' => 'module_' . $mod
        ];
    }
} else {
    $debug['error'] = "No session active";
}

// Add system info
$debug['system_info'] = [
    'roles' => $pdo->query("SELECT * FROM roles")->fetchAll(),
    'all_permissions' => $pdo->query("SELECT * FROM permissions WHERE code LIKE 'module_%'")->fetchAll(),
    'role_permissions_map' => $pdo->query("SELECT rp.role_id, p.code FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id")->fetchAll()
];

$current_debug = json_encode($debug, JSON_PRETTY_PRINT);
file_put_contents('debug_log.json', $current_debug);
echo "Debug data written to debug_log.json\n";
echo $current_debug;
?>
