<?php
// modules/profile/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Check/Add navbar_order column (Migration)
try {
    $pdo->query("SELECT navbar_order FROM users LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN navbar_order TEXT DEFAULT NULL");
        } catch(Exception $ex) {}
    }
}

// Handle Signature Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_signature') {
    $stmtUpdate = $pdo->prepare("UPDATE users SET signature_path = NULL WHERE id = ?");
    if ($stmtUpdate->execute([$user_id])) {
        $success = "Firma eliminada correctamente.";
        log_audit($pdo, 'users', $user_id, 'UPDATE', null, ['event' => 'signature_removed']);
    } else {
        $error = "Error al eliminar la firma.";
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signature'])) {
    $file = $_FILES['signature'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileType = mime_content_type($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'png' && $fileType === 'image/png') {
            $filename = "user_" . $user_id . "_" . time() . ".png"; 
            $uploadDir = '../../assets/uploads/signatures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
                $stmt->execute([$filename, $user_id]);
                $success = "Firma actualizada correctamente.";
                log_audit($pdo, 'users', $user_id, 'UPDATE', null, ['event' => 'signature_upload']);
            } else { $error = "Error al guardar el archivo."; }
        } else { $error = "Solo se permiten archivos PNG."; }
    } else { $error = "Error al subir el archivo."; }
}

// Handle Personal Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_full_name = clean($_POST['full_name']);
    $new_email = clean($_POST['email']);
    
    $stmtUpdate = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
    if ($stmtUpdate->execute([$new_full_name, $new_email, $user_id])) {
        $_SESSION['full_name'] = $new_full_name;
        $success = "Perfil actualizado correctamente.";
        log_audit($pdo, 'users', $user_id, 'UPDATE', null, ['event' => 'profile_update', 'full_name' => $new_full_name]);
    } else {
        $error = "Error al actualizar el perfil.";
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT u.username, u.full_name, u.email, r.name as role_name, u.signature_path FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$page_title = 'Mi Perfil';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="main-content" style="padding: 2rem;">
    <div class="page-header" style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--text-main);">Mi Perfil</h1>
        <p class="text-muted">Gestiona tu firma digital y personalización de la barra de navegación.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="border-radius: 12px; margin-bottom: 2rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981;">
            <i class="ph-fill ph-check-circle" style="margin-right: 8px;"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="border-radius: 12px; margin-bottom: 2rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444;">
            <i class="ph-fill ph-warning-circle" style="margin-right: 8px;"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- LEFT COLUMN -->
        <div class="profile-sidebar">
            <div class="profile-card identity-card">
                <div class="avatar-container">
                    <div class="avatar-circle"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                    <div class="avatar-ring"></div>
                </div>
                <div class="identity-info">
                    <h2 class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                    <span class="user-role-badge"><?php echo htmlspecialchars($user['role_name']); ?></span>
                    <p class="user-email"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <div class="sidebar-divider"></div>
                <div class="signature-section">
                    <h3 class="section-title-sm"><i class="ph-fill ph-pen-nib"></i> Firma Digital</h3>
                    <?php if (!empty($user['signature_path'])): ?>
                        <div class="signature-display">
                            <img src="../../assets/uploads/signatures/<?php echo $user['signature_path']; ?>?v=<?php echo time(); ?>" alt="Firma">
                            <form id="deleteSignatureForm" method="POST">
                                <input type="hidden" name="action" value="delete_signature">
                                <button type="button" id="btnDeleteSignature" class="btn-icon-delete"><i class="ph ph-trash"></i></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="signature-upload-placeholder">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="file" name="signature" id="sigInput" style="display:none;" accept="image/png" onchange="this.form.submit()">
                                <label for="sigInput" class="upload-zone">
                                    <i class="ph ph-cloud-arrow-up"></i>
                                    <span>Subir Firma PNG</span>
                                </label>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="profile-main">
            <div class="profile-card main-content-card">
                </div>

                <form method="POST" style="margin-bottom: 2rem;">
                    <input type="hidden" name="action" value="update_profile">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group-premium">
                            <label><i class="ph ph-user"></i> Nombre Completo (Profesional)</label>
                            <input type="text" name="full_name" class="input-premium" value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="Ej: Eduardo Chang">
                        </div>
                        <div class="form-group-premium">
                            <label><i class="ph ph-envelope"></i> Correo Electrónico</label>
                            <input type="email" name="email" class="input-premium" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="usuario@empresa.com">
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <button type="submit" class="btn-premium">
                            <i class="ph ph-check"></i> Actualizar Información
                        </button>
                    </div>
                </form>

                <div class="section-header">
                    <div class="header-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"><i class="ph ph-list-numbers"></i></div>
                    <div>
                        <h3 class="section-title">Personalización del Menú</h3>
                        <p class="section-desc">Arrastra los módulos para definir el orden de tu barra de navegación.</p>
                    </div>
                </div>

                <ul id="sortable-menu" class="modern-menu-list">
                    <?php if (can_access_module('dashboard', $pdo)): ?>
                        <li data-id="dashboard" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-squares-four"></i> Dashboard</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('clients', $pdo)): ?>
                        <li data-id="clients" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-users"></i> Clientes</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('equipment', $pdo)): ?>
                        <li data-id="equipment_menu" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-monitor"></i> Solicitud</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('new_warranty', $pdo)): ?>
                        <li data-id="bodega_menu" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-package"></i> Bodega</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('services', $pdo) || can_access_module('warranties', $pdo) || can_access_module('history', $pdo)): ?>
                        <li data-id="services" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-clipboard-text"></i> Servicios</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('schedule', $pdo) || can_access_module('tech_agenda', $pdo)): ?>
                        <li data-id="agenda" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-map-trifold"></i> Agenda</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('proyectos', $pdo) || can_access_module('surveys', $pdo)): ?>
                        <li data-id="projects_group" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-folder-open"></i> Proyectos</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('comisiones', $pdo) || can_access_module('anexos', $pdo)): ?>
                        <li data-id="admin_group" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-briefcase"></i> Administración</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('reports', $pdo)): ?>
                        <li data-id="reports" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-chart-bar"></i> Reportes</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('tools', $pdo)): ?>
                        <li data-id="tools" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-wrench"></i> Herramientas</div></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['role_name'] === 'SuperAdmin'): ?>
                        <li data-id="audit_log" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-shield-check"></i> Auditoría</div></li>
                    <?php endif; ?>
                    <?php if (can_access_module('settings', $pdo)): ?>
                        <li data-id="settings" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-gear"></i> Configuración</div></li>
                    <?php endif; ?>
                </ul>

                <div style="text-align: right;">
                    <button id="saveMenuOrder" class="btn-premium">
                        <i class="ph ph-floppy-disk"></i> Guardar Configuración
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* DEFINE MISSING VARIABLES FOR PROFILE SCOPE */
:root {
    --profile-primary: #6366f1;
    --profile-primary-dark: #4f46e5;
    --profile-gradient: linear-gradient(135deg, #4f46e5, #6366f1);
}

.profile-grid { display: grid; grid-template-columns: 340px 1fr; gap: 2rem; }
.profile-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 24px; padding: 2rem; box-shadow: var(--shadow-card); }
.identity-card { text-align: center; }
.avatar-container { position: relative; width: 120px; height: 120px; margin: 0 auto 1.5rem; }
.avatar-circle { width: 100%; height: 100%; background: var(--profile-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: white; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.2); }
.user-role-badge { display: inline-block; background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; }
.sidebar-divider { height: 1px; background: var(--border-color); margin: 2rem 0; opacity: 0.5; }
.section-title-sm { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.signature-display { position: relative; background: white; border-radius: 12px; padding: 1rem; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); }
.signature-display img { max-width: 100%; max-height: 80px; }
.btn-icon-delete { position: absolute; top: -10px; right: -10px; width: 30px; height: 30px; background: #ef4444; color: white; border: none; border-radius: 50%; cursor: pointer; box-shadow: var(--shadow-md); }
.upload-zone { border: 2px dashed var(--border-color); border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: 0.3s; }
.upload-zone:hover { border-color: var(--profile-primary); background: var(--bg-hover); }
.section-header { display: flex; align-items: center; gap: 1.25rem; margin-bottom: 2rem; }
.header-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0; }
.modern-menu-list { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.modern-drag-item { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem; display: flex; align-items: center; gap: 1rem; cursor: grab; transition: 0.2s; }
.modern-drag-item:hover { border-color: var(--profile-primary); transform: translateY(-2px); }
.item-body { display: flex; align-items: center; gap: 0.75rem; font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
.btn-premium { background: var(--profile-gradient); color: white !important; border: none; padding: 1rem 2rem; border-radius: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4); text-decoration: none; }
.btn-premium:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5); filter: brightness(1.1); }

.form-group-premium { display: flex; flex-direction: column; gap: 0.5rem; }
.form-group-premium label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; }
.input-premium { background: var(--bg-hover); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem 1rem; color: var(--text-main); font-size: 0.95rem; transition: 0.3s; }
.input-premium:focus { border-color: var(--profile-primary); outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnDelete = document.getElementById('btnDeleteSignature');
    if(btnDelete) {
        btnDelete.addEventListener('click', () => {
            Swal.fire({
                title: '¿Eliminar firma?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Sí, eliminar',
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            }).then((result) => { if (result.isConfirmed) document.getElementById('deleteSignatureForm').submit(); });
        });
    }

    const el = document.getElementById('sortable-menu');
    if(el) {
        const sortable = Sortable.create(el, { animation: 200 });
        document.getElementById('saveMenuOrder').addEventListener('click', () => {
            const order = sortable.toArray(); 
            fetch('save_navbar_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ icon: 'success', title: 'Guardado', timer: 1500, showConfirmButton: false, background: 'var(--bg-card)', color: 'var(--text-main)' }).then(() => location.reload());
                }
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
