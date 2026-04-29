<?php
// modules/levantamientos/edit.php
@session_start(['gc_probability' => 0]);
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

// Handle Image Deletion
if (isset($_GET['delete_img'])) {
    $imgId = intval($_GET['delete_img']);
    try {
        $stmtCheck = $pdo->prepare("SELECT image_path FROM project_survey_images WHERE id = ? AND survey_id = ?");
        $stmtCheck->execute([$imgId, $id]);
        $imgData = $stmtCheck->fetch();
        if ($imgData) {
            $filePath = '../../' . $imgData['image_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $pdo->prepare("DELETE FROM project_survey_images WHERE id = ?")->execute([$imgId]);
        }
        header("Location: edit.php?id=$id&msg=img_deleted");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar imagen: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = clean($_POST['client_name'] ?? '');
    $title = clean($_POST['title']);
    $general_description = clean($_POST['general_description']);
    $scope_activities = $_POST['scope_activities']; // Can contain HTML
    $trabajos_revisar = $_POST['trabajos_revisar'] ?? ''; // Can contain HTML
    $notas = $_POST['notas'] ?? ''; // Can contain HTML
    $estimated_time = clean($_POST['estimated_time']);

    $personnel_required = clean($_POST['personnel_required'] ?? '');
    $vendedor = clean($_POST['vendedor'] ?? '');

    // Materials arrays
    $mat_ids = $_POST['mat_id'] ?? [];
    $mat_descriptions = $_POST['mat_description'] ?? [];
    $mat_quantities = $_POST['mat_quantity'] ?? [];
    $mat_units = $_POST['mat_unit'] ?? [];
    $mat_notes = $_POST['mat_notes'] ?? [];

    // Tools arrays (Internal Use)
    $tool_ids = $_POST['tool_id'] ?? [];
    $tool_names_manual = $_POST['tool_name_manual'] ?? [];
    $tool_quantities = $_POST['tool_quantity'] ?? [];
    $tool_notes = $_POST['tool_notes'] ?? [];

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

            $stmtOldTools = $pdo->prepare("SELECT * FROM project_survey_tools WHERE survey_id = ? ORDER BY id ASC");
            $stmtOldTools->execute([$id]);
            $old_tools = $stmtOldTools->fetchAll(PDO::FETCH_ASSOC);

            $old_state = [
                'client_name' => $survey['client_name'],
                'title' => $survey['title'],
                'vendedor' => $survey['vendedor'],
                'general_description' => $survey['general_description'],
                'scope_activities' => $survey['scope_activities'],
                'trabajos_revisar' => $survey['trabajos_revisar'],
                'notas' => $survey['notas'],
                'estimated_time' => $survey['estimated_time'],
                'personnel_required' => $survey['personnel_required'],
                'materials' => $old_materials,
                'tools' => $old_tools
            ];

            // 1. Update Survey
            $stmtU = $pdo->prepare("
                UPDATE project_surveys 
                SET client_name = ?, title = ?, vendedor = ?, general_description = ?, scope_activities = ?, trabajos_revisar = ?, notas = ?, estimated_time = ?, personnel_required = ?
                WHERE id = ?
            ");

            $stmtU->execute([
                $client_name,
                $title,
                $vendedor,
                $general_description,
                $scope_activities,
                $trabajos_revisar,
                $notas,
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

            // 4. Clear old tools
            $pdo->prepare("DELETE FROM project_survey_tools WHERE survey_id = ?")->execute([$id]);

            // 5. Insert Tools & Build New State
            $new_tools = [];
            if (!empty($tool_ids) || !empty($tool_names_manual)) {
                $stmtTool = $pdo->prepare("
                    INSERT INTO project_survey_tools (survey_id, tool_id, tool_name, quantity, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");

                $max_tools = max(count($tool_ids), count($tool_names_manual));
                for ($i = 0; $i < $max_tools; $i++) {
                    $t_id = !empty($tool_ids[$i]) ? intval($tool_ids[$i]) : null;
                    $t_manual = clean($tool_names_manual[$i] ?? '');
                    
                    if ($t_id !== null || !empty($t_manual)) {
                        $t_qty = !empty($tool_quantities[$i]) ? intval($tool_quantities[$i]) : 1;
                        $t_notes = clean($tool_notes[$i] ?? '');

                        $stmtTool->execute([$id, $t_id, $t_manual, $t_qty, $t_notes]);

                        $new_tools[] = [
                            'tool_id' => $t_id,
                            'tool_name' => $t_manual,
                            'quantity' => $t_qty,
                            'notes' => $t_notes
                        ];
                    }
                }
            }

            $new_state = [
                'client_name' => $client_name,
                'title' => $title,
                'vendedor' => $vendedor,
                'general_description' => $general_description,
                'scope_activities' => $scope_activities,
                'trabajos_revisar' => $trabajos_revisar,
                'notas' => $notas,
                'estimated_time' => $estimated_time,
                'personnel_required' => $personnel_required,
                'materials' => $new_materials,
                'tools' => $new_tools
            ];

            // 6. Log Audit Action
            log_audit($pdo, 'project_surveys', $id, 'EDICION', $old_state, $new_state);

            // 7. Handle Image Uploads
            if (isset($_FILES['survey_images']) && !empty($_FILES['survey_images']['name'][0])) {
                $uploadDir = '../../uploads/levantamientos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $stmtImg = $pdo->prepare("INSERT INTO project_survey_images (survey_id, image_path, created_at) VALUES (?, ?, ?)");
                
                foreach ($_FILES['survey_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['survey_images']['error'][$key] === 0) {
                        $fileName = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', basename($_FILES['survey_images']['name'][$key]));
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($tmp_name, $targetPath)) {
                            $stmtImg->execute([$id, 'uploads/levantamientos/' . $fileName, get_local_datetime()]);
                        }
                    }
                }
            }

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

// Fetch existing tools
$stmtT = $pdo->prepare("
    SELECT pt.*, t.name as inventory_name 
    FROM project_survey_tools pt 
    LEFT JOIN tools t ON pt.tool_id = t.id 
    WHERE pt.survey_id = ? 
    ORDER BY pt.id ASC
");
$stmtT->execute([$id]);
$tools_list = $stmtT->fetchAll();

// Fetch tools for dropdown
$available_tools = $pdo->query("SELECT id, name FROM tools WHERE status = 'available' ORDER BY name ASC")->fetchAll();

// Fetch Survey Images
$stmtImages = $pdo->prepare("SELECT id, image_path FROM project_survey_images WHERE survey_id = ? ORDER BY id ASC");
$stmtImages->execute([$id]);
$survey_images = $stmtImages->fetchAll();

$page_title = 'Editar Levantamiento';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<!-- TinyMCE CDN for Rich Text -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    function initTinyMCE() {
        const isLight = document.body.classList.contains('light-mode') || localStorage.getItem('theme') === 'light';
        tinymce.init({
            selector: '#scope_activities, #trabajos_revisar, #notas',
            height: 500,
            resize: 'vertical',
            menubar: true,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
            ],
            toolbar: 'undo redo | formatselect fontselect fontsizeselect | ' +
                'bold italic underline strikethrough forecolor backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'link image media table emoticons | removeformat | fullscreen help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:15px }',
            skin: isLight ? 'oxide' : 'oxide-dark',
            content_css: isLight ? 'default' : 'dark',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }

    // Inicializar en carga
    initTinyMCE();

    // Re-inicializar cuando el usuario cambie el tema
    window.addEventListener('DOMContentLoaded', () => {
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                // Pequeño retardo para permitir que se actualice el body.classList en el otro script
                setTimeout(() => {
                    tinymce.remove('#scope_activities, #trabajos_revisar, #notas');
                    initTinyMCE();
                }, 100);
            });
        }
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

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'img_deleted'): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            Imagen eliminada correctamente.
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="surveyForm" enctype="multipart/form-data">
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

                <div class="form-group">
                    <label class="form-label">Vendedor Asignado</label>
                    <div class="input-group">
                        <input type="text" name="vendedor" class="form-control" 
                            value="<?php echo htmlspecialchars($survey['vendedor'] ?? ''); ?>"
                            placeholder="Nombre del Vendedor">
                        <i class="ph ph-user-tag input-icon"></i>
                    </div>
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
                <textarea id="scope_activities" name="scope_activities"><?php echo htmlspecialchars($survey['scope_activities']); ?></textarea>
            </div>
        </div>

        <!-- 2.5 Trabajo a Realizar -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-magnifying-glass" style="font-size: 1.2rem; color: var(--warning);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Trabajo a Realizar</h3>
            </div>
            <div class="form-group">
                <textarea id="trabajos_revisar" name="trabajos_revisar"><?php echo htmlspecialchars($survey['trabajos_revisar'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- 2.6 Notas -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-note" style="font-size: 1.2rem; color: var(--info);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Notas Adicionales</h3>
            </div>
            <div class="form-group">
                <textarea id="notas" name="notas"><?php echo htmlspecialchars($survey['notas'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- 2.7 Evidencia Fotográfica -->
        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <i class="ph ph-image" style="font-size: 1.2rem; color: var(--primary-500);"></i>
                <h3 style="margin: 0; color: var(--text-primary);">Evidencia Fotográfica (Anexos)</h3>
            </div>
            
            <?php if (!empty($survey_images)): ?>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                <?php foreach ($survey_images as $img): ?>
                    <div style="position: relative; width: 100px; height: 100px;">
                        <a href="<?php echo BASE_URL . $img['image_path']; ?>" target="_blank">
                            <img src="<?php echo BASE_URL . $img['image_path']; ?>" alt="Evidencia" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        </a>
                        <button type="button" onclick="deleteSurveyImage(<?php echo $img['id']; ?>)" 
                                style="position: absolute; top: -8px; right: -8px; width: 24px; height: 24px; border-radius: 50%; background: #ef4444; color: white; border: 2px solid var(--bg-card); cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0;">
                            <i class="ph ph-x" style="font-size: 0.8rem;"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
            function deleteSurveyImage(id) {
                if(confirm('¿Seguro que deseas eliminar esta imagen de forma permanente?')) {
                    window.location.href = 'edit.php?id=<?php echo $id; ?>&delete_img=' + id;
                }
            }
            </script>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Adjuntar Más Imágenes (opcional)</label>
                <input type="file" name="survey_images[]" id="survey_images_input" multiple accept="image/*" class="form-control" style="padding: 0.5rem;">
                <div id="survey_images_preview" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;"></div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const fileInput = document.getElementById('survey_images_input');
                const previewContainer = document.getElementById('survey_images_preview');
                let selectedFiles = [];

                fileInput.addEventListener('change', function(e) {
                    for (let i = 0; i < this.files.length; i++) {
                        selectedFiles.push(this.files[i]);
                    }
                    updateInputAndPreview();
                });

                function updateInputAndPreview() {
                    const dt = new DataTransfer();
                    selectedFiles.forEach(file => dt.items.add(file));
                    fileInput.files = dt.files;

                    previewContainer.innerHTML = '';
                    selectedFiles.forEach((file, index) => {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.style.cssText = 'position: relative; width: 100px; height: 100px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden;';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                            
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.innerHTML = '<i class="ph ph-x" style="font-size: 0.8rem;"></i>';
                            btn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 20px; height: 20px; border-radius: 50%; background: #ef4444; color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; z-index: 10;';
                            btn.onclick = function() {
                                selectedFiles.splice(index, 1);
                                updateInputAndPreview();
                            };

                            div.appendChild(img);
                            div.appendChild(btn);
                            previewContainer.appendChild(div);
                        }
                        reader.readAsDataURL(file);
                    });
                }
            });
            </script>
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

        <!-- 5. Tool Requirement (Internal Use) -->

        <div class="glass-card" style="margin-top: 2rem; border-left: 4px solid var(--warning); padding: 1.5rem; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; right: 0; padding: 0.5rem 1rem; background: rgba(245, 158, 11, 0.1); color: var(--warning); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; border-bottom-left-radius: 12px;">
                Borrador / Logística
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin: 0; color: #f8fafc; display: flex; align-items: center; gap: 0.75rem; font-size: 1.25rem;">
                    <div style="width: 36px; height: 36px; background: rgba(245, 158, 11, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="ph ph-wrench" style="color: var(--warning); font-size: 1.25rem;"></i>
                    </div>
                    Requerimiento de Herramientas
                </h3>
                <p style="margin: 0.5rem 0 0 0; color: #94a3b8; font-size: 0.9rem; line-height: 1.4;">
                    Seleccione herramientas del inventario o ingrese manualmente las necesarias para la ejecución técnica.
                </p>
            </div>

            <div style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 1.5rem; border: 1px solid rgba(255,255,255,0.05);">
                <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0; color: #f8fafc; font-size: 1.05rem;">Herramientas Seleccionadas</h4>
                        <p style="margin: 0.25rem 0 0 0; color: #94a3b8; font-size: 0.85rem;">Lista de herramientas para la solicitud.</p>
                    </div>
                    <button type="button" class="btn btn-sm" onclick="openToolModal()" style="background: rgba(245, 158, 11, 0.1); color: var(--warning); border: 1px dashed var(--warning); border-radius: 20px; transition: all 0.2s;" onmouseover="this.style.background='rgba(245, 158, 11, 0.2)'" onmouseout="this.style.background='rgba(245, 158, 11, 0.1)'">
                        <i class="ph ph-plus-circle"></i> Agregar Herramienta
                    </button>
                </div>

                <div id="toolsCardContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                    <?php if (count($tools_list) > 0): ?>
                        <?php foreach ($tools_list as $t): ?>
                            <?php 
                                $isManual = empty($t['tool_id']);
                                $display_id = $t['tool_id'];
                                $display_name = $isManual ? $t['tool_name'] : $t['inventory_name'];
                                $qty = $t['quantity'];
                                $notes = $t['notes'];
                            ?>
                            <div class="tool-card">
                                <div style="background: rgba(245, 158, 11, 0.1); border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative;">
                                    <i class="ph ph-wrench" style="color: var(--warning); font-size: 1.25rem;"></i>
                                    <?php if ($isManual): ?><i class="ph-fill ph-warning" style="position: absolute; bottom: -4px; right: -4px; color: var(--danger); font-size: 0.85rem;" title="Ingreso manual"></i><?php endif; ?>
                                </div>
                                <div style="flex: 1; overflow: hidden;">
                                    <h4 style="margin: 0; color: #f1f5f9; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;"><?php echo htmlspecialchars($display_name ?? ''); ?></h4>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                        <span style="font-size: 0.75rem; color: var(--warning); background: rgba(245, 158, 11, 0.15); padding: 0.1rem 0.5rem; border-radius: 4px; font-weight: 600;">Cant: <?php echo htmlspecialchars($qty); ?></span>
                                        <?php if ($notes): ?><span style="font-size: 0.8rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><i class="ph ph-text-align-left"></i> <?php echo htmlspecialchars($notes); ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-icon" style="color: #64748b; background: rgba(0,0,0,0.2); border-radius: 50%; padding: 0.4rem; transition: all 0.2s;" onclick="this.closest('.tool-card').remove()" onmouseover="this.style.color='var(--danger)'; this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.color='#64748b'; this.style.background='rgba(0,0,0,0.2)'" title="Eliminar">
                                    <i class="ph ph-x" style="font-size: 1rem;"></i>
                                </button>
                                <input type="hidden" name="tool_id[]" value="<?php echo htmlspecialchars($display_id ?? ''); ?>">
                                <input type="hidden" name="tool_name_manual[]" value="<?php echo htmlspecialchars($isManual ? $display_name : ''); ?>">
                                <input type="hidden" name="tool_quantity[]" value="<?php echo htmlspecialchars($qty); ?>">
                                <input type="hidden" name="tool_notes[]" value="<?php echo htmlspecialchars($notes ?? ''); ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <style>
                @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
                .tool-list-item:hover { background: rgba(245, 158, 11, 0.1) !important; color: var(--warning) !important; }
                .tool-list-item.selected { background: rgba(245, 158, 11, 0.15) !important; border-left: 3px solid var(--warning); color: var(--warning) !important; font-weight: 600; }
                
                .tool-card {
                    background: rgba(0,0,0,0.3);
                    border: 1px solid rgba(255,255,255,0.05);
                    border-radius: 10px;
                    padding: 0.75rem 1rem;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    transition: all 0.2s;
                    position: relative;
                }
                .tool-card:hover { border-color: rgba(245, 158, 11, 0.3); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
                
                #toolList::-webkit-scrollbar { width: 6px; }
                #toolList::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
                #toolList::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
                #toolList::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
            </style>
        </div>

        <!-- Spacer so form content doesn't hide behind the fixed bar -->
        <div style="height: 5rem;"></div>
    </form>

    <!-- Modal for Tool Selection -->
    <div id="toolModal" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center;">
        <div style="width: 100%; max-width: 500px; padding: 0; margin: 1rem; overflow: hidden; animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2);">
                <h3 style="margin: 0; color: #f8fafc; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-wrench" style="color: var(--warning);"></i> Añadir Herramienta
                </h3>
                <button type="button" class="btn-icon" onclick="closeToolModal()" style="color: #94a3b8;"><i class="ph ph-x" style="font-size: 1.25rem;"></i></button>
            </div>
            
            <div style="padding: 1.5rem;">
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 0.35rem; border-radius: 8px;">
                    <button type="button" id="tabInvBtn" onclick="switchToolTab('inv')" style="flex: 1; padding: 0.6rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; background: rgba(245, 158, 11, 0.1); color: var(--warning);">Desde Inventario</button>
                    <button type="button" id="tabManBtn" onclick="switchToolTab('man')" style="flex: 1; padding: 0.6rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: transparent; color: #94a3b8; transition: all 0.2s;">Ingreso Manual</button>
                </div>

                <div id="tabInv">
                    <div style="position: relative; margin-bottom: 1rem;">
                        <i class="ph ph-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none;"></i>
                        <input type="text" id="toolSearch" class="form-control" placeholder="Buscar en inventario..." style="width: 100%; padding-left: 2.5rem; background: rgba(15, 23, 42, 0.5); border-color: rgba(255,255,255,0.1);" oninput="filterTools()">
                    </div>
                    
                    <div id="toolList" style="height: 220px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 1.5rem; background: rgba(0,0,0,0.2);">
                        <?php foreach ($available_tools as $at): ?>
                            <div class="tool-list-item" data-id="<?php echo $at['id']; ?>" data-name="<?php echo htmlspecialchars($at['name']); ?>" onclick="selectToolItem(this)" style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.02); transition: all 0.2s; color: #cbd5e1; display: flex; align-items: center; gap: 0.75rem;">
                                <i class="ph ph-circle sel-icon" style="color: #64748b; font-size: 1.1rem;"></i>
                                <?php echo htmlspecialchars($at['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" id="modalToolId">
                    <input type="hidden" id="modalToolName">
                </div>

                <div id="tabMan" style="display: none; height: 265px;">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; display: block; font-weight: 500;">Nombre de la herramienta</label>
                        <input type="text" id="modalManualName" class="form-control" placeholder="Ej: Taladro percutor..." style="width: 100%; background: rgba(15, 23, 42, 0.5); border-color: rgba(255,255,255,0.1);">
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.05); border: 1px dashed rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 1rem; text-align: center;">
                        <i class="ph ph-info" style="color: var(--warning); font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0; color: #cbd5e1; font-size: 0.85rem;">Utilice esta opción únicamente si la herramienta no se encuentra registrada en el inventario oficial.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="flex: 1;">
                        <label style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; display: block; font-weight: 500;">Cantidad</label>
                        <input type="number" id="modalQty" class="form-control" value="1" min="1" style="width: 100%; background: rgba(15, 23, 42, 0.5); border-color: rgba(255,255,255,0.1); text-align: center;">
                    </div>
                    <div style="flex: 2;">
                        <label style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; display: block; font-weight: 500;">Notas (Opcional)</label>
                        <input type="text" id="modalNotes" class="form-control" placeholder="Observaciones..." style="width: 100%; background: rgba(15, 23, 42, 0.5); border-color: rgba(255,255,255,0.1);">
                    </div>
                </div>

                <button type="button" class="btn btn-primary" onclick="addToolFromModal()" style="width: 100%; display: flex; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 600; font-size: 1rem; box-shadow: 0 4px 12px rgba(99,102,241,0.25);">
                    <i class="ph ph-plus-circle" style="font-size: 1.2rem;"></i> Agregar a la Solicitud
                </button>
            </div>
        </div>
    </div>

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

    function openToolModal() {
        document.getElementById('toolModal').style.display = 'flex';
        // Reset form
        document.getElementById('toolSearch').value = '';
        filterTools();
        document.querySelectorAll('.tool-list-item').forEach(el => {
            el.classList.remove('selected');
            el.querySelector('.sel-icon').classList.replace('ph-check-circle', 'ph-circle');
            el.querySelector('.sel-icon').style.color = '#64748b';
        });
        document.getElementById('modalToolId').value = '';
        document.getElementById('modalToolName').value = '';
        document.getElementById('modalManualName').value = '';
        document.getElementById('modalQty').value = '1';
        document.getElementById('modalNotes').value = '';
        switchToolTab('inv');
        document.getElementById('toolSearch').focus();
    }

    function closeToolModal() {
        document.getElementById('toolModal').style.display = 'none';
    }

    function switchToolTab(tab) {
        if (tab === 'inv') {
            document.getElementById('tabInv').style.display = 'block';
            document.getElementById('tabMan').style.display = 'none';
            document.getElementById('tabInvBtn').style.background = 'rgba(245, 158, 11, 0.1)';
            document.getElementById('tabInvBtn').style.color = 'var(--warning)';
            document.getElementById('tabManBtn').style.background = 'transparent';
            document.getElementById('tabManBtn').style.color = '#94a3b8';
        } else {
            document.getElementById('tabInv').style.display = 'none';
            document.getElementById('tabMan').style.display = 'block';
            document.getElementById('tabManBtn').style.background = 'rgba(245, 158, 11, 0.1)';
            document.getElementById('tabManBtn').style.color = 'var(--warning)';
            document.getElementById('tabInvBtn').style.background = 'transparent';
            document.getElementById('tabInvBtn').style.color = '#94a3b8';
            document.getElementById('modalManualName').focus();
        }
    }

    function filterTools() {
        const q = document.getElementById('toolSearch').value.toLowerCase();
        document.querySelectorAll('.tool-list-item').forEach(el => {
            if (el.dataset.name.toLowerCase().includes(q)) el.style.display = 'flex';
            else el.style.display = 'none';
        });
    }

    function selectToolItem(el) {
        document.querySelectorAll('.tool-list-item').forEach(e => {
            e.classList.remove('selected');
            e.querySelector('.sel-icon').classList.replace('ph-check-circle', 'ph-circle');
            e.querySelector('.sel-icon').style.color = '#64748b';
        });
        el.classList.add('selected');
        el.querySelector('.sel-icon').classList.replace('ph-circle', 'ph-check-circle');
        el.querySelector('.sel-icon').style.color = 'var(--warning)';
        
        document.getElementById('modalToolId').value = el.dataset.id;
        document.getElementById('modalToolName').value = el.dataset.name;
    }

    function addToolFromModal() {
        const isManual = document.getElementById('tabMan').style.display === 'block';
        let id = '', name = '';
        
        if (isManual) {
            name = document.getElementById('modalManualName').value.trim();
            if (!name) { alert('Ingrese el nombre de la herramienta'); return; }
        } else {
            id = document.getElementById('modalToolId').value;
            name = document.getElementById('modalToolName').value;
            if (!id) { alert('Seleccione una herramienta del inventario'); return; }
        }
        
        const qty = document.getElementById('modalQty').value || 1;
        const notes = document.getElementById('modalNotes').value.trim();
        
        renderToolCard(id, name, qty, notes);
        closeToolModal();
    }

    function renderToolCard(id, name, qty, notes) {
        const container = document.getElementById('toolsCardContainer');
        const manualName = id === '' ? name : '';
        const isManual = id === '';
        
        const cardHTML = `
            <div class="tool-card">
                <div style="background: rgba(245, 158, 11, 0.1); border-radius: 8px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative;">
                    <i class="ph ph-wrench" style="color: var(--warning); font-size: 1.25rem;"></i>
                    ${isManual ? '<i class="ph-fill ph-warning" style="position: absolute; bottom: -4px; right: -4px; color: var(--danger); font-size: 0.85rem;" title="Ingreso manual"></i>' : ''}
                </div>
                <div style="flex: 1; overflow: hidden;">
                    <h4 style="margin: 0; color: #f1f5f9; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">${name}</h4>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                        <span style="font-size: 0.75rem; color: var(--warning); background: rgba(245, 158, 11, 0.15); padding: 0.1rem 0.5rem; border-radius: 4px; font-weight: 600;">Cant: ${qty}</span>
                        ${notes ? `<span style="font-size: 0.8rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><i class="ph ph-text-align-left"></i> ${notes}</span>` : ''}
                    </div>
                </div>
                <button type="button" class="btn-icon" style="color: #64748b; background: rgba(0,0,0,0.2); border-radius: 50%; padding: 0.4rem; transition: all 0.2s;" onclick="this.closest('.tool-card').remove()" onmouseover="this.style.color='var(--danger)'; this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.color='#64748b'; this.style.background='rgba(0,0,0,0.2)'" title="Eliminar">
                    <i class="ph ph-x" style="font-size: 1rem;"></i>
                </button>
                <input type="hidden" name="tool_id[]" value="${id}">
                <input type="hidden" name="tool_name_manual[]" value="${manualName}">
                <input type="hidden" name="tool_quantity[]" value="${qty}">
                <input type="hidden" name="tool_notes[]" value="${notes}">
            </div>
        `;
        container.insertAdjacentHTML('beforeend', cardHTML);
    }
</script>


<?php
require_once '../../includes/footer.php';
?>