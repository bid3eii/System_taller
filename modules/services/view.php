<?php
// modules/services/view.php
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

// Handle Status Updates
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $new_status = clean($_POST['status']);
        $note = clean($_POST['note']);
        
        try {
            $pdo->beginTransaction();
            
            // Update Order
            $stmt = $pdo->prepare("UPDATE service_orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            
            // Log History
            $stmtH = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id) VALUES (?, ?, ?, ?)");
            $stmtH->execute([$id, $new_status, $note, $_SESSION['user_id']]);
            
            $pdo->commit();
            $success_msg = "Estado actualizado correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.name as client_name, c.phone, c.email,
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

// Fetch History
$stmtHist = $pdo->prepare("
    SELECT h.*, u.username as user_name 
    FROM service_order_history h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.service_order_id = ?
    ORDER BY h.created_at DESC
");
$stmtHist->execute([$id]);
$history = $stmtHist->fetchAll();

$page_title = 'Detalle de Servicio #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Status Mapping
$statusLabels = [
    'received' => 'Recibido',
    'diagnosing' => 'En Revisión',
    'pending_approval' => 'En Espera',
    'in_repair' => 'En Proceso',
    'ready' => 'Listo',
    'delivered' => 'Entregado',
    'cancelled' => 'Cancelado'
];
// View Logic
$view_mode = $_GET['view_mode'] ?? 'current';
$is_original_mode = ($view_mode === 'original');
$is_history_view = (isset($_GET['view_source']) && $_GET['view_source'] === 'history');
?>

<div class="animate-enter">
    <!-- Header -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                <a href="<?php echo ($is_history_view) ? '../history/index.php' : '../services/index.php'; ?>" style="color: var(--text-secondary); text-decoration: none;">
                    <i class="ph ph-arrow-left"></i> Volver
                </a>
                <span class="badge" style="font-size: 1rem;">
                    <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                </span>
            </div>
            <h1>Orden #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h1>
            <p class="text-muted">Ingresado el <?php echo date('d/m/Y H:i', strtotime($order['entry_date'])); ?></p>
        </div>
        
        <div style="text-align: right;">
            <a href="print.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-primary" style="margin-bottom: 0.5rem; text-decoration: none; display: inline-flex;">
                <i class="ph ph-printer"></i> Imprimir
            </a>
            <?php if($order['invoice_number']): ?>
                <div style="margin-bottom: 0.5rem;">
                    <span class="text-muted">Factura:</span> 
                    <strong style="font-size: 1.1rem; color: var(--primary-400);"><?php echo htmlspecialchars($order['invoice_number']); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div style="background: rgba(16, 185, 129, 0.1); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php
    ?>

    <style>
        .view-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        .form-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }
        .form-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--primary-500);
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-section-header i {
            font-size: 1.25rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .info-item label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        .info-item div {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .timeline-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .timeline-line {
             position: absolute; left: 15px; top: 24px; bottom: -24px; width: 2px; background: var(--border-color); z-index: 0;
        }
        .timeline-icon {
            width: 30px; height: 30px; background: var(--bg-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 1; border: 2px solid var(--bg-card);
        }
    </style>

    <div class="view-container">
        
        <div style="grid-column: 1 / 2;">

            <?php if (!$is_history_view): ?>
                
                <?php
                // Logic to find Original Values from History (Preserved)
                $original_values = [];
                $history_asc = array_reverse($history); 
                foreach ($history_asc as $h) {
                    if ($h['action'] == 'updated') {
                        $note_content = str_replace('Datos editados: ', '', $h['notes']);
                        if (preg_match_all('/([^:]+): (.*?) -> /', $note_content, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $match) {
                                $field_parts = explode(',', $match[1]);
                                $key = trim(end($field_parts));
                                $original_values[$key] = trim($match[2]);
                            }
                        }
                    }
                }
                
                if ($is_original_mode) {
                     $map = [
                        'Cliente' => 'client_name', 'Teléfono' => 'phone', 'Email' => 'email',
                        'Marca' => 'brand', 'Modelo' => 'model', 'Serie' => 'serial_number',
                        'Tipo' => 'equipment_type', 'Accesorios' => 'accessories_received',
                        'Problema' => 'problem_reported', 'Notas' => 'entry_notes'
                    ];
                    foreach ($map as $histKey => $dbKey) {
                        if (isset($original_values[$histKey])) $order[$dbKey] = $original_values[$histKey];
                    }
                }
                ?>

                <!-- 1. GENERAL INFO SECTION -->
                <div class="form-section">
                    <div class="form-section-header" style="justify-content: space-between;">
                         <div style="display:flex; align-items:center; gap:0.5rem">
                            <i class="ph ph-info"></i> Información General
                         </div>
                         <!-- Toggle Mode Buttons -->
                         <?php if($is_original_mode): ?>
                            <a href="?id=<?php echo $id; ?>&view_mode=current" class="btn btn-sm btn-primary">
                                <i class="ph ph-eye"></i> Ver Actual
                            </a>
                        <?php elseif(!empty($original_values)): ?>
                            <a href="?id=<?php echo $id; ?>&view_mode=original" class="btn btn-sm btn-secondary">
                                <i class="ph ph-clock-counter-clockwise"></i> Ver Original
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="info-grid">
                        <!-- Client Column -->
                        <div>
                            <h4 style="font-size: 0.95rem; color: var(--primary-500); margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">Cliente</h4>
                            
                            <div class="info-item" style="margin-bottom: 1rem;">
                                <label>Nombre Completo</label>
                                <div style="font-size: 1.1rem;"><?php echo htmlspecialchars($order['client_name']); ?></div>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <div class="info-item">
                                    <label>Teléfono</label>
                                    <div><i class="ph ph-phone text-muted"></i> <?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Email</label>
                                    <div><i class="ph ph-envelope text-muted"></i> <?php echo htmlspecialchars($order['email']); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment Column -->
                        <div>
                             <h4 style="font-size: 0.95rem; color: var(--primary-500); margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">Equipo</h4>
                             
                             <div class="info-item" style="margin-bottom: 1rem;">
                                <label>Equipo</label>
                                <div style="font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($order['equipment_type']); ?> <?php echo htmlspecialchars($order['brand']); ?>
                                </div>
                                <div class="text-muted"><?php echo htmlspecialchars($order['model']); ?></div>
                            </div>

                            <div class="info-item">
                                <label>Número de Serie</label>
                                <div><?php echo htmlspecialchars($order['serial_number']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. REPORT & NOTES SECTION -->
                <div class="form-section">
                     <div class="form-section-header">
                        <i class="ph ph-clipboard-text"></i> Detalles del Servicio
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item" style="grid-column: 1 / -1;">
                             <label>Problema Reportado</label>
                             <div style="background: var(--bg-body); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); line-height: 1.6;">
                                 <?php echo nl2br(htmlspecialchars($order['problem_reported'])); ?>
                             </div>
                        </div>
                        
                        <?php if($order['accessories_received'] || $order['entry_notes']): ?>
                        <div class="info-item" style="grid-column: 1 / -1; margin-top: 1rem;">
                             <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                 <div>
                                     <label>Accesorios Recibidos</label>
                                     <div><?php echo htmlspecialchars($order['accessories_received'] ?: '-'); ?></div>
                                 </div>
                                 <div>
                                     <label>Notas de Ingreso</label>
                                     <div class="text-muted"><?php echo htmlspecialchars($order['entry_notes'] ?: '-'); ?></div>
                                 </div>
                             </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
            <!-- HISTORY SECTION -->
            <div class="form-section">
                <div class="form-section-header">
                     <i class="ph ph-clock-counter-clockwise"></i> Historial
                </div>
                
                <div class="timeline" style="margin-left: 0.5rem;">
                    <?php foreach($history as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-line"></div>
                            
                            <!-- Icon logic -->
                            <?php 
                                $color = 'var(--text-secondary)';
                                $icon = 'ph-info';
                                if($event['action']=='received') { $color='var(--primary-400)'; $icon='ph-download-simple'; }
                                if($event['action']=='ready') { $color='var(--success)'; $icon='ph-check'; }
                                if($event['action']=='delivered') { $color='var(--success)'; $icon='ph-package'; }
                            ?>
                            <div class="timeline-icon" style="color: <?php echo $color; ?>">
                                <i class="ph <?php echo $icon; ?>" style="font-size: 0.9rem;"></i>
                            </div>
                            
                            <div>
                                <div style="font-weight: 600; font-size: 0.95rem;">
                                    <?php echo $statusLabels[$event['action']] ?? $event['action']; ?>
                                </div>
                                <div class="text-sm text-muted" style="margin-bottom: 0.25rem;">
                                    <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?> • <?php echo htmlspecialchars($event['user_name']); ?>
                                </div>
                                <?php if($event['notes']): ?>
                                    <div style="background: var(--bg-body); padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9rem; margin-top: 0.25rem;">
                                        <?php echo htmlspecialchars($event['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div> <!-- End Left Column -->

        <!-- Right Column: Actions -->
        <?php if(!$is_history_view): ?>
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <?php if(!$is_original_mode): ?>
                
                <!-- Status Control -->
                <?php if($order['status'] !== 'delivered'): ?>
                <div class="card" style="border-top: 4px solid var(--primary-500);">
                    <h3 class="mb-4">Actualizar Estado</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="form-group">
                            <label class="form-label">Nuevo Estado</label>
                            <select name="status" class="form-control">
                                <?php foreach($statusLabels as $key => $label): ?>
                                    <?php if($key !== 'delivered' && $key !== 'cancelled'): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $order['status'] === $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nota de Progreso</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Ej. Se realizó cambio de pasta térmica..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Guardar Cambios
                        </button>
                    </form>
                </div>
                <?php else: ?>
                    <div class="card" style="border-left: 4px solid var(--success); background: rgba(16, 185, 129, 0.05);">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="width: 44px; height: 44px; min-width: 44px; background: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0; margin-top: 0.25rem; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);">
                                <i class="ph ph-check" style="font-size: 1.5rem; font-weight: bold;"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0 0 0.5rem 0; color: var(--success); font-size: 1.2rem;">Orden Entregada</h3>
                                <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                                    Este equipo ya ha sido entregado al cliente y el proceso ha finalizado. No se admiten más cambios en esta orden.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div> <!-- End Grid -->
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
