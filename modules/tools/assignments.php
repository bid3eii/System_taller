<?php
// modules/tools/assignments.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Asignaciones de Herramientas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (project_name LIKE ? OR assigned_to LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tool_assignments WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $assignments = [];
    $error = "Error al cargar asignaciones: " . $e->getMessage();
}
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Asignaciones de Herramientas</h1>
        <p class="text-muted">Gestiona las entregas y devoluciones de herramientas por proyecto.</p>
    </div>

    <?php if (isset($error) || isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error ?? $_GET['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Historial de Asignaciones</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por proyecto o encargado..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </form>
                <a href="assign.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nueva Asignación</a>
                <a href="index.php" class="btn btn-secondary"><i class="ph ph-wrench"></i> Inventario</a>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proyecto</th>
                        <th>Encargado</th>
                        <th>Entrega / Devolución</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assignments) > 0): ?>
                        <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td>#<?php echo $assignment['id']; ?></td>
                            <td>
                                <span class="font-medium"><?php echo htmlspecialchars($assignment['project_name']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($assignment['assigned_to']); ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                    <span class="text-sm"><i class="ph ph-arrow-right"></i> <?php echo date('d/m/Y', strtotime($assignment['delivery_date'])); ?></span>
                                    <?php if($assignment['return_date']): ?>
                                    <span class="text-sm text-muted"><i class="ph ph-arrow-left"></i> <?php echo date('d/m/Y', strtotime($assignment['return_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'pending' => 'var(--warning)',
                                    'delivered' => 'var(--info)',
                                    'returned' => 'var(--success)'
                                ];
                                $status_labels = [
                                    'pending' => 'Pendiente',
                                    'delivered' => 'Entregado',
                                    'returned' => 'Devuelto'
                                ];
                                $color = $status_colors[$assignment['status']] ?? 'var(--text-secondary)';
                                $label = $status_labels[$assignment['status']] ?? ucfirst($assignment['status']);
                                ?>
                                <span class="badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td>
                                <a href="print_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-secondary btn-icon" title="Imprimir" target="_blank"><i class="ph ph-printer"></i></a>
                                
                                <?php if($assignment['status'] !== 'returned'): ?>
                                <button onclick="openReturnModal(<?php echo $assignment['id']; ?>)" class="btn btn-secondary btn-icon" style="color: var(--success);" title="Registrar Devolución">
                                    <i class="ph ph-arrow-u-down-left"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-clipboard-text" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No hay asignaciones registradas</h3>
                                <p class="text-muted">Crea una nueva asignación para comenzar.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Custom Return Modal -->
<div id="returnModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Confirmar Devolución</h3>
            <button onclick="closeReturnModal()" class="modal-close"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 60px; height: 60px; background: var(--success); opacity: 0.1; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;"></div>
                <i class="ph ph-check-circle" style="font-size: 3rem; color: var(--success); position: absolute; margin-top: -72px; margin-left: -24px;"></i>
            </div>
            <p style="text-align: center; font-size: 1.1rem; margin-bottom: 0.5rem;">¿Estás seguro de registrar la devolución?</p>
            <p class="text-muted" style="text-align: center; font-size: 0.9rem;">Esta acción confirmará la recepción de todas las herramientas y devolverá el stock al inventario.</p>
        </div>
        <div class="modal-footer">
            <button onclick="closeReturnModal()" class="btn btn-secondary">Cancelar</button>
            <a id="confirmReturnBtn" href="#" class="btn btn-primary" style="background: var(--success); border-color: var(--success);">Confirmar Devolución</a>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-body" style="padding-bottom: 2rem;">
            <div style="text-align: center; margin-bottom: 1rem;">
                <div style="width: 60px; height: 60px; background: var(--success); opacity: 0.1; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;"></div>
                <i class="ph ph-check-circle" style="font-size: 3rem; color: var(--success); position: absolute; margin-top: -72px; margin-left: -24px;"></i>
            </div>
            <h3 style="text-align: center; margin: 0 0 0.5rem 0;">¡Operación Exitosa!</h3>
            <p class="text-muted" style="text-align: center; margin: 0;">Devolución registrada correctamente. Inventario actualizado.</p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button onclick="closeSuccessModal()" class="btn btn-primary" style="background: var(--success); border-color: var(--success); min-width: 120px; display: inline-flex; align-items: center; justify-content: center;">Aceptar</button>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 1rem;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transform: translateY(20px);
    transition: transform 0.3s ease;
    border: 1px solid var(--border-color);
}

.modal-overlay.active .modal-content {
    transform: translateY(0);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    padding: 2rem 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: var(--bg-hover);
    border-radius: 0 0 1rem 1rem;
}

/* Light Mode Overrides */
body.light-mode .modal-content {
    background: #ffffff;
    border-color: #e5e7eb;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

body.light-mode .modal-header {
    border-bottom-color: #f3f4f6;
}

body.light-mode .modal-footer {
    background-color: #f9fafb;
    border-top-color: #f3f4f6;
}

body.light-mode .modal-title {
    color: #111827;
}

body.light-mode .modal-body p {
    color: #374151;
}

body.light-mode .modal-body .text-muted {
    color: #6b7280 !important;
}
</style>

<script>
function openReturnModal(id) {
    const modal = document.getElementById('returnModal');
    const confirmBtn = document.getElementById('confirmReturnBtn');
    
    confirmBtn.href = 'process_return.php?id=' + id;
    
    modal.classList.add('active');
}

function closeReturnModal() {
    document.getElementById('returnModal').classList.remove('active');
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
    const url = new URL(window.location);
    url.searchParams.delete('success');
    window.history.replaceState({}, '', url);
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Check for success param
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'returned') {
        document.getElementById('successModal').classList.add('active');
    }

    // Close on outside click
    window.addEventListener('click', function(e) {
        const returnModal = document.getElementById('returnModal');
        const successModal = document.getElementById('successModal');
        if (e.target === returnModal) {
            closeReturnModal();
        }
        if (e.target === successModal) {
            closeSuccessModal();
        }
    });
});
</script>

<?php
require_once '../../includes/footer.php';
?>
