<?php
// modules/comisiones/view.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('comisiones', $pdo)) {
    die("Acceso denegado.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Handle inline update POST (quick edit panel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_fields' && can_access_module('comisiones_edit', $pdo)) {
        $factura = clean($_POST['factura'] ?? '');
        $fecha_fact = !empty($_POST['fecha_facturacion']) ? $_POST['fecha_facturacion'] : null;
        $lugar = clean($_POST['lugar'] ?? '');
        $vendedor = clean($_POST['vendedor'] ?? '');
        $notas = clean($_POST['notas'] ?? '');

        $stmt = $pdo->prepare("UPDATE comisiones SET factura=?, fecha_facturacion=?, lugar=?, vendedor=?, notas=? WHERE id=?");
        $stmt->execute([$factura ?: null, $fecha_fact, $lugar ?: null, $vendedor ?: null, $notas ?: null, $id]);
        $_SESSION['success'] = "Comisión actualizada correctamente.";
    }

    if ($_POST['action'] === 'mark_paid' && can_access_module('comisiones_edit', $pdo)) {
        $stmt = $pdo->prepare("UPDATE comisiones SET estado = 'PAGADA', fecha_pago = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Comisión marcada como PAGADA.";
    }

    header("Location: view.php?id=$id");
    exit;
}

// Fetch commission
$stmt = $pdo->prepare("
    SELECT c.*, u.username as tech_username
    FROM comisiones c
    LEFT JOIN users u ON c.tech_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$comision = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comision) {
    header("Location: index.php");
    exit;
}

// Non-admin techs can only see their own
$is_admin = can_access_module('comisiones_add', $pdo);
if (!$is_admin && $comision['tech_id'] != $_SESSION['user_id']) {
    die("Acceso denegado.");
}

$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_msg = $_SESSION['error'];
    unset($_SESSION['error']);
}

$page_title = 'Comisión #' . str_pad($comision['id'], 5, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$isPending = ($comision['estado'] === 'PENDIENTE');
$canEdit = can_access_module('comisiones_edit', $pdo);
?>

<style>
    .cv-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 2rem;
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 3rem;
    }

    @media(max-width:900px) {
        .cv-layout {
            grid-template-columns: 1fr;
        }
    }

    .glass-card {
        background: rgba(30, 41, 59, 0.4);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--text-muted);
        margin: 0 0 1.25rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .field-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 1.25rem;
    }

    .field-item .label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        display: block;
        margin-bottom: 0.35rem;
    }

    .field-item .value {
        font-size: 1rem;
        font-weight: 600;
        color: #f1f5f9;
    }

    .field-item .value.empty {
        color: var(--text-muted);
        font-style: italic;
        font-weight: 400;
    }

    .divider {
        border: none;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        margin: 1.5rem 0;
    }

    /* Inline edit form */
    .edit-input {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: #fff;
        padding: 0.55rem 0.75rem;
        width: 100%;
        font-size: 0.9rem;
        transition: border-color .2s;
    }

    .edit-input:focus {
        border-color: var(--primary-500);
        outline: none;
    }

    .edit-label {
        display: block;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 0.4rem;
    }

    /* Status badge styled large */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1.25rem;
        border-radius: 99px;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
    }

    .pill-pending {
        background: rgba(251, 146, 60, 0.15);
        color: #fb923c;
        border: 1px solid rgba(251, 146, 60, 0.3);
    }

    .pill-paid {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .amount-hero {
        text-align: center;
        padding: 1.5rem 1rem;
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 0.75rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(15, 23, 42, 0.6));
        margin-bottom: 1.5rem;
    }

    .amount-hero .label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }

    .amount-hero .amount {
        font-size: 2.6rem;
        font-weight: 800;
        color: #a5b4fc;
    }

    .action-btn {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: filter .2s;
        text-decoration: none;
        border: none;
    }

    .action-btn:hover {
        filter: brightness(1.15);
    }

    .btn-paid {
        background: #10b981;
        color: #fff;
    }

    .btn-edit {
        background: rgba(99, 102, 241, 0.2);
        color: #a5b4fc;
        border: 1px solid rgba(99, 102, 241, 0.3) !important;
    }

    .btn-ref {
        background: rgba(255, 255, 255, 0.05);
        color: #94a3b8;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        margin-top: 0.5rem;
    }
</style>

<div class="animate-enter">
    <!-- Header -->
    <div style="max-width:1200px; margin:0 auto 1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
        <a href="index.php" class="btn btn-secondary"
            style="border-radius:50%; width:40px; height:40px; padding:0; display:flex; justify-content:center; align-items:center;">
            <i class="ph ph-arrow-left"></i>
        </a>
        <div>
            <h1 style="margin:0; font-size:1.6rem; display:flex; align-items:center; gap:0.75rem;">
                <i class="ph ph-coins" style="color:var(--primary-400);"></i>
                Comisión <span
                    style="color:var(--primary-400);">#<?php echo str_pad($comision['id'], 5, '0', STR_PAD_LEFT); ?></span>
                <span class="status-pill <?php echo $isPending ? 'pill-pending' : 'pill-paid'; ?>">
                    <i class="ph <?php echo $isPending ? 'ph-clock' : 'ph-check-circle'; ?>"></i>
                    <?php echo $comision['estado']; ?>
                </span>
            </h1>
            <p class="text-muted" style="margin:0.25rem 0 0 0; font-size:0.9rem;">
                Registrado el <?php echo date('d/m/Y', strtotime($comision['fecha_servicio'])); ?>
                <?php if ($comision['tipo']): ?>
                    &nbsp;·&nbsp; <span class="badge"><?php echo htmlspecialchars($comision['tipo']); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="glass-card"
            style="max-width:1200px; margin:0 auto 1rem; display:flex; align-items:center; gap:0.5rem; border-color:rgba(16,185,129,0.3); background:rgba(16,185,129,0.08); color:#34d399;">
            <i class="ph ph-check-circle" style="font-size:1.2rem;"></i>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>

    <div class="cv-layout">

        <!-- ======= LEFT: Info + Edit Form ======= -->
        <div>
            <!-- Main Info -->
            <div class="glass-card">
                <p class="card-title"><i class="ph ph-info"></i> Información General</p>
                <div class="field-grid">
                    <div class="field-item">
                        <span class="label">Caso / Número</span>
                        <div class="value"><?php echo htmlspecialchars($comision['caso'] ?: '—'); ?></div>
                    </div>
                    <div class="field-item">
                        <span class="label">Cliente</span>
                        <div class="value"><?php echo htmlspecialchars($comision['cliente'] ?: '—'); ?></div>
                    </div>
                    <div class="field-item">
                        <span class="label">Técnico Asignado</span>
                        <div class="value"><?php echo htmlspecialchars($comision['tech_username'] ?: '—'); ?></div>
                    </div>
                    <div class="field-item">
                        <span class="label">Vendedor / Captador</span>
                        <div class="value <?php echo empty($comision['vendedor']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($comision['vendedor'] ?: 'Sin especificar'); ?>
                        </div>
                    </div>
                    <div class="field-item">
                        <span class="label">Servicio</span>
                        <div class="value"><?php echo htmlspecialchars($comision['servicio'] ?: '—'); ?></div>
                    </div>
                    <div class="field-item">
                        <span class="label">Lugar / Zona</span>
                        <div class="value <?php echo empty($comision['lugar']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($comision['lugar'] ?: 'Sin especificar'); ?>
                        </div>
                    </div>
                </div>

                <hr class="divider">
                <p class="card-title"><i class="ph ph-receipt"></i> Datos de Facturación</p>
                <div class="field-grid">
                    <div class="field-item">
                        <span class="label">Fecha del Servicio</span>
                        <div class="value"><?php echo date('d/m/Y', strtotime($comision['fecha_servicio'])); ?></div>
                    </div>
                    <div class="field-item">
                        <span class="label">Número de Factura</span>
                        <div class="value <?php echo empty($comision['factura']) ? 'empty' : ''; ?>">
                            <?php echo htmlspecialchars($comision['factura'] ?: 'Pendiente'); ?>
                        </div>
                    </div>
                    <div class="field-item">
                        <span class="label">Fecha de Facturación</span>
                        <div class="value <?php echo empty($comision['fecha_facturacion']) ? 'empty' : ''; ?>">
                            <?php echo !empty($comision['fecha_facturacion']) ? date('d/m/Y', strtotime($comision['fecha_facturacion'])) : 'Pendiente'; ?>
                        </div>
                    </div>
                    <?php if ($comision['fecha_pago']): ?>
                        <div class="field-item">
                            <span class="label">Fecha de Pago</span>
                            <div class="value" style="color:#34d399;">
                                <?php echo date('d/m/Y', strtotime($comision['fecha_pago'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($comision['notas'])): ?>
                    <hr class="divider">
                    <p class="card-title"><i class="ph ph-note"></i> Notas</p>
                    <div style="color:#cbd5e1; font-size:0.95rem; line-height:1.7; white-space:pre-line;">
                        <?php echo htmlspecialchars($comision['notas']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Edit Form (only if pending and has permission) -->
            <?php if ($isPending && $canEdit): ?>
                <div class="glass-card" style="border-color: rgba(99,102,241,0.2);">
                    <p class="card-title"><i class="ph ph-pencil-simple"></i> Completar / Corregir Información</p>
                    <form method="POST" action="view.php?id=<?php echo $id; ?>">
                        <input type="hidden" name="action" value="update_fields">
                        <div class="field-grid" style="margin-bottom:1.25rem;">
                            <div>
                                <label class="edit-label">Número de Factura / O.S.</label>
                                <input type="text" name="factura" class="edit-input" placeholder="Ej. 162453"
                                    value="<?php echo htmlspecialchars($comision['factura'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="edit-label">Fecha de Facturación</label>
                                <input type="date" name="fecha_facturacion" class="edit-input"
                                    value="<?php echo $comision['fecha_facturacion'] ? date('Y-m-d', strtotime($comision['fecha_facturacion'])) : ''; ?>">
                            </div>
                            <div>
                                <label class="edit-label">Lugar / Zona</label>
                                <input type="text" name="lugar" class="edit-input"
                                    placeholder="Ej. Taller, León, Managua..."
                                    value="<?php echo htmlspecialchars($comision['lugar'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="edit-label">Vendedor / Captador</label>
                                <input type="text" name="vendedor" class="edit-input" placeholder="Nombre del vendedor"
                                    value="<?php echo htmlspecialchars($comision['vendedor'] ?? ''); ?>">
                            </div>
                        </div>
                        <div style="margin-bottom:1.25rem;">
                            <label class="edit-label">Notas / Observaciones</label>
                            <textarea name="notas" class="edit-input" rows="3"
                                placeholder="Agregar observaciones relevantes..."><?php echo htmlspecialchars($comision['notas'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="action-btn btn-edit" style="width:auto; padding: 0.65rem 2rem;">
                            <i class="ph ph-floppy-disk"></i> Guardar Cambios
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======= RIGHT: Actions Sidebar ======= -->
        <div>

            <!-- Mark As Paid -->
            <?php if ($isPending && $canEdit): ?>
                <div class="glass-card" style="border-color:rgba(16,185,129,0.2); background:rgba(16,185,129,0.06);">
                    <p class="card-title" style="color:#34d399;"><i class="ph ph-currency-circle-dollar"></i> Liquidar
                        Comisión</p>
                    <p style="font-size:0.85rem; color:#94a3b8; margin-bottom:1.25rem;">
                        Confirma que esta comisión ya fue entregada/pagada al técnico. Esta acción cierra el ciclo.
                    </p>
                    <?php if (!empty($comision['factura'])): ?>
                        <div
                            style="background:rgba(16,185,129,0.1); border-radius:6px; padding:0.75rem; margin-bottom:1rem; font-size:0.85rem; color:#6ee7b7; display:flex; gap:0.5rem; align-items:center;">
                            <i class="ph ph-check"></i> Factura
                            <strong><?php echo htmlspecialchars($comision['factura']); ?></strong> registrada.
                        </div>
                    <?php else: ?>
                        <div
                            style="background:rgba(251,146,60,0.1); border-radius:6px; padding:0.75rem; margin-bottom:1rem; font-size:0.85rem; color:#fbbf24; display:flex; gap:0.5rem; align-items:center;">
                            <i class="ph ph-warning"></i> Aún no hay número de factura registrado.
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="view.php?id=<?php echo $id; ?>"
                        onsubmit="return confirm('¿Confirmas que esta comisión fue PAGADA al técnico?\n\nEsta acción no se puede deshacer.');">
                        <input type="hidden" name="action" value="mark_paid">
                        <button type="submit" class="action-btn btn-paid">
                            <i class="ph ph-check-circle"></i> Marcar como Pagada
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="glass-card" style="text-align:center;">
                    <i class="ph ph-check-circle" style="font-size:2.5rem; color:#34d399;"></i>
                    <p style="margin:0.5rem 0 0; color:#34d399; font-weight:600;">Comisión Liquidada</p>
                    <?php if ($comision['fecha_pago']): ?>
                        <p style="font-size:0.85rem; color:var(--text-muted); margin:0.25rem 0 0;">
                            Pagada el <?php echo date('d/m/Y', strtotime($comision['fecha_pago'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Links to source -->
            <div class="glass-card">
                <p class="card-title"><i class="ph ph-link"></i> Referencia de Origen</p>
                <?php if ($comision['tipo'] === 'PROYECTO' && !empty($comision['reference_id'])): ?>
                    <a href="../levantamientos/view.php?id=<?php echo $comision['reference_id']; ?>"
                        class="action-btn btn-ref" style="display:flex;">
                        <i class="ph ph-clipboard-text"></i> Ver Proyecto Origen
                    </a>
                <?php elseif ($comision['tipo'] === 'SERVICIO' && !empty($comision['reference_id'])): ?>
                    <a href="../services/view.php?id=<?php echo $comision['reference_id']; ?>" class="action-btn btn-ref"
                        style="display:flex;">
                        <i class="ph ph-wrench"></i> Ver Servicio Origen
                    </a>
                <?php else: ?>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0;">Comisión manual sin referencia directa.
                    </p>
                <?php endif; ?>
                <a href="index.php" class="action-btn btn-ref" style="display:flex; margin-top:0.75rem;">
                    <i class="ph ph-list"></i> Volver a la Lista
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>