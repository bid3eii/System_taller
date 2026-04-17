<?php
require_once __DIR__ . '/../../config/db.php';

$modules = [
    'dashboard' => 'Acceso al Dashboard',
    'clients' => 'Gestión de Clientes',
    'equipment' => 'Gestión de Equipos',
    'tools' => 'Gestión de Herramientas',
    'services' => 'Gestión de Servicios',
    'warranties' => 'Gestión de Garantías',
    'history' => 'Ver Historial',
    'users' => 'Gestión de Usuarios',
    'reports' => 'Ver Reportes',
    'settings' => 'Configuración del Sistema',
    'edit_entries' => 'Permitir Editar Entradas de Servicios/Garantías',
    'master_visit_control' => 'Control Maestro de Visitas (Supervisión de Técnicos)'
];

echo "Checking permissions...\n";

foreach ($modules as $key => $desc) {
    $code = 'module_' . $key;
    
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
    $stmt->execute([$code]);
    
    if (!$stmt->fetch()) {
        echo "Creating permission: $code\n";
        $stmtInsert = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");
        $stmtInsert->execute([$code, $desc]);
    } else {
        echo "Permission exists: $code\n";
    }
}

echo "Done.\n";
?>
