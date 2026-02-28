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
    // If column doesn't exist
    if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN navbar_order TEXT DEFAULT NULL");
        } catch(Exception $ex) {
            // Ignore if fails (might be permissions), but user will see visual errors later if strict
        }
    }
}

// Handle Signature Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_signature') {
    // Get current signature path
    $stmtGet = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
    $stmtGet->execute([$user_id]);
    $currentSig = $stmtGet->fetchColumn();
    
    // Remove from DB
    $stmtUpdate = $pdo->prepare("UPDATE users SET signature_path = NULL WHERE id = ?");
    if ($stmtUpdate->execute([$user_id])) {
        // DO NOT remove file physicaly to preserve history on old documents
        // if ($currentSig && file_exists('../../assets/uploads/signatures/' . $currentSig)) {
        //    unlink('../../assets/uploads/signatures/' . $currentSig);
        // }
        
        $success = "Firma eliminada correctamente (se mantendrá en documentos históricos).";
        log_audit($pdo, 'users', $user_id, 'UPDATE', null, ['event' => 'signature_removed'], $user_id, $_SERVER['REMOTE_ADDR']);
    } else {
        $error = "Error al eliminar la firma.";
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signature'])) {
    $file = $_FILES['signature'];
    
    // Validations
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo.";
    } else {
        $fileType = mime_content_type($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'png' || $fileType !== 'image/png') {
            $error = "Solo se permiten archivos PNG.";
        } else {
            // Define path
            // Use timestamp to make filename unique for every upload (history preservation)
            $filename = "user_" . $user_id . "_" . time() . ".png"; 
            $uploadDir = '../../assets/uploads/signatures/';
            $targetPath = $uploadDir . $filename;
            
             // Ensure dir exists (redundant check)
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Update DB
                $stmt = $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
                $stmt->execute([$filename, $user_id]);
                
                $success = "Firma actualizada correctamente.";
                
                // Log audit
                log_audit($pdo, 'users', $user_id, 'UPDATE', null, ['event' => 'signature_upload'], $user_id, $_SERVER['REMOTE_ADDR']);
            } else {
                $error = "Error al guardar el archivo.";
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Basic Validations
    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error = "Todos los campos de contraseña son obligatorios.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "La nueva contraseña y su confirmación no coinciden.";
    } elseif (strlen($new_pass) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verify current password
        $stmtPass = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmtPass->execute([$user_id]);
        $stored_hash = $stmtPass->fetchColumn();

        if (password_verify($current_pass, $stored_hash)) {
            // Update password
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmtUpdate->execute([$new_hash, $user_id])) {
                $success = "Contraseña actualizada correctamente.";
                log_audit($pdo, 'users', $user_id, 'UPDATE_PASSWORD', null, ['event' => 'password_changed'], $user_id, $_SERVER['REMOTE_ADDR']);
            } else {
                $error = "Error al actualizar la contraseña en la base de datos.";
            }
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT u.username, u.email, r.name as role_name, u.signature_path FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$page_title = 'Mi Perfil';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="main-content" style="padding: 2rem; overflow: visible;">
    <div class="page-header" style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--text-main);">Mi Perfil</h1>
        <p class="text-muted">Gestiona tu información personal y preferencias del sistema.</p>
    </div>

    <!-- Feedback Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success" style="border-radius: 12px; margin-bottom: 2rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981;">
            <i class="ph-fill ph-check-circle" style="vertical-align: middle; margin-right: 8px;"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" style="border-radius: 12px; margin-bottom: 2rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444;">
            <i class="ph-fill ph-warning-circle" style="vertical-align: middle; margin-right: 8px;"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- LEFT COLUMN: IDENTITY & QUICK ACTIONS -->
        <div class="profile-sidebar">
            <div class="profile-card identity-card">
                <div class="avatar-container">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="avatar-ring"></div>
                </div>
                
                <div class="identity-info">
                    <h2 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <span class="user-role-badge"><?php echo htmlspecialchars($user['role_name']); ?></span>
                    <p class="user-email">
                        <i class="ph ph-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                </div>

                <div class="sidebar-divider"></div>

                <!-- SIGNATURE SECTION -->
                <div class="signature-section">
                    <h3 class="section-title-sm">
                        <i class="ph-fill ph-pen-nib"></i> Firma Digital
                    </h3>
                    
                    <?php if (!empty($user['signature_path'])): ?>
                        <div class="signature-display">
                            <img src="../../assets/uploads/signatures/<?php echo $user['signature_path']; ?>?v=<?php echo time(); ?>" alt="Firma">
                            <form id="deleteSignatureForm" method="POST">
                                <input type="hidden" name="action" value="delete_signature">
                                <button type="button" id="btnDeleteSignature" class="btn-icon-delete" title="Eliminar Firma">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="signature-upload-placeholder">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="file" name="signature" id="sigInput" class="hidden-input" accept="image/png" onchange="this.form.submit()">
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

        <!-- RIGHT COLUMN: CONTENT BLOCKS -->
        <div class="profile-main">
            <!-- TABS NAVIGATION -->
            <div class="profile-tabs">
                <button class="tab-btn active" data-target="tab-customization">
                    <i class="ph ph-layout"></i> Personalización
                </button>
                <button class="tab-btn" data-target="tab-security">
                    <i class="ph ph-lock-key"></i> Seguridad
                </button>
            </div>

            <!-- TAB CONTENT: CUSTOMIZATION -->
            <div id="tab-customization" class="tab-content active">
                <div class="profile-card main-content-card">
                    <div class="section-header">
                        <div class="header-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                            <i class="ph ph-list-numbers"></i>
                        </div>
                        <div>
                            <h3 class="section-title">Orden del Menú Lateral</h3>
                            <p class="section-desc">Arrastra los módulos para personalizar tu barra de navegación.</p>
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
                            <li data-id="equipment" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-desktop"></i> Equipos</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('services', $pdo)): ?>
                            <li data-id="services" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-wrench"></i> Servicios</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('warranties', $pdo)): ?>
                            <li data-id="warranties" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-shield-check"></i> Garantías</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('new_warranty', $pdo)): ?>
                            <li data-id="new_warranty" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-plus-circle"></i> Nueva Garantía</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('tools', $pdo)): ?>
                            <li data-id="tools" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-toolbox"></i> Herramientas</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('services', $pdo) || can_access_module('warranties', $pdo) || can_access_module('history', $pdo)): ?>
                            <li data-id="requests" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-clipboard-text"></i> Solicitudes</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('reports', $pdo)): ?>
                            <li data-id="reports" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-chart-bar"></i> Reportes</div></li>
                        <?php endif; ?>
                        <?php if (can_access_module('settings', $pdo)): ?>
                            <li data-id="settings" class="modern-drag-item"><div class="drag-handle"><i class="ph ph-dots-six-vertical"></i></div> <div class="item-body"><i class="ph ph-gear"></i> Configuración</div></li>
                        <?php endif; ?>
                    </ul>

                    <div class="action-footer">
                        <button id="saveMenuOrder" class="btn-premium">
                            <i class="ph ph-floppy-disk"></i> Guardar Configuración
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: SECURITY -->
            <div id="tab-security" class="tab-content">
                <div class="profile-card main-content-card">
                    <div class="section-header">
                        <div class="header-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="ph ph-shield-check"></i>
                        </div>
                        <div>
                            <h3 class="section-title">Seguridad de la Cuenta</h3>
                            <p class="section-desc">Actualiza tu contraseña para mantener tu cuenta protegida.</p>
                        </div>
                    </div>

                    <form method="POST" id="changePasswordForm" class="modern-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label>Contraseña Actual</label>
                                <div class="input-wrapper">
                                    <i class="ph ph-key"></i>
                                    <input type="password" name="current_password" required placeholder="Ingresa tu clave actual">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-modern">
                                <label>Nueva Contraseña</label>
                                <div class="input-wrapper">
                                    <i class="ph ph-lock"></i>
                                    <input type="password" name="new_password" id="new_password" required placeholder="Mínimo 6 caracteres">
                                </div>
                            </div>
                            <div class="form-group-modern">
                                <label>Confirmar Nueva Contraseña</label>
                                <div class="input-wrapper">
                                    <i class="ph ph-shield-check"></i>
                                    <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repite la contraseña">
                                </div>
                            </div>
                        </div>

                        <div class="action-footer">
                            <button type="submit" class="btn-premium btn-danger-gradient">
                                <i class="ph ph-arrows-counter-clockwise"></i> Actualizar Credenciales
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* MODERN PROFILE ARCHITECTURE */
.profile-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 2rem;
    align-items: start;
}

.profile-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.profile-card {
    background: var(--bg-card);
    backdrop-filter: blur(15px);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

/* IDENTITY CARD */
.identity-card {
    padding-top: 3rem;
    text-align: center;
}

.avatar-container {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 1.5rem;
}

.avatar-circle {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    position: relative;
    z-index: 2;
}

.avatar-ring {
    position: absolute;
    inset: -6px;
    border: 2px solid #6366f1;
    border-radius: 50%;
    opacity: 0.3;
}

.user-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
    color: var(--text-main);
}

.user-role-badge {
    display: inline-block;
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
}

.user-email {
    color: var(--text-muted);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.sidebar-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, var(--border-color), transparent);
    margin: 2rem 0;
}

/* SIGNATURE SECTION */
.section-title-sm {
    font-size: 0.9rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.signature-display {
    position: relative;
    background: white;
    border-radius: 12px;
    padding: 1rem;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.signature-display img {
    max-width: 100%;
    max-height: 80px;
}

.btn-icon-delete {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 30px;
    height: 30px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
    transition: transform 0.2s;
}

.btn-icon-delete:hover { transform: scale(1.1); }

.upload-zone {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s;
}

.upload-zone:hover {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.05);
}
 
.upload-zone i { font-size: 2rem; color: #6366f1; }
.upload-zone span { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }

/* TABS NAVIGATION */
.profile-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    background: rgba(255,255,255,0.02);
    padding: 0.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.tab-btn {
    flex: 1;
    background: transparent;
    border: none;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    transition: all 0.3s;
}

.tab-btn i { font-size: 1.2rem; }

.tab-btn.active {
    background: var(--bg-card);
    color: #6366f1;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* TAB CONTENT */
.tab-content { display: none; width: 100%; animation: fadeIn 0.4s easeOut; }
.tab-content.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* MAIN CONTENT CARD */
.section-header {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.9rem; color: var(--text-muted); }

/* MODERN MENU LIST */
.modern-menu-list {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.modern-drag-item {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: grab;
    transition: all 0.2s;
}

.modern-drag-item:hover {
    border-color: #6366f1;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.item-body { display: flex; align-items: center; gap: 0.75rem; font-weight: 600; font-size: 0.9rem; }
.item-body i { font-size: 1.2rem; color: #6366f1; }

/* MODERN FORM */
.modern-form { display: flex; flex-direction: column; gap: 1.5rem; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

.form-group-modern label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.input-wrapper { position: relative; }
.input-wrapper i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1.2rem;
}

.input-wrapper input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.8rem 1rem 0.8rem 3rem;
    color: var(--text-main);
    font-size: 0.95rem;
    transition: all 0.3s;
}

.input-wrapper input:focus {
    outline: none;
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.1);
}

/* BUTTONS */
.btn-premium {
    background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
    color: white !important;
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
}

.btn-premium:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(99, 102, 241, 0.4); }

.btn-danger-gradient { background: linear-gradient(135deg, #ef4444, #f87171); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3); }
.btn-danger-gradient:hover { box-shadow: 0 12px 25px rgba(239, 68, 68, 0.4); }

.action-footer { text-align: right; margin-top: 1rem; }

.hidden-input { display: none; }
</style>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- TABS LOGIC ---
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-target');

                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById(target).classList.add('active');
            });
        });

        // --- SIGNATURE LOGIC ---
        const btnDelete = document.getElementById('btnDeleteSignature');
        if(btnDelete) {
            btnDelete.addEventListener('click', function() {
                Swal.fire({
                    title: '¿Eliminar firma?',
                    text: "Esta acción no se puede deshacer. Tu firma dejará de aparecer en los documentos.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    background: '#1e293b', 
                    color: '#fff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteSignatureForm').submit();
                    }
                });
            });
        }

        // --- SORTABLE MENU LOGIC ---
        const el = document.getElementById('sortable-menu');
        if(el) {
            const sortable = Sortable.create(el, {
                animation: 250,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag'
            });

            document.getElementById('saveMenuOrder').addEventListener('click', function() {
                const order = sortable.toArray(); 
                
                fetch('save_navbar_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: order })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Configuración Guardada!',
                            text: 'El nuevo orden del menú se aplicará en tu próxima sesión.',
                            timer: 2000,
                            showConfirmButton: false,
                            background: '#1e293b',
                            color: '#fff'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', 'No se pudo guardar el orden', 'error');
                    }
                });
            });
        }

        // --- PASSWORD CHANGE VALIDATION ---
        const changePassForm = document.getElementById('changePasswordForm');
        if(changePassForm) {
            changePassForm.addEventListener('submit', function(e) {
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;

                if(newPass.length < 6) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Contraseña muy corta',
                        text: 'La nueva contraseña debe tener al menos 6 caracteres para mayor seguridad.',
                        background: '#1e293b',
                        color: '#fff'
                    });
                    return;
                }

                if(newPass !== confirmPass) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de coincidencia',
                        text: 'La nueva contraseña y su confirmación no coinciden. Por favor verifícalas.',
                        background: '#1e293b',
                        color: '#fff'
                    });
                    return;
                }
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
