<?php
// modules/tools/add.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Agregar Herramienta';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $status = $_POST['status'];

    if (empty($name)) {
        $error = 'El nombre es obligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO tools (name, description, quantity, status) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $quantity, $status])) {
                $success = 'Herramienta agregada correctamente.';
                // Redirect after success
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            } else {
                $error = 'Error al guardar la herramienta.';
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <a href="index.php" class="btn btn-secondary btn-icon"><i class="ph ph-arrow-left"></i></a>
        <div>
            <h1 style="margin: 0;">Nueva Herramienta</h1>
            <p class="text-muted" style="margin: 0;">Agrega una nueva herramienta al inventario.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div style="padding: 1.5rem;">
            <form method="POST">
                <div class="form-group box-input">
                    <label class="form-label">Nombre de la Herramienta *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Ej: Taladro Inalámbrico">
                </div>

                <div class="form-group box-input">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Detalles adicionales..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group box-input">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="0" required>
                    </div>

                    <div class="form-group box-input">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-control">
                            <option value="available">Disponible</option>
                            <option value="assigned">Asignado</option>
                            <option value="maintenance">Mantenimiento</option>
                            <option value="lost">Extraviado</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Herramienta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
