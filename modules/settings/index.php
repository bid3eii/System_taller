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
    'clients_delete' => 'Eliminar Clientes',
    'equipment' => 'Gestión de Equipos',
    'equipment_entry' => 'Registrar Entrada',
    'equipment_exit' => 'Registrar Salida',
    'tools'     => 'Gestión de Herramientas',
    'services'  => 'Gestión de Servicios',
    'warranties'=> 'Gestión de Garantías',
    'new_warranty' => 'Registrar Nueva Garantía',
    'history'   => 'Ver Historial',
    'users'     => 'Gestión de Usuarios',
    'users_delete' => 'Eliminar Usuarios',
    'reports'   => 'Ver Reportes',
    'settings'  => 'Configuración del Sistema',
    'settings_general' => 'Config. General',
    'settings_roles'   => 'Gestión de Roles',
    'settings_modules' => 'Control de Módulos',
    'settings_users'   => 'Gestión de Usuarios (Admin)',
    'settings_restore' => 'Restaurar Sistema',
    're_enter_workshop' => 'Reingresar a Taller',
    'view_all_entries' => 'Ver todos los equipos ingresados (sin estar asignados)',
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

    // Auto-grant 'view_all_entries' to Reception (Role ID 4) by default
    if ($key === 'view_all_entries') {
        $stmtRP4 = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = 4 AND permission_id = ?");
        $stmtRP4->execute([$perm_id]);
        if (!$stmtRP4->fetch()) {
            $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (4, ?)")->execute([$perm_id]);
        }
    }
}

// Ensure site_settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT
)");

// Ensure user_custom_modules table exists for per-user overrides
$pdo->exec("CREATE TABLE IF NOT EXISTS user_custom_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_module (user_id, module_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
// ---------------------------------------------------------

// Handle POST Actions
$success_msg = '';
$error_msg = '';

if (isset($_GET['success'])) {
    $success_msg = "Cambios guardados correctamente.";
}
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; // Default to general

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- BACKUP DATABASE ---
    if ($action === 'backup_db') {
        if (!can_access_module('settings_restore', $pdo) && !can_access_module('settings', $pdo)) {
            die("Acceso denegado.");
        }

        $filename = 'system_taller_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $dumpPath = tempnam(sys_get_temp_dir(), 'sql_dump');
        
        // Command for XAMPP Windows Default
        $command = 'C:\xampp\mysql\bin\mysqldump --user=root --host=localhost system_taller > "' . $dumpPath . '"';
        
        // Execute
        system($command, $returnVar);

        if ($returnVar === 0 && file_exists($dumpPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($dumpPath));
            readfile($dumpPath);
            unlink($dumpPath);
            exit;
        } else {
            header("Location: index.php?tab=restore&error=backup_failed");
            exit;
        }
    }

    // --- RESTORE DATABASE ---
    if ($action === 'restore_db') {
         if (!can_access_module('settings_restore', $pdo) && !can_access_module('settings', $pdo)) {
            die("Acceso denegado.");
        }

        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
            $tmpName = $_FILES['backup_file']['tmp_name'];
            
            // Command for XAMPP Windows Default (Import)
            $command = 'C:\xampp\mysql\bin\mysql --user=root --host=localhost system_taller < "' . $tmpName . '"';
            
            system($command, $returnVar);

            if ($returnVar === 0) {
                 header("Location: index.php?tab=restore&msg=restored");
                 exit;
            } else {
                 header("Location: index.php?tab=restore&error=restore_failed");
                 exit;
            }
        } else {
             header("Location: index.php?tab=restore&error=upload_error");
             exit;
        }
    }
    
    // --- UPDATE GENERAL SETTINGS (LOGO) ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_general_settings') {
        if (!can_access_module('settings_general', $pdo) && !can_access_module('settings', $pdo)) die("Acceso denegado.");
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
            'print_footer_text' => clean($_POST['print_footer_text'] ?? ''),
            'print_entry_text' => clean($_POST['print_entry_text'] ?? '')
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
        if (!can_access_module('settings_roles', $pdo) && !can_access_module('settings', $pdo)) die("Acceso denegado.");
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


    // --- UPDATE PERMISSIONS (ROLES) ---
    // --- UPDATE PERMISSIONS (ROLES - SINGLE or ALL) ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
        if (!can_access_module('settings_modules', $pdo) && !can_access_module('settings', $pdo)) die("Acceso denegado.");
        $active_tab = 'modules';
        try {
            $pdo->beginTransaction();
            
            // Determine Target Role
            // If role_id is passed, updating single role.
            $target_r_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
            
            // If target_r_id provided, we update ONLY that role
            if ($target_r_id > 1) { // Never update Admin (1)
                
                // 1. Clear existing permissions for this role
                $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$target_r_id]);
                
                // 2. Insert new
                if (isset($_POST['perms'])) {
                    foreach ($_POST['perms'] as $p_code => $val) {
                        if ($val == '1') { // If allowed
                            // Find ID
                            $stmtFind = $pdo->prepare("SELECT id FROM permissions WHERE code = ?");
                            $stmtFind->execute([$p_code]);
                            $pid = $stmtFind->fetchColumn();
                            
                            if ($pid) {
                                $stmtIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                                $stmtIns->execute([$target_r_id, $pid]);
                            }
                        }
                    }
                }
                
                $success_msg = "Permisos del rol actualizados correctamente.";
                $url_params = "&subtab=roles&target_role_id=$target_r_id";
                
            } else {
                // Fallback implementation for "All Roles" (Legacy support if needed, but we are moving to single role editing)
                // For safety, if no role_id, we do nothing or throw error in this new design.
                // But to be safe, let's just warn or do nothing, or keep old logic?
                // Given the redesign, we should always have a role_id.
                $error_msg = "No se especificó un rol válido para actualizar.";
                $url_params = "&subtab=roles";
            }

            $pdo->commit();
            if(!isset($error_msg)) {
                header("Location: ?tab=modules$url_params&success=1");
                exit;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error al actualizar permisos: " . $e->getMessage();
        }
    }

    // --- UPDATE USER SPECIFIC PERMISSIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'update_user_permissions') {
        if (!can_access_module('settings_modules', $pdo) && !can_access_module('settings', $pdo)) die("Acceso denegado.");
        $active_tab = 'modules';
        $subtab = 'users'; // Stay on users tab
        $target_user_id = intval($_POST['user_id']);
        
        try {
            $pdo->beginTransaction();
            
            if (isset($_POST['perms'])) {
                foreach ($_POST['perms'] as $module => $val) {
                    // $val can be '1' (allow), '0' (deny), or 'inherit'
                    if ($val === 'inherit') {
                        // Remove override
                        $stmt = $pdo->prepare("DELETE FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
                        $stmt->execute([$target_user_id, $module]);
                    } else {
                        // Upsert Override
                        $isEnabled = intval($val);
                        $stmt = $pdo->prepare("
                            INSERT INTO user_custom_modules (user_id, module_name, is_enabled) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE is_enabled = ?
                        ");
                        $stmt->execute([$target_user_id, $module, $isEnabled, $isEnabled]);
                    }
                }
            }
            
            $pdo->commit();
            $success_msg = "Excepciones de usuario actualizadas correctamente.";
            // ensure URL keeps params
            header("Location: ?tab=modules&subtab=users&target_user_id=$target_user_id&success=1");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error al guardar excepciones: " . $e->getMessage();
        }
    }

    // --- DELETE USER ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $active_tab = 'users';
        $user_id_to_delete = intval($_POST['user_id']);

        // Check permission
        if (!can_access_module('users_delete', $pdo)) {
             $error_msg = "No tienes permiso para eliminar usuarios.";
        } elseif ($user_id_to_delete == 1) {
             $error_msg = "No se puede eliminar al SuperAdmin.";
        } elseif ($user_id_to_delete == $_SESSION['user_id']) {
             $error_msg = "No puedes eliminar tu propia cuenta.";
        } else {
             try {
                // Check if user exists
                $stmtChk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmtChk->execute([$user_id_to_delete]);
                if ($stmtChk->fetch()) {
                    // Soft Delete: Mark as inactive to preserve history
                    $stmtDel = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    if ($stmtDel->execute([$user_id_to_delete])) {
                        $success_msg = "Usuario desactivado correctamente. (El historial se conserva).";
                    } else {
                        $error_msg = "Error al desactivar el usuario.";
                    }
                } else {
                     $error_msg = "El usuario no existe.";
                }
             } catch (PDOException $e) {
                 $error_msg = "Error de base de datos: " . $e->getMessage();
             }
        }
    }



    // --- SYSTEM RESTORE ---
    if (isset($_POST['action']) && $_POST['action'] === 'system_restore') {
        if (!can_access_module('settings_restore', $pdo) && !can_access_module('settings', $pdo)) die("Acceso denegado.");
        $active_tab = 'restore';
        $admin_pass = $_POST['admin_password'] ?? '';
        
        // Verify Admin Password
        $stmtUser = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $currentUser = $stmtUser->fetch();
        
        if ($currentUser && password_verify($admin_pass, $currentUser['password_hash'])) {
            try {
                // Truncate Tables (TRUNCATE causes implicit commit in MySQL, so no Transaction used)
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->exec("TRUNCATE TABLE service_order_history");
                $pdo->exec("TRUNCATE TABLE warranties");
                $pdo->exec("TRUNCATE TABLE service_orders");
                $pdo->exec("TRUNCATE TABLE tool_assignments");
                $pdo->exec("TRUNCATE TABLE tool_assignment_items");
                $pdo->exec("TRUNCATE TABLE tool_loans");
                $pdo->exec("TRUNCATE TABLE tools");
                $pdo->exec("TRUNCATE TABLE equipments");
                $pdo->exec("TRUNCATE TABLE clients");
                $pdo->exec("TRUNCATE TABLE audit_logs");
                
                // Reset Sequences
                $pdo->exec("UPDATE system_sequences SET current_value = 0");
                
                // Delete all users except SuperAdmin (ID 1)
                $pdo->exec("DELETE FROM users WHERE id != 1");
                $pdo->exec("DELETE FROM user_custom_modules WHERE user_id != 1");

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $success_msg = "Sistema restaurado correctamente. Todos los datos han sido eliminados.";
            } catch (Exception $e) {
                // If checking FKs failed or connection error
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
        <?php if (!can_access_module('settings_general', $pdo) && !can_access_module('settings', $pdo)): ?>
             <div class="card"><div class="text-center p-4">Acceso denegado a Configuración General.</div></div>
        <?php else: ?>
        <div class="card" style="max-width: 1200px; margin: 0 auto; overflow: visible;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_general_settings">
                
                <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
                    
                    <!-- LEFT COLUMN: BRANDING -->
                    <div>
                        <div style="background: rgba(var(--primary-rgb), 0.03); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; text-align: center;">
                            <h4 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1rem; color: var(--text-main);">Identidad Visual</h4>
                            
                            <div style="position: relative; width: 100%; height: 180px; background: white; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; border: 2px dashed var(--border-color); overflow: hidden; transition: all 0.2s;">
                                <?php if($system_logo && file_exists("../../assets/uploads/" . $system_logo)): ?>
                                    <img src="../../assets/uploads/<?php echo $system_logo; ?>" id="logoPreview" alt="Logo" style="max-height: 140px; max-width: 90%; object-fit: contain;">
                                <?php else: ?>
                                    <div id="noLogoText" style="color: var(--text-muted); font-size: 0.9rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                                        <i class="ph ph-image" style="font-size: 2rem;"></i>
                                        <span>Sin Logo</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="position: relative;">
                                <input type="file" name="system_logo" id="logoInput" class="form-control" accept="image/*" style="opacity: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;" onchange="previewLogo(this)">
                                <button type="button" class="btn btn-secondary" style="width: 100%; position: relative; z-index: 1;">
                                    <i class="ph ph-upload-simple"></i> Cambiar Logo
                                </button>
                            </div>
                            <p class="text-muted" style="font-size: 0.75rem; margin-top: 0.75rem; margin-bottom: 0;">Recomendado: PNG/JPG, fondo transparente.</p>
                        </div>

                        <!-- Organization Info moved here or keep simple -->
                    </div>

                    <!-- RIGHT COLUMN: DETAILS -->
                    <div>
                        <div style="background: rgba(var(--bg-card-rgb), 0.6); border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem;">
                            
                            <h3 class="mb-4" style="font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph-fill ph-buildings" style="color: var(--primary);"></i> Información de la Empresa
                            </h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block;">Nombre de la Empresa</label>
                                    <input type="text" name="company_name" class="form-control premium-input" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Ej. Mastertec">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block;">Dirección</label>
                                    <input type="text" name="company_address" class="form-control premium-input" value="<?php echo htmlspecialchars($company_address); ?>" placeholder="Ej. Calle Principal #123">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block;">Teléfono</label>
                                    <div style="position: relative;">
                                        <i class="ph ph-phone" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                        <input type="text" name="company_phone" class="form-control premium-input" style="padding-left: 2.5rem;" value="<?php echo htmlspecialchars($company_phone); ?>" placeholder="Ej. +505 8888 8888">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; display: block;">Email de Contacto</label>
                                    <div style="position: relative;">
                                        <i class="ph ph-envelope" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                        <input type="email" name="company_email" class="form-control premium-input" style="padding-left: 2.5rem;" value="<?php echo htmlspecialchars($company_email); ?>" placeholder="contacto@empresa.com">
                                    </div>
                                </div>
                            </div>
                            
                            <hr style="border-color: var(--border-color); opacity: 0.5; margin: 2rem 0;">

                            <h3 class="mb-4" style="font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph-fill ph-file-text" style="color: #8b5cf6;"></i> Configuración de Documentos
                            </h3>

                            <div class="form-group" style="margin-bottom: 2rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Términos de Ingreso (Hoja de Recepción)</label>
                                    <span class="badge" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary); font-size: 0.7rem;">Visible al cliente</span>
                                </div>
                                <textarea name="print_entry_text" class="form-control premium-input" rows="6" style="resize: vertical; white-space: pre-wrap; line-height: 1.5; font-size: 0.9rem;"><?php echo htmlspecialchars($settings['print_entry_text'] ?? ""); ?></textarea>
                            </div>

                            <div class="form-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">Garantía y Entrega (Pie de Página)</label>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981; font-size: 0.7rem;">Visible en Salida</span>
                                </div>
                                <textarea name="print_footer_text" class="form-control premium-input" rows="4" style="resize: vertical; line-height: 1.5; font-size: 0.9rem;"><?php echo htmlspecialchars($print_footer_text); ?></textarea>
                            </div>

                            <div style="margin-top: 2rem; text-align: right;">
                                <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);">
                                    <i class="ph-bold ph-floppy-disk"></i> Guardar Cambios
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.getElementById('logoPreview');
                    var noLogo = document.getElementById('noLogoText');
                    
                    if(img) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    } else {
                        // Create img if it doesn't exist
                        img = document.createElement('img');
                        img.id = 'logoPreview';
                        img.src = e.target.result;
                        img.style.maxHeight = '140px';
                        img.style.maxWidth = '90%';
                        img.style.objectFit = 'contain';
                        
                        var container = document.querySelector('#noLogoText').parentNode;
                        container.innerHTML = '';
                        container.appendChild(img);
                    }
                    
                    if(noLogo) noLogo.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        </script>

        <style>
        .premium-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            transition: all 0.2s;
        }
        .premium-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1);
        }
        body.light-mode .premium-input {
            background: white;
            border-color: #e2e8f0;
        }
        body.light-mode .premium-input:focus {
            border-color: var(--primary);
        }
        </style>
        <?php endif; ?>
    </div>
</div>

<!-- TAB: ROLES -->
<div id="tab-roles" style="display: <?php echo $active_tab == 'roles' ? 'block' : 'none'; ?>;">
    <?php if (!can_access_module('settings_roles', $pdo) && !can_access_module('settings', $pdo)): ?>
         <div class="card"><div class="text-center p-4">Acceso denegado a Gestión de Roles.</div></div>
    <?php else: ?>
    <div class="card" style="margin-bottom: 2rem; width: fit-content;">
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

    <!-- Roles Summary Table -->
    <div class="card">
        <h3 class="mb-4">Resumen de Roles</h3>
        
        <?php
            // Fetch Roles with Counts
            $stmtRolesSummary = $pdo->query("
                SELECT 
                    r.id, 
                    r.name, 
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.status = 'active') as user_count,
                    (SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = r.id AND p.code LIKE 'module_%') as module_count
                FROM roles r
                ORDER BY r.id ASC
            ");
            $roles_summary = $stmtRolesSummary->fetchAll();
        ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Rol</th>
                    <th style="text-align: center;">Usuarios Asignados</th>
                    <th style="text-align: center;">Módulos Activos</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($roles_summary as $rs): ?>
                    <?php 
                        $is_super_role = ($rs['id'] == 1);
                        $row_style = $is_super_role ? "opacity: 0.7; cursor: not-allowed; background: rgba(0,0,0,0.02);" : "cursor: pointer; transition: background 0.2s;";
                        $onclick = $is_super_role ? "" : "window.location.href='?tab=modules&subtab=roles&target_role_id={$rs['id']}'";
                    ?>
                    <tr onclick="<?php echo $onclick; ?>" style="<?php echo $row_style; ?>" class="<?php echo $is_super_role ? '' : 'hover-row'; ?>">
                        <td style="font-weight: 500; font-size: 0.95rem;">
                            <?php if($is_super_role): ?>
                                <i class="ph-fill ph-lock-key" style="color: var(--warning); margin-right: 0.5rem;"></i>
                            <?php else: ?>
                                <i class="ph ph-shield" style="color: var(--text-muted); margin-right: 0.5rem;"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($rs['name']); ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: var(--bg-hover); color: var(--text-main);">
                                <i class="ph-bold ph-users" style="font-size: 0.8rem; margin-right: 4px;"></i>
                                <?php echo $rs['user_count']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                <?php echo $rs['id'] == 1 ? 'Todos' : $rs['module_count']; ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php if(!$is_super_role): ?>
                                <i class="ph ph-caret-right"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<!-- TAB: MODULES -->
<div id="tab-modules" style="display: <?php echo $active_tab == 'modules' ? 'block' : 'none'; ?>;">
    <?php if (!can_access_module('settings_modules', $pdo) && !can_access_module('settings', $pdo)): ?>
         <div class="card"><div class="text-center p-4">Acceso denegado a Control de Módulos.</div></div>
    <?php else: ?>
    
    <?php
    $subtab = isset($_GET['subtab']) ? $_GET['subtab'] : 'roles';
    ?>

    <!-- Sub-Tabs Navigation & Toolbar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
        <!-- Pills -->
        <div style="display: flex; gap: 0.25rem; background: var(--bg-hover); padding: 0.35rem; border-radius: 8px;">
            <a href="?tab=modules&subtab=roles" class="btn btn-sm <?php echo $subtab === 'roles' ? 'btn-primary' : 'btn-text'; ?>" style="<?php echo $subtab !== 'roles' ? 'color: var(--text-secondary);' : ''; ?>">
                <i class="ph ph-identification-badge"></i> Por Roles (General)
            </a>
            <a href="?tab=modules&subtab=users" class="btn btn-sm <?php echo $subtab === 'users' ? 'btn-primary' : 'btn-text'; ?>" style="<?php echo $subtab !== 'users' ? 'color: var(--text-secondary);' : ''; ?>">
                <i class="ph ph-user-gear"></i> Por Usuario (Excepciones)
            </a>
        </div>

        <!-- Right Toolbar (Context) -->
        <div id="modules-toolbar">
            <?php if ($subtab === 'users' && isset($_GET['target_user_id'])): ?>
                <?php 
                    $t_uid = intval($_GET['target_user_id']);
                    $stmtH = $pdo->prepare("SELECT username, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                    $stmtH->execute([$t_uid]);
                    $hUser = $stmtH->fetch();
                ?>
                <?php if($hUser): ?>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 0.9rem; color: var(--text-muted);">
                            Editando excepciones de: <strong style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($hUser['username']); ?></strong>
                        </span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge" style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary);">
                                <i class="ph-fill ph-shield-star" style="margin-right: 4px; color: var(--primary);"></i>
                                <?php echo htmlspecialchars($hUser['role_name']); ?>
                            </span>
                            <a href="?tab=modules&subtab=users" class="btn btn-sm btn-icon btn-text" title="Cerrar y volver a lista" style="color: var(--text-muted);">
                                <i class="ph ph-x"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUB-TAB: ROLES (The Grid -> Cards) -->
    <?php if ($subtab === 'roles'): ?>
    <?php
        // Prepare Data for Roles View
        $target_role_id = isset($_GET['target_role_id']) ? intval($_GET['target_role_id']) : 0;
        
        // Default to first editable role if not selected
        if ($target_role_id === 0 && count($users_roles_edit) > 0) {
            $target_role_id = $users_roles_edit[array_key_first($users_roles_edit)]['id'];
        }
        
        // Define Modules Layout (Reused)
        $modules_config = [
            'dashboard' => ['label' => 'Dashboard', 'cat' => 'Sistema', 'icon' => 'ph-squares-four'],
            'history'   => ['label' => 'Historial', 'cat' => 'Sistema', 'icon' => 'ph-clock-counter-clockwise'],
            
            'clients'   => ['label' => 'Clientes', 'cat' => 'Gestión', 'icon' => 'ph-users'],
            'clients_delete' => ['label' => 'Eliminar Clientes', 'cat' => 'Gestión', 'icon' => 'ph-trash'],
            'equipment' => ['label' => 'Equipos', 'cat' => 'Gestión', 'icon' => 'ph-desktop'],
            'equipment_entry' => ['label' => 'Reg. Entrada', 'cat' => 'Gestión', 'icon' => 'ph-download-simple'],
            'equipment_exit' => ['label' => 'Reg. Salida', 'cat' => 'Gestión', 'icon' => 'ph-upload-simple'],
            'tools'     => ['label' => 'Herramientas', 'cat' => 'Gestión', 'icon' => 'ph-wrench'],
            'services'  => ['label' => 'Servicios', 'cat' => 'Gestión', 'icon' => 'ph-briefcase'],
            'warranties'=> ['label' => 'Garantías', 'cat' => 'Gestión', 'icon' => 'ph-shield-check'],
            'new_warranty' => ['label' => 'Nueva Garantía', 'cat' => 'Gestión', 'icon' => 'ph-plus-circle'],
            
            'users'     => ['label' => 'Usuarios', 'cat' => 'Administración', 'icon' => 'ph-user-gear'],
            'users_delete' => ['label' => 'Eliminar Usuarios', 'cat' => 'Administración', 'icon' => 'ph-trash'],
            'reports'   => ['label' => 'Reportes', 'cat' => 'Administración', 'icon' => 'ph-chart-bar'],
            'settings'  => ['label' => 'Config. Sistema', 'cat' => 'Administración', 'icon' => 'ph-gear'],
            'settings_general' => ['label' => 'Conf. General', 'cat' => 'Administración', 'icon' => 'ph-sliders'],
            'settings_roles'   => ['label' => 'Roles', 'cat' => 'Administración', 'icon' => 'ph-shield-check'],
            'settings_modules' => ['label' => 'Módulos', 'cat' => 'Administración', 'icon' => 'ph-squares-four'],
            'settings_users'   => ['label' => 'Usuarios (Admin)', 'cat' => 'Administración', 'icon' => 'ph-users-three'],
            'settings_restore' => ['label' => 'Restaurar', 'cat' => 'Administración', 'icon' => 'ph-warning-octagon'],
            're_enter_workshop' => ['label' => 'Reingresar', 'cat' => 'Gestión', 'icon' => 'ph-arrow-u-down-left'],
            'view_all_entries'  => ['label' => 'Ver equipos de todos', 'cat' => 'Gestión', 'icon' => 'ph-eye'],
        ];

        // Group by Category
        $grouped_modules = [];
        foreach ($modules_config as $key => $info) {
            if (isset($defined_modules[$key])) {
                $grouped_modules[$info['cat']][$key] = $info;
            }
        }
    ?>
    <div class="card" style="max-width: 100%;">
        <div style="margin-bottom: 2rem;">
            <h3 class="mb-2">Matriz de Acceso a Módulos</h3>
            <p class="text-muted">Seleccione un rol y defina sus permisos globales. Estos permisos aplican a todos los usuarios con este rol, a menos que tengan excepciones.</p>
            
            <!-- ROLE SELECTOR -->
            <div style="margin-top: 1.5rem; max-width: 400px;">
                <label class="form-label">Seleccionar Rol</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select class="form-control" onchange="window.location.href='?tab=modules&subtab=roles&target_role_id='+this.value">
                        <?php foreach($users_roles_edit as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $target_role_id == $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_permissions">
            <input type="hidden" name="role_id" value="<?php echo $target_role_id; ?>">
            
            <div class="permissions-container">
                <?php foreach ($grouped_modules as $category => $modules): ?>
                    <div class="category-section">
                        <h4 class="category-title"><?php echo htmlspecialchars($category); ?></h4>
                        <div class="modules-grid">
                            <?php foreach ($modules as $mod_key => $mod_info): ?>
                                <?php 
                                    $p_code = 'module_' . $mod_key;
                                    $has_perm = isset($current_perms[$target_role_id][$p_code]);
                                ?>
                                <div class="module-card <?php echo $has_perm ? 'allow' : ''; ?>">
                                    <div class="module-header" style="justify-content: space-between;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div class="module-icon">
                                                <i class="ph <?php echo $mod_info['icon']; ?>"></i>
                                            </div>
                                            <div class="module-name">
                                                <?php echo htmlspecialchars($mod_info['label']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Role Toggles (Binary) -->
                                    <div class="segmented-control">
                                        <label class="segment <?php echo $has_perm ? 'active' : ''; ?>">
                                            <input type="radio" name="perms[<?php echo $p_code; ?>]" value="1" <?php echo $has_perm ? 'checked' : ''; ?>>
                                            <span>Permitir</span>
                                        </label>
                                        <label class="segment <?php echo !$has_perm ? 'active' : ''; ?>">
                                            <input type="radio" name="perms[<?php echo $p_code; ?>]" value="0" <?php echo !$has_perm ? 'checked' : ''; ?>>
                                            <span>Denegar</span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 2rem; display: flex; justify-content: flex-end; position: sticky; bottom: 0; background: var(--bg-card); border-top: 1px solid var(--border-color); padding: 1.5rem; margin: 0 -1.5rem -1.5rem -1.5rem; z-index: 100; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <button type="submit" class="btn btn-primary" style="min-width: 200px;">
                    <i class="ph ph-floppy-disk"></i> Guardar Permisos del Rol
                </button>
            </div>
        </form>
    </div>
    
    <!-- SUB-TAB: USER EXCEPTIONS -->
    <?php elseif ($subtab === 'users'): ?>
        <?php
        $target_user_id = isset($_GET['target_user_id']) ? intval($_GET['target_user_id']) : 0;
        
        // Define Modules Layout (Reused)
        $modules_config = [
            'dashboard' => ['label' => 'Dashboard', 'cat' => 'Sistema', 'icon' => 'ph-squares-four'],
            'history'   => ['label' => 'Historial', 'cat' => 'Sistema', 'icon' => 'ph-clock-counter-clockwise'],
            
            'clients'   => ['label' => 'Clientes', 'cat' => 'Gestión', 'icon' => 'ph-users'],
            'clients_delete' => ['label' => 'Eliminar Clientes', 'cat' => 'Gestión', 'icon' => 'ph-trash'],
            'equipment' => ['label' => 'Equipos', 'cat' => 'Gestión', 'icon' => 'ph-desktop'],
            'equipment_entry' => ['label' => 'Reg. Entrada', 'cat' => 'Gestión', 'icon' => 'ph-download-simple'],
            'equipment_exit' => ['label' => 'Reg. Salida', 'cat' => 'Gestión', 'icon' => 'ph-upload-simple'],
            'tools'     => ['label' => 'Herramientas', 'cat' => 'Gestión', 'icon' => 'ph-wrench'],
            'services'  => ['label' => 'Servicios', 'cat' => 'Gestión', 'icon' => 'ph-briefcase'],
            'warranties'=> ['label' => 'Garantías', 'cat' => 'Gestión', 'icon' => 'ph-shield-check'],
            'new_warranty' => ['label' => 'Nueva Garantía', 'cat' => 'Gestión', 'icon' => 'ph-plus-circle'],
            
            'users'     => ['label' => 'Usuarios', 'cat' => 'Administración', 'icon' => 'ph-user-gear'],
            'reports'   => ['label' => 'Reportes', 'cat' => 'Administración', 'icon' => 'ph-chart-bar'],
            'settings'  => ['label' => 'Config. Sistema', 'cat' => 'Administración', 'icon' => 'ph-gear'],
            'settings_general' => ['label' => 'Conf. General', 'cat' => 'Administración', 'icon' => 'ph-sliders'],
            'settings_roles'   => ['label' => 'Roles', 'cat' => 'Administración', 'icon' => 'ph-shield-check'],
            'settings_modules' => ['label' => 'Módulos', 'cat' => 'Administración', 'icon' => 'ph-squares-four'],
            'settings_users'   => ['label' => 'Usuarios (Admin)', 'cat' => 'Administración', 'icon' => 'ph-users-three'],
            'settings_restore' => ['label' => 'Restaurar', 'cat' => 'Administración', 'icon' => 'ph-warning-octagon'],
            're_enter_workshop' => ['label' => 'Reingresar', 'cat' => 'Gestión', 'icon' => 'ph-arrow-u-down-left'],
            'view_all_entries'  => ['label' => 'Ver equipos de todos', 'cat' => 'Gestión', 'icon' => 'ph-eye'],
        ];
        
        // Group by Category (Reused Logic)
        $grouped_modules = [];
        foreach ($modules_config as $key => $info) {
            if (isset($defined_modules[$key])) {
                $grouped_modules[$info['cat']][$key] = $info;
            }
        }
        ?>

        <!-- VIEW STATE: TABLE (NO SELECTION) -->
        <?php if (!$target_user_id): ?>
            <?php 
                // Fetch Users with Role Names and Module Counts
                $stmtAllUsers = $pdo->query("
                    SELECT 
                        u.id, u.username, u.email, u.status, u.role_id, 
                        r.name as role_name,
                        (SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = u.role_id AND p.code LIKE 'module_%') as role_module_count
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.status = 'active'
                    ORDER BY u.role_id ASC, u.username ASC
                ");
                $all_users = $stmtAllUsers->fetchAll();
            ?>
            <div class="card">
                <div style="margin-bottom: 2rem;">
                    <h3 class="mb-2">Usuarios del Sistema</h3>
                    <p class="text-muted">Seleccione un usuario de la lista para gestionar sus permisos específicos.</p>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th style="text-align: center;">Módulos</th>
                            <th>Estado</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_users as $u): ?>
                            <?php 
                                $is_super = ($u['id'] == 1);
                                $row_style = $is_super ? "opacity: 0.7; cursor: not-allowed; background: rgba(0,0,0,0.02);" : "cursor: pointer;";
                                $onclick = $is_super ? "" : "window.location.href='?tab=modules&subtab=users&target_user_id={$u['id']}'";
                            ?>
                            <tr onclick="<?php echo $onclick; ?>" style="<?php echo $row_style; ?>">
                                <td>
                                    <div class="avatar" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                        <?php if($is_super): ?>
                                            <i class="ph-fill ph-lock-key" style="color: var(--warning);"></i>
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($u['username']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></div>
                                </td>
                                <td>
                                    <span class="badge"><?php echo htmlspecialchars($u['role_name']); ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge" style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        <?php echo ($u['id'] == 1) ? 'Todos' : $u['role_module_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($u['status'] == 'active'): ?>
                                        <span class="status-badge status-green" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="ph-fill ph-toggle-right" style="font-size: 1.1em;"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-gray" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="ph-fill ph-toggle-left" style="font-size: 1.1em;"></i> Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted);">
                                    <?php if(!$is_super): ?>
                                        <i class="ph ph-caret-right"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <!-- VIEW STATE: DETAIL (USER SELECTED) -->
        <?php else: ?>
            <?php
            // Fetch Selected User Details
            $stmtTU = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmtTU->execute([$target_user_id]);
            $target_user = $stmtTU->fetch();
            
            if ($target_user) {
                // SECURITY: Prevent editing SuperAdmin (ID 1)
                if ($target_user['id'] == 1) {
                   echo '<div class="alert alert-danger">No se pueden modificar los permisos del SuperAdmin.</div>';
                   $target_user = false; // Disable form render
                } else {
                    $target_role_id = $target_user['role_id'];
                }
            }
            ?>
            
            <?php if ($target_user): ?>
                <div class="card">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_user_permissions">
                        <input type="hidden" name="user_id" value="<?php echo $target_user_id; ?>">
                        
                        <div class="permissions-container">
                            <?php foreach ($grouped_modules as $category => $modules): ?>
                                <div class="category-section">
                                    <h4 class="category-title"><?php echo htmlspecialchars($category); ?></h4>
                                    <div class="modules-grid">
                                        <?php foreach ($modules as $mod_key => $mod_info): ?>
                                            <?php 
                                                // Determine Role Access
                                                $perm_code = 'module_' . $mod_key;
                                                $stmtRolePerm = $pdo->prepare("
                                                    SELECT COUNT(*) FROM role_permissions rp 
                                                    JOIN permissions p ON rp.permission_id = p.id 
                                                    WHERE rp.role_id = ? AND p.code = ?
                                                ");
                                                $stmtRolePerm->execute([$target_role_id, $perm_code]);
                                                $role_has_access = $stmtRolePerm->fetchColumn() > 0;
                                                
                                                // Determine User Override
                                                // Fetch strict value from DB
                                                $stmtUserPerm = $pdo->prepare("SELECT is_enabled FROM user_custom_modules WHERE user_id = ? AND module_name = ?");
                                                $stmtUserPerm->execute([$target_user_id, $mod_key]);
                                                $override = $stmtUserPerm->fetchColumn(); 
                                                // $override will be 1, 0, or false (null)
                                                
                                                // Current Selection State
                                                // If override is present (1 or 0), use it. Else 'inherit'.
                                                $current_val = 'inherit';
                                                if ($override !== false) {
                                                    $current_val = $override == 1 ? 'allow' : 'deny';
                                                }
                                            ?>
                                            
                                            <div class="module-card <?php echo $current_val === 'inherit' ? ($role_has_access ? 'inherit-allow' : 'inherit-deny') : $current_val; ?>">
                                                <div class="module-header" style="justify-content: space-between;">
                                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                        <div class="module-icon">
                                                            <i class="ph <?php echo $mod_info['icon']; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <div class="module-name"><?php echo htmlspecialchars($mod_info['label']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="role-status">
                                                        <?php if($role_has_access): ?>
                                                            <span class="text-xs text-success"><i class="ph-bold ph-check"></i> Por Rol: Sí</span>
                                                        <?php else: ?>
                                                            <span class="text-xs text-muted"><i class="ph-bold ph-x"></i> Por Rol: No</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="segmented-control">
                                                    <label class="segment <?php echo $current_val === 'inherit' ? 'active' : ''; ?>">
                                                        <input type="radio" name="perms[<?php echo $mod_key; ?>]" value="inherit" <?php echo $current_val === 'inherit' ? 'checked' : ''; ?>>
                                                        <span>Heredar</span>
                                                    </label>
                                                    <label class="segment <?php echo $current_val === 'allow' ? 'active' : ''; ?>">
                                                        <input type="radio" name="perms[<?php echo $mod_key; ?>]" value="1" <?php echo $current_val === 'allow' ? 'checked' : ''; ?>>
                                                        <span>Permitir</span>
                                                    </label>
                                                    <label class="segment <?php echo $current_val === 'deny' ? 'active' : ''; ?>">
                                                        <input type="radio" name="perms[<?php echo $mod_key; ?>]" value="0" <?php echo $current_val === 'deny' ? 'checked' : ''; ?>>
                                                        <span>Denegar</span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="margin-top: 2rem; display: flex; justify-content: flex-end; position: sticky; bottom: 0; background: rgba(17, 24, 39, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); padding: 1.5rem; margin: 0 -1.5rem -1.5rem -1.5rem; border-top: 1px solid var(--border-color); z-index: 100; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                            <button type="submit" class="btn btn-primary" style="min-width: 200px;">
                                <i class="ph ph-floppy-disk"></i> Guardar Excepciones
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; // End Target User check ?>
    <?php endif; // End Subtab check ?>
</div>
<?php endif; ?>

<style>
/* Segmented Control Styles (Reused) */
.segmented-control {
    display: flex;
    background: var(--bg-body);
    padding: 4px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
}
.segment {
    flex: 1;
    text-align: center;
    padding: 6px 4px;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 4px;
    position: relative;
    user-select: none;
    color: var(--text-secondary);
    transition: all 0.2s;
}
.segment input {
    position: absolute;
    opacity: 0;
    width: 0; 
    height: 0;
}
.segment.active {
    background: var(--bg-card); /* or highlight color */
    color: var(--text-primary);
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
/* Allow/Deny Colors when Active */
.segment:has(input[value="1"]).active { color: var(--success); }
.segment:has(input[value="0"]).active { color: var(--danger); }

/* Card Border Colors based on status */
.module-card.allow { border-color: var(--success); }
.module-card.deny { border-color: var(--danger); }
.module-card.inherit-allow { border-left: 3px solid var(--success); } /* Optional visual cue */
/* ... */
</style>

<script>
// Re-enable interactivity for new elements
document.addEventListener('DOMContentLoaded', function() {
    const segments = document.querySelectorAll('.segment');
    segments.forEach(segment => {
        segment.addEventListener('click', function(e) {
            const input = this.querySelector('input[type="radio"]');
            if (e.target !== input) {
                input.checked = true;
            }
            const control = this.closest('.segmented-control');
            const card = this.closest('.module-card');
            control.querySelectorAll('.segment').forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            card.classList.remove('inherit-allow', 'inherit-deny', 'allow', 'deny', 'inherit');
            
            // Re-calc class simplified
            let val = input.value;
            if(val === 'inherit') {
                // If inherit, we might want to check the server-side role again, 
                // OR just remove the explicit color class.
                // For simplified JS here, we just remove explicit colors.
            } else if (val === '1') {
                card.classList.add('allow');
            } else {
                card.classList.add('deny');
            }
        });
    });
});
</script>




<!-- TAB: USERS -->
<div id="tab-users" style="display: <?php echo $active_tab == 'users' ? 'block' : 'none'; ?>;">
    <?php if (!can_access_module('settings_users', $pdo) && !can_access_module('settings', $pdo)): ?>
         <div class="card"><div class="text-center p-4">Acceso denegado a Gestión de Usuarios.</div></div>
    <?php else: ?>
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
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                            <?php 
                                $is_super_user = ($user['id'] == 1);
                                $row_style = $is_super_user ? "opacity: 0.7; cursor: not-allowed; background: rgba(0,0,0,0.02);" : "cursor: pointer; transition: background 0.2s;";
                                $onclick = $is_super_user ? "" : "window.location.href='../users/edit.php?id={$user['id']}'";
                            ?>
                            <tr onclick="<?php echo $onclick; ?>" style="<?php echo $row_style; ?>" class="<?php echo $is_super_user ? '' : 'hover-row'; ?>">
                                <td><strong>#<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="user-avatar-sm" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                            <?php if($is_super_user): ?>
                                                 <i class="ph-fill ph-lock-key" style="color: var(--warning);"></i>
                                            <?php else: ?>
                                                 <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            <?php endif; ?>
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
                                <td style="text-align: center; color: var(--text-muted); white-space: nowrap;">
                                    
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                        <?php if(can_access_module('users_delete', $pdo) && !$is_super_user && $user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn-icon btn-icon-danger" 
                                                    onclick='event.stopPropagation(); openDeleteModal(<?php echo $user['id']; ?>, <?php echo json_encode($user['username']); ?>);'
                                                    title="Eliminar Usuario"
                                                    style="background: none; border: none; cursor: pointer; color: var(--danger);">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                        <?php endif; ?>

                                        <?php if(!$is_super_user): ?>
                                            <i class="ph ph-caret-right"></i>
                                        <?php endif; ?>
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
    <?php endif; ?>
    </div>

    <!-- TAB: RESTORE -->
    <div id="tab-restore" style="display: <?php echo $active_tab == 'restore' ? 'block' : 'none'; ?>;">
        <?php if (!can_access_module('settings_restore', $pdo) && !can_access_module('settings', $pdo)): ?>
                <div class="card"><div class="text-center p-4">Acceso denegado a Restaurar Sistema.</div></div>
        <?php else: ?>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_GET['msg']) && $_GET['msg']=='restored'): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem; background: rgba(34, 197, 94, 0.1); color: var(--success); border: 1px solid var(--success);">
                <i class="ph ph-check-circle"></i> Base de datos restaurada correctamente.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger);">
                <i class="ph ph-warning"></i> Error en la operación: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: stretch;">
            
            <!-- BACKUP SECTION -->
            <div class="card card-premium purple">
                <div class="icon-box">
                    <i class="ph ph-database"></i>
                </div>
                <h3>Respaldo de BD</h3>
                <p>Descarga una copia SQL de seguridad para proteger tu información.</p>
                <form method="POST" style="margin-top: auto;">
                    <input type="hidden" name="action" value="backup_db">
                    <button type="submit" class="btn btn-premium btn-premium-purple">
                        <i class="ph ph-download-simple"></i> Descargar (.sql)
                    </button>
                </form>
            </div>

            <!-- RESTORE SECTION -->
            <div class="card card-premium yellow">
                <div class="icon-box">
                    <i class="ph ph-upload-simple"></i>
                </div>
                <h3>Restaurar BD</h3>
                <p>Importar archivo SQL. Esta acción sobrescribirá los datos actuales.</p>
                <form method="POST" id="restoreForm" enctype="multipart/form-data" onsubmit="confirmRestore(event)" style="margin-top: auto;">
                    <input type="hidden" name="action" value="restore_db">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <input type="file" name="backup_file" class="form-control" accept=".sql" required style="font-size: 0.85rem; padding: 0.4rem; width: 100%;">
                        <button type="submit" class="btn btn-premium btn-premium-outline">
                            <i class="ph ph-arrow-counter-clockwise"></i> Restaurar
                        </button>
                    </div>
                </form>
            </div>

                        <!-- IMPORT SECTION -->
            <div class="card card-premium blue">
                <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: var(--primary-400);">
                    <i class="ph ph-file-csv"></i>
                </div>
                <h3>Importar Garantías</h3>
                <p>Carga masiva de registros (CSV). Ideal para migraciones o cargas iniciales de datos.</p>
                <div style="margin-top: auto;">
                    <a href="import_warranties.php" class="btn btn-premium" style="background: var(--primary-500); color: white; border: none; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                        <i class="ph ph-rocket-launch"></i> Iniciar Importador
                    </a>
                </div>
            </div>
            <!-- DANGER ZONE (Factory Reset) -->
            <div class="card card-premium red">
                <div class="icon-box">
                    <i class="ph ph-warning-octagon"></i>
                </div>
                <h3>Reset de Fábrica</h3>
                <p>Elimina clientes, equipos y órdenes. Mantiene usuarios y config.</p>
                <form method="POST" id="factoryResetForm" style="margin-top: auto; display: flex; flex-direction: column; gap: 0.75rem;">
                    <input type="hidden" name="action" value="system_restore">
                    <input type="password" name="admin_password" class="form-control" placeholder="Pass de SuperAdmin" required style="font-size: 0.85rem; padding: 0.4rem; width: 100%;">
                    <button type="button" onclick="confirmFactoryReset()" class="btn btn-premium btn-premium-danger">
                        <i class="ph ph-trash"></i> Eliminar Todo
                    </button>
                </form>
            </div>

        </div>
        
        <script>
            function confirmRestore(e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Restaurar Base de Datos?',
                    text: 'Se sobrescribirá TODA la información actual con la del archivo seleccionado. Esta acción es irreversible.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#eab308',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar',
                    background: '#1e293b', 
                    color: '#fff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('restoreForm').submit();
                    }
                });
            }

            function confirmFactoryReset() {
                 const pass = document.querySelector('input[name="admin_password"]').value;
                 if(!pass) {
                     Swal.fire('Error', 'Ingrese la contraseña de SuperAdmin', 'error');
                     return;
                 }

                 Swal.fire({
                    title: '¿RESET DE FÁBRICA?',
                    text: '¡ESTA ACCIÓN ELIMINARÁ TODOS LOS DATOS! (Clientes, Equipos, Órdenes, etc). Solo quedarán los usuarios y configuraciones.',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'SÍ, BORRAR TODO',
                    cancelButtonText: 'Cancelar',
                    background: '#1e293b', 
                    color: '#fff',
                    focusCancel: true
                }).then((result) => {
                    if (result.isConfirmed) {
                         document.getElementById('factoryResetForm').submit();
                    }
                });
            }
        </script>
        <?php endif; ?>
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


/* Permissions Grid Styles */
.permissions-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.category-title {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-bottom: 1rem;
    padding-left: 0.25rem;
    font-weight: 600;
}

.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.module-card {
    background: var(--bg-hover); /* Slightly lighter than card base */
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.2s ease;
}

.module-card:hover {
    border-color: var(--primary-500);
    background: var(--bg-card);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.module-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.module-icon {
    width: 32px;
    height: 32px;
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.module-name {
    font-weight: 600;
    font-size: 0.95rem;
}

.role-toggles {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.role-toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Updated Checkbox Style for Rows */
.custom-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
    width: 100%;
}

.role-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.custom-checkbox input:checked ~ .role-label {
    color: var(--text-primary);
    font-weight: 500;
}

.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: relative;
    height: 20px; 
    width: 20px;
    background-color: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    flex-shrink: 0;
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

</script>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease-out;
}

.modal-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    transform: scale(0.95);
    opacity: 0;
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes popIn {
    to { transform: scale(1); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}


.btn-modal {
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-modal-cancel {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-muted);
}

.btn-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-main);
    border-color: var(--text-secondary);
}

.btn-modal-danger {
    background: var(--danger);
    border: none;
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}

.btn-modal-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.35);
}
</style>

<!-- DELETE CONFIRMATION MODAL -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container" style="text-align: center;">
        <div class="modal-header">
            <div style="width: 72px; height: 72px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; animation: pulseRed 2s infinite;">
                <i class="ph-fill ph-trash" style="font-size: 36px; color: var(--danger);"></i>
            </div>
        </div>
        <div class="modal-body">
            <h3 style="font-size: 1.5rem; margin-bottom: 0.75rem; color: var(--text-main); font-weight: 600;">¿Eliminar Usuario?</h3>
            <p style="color: var(--text-muted); margin-bottom: 2.5rem; font-size: 0.95rem; line-height: 1.6; max-width: 80%; margin-left: auto; margin-right: auto;">
                Estás a punto de eliminar permanentemente a <strong id="deleteUserName" style="color: var(--text-main);">UNKNOWN</strong>.<br>
                <span style="font-size: 0.85rem; opacity: 0.8;">Esta acción no se puede deshacer.</span>
            </p>
            
            <form method="POST" id="confirmDeleteForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="deleteUserIdInput">
                
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeDeleteModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-modal btn-modal-danger">
                        Sí, eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes pulseRed {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
</style>

<script>
function openDeleteModal(userId, userName) {
    document.getElementById('deleteUserIdInput').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close on outside click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>
