<?php
// modules/comisiones/add.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_add', $pdo)) {
    $_SESSION['error'] = "No tienes permiso para agregar comisiones manuales.";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tech_id = intval($_POST['tech_id']);
    $cliente = clean($_POST['cliente']);
    $servicio = clean($_POST['servicio']);
    $cantidad = floatval($_POST['cantidad']);
    $tipo = 'SERVICIO'; // Default for manual
    $lugar = clean($_POST['lugar'] ?? '');
    $factura = clean($_POST['factura'] ?? '');
    $caso = clean($_POST['caso'] ?? 'Manual');
    $estado = clean($_POST['estado']);
    $fecha_servicio = clean($_POST['fecha_servicio']);

    try {
        // Vendedor can be current user
        $stmtV = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtV->execute([$_SESSION['user_id']]);
        $vendedor = $stmtV->fetchColumn() ?: 'Administrador';

        $stmt = $pdo->prepare("
            INSERT INTO comisiones 
            (fecha_servicio, cliente, servicio, cantidad, tipo, lugar, factura, vendedor, caso, estado, tech_id, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
        ");

        $stmt->execute([
            $fecha_servicio,
            $cliente,
            $servicio,
            $cantidad,
            $tipo,
            $lugar,
            $factura,
            $vendedor,
            $caso,
            $estado,
            $tech_id
        ]);

        $_SESSION['success'] = "Comisión manual registrada correctamente.";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error al registrar la comisión: " . $e->getMessage();
    }
}

// Fetch technicians
$stmtT = $pdo->query("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username");
$technicians = $stmtT->fetchAll();

$page_title = 'Nueva Comisión Manual';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <i class="ph ph-plus-circle" style="color: var(--primary);"></i>
                Nueva Comisión Manual
            </h1>
            <p class="text-muted">Ingresa los datos de la comisión que deseas asignar.</p>
        </div>
        <a href="index.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Volver</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;"><i class="ph ph-warning-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2rem;">
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Técnico Asignado <span
                            style="color:red;">*</span></label>
                    <select name="tech_id" class="form-control" required>
                        <option value="">Seleccione un técnico</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>">
                                <?php echo htmlspecialchars($tech['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Fecha de Servicio <span
                            style="color:red;">*</span></label>
                    <input type="date" name="fecha_servicio" class="form-control" value="<?php echo date('Y-m-d'); ?>"
                        required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 600;">Cliente <span style="color:red;">*</span></label>
                <input type="text" name="cliente" class="form-control"
                    placeholder="Nombre de la empresa o cliente individual" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 600;">Servicio Realizado <span
                        style="color:red;">*</span></label>
                <textarea name="servicio" class="form-control" rows="3"
                    placeholder="Descripción breve del trabajo efectuado" required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Lugar (Opcional)</label>
                    <input type="text" name="lugar" class="form-control" placeholder="Ubicación física">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">No. Factura (Opcional)</label>
                    <input type="text" name="factura" class="form-control" placeholder="FACT-001">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600;">Ref. / Caso / OT</label>
                    <input type="text" name="caso" class="form-control" value="Manual"
                        placeholder="Ticket o Referencia">
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
                            style="padding-left: 2rem; font-size: 1.1rem; font-weight: bold;" placeholder="0.00"
                            required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 1.1rem;">Estado del Pago</label>
                    <select name="estado" class="form-control" style="font-size: 1.1rem;">
                        <option value="PENDIENTE">Pendiente (Por Pagar)</option>
                        <option value="PAGADA">Pagada</option>
                    </select>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                    <i class="ph ph-floppy-disk"></i> Guardar Comisión
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>