<?php
// modules/users/edit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permissions
if ($_SESSION['role_id'] != 1) {
    die("Acceso denegado.");
}

$error = '';
$success = '';
$id = isset($_GET['id']) ? clean($_GET['id']) : null;

if (!$id) {
    header("Location: ../settings/index.php?tab=users");
    exit;
}

// Fetch Roles
try {
    $stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
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

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                <?php echo $success; ?>
                <div style="margin-top: 0.5rem;">
                    <a href="../settings/index.php?tab=users" style="color: var(--primary-500); text-decoration: none; font-weight: 600;">Volver a la lista</a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
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
                    <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para mantener la actual">
                    <i class="ph ph-lock input-icon"></i>
                </div>
                <small class="text-muted" style="margin-top: 0.25rem; display: block;">Solo rellene este campo si desea cambiar la contraseña.</small>
            </div>

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
                    <select name="status" class="form-control" style="padding-left: 3rem;">
                        <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactive" <?php echo $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                    <i class="ph ph-toggle-left input-icon"></i>
                </div>
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
