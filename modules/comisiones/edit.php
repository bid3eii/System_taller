<?php
// modules/comisiones/edit.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_edit', $pdo)) {
    $_SESSION['error'] = "No tienes permiso para editar incentivos.";
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch record
$stmt = $pdo->prepare("SELECT * FROM comisiones WHERE id = ?");
$stmt->execute([$id]);
$comision = $stmt->fetch();

if (!$comision) {
    $_SESSION['error'] = "El incentivo no existe.";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tech_id = intval($_POST['tech_id']);
    $cliente = clean($_POST['cliente']);
    $servicio = clean($_POST['servicio']);
    $cantidad = floatval($_POST['cantidad']);
    $lugar = clean($_POST['lugar'] ?? '');
    $factura = clean($_POST['factura'] ?? '');
    $caso = clean($_POST['caso'] ?? '');
    $estado = clean($_POST['estado']);
    $fecha_servicio = clean($_POST['fecha_servicio']);

    // Only update these flat fields, prevent changing reference structure if it's auto
    try {
        $updateStmt = $pdo->prepare("
            UPDATE comisiones SET 
                tech_id = ?, 
                fecha_servicio = ?, 
                cliente = ?, 
                servicio = ?, 
                lugar = ?, 
                factura = ?, 
                caso = ?, 
                cantidad = ?, 
                estado = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $tech_id,
            $fecha_servicio,
            $cliente,
            $servicio,
            $lugar,
            $factura,
            $caso,
            $cantidad,
            $estado,
            $id
        ]);

        $_SESSION['success'] = "Incentivo actualizado exitosamente.";
        header("Location: view.php?id=" . $id);
        exit;
    } catch (PDOException $e) {
        $error = "Error al actualizar el incentivo: " . $e->getMessage();
    }
}

// Fetch technicians
$stmtT = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username");
$technicians = $stmtT->fetchAll();

$page_title = 'Editar Incentivo #' . str_pad($comision['id'], 5, '0', STR_PAD_LEFT);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="ph ph-pencil-simple" style="color: var(--primary);"></i>
                Editar Incentivo #
                <?php echo str_pad($comision['id'], 5, '0', STR_PAD_LEFT); ?>
            </h1>
            <p class="text-muted">Modifica los detalles, técnico asignado o monto (cuota).</p>
        </div>
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Cancelar</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;"><i class="ph ph-warning-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($comision['reference_id']): ?>
        <div class="alert"
            style="background: var(--bg-surface); margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; border: 1px solid var(--border-color);">
            <i class="ph ph-info" style="font-size: 1.5rem; color: var(--primary);"></i>
            <div>
                <strong style="display: block;">Incentivo Generado Automáticamente</strong>
                <span class="text-muted">Este incentivo está enlazado al
                    <?php echo $comision['tipo'] === 'PROYECTO' ? 'Levantamiento' : 'Servicio'; ?> ID:
                    <?php echo $comision['reference_id']; ?>. Modifica la cuota con precaución.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2rem;">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Técnico Asignado <span
                            style="color:red;">*</span></label>
                    <select name="tech_id" class="form-control" required>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>" <?php echo $tech['id'] == $comision['tech_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Fecha de Servicio <span
                            style="color:red;">*</span></label>
                    <input type="date" name="fecha_servicio" class="form-control"
                        value="<?php echo htmlspecialchars($comision['fecha_servicio']); ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 600;">Cliente <span style="color:red;">*</span></label>
                <input type="text" name="cliente" class="form-control"
                    value="<?php echo htmlspecialchars($comision['cliente']); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 600;">Servicio Realizado <span
                        style="color:red;">*</span></label>
                <textarea name="servicio" class="form-control" rows="3"
                    required><?php echo htmlspecialchars($comision['servicio']); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Lugar</label>
                    <input type="text" name="lugar" class="form-control"
                        value="<?php echo htmlspecialchars($comision['lugar'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">No. Factura</label>
                    <input type="text" name="factura" class="form-control"
                        value="<?php echo htmlspecialchars($comision['factura'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Ref. / Caso / OT</label>
                    <input type="text" name="caso" class="form-control"
                        value="<?php echo htmlspecialchars($comision['caso']); ?>">
                </div>
            </div>

            <hr style="border-color: var(--border-color); margin-bottom: 2rem;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 1.1rem;">Cuota ($) <span
                            style="color:red;">*</span></label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: bold; color: var(--text-muted);">$</span>
                        <input type="number" step="0.01" min="0" name="cantidad" class="form-control"
                            style="padding-left: 2rem; font-size: 1.1rem; font-weight: bold;"
                            value="<?php echo htmlspecialchars($comision['cantidad']); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 1.1rem;">Estado del Pago</label>
                    <select name="estado" class="form-control" style="font-size: 1.1rem;">
                        <option value="PENDIENTE" <?php echo $comision['estado'] === 'PENDIENTE' ? 'selected' : ''; ?>
                            >Pendiente (Por Pagar)</option>
                        <option value="PAGADA" <?php echo $comision['estado'] === 'PAGADA' ? 'selected' : ''; ?>>Pagada
                        </option>
                    </select>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                    <i class="ph ph-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>