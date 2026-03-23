<?php
// modules/tools/edit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$tool = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM tools WHERE id = ?");
    $stmt->execute([$id]);
    $tool = $stmt->fetch();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$tool) {
    die("Herramienta no encontrada.");
}

$page_title = 'Editar Herramienta';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $status = $_POST['status'];
    $physical_condition = $_POST['physical_condition'] ?? 'good';

    if (empty($name)) {
        $error = 'El nombre es obligatorio.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE tools SET name = ?, description = ?, quantity = ?, status = ?, physical_condition = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $quantity, $status, $physical_condition, $id])) {
                $success = 'Herramienta actualizada correctamente.';
                // Refresh data
                $tool['name'] = $name;
                $tool['description'] = $description;
                $tool['quantity'] = $quantity;
                $tool['status'] = $status;
                
                // Redirect
                 echo "<script>window.location.href = 'index.php';</script>";
                 exit;
            } else {
                $error = 'Error al actualizar la herramienta.';
            }
        } catch (PDOException $e) {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <a href="index.php" class="btn btn-secondary btn-icon"><i class="ph ph-arrow-left"></i></a>
        <div>
            <h1 style="margin: 0;">Editar Herramienta</h1>
            <p class="text-muted" style="margin: 0;">Modifica los datos de la herramienta.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <div style="padding: 1.5rem;">
            <form method="POST">
                <div class="form-group box-input">
                    <label class="form-label">Nombre de la Herramienta *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($tool['name']); ?>">
                </div>

                <div class="form-group box-input">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($tool['description']); ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group box-input">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="quantity" class="form-control" value="<?php echo htmlspecialchars($tool['quantity']); ?>" min="0" required>
                    </div>

                    <div class="form-group box-input">
                        <label class="form-label">Disponibilidad</label>
                        <select name="status" class="form-control">
                            <option value="available" <?php echo $tool['status'] === 'available' ? 'selected' : ''; ?>>Disponible</option>
                            <option value="assigned" <?php echo $tool['status'] === 'assigned' ? 'selected' : ''; ?>>Asignado</option>
                            <option value="maintenance" <?php echo $tool['status'] === 'maintenance' ? 'selected' : ''; ?>>Mantenimiento</option>
                            <option value="lost" <?php echo $tool['status'] === 'lost' ? 'selected' : ''; ?>>Extraviado</option>
                        </select>
                    </div>
                    
                    <div class="form-group box-input">
                        <label class="form-label">Condición Física</label>
                        <select name="physical_condition" class="form-control">
                            <option value="new" <?php echo ($tool['physical_condition'] ?? '') === 'new' ? 'selected' : ''; ?>>Nuevo (Sin usar)</option>
                            <option value="good" <?php echo ($tool['physical_condition'] ?? 'good') === 'good' ? 'selected' : ''; ?>>Bueno (Operativo)</option>
                            <option value="fair" <?php echo ($tool['physical_condition'] ?? '') === 'fair' ? 'selected' : ''; ?>>Regular (Desgastado)</option>
                            <option value="bad" <?php echo ($tool['physical_condition'] ?? '') === 'bad' ? 'selected' : ''; ?>>Malo (Requiere Cambio)</option>
                            <option value="damaged" <?php echo ($tool['physical_condition'] ?? '') === 'damaged' ? 'selected' : ''; ?>>Dañado (Inoperativo)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
