<?php
// modules/clients/edit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('clients', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch client data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    die("Cliente no encontrado.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $tax_id = clean($_POST['tax_id']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);

    if (empty($name)) {
        $error = "El nombre es obligatorio.";
    } else {
        try {
            // Check for duplicate tax_id ONLY if it's not the same client (optional check, skipped for now or simple uniqueness)
            
            $stmt = $pdo->prepare("UPDATE clients SET name = ?, tax_id = ?, phone = ?, email = ?, address = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$name, $tax_id, $phone, $email, $address, $id])) {
                // Audit Log
                log_audit($pdo, 'clients', $id, 'UPDATE', json_encode($client), $_POST);
                
                $success = "Cliente actualizado correctamente.";
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                $client = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

$page_title = 'Editar Cliente';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php'; // Navbar
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
        <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem;"><i class="ph ph-arrow-left"></i></a>
        <h1>Editar Cliente</h1>
    </div>

    <?php if($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <!-- Full Name -->
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Nombre Completo *</label>
                    <div class="input-group">
                        <input type="text" name="name" class="form-control" placeholder="Ej. Juan Pérez" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                        <i class="ph ph-user input-icon"></i>
                    </div>
                </div>

                <!-- DNI/RUC -->
                <div class="form-group">
                    <label class="form-label">DNI / RUC</label>
                    <div class="input-group">
                        <input type="text" name="tax_id" class="form-control" placeholder="Número de identificación" value="<?php echo htmlspecialchars($client['tax_id']); ?>">
                        <i class="ph ph-identification-card input-icon"></i>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <div class="input-group">
                        <input type="text" name="phone" class="form-control" placeholder="Ej. 5555-4444" value="<?php echo htmlspecialchars($client['phone']); ?>">
                        <i class="ph ph-phone input-icon"></i>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Correo Electrónico</label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" placeholder="cliente@ejemplo.com" value="<?php echo htmlspecialchars($client['email']); ?>">
                        <i class="ph ph-envelope input-icon"></i>
                    </div>
                </div>

                <!-- Address -->
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Dirección</label>
                    <div class="input-group">
                        <input type="text" name="address" class="form-control" placeholder="Dirección completa" value="<?php echo htmlspecialchars($client['address']); ?>">
                        <i class="ph ph-map-pin input-icon"></i>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
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
