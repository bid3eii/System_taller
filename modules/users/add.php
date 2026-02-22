<?php
// modules/users/add.php
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

// Fetch Roles for Dropdown
// Fetch Roles for Dropdown
try {
    $stmtRoles = $pdo->query("SELECT * FROM roles WHERE id > 1 ORDER BY id ASC");
    $roles = $stmtRoles->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar roles: " . $e->getMessage();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean($_POST['username']);
    $email = clean($_POST['email']);
    $role_id = clean($_POST['role_id']);
    $password = $_POST['password'];
    $status = clean($_POST['status']);

    if (empty($username) || empty($password) || empty($role_id) || empty($email)) {
        $error = "Por favor complete los campos obligatorios.";
    } else {
        // Check if username or email already exists
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $error = "El nombre de usuario o correo electrónico ya está en uso.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password_hash, $role_id, $status])) {
                    $success = "Usuario creado exitosamente.";
                } else {
                    $error = "Error al crear el usuario.";
                }
            } catch (PDOException $e) {
                $error = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Agregar Usuario';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1>Nuevo Usuario</h1>
            <p class="text-muted">Registre un nuevo usuario en el sistema.</p>
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
                    <input type="text" name="username" class="form-control" required placeholder="Ej. juanperez">
                    <i class="ph ph-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Correo Electrónico <span style="color: var(--danger)">*</span></label>
                <div class="input-group">
                    <input type="email" name="email" class="form-control" required placeholder="Ej. juan@taller.com">
                    <i class="ph ph-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña <span style="color: var(--danger)">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" required placeholder="********">
                    <i class="ph ph-lock input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Rol <span style="color: var(--danger)">*</span></label>
                <div class="input-group">
                    <select name="role_id" class="form-control" required style="padding-left: 3rem;">
                        <option value="">Seleccione un rol...</option>
                        <?php foreach($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="ph ph-shield-star input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Estado</label>
                <div class="input-group">
                    <select name="status" id="statusSelect" class="form-control" style="padding-left: 3rem;">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                    <i id="statusIcon" class="ph ph-toggle-right input-icon" style="color: var(--success);"></i>
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

            <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check"></i> Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
