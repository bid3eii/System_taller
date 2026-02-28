<?php
// modules/levantamientos/add.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys_add', $pdo)) {
    die("Acceso denegado.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = clean($_POST['client_name'] ?? '');
    $title = clean($_POST['title']);
    $general_description = clean($_POST['general_description']);
    $scope_activities = $_POST['scope_activities']; // Can contain HTML if using rich text
    $estimated_time = clean($_POST['estimated_time']);
    $personnel_required = clean($_POST['personnel_required']);
    $user_id = $_SESSION['user_id'];

    // Materials arrays
    $mat_descriptions = $_POST['mat_description'] ?? [];
    $mat_quantities = $_POST['mat_quantity'] ?? [];
    $mat_units = $_POST['mat_unit'] ?? [];
    $mat_notes = $_POST['mat_notes'] ?? [];

    if (empty($client_name) || empty($title)) {
        $error = "El cliente y el título son obligatorios.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert Survey
            $stmt = $pdo->prepare("
                INSERT INTO project_surveys 
                (client_name, user_id, title, general_description, scope_activities, estimated_time, personnel_required, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");

            $stmt->execute([
                $client_name,
                $user_id,
                $title,
                $general_description,
                $scope_activities,
                $estimated_time,
                $personnel_required,
                get_local_datetime()
            ]);

            $survey_id = $pdo->lastInsertId();

            // 2. Insert Materials
            if (!empty($mat_descriptions)) {
                $stmtMat = $pdo->prepare("
                    INSERT INTO project_materials (survey_id, item_description, quantity, unit, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");

                for ($i = 0; $i < count($mat_descriptions); $i++) {
                    $desc = clean($mat_descriptions[$i]);
                    if (!empty($desc)) {
                        $qty = !empty($mat_quantities[$i]) ? $mat_quantities[$i] : 1;
                        $unit = clean($mat_units[$i] ?? 'unidades');
                        $notes = clean($mat_notes[$i] ?? '');

                        $stmtMat->execute([$survey_id, $desc, $qty, $unit, $notes]);
                    }
                }
            }

            $pdo->commit();
            header("Location: index.php?msg=added");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al guardar el levantamiento: " . $e->getMessage();
        }
    }
}

$page_title = 'Nuevo Levantamiento';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<!-- TinyMCE CDN for Rich Text -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#scope_activities',
        height: 300,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
            'searchreplace', 'visualblocks', 'fullscreen',
            'insertdatetime', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
        skin: document.body.classList.contains('light-mode') ? 'oxide' : 'oxide-dark',
        content_css: document.body.classList.contains('light-mode') ? 'default' : 'dark'
    });
</script>

<div class="animate-enter" style="max-width: 1000px; margin: 0 auto;">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
        <a href="index.php" class="btn btn-secondary" style="padding: 0.5rem;"><i class="ph ph-arrow-left"></i></a>
        <h1>Nuevo Levantamiento</h1>
    </div>

    <?php if ($error): ?>
        <div
            style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="surveyForm">
        <!-- 1. General Data -->
        <div class="card" style="margin-bottom: 2rem;">
            <h3
                style="margin-top: 0; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary);">
                Datos del Proyecto</h3>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Cliente / Empresa *</label>
                    <div class="input-group">
                        <input type="text" name="client_name" class="form-control"
                            placeholder="Nombre completo del cliente o empresa" required>
                        <i class="ph ph-buildings input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Título del Proyecto *</label>
                    <div class="input-group">
                        <input type="text" name="title" class="form-control"
                            placeholder="Ej. Implementación NAS y 20 Usuarios" required>
                        <i class="ph ph-text-t input-icon"></i>
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 3;">
                    <label class="form-label">Descripción General del Proyecto</label>
                    <textarea name="general_description" class="form-control" rows="3"
                        placeholder="Se requiere la implementación completa de la unidad NAS..."></textarea>
                </div>
            </div>
        </div>

        <!-- 2. Scope & Activities -->
        <div class="card" style="margin-bottom: 2rem;">
            <div
                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-target" style="font-size: 1.2rem; color: var(--primary-500);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Alcances del Proyecto (Actividades)</h3>
            </div>

            <div class="form-group">
                <textarea id="scope_activities" name="scope_activities"></textarea>
                <p class="text-xs text-muted" style="margin-top: 0.5rem;">Describe detalladamente las tareas que incluye
                    el servicio.</p>
            </div>
        </div>

        <!-- 3. Time & Resources Estimation -->
        <div class="card" style="margin-bottom: 2rem;">
            <div
                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-clock" style="font-size: 1.2rem; color: var(--warning);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Estimación de Tiempo y Recursos</h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Personal Requerido</label>
                    <div class="input-group">
                        <input type="text" name="personnel_required" class="form-control"
                            placeholder="Ej. 1 técnico especializado (mínimo)">
                        <i class="ph ph-users input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tiempo Estimado</label>
                    <div class="input-group">
                        <input type="text" name="estimated_time" class="form-control"
                            placeholder="Ej. 3 a 5 días hábiles">
                        <i class="ph ph-calendar input-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Materials Requirement -->
        <div class="card" style="margin-bottom: 2rem;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-list-dashes" style="font-size: 1.2rem; color: var(--success);"></i>
                    <h3 style="margin: 0; color: var(--text-primary);">Requerimientos y Materiales</h3>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addMaterialRow()">
                    <i class="ph ph-plus"></i> Añadir Ítem
                </button>
            </div>

            <div class="table-container">
                <table style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Descripción del Ítem</th>
                            <th style="width: 15%;">Cantidad</th>
                            <th style="width: 15%;">Unidad</th>
                            <th style="width: 15%;">Notas</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="materialsContainer">
                        <!-- Default Row -->
                        <tr class="material-row">
                            <td>
                                <input type="text" name="mat_description[]" class="form-control"
                                    placeholder="Ej. Servidor NAS Synology DS224+" style="width: 100%;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="mat_quantity[]" class="form-control" value="1"
                                    style="width: 100%;">
                            </td>
                            <td>
                                <input type="text" name="mat_unit[]" class="form-control" value="unidades"
                                    placeholder="unidades, m, l" style="width: 100%;">
                            </td>
                            <td>
                                <input type="text" name="mat_notes[]" class="form-control" placeholder="Opcional"
                                    style="width: 100%;">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-icon" style="color: var(--danger);"
                                    onclick="removeRow(this)">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; position: sticky; bottom: 2rem; z-index: 10;">
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                <i class="ph ph-floppy-disk"></i> Guardar Borrador
            </button>
        </div>
    </form>
</div>

<script>
    function addMaterialRow() {
        const container = document.getElementById('materialsContainer');
        const rowContent = `
        <tr class="material-row">
            <td>
                <input type="text" name="mat_description[]" class="form-control" placeholder="Descripción del material..." style="width: 100%;">
            </td>
            <td>
                <input type="number" step="0.01" name="mat_quantity[]" class="form-control" value="1" style="width: 100%;">
            </td>
            <td>
                <input type="text" name="mat_unit[]" class="form-control" value="unidades" placeholder="unidades, m, l" style="width: 100%;">
            </td>
            <td>
                <input type="text" name="mat_notes[]" class="form-control" placeholder="Opcional" style="width: 100%;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-icon" style="color: var(--danger);" onclick="removeRow(this)">
                    <i class="ph ph-trash"></i>
                </button>
            </td>
        </tr>
    `;
        container.insertAdjacentHTML('beforeend', rowContent);
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        // Ensure at least one row exists
        if (document.querySelectorAll('.material-row').length > 1) {
            row.remove();
        } else {
            // Clear instead of removing
            const inputs = row.querySelectorAll('input');
            inputs[0].value = ''; // desc
            inputs[1].value = '1'; // qty
            inputs[2].value = 'unidades'; // unit
            inputs[3].value = ''; // notes
        }
    }

</script>

<?php
require_once '../../includes/footer.php';
?>