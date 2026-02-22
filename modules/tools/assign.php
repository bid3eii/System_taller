<?php
// modules/tools/assign.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('tools', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Asignar Herramientas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$error = '';
$success = '';

// Check if tools table exists
try {
    $stmt = $pdo->query("SELECT * FROM tools WHERE status != 'lost' ORDER BY name ASC");
    $tools = $stmt->fetchAll();
} catch (PDOException $e) {
    $tools = [];
    $error = "Error al cargar herramientas: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['project_name']);
    $assigned_to = trim($_POST['assigned_to']);
    $technician_1 = trim($_POST['technician_1']);
    $technician_2 = trim($_POST['technician_2']);
    $technician_3 = trim($_POST['technician_3']);
    $delivery_date = $_POST['delivery_date'];
    $return_date = !empty($_POST['return_date']) ? $_POST['return_date'] : null;
    $observations = trim($_POST['observations']);
    $selected_tools = $_POST['tools'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if (empty($project_name) || empty($assigned_to) || empty($delivery_date)) {
        $error = 'Por favor complete los campos obligatorios (Proyecto, Encargado, Fecha de Entrega).';
    } elseif (empty($selected_tools)) {
        $error = 'Debe seleccionar al menos una herramienta.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO tool_assignments (project_name, assigned_to, technician_1, technician_2, technician_3, delivery_date, return_date, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$project_name, $assigned_to, $technician_1, $technician_2, $technician_3, $delivery_date, $return_date, $observations]);
            $assignment_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO tool_assignment_items (assignment_id, tool_id, quantity) VALUES (?, ?, ?)");
            $stmt_update_tool = $pdo->prepare("UPDATE tools SET quantity = quantity - ? WHERE id = ?");

            foreach ($selected_tools as $tool_id) {
                $qty = isset($quantities[$tool_id]) ? (int)$quantities[$tool_id] : 1;
                if ($qty > 0) {
                    $stmt_item->execute([$assignment_id, $tool_id, $qty]);
                    
                    // Update quantity
                    $stmt_update_tool->execute([$qty, $tool_id]);

                    // Check if quantity reached 0
                    $stmt_check = $pdo->prepare("SELECT quantity FROM tools WHERE id = ?");
                    $stmt_check->execute([$tool_id]);
                    $new_qty = $stmt_check->fetchColumn();

                    if ($new_qty <= 0) {
                        $pdo->prepare("UPDATE tools SET status = 'assigned' WHERE id = ?")->execute([$tool_id]);
                    }
                }
            }

            $pdo->commit();
            $success = 'Asignación creada correctamente.';
            echo "<script>window.location.href = 'assignments.php';</script>";
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error al guardar la asignación: ' . $e->getMessage();
        }
    }
}
?>

<div class="animate-enter">
    <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
        <a href="index.php" class="btn btn-secondary btn-icon"><i class="ph ph-arrow-left"></i></a>
        <div>
            <h1 style="margin: 0;">Nueva Asignación de Herramientas</h1>
            <p class="text-muted" style="margin: 0;">Asigna herramientas a un proyecto específico.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="assignmentForm">
        <div class="card" style="margin-bottom: 2rem;">
            <div style="padding: 1.5rem;">
                <h3 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Información del Proyecto</h3>
                
                <style>
                    .form-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 1.5rem;
                    }
                    .form-full {
                        grid-column: span 2;
                    }
                    .tech-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 1rem;
                        grid-column: span 2;
                    }
                    @media (max-width: 768px) {
                        .form-grid, .tech-grid {
                            grid-template-columns: 1fr;
                        }
                        .form-full, .tech-grid {
                            grid-column: span 1;
                        }
                    }
                </style>
                
                <div class="form-grid">
                    <!-- Project Info -->
                    <div class="form-group box-input">
                        <label class="form-label">Proyecto *</label>
                        <input type="text" name="project_name" class="form-control" required placeholder="Nombre del proyecto" autofocus>
                    </div>

                    <div class="form-group box-input">
                        <label class="form-label">Encargado *</label>
                        <input type="text" name="assigned_to" class="form-control" required placeholder="Nombre del encargado">
                    </div>

                    <!-- Technicians Section -->
                    <div class="tech-grid">
                        <div class="form-group box-input">
                            <label class="form-label">Técnico 1</label>
                            <input type="text" name="technician_1" class="form-control" placeholder="Nombre completo">
                        </div>
                        
                        <div class="form-group box-input">
                            <label class="form-label">Técnico 2</label>
                            <input type="text" name="technician_2" class="form-control" placeholder="Nombre completo">
                        </div>

                        <div class="form-group box-input">
                            <label class="form-label">Técnico 3</label>
                            <input type="text" name="technician_3" class="form-control" placeholder="Nombre completo">
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="form-group box-input">
                        <label class="form-label">Fecha de Entrega *</label>
                        <input type="date" name="delivery_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group box-input">
                        <label class="form-label">Fecha de Devolución Estimada</label>
                        <input type="date" name="return_date" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div style="padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <h3 style="margin: 0; font-size: 1.1rem;">Selección de Herramientas</h3>
                    <div class="input-group" style="width: 250px;">
                        <input type="text" id="toolSearch" class="form-control" placeholder="Filtrar herramientas..." style="padding-top: 0.4rem; padding-bottom: 0.4rem;">
                        <i class="ph ph-magnifying-glass input-icon"></i>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Herramienta</th>
                                <th>Descripción</th>
                                <th>Disponible</th>
                                <th style="width: 150px;">Cantidad a Asignar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="tools[]" value="<?php echo $tool['id']; ?>" class="tool-checkbox">
                                </td>
                                <td>
                                    <span class="font-medium"><?php echo htmlspecialchars($tool['name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($tool['description']); ?></td>
                                <td>
                                    <span class="badge" style="background: var(--bg-hover);">
                                        <?php echo htmlspecialchars($tool['quantity']); ?> 
                                    </span>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="quantities[<?php echo $tool['id']; ?>]" 
                                           class="form-control tool-quantity" 
                                           min="1" 
                                           max="<?php echo $tool['quantity']; ?>" 
                                           value="1"
                                           disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-group box-input" style="margin-top: 2rem;">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observations" class="form-control" rows="3" placeholder="Notas sobre el estado de las herramientas o detalles adicionales..."></textarea>
                </div>

                <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-check"></i> Registrar Asignación</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllInfo = document.getElementById('selectAll');
    const toolCheckboxes = document.querySelectorAll('.tool-checkbox');
    const toolQuantities = document.querySelectorAll('.tool-quantity');
    const tableRows = document.querySelectorAll('tbody tr');

    // Handle Row Clicks
    tableRows.forEach((row, index) => {
        row.style.cursor = 'pointer'; // Visual cue
        
        row.addEventListener('click', function(e) {
            // Prevent triggering if clicked directly on checkbox or input
            if (e.target.type === 'checkbox' || e.target.type === 'number') {
                return;
            }

            const checkbox = toolCheckboxes[index];
            checkbox.checked = !checkbox.checked;
            
            // Trigger change event manually
            checkbox.dispatchEvent(new Event('change'));
        });
    });

    // Handle Checkbox Changes
    toolCheckboxes.forEach((checkbox, index) => {
        checkbox.addEventListener('change', function() {
            // Enable/Disable quantity input
            const qtyInput = toolQuantities[index];
            qtyInput.disabled = !this.checked;
            
            // Highlight row
            const row = this.closest('tr');
            if (this.checked) {
                row.style.backgroundColor = 'var(--bg-hover)';
            } else {
                row.style.backgroundColor = '';
            }
        });
    });

    // Handle Select All
    if (selectAllInfo) {
        selectAllInfo.addEventListener('change', function() {
            toolCheckboxes.forEach((checkbox, index) => {
                checkbox.checked = this.checked;
                // Trigger change event to update UI
                checkbox.dispatchEvent(new Event('change'));
            });
        });
    }

    // Tool Search Filter
    const toolSearch = document.getElementById('toolSearch');
    if (toolSearch) {
        toolSearch.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const nameCell = row.cells[1];
                const descCell = row.cells[2];
                
                if (nameCell && descCell) {
                    const nameText = nameCell.textContent || nameCell.innerText;
                    const descText = descCell.textContent || descCell.innerText;
                    const text = (nameText + " " + descText).toLowerCase();
                    
                    if (text.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    }
});
</script>

<?php
require_once '../../includes/footer.php';
?>
