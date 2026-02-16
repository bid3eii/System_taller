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
    
    // RE-ENTRY LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'reentry') {
        // Permission Check
        if (!can_access_module('re_enter_workshop', $pdo)) {
            die("Acceso denegado.");
        }

        $reentry_reason = clean($_POST['reentry_reason'] ?? '');
        if (empty($reentry_reason)) {
            $error = "Debe especificar un motivo para el reingreso.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update Order Status -> 'pending' (En Espera)
                // Reset exit_date
                $stmt = $pdo->prepare("UPDATE service_orders SET status = 'pending', exit_date = NULL WHERE id = ?");
                $stmt->execute([$id]);

                // Log History
                $history_note = "REINGRESO A TALLER. Motivo: $reentry_reason";
                $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', ?, ?, ?)");
                $stmtH->execute([$id, $history_note, $_SESSION['user_id'], get_local_datetime()]);

                $pdo->commit();
                
                // Redirect to Service View
                header("Location: ../../modules/services/view.php?id=" . $id);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al procesar reingreso: " . $e->getMessage();
            }
        }

    } else {
        // STANDARD DELIVERY LOGIC
        $receiver_name = clean($_POST['receiver_name']);
        $receiver_id = clean($_POST['receiver_id']);
        $delivery_notes = clean($_POST['delivery_notes']);

        try {
            $pdo->beginTransaction();

            // SNAPSHOT SIGNATURE: Fetch current user's signature path
            $stmtSig = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
            $stmtSig->execute([$_SESSION['user_id']]);
            $currentUserSig = $stmtSig->fetchColumn();

            // Update Order
            $stmt = $pdo->prepare("UPDATE service_orders SET status = 'delivered', exit_date = NOW(), authorized_by_user_id = ?, exit_signature_path = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $currentUserSig, $id]);

            // Log History (with delivery details)
            $history_note = "Entregado a: $receiver_name ($receiver_id). Notas: $delivery_notes";
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'delivered', ?, ?, ?)");
            $stmtH->execute([$id, $history_note, $_SESSION['user_id'], get_local_datetime()]);

            $pdo->commit();
            header("Location: print_delivery.php?id=" . $id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al procesar la entrega: " . $e->getMessage();
        }
    }
}

$page_title = 'Confirmar Entrega - Orden #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: start;">
        <div>
            <a href="exit.php" style="color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <i class="ph ph-arrow-left"></i> Volver a Salidas
            </a>
            <h1>Orden de Salida</h1>
            <p class="text-muted">Confirma los detalles de la entrega del equipo.</p>
        </div>
        
        <?php if(can_access_module('re_enter_workshop', $pdo)): ?>
        <div>
            <button type="button" onclick="openReentryModal()" class="btn" style="background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px solid var(--warning);">
                <i class="ph ph-arrow-u-up-left"></i> Reingresar al Taller
            </button>
        </div>
        <?php endif; ?>
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

        <!-- RE-ENTRY MODAL -->
        <div id="reentryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
            <div style="background: var(--bg-card); padding: 2rem; border-radius: 12px; width: 100%; max-width: 500px; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
                <h3 style="margin-top: 0; margin-bottom: 1rem; color: var(--warning); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-warning"></i> Confirmar Reingreso
                </h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.5;">
                    Esta acción cancelará la salida y devolverá el equipo a estado <strong>"En Revisión"</strong> manteniendo el mismo número de caso (#<?php echo $id; ?>).
                </p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="reentry">
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Motivo del Reingreso *</label>
                        <textarea name="reentry_reason" class="form-control" rows="3" required placeholder="Explique por qué regresa el equipo (falla persistente, cliente insatisfecho, etc.)"></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                        <button type="button" onclick="closeReentryModal()" class="btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary);">
                            Cancelar
                        </button>
                        <button type="submit" class="btn" style="background: var(--warning); color: #000; border: none; font-weight: 600;">
                            Confirmar Reingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openReentryModal() {
                const modal = document.getElementById('reentryModal');
                modal.style.display = 'flex';
                // Focus textarea
                setTimeout(() => modal.querySelector('textarea').focus(), 100);
            }

            function closeReentryModal() {
                document.getElementById('reentryModal').style.display = 'none';
            }
            
            // Close on click outside
            document.getElementById('reentryModal').addEventListener('click', function(e) {
                if(e.target === this) closeReentryModal();
            });
        </script>

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
