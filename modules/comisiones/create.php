<?php
// modules/comisiones/create.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check permission
if (!can_access_module('comisiones_add', $pdo)) {
    die("Acceso denegado.");
}

$page_title = 'Nueva Comisión';

// Pre-fill parameters if arriving from Levantamientos
$survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : '';
$project_title = isset($_GET['title']) ? $_GET['title'] : '';

// Fetch active technicians for the dropdown
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username ASC");
$stmt->execute();
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .form-card {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    .matrix-wrapper {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow-x: auto;
        margin-top: 1rem;
    }

    .matrix-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .matrix-table th,
    .matrix-table td {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .matrix-table th {
        background: var(--bg-surface);
        font-weight: 600;
        color: var(--text-muted);
    }
    
    .tech-col-header {
        min-width: 150px;
    }
    
    .delete-row-btn, .delete-col-btn {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 1.2rem;
        opacity: 0.7;
        transition: 0.2s;
    }
    .delete-row-btn:hover, .delete-col-btn:hover {
        opacity: 1;
        transform: scale(1.1);
    }

    .grand-total-box {
        background: var(--primary-900);
        color: var(--primary-100);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--primary-700);
        text-align: right;
        margin-top: 2rem;
    }
</style>

<div class="animate-enter">
    <div class="page-header">
        <div>
            <h1 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph ph-coins" style="color: var(--warning);"></i>
                Generar Comisiones
            </h1>
            <p class="text-muted">Calculadora y registro de comisiones para técnicos.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <form id="comisionesForm" action="save.php" method="POST">
        <input type="hidden" name="survey_id" value="<?php echo htmlspecialchars($survey_id); ?>">

        <div class="form-card">
            <div class="form-row">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight: bold;">MANTENIMIENTO / PROYECTO</label>
                    <input type="text" name="project_title" class="form-control" 
                           value="<?php echo htmlspecialchars($project_title); ?>" required
                           <?php echo $survey_id ? 'readonly' : ''; ?>
                           style="font-size: 1.1rem; font-weight: bold; padding: 0.75rem;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight: bold;">FECHA DE REGISTRO</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required
                           style="font-size: 1.1rem; padding: 0.75rem;">
                </div>
            </div>
            
            <hr style="border-color: var(--border-color); margin: 2rem 0;">

            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin-bottom: 0.25rem;">Asignación de Técnicos</h3>
                    <p class="text-muted" style="margin: 0; font-size: 0.9rem;">Agrega técnicos para abrir sus respectivas columnas en la tabla de pagos.</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="techSelect" class="form-control" style="width: 250px;">
                        <option value="">-- Seleccionar Técnico --</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>" data-name="<?php echo htmlspecialchars($tech['username']); ?>">
                                <?php echo htmlspecialchars($tech['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="addTechBtn" class="btn btn-secondary">
                        <i class="ph ph-user-plus"></i> Añadir Técnico
                    </button>
                </div>
            </div>

            <div class="matrix-wrapper">
                <table class="matrix-table" id="matrixTable">
                    <thead>
                        <tr id="matrixHeaderRow">
                            <th style="width: 50px;"></th>
                            <th style="text-align: left; width: 250px;">Concepto de Pago</th>
                            <!-- Dynamic tech columns will be appended here -->
                        </tr>
                    </thead>
                    <tbody id="matrixBody">
                        <!-- Dynamic Concept Rows -->
                        <tr class="concept-row" id="row_1">
                            <td>
                                <button type="button" class="delete-row-btn" onclick="removeRow(this)"><i class="ph ph-trash"></i></button>
                            </td>
                            <td>
                                <input type="text" name="concepts[]" class="form-control" value="Instalación Base" required placeholder="Ej. Cableado">
                            </td>
                            <!-- Tech inputs appended here -->
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--bg-surface);">
                            <td colspan="2" style="text-align: right; font-weight: bold;">TOTAL POR TÉCNICO:</td>
                            <!-- Dynamic total columns -->
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div style="margin-top: 1rem;">
                <button type="button" id="addRowBtn" class="btn btn-secondary" style="border-style: dashed;">
                    <i class="ph ph-plus"></i> Añadir Concepto Extra
                </button>
            </div>

            <div class="grand-total-box">
                <p style="margin: 0; font-size: 1rem; opacity: 0.8;">GRAN TOTAL DEL PROYECTO</p>
                <div style="font-size: 2.5rem; font-weight: bold; margin-top: 0.5rem;">
                    $<span id="grandTotalDisplay">0.00</span>
                </div>
                <input type="hidden" name="total_amount" id="grandTotalInput" value="0">
            </div>

            <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="background: var(--success); font-size: 1.1rem; padding: 0.75rem 2rem;">
                    <i class="ph ph-floppy-disk"></i> Guardar Comisiones
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Javascript Logic for handling dynamic Tech columns and Concept rows and totals.
    let techs = [];
    let rowCount = 1;

    document.getElementById('addTechBtn').addEventListener('click', function() {
        const select = document.getElementById('techSelect');
        const techId = select.value;
        const techName = select.options[select.selectedIndex]?.getAttribute('data-name');

        if (!techId) return;
        
        // Check if already added
        if (techs.find(t => t.id === techId)) {
            alert('Este técnico ya fue añadido.');
            return;
        }

        const tIndex = techs.length;
        techs.push({ id: techId, name: techName, index: tIndex });

        // 1. Add Column Header
        const headerRow = document.getElementById('matrixHeaderRow');
        const th = document.createElement('th');
        th.className = 'tech-col-header';
        th.id = `th_tech_${tIndex}`;
        th.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span><i class="ph ph-user"></i> ${techName}</span>
                <button type="button" class="delete-col-btn" onclick="removeTech(${tIndex})"><i class="ph ph-x-circle"></i></button>
            </div>
            <input type="hidden" name="tech_ids[]" value="${techId}">
        `;
        headerRow.appendChild(th);

        // 2. Add input to all existing rows
        const rows = document.querySelectorAll('.concept-row');
        rows.forEach(row => {
            const td = document.createElement('td');
            td.className = `td_tech_${tIndex}`;
            td.innerHTML = `<input type="number" step="0.01" min="0" name="amounts[${techId}][]" class="form-control text-center amount-input" value="0" oninput="calculateTotals()">`;
            row.appendChild(td);
        });

        // 3. Add footer total cell
        const tfoot = document.querySelector('tfoot tr');
        const fTd = document.createElement('td');
        fTd.className = `foot_tech_${tIndex}`;
        fTd.innerHTML = `<h4 style="margin: 0; color: var(--success);">$<span id="total_tech_${techId}">0.00</span></h4>`;
        tfoot.appendChild(fTd);

        // Reset select
        select.value = '';
        calculateTotals();
    });

    document.getElementById('addRowBtn').addEventListener('click', function() {
        rowCount++;
        const tbody = document.getElementById('matrixBody');
        const tr = document.createElement('tr');
        tr.className = 'concept-row';
        tr.id = `row_${rowCount}`;
        
        let html = `
            <td>
                <button type="button" class="delete-row-btn" onclick="removeRow(this)"><i class="ph ph-trash"></i></button>
            </td>
            <td>
                <input type="text" name="concepts[]" class="form-control" placeholder="Nuevo Concepto" required>
            </td>
        `;

        techs.forEach(t => {
            html += `
            <td class="td_tech_${t.index}">
                <input type="number" step="0.01" min="0" name="amounts[${t.id}][]" class="form-control text-center amount-input" value="0" oninput="calculateTotals()">
            </td>
            `;
        });

        tr.innerHTML = html;
        tbody.appendChild(tr);
    });

    window.removeRow = function(btn) {
        const rows = document.querySelectorAll('.concept-row');
        if (rows.length <= 1) {
            alert('Debe haber al menos un concepto.');
            return;
        }
        btn.closest('tr').remove();
        calculateTotals();
    };

    window.removeTech = function(tIndex) {
        const tObj = techs.find(t => t.index === tIndex);
        if(!tObj) return;

        // Remove header
        document.getElementById(`th_tech_${tIndex}`).remove();
        // Remove from body
        document.querySelectorAll(`.td_tech_${tIndex}`).forEach(el => el.remove());
        // Remove from footer
        document.querySelector(`.foot_tech_${tIndex}`).remove();

        techs = techs.filter(t => t.index !== tIndex);
        calculateTotals();
    };

    window.calculateTotals = function() {
        let grandTotal = 0;

        techs.forEach(t => {
            let techTotal = 0;
            const inputs = document.querySelectorAll(`input[name="amounts[${t.id}][]"]`);
            inputs.forEach(input => {
                const val = parseFloat(input.value) || 0;
                techTotal += val;
            });
            document.getElementById(`total_tech_${t.id}`).innerText = techTotal.toFixed(2);
            grandTotal += techTotal;
        });

        document.getElementById('grandTotalDisplay').innerText = grandTotal.toFixed(2);
        document.getElementById('grandTotalInput').value = grandTotal.toFixed(2);
    };

    // Form Validation ensure at least 1 tech
    document.getElementById('comisionesForm').addEventListener('submit', function(e) {
        if (techs.length === 0) {
            e.preventDefault();
            alert('Debes agregar al menos un técnico para asignar comisiones.');
        }
    });

</script>

<?php require_once '../../includes/footer.php'; ?>
