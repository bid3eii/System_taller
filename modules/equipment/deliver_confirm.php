<?php
// modules/equipment/deliver_confirm.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID no especificado.");
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.name as client_name, c.phone,
        e.brand, e.model, e.serial_number, e.type as equipment_type
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    WHERE so.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die("Orden no encontrada.");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_name = clean($_POST['receiver_name']);
    $receiver_id = clean($_POST['receiver_id']);
    $delivery_notes = clean($_POST['delivery_notes']);

    try {
        $pdo->beginTransaction();

        // Update Order
        $stmt = $pdo->prepare("UPDATE service_orders SET status = 'delivered', exit_date = NOW(), authorized_by_user_id = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);

        // Log History (with delivery details)
        $history_note = "Entregado a: $receiver_name ($receiver_id). Notas: $delivery_notes";
        $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id) VALUES (?, 'delivered', ?, ?)");
        $stmtH->execute([$id, $history_note, $_SESSION['user_id']]);

        $pdo->commit();
        header("Location: print_delivery.php?id=" . $id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al procesar la entrega: " . $e->getMessage();
    }
}

$page_title = 'Confirmar Entrega - Orden #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="exit.php" style="color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <i class="ph ph-arrow-left"></i> Volver a Salidas
        </a>
        <h1>Orden de Salida</h1>
        <p class="text-muted">Confirma los detalles de la entrega del equipo.</p>
    </div>

    <?php if(isset($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <!-- Summary Section -->
        <div style="background: var(--bg-body); padding: 1.5rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid var(--border-color);">
            <h3 style="font-size: 1.1rem; color: var(--primary-400); margin-bottom: 1rem;">Resumen del Equipo</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <span class="text-muted d-block text-sm">Cliente</span>
                    <span class="font-medium"><?php echo htmlspecialchars($order['client_name']); ?></span>
                </div>
                <div>
                    <span class="text-muted d-block text-sm">Equipo</span>
                    <span class="font-medium"><?php echo htmlspecialchars($order['equipment_type'] . ' ' . $order['brand'] . ' ' . $order['model']); ?></span>
                </div>

            </div>
        </div>

        <form method="POST">
            <h3 class="mb-4">Datos de Quien Recibe</h3>
            
            <div class="form-group">
                <label class="form-label">Nombre Completo *</label>
                <div class="input-group">
                    <input type="text" name="receiver_name" class="form-control" placeholder="Nombre de la persona que retira" required value="<?php echo htmlspecialchars($order['client_name']); ?>">
                    <i class="ph ph-user input-icon"></i>
                </div>
                <small class="text-muted">Por defecto se sugiere el nombre del cliente titular.</small>
            </div>

            <div class="form-group">
                <label class="form-label">DNI / Identificación *</label>
                <div class="input-group">
                    <input type="text" name="receiver_id" class="form-control" placeholder="Número de documento" required>
                    <i class="ph ph-identification-card input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notas de Entrega</label>
                <textarea name="delivery_notes" class="form-control" rows="3" placeholder="Comentarios adicionales sobre la entrega..."></textarea>
            </div>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="exit.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check-circle"></i> Confirmar Entrega
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
