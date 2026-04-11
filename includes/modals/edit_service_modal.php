<?php
// includes/modals/edit_service_modal.php
// This modal is shared between Services and Warranties modules.
// Included at the bottom of index.php files.
?>

<!-- UNIFIED EDIT MODAL -->
<div id="editEntryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-content animate-pop" style="max-width: 600px; width: 90%; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
        <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), transparent); padding: 2rem; border-top-left-radius: 20px; border-top-right-radius: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2 style="font-size: 1.5rem; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="ph ph-note-pencil" style="color: #f59e0b;"></i>
                        Editar Entrada
                    </h2>
                    <p class="text-muted" style="margin: 0.25rem 0 0 0;">Actualice la información básica del registro.</p>
                </div>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <form action="../shared/update_service_entry.php" method="POST">
                <input type="hidden" name="service_order_id" id="edit_service_order_id">
                <input type="hidden" name="equipment_id" id="edit_equipment_id">
                <input type="hidden" name="module_context" id="edit_module_context">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">Nombre del Equipo</label>
                        <input type="text" name="equipment_name" id="edit_equipment_name" class="form-control" required style="background: rgba(0,0,0,0.2);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Número de Serie</label>
                        <input type="text" name="serial_number" id="edit_serial_number" class="form-control" style="background: rgba(0,0,0,0.2);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label">No. Factura / Referencia</label>
                        <input type="text" name="invoice_number" id="edit_invoice_number" class="form-control" style="background: rgba(0,0,0,0.2);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nombre del Dueño</label>
                        <input type="text" name="owner_name" id="edit_owner_name" class="form-control" style="background: rgba(0,0,0,0.2);">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Accesorios Recibidos</label>
                    <textarea name="accessories" id="edit_accessories" class="form-control" rows="2" style="background: rgba(0,0,0,0.2);"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label class="form-label">Problema Reportado</label>
                    <textarea name="problem_reported" id="edit_problem" class="form-control" rows="3" required style="background: rgba(0,0,0,0.2);"></textarea>
                </div>

                <div style="background: rgba(var(--primary-rgb), 0.05); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(var(--primary-rgb), 0.1); margin-bottom: 2rem;">
                    <label class="form-label" style="color: var(--primary); font-weight: 600;">Motivo de la Edición</label>
                    <input type="text" name="edit_reason" class="form-control" required placeholder="Ej. El cliente trajo accesorios olvidados..." style="border-color: rgba(var(--primary-rgb), 0.2); background: white; color: black;">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0.5rem 0 0 0;">Este motivo quedará registrado en el historial y bitácora de auditoría.</p>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="min-width: 120px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="min-width: 150px; background: #f59e0b; border-color: #f59e0b;">
                        <i class="ph ph-check"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(btn) {
    const modal = document.getElementById('editEntryModal');
    
    // Fill data
    document.getElementById('edit_service_order_id').value = btn.dataset.id;
    document.getElementById('edit_equipment_id').value = btn.dataset.equipmentId;
    document.getElementById('edit_module_context').value = btn.dataset.context || 'services';
    
    // Combine Brand and Model for unified equipment name if needed, or just use brand as we've been doing
    let eqName = btn.dataset.brand;
    if (btn.dataset.model && btn.dataset.model.trim() !== '') {
        eqName += " " + btn.dataset.model;
    }
    
    document.getElementById('edit_equipment_name').value = eqName;
    document.getElementById('edit_serial_number').value = btn.dataset.serial;
    document.getElementById('edit_invoice_number').value = btn.dataset.invoice;
    document.getElementById('edit_owner_name').value = btn.dataset.ownerName;
    document.getElementById('edit_accessories').value = btn.dataset.accessories;
    document.getElementById('edit_problem').value = btn.dataset.problem;
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editEntryModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close on outside click
document.getElementById('editEntryModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
