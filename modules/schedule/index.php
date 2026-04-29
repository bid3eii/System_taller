<?php
// modules/schedule/index.php
@session_start(['gc_probability' => 0]);
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Access Control
if (!can_access_module('schedule', $pdo)) {
    die("Acceso denegado.");
}

$can_manage_schedule = can_access_module('schedule_manage', $pdo);

$page_title = 'Agenda / Visitas';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Fetch Technicians
$stmtTechs = $pdo->query("SELECT id, username FROM users WHERE role_id IN (1, 3) AND status = 'active' ORDER BY username ASC");
$technicians = $stmtTechs->fetchAll();

?>

<!-- FullCalendar 6 Bundle -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

<!-- Leaflet (GPS Maps) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
    /* 1. NUCLEAR DARK MODE RESET FOR FULLCALENDAR */
    :root {
        --fc-page-bg-color: transparent !important;
        --fc-neutral-bg-color: rgba(255, 255, 255, 0.05) !important;
        --fc-neutral-text-color: var(--text-secondary) !important;
        --fc-border-color: var(--border-color) !important;
        --fc-today-bg-color: rgba(99, 102, 241, 0.1) !important;
        --fc-button-bg-color: var(--bg-card) !important;
        --fc-button-border-color: var(--border-color) !important;
        --fc-button-text-color: var(--text-primary) !important;
        --fc-button-active-bg-color: var(--primary-500) !important;
        --fc-button-active-border-color: var(--primary-500) !important;
    }

    /* Core Layout */
    .fc { 
        color: var(--text-primary) !important; 
        font-family: inherit;
    }

    .fc .fc-toolbar-title { 
        font-size: 1.5rem !important; 
        font-weight: 800 !important; 
        letter-spacing: -0.025em;
        color: var(--text-primary) !important;
    }

    /* Buttons */
    .fc .fc-button {
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-size: 0.85rem !important;
        padding: 0.6rem 1.2rem !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: var(--shadow-sm) !important;
        border: 1px solid var(--border-color) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .fc .fc-button-group {
        gap: 0.5rem !important;
        background: transparent !important;
    }

    .fc .fc-button-group > .fc-button {
        margin-left: 0 !important; /* Undo FullCalendar negative margin */
    }

    .fc .fc-button:hover {
        background: var(--primary-500) !important;
        color: white !important;
        border-color: var(--primary-500) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active {
        background: var(--primary-600) !important;
        border-color: var(--primary-600) !important;
        color: white !important;
        box-shadow: 0 0 15px var(--primary-glow) !important;
        z-index: 2;
    }

    /* Force text visibility on all states */
    .fc .fc-button:not(.fc-button-active) {
        color: var(--text-primary) !important;
        background: var(--bg-card) !important;
    }

    /* MultiMonth (Year View) Specific Fixes - THE "0" REDO */
    .fc-multimonth {
        background: transparent !important;
        border: none !important;
    }

    .fc-multimonth-month {
        background: var(--bg-card) !important; 
        border: 1px solid var(--border-color) !important;
        border-radius: 12px !important;
        margin: 8px !important;
        flex: 1 1 280px !important; 
        max-width: 32% !important; 
        overflow: hidden !important;
        box-shadow: var(--shadow-sm) !important;
    }

    body.light-mode .fc-multimonth-month {
        background: white !important;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
    }

    .fc-multimonth-title {
        color: var(--primary-500) !important;
        font-weight: 800 !important;
        padding: 1.25rem !important;
        background: var(--bg-hover) !important;
        border-bottom: 1px solid var(--border-color) !important;
        text-transform: capitalize !important;
        font-size: 1.1rem !important;
    }

    .fc-multimonth-daygrid-table, 
    .fc-multimonth-daygrid,
    .fc-daygrid-day,
    .fc-daygrid-day-frame,
    .fc-scrollgrid {
        background: transparent !important;
        background-color: transparent !important;
        border-color: var(--border-color) !important;
    }

    .fc .fc-daygrid-day-number {
        color: var(--text-primary) !important;
        font-weight: 600 !important;
        padding: 4px !important;
        font-size: 0.85rem !important;
    }

    .fc .fc-col-header-cell-cushion {
        color: var(--text-secondary) !important;
        font-size: 0.7rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        font-weight: 700 !important;
        padding: 8px 0 !important;
    }

    /* Events Styling */
    .fc-event {
        border: none !important;
        border-radius: 6px !important;
        padding: 2px 6px !important;
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2) !important;
        cursor: pointer !important;
    }

    .fc-h-event .fc-event-main { color: white !important; }

    /* Modal Styling */
    .modal-backdrop { 
        display: none; 
        position: fixed; 
        inset: 0; 
        background: rgba(2, 6, 23, 0.85); 
        backdrop-filter: blur(10px);
        z-index: 1000;
        justify-content: center;
        align-items: flex-start;
        overflow-y: auto;
        padding: 4rem 1rem;
    }
</style>

<div class="animate-enter">
    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem;">
        <div>
            <h1 style="margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="ph ph-calendar-blank" style="color: var(--primary-500);"></i>
                Agenda Técnica
            </h1>
            <p class="text-muted" style="margin: 0.5rem 0 0 2.5rem;">Seguimiento integral de visitas, levantamientos y servicios.</p>
        </div>
        
        <div style="display: flex; gap: 1rem; align-items: center;">
            <?php if (can_access_module('schedule_view_all', $pdo)): ?>
                <div class="form-group" style="margin: 0; min-width: 250px;">
                    <label class="text-xs font-bold text-muted uppercase tracking-wider">Visualizar Técnico</label>
                    <select id="techFilter" class="form-control" style="background: var(--bg-card); border-color: var(--border-color);">
                        <option value="all">TODOS LOS TÉCNICOS</option>
                        <?php foreach ($technicians as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <?php if ($can_manage_schedule): ?>
            <button class="btn btn-primary" onclick="openEventModal()" style="height: 44px; margin-top: auto;">
                <i class="ph ph-plus-circle"></i> Nueva Visita
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar Card -->
    <div class="card" style="padding: 2rem; border-radius: 20px;">
        <div id='calendar'></div>
    </div>
</div>

<!-- Refined Event Modal -->
<div id="eventModal" class="modal-backdrop">
    <div class="modal-card animate-enter" style="background: var(--bg-body); width: 550px; max-width: 95%; border-radius: 24px; border: 1px solid var(--border-color); box-shadow: 0 0 50px rgba(0,0,0,0.5); margin: auto;">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02);">
            <h3 id="modalTitle" style="margin: 0; font-size: 1.25rem;">Programar Visita</h3>
            <button onclick="closeEventModal()" class="btn-icon" style="background: none; border: none; font-size: 1.5rem;"><i class="ph ph-x"></i></button>
        </div>
        
        <form id="eventForm" style="padding: 2rem;">
            <input type="hidden" id="event_id" name="id">
            
            <div class="form-group">
                <label class="form-label">Motivo / Título</label>
                <div class="input-group">
                    <i class="ph ph-tag input-icon"></i>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Ej: Levantamiento Planta Yazaki" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Fecha y Hora Inicio</label>
                    <input type="datetime-local" id="start" name="start" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha y Hora Fin</label>
                    <input type="datetime-local" id="end" name="end" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Técnico Responsable</label>
                <select id="tech_id" name="tech_id" class="form-control" required>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Ubicación / Empresa</span>
                    <button type="button" onclick="toggleMap()" class="btn btn-sm btn-text" style="padding: 0; display: flex; align-items: center; gap: 0.25rem; color: var(--primary-500); background: transparent; border: none;">
                        <i class="ph-fill ph-map-pin"></i> Usar GPS Remoto
                    </button>
                </label>
                <div class="input-group" style="display: flex; gap: 0.5rem; border: none; background: transparent;">
                    <div style="position: relative; flex: 1; display: flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; height: 50px;">
                        <i class="ph ph-map-pin" style="position: absolute; left: 1rem; color: var(--primary-500); font-size: 1.25rem;"></i>
                        <input type="text" id="location" name="location" class="form-control" placeholder="Dirección o cliente" 
                               onkeydown="if(event.key === 'Enter'){ event.preventDefault(); searchAddress(); }"
                               style="background: transparent; border: none; padding-left: 3rem; width: 100%;">
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                    <button type="button" onclick="searchAddress()" id="searchAddrBtn" title="Buscar en el mapa"
                            style="background: var(--primary-500); border: none; cursor: pointer; color: white; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                        <i class="ph-bold ph-magnifying-glass" style="font-size: 1.2rem;"></i>
                    </button>
                </div>
            </div>

            <div id="mapContainerWrapper" style="display: none; margin-top: -1rem; margin-bottom: 1.5rem;">
                <div id="leafletMap" style="height: 250px; border-radius: 12px; border: 1px solid var(--border-color); z-index: 1;"></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; display: flex; align-items: center; gap: 0.25rem;">
                    <i class="ph-fill ph-info"></i> Haz clic en el mapa para marcar el lugar exacto.
                </div>
            </div>


            <div class="form-group">
                <label class="form-label">Notas Adicionales</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <div id="deleteBtnContainer"></div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" onclick="closeEventModal()" class="btn btn-secondary">
                        <?php echo $can_manage_schedule ? 'Cancelar' : 'Cerrar'; ?>
                    </button>
                    <?php if ($can_manage_schedule): ?>
                        <button type="submit" class="btn btn-primary">Agendar Ahora</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const techFilter = document.getElementById('techFilter');
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        multiMonthMaxColumns: 4, 
        multiMonthMinWidth: 200,
        locale: 'es',
        firstDay: 1, // Lunes
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            multiMonthYear: 'Vista Anual',
            listYear: 'Lista de Visitas'
        },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'multiMonthYear,dayGridMonth,timeGridWeek,listYear'
        },
        events: function(info, successCallback, failureCallback) {
            const tId = techFilter ? techFilter.value : 'all';
            fetch(`get_events.php?start=${info.startStr}&end=${info.endStr}&tech_id=${tId}`)
                .then(r => r.json())
                .then(data => successCallback(data))
                .catch(e => failureCallback(e));
        },
        editable: <?php echo $can_manage_schedule ? 'true' : 'false'; ?>,
        selectable: <?php echo $can_manage_schedule ? 'true' : 'false'; ?>,
        eventClick: (i) => openEventModal(i.event),
        select: (i) => openEventModal(null, i.startStr, i.endStr),
        eventDrop: (i) => updateEventQuick(i.event),
        eventResize: (i) => updateEventQuick(i.event),
    });

    calendar.render();

    if (techFilter) techFilter.onchange = () => calendar.refetchEvents();

    const modal = document.getElementById('eventModal');
    const form = document.getElementById('eventForm');

    window.openEventModal = function(event = null, startStr = null, endStr = null) {
        form.reset();
        document.getElementById('event_id').value = '';
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        document.getElementById('modalTitle').textContent = event ? 'Detalles de la Visita' : 'Programar Nueva Visita';
        document.getElementById('mapContainerWrapper').style.display = 'none';
        if (mapMarker && leafletMap) {
            leafletMap.removeLayer(mapMarker);
            mapMarker = null;
        }
        
        if (event) {
            document.getElementById('event_id').value = event.id;
            document.getElementById('title').value = event.title;
            document.getElementById('description').value = event.extendedProps.description || '';
            document.getElementById('location').value = event.extendedProps.location || '';
            document.getElementById('latitude').value = event.extendedProps.latitude || '';
            document.getElementById('longitude').value = event.extendedProps.longitude || '';
            document.getElementById('tech_id').value = event.extendedProps.tech_id;

            
            document.getElementById('start').value = event.start.toISOString().slice(0, 16);
            if (event.end) document.getElementById('end').value = event.end.toISOString().slice(0, 16);
            
            if (canManage) {
                document.getElementById('deleteBtnContainer').innerHTML = `
                    <button type="button" onclick="deleteEvent(${event.id})" class="btn" style="color: var(--danger); background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger);">
                        <i class="ph ph-trash"></i> Eliminar
                    </button>
                `;
            } else {
                document.getElementById('deleteBtnContainer').innerHTML = '';
            }
        } else {
            document.getElementById('deleteBtnContainer').innerHTML = '';
            if (startStr) {
                let finalStart = startStr.includes('T') ? startStr.slice(0, 16) : startStr + 'T09:00';
                document.getElementById('start').value = finalStart;
                
                if (endStr && endStr.includes('T')) {
                    document.getElementById('end').value = endStr.slice(0, 16);
                } else if (endStr) {
                    // FullCalendar sends endStr as the EXCLUSIVE next day for all-day selections.
                    // We check if it's a single day click by seeing if endStr - 1 day == startStr
                    let ed = new Date(endStr);
                    ed.setDate(ed.getDate() - 1);
                    
                    let isSingleDay = false;
                    if (!startStr.includes('T')) {
                        let sdCheck = new Date(startStr);
                        if (ed.getTime() === sdCheck.getTime()) {
                            isSingleDay = true;
                        }
                    }

                    if (isSingleDay) {
                        // Single day clicked: set end time to 1 hour after start time
                        let sd = new Date(finalStart);
                        sd.setHours(sd.getHours() + 1);
                        let y = sd.getFullYear();
                        let m = String(sd.getMonth() + 1).padStart(2, '0');
                        let d = String(sd.getDate()).padStart(2, '0');
                        let h = String(sd.getHours()).padStart(2, '0');
                        let min = String(sd.getMinutes()).padStart(2, '0');
                        document.getElementById('end').value = `${y}-${m}-${d}T${h}:${min}`;
                    } else {
                        // Multi-day selection: use the real inclusive end day
                        let y = ed.getFullYear();
                        let m = String(ed.getMonth() + 1).padStart(2, '0');
                        let d = String(ed.getDate()).padStart(2, '0');
                        document.getElementById('end').value = `${y}-${m}-${d}T10:00`;
                    }
                }
            }
        }
        modal.style.display = 'flex';
    };

    window.closeEventModal = () => modal.style.display = 'none';

    // Check manage permission in JS
    const canManage = <?php echo $can_manage_schedule ? 'true' : 'false'; ?>;
    if (!canManage) {
        // Disable all inputs in the form
        form.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
        // Hide the search button in map
        const searchBtn = document.getElementById('searchAddrBtn');
        if (searchBtn) searchBtn.style.display = 'none';
        // Hide "Usar GPS Remoto" text button
        const toggleMapBtn = form.querySelector('button[onclick="toggleMap()"]');
        if (toggleMapBtn) toggleMapBtn.style.display = 'none';
    }

    form.onsubmit = function(e) {
        e.preventDefault();
        if (!canManage) return;

        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());

        fetch('save_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeEventModal();
                calendar.refetchEvents();
                Swal.fire({ icon: 'success', title: '¡Hecho!', text: 'Agenda actualizada.', timer: 1500 });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    };

    function updateEventQuick(event) {
        const data = {
            id: event.id,
            start: event.start.toISOString().slice(0, 19).replace('T', ' '),
            end: event.end ? event.end.toISOString().slice(0, 19).replace('T', ' ') : '',
            title: event.title,
            tech_id: event.extendedProps.tech_id,
            location: event.extendedProps.location,
            description: event.extendedProps.description

        };
        fetch('save_event.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(r => r.json())
        .then(res => { if(!res.success) calendar.refetchEvents(); });
    }

    window.deleteEvent = function(id) {
        Swal.fire({
            title: '¿Confirmar eliminación?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cerrar'
        }).then(r => {
            if (r.isConfirmed) {
                fetch('delete_event.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({id: id}) })
                .then(res => res.json())
                .then(res => { if(res.success) { closeEventModal(); calendar.refetchEvents(); } });
            }
        });
    };

    // LEAFLET MAP LOGIC
    let leafletMap = null;
    let mapMarker = null;

    window.toggleMap = function() {
        const wrapper = document.getElementById('mapContainerWrapper');
        if (wrapper.style.display === 'none') {
            wrapper.style.display = 'block';
            if (!leafletMap) {
                // Initialize map (Default Managua, or generic if unknown location)
                leafletMap = L.map('leafletMap').setView([12.136389, -86.251389], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(leafletMap);

                leafletMap.on('click', function(e) {
                    setMarker(e.latlng.lat, e.latlng.lng);
                });
            }
            setTimeout(() => {
                leafletMap.invalidateSize();
                
                const lat = document.getElementById('latitude').value;
                const lng = document.getElementById('longitude').value;
                if (lat && lng) {
                    setMarker(parseFloat(lat), parseFloat(lng), true);
                } else if ("geolocation" in navigator) {
                    // Ask for user location to center map quickly
                    navigator.geolocation.getCurrentPosition((pos) => {
                         if(!document.getElementById('latitude').value) {
                             leafletMap.setView([pos.coords.latitude, pos.coords.longitude], 14);
                         }
                    });
                }
            }, 250);
        } else {
            wrapper.style.display = 'none';
        }
    };

    function setMarker(lat, lng, center = false, updateInput = true) {
        if (!mapMarker) {
            mapMarker = L.marker([lat, lng]).addTo(leafletMap);
        } else {
            mapMarker.setLatLng([lat, lng]);
        }
        
        if (center) {
            leafletMap.setView([lat, lng], 16);
        }

        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        if (!updateInput) return;

        // Fetch Address (Reverse Geocoding)
        const locationInput = document.getElementById('location');
        const originalPlaceholder = locationInput.placeholder;
        locationInput.placeholder = "Obteniendo dirección...";

        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.display_name) {
                    locationInput.value = data.display_name;
                }
                locationInput.placeholder = originalPlaceholder;
            })
            .catch(() => {
                locationInput.placeholder = originalPlaceholder;
            });
    }

    window.searchAddress = function() {
        const query = document.getElementById('location').value.trim();
        if (query.length < 3) return;

        const btn = document.getElementById('searchAddrBtn');
        const icon = btn.querySelector('i');
        const oldIconClass = icon.className;
        
        icon.className = 'ph ph-spinner ph-spin';
        btn.disabled = true;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`)
            .then(r => r.json())
            .then(data => {
                if (data && data.length > 0) {
                    const result = data[0];
                    setMarker(parseFloat(result.lat), parseFloat(result.lon), true, false);
                    
                    // Show map if hidden
                    const wrapper = document.getElementById('mapContainerWrapper');
                    if (wrapper.style.display === 'none') {
                        toggleMap();
                    }
                } else {
                    Swal.fire({ icon: 'info', title: 'Sin resultados', text: 'No se encontró la ubicación especificada.', timer: 2000 });
                }
                icon.className = oldIconClass;
                btn.disabled = false;
            })
            .catch(() => {
                icon.className = oldIconClass;
                btn.disabled = false;
            });
    };
});
</script>

<?php require_once '../../includes/footer.php'; ?>
