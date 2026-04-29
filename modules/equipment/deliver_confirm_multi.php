<?php
// modules/equipment/deliver_confirm_multi.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../modules/auth/login.php");
    exit;
}

$ids = $_GET['ids'] ?? null;
if (!$ids) {
    die("IDs no especificados.");
}

// Parse IDs
$idArray = array_map('intval', explode(',', $ids));
if (empty($idArray)) {
    die("IDs inválidos.");
}

// Fetch All Orders
$placeholders = implode(',', array_fill(0, count($idArray), '?'));
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.id as client_id,
        c.name as contact_client_name, 
        c.phone,
        e.brand, 
        e.model, 
        e.serial_number, 
        e.type as equipment_type,
        co.name as registered_owner_name
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN clients co ON e.client_id = co.id
    WHERE so.id IN ($placeholders)
    ORDER BY so.id ASC
");
$stmt->execute($idArray);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    die("No se encontraron órdenes.");
}

// Logic to determine the Actual Client Name (Prioritizing Business/Owner)
foreach ($orders as &$o) {
    $o['final_client_name'] = trim(!empty($o['owner_name']) ? $o['owner_name'] : 
                              (!empty($o['registered_owner_name']) ? $o['registered_owner_name'] : 
                              $o['contact_client_name']));
}
unset($o);

$clientData = $orders[0];
$finalClientDisplayName = $clientData['final_client_name'];
$equipmentCount = count($orders);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_name = clean($_POST['receiver_name']);
    $receiver_id = clean($_POST['receiver_id']);
    $delivery_notes = clean($_POST['delivery_notes']);
    
    $has_driver = isset($_POST['has_driver']) ? 1 : 0;
    $driver_name = clean($_POST['driver_name'] ?? '');
    $driver_id = clean($_POST['driver_id'] ?? '');

    try {
        $pdo->beginTransaction();

        // SNAPSHOT SIGNATURE: Fetch current user's signature path
        $stmtSig = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
        $stmtSig->execute([$_SESSION['user_id']]);
        $currentUserSig = $stmtSig->fetchColumn();

        // Update all orders
        foreach ($orders as $order) {
            // Update Order
            $stmt = $pdo->prepare("UPDATE service_orders SET status = 'delivered', exit_date = NOW(), authorized_by_user_id = ?, exit_signature_path = ? WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $currentUserSig, $order['id']]);

            // Log History
            if ($has_driver && !empty($driver_name)) {
                $history_note = "Entregado a: $receiver_name ($receiver_id). Conductor: $driver_name ($driver_id). Notas: $delivery_notes";
            } else {
                $history_note = "Entregado a: $receiver_name ($receiver_id). Notas: $delivery_notes";
            }
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'delivered', ?, ?, ?)");
            $stmtH->execute([$order['id'], $history_note, $_SESSION['user_id'], get_local_datetime()]);
        }

        $pdo->commit();
        
        // Redirect to multi-print page
        header("Location: print_delivery_multi.php?ids=" . $ids);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al procesar la entrega: " . $e->getMessage();
    }
}

$page_title = "Confirmar Entrega Múltiple - {$equipmentCount} Equipos";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <a href="exit.php" style="color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <i class="ph ph-arrow-left"></i> Volver a Salidas
        </a>
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
            <div style="background: rgba(16, 185, 129, 0.15); padding: 0.75rem; border-radius: 10px;">
                <i class="ph-fill ph-stack" style="font-size: 1.75rem; color: #10b981;"></i>
            </div>
            <div>
                <h1 style="margin: 0;">Entrega Múltiple</h1>
                <p class="text-muted" style="margin: 0.25rem 0 0 0;">Confirma la entrega de <?php echo $equipmentCount; ?> equipos</p>
            </div>
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <!-- Client Summary -->
        <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); padding: 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid rgba(16, 185, 129, 0.3);">
            <h3 style="font-size: 1.1rem; color: #10b981; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-fill ph-user-circle"></i> Cliente
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <span class="text-muted d-block text-sm">Nombre del Cliente / Empresa</span>
                    <span class="font-medium" style="font-size: 1.1rem;"><?php echo htmlspecialchars($finalClientDisplayName); ?></span>
                </div>
                <div>
                    <span class="text-muted d-block text-sm">Teléfono</span>
                    <span class="font-medium"><?php echo htmlspecialchars($clientData['phone']); ?></span>
                </div>
            </div>
        </div>

        <!-- Equipment List -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-fill ph-package"></i> Equipos a Entregar (<?php echo $equipmentCount; ?>)
            </h3>
            <div style="display: grid; gap: 0.75rem;">
                <?php foreach ($orders as $index => $order): 
                    // Fetch Latest Repair Note for this order
                    $stmtRepair = $pdo->prepare("
                        SELECT notes 
                        FROM service_order_history 
                        WHERE service_order_id = ? AND action = 'in_repair' AND notes IS NOT NULL AND notes != ''
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $stmtRepair->execute([$order['id']]);
                    $latestRepairNote = $stmtRepair->fetchColumn();
                ?>
                    <div style="display: flex; align-items: start; gap: 1rem; padding: 1rem; background: var(--bg-hover); border-radius: 8px; border: 1px solid var(--border-color); position: relative;">
                        <div style="background: #10b981; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; margin-top: 0.25rem;">
                            <?php echo $index + 1; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; margin-bottom: 0.25rem; display: flex; justify-content: space-between; align-items: center;">
                                <span><?php echo htmlspecialchars($order['equipment_type'] . ' ' . $order['brand'] . ' ' . $order['model']); ?></span>
                                <button type="button" class="btn-icon" style="color: var(--danger); background: rgba(239, 68, 68, 0.1); border: none; width: 28px; height: 28px; border-radius: 6px;" 
                                    onclick="removeItem(<?php echo $order['id']; ?>)" title="Quitar de esta entrega">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                Serie: <?php echo htmlspecialchars($order['serial_number']); ?> | 
                                Orden: <?php echo get_order_number($order, 5); ?>
                            </div>
                            <?php if ($latestRepairNote): ?>
                            <div style="font-size: 0.9rem; color: var(--text-main); background: rgba(0,0,0,0.2); padding: 0.5rem 0.75rem; border-radius: 6px; border-left: 2px solid var(--primary-400); margin-top: 0.5rem;">
                                <strong>Detalle Téc.:</strong> <?php echo nl2br(htmlspecialchars($latestRepairNote)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="POST">
            <h3 class="mb-4" style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-fill ph-identification-card"></i> Datos de Quien Recibe
            </h3>
            
            <div class="form-group">
                <label class="form-label">Nombre Completo *</label>
                <div class="input-group">
                    <input type="text" name="receiver_name" class="form-control" placeholder="Nombre de la persona que retira" required value="<?php echo htmlspecialchars($finalClientDisplayName); ?>">
                    <i class="ph ph-user input-icon"></i>
                </div>
                <small class="text-muted">Por defecto se sugiere el nombre del cliente titular.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Cédula *</label>
                <div class="input-group">
                    <input type="text" name="receiver_id" class="form-control" placeholder="Número de documento" required>
                    <i class="ph ph-identification-card input-icon"></i>
                </div>
            </div>

            <div class="form-group" style="margin-top: 1rem; margin-bottom: 1rem;">
                <label class="custom-checkbox" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="has_driver" id="has_driver_multi" style="width: 18px; height: 18px;" onchange="toggleDriverFieldsMulti(this.checked)">
                    <span>Entregado a un conductor de transporte (Tercero)</span>
                </label>
            </div>

            <script>
                function toggleDriverFieldsMulti(isChecked) {
                    document.getElementById('driver_fields_multi').style.display = isChecked ? 'block' : 'none';
                    let receiverIdField = document.querySelector('input[name="receiver_id"]');
                    let receiverNameField = document.querySelector('input[name="receiver_name"]');
                    if (isChecked) {
                        receiverIdField.removeAttribute('required');
                        receiverNameField.removeAttribute('required');
                    } else {
                        receiverIdField.setAttribute('required', 'required');
                        receiverNameField.setAttribute('required', 'required');
                    }
                }
            </script>

            <div id="driver_fields_multi" style="display: none; background: rgba(0,0,0,0.05); padding: 1rem; border-radius: 8px; border: 1px dashed var(--border-color); margin-bottom: 1rem;">
                <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--text-main); font-size: 0.95rem;"><i class="ph ph-truck"></i> Datos del Conductor</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Nombre del Conductor</label>
                        <input type="text" name="driver_name" class="form-control" placeholder="Nombre completo">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Cédula / Placa</label>
                        <input type="text" name="driver_id" class="form-control" placeholder="Identificación o placa">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notas de Entrega</label>
                <textarea name="delivery_notes" class="form-control" rows="3" placeholder="Comentarios adicionales sobre la entrega..."></textarea>
            </div>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="ph-fill ph-info" style="color: #10b981; font-size: 1.5rem;"></i>
                        <div>
                            <div style="font-weight: 600; color: #10b981; margin-bottom: 0.25rem;">Entrega Múltiple</div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary);">
                                Se marcarán <?php echo $equipmentCount; ?> equipos como entregados y se generará una hoja de entrega combinada.
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="exit.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="ph ph-check-circle"></i> Confirmar Entrega de <?php echo $equipmentCount; ?> Equipos
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function removeItem(id) {
    const urlParams = new URLSearchParams(window.location.search);
    const idsString = urlParams.get('ids');
    if (!idsString) return;

    let ids = idsString.split(',');
    ids = ids.filter(item => parseInt(item) !== id);

    if (ids.length === 0) {
        window.location.href = 'exit.php';
    } else {
        urlParams.set('ids', ids.join(','));
        window.location.search = urlParams.toString();
    }
}

// ID (Cedula) Masking
document.querySelector('input[name="receiver_id"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9a-zA-Z]/g, '').toUpperCase();
    let formatted = '';

    if (value.length > 0) {
        // Pattern: 000-000000-0000A
        if (value.length <= 3) {
            formatted = value;
        } else if (value.length <= 9) {
            formatted = value.slice(0, 3) + '-' + value.slice(3);
        } else {
            formatted = value.slice(0, 3) + '-' + value.slice(3, 9) + '-' + value.slice(9, 14);
        }
    }

    e.target.value = formatted;
});

// Phone masking if name="phone" existed but it's not here, 
// let's stick to receiver_id as requested
</script>

<?php
require_once '../../includes/footer.php';
?>
