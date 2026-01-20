<?php
// modules/settings/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Only Admin or verified access
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

// Check 'settings' permission
if (!can_access_module('settings', $pdo)) {
    die("Acceso denegado. No tienes permiso para acceder a la configuración del sistema.");
}

// ---------------------------------------------------------
// AUTO-SEED PERMISSIONS (Run once/check on load)
// ---------------------------------------------------------
$defined_modules = [
    'dashboard' => 'Acceso al Dashboard',
    'clients'   => 'Gestión de Clientes',
    'equipment' => 'Gestión de Equipos',
    'tools'     => 'Gestión de Herramientas',
    'services'  => 'Gestión de Servicios',
    'warranties'=> 'Gestión de Garantías',
    'new_warranty' => 'Registrar Nueva Garantía',
    'history'   => 'Ver Historial',
    'users'     => 'Gestión de Usuarios',
    'reports'   => 'Ver Reportes',
    'settings'  => 'Configuración del Sistema'
];

foreach ($defined_modules as $key => $desc) {
    $code = 'module_' . $key;
    // Check if exists
    $stmtCh = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
    $stmtCh->execute([$code]);
    $perm = $stmtCh->fetch();
    
    if (!$perm) {
        $stmtIn = $pdo->prepare("INSERT INTO permissions (code, description) VALUES (?, ?)");
        $stmtIn->execute([$code, $desc]);
        $perm_id = $pdo->lastInsertId();
    } else {
        $perm_id = $perm['id'];
    }

    // Auto-grant to Admin (Role ID 1) if not exists
    $stmtRP = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = ?");
    $stmtRP->execute([$perm_id]);
    if (!$stmtRP->fetch()) {
        $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, ?)")->execute([$perm_id]);
    }
}

// Ensure site_settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT
)");
// ---------------------------------------------------------

// Handle POST Actions
$success_msg = '';
$error_msg = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; // Default to general

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- UPDATE GENERAL SETTINGS (LOGO) ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_general_settings') {
        $active_tab = 'general';
        
        // Handle Logo Upload
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['system_logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $unique_name = 'logo_' . time() . '.' . $ext;
                $target_dir = '../../assets/uploads/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $target_file = $target_dir . $unique_name;
                
                if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $target_file)) {
                    // Update DB for Logo
                    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('system_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$unique_name, $unique_name]);
                } else {
                    $error_msg = "Error al mover el archivo subido.";
                }
            } else {
                $error_msg = "Formato de archivo no permitido. Use JPG, PNG o WEBP.";
            }
        }

        // Handle Text Fields
        $settings_to_update = [
            'company_name' => clean($_POST['company_name'] ?? ''),
            'company_email' => clean($_POST['company_email'] ?? ''),
            'company_address' => clean($_POST['company_address'] ?? ''),
            'company_phone' => clean($_POST['company_phone'] ?? ''),
            'print_footer_text' => clean($_POST['print_footer_text'] ?? '')
        ];

        foreach ($settings_to_update as $key => $val) {
             $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
             $stmt->execute([$key, $val, $val]);
        }

        if (empty($error_msg)) {
            $success_msg = "Configuración general actualizada correctamente.";
        }
    }

    // --- CREATE ROLE ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_role') {
        $active_tab = 'roles';
        $role_name = clean($_POST['role_name']);
        
        if (!empty($role_name)) {
            try {
                // Check if role exists
                $stmtCheckRole = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
                $stmtCheckRole->execute([$role_name]);
                if ($stmtCheckRole->fetchColumn() > 0) {
                     $error_msg = "El rol '$role_name' ya existe.";
                } else {
                    $stmtCR = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
                    $stmtCR->execute([$role_name]);
                    $success_msg = "Nuevo rol creado exitosamente.";
                }
            } catch (Exception $e) {
                $error_msg = "Error al crear rol: " . $e->getMessage();
            }
        } else {
            $error_msg = "El nombre del rol no puede estar vacío.";
        }
    }

    // --- UPDATE PERMISSIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
        $active_tab = 'modules';
        try {
            $pdo->beginTransaction();
            
            // Clear existing module permissions for NON-ADMIN roles (ID != 1) to avoid locking admin out via UI
            // We'll iterate through roles > 1
            $stmtRoles = $pdo->query("SELECT id FROM roles WHERE id > 1"); // Skip Admin
            $roles = $stmtRoles->fetchAll();
            
            // Get all module permission IDs
            $module_codes = array_map(function($k) { return 'module_'.$k; }, array_keys($defined_modules));
            $in_query = implode(',', array_fill(0, count($module_codes), '?'));
            $stmtPIDs = $pdo->prepare("SELECT id FROM permissions WHERE code IN ($in_query)");
            $stmtPIDs->execute($module_codes);
            $p_ids = $stmtPIDs->fetchAll(PDO::FETCH_COLUMN);
            
            if ($p_ids) {
                $p_ids_str = implode(',', $p_ids);
                foreach ($roles as $r) {
                    $pdo->exec("DELETE FROM role_permissions WHERE role_id = {$r['id']} AND permission_id IN ($p_ids_str)");
                }
            }

            // Insert new permissions
            if (isset($_POST['perms'])) {
                foreach ($_POST['perms'] as $r_id => $p_list) {
                    if ($r_id == 1) continue; // Skip Admin
                    
                    foreach ($p_list as $p_code) {
                        // Find ID
                        $stmtFind = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
                        $stmtFind->execute([$p_code]);
                        $pid = $stmtFind->fetchColumn();
                        
                        if ($pid) {
                            $stmtIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                            $stmtIns->execute([$r_id, $pid]);
                        }
                    }
                }
            }

            $pdo->commit();
            $success_msg = "Permisos actualizados correctamente.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error al actualizar permisos: " . $e->getMessage();
        }
    }

    // --- SYSTEM RESTORE ---
    if (isset($_POST['action']) && $_POST['action'] === 'system_restore') {
        $active_tab = 'restore';
        $admin_pass = $_POST['admin_password'] ?? '';
        
        // Verify Admin Password
        $stmtUser = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $currentUser = $stmtUser->fetch();
        
        if ($currentUser && password_verify($admin_pass, $currentUser['password_hash'])) {
            try {
                $pdo->beginTransaction();
                
                // Truncate Tables
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("TRUNCATE TABLE service_order_history");
                $pdo->exec("TRUNCATE TABLE warranties");
                $pdo->exec("TRUNCATE TABLE service_orders");
                $pdo->exec("TRUNCATE TABLE tool_loans");
                $pdo->exec("TRUNCATE TABLE tools");
                $pdo->exec("TRUNCATE TABLE equipments");
                $pdo->exec("TRUNCATE TABLE clients");
                $pdo->exec("TRUNCATE TABLE audit_logs");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $pdo->commit();
                $success_msg = "Sistema restaurado correctamente. Todos los datos han sido eliminados.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error crítico al restaurar: " . $e->getMessage();
            }
        } else {
            $error_msg = "Contraseña incorrecta. No se realizaron cambios.";
        }
    }
}

// Fetch Data for View
// Get All Site Settings
$settings = [];
$stmtAll = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
while ($row = $stmtAll->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$system_logo = $settings['system_logo'] ?? '';
$company_name = $settings['company_name'] ?? 'SYSTEM TALLER';
$company_email = $settings['company_email'] ?? 'contacto@taller.com';
$company_address = $settings['company_address'] ?? 'Av. Principal 123, Ciudad';
$company_phone = $settings['company_phone'] ?? '(555) 123-4567';
$print_footer_text = $settings['print_footer_text'] ?? 'Declaración de Conformidad: El cliente declara recibir el equipo a su entera satisfacción, habiendo verificado su funcionamiento. La empresa no se hace responsable por fallas posteriores no relacionadas con el servicio efectuado.';

$users_roles_all = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll(); // All roles for display
$users_roles_edit = array_filter($users_roles_all, function($r) { return $r['id'] > 1; }); // Skip Admin for permissions editing

$permissions_list = $pdo->query("SELECT * FROM permissions WHERE code LIKE 'module_%'")->fetchAll();

// Pre-fetch current permissions map [role_id][code] = true
$current_perms = [];
$stmtCP = $pdo->query("
    SELECT rp.role_id, p.code 
    FROM role_permissions rp 
    JOIN permissions p ON rp.permission_id = p.id
");
while ($row = $stmtCP->fetch()) {
    $current_perms[$row['role_id']][$row['code']] = true;
}

// Fetch Users (moved from users/index.php)
$stmtUsers = $pdo->prepare("
    SELECT 
        u.id, u.username, u.email, u.status, u.created_at,
        r.name as role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC
");
$stmtUsers->execute();
$users = $stmtUsers->fetchAll();

$page_title = 'Configuración';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Configuración</h1>
        <p class="text-muted">Gestión del sistema, usuarios y permisos.</p>
    </div>

    <?php if ($success_msg): ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <i class="ph ph-check-circle"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <i class="ph ph-warning"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Tabs Header -->
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); overflow-x: auto;">
        <button onclick="switchTab('general')" id="tab-btn-general" class="tab-btn <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
            <i class="ph ph-gear"></i> General
        </button>
        <button onclick="switchTab('roles')" id="tab-btn-roles" class="tab-btn <?php echo $active_tab == 'roles' ? 'active' : ''; ?>">
            <i class="ph ph-identification-badge"></i> Roles
        </button>
        <button onclick="switchTab('modules')" id="tab-btn-modules" class="tab-btn <?php echo $active_tab == 'modules' ? 'active' : ''; ?>">
            <i class="ph ph-squares-four"></i> Control de Módulos
        </button>
        <button onclick="switchTab('users')" id="tab-btn-users" class="tab-btn <?php echo $active_tab == 'users' ? 'active' : ''; ?>">
            <i class="ph ph-users"></i> Usuarios
        </button>
        <button onclick="switchTab('restore')" id="tab-btn-restore" class="tab-btn <?php echo $active_tab == 'restore' ? 'active' : ''; ?>">
            <i class="ph ph-trash"></i> Restaurar Sistema
        </button>
    </div>

    <div id="tab-general" style="display: <?php echo $active_tab == 'general' ? 'block' : 'none'; ?>;">
        <div class="card" style="max-width: 100%;">
            <h3 class="mb-4">Configuración General</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_general_settings">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem;">
                    <div>
                        <h4 class="mb-3" style="color: var(--primary); border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">Información de la Empresa</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Nombre de la Empresa</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Ej. Mi Taller Pro">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="company_address" class="form-control" value="<?php echo htmlspecialchars($company_address); ?>" placeholder="Ej. Calle 123...">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($company_phone); ?>" placeholder="Ej. (555) 000-0000">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email de Contacto</label>
                                <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($company_email); ?>" placeholder="contacto@empresa.com">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="mb-3" style="color: var(--primary); border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">Personalización de Impresión</h4>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 0.5rem;">Logo del Sistema</label>
                            
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <?php if($system_logo && file_exists("../../assets/uploads/" . $system_logo)): ?>
                                    <div style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; background: #fff;">
                                        <img src="../../assets/uploads/<?php echo $system_logo; ?>" alt="Logo Actual" style="max-height: 50px; display: block;">
                                    </div>
                                <?php endif; ?>
                                <div style="flex-grow: 1;">
                                    <input type="file" name="system_logo" class="form-control" accept="image/*">
                                    <p class="text-muted" style="font-size: 0.8rem; margin-top: 0.25rem;">Recomendado: 300px ancho. PNG/JPG.</p>
                                </div>
                            </div>
                        </div>

                         <div class="form-group">
                            <label class="form-label">Texto Legal / Garantía (Pie de Página)</label>
                            <textarea name="print_footer_text" class="form-control" rows="5" style="font-size: 0.85rem; resize: vertical;"><?php echo htmlspecialchars($print_footer_text); ?></textarea>
                            <p class="text-muted" style="font-size: 0.8rem; margin-top: 0.5rem;">Aparecerá al final de los comprobantes impresos.</p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 1rem; text-align: right;">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TAB: ROLES -->
<div id="tab-roles" style="display: <?php echo $active_tab == 'roles' ? 'block' : 'none'; ?>;">
    <div class="card" style="margin-bottom: 2rem;">
            <h3><i class="ph ph-plus-circle"></i> Crear Nuevo Rol</h3>
            <p class="text-muted mb-4">Agregue nuevos roles al sistema para asignar permisos específicos.</p>
            <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="action" value="create_role">
                <div class="form-group" style="margin-bottom: 0; flex-grow: 1; max-width: 400px;">
                    <label class="form-label">Nombre del Rol</label>
                    <input type="text" name="role_name" class="form-control" placeholder="Ej. Supervisor, Técnico..." required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-plus"></i> Guardar Rol
                </button>
            </form>
    </div>
</div>

<!-- TAB: MODULES -->
<div id="tab-modules" style="display: <?php echo $active_tab == 'modules' ? 'block' : 'none'; ?>;">
    <div class="card">
            <h3 class="mb-4">Matriz de Acceso a Módulos</h3>
            <p class="text-muted mb-4">Seleccione qué módulos puede ver cada rol. El Administrador siempre tiene acceso total.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_permissions">
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <th style="text-align: left; padding: 1rem;">Módulo</th>
                                <?php foreach ($users_roles_edit as $role): ?>
                                    <th style="text-align: center; padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($role['name']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defined_modules as $mod_key => $mod_desc): ?>
                            <?php $p_code = 'module_' . $mod_key; ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($mod_desc); ?></strong>
                                </td>
                                <?php foreach ($users_roles_edit as $role): ?>
                                    <td style="text-align: center; padding: 1rem;">
                                        <label class="custom-checkbox">
                                            <input type="checkbox" name="perms[<?php echo $role['id']; ?>][]" value="<?php echo $p_code; ?>"
                                                <?php echo isset($current_perms[$role['id']][$p_code]) ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 2rem; text-align: right;">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk"></i> Guardar Cambios de Permisos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB: USERS -->
    <div id="tab-users" style="display: <?php echo $active_tab == 'users' ? 'block' : 'none'; ?>;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h3>Usuarios del Sistema</h3>
                <p class="text-muted">Administración de accesos y roles de personal.</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <div class="input-group" style="width: 300px;">
                     <input type="text" id="searchInput" class="form-control" placeholder="Buscar usuario, email o rol...">
                     <i class="ph ph-magnifying-glass input-icon"></i>
                </div>
                 <a href="../users/add.php" class="btn btn-primary">
                    <i class="ph ph-user-plus"></i> Nuevo Usuario
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-container">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Fecha Registro</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $user['email'] ? htmlspecialchars($user['email']) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--bg-hover); color: var(--text-primary);">
                                        <?php echo htmlspecialchars($user['role_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $statusConfig = [
                                            'active' => ['color' => 'green', 'label' => 'Activo'],
                                            'inactive' => ['color' => 'gray', 'label' => 'Inactivo']
                                        ];
                                        $config = $statusConfig[$user['status']] ?? ['color' => 'gray', 'label' => $user['status']];
                                    ?>
                                    <span class="status-badge status-<?php echo $config['color']; ?>">
                                        <?php echo $config['label']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td style="text-align: center;">
                                    <div class="table-actions">
                                        <a href="../users/edit.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Editar">
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 3rem;">
                                    <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                        <i class="ph ph-users" style="font-size: 3rem;"></i>
                                    </div>
                                    <h3 style="margin-bottom: 0.5rem;">No hay usuarios registrados</h3>
                                    <p class="text-muted">Comienza agregando nuevos usuarios al sistema.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: RESTORE -->
    <div id="tab-restore" style="display: <?php echo $active_tab == 'restore' ? 'block' : 'none'; ?>;">
        <div class="card" style="border: 1px solid var(--danger);">
            <div style="display: flex; gap: 1.5rem; align-items: flex-start;">
                <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 12px; color: var(--danger);">
                    <i class="ph ph-warning-octagon" style="font-size: 2rem;"></i>
                </div>
                <div>
                    <h3 style="color: var(--danger);">Zona de Peligro: Restaurar de Fábrica</h3>
                    <p class="text-muted" style="margin-bottom: 1rem;">
                        Esta acción <strong>ELIMINARÁ PERMANENTEMENTE</strong> todos los datos operativos del sistema, incluyendo:
                    </p>
                    <ul style="color: var(--text-secondary); margin-bottom: 1.5rem; padding-left: 1.5rem;">
                        <li>Todos los clientes y sus datos.</li>
                        <li>Todos los equipos registrados.</li>
                        <li>Todas las órdenes de servicio y garantías.</li>
                        <li>El historial completo de reparaciones.</li>
                        <li>Préstamos de herramientas y registros de auditoría.</li>
                    </ul>
                    <p style="margin-bottom: 1.5rem;">
                        <strong>Nota:</strong> Los Usuarios, Roles y Configuraciones de Módulos NO serán eliminados.
                    </p>

                    <form method="POST" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción es irreversible.');">
                        <input type="hidden" name="action" value="system_restore">
                        
                        <div class="form-group" style="max-width: 400px; margin-bottom: 1.5rem;">
                            <label class="form-label">Contraseña de Administrador (para confirmar)</label>
                            <input type="password" name="admin_password" class="form-control" placeholder="Ingresa tu contraseña actual" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger" style="background-color: var(--danger); color: white;">
                            <i class="ph ph-trash"></i> Confirmar y Eliminar Todo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tab Styles */
.tab-btn {
    background: transparent;
    border: none;
    padding: 0.75rem 1.5rem;
    color: var(--text-secondary);
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.tab-btn:hover {
    color: var(--text-primary);
}

.tab-btn.active {
    color: var(--primary-500);
    border-bottom-color: var(--primary-500);
}

/* Checkbox Style */
.custom-checkbox {
    display: inline-block;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
}
.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.checkmark {
    position: absolute;
    top: 0; left: 0;
    height: 20px; width: 20px;
    background-color: var(--bg-hover);
    border: 1px solid var(--border-color);
    border-radius: 4px;
}
.custom-checkbox input:checked ~ .checkmark {
    background-color: var(--primary-500);
    border-color: var(--primary-500);
}
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}
.custom-checkbox input:checked ~ .checkmark:after {
    display: block;
}
.custom-checkbox .checkmark:after {
    left: 7px;
    top: 3px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
</style>

<script>
function switchTab(tabId) {
    // Hide all
    document.getElementById('tab-general').style.display = 'none';
    // Hide all contents
    ['general', 'roles', 'modules', 'users', 'restore'].forEach(id => {
        const el = document.getElementById('tab-' + id);
        if (el) el.style.display = 'none';
        const btn = document.getElementById('tab-btn-' + id);
        if (btn) btn.classList.remove('active');
    });
    
    // Show target
    const target = document.getElementById('tab-' + tabId);
    if (target) target.style.display = 'block';
    
    const targetBtn = document.getElementById('tab-btn-' + tabId);
    if (targetBtn) targetBtn.classList.add('active');
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
}

// Check URL params on load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['general', 'roles', 'modules', 'users', 'restore'].includes(tab)) {
        switchTab(tab);
    }
});

// Search Users
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#usersTable tbody tr');

    tableRows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if(text.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>
