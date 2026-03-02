<?php
// modules/anexos/create.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('zlib.output_compression', '0');
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('anexos', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Nuevo Anexo Yazaki';

// Load surveys for dropdown
$stmtS = $pdo->prepare("SELECT id, title, client_name FROM project_surveys ORDER BY id DESC LIMIT 100");
$stmtS->execute();
$surveys = $stmtS->fetchAll();

// Load tools for autocomplete logic in JS
$stmtT = $pdo->prepare("SELECT id, name, description as type FROM tools ORDER BY name ASC");
$stmtT->execute();
$tools_json = json_encode($stmtT->fetchAll(PDO::FETCH_ASSOC));

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $survey_id = !empty($_POST['survey_id']) ? intval($_POST['survey_id']) : null;
        $client_name = trim($_POST['client_name'] ?? 'YAZAKI DE NICARAGUA SA');
        $user_id = $_SESSION['user_id'];

        // 1. Create main anexo
        $stmtA = $pdo->prepare("INSERT INTO anexos_yazaki (survey_id, user_id, client_name) VALUES (?, ?, ?)");
        $stmtA->execute([$survey_id, $user_id, $client_name]);
        $anexo_id = $pdo->lastInsertId();

        // 2. Insert rows from POST array
        $stmtRow = $pdo->prepare("INSERT INTO anexo_tools (anexo_id, row_index, tool_id, custom_description, quantity) VALUES (?, ?, ?, ?, ?)");
        
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            $rowIndex = 1;
            for ($i = 0; $i < count($_POST['qty']); $i++) {
                $qty = floatval($_POST['qty'][$i]);
                $tool_id = !empty($_POST['tool_id'][$i]) ? intval($_POST['tool_id'][$i]) : null;
                $custom_desc = trim($_POST['desc'][$i] ?? '');

                if ($qty > 0 && ($tool_id || $custom_desc !== '')) {
                    $stmtRow->execute([$anexo_id, $rowIndex, $tool_id, $custom_desc, $qty]);
                    $rowIndex++;
                }
            }
        }

        $pdo->commit();
        header("Location: view.php?id=$anexo_id&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al crear el anexo: " . $e->getMessage();
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1>Nuevo Anexo 10 (Yazaki)</h1>
            <p class="text-muted">Llenar las partidas manuales o seleccionar herramientas de bodega.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Volver a Bitácora
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"
            style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card" id="anexoForm">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="form-group">
                <label class="form-label font-medium"><i class="ph ph-projector-screen text-muted"></i> Vincular a un Levantamiento (Opcional)</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="hidden" name="survey_id" id="survey_id" value="">
                    <input type="text" id="survey_display" class="form-control" value="-- Independiente (Sin vínculo a proyecto) --" readonly style="cursor: pointer; opacity: 0.9;" onclick="openModal('modalProjects')">
                    <button type="button" class="btn btn-secondary" onclick="openModal('modalProjects')" style="padding: 0.5rem;">
                        <i class="ph ph-magnifying-glass"></i>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearProject()" title="Desvincular" style="padding: 0.5rem;">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label font-medium"><i class="ph ph-buildings text-muted"></i> Empresa Receptora (Fijo para formato)</label>
                <input type="text" name="client_name" class="form-control" value="YAZAKI DE NICARAGUA SA" readonly style="opacity: 0.8; cursor: not-allowed;">
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); margin-top: 1rem; margin-bottom: 1rem; padding-bottom: 0.5rem;">
            <h3 style="margin: 0;">Detalle de Herramientas / Equipos</h3>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="addManualRow()" style="font-size: 0.9rem;">
                    <i class="ph ph-pencil-simple"></i> Agregar Herramienta
                </button>
                <button type="button" class="btn btn-primary" onclick="openModal('modalTools')" style="font-size: 0.9rem; background: var(--primary-600);">
                    <i class="ph ph-database"></i> Buscar en Bodega
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="toolsTable" style="margin-bottom: 1rem;">
                <thead>
                    <tr>
                        <th style="width: 50px;">Item</th>
                        <th style="width: 100px;">Cant.</th>
                        <th>Seleccionar Herramienta / Describir Artículo Externo</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="toolsBody">
                    <!-- Rows injected by JS -->
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
            <button type="submit" class="btn btn-primary"
                style="background: var(--primary-600); font-size: 1.1rem; padding: 0.8rem 2rem;">
                <i class="ph ph-check-circle"></i> Guardar Anexo 10
            </button>
        </div>
    </form>
</div>

<!-- Modals HTML -->
<style>
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 999999 !important; justify-content: center; align-items: center;
    backdrop-filter: blur(4px);
}
.modal-content {
    background: var(--bg-card); width: 90%; max-width: 800px;
    border-radius: var(--radius); padding: 1.5rem; border: 1px solid var(--border-color);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); max-height: 85vh; display: flex; flex-direction: column;
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;
}
.modal-body {
    overflow-y: auto; flex-grow: 1; padding-right: 0.5rem;
}
.modal-close {
    background: transparent; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);
}
.modal-close:hover { color: var(--danger); }
.select-row:hover { background: rgba(255, 255, 255, 0.05); cursor: pointer; }
</style>

<!-- Modal Projects -->
<div id="modalProjects" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;"><i class="ph ph-projector-screen text-primary"></i> Seleccionar Levantamiento</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalProjects')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body custom-scrollbar">
            <input type="text" id="searchProjects" class="form-control" placeholder="Buscar por cliente o título..." onkeyup="filterTable('searchProjects', 'tableProjects')" style="margin-bottom: 1rem; padding: 0.8rem;">
            <table style="width: 100%;" id="tableProjects">
                <thead>
                    <tr><th style="text-align: left; padding: 0.5rem;">ID / Título</th><th style="text-align: left; padding: 0.5rem;">Cliente</th><th style="width: 100px;"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($surveys as $s): ?>
                        <tr class="select-row" style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.8rem;"><strong>#<?php echo $s['id']; ?></strong> - <?php echo htmlspecialchars($s['title']); ?></td>
                            <td style="padding: 0.8rem;"><?php echo htmlspecialchars($s['client_name'] ?? ''); ?></td>
                            <td style="padding: 0.8rem; text-align: right;">
                                <button type="button" class="btn btn-secondary" style="padding: 0.3rem 0.6rem;" onclick="selectProject(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['title'])); ?>')">Seleccionar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tools -->
<div id="modalTools" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;"><i class="ph ph-database text-primary"></i> Seleccionar de Bodega</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTools')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body custom-scrollbar">
            <input type="text" id="searchToolsModal" class="form-control" placeholder="Buscar herramienta por nombre o tipo..." onkeyup="filterTable('searchToolsModal', 'tableTools')" style="margin-bottom: 1rem; padding: 0.8rem;">
            <table style="width: 100%;" id="tableTools">
                <thead>
                    <tr><th style="text-align: left; padding: 0.5rem;">Herramienta</th><th style="text-align: left; padding: 0.5rem;">Detalle / Tipo</th><th style="width: 100px;"></th></tr>
                </thead>
                <tbody>
                    <?php 
                    $toolsDecoded = json_decode($tools_json, true);
                    foreach ($toolsDecoded as $t): ?>
                        <tr class="select-row" style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.8rem;"><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                            <td style="padding: 0.8rem; color: var(--text-muted); font-size: 0.9em;"><?php echo htmlspecialchars($t['type'] ?? ''); ?></td>
                            <td style="padding: 0.8rem; text-align: right;">
                                <button type="button" class="btn btn-primary" style="padding: 0.3rem 0.6rem; background: var(--primary-600);" onclick="addToolRow(this, <?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name'])); ?>')">
                                    <i class="ph ph-plus"></i> Añadir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JS Logic for Modals & Rows -->
<script>
    let rowCount = 0;
    
    // Modal controls
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    }

    // Project Logic
    function selectProject(id, title) {
        document.getElementById('survey_id').value = id;
        document.getElementById('survey_display').value = '#' + id + ' - ' + title;
        closeModal('modalProjects');
    }
    
    function clearProject() {
        document.getElementById('survey_id').value = '';
        document.getElementById('survey_display').value = '-- Independiente (Sin vínculo a proyecto) --';
    }

    // Generic Table Filter (Search inside Modal)
    function filterTable(inputId, tableId) {
        let input = document.getElementById(inputId);
        let filter = input.value.toLowerCase();
        let rows = document.getElementById(tableId).getElementsByTagName('tr');
        for (let i = 1; i < rows.length; i++) { // skip head
            let text = rows[i].textContent || rows[i].innerText;
            if (text.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }

    // Tool Rows Logic
    function addToolRow(btn, id, name) {
        rowCount++;
        let tr = document.createElement('tr');
        tr.innerHTML = '<td style=\"text-align: center; font-weight: bold;\" class=\"row-number\">' + rowCount + '</td>' + 
            '<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"qty[]\" class=\"form-control qty-input\" value=\"1\" style=\"padding: 0.4rem; font-size: 0.9rem;\"></td>' + 
            '<td>' + 
                '<input type=\"hidden\" name=\"tool_id[]\" value=\"' + id + '\">' + 
                '<input type=\"hidden\" name=\"desc[]\" value=\"' + name + '\">' + 
                '<div style=\"padding: 0.4rem 0.6rem; font-size: 0.95rem; background: rgba(56, 189, 248, 0.1); border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3); color: var(--text-primary); cursor: not-allowed; display: flex; align-items: center; gap: 0.5rem;\">' + 
                    '<i class=\"ph ph-database\" style=\"color: var(--primary-500);\"></i> <strong>' + name + '</strong>' + 
                '</div>' + 
            '</td>' + 
            '<td style=\"text-align: center;\"><button type=\"button\" class=\"btn-icon\" style=\"color: var(--danger); background: transparent; border: none; cursor: pointer;\" onclick=\"removeRow(this)\" title=\"Quitar\"><i class=\"ph ph-trash\"></i></button></td>';
        document.getElementById('toolsBody').appendChild(tr);
        reindexRows();
        
        if(btn) {
            let oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class=\"ph ph-check\"></i> Agregado';
            btn.style.background = 'var(--success)';
            setTimeout(() => {
                btn.innerHTML = oldHtml;
                btn.style.background = 'var(--primary-600)';
            }, 800);
        }
    }

    function addManualRow() {
        rowCount++;
        let tr = document.createElement('tr');
        tr.innerHTML = '<td style=\"text-align: center; font-weight: bold;\" class=\"row-number\">' + rowCount + '</td>' + 
            '<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"qty[]\" class=\"form-control qty-input\" value=\"1\" style=\"padding: 0.4rem; font-size: 0.9rem;\"></td>' + 
            '<td>' + 
                '<input type=\"hidden\" name=\"tool_id[]\" value=\"\">' + 
                '<input type=\"text\" name=\"desc[]\" class=\"form-control\" placeholder=\"Describe el artículo manual...\" style=\"padding: 0.4rem; font-size: 0.9rem;\" required>' + 
            '</td>' + 
            '<td style=\"text-align: center;\"><button type=\"button\" class=\"btn-icon\" style=\"color: var(--danger); background: transparent; border: none; cursor: pointer;\" onclick=\"removeRow(this)\" title=\"Quitar\"><i class=\"ph ph-trash\"></i></button></td>';
        document.getElementById('toolsBody').appendChild(tr);
        reindexRows();
    }

    function removeRow(btn) {
        btn.closest('tr').remove();
        reindexRows();
    }

    function reindexRows() {
        let rows = document.querySelectorAll('#toolsBody tr');
        rows.forEach((row, index) => {
            row.querySelector('.row-number').textContent = index + 1;
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
