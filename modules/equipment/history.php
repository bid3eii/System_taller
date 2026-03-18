<?php
// modules/equipment/history.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('equipment', $pdo) && !can_access_module('history', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Historial de Equipos (Por S/N)';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$search_serial = clean($_GET['serial'] ?? '');
$equipment = null;
$orders = [];
$error = '';

if (!empty($search_serial)) {
    // 1. Fetch Equipment Info
    $stmtEq = $pdo->prepare("
        SELECT e.*, c.name as client_name, c.phone as client_phone
        FROM equipments e
        LEFT JOIN clients c ON e.client_id = c.id
        WHERE e.serial_number = ?
    ");
    $stmtEq->execute([$search_serial]);
    $equipment = $stmtEq->fetch();

    if ($equipment) {
        // 2. Fetch associated orders
        $stmtOrders = $pdo->prepare("
            SELECT so.*, 
                   u.username as tech_name
            FROM service_orders so
            LEFT JOIN users u ON so.assigned_tech_id = u.id
            WHERE so.equipment_id = ? AND (so.problem_reported NOT LIKE 'Garant%a Registrada' OR so.problem_reported IS NULL)
            ORDER BY so.entry_date DESC
        ");
        $stmtOrders->execute([$equipment['id']]);
        $orders = $stmtOrders->fetchAll();
    } else {
        $error = "No se encontró ningún equipo registrado con el S/N: " . htmlspecialchars($search_serial);
    }
}
?>

<div class="animate-enter" style="max-width: 1100px; margin: 0 auto; padding-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1><?php echo $page_title; ?></h1>
            <p class="text-muted">Busca un equipo por su Número de Serie para revisar su expediente de servicios, estado actual y visitas anteriores.</p>
        </div>
    </div>

    <!-- Buscador -->
    <div class="form-section" style="margin-bottom: 2rem;">
        <form method="GET" action="history.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex-grow: 1; min-width: 250px;">
                <label class="form-label">Número de Serie (S/N)</label>
                <div class="input-group">
                    <input type="text" name="serial" class="form-control" placeholder="Ingrese el Número de Serie" value="<?php echo htmlspecialchars($search_serial); ?>" required autofocus>
                    <i class="ph ph-barcode input-icon"></i>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                    <i class="ph ph-magnifying-glass"></i> Buscar Historial
                </button>
            </div>
            <?php if (!empty($search_serial)): ?>
            <div class="form-group">
                <a href="history.php" class="btn btn-secondary" style="padding: 0.75rem 1rem;" data-tooltip="Limpiar búsqueda">
                    <i class="ph ph-x"></i>
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <i class="ph ph-warning-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($equipment): ?>
        <!-- Ficha del Equipo -->
        <div class="form-section" style="margin-bottom: 2rem; background: var(--bg-body); border: 1px dashed var(--border-color);">
            <div class="form-section-header" style="color: var(--primary-light);">
                <i class="ph ph-desktop"></i> Ficha Técnica del Equipo
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                <div>
                    <span class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Marca / Equipo</span>
                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--text-primary);">
                        <?php echo htmlspecialchars($equipment['brand']); ?>
                        <?php if ($equipment['model']) echo ' / ' . htmlspecialchars($equipment['model']); ?>
                    </div>
                </div>
                <div>
                    <span class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Serie (S/N)</span>
                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--primary-400);">
                        <?php echo htmlspecialchars($equipment['serial_number']); ?>
                    </div>
                </div>
                <div>
                    <span class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Cliente Actual</span>
                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--text-primary);">
                        <?php echo htmlspecialchars($equipment['client_name'] ?? 'Desconocido'); ?>
                    </div>
                </div>
                <div>
                    <span class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Servicios Realizados</span>
                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--success);">
                        <?php echo count($orders); ?> orden(es)
                    </div>
                </div>
            </div>
        </div>

        <!-- Línea de Tiempo de Órdenes -->
        <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary);">
            <i class="ph ph-clock-counter-clockwise"></i> Línea de Tiempo de Servicios
        </h3>

        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; border: 1px dashed var(--border-color);">
                <i class="ph ph-folder-open text-muted" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p class="text-muted">Este equipo está registrado, pero no tiene órdenes de servicio asociadas.</p>
            </div>
        <?php else: ?>
            <div style="position: relative; padding-left: 2rem;">
                <!-- Línea conectora -->
                <div style="position: absolute; left: 0.5rem; top: 1rem; bottom: 1rem; width: 2px; background: var(--border-color); z-index: 1;"></div>

                <?php foreach ($orders as $index => $order): 
                    $status_color = 'var(--text-muted)';
                    $status_text = 'Desconocido';
                    $icon = 'ph-file-text';
                    
                    switch($order['status']) {
                        case 'received': $status_color = '#3b82f6'; $status_text = 'Recibido'; $icon = 'ph-arrow-down-left'; break;
                        case 'diagnosing': $status_color = '#f59e0b'; $status_text = 'Diagnosticando'; $icon = 'ph-magnifying-glass'; break;
                        case 'pending_approval': $status_color = '#eab308'; $status_text = 'Esperando Aprobación'; $icon = 'ph-clock'; break;
                        case 'in_repair': $status_color = '#8b5cf6'; $status_text = 'En Reparación'; $icon = 'ph-wrench'; break;
                        case 'ready': $status_color = '#10b981'; $status_text = 'Listo / Terminado'; $icon = 'ph-check-circle'; break;
                        case 'delivered': $status_color = '#64748b'; $status_text = 'Entregado'; $icon = 'ph-arrow-up-right'; break;
                        case 'cancelled': $status_color = '#ef4444'; $status_text = 'Cancelado'; $icon = 'ph-x-circle'; break;
                    }
                ?>
                <div style="position: relative; margin-bottom: 2rem; z-index: 2;" class="animate-enter">
                    <!-- Nodo indicador -->
                    <div style="position: absolute; left: -2.3rem; top: 1rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--bg-card); border: 3px solid <?php echo $status_color; ?>; display: flex; align-items: center; justify-content: center;">
                        <i class="ph <?php echo $icon; ?>" style="color: <?php echo $status_color; ?>; font-size: 0.75rem;"></i>
                    </div>

                    <!-- Tarjeta de la orden -->
                    <div class="form-section form-control" style="border-left-width: 0; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--primary-light);">
                                    Caso <?php echo get_order_number($order); ?> 
                                    <span style="font-size: 0.8rem; background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 12px; color: <?php echo $status_color; ?>; border: 1px solid <?php echo $status_color; ?>; margin-left: 0.5rem;">
                                        <?php echo $status_text; ?>
                                    </span>
                                </h4>
                                <div class="text-muted" style="font-size: 0.85rem; display: flex; gap: 1.5rem;">
                                    <span><i class="ph ph-calendar"></i> Entrada: <?php echo date('d M Y, h:i A', strtotime($order['entry_date'])); ?></span>
                                    <?php if ($order['exit_date']): ?>
                                        <span><i class="ph ph-calendar-check"></i> Salida: <?php echo date('d M Y, h:i A', strtotime($order['exit_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="<?php echo BASE_URL; ?>modules/equipment/print_entry.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" data-tooltip="Comprobante de Recepción">
                                    <i class="ph ph-printer"></i> Ingreso
                                </a>
                                <?php if (!empty($order['exit_date'])): ?>
                                <a href="<?php echo BASE_URL; ?>modules/equipment/print_delivery.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" data-tooltip="Comprobante de Entrega">
                                    <i class="ph ph-printer"></i> Salida
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($order['diagnosis_notes'])): ?>
                                <a href="<?php echo BASE_URL; ?>modules/services/print_diagnosis.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary" target="_blank" data-tooltip="Imprimir Reporte de Diagnóstico">
                                    <i class="ph ph-file-pdf"></i> Diagnóstico
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo BASE_URL; ?>modules/services/view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary" target="_blank" data-tooltip="Abrir Documento Completo">
                                    <i class="ph ph-arrow-square-out"></i> Detalles
                                </a>
                            </div>
                        </div>

                        <div class="modern-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 1rem; gap: 1rem;">
                            
                            <div>
                                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Técnico Asignado</label>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="ph ph-user-circle text-muted" style="font-size: 1.25rem;"></i>
                                    <span><?php echo $order['tech_name'] ? htmlspecialchars($order['tech_name']) : '<span class="text-muted">No asignado</span>'; ?></span>
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem;">Problema Reportado (Motivo de Ingreso)</label>
                                <p style="margin: 0; font-size: 0.9rem; line-height: 1.4; color: var(--text-primary);">
                                    <?php echo nl2br(htmlspecialchars($order['problem_reported'] ?? 'Sin registro')); ?>
                                </p>
                            </div>

                            <?php if (!empty($order['diagnosis_notes'])): ?>
                            <div style="grid-column: 1 / -1; background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); padding: 1rem; border-radius: 8px;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size: 0.8rem; color: #f59e0b; margin-bottom: 0.5rem; font-weight: 600;">
                                    <i class="ph ph-magnifying-glass-plus"></i> Diagnóstico Técnico
                                </label>
                                <p style="margin: 0; font-size: 0.9rem; line-height: 1.4; color: var(--text-primary);">
                                    <?php echo nl2br(htmlspecialchars($order['diagnosis_notes'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($order['work_done']) || !empty($order['parts_replaced'])): ?>
                            <div style="grid-column: 1 / -1; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 8px;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size: 0.8rem; color: #10b981; margin-bottom: 0.5rem; font-weight: 600;">
                                    <i class="ph ph-check-square"></i> Trabajo Realizado / Resolución
                                </label>
                                <p style="margin: 0; font-size: 0.9rem; line-height: 1.4; color: var(--text-primary);">
                                    <?php echo nl2br(htmlspecialchars($order['work_done'])); ?>
                                    <?php if (!empty($order['parts_replaced'])): ?>
                                        <br><br><strong>Repuestos utilizados:</strong><br><?php echo nl2br(htmlspecialchars($order['parts_replaced'])); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require_once '../../includes/footer.php'; ?>
