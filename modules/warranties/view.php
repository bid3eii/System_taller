<?php
// modules/warranties/view.php
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

// Status updates are disabled for warranties as per user request.

// Fetch Order Details
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.name as client_name, c.phone, c.email,
        e.brand, e.model, e.serial_number, e.type as equipment_type,
        w.product_code, w.sales_invoice_number, w.master_entry_invoice, w.master_entry_date, w.supplier_name
    FROM service_orders so
    JOIN clients c ON so.client_id = c.id
    JOIN equipments e ON so.equipment_id = e.id
    LEFT JOIN warranties w ON w.service_order_id = so.id
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

$page_title = 'Detalle de Garantía #' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Status Mapping
$statusLabels = [
    'received' => 'Recibido',
    'diagnosing' => 'En Revisión/Diagnóstico',
    'pending_approval' => 'En Espera',
    'in_repair' => 'En Reparación',
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
        <!-- New Content Starts Here (Styles & Container) -->

    <style>
        :root {
            --p-bg-main: #020617;
            --p-bg-card: #0f172a;
            --p-bg-input: #1e293b;
            --p-border:   #334155;
            --p-text-main: #f8fafc;
            --p-text-muted: #94a3b8;
            --p-primary:  #6366f1;
        }

        .view-container {
            max-width: 1400px;
            margin: 0 auto;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--p-text-main);
        }

        /* Grid Layout */
        /* Grid Layout */
        .layout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            align-items: start;
            gap: 2rem;
        }
        
        .form-section {
            background: var(--p-bg-card);
            border: 1px solid var(--p-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .form-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--p-border);
            color: var(--p-primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .info-group {
            margin-bottom: 1.25rem;
        }
        .info-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--p-text-muted);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        .info-value {
            font-size: 0.95rem;
            color: var(--p-text-main);
            font-weight: 500;
        }
        .info-value.highlight {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .problem-box {
            background: #020617; /* Darker than card */
            border: 1px solid var(--p-border);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Right Column Form */
        .update-card {
            position: sticky;
            top: 2rem;
            background: var(--p-bg-card);
            border: 1px solid var(--p-border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .modern-input, .modern-select, .modern-textarea {
            background: var(--p-bg-input);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--p-text-main);
            width: 100%;
            font-family: inherit;
            transition: all 0.2s;
        }
        .modern-input:focus, .modern-select:focus, .modern-textarea:focus {
            outline: none;
            border-color: var(--p-primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: #1e293b;
        }

        .btn-update {
            background: var(--p-primary);
            color: white;
            border: none;
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-update:hover {
            filter: brightness(1.1);
        }

        /* Timeline Styles (Preserved) */
        .timeline-item { position: relative; padding-left: 2rem; padding-bottom: 2rem; border-left: 2px solid var(--p-border); }
        .timeline-item:last-child { border-left: none; padding-bottom: 0; }
        .timeline-icon { position: absolute; left: -9px; top: 0; width: 16px; height: 16px; border-radius: 50%; background: var(--p-primary); border: 2px solid var(--p-bg-card); }
        .timeline-date { font-size: 0.8rem; color: var(--p-text-muted); }
        .timeline-text { font-size: 0.95rem; font-weight: 500; margin-bottom: 0.25rem; }
        .mobile-hidden {
             display: block;
        }
        @media (max-width: 900px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
            .mobile-hidden {
                display: none;
            }
        }
        
        .sidebar-sticky {
            position: sticky;
            top: 2rem;
        }
    </style>

    <div class="view-container">
        <!-- Header -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <?php 
                        $back_link = 'index.php';
                        if(isset($_GET['return_to']) && $_GET['return_to'] === 'entry') {
                            $back_link = '../equipment/entry.php?type=warranty';
                        }
                    ?>
                    <a href="<?php echo $back_link; ?>" style="color: var(--p-text-muted); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                        <i class="ph ph-arrow-left"></i> Volver
                    </a>
                    <span style="font-size: 0.9rem; color: var(--p-text-muted);"><?php echo $statusLabels[$order['status']] ?? $order['status']; ?></span>
                </div>
                <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.25rem;">Orden #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h1>
                <p style="color: var(--p-text-muted); font-size: 0.9rem;">Ingresado el <?php echo date('d/m/Y H:i', strtotime($order['entry_date'])); ?></p>
            </div>
            
            <div style="text-align: right;">
                <a href="print.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-primary" style="margin-bottom: 0.5rem; text-decoration: none; display: inline-flex;">
                    <i class="ph ph-printer"></i> Imprimir
                </a>
                <?php if($order['sales_invoice_number']): ?>
                    <div>
                        <span style="color: var(--p-text-muted); font-size: 0.9rem;">Factura:</span>
                        <strong style="font-size: 1.1rem; color: var(--p-text-main);"><?php echo htmlspecialchars($order['sales_invoice_number']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div style="background: rgba(99, 102, 241, 0.1); color: #818cf8; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid rgba(99, 102, 241, 0.2);">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <div class="layout-grid">
            <!-- Left Column: Details & History -->
            <div>
                <!-- 1. Information General -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="ph ph-info"></i> Información General
                    </div>
                    
                    <div class="info-grid">
                        <!-- Client Column -->
                        <div>
                            <h4 style="color: var(--p-primary); font-size: 0.9rem; margin-bottom: 1rem;">Cliente</h4>
                            
                            <div class="info-group">
                                <span class="info-label">Nombre Completo</span>
                                <div class="info-value highlight"><?php echo htmlspecialchars($order['client_name']); ?></div>
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <div class="info-group">
                                    <span class="info-label">Teléfono</span>
                                    <div class="info-value"><i class="ph ph-phone"></i> <?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Email</span>
                                    <div class="info-value"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($order['email']); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment Column -->
                        <div>
                            <h4 style="color: var(--p-primary); font-size: 0.9rem; margin-bottom: 1rem;">Equipo</h4>
                            
                            <div class="info-group">
                                <span class="info-label">Equipo</span>
                                <div class="info-value highlight">
                                    <?php echo htmlspecialchars($order['equipment_type']); ?> <?php echo htmlspecialchars($order['brand']); ?>
                                </div>
                                <div class="info-value text-muted"><?php echo htmlspecialchars($order['model']); ?></div>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Número de Serie</span>
                                <div class="info-value" style="font-family: monospace; letter-spacing: 0.05em;"><?php echo htmlspecialchars($order['serial_number']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Service Details -->
                <div class="form-section">
                     <div class="form-section-header">
                        <i class="ph ph-clipboard-text"></i> Detalles del Servicio
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Problema Reportado</span>
                        <div class="problem-box">
                            <?php echo nl2br(htmlspecialchars($order['problem_reported'])); ?>
                        </div>
                    </div>
                </div>

                </div>


            <!-- Right Column: History -->
            <div class="sidebar-sticky">
                <div class="form-section">
                    <div class="form-section-header">
                         <i class="ph ph-clock-counter-clockwise"></i> Historial
                    </div>
                    
                    <div style="padding-left: 0.5rem;">
                        <?php foreach($history as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon"></div>
                                <div class="timeline-text">
                                    <?php echo $statusLabels[$event['action']] ?? $event['action']; ?>
                                </div>
                                <div class="timeline-date">
                                    <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?> • <?php echo htmlspecialchars($event['user_name']); ?>
                                </div>
                                <?php if($event['notes']): ?>
                                    <div style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--p-text-muted); background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 4px;">
                                        <?php echo htmlspecialchars($event['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


            <!-- Right Column: Update Status -->
            <!-- Right Column Removed as per user request (Warranties are independent) -->
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
