<?php
// modules/clients/search_clients.php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';


// Check permission
if (!can_access_module('clients', $pdo)) {
    die("Acceso denegado.");
}

$search = $_GET['search'] ?? '';
$where = "1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR tax_id LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT DISTINCT c.* 
    FROM clients c 
    LEFT JOIN service_orders so ON c.id = so.client_id 
    WHERE $where 
      AND (so.id IS NULL OR so.service_type != 'warranty' OR so.problem_reported != 'Garantía Registrada')
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$clients = $stmt->fetchAll();

if (count($clients) > 0): 
    foreach ($clients as $client): 
?>
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
            <button type="button" class="btn btn-secondary" style="padding: 0.4rem; font-size: 1rem; color: var(--danger);" title="Eliminar" onclick="openDeleteModal(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name'], ENT_QUOTES); ?>')"><i class="ph ph-trash"></i></button>
        </td>
    </tr>
<?php 
    endforeach;
else: 
?>
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
