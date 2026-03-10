<?php
// modules/levantamientos/edit.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('surveys_edit', $pdo)) {
    die("Acceso denegado.");
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);
$error = '';
$success = '';


// Fetch Survey
$stmt = $pdo->prepare("SELECT * FROM project_surveys WHERE id = ?");
$stmt->execute([$id]);
$survey = $stmt->fetch();

if (!$survey) {
    die("Levantamiento no encontrado.");
}

// Cannot edit if already a project or if payment was already made
if (in_array($survey['status'], ['approved', 'in_progress', 'completed']) || $survey['payment_status'] === 'pagado') {
    die("No se puede editar un levantamiento que ya está en curso o ha sido pagado.");
}

// Security: If tech doesn't have view_all, only allow if they own it
if (!can_access_module('surveys_view_all', $pdo) && $survey['user_id'] != $_SESSION['user_id']) {
    die("Acceso denegado a este levantamiento.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = clean($_POST['client_name'] ?? '');
    $title = clean($_POST['title']);
    $general_description = clean($_POST['general_description']);
    $scope_activities = $_POST['scope_activities']; // Can contain HTML
    $estimated_time = clean($_POST['estimated_time']);

    $personnel_required = clean($_POST['personnel_required'] ?? '');

    // Materials arrays
    $mat_ids = $_POST['mat_id'] ?? [];
    $mat_descriptions = $_POST['mat_description'] ?? [];
    $mat_quantities = $_POST['mat_quantity'] ?? [];
    $mat_units = $_POST['mat_unit'] ?? [];
    $mat_notes = $_POST['mat_notes'] ?? [];

    if (empty($client_name) || empty($title)) {
        $error = "El cliente y el título son obligatorios.";
    } else {
        try {
            $pdo->beginTransaction();

            // 0. Prepare Audit Log Data (Before Changes)
            // $survey variable already contains old survey data fetched at the top
            $stmtOldMat = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
            $stmtOldMat->execute([$id]);
            $old_materials = $stmtOldMat->fetchAll(PDO::FETCH_ASSOC);

            $old_state = [
                'client_name' => $survey['client_name'],
                'title' => $survey['title'],
                'general_description' => $survey['general_description'],
                'scope_activities' => $survey['scope_activities'],
                'estimated_time' => $survey['estimated_time'],
                'personnel_required' => $survey['personnel_required'],
                'materials' => $old_materials
            ];

            // 1. Update Survey
            $stmtU = $pdo->prepare("
                UPDATE project_surveys 
                SET client_name = ?, title = ?, general_description = ?, scope_activities = ?, estimated_time = ?, personnel_required = ?
                WHERE id = ?
            ");

            $stmtU->execute([
                $client_name,
                $title,
                $general_description,
                $scope_activities,
                $estimated_time,
                $personnel_required,
                $id
            ]);

            // 2. Clear old materials
            $pdo->prepare("DELETE FROM project_materials WHERE survey_id = ?")->execute([$id]);

            // 3. Insert Materials & Build New State
            $new_materials = [];
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

                        $stmtMat->execute([$id, $desc, $qty, $unit, $notes]);

                        $new_materials[] = [
                            'item_description' => $desc,
                            'quantity' => $qty,
                            'unit' => $unit,
                            'notes' => $notes
                        ];
                    }
                }
            }

            $new_state = [
                'client_name' => $client_name,
                'title' => $title,
                'general_description' => $general_description,
                'scope_activities' => $scope_activities,
                'estimated_time' => $estimated_time,
                'personnel_required' => $personnel_required,
                'materials' => $new_materials
            ];

            // 4. Log Audit Action
            log_audit($pdo, 'project_surveys', $id, 'EDICION', $old_state, $new_state);

            $pdo->commit();
            header("Location: view.php?id=$id&msg=updated");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}

// Fetch existing materials
$stmtM = $pdo->prepare("SELECT * FROM project_materials WHERE survey_id = ? ORDER BY id ASC");
$stmtM->execute([$id]);
$materials = $stmtM->fetchAll();

$page_title = 'Editar Levantamiento';
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
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary" style="padding: 0.5rem;"><i
                class="ph ph-arrow-left"></i></a>
        <h1>Editar Levantamiento #
            <?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?>
        </h1>
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
                            value="<?php echo htmlspecialchars($survey['client_name']); ?>" required>
                        <i class="ph ph-buildings input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Título del Proyecto *</label>
                    <div class="input-group">
                        <input type="text" name="title" class="form-control"
                            placeholder="Ej. Implementación NAS y 20 Usuarios"
                            value="<?php echo htmlspecialchars($survey['title']); ?>" required>
                        <i class="ph ph-text-t input-icon"></i>
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 3;">
                    <label class="form-label">Descripción General del Proyecto</label>
                    <textarea name="general_description" class="form-control"
                        rows="3"><?php echo htmlspecialchars($survey['general_description']); ?></textarea>
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
                <textarea id="scope_activities"
                    name="scope_activities"><?php echo htmlspecialchars($survey['scope_activities']); ?></textarea>
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
                            value="<?php echo htmlspecialchars($survey['personnel_required']); ?>"
                            placeholder="Ej. 2 Técnicos, o 1 Especialista">
                        <i class="ph ph-users input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tiempo Estimado</label>
                    <div class="input-group">
                        <input type="text" name="estimated_time" class="form-control"
                            value="<?php echo htmlspecialchars($survey['estimated_time']); ?>">
                        <i class="ph ph-calendar input-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Materials Requirement -->
        <div class="card" style="margin-bottom: 2rem;">
            <div
                style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-list-dashes" style="font-size: 1.2rem; color: var(--success);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Requerimientos y Materiales</h3>
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
                        <?php if (count($materials) > 0): ?>
                            <?php foreach ($materials as $m): ?>
                                <tr class="material-row">
                                    <td>
                                        <input type="text" name="mat_description[]" class="form-control"
                                            value="<?php echo htmlspecialchars($m['item_description']); ?>"
                                            style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="mat_quantity[]" class="form-control"
                                            value="<?php echo floatval($m['quantity']); ?>" style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="text" name="mat_unit[]" class="form-control"
                                            value="<?php echo htmlspecialchars($m['unit']); ?>" style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="text" name="mat_notes[]" class="form-control"
                                            value="<?php echo htmlspecialchars($m['notes']); ?>" style="width: 100%;">
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-icon" style="color: var(--danger);"
                                            onclick="removeRow(this)">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="material-row">
                                <td><input type="text" name="mat_description[]" class="form-control"
                                        placeholder="Ej. Servidor NAS" style="width: 100%;"></td>
                                <td><input type="number" step="0.01" name="mat_quantity[]" class="form-control" value="1"
                                        style="width: 100%;"></td>
                                <td><input type="text" name="mat_unit[]" class="form-control" value="unidades"
                                        style="width: 100%;"></td>
                                <td><input type="text" name="mat_notes[]" class="form-control" placeholder="Opcional"
                                        style="width: 100%;"></td>
                                <td style="text-align: center;"><button type="button" class="btn-icon"
                                        style="color: var(--danger);" onclick="removeRow(this)"><i
                                            class="ph ph-trash"></i></button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Bottom Add Row Button -->
            <div style="margin-top: 1rem; text-align: center;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="addMaterialRow()">
                    <i class="ph ph-plus"></i> Añadir Ítem
                </button>
            </div>
        </div>

        <!-- Spacer so form content doesn't hide behind the fixed bar -->
        <div style="height: 5rem;"></div>
    </form>

    <!-- Fixed action bar pinned to bottom of screen -->
    <div style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
                background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(12px);
                border-top: 1px solid rgba(255,255,255,0.07);
                padding: 0.85rem 2rem;
                display: flex; justify-content: flex-end; gap: 0.85rem; align-items: center;
                box-shadow: 0 -4px 24px rgba(0,0,0,0.4);">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary"
            style="min-width: 110px; text-align: center;">Cancelar</a>
        <button form="surveyForm" type="submit" class="btn btn-primary"
            style="min-width: 160px; box-shadow: 0 4px 12px rgba(99,102,241,0.35); font-weight: 600;">
            <i class="ph ph-floppy-disk"></i> Guardar Cambios
        </button>
    </div>
</div>

<script>
    function addMaterialRow() {
        const container = document.getElementById('materialsContainer');
        const rowContent = `
        <tr class="material-row">
            <td><input type="text" name="mat_description[]" class="form-control" placeholder="Descripción..." style="width: 100%;"></td>
            <td><input type="number" step="0.01" name="mat_quantity[]" class="form-control" value="1" style="width: 100%;"></td>
            <td><input type="text" name="mat_unit[]" class="form-control" value="unidades" style="width: 100%;"></td>
            <td><input type="text" name="mat_notes[]" class="form-control" placeholder="Opcional" style="width: 100%;"></td>
            <td style="text-align: center;"><button type="button" class="btn-icon" style="color: var(--danger);" onclick="removeRow(this)"><i class="ph ph-trash"></i></button></td>
        </tr>
    `;
        container.insertAdjacentHTML('beforeend', rowContent);
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        if (document.querySelectorAll('.material-row').length > 1) {
            row.remove();
        } else {
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