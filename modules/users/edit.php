<?php
// modules/users/edit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permissions
// Check permissions
if (!can_access_module('users', $pdo)) {
    die("Acceso denegado.");
}

$error = '';
$success = '';
$id = isset($_GET['id']) ? clean($_GET['id']) : null;

if (!$id) {
    header("Location: ../settings/index.php?tab=users");
    exit;
}

// Fetch Roles (Exclude SuperAdmin ID 1)
try {
    $stmtRoles = $pdo->query("SELECT * FROM roles WHERE id > 1 ORDER BY id ASC");
    $roles = $stmtRoles->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar roles: " . $e->getMessage();
}

// Fetch User Data
try {
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$id]);
    $user = $stmtUser->fetch();
    if (!$user) {
        die("Usuario no encontrado.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $role_id = clean($_POST['role_id']);
    $password = $_POST['password']; // Optional
    $status = clean($_POST['status']);

    if (empty($username) || empty($role_id) || empty($email)) {
        $error = "Por favor complete los campos obligatorios.";
    } else {
        // Check if username/email exists for OTHER users
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$username, $email, $id]);
        if ($stmtCheck->fetchColumn() > 0) {
            $error = "El nombre de usuario o correo electrónico ya está en uso por otro usuario.";
        } else {
            try {
                if (!empty($password)) {
                    // Update validation with password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role_id = ?, status = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role_id, $status, $password_hash, $id]);
                } else {
                    // Update without password
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role_id, $status, $id]);
                }
                
                $success = "Usuario actualizado exitosamente.";
                // Refresh user data
                $stmtUser->execute([$id]);
                $user = $stmtUser->fetch();
                
                // (Permissions saving logic removed - centralized in Settings)

                // Redirect to user list as requested
                header("Location: ../settings/index.php?tab=users&success=1");
                exit;

            } catch (PDOException $e) {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Editar Usuario';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1>Editar Usuario</h1>
            <p class="text-muted">Modificar datos de acceso y roles.</p>
        </div>
        <a href="../settings/index.php?tab=users" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                     <h3 class="mb-3" style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Datos de Cuenta</h3>
                     
                     <div class="form-group">
                        <label class="form-label">Nombre de Usuario <span style="color: var(--danger)">*</span></label>
                        <div class="input-group">
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($user['username']); ?>">
                            <i class="ph ph-user input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Correo Electrónico <span style="color: var(--danger)">*</span></label>
                        <div class="input-group">
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                            <i class="ph ph-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para mantener">
                            <i class="ph ph-lock input-icon"></i>
                        </div>
                    </div>
                </div>

                <div>
                     <h3 class="mb-3" style="font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Rol y Estado</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Rol <span style="color: var(--danger)">*</span></label>
                        <div class="input-group">
                            <select name="role_id" class="form-control" required style="padding-left: 3rem;">
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="ph ph-shield-star input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <div class="input-group">
                            <select name="status" id="statusSelect" class="form-control" style="padding-left: 3rem;">
                                <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                            <i id="statusIcon" class="ph <?php echo $user['status'] == 'active' ? 'ph-toggle-right' : 'ph-toggle-left'; ?> input-icon" style="<?php echo $user['status'] == 'active' ? 'color: var(--success);' : ''; ?>"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Script for Dynamic Icon -->
            <script>
                document.getElementById('statusSelect').addEventListener('change', function() {
                    const icon = document.getElementById('statusIcon');
                    if(this.value === 'active') {
                        icon.classList.remove('ph-toggle-left');
                        icon.classList.add('ph-toggle-right');
                        icon.style.color = 'var(--success)';
                    } else {
                        icon.classList.remove('ph-toggle-right');
                        icon.classList.add('ph-toggle-left');
                        icon.style.color = ''; // Reset color
                    }
                });
            </script>


            <!-- MODULE PERMISSIONS SECTION REMOVED -->


            <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem; position: sticky; bottom: 0; background: var(--bg-card); border-top: 1px solid var(--border-color); padding: 1.5rem; margin: 0 -1.5rem -1.5rem -1.5rem; z-index: 100; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <a href="../settings/index.php?tab=users" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="min-width: 150px;">
                    <i class="ph ph-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>



<?php
require_once '../../includes/footer.php';
?>
