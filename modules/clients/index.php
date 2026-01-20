<?php
// modules/clients/index.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('clientes', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Clientes';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php'; // Actually Navbar

// Search Logic
$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR tax_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem;">
        <h1>Gestión de Clientes</h1>
        <p class="text-muted">Administra la base de datos de tus clientes.</p>
    </div>

    <!-- Clients Table -->
    <div class="card">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Listado de Clientes</h3>
            <div style="display: flex; gap: 1rem;">
                <form method="GET" style="display: flex; gap: 0.5rem; margin: 0;">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, DNI..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </form>
                <a href="add.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nuevo Cliente</a>
            </div>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>DNI / RUC</th>
                        <th>Contacto</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clients) > 0): ?>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 32px; height: 32px; background: var(--bg-hover); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-500); font-weight: bold;">
                                        <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
                                    </div>
                                    <span class="font-medium"><?php echo htmlspecialchars($client['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($client['tax_id']); ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                    <span class="text-sm"><i class="ph ph-phone"></i> <?php echo htmlspecialchars($client['phone']); ?></span>
                                    <span class="text-sm text-muted"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($client['email']); ?></span>
                                </div>
                            </td>
                            <td class="text-sm"><?php echo htmlspecialchars($client['address']); ?></td>
                            <td>
                                <a href="edit.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                <a href="history.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem;" title="Ver Historial"><i class="ph ph-clock-counter-clockwise"></i></a>
                                <a href="delete.php?id=<?php echo $client['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem; color: var(--danger);" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este cliente?');"><i class="ph ph-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 3rem;">
                                <div style="margin-bottom: 1rem; color: var(--text-secondary);">
                                    <i class="ph ph-users" style="font-size: 3rem;"></i>
                                </div>
                                <h3 style="margin-bottom: 0.5rem;">No se encontraron clientes</h3>
                                <p class="text-muted">Intenta con otra búsqueda o agrega un nuevo cliente.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once '../../includes/footer.php';
?>
