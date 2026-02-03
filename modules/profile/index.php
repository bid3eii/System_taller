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

<div class="main-content">
    <div class="card">
        <h2 class="mb-4">Mi Perfil</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row" style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <!-- User Info -->
            <div style="flex: 1; min-width: 300px;">
                <h3 class="mb-3">Información Personal</h3>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role_name']); ?>" disabled>
                </div>
                <!-- Add more fields if needed -->
            </div>

            <!-- Signature Upload -->
            <div style="flex: 1; min-width: 300px; border-left: 1px solid var(--border-color); padding-left: 2rem;">
                <h3 class="mb-3">Firma Digital</h3>
                <p class="text-muted text-sm mb-3">Sube tu firma en formato <strong>PNG</strong> (fondo transparente recomendado) para que aparezca automáticamente en los documentos que generes.</p>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Archivo (PNG)</label>
                        <input type="file" name="signature" class="form-control" accept="image/png" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-upload-simple"></i> Subir Firma
                    </button>
                </form>

                <?php if (!empty($user['signature_path'])): ?>
                    <div class="mt-4">
                        <label class="form-label">Firma Actual</label>
                        <div style="border: 1px dashed var(--border-color); padding: 1.5rem; border-radius: 12px; text-align: center; background: rgba(255,255,255,0.02); margin-bottom: 1rem;">
                            <img src="../../assets/uploads/signatures/<?php echo $user['signature_path']; ?>?v=<?php echo time(); ?>" alt="Firma" style="max-height: 120px; max-width: 100%; object-fit: contain;">
                        </div>
                        
                        <div class="text-end">
                            <form id="deleteSignatureForm" method="POST">
                                <input type="hidden" name="action" value="delete_signature">
                                <button type="button" id="btnDeleteSignature" class="btn btn-outline-danger" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.75rem;">
                                    <i class="ph ph-trash"></i> Eliminar Firma
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Navbar Customization -->
            <div style="flex: 1; min-width: 300px; border-left: 1px solid var(--border-color); padding-left: 2rem;">
                <h3 class="mb-3">Personalizar Menú</h3>
                <p class="text-muted text-sm mb-3">Arrastra los elementos para cambiar el orden del menú.</p>
                
                <ul id="sortable-menu" style="list-style: none; padding: 0;">
                    <!-- Items defined in JS or PHP -->
                    <li data-id="clients" class="draggable-item"><i class="ph ph-users"></i> Clientes</li>
                    <li data-id="equipment" class="draggable-item"><i class="ph ph-desktop"></i> Equipos</li>
                    <li data-id="new_warranty" class="draggable-item"><i class="ph ph-plus-circle"></i> Registro de Garantía</li>
                    <li data-id="tools" class="draggable-item"><i class="ph ph-wrench"></i> Herramientas</li>
                    <li data-id="requests" class="draggable-item"><i class="ph ph-clipboard-text"></i> Solicitud</li>
                    <li data-id="reports" class="draggable-item"><i class="ph ph-chart-bar"></i> Reportes</li>
                </ul>
                
                <style>
                    .draggable-item {
                        padding: 10px;
                        margin-bottom: 8px;
                        background: var(--bg-card);
                        border: 1px solid var(--border-color);
                        border-radius: 6px;
                        cursor: grab;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    .draggable-item:active { cursor: grabbing; }
                    .draggable-item i { width: 20px; text-align: center; }
                    .sortable-ghost { opacity: 0.4; background: #e2e8f0; }
                </style>
                
                <button id="saveMenuOrder" class="btn btn-primary w-100 mt-3">
                    <i class="ph ph-floppy-disk"></i> Guardar Orden
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
