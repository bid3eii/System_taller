<?php
// modules/warranties/view_details.php
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

// Fetch Warranty Details (Directly from warranties table)
$stmt = $pdo->prepare("
    SELECT 
        w.*,
        e.brand, e.model, e.submodel, e.serial_number, e.type as equipment_type,
        c.name as client_name, c.phone, c.email, c.tax_id, c.address
    FROM warranties w
    LEFT JOIN equipments e ON w.equipment_id = e.id
    LEFT JOIN clients c ON e.client_id = c.id
    WHERE w.id = ?
");
$stmt->execute([$id]);
$warranty = $stmt->fetch();

if (!$warranty) {
    die("Garantía no encontrada.");
}

$page_title = 'Detalle de Garantía #' . str_pad($warranty['id'], 6, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 1000px; margin: 0 auto;">
    
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <a href="index.php" style="color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                    <i class="ph ph-arrow-left"></i> Volver
                </a>
                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <?php echo ucfirst($warranty['status']); ?>
                </span>
            </div>
            <h1 style="margin-top: 0;">Garantía #<?php echo str_pad($warranty['id'], 6, '0', STR_PAD_LEFT); ?></h1>
            <p class="text-muted">Registrada el <?php echo date('d/m/Y H:i', strtotime($warranty['created_at'])); ?></p>
        </div>
        
        <!-- Optional Actions -->
        <div>
            <!-- Print Certificate Button (Mockup) -->
            <!-- <button class="btn btn-secondary"><i class="ph ph-printer"></i> Imprimir</button> -->
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        
        <!-- Left Column: Details -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Information Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; color: var(--primary-500);">
                    <i class="ph ph-shield-check"></i> Información de Garantía
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Producto / Código</label>
                        <div style="font-size: 1.1rem; font-weight: 500;"><?php echo htmlspecialchars($warranty['product_code']); ?></div>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Factura Venta</label>
                        <div style="font-size: 1.1rem; font-weight: 500; color: var(--text-primary);">
                             <?php echo htmlspecialchars($warranty['sales_invoice_number']); ?>
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Factura Master (Ingreso)</label>
                        <div><?php echo htmlspecialchars($warranty['master_entry_invoice']); ?></div>
                    </div>
                     <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Proveedor</label>
                        <div><?php echo htmlspecialchars($warranty['supplier_name']); ?></div>
                    </div>

                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Fecha Vencimiento</label>
                        <div style="font-size: 1.1rem; color: #fbbf24; font-weight: 600;">
                            <i class="ph ph-calendar"></i> <?php echo date('d/m/Y', strtotime($warranty['end_date'])); ?>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">
                            Duración: <?php echo $warranty['duration_months']; ?> <?php echo $warranty['terms']; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Equipment Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <i class="ph ph-desktop"></i> Equipo Asegurado
                </h3>
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Marca / Modelo</label>
                        <div style="font-size: 1rem; font-weight: 500;">
                            <?php echo htmlspecialchars($warranty['brand'] . ' ' . $warranty['model']); ?>
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($warranty['submodel']); ?></div>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Número de Serie</label>
                        <div style="font-family: monospace; font-size: 1rem; background: var(--bg-body); padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block;">
                            <?php echo htmlspecialchars($warranty['serial_number']); ?>
                        </div>
                    </div>
                     <div>
                        <label class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Tipo</label>
                        <div><?php echo htmlspecialchars($warranty['equipment_type']); ?></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Client -->
        <div>
            <div class="card" style="padding: 1.5rem;">
                 <h3 style="margin-top: 0; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <i class="ph ph-user"></i> Cliente
                </h3>
                
                <div style="margin-bottom: 1rem;">
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($warranty['client_name']); ?></div>
                    <div style="font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($warranty['tax_id']); ?></div>
                </div>
                
                <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                    <i class="ph ph-phone" style="color: var(--primary-500);"></i>
                    <?php echo htmlspecialchars($warranty['phone']); ?>
                </div>
                <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                    <i class="ph ph-envelope" style="color: var(--primary-500);"></i>
                    <?php echo htmlspecialchars($warranty['email']); ?>
                </div>
                <?php if($warranty['address']): ?>
                <div style="margin-bottom: 0.75rem; display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.9rem;">
                    <i class="ph ph-map-pin" style="color: var(--primary-500); margin-top: 2px;"></i>
                    <?php echo htmlspecialchars($warranty['address']); ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if($warranty['notes']): ?>
            <div class="card" style="padding: 1.5rem; margin-top: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1rem;">Observaciones</h3>
                <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($warranty['notes'])); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
