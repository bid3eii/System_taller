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

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
        
        <!-- LEFT COLUMN: IDENTITY -->
        <div class="profile-card">
            <div class="text-center mb-4">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($user['username']); ?></h2>
                <div class="badge badge-role"><?php echo htmlspecialchars($user['role_name']); ?></div>
            </div>

            <div class="info-group">
                <label>Email</label>
                <div class="info-value">
                    <i class="ph ph-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                </div>
            </div>

            <hr style="border-color: var(--border-color); opacity: 0.5; margin: 1.5rem 0;">

            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                <i class="ph-fill ph-pen-nib" style="color: var(--primary);"></i> Firma Digital
            </h3>
            
            <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 1rem;">
                Aparecerá en los documentos que generes (Diagnósticos, Entradas, Salidas).
            </p>

            <?php if (!empty($user['signature_path'])): ?>
                <div class="signature-preview mb-3">
                    <img src="../../assets/uploads/signatures/<?php echo $user['signature_path']; ?>?v=<?php echo time(); ?>" alt="Firma">
                </div>
                <form id="deleteSignatureForm" method="POST">
                    <input type="hidden" name="action" value="delete_signature">
                    <button type="button" id="btnDeleteSignature" class="btn btn-outline-danger w-100 btn-sm-custom">
                        <i class="ph ph-trash"></i> Eliminar Firma
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div style="position: relative; margin-bottom: 1rem;">
                        <input type="file" name="signature" id="sigInput" class="form-control" accept="image/png" required style="display: none;" onchange="this.form.submit()">
                        <label for="sigInput" class="btn btn-secondary w-100 btn-sm-custom" style="cursor: pointer;">
                            <i class="ph ph-upload-simple"></i> Subir Firma (PNG)
                        </label>
                    </div>
                </form>
                <div class="text-xs text-center text-muted">Fondo transparente recomendado</div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: INTERFACE -->
        <div class="profile-card">
            <h3 class="mb-4" style="font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                <i class="ph-fill ph-layout" style="color: #8b5cf6;"></i> Personalización del Sistema
            </h3>

            <div class="mb-4">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Orden del Menú Lateral</h4>
                <p class="text-muted" style="font-size: 0.9rem;">Arrastra y suelta los elementos para organizar tu menú de navegación según tu flujo de trabajo.</p>
            </div>

            <ul id="sortable-menu" class="menu-list">
                <!-- Keep IDs consistent with original logic -->
                <?php if (can_access_module('dashboard', $pdo)): ?>
                    <li data-id="dashboard" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-squares-four"></i> Dashboard</div></li>
                <?php endif; ?>

                <?php if (can_access_module('clients', $pdo)): ?>
                    <li data-id="clients" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-users"></i> Clientes</div></li>
                <?php endif; ?>

                <?php if (can_access_module('equipment', $pdo)): ?>
                    <li data-id="equipment" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-desktop"></i> Equipos</div></li>
                <?php endif; ?>

                <?php if (can_access_module('services', $pdo)): ?>
                    <li data-id="services" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-wrench"></i> Servicios</div></li>
                <?php endif; ?>

                <?php if (can_access_module('warranties', $pdo)): ?>
                    <li data-id="warranties" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-shield-check"></i> Garantías</div></li>
                <?php endif; ?>

                <?php if (can_access_module('new_warranty', $pdo)): ?>
                    <li data-id="new_warranty" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-plus-circle"></i> Nueva Garantía</div></li>
                <?php endif; ?>

                <?php if (can_access_module('tools', $pdo)): ?>
                    <li data-id="tools" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-wrench"></i> Herramientas</div></li>
                <?php endif; ?>

                <?php if (can_access_module('services', $pdo) || can_access_module('warranties', $pdo) || can_access_module('history', $pdo)): ?>
                    <li data-id="requests" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-clipboard-text"></i> Solicitudes</div></li>
                <?php endif; ?>

                <?php if (can_access_module('reports', $pdo)): ?>
                    <li data-id="reports" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-chart-bar"></i> Reportes</div></li>
                <?php endif; ?>

                <?php if (can_access_module('settings', $pdo)): ?>
                    <li data-id="settings" class="draggable-item"><div class="drag-handle"><i class="ph-fill ph-dots-six-vertical"></i></div> <div class="item-content"><i class="ph ph-gear"></i> Configuración</div></li>
                <?php endif; ?>
            </ul>

            <div style="margin-top: 2rem; text-align: right;">
                <button id="saveMenuOrder" class="btn btn-primary btn-lg-custom">
                    <i class="ph-bold ph-floppy-disk"></i> Guardar Orden
                </button>
            </div>
        </div>

    </div>
</div>

<style>
/* PROFILE STYLES */
.profile-card {
    background: var(--bg-card); /* Use defined variable */
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

/* Light Mode Overrides for Profile Card */
body.light-mode .profile-card {
    background: #ffffff;
    border: 1px solid var(--slate-300); /* Stronger border */
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* Stronger shadow */
}

.avatar-circle {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    color: white;
    box-shadow: 0 10px 25px rgba(var(--primary-rgb), 0.3);
}

.badge-role {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--border-color);
    padding: 0.35rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

body.light-mode .badge-role {
    background: var(--slate-100);
    border-color: var(--slate-300);
    color: var(--slate-700);
}

.info-group {
    margin-bottom: 1rem;
}

.info-group label {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.25rem;
    font-weight: 700;
}

.info-value {
    font-size: 0.95rem;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.signature-preview {
    background: white;
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}

body.light-mode .signature-preview {
    border-color: var(--slate-300);
    background: var(--slate-50);
}

.signature-preview img {
    max-width: 100%;
    max-height: 80px;
    object-fit: contain;
}

/* MENU LIST STYLES */
.menu-list {
    list-style: none; 
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.draggable-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: grab;
    transition: all 0.2s ease;
    user-select: none;
}

/* Light Mode Overrides for Draggable Items */
body.light-mode .draggable-item {
    background: #f8fafc; /* Slate 50 */
    border: 1px solid var(--slate-300); /* Visible border */
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.draggable-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

body.light-mode .draggable-item:hover {
    background: white;
    border-color: var(--primary-500);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.draggable-item:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.drag-handle {
    color: var(--text-muted);
    cursor: grab;
}

.item-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    font-size: 0.95rem;
}

.item-content i {
    font-size: 1.2rem;
    color: var(--primary);
}

.sortable-ghost {
    opacity: 0.4;
    background: rgba(var(--primary-rgb), 0.1);
    border-style: dashed;
}

.btn-lg-custom {
    padding: 0.8rem 2rem;
    font-size: 1rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
}

.btn-sm-custom {
    padding: 0.6rem 1rem;
    border-radius: 10px;
    font-size: 0.9rem;
}
</style>

<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ... (Existing Signature Logic) ...
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
        // Simple check to ensure element exists before init
        if(el) {
            const sortable = Sortable.create(el, {
                animation: 150,
                ghostClass: 'sortable-ghost'
            });

            document.getElementById('saveMenuOrder').addEventListener('click', function() {
                const order = sortable.toArray(); // Get array of data-ids
                
                fetch('save_navbar_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ order: order })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Orden Guardado',
                            text: 'El menú se actualizará en la próxima recarga.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', 'No se pudo guardar el orden', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error de red', 'error');
                });
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
