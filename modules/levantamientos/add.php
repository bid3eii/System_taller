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


// Pre-fill from Agenda Event if supplied
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$pre_client = '';
$pre_title = '';

if ($event_id) {
    $stmtE = $pdo->prepare("SELECT title, location FROM schedule_events WHERE id = ?");
    $stmtE->execute([$event_id]);
    $eventData = $stmtE->fetch();
    if ($eventData) {
        $pre_title = $eventData['title'];
        $pre_client = $eventData['location']; // Location usually contains client name in this context
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = clean($_POST['client_name'] ?? '');
    $title = clean($_POST['title']);
    $general_description = clean($_POST['general_description']);
    $scope_activities = $_POST['scope_activities']; // Can contain HTML if using rich text
    $estimated_time = clean($_POST['estimated_time']);

    $personnel_required = clean($_POST['personnel_required'] ?? '');
    $vendedor = clean($_POST['vendedor'] ?? '');

    $user_id = $_SESSION['user_id'];

    // Materials arrays
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

            // 1. Insert Survey
            $stmt = $pdo->prepare("
                INSERT INTO project_surveys 
                (client_name, user_id, vendedor, title, general_description, scope_activities, estimated_time, personnel_required, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");

            $stmt->execute([
                $client_name,
                $user_id,
                $vendedor,
                $title,
                $general_description,
                $scope_activities,
                $estimated_time,
                $personnel_required,
                get_local_datetime()
            ]);

            $survey_id = $pdo->lastInsertId();

            // 4. Link back to Agenda Event
            $form_event_id = isset($_POST['origin_event_id']) ? intval($_POST['origin_event_id']) : $event_id;
            if ($form_event_id) {
                $stmtLink = $pdo->prepare("UPDATE schedule_events SET survey_id = ? WHERE id = ?");
                $stmtLink->execute([$survey_id, $form_event_id]);
            }

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

            // 3. Insert Tools (Internal Use)
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

                        $stmtTool->execute([$survey_id, $t_id, $t_manual, $t_qty, $t_notes]);
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

// Fetch tools for dropdown
$available_tools = $pdo->query("SELECT id, name FROM tools WHERE status = 'available' ORDER BY name ASC")->fetchAll();
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
        <input type="hidden" name="origin_event_id" value="<?php echo $event_id; ?>">
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
                            placeholder="Nombre completo del cliente o empresa" required
                            value="<?php echo htmlspecialchars($pre_client); ?>">
                        <i class="ph ph-buildings input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Título del Proyecto *</label>
                    <div class="input-group">
                        <input type="text" name="title" class="form-control"
                            placeholder="Ej. Implementación NAS y 20 Usuarios" required
                            value="<?php echo htmlspecialchars($pre_title); ?>">
                        <i class="ph ph-text-t input-icon"></i>
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 3;">
                    <label class="form-label">Descripción General del Proyecto</label>
                    <textarea name="general_description" class="form-control" rows="3"
                        placeholder="Se requiere la implementación completa de la unidad NAS..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Vendedor Asignado</label>
                    <div class="input-group">
                        <input type="text" name="vendedor" class="form-control" placeholder="Nombre del Vendedor">
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
                            placeholder="Ej. 2 Técnicos, o 1 Especialista">
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
                    <!-- JS will append tool cards here -->
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
        <a href="index.php" class="btn btn-secondary" style="min-width: 110px; text-align: center;">Cancelar</a>
        <button form="surveyForm" type="submit" class="btn btn-primary"
            style="min-width: 160px; box-shadow: 0 4px 12px rgba(99,102,241,0.35); font-weight: 600;">
            <i class="ph ph-floppy-disk"></i> Guardar Borrador
        </button>
    </div>
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
                <input type="text" name="mat_unit[]" class="form-control" value="unidades" style="width: 100%;">
            </td>
            <td>
                <input type="text" name="mat_notes[]" class="form-control" placeholder="Opcional" style="width: 100%;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-icon" style="color: var(--danger);" onclick="removeRow(this)"><i class="ph ph-trash"></i></button>
            </td>
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
            inputs[0].value = '';
            inputs[1].value = '1';
            inputs[2].value = 'unidades';
            inputs[3].value = '';
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