<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificación de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !can_access_module('viaticos_add', $pdo)) {
    header("Location: " . BASE_URL . "modules/viaticos/index.php");
    exit;
}

// Fetch all technicians/users to populate the dropdown
$stmt = $pdo->query("SELECT id, username FROM users WHERE status = 'active' ORDER BY username ASC");
$all_techs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Nuevo Viático';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<style>
    .viatico-grid {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-card);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .viatico-grid th,
    .viatico-grid td {
        border: 1px solid var(--border-color);
        padding: 0.75rem;
        text-align: right;
        position: relative;
    }

    .viatico-grid th {
        background: var(--bg-body);
        font-weight: 600;
        color: var(--text-main);
        text-align: center;
    }

    .viatico-grid th:first-child,
    .viatico-grid td:first-child {
        text-align: left;
        font-weight: 600;
        background: var(--bg-body);
        width: 200px;
    }

    .cat-header {
        background: rgba(var(--primary-rgb), 0.05) !important;
        color: var(--primary) !important;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .subtotal-row td {
        background: rgba(var(--secondary-rgb), 0.05);
        font-weight: bold;
        color: var(--text-main);
    }

    .grand-total-row td {
        background: rgba(var(--primary-rgb), 0.1);
        font-weight: bold;
        font-size: 1.1rem;
        color: var(--primary);
        border-top: 2px solid var(--primary);
    }

    .amount-input {
        width: 100%;
        background: transparent;
        border: none;
        color: var(--text-main);
        text-align: right;
        font-family: inherit;
        font-size: 1rem;
        outline: none;
        padding: 0.25rem;
    }

    .amount-input:focus {
        background: rgba(var(--primary-rgb), 0.1);
        border-radius: 4px;
    }

    .amount-input::-webkit-outer-spin-button,
    .amount-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .remove-col-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s;
    }

    th:hover .remove-col-btn {
        opacity: 1;
    }
</style>
<main class="main-content">
    <div class="content-header">
        <div class="header-title">
            <h1>Crear Nuevo Viático</h1>
            <p class="text-muted">Presupuesto estilo matriz (Técnicos vs Gastos)</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline"><i class="ph ph-arrow-left"></i> Volver</a>
        </div>
    </div>

    <!-- Configuration Form -->
    <form id="viaticoForm" method="POST" action="save.php" style="max-width: 1200px; margin: 0 auto;">

        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-body"
                style="display: grid; grid-template-columns: 2fr 1fr 1.5fr; gap: 1.5rem; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label"
                        style="font-size: 0.85rem; font-weight: bold;">MANTENIMIENTO
                        / PROYECTO</label>
                    <input type="text" name="project_title" class="form-control"
                        placeholder="Ej. PLOTTER YAZAKI PLANTA 6" required
                        style="font-size: 1.1rem; font-weight: bold; padding: 0.75rem;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Tech Adder -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Añadir Técnico (Columna)</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <select id="techSelector" class="form-control">
                            <option value="" disabled selected>Selecciona un técnico...</option>
                            <?php foreach ($all_techs as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddTech" class="btn btn-secondary"><i
                                class="ph ph-plus"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Matrix -->
        <div style="overflow-x: auto; padding-bottom: 1rem;">
            <table class="viatico-grid" id="mainGrid">
                <thead>
                    <tr id="headerRow">
                        <th>DETALLE</th>
                        <!-- Columns will be injected here -->
                    </tr>
                </thead>
                <tbody id="gridBody">
                    <!-- FOOD SECTION -->
                    <tr class="cat-header">
                        <td colspan="1" class="col-span-dynamic">ALIMENTOS</td>
                    </tr>
                    <tr data-type="predetermined" data-cat="food" data-label="Desayuno">
                        <td>DESAYUNO</td>
                    </tr>
                    <tr data-type="predetermined" data-cat="food" data-label="Almuerzo">
                        <td>ALMUERZO</td>
                    </tr>
                    <tr data-type="predetermined" data-cat="food" data-label="Cena">
                        <td>CENA</td>
                    </tr>
                    <tr class="subtotal-row" data-subtotal="food">
                        <td>SUBTOTAL ALIMENTOS</td>
                    </tr>

                    <!-- TRANSPORT SECTION -->
                    <tr class="cat-header">
                        <td colspan="1" class="col-span-dynamic">TRANSPORTE</td>
                    </tr>
                    <tr data-type="predetermined" data-cat="transport" data-label="AM">
                        <td>AM</td>
                    </tr>
                    <tr data-type="predetermined" data-cat="transport" data-label="PM">
                        <td>PM</td>
                    </tr>
                    <tr class="subtotal-row" data-subtotal="transport">
                        <td>SUBTOTAL TRANSPORTE</td>
                    </tr>

                    <!-- CUSTOM/OTHER SECTION -->
                    <tr class="cat-header" id="customCatHeader" style="display: none;">
                        <td colspan="1" class="col-span-dynamic">OTROS</td>
                    </tr>
                    <!-- Custom rows injected here -->

                    <tr class="subtotal-row" id="customSubtotalRow" data-subtotal="other" style="display: none;">
                        <td>SUBTOTAL OTROS</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="grand-total-row">
                        <td>TOTAL</td>
                        <!-- Footer totals injected here -->
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Custom Row Adder -->
        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem; width: 400px;">
                <input type="text" id="customConceptInput" class="form-control"
                    placeholder="Agregar fila (ej. Hospedaje, Caseta)">
                <button type="button" id="btnAddRow" class="btn btn-outline"><i class="ph ph-plus"></i> Añadir
                    Concepto</button>
            </div>
            <div style="flex: 1; text-align: right;">
                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);">
                    Gran Total: $<span id="grandTotalDisplay">0.00</span>
                    <input type="hidden" name="total_amount" id="grandTotalInput" value="0.00">
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                <i class="ph ph-floppy-disk"></i> Guardar Viáticos
            </button>
        </div>
    </form>
<script>
const addedTechs = [];
let techIndex = 0;

// Elements
const selTech = document.getElementById('techSelector');
const btnAddTech = document.getElementById('btnAddTech');
const headerRow = document.getElementById('headerRow');
const grandTotalRow = document.querySelector('.grand-total-row');
const grandTotalDisplay = document.getElementById('grandTotalDisplay');
const grandTotalInput = document.getElementById('grandTotalInput');
const customConceptInput = document.getElementById('customConceptInput');
const btnAddRow = document.getElementById('btnAddRow');

btnAddTech.addEventListener('click', () => {
const val = selTech.value;
const text = selTech.options[selTech.selectedIndex].text;

if (!val) return;
if (addedTechs.some(t => t.id === val)) {
alert('Ese técnico ya fue añadido.');
return;
}

// Register
const tech = { id: val, name: text, colIndex: techIndex++ };
addedTechs.push(tech);

// 1. Add Header Cell
const th = document.createElement('th');
th.innerHTML = `${text.toUpperCase()}
<button type="button" class="remove-col-btn" onclick="removeTech(${tech.id})"><i
        class="ph-fill ph-x-circle"></i></button>
<input type="hidden" name="techs[${tech.colIndex}][id]" value="${val}">
<input type="hidden" name="techs[${tech.colIndex}][name]" value="${text}">`;
headerRow.appendChild(th);

// 2. Add input cell to EVERY data row
document.querySelectorAll('tr[data-type]').forEach((row, rIndex) => {
const td = document.createElement('td');
const cat = row.getAttribute('data-cat');
const label = row.getAttribute('data-label');
const type = row.getAttribute('data-type');

td.innerHTML = `<input type="number" name="amounts[${type}][${cat}][${label}][${tech.colIndex}]"
    class="amount-input calc-input" data-tech="${tech.id}" data-cat="${cat}" value="" min="0" step="0.01">`;
row.appendChild(td);
});

// 3. Add subtotal cell to EVERY subtotal row
document.querySelectorAll('tr.subtotal-row').forEach(row => {
const td = document.createElement('td');
const cat = row.getAttribute('data-subtotal');
td.id = `subtotal_${cat}_${tech.id}`;
td.textContent = '0.00';
row.appendChild(td);
});

// 4. Update colspans for category headers
document.querySelectorAll('.col-span-dynamic').forEach(td => {
td.colSpan = addedTechs.length + 1;
});

// 5. Add Total Footer
const tf = document.createElement('td');
tf.id = `total_${tech.id}`;
tf.textContent = '0.00';
grandTotalRow.appendChild(tf);

// Bind listeners to new inputs
bindInputs();

// Reset select
selTech.selectedIndex = 0;
});

btnAddRow.addEventListener('click', () => {
const concept = customConceptInput.value.trim().toUpperCase();
if(!concept) return;

// Ensure Other header is visible
document.getElementById('customCatHeader').style.display = 'table-row';
document.getElementById('customSubtotalRow').style.display = 'table-row';

// Create new row
const tr = document.createElement('tr');
tr.setAttribute('data-type', 'custom');
tr.setAttribute('data-cat', 'other');
tr.setAttribute('data-label', concept);

// First cell label
const tdLabel = document.createElement('td');
tdLabel.textContent = concept;
tr.appendChild(tdLabel);

// Add cell for each tech
addedTechs.forEach(tech => {
const td = document.createElement('td');
td.innerHTML = `<input type="number" name="amounts[custom][other][${concept}][${tech.colIndex}]"
    class="amount-input calc-input" data-tech="${tech.id}" data-cat="other" value="" min="0" step="0.01">`;
tr.appendChild(td);
});

// Insert before the Other subtotal
const subRow = document.getElementById('customSubtotalRow');
subRow.parentNode.insertBefore(tr, subRow);

customConceptInput.value = '';
bindInputs();
});

// Event delegation or rebinding for calculation
function bindInputs() {
document.querySelectorAll('.calc-input').forEach(input => {
// remove old listeners context to avoid dupes, easiest is just cloning or wrapping,
// but since it's targeted we just use input event
input.oninput = calculateTotals;
});
}

function calculateTotals() {
let overallTotal = 0;

// Calculate vertically per tech
addedTechs.forEach(tech => {
let techFood = 0;
let techTransport = 0;
let techOther = 0;

// Find all inputs for this tech
document.querySelectorAll(`.calc-input[data-tech="${tech.id}"]`).forEach(input => {
const val = parseFloat(input.value) || 0;
const cat = input.getAttribute('data-cat');

if (cat === 'food') techFood += val;
else if (cat === 'transport') techTransport += val;
else if (cat === 'other') techOther += val;
});

// Update UI Subtotals
const fSub = document.getElementById(`subtotal_food_${tech.id}`);
const tSub = document.getElementById(`subtotal_transport_${tech.id}`);
const oSub = document.getElementById(`subtotal_other_${tech.id}`);

if(fSub) fSub.textContent = techFood.toFixed(2);
if(tSub) tSub.textContent = techTransport.toFixed(2);
if(oSub) oSub.textContent = techOther.toFixed(2);

const techTotal = techFood + techTransport + techOther;
const grandTd = document.getElementById(`total_${tech.id}`);
if(grandTd) grandTd.textContent = techTotal.toFixed(2);

overallTotal += techTotal;
});

// Update main big total
grandTotalDisplay.textContent = overallTotal.toFixed(2);
grandTotalInput.value = overallTotal.toFixed(2);
}

function removeTech(techId) {
// Future enhancement: remove DOM elements related to tech id.
// For now, simpler to reload or just restrict removal.
alert('En esta versión, si te equivocas de técnico, recarga la página.');
}

</script>
<?php require_once '../../includes/footer.php'; ?>