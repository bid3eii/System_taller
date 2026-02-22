<?php
// modules/clients/history.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('clients', $pdo)) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    die("Cliente no encontrado.");
}

// Fetch Services
// Fetch All Orders (Services & Warranties are in service_orders table)
// Need to join equipments table to get equipment details
$stmt = $pdo->prepare("
    SELECT so.*, e.brand, e.model, e.type as equipment_type 
    FROM service_orders so
    LEFT JOIN equipments e ON so.equipment_id = e.id
    WHERE so.client_id = ? 
    ORDER BY so.created_at DESC
");
$stmt->execute([$id]);
$all_orders = $stmt->fetchAll();

$page_title = 'Historial de Cliente';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php'; // Navbar
?>

<div class="animate-enter" style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem;"><i class="ph ph-arrow-left"></i></a>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Historial de Cliente</h1>
                <p class="text-muted" style="margin: 0;">Actividad y órdenes recientes</p>
            </div>
        </div>
        <div>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-secondary"><i class="ph ph-pencil-simple"></i> Editar Perfil</a>
        </div>
    </div>

    <!-- Client Profile Card -->
    <div class="card mb-6">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <div style="width: 64px; height: 64px; background: var(--primary-100); color: var(--primary-600); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold;">
                <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
            </div>
            <div>
                <h2 style="font-size: 1.25rem; margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($client['name']); ?></h2>
                <div style="display: flex; gap: 1.5rem; color: var(--text-secondary); font-size: 0.95rem;">
                    <?php if($client['tax_id']): ?>
                        <span><i class="ph ph-identification-card"></i> <?php echo htmlspecialchars($client['tax_id']); ?></span>
                    <?php endif; ?>
                    <?php if($client['phone']): ?>
                        <span><i class="ph ph-phone"></i> <?php echo htmlspecialchars($client['phone']); ?></span>
                    <?php endif; ?>
                    <?php if($client['email']): ?>
                        <span><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($client['email']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if($client['address']): ?>
                    <div style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">
                        <i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($client['address']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Orders History -->
    <h3 class="mb-4">Historial de Órdenes</h3>
    
    <?php if(empty($all_orders)): ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <i class="ph ph-scroll" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
            <h3 style="margin: 0 0 0.5rem 0;">Sin Historial</h3>
            <p class="text-muted">Este cliente aún no tiene órdenes de servicio ni garantías registradas.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Equipo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_orders as $order): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <?php if($order['service_type'] === 'warranty'): ?>
                                        <span class="badge badge-purple">Garantía</span>
                                    <?php else: ?>
                                        <span class="badge badge-blue">Servicio</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['equipment_type']); ?></strong>
                                    <div class="text-sm text-muted"><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'badge-gray';
                                        if (in_array($order['status'], ['pending', 'received'])) $statusClass = 'badge-yellow';
                                        if (in_array($order['status'], ['diagnosing', 'repairing'])) $statusClass = 'badge-blue';
                                        if ($order['status'] === 'ready') $statusClass = 'badge-purple';
                                        if ($order['status'] === 'delivered') $statusClass = 'badge-green';
                                        if ($order['status'] === 'cancelled') $statusClass = 'badge-red';
                                        
                                        echo "<span class='badge $statusClass'>" . strtoupper($order['status']) . "</span>";
                                    ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php 
                                        $viewLink = ($order['service_type'] === 'warranty') ? '../warranties/view.php' : '../services/view.php';
                                    ?>
                                    <a href="<?php echo $viewLink; ?>?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary">Ver Detalles</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
require_once '../../includes/footer.php';
?>
