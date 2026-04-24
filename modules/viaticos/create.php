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

// Fetch solo técnicos (role_id = 3) para el dropdown
$stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE role_id = 3 AND status = 'active' ORDER BY username ASC");
$all_techs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Nuevo Viático';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<style>
    .viatico-container {
        background: var(--bg-card);
        border-radius: 16px;
        box-shadow: 0 4px 24px -8px rgba(0, 0, 0, 0.15);
        padding: 0;
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .viatico-grid {
        width: 100%;
        border-collapse: collapse;
        background: transparent;
    }

    .viatico-grid th,
    .viatico-grid td {
        border-bottom: 1px solid rgba(var(--border-color-rgb), 0.4);
        padding: 1.25rem 1rem;
        text-align: right;
        position: relative;
        vertical-align: middle;
        transition: background-color 0.2s;
    }

    .viatico-grid th {
        background: rgba(var(--bg-body-rgb), 0.6);
        font-weight: 700;
        color: var(--text-main);
        text-align: center;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        border-bottom: 2px solid var(--border-color);
    }

    .viatico-grid th:first-child,
    .viatico-grid td:first-child {
        text-align: left;
        font-weight: 600;
        background: rgba(var(--bg-body-rgb), 0.3);
        width: 250px;
        border-right: 1px solid rgba(var(--border-color-rgb), 0.4);
    }
    
    .viatico-grid tbody tr:hover td {
        background: rgba(var(--primary-rgb), 0.02);
    }

    .cat-header td {
        background: rgba(var(--primary-rgb), 0.05) !important;
        color: var(--primary) !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1.5px;
        padding-top: 1.5rem;
        padding-bottom: 1.5rem;
    }

    .subtotal-row td {
        background: rgba(var(--bg-body-rgb), 0.8) !important;
        font-weight: 700;
        color: var(--text-muted);
        border-top: 2px dashed rgba(var(--border-color-rgb), 0.6);
        font-size: 1.05rem;
    }

    .subtotal-row td:not(:first-child) {
        color: var(--text-main);
    }

    .grand-total-row td {
        background: rgba(var(--primary-rgb), 0.08) !important;
        font-weight: 800;
        font-size: 1.3rem;
        color: var(--primary);
        border-top: 3px solid var(--primary);
        padding: 1.5rem 1rem;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .input-wrapper::before {
        content: '$';
        position: absolute;
        left: 12px;
        color: var(--text-muted);
        font-weight: 600;
        pointer-events: none;
        z-index: 2;
    }

    .amount-input {
        width: 100%;
        max-width: 180px;
        background: var(--bg-body);
        border: 1px solid rgba(var(--border-color-rgb), 0.8);
        border-radius: 8px;
        color: var(--text-main);
        text-align: right;
        font-family: inherit;
        font-size: 1.05rem;
        font-weight: 600;
        outline: none;
        padding: 0.6rem 1rem 0.6rem 2rem;
        transition: all 0.25s ease;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }

    .amount-input:hover {
        border-color: rgba(var(--primary-rgb), 0.4);
        background: var(--bg-card);
    }

    .amount-input:focus {
        background: var(--bg-card);
        border-color: var(--primary);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02), 0 0 0 4px rgba(var(--primary-rgb), 0.15);
    }

    .amount-input::-webkit-outer-spin-button,
    .amount-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .remove-col-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        right: 8px;
        background: rgba(239, 68, 68, 0.1);
        border: none;
        color: var(--danger);
        cursor: pointer;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.2s;
    }

    th:hover .remove-col-btn {
        opacity: 1;
    }
    
    .remove-col-btn:hover {
        background: var(--danger);
        color: white;
    }

    /* Top controls */
    .controls-card {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: grid;
        grid-template-columns: 2fr 1fr 1.5fr;
        gap: 1.5rem;
        align-items: end;
    }
    
    .controls-card .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
    }
    
    .tech-add-group {
        display: flex;
        gap: 0.5rem;
    }
    
    .tech-add-group .btn {
        height: 50px;
        width: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .bottom-actions {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        gap: 1rem;
    }

    .grand-total-display-area {
        font-size: 2.25rem;
        font-weight: 800;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .grand-total-display-area span.label {
        color: var(--text-muted);
        font-size: 1rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 1px;
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

        <div class="controls-card">
            <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight: bold;">MANTENIMIENTO / PROYECTO</label>
                    <select name="survey_id" id="survey_id" class="form-control" required style="font-size: 1rem; font-weight: 500; height: 50px;" onchange="updateProjectTitle(this)">
                        <option value="" disabled selected>Selecciona un Proyecto / Levantamiento...</option>
                        <optgroup label="Proyectos Activos">
                            <?php 
                            $stmtP = $pdo->query("SELECT id, title, client_name FROM project_surveys WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC");
                            while($p = $stmtP->fetch()): ?>
                                <option value="<?php echo $p['id']; ?>" data-title="<?php echo htmlspecialchars($p['client_name'] . ' - ' . $p['title']); ?>">
                                    <?php echo htmlspecialchars($p['client_name'] . ' - ' . $p['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </optgroup>
                        <option value="other">-- OTRO (ESPECIFICAR) --</option>
                    </select>
                    <input type="hidden" name="project_title" id="project_title_hidden">
                    <input type="text" id="project_title_custom" class="form-control" placeholder="Especificar nombre de proyecto..." style="display: none; margin-top: 0.5rem;">
                </div>

                <script>
                function updateProjectTitle(select) {
                    const customInput = document.getElementById('project_title_custom');
                    const hiddenInput = document.getElementById('project_title_hidden');
                    
                    if (select.value === 'other') {
                        customInput.style.display = 'block';
                        customInput.required = true;
                        hiddenInput.value = customInput.value;
                        
                        customInput.oninput = function() {
                            hiddenInput.value = this.value;
                        }
                    } else {
                        customInput.style.display = 'none';
                        customInput.required = false;
                        const option = select.options[select.selectedIndex];
                        hiddenInput.value = option.getAttribute('data-title');
                    }
                }
                </script>
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Tech Adder -->
                <div class="form-group" style="margin: 0;">
                    <label class="form-label">Añadir Técnico (Columna)</label>
                    <div class="tech-add-group">
                        <select id="techSelector" class="form-control" style="font-size: 1rem; font-weight: 500; height: 50px;">
                            <option value="" disabled selected>Selecciona un técnico...</option>
                            <?php foreach ($all_techs as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['full_name'] ?: $t['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddTech" class="btn btn-secondary"><i
                                class="ph-bold ph-plus"></i></button>
                    </div>
                </div>
            </div>

        <!-- Data Matrix -->
        <div class="viatico-container">
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

        <!-- Custom Row Adder and Totals -->
        <div class="bottom-actions">
            <div style="display: flex; gap: 0.5rem; width: 100%; max-width: 450px;">
                <input type="text" id="customConceptInput" class="form-control"
                    placeholder="Agregar fila (ej. Hospedaje, Caseta)" style="height: 50px; font-size: 1rem;">
                <button type="button" id="btnAddRow" class="btn btn-outline" style="height: 50px; border-radius: 8px; white-space: nowrap;"><i class="ph-bold ph-plus"></i> Añadir
                    Concepto</button>
            </div>
            
            <div class="grand-total-display-area">
                <span class="label">Gran Total</span>
                <span style="color: var(--text-main);">$</span><span id="grandTotalDisplay">0.00</span>
                <input type="hidden" name="total_amount" id="grandTotalInput" value="0.00">
            </div>

            <div style="display: flex; justify-content: flex-end; width: 100%; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; font-size: 1.1rem; height: 55px; border-radius: 12px; font-weight: 600;">
                    <i class="ph-bold ph-floppy-disk"></i> Guardar Viáticos
                </button>
            </div>
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
                Swal.fire({
                    icon: 'warning',
                    title: 'Técnico duplicado',
                    text: 'Este técnico ya ha sido añadido a la matriz.',
                    confirmButtonColor: '#3b82f6',
                    background: 'var(--bg-card)',
                    color: 'var(--text-main)'
                });
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

                td.innerHTML = `<div class="input-wrapper"><input type="number" name="amounts[${type}][${cat}][${label}][${tech.colIndex}]"
    class="amount-input calc-input" data-tech="${tech.id}" data-cat="${cat}" value="" min="0" step="0.01"></div>`;
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
            if (!concept) return;

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
                td.innerHTML = `<div class="input-wrapper"><input type="number" name="amounts[custom][other][${concept}][${tech.colIndex}]"
    class="amount-input calc-input" data-tech="${tech.id}" data-cat="other" value="" min="0" step="0.01"></div>`;
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

                if (fSub) fSub.textContent = techFood.toFixed(2);
                if (tSub) tSub.textContent = techTransport.toFixed(2);
                if (oSub) oSub.textContent = techOther.toFixed(2);

                const techTotal = techFood + techTransport + techOther;
                const grandTd = document.getElementById(`total_${tech.id}`);
                if (grandTd) grandTd.textContent = techTotal.toFixed(2);

                overallTotal += techTotal;
            });

            // Update main big total
            grandTotalDisplay.textContent = overallTotal.toFixed(2);
            grandTotalInput.value = overallTotal.toFixed(2);
        }

        function removeTech(techId) {
            // Future enhancement: remove DOM elements related to tech id.
            // For now, simpler to reload or just restrict removal.
            Swal.fire({
                icon: 'info',
                title: 'Función en desarrollo',
                text: 'En esta versión, si te equivocas de técnico, por favor recarga la página.',
                confirmButtonColor: '#3b82f6',
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            });
        }

    </script>
    <?php require_once '../../includes/footer.php'; ?>