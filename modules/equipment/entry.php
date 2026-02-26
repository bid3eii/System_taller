<?php
// modules/equipment/entry.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';


// --- AJAX HANDLER FOR NEW CLIENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'create_client') {
    header('Content-Type: application/json');
    $name = clean($_POST['name']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $tax_id = clean($_POST['tax_id'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, tax_id, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $tax_id, get_local_datetime()]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $name, 'tax_id' => $tax_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear cliente: ' . $e->getMessage()]);
    }
    exit;
}
// -----------------------------------

if (!can_access_module('equipment', $pdo) && !can_access_module('new_order', $pdo) && !can_access_module('warranties', $pdo) && !can_access_module('new_warranty', $pdo) && !can_access_module('equipment_entry', $pdo)) {
    die("Acceso denegado.");
}

// --- AJAX HANDLER FOR SEARCH (Optional, but let's stick to inline JS for now since data is loaded) ---
// If we wanted server-side search, we'd add it here.
// But we will use the loaded $clients for the JS autocomplete.
// -----------------------------------

// Fetch Clients for Autocomplete
$stmt = $pdo->query("
    SELECT DISTINCT c.* 
    FROM clients c 
    LEFT JOIN service_orders so ON c.id = so.client_id 
    WHERE (so.id IS NULL OR so.service_type != 'warranty' OR so.problem_reported != 'Garantía Registrada')
    ORDER BY c.name ASC
");
$clients = $stmt->fetchAll();


$error = '';
$success = '';
if(isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $success = "Cambios guardados correctamente.";
}
$edit_order = null;

// Determine Mode
$is_warranty_mode = (isset($_GET['type']) && $_GET['type'] === 'warranty');

// Handle Edit Mode
if (isset($_GET['edit'])) {
    $edit_id = clean($_GET['edit']);
    $stmtEdit = $pdo->prepare("
        SELECT so.*, e.brand, e.model, e.submodel, e.serial_number, e.type, c.name as client_name,
               w.product_code, w.sales_invoice_number, w.master_entry_invoice, w.master_entry_date, w.supplier_name
        FROM service_orders so
        JOIN equipments e ON so.equipment_id = e.id
        JOIN clients c ON so.client_id = c.id
        LEFT JOIN warranties w ON w.service_order_id = so.id
        WHERE so.id = ?
    ");
    $stmtEdit->execute([$edit_id]);
    $edit_order = $stmtEdit->fetch();
    
    if ($edit_order && $edit_order['service_type'] === 'warranty') {
        $is_warranty_mode = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $client_id = $_POST['client_id'] ?? '';
    $invoice_number = clean($_POST['invoice_number'] ?? ''); // Standard Invoice implies 'Factura de Venta' usually, but in warranty mode we have specific fields
    
    // In Warranty mode, we map:
    // Code -> product_code (warranties)
    // Descripcion -> model (equipments)
    // Fecha -> entry_date (service_orders)
    // Cliente -> client_id (service_orders)
    // Fat. Venta -> sales_invoice_number (warranties)
    // Serie -> serial_number (equipments)
    // Fact. Ingreso Master -> master_entry_invoice (warranties)
    // Fecha Ingreso Master -> master_entry_date (warranties)
    // Proveedor -> supplier_name (warranties)
    
    $is_multi = isset($_POST['serial_number']) && is_array($_POST['serial_number']);
    
    if (!$is_multi) {
        $service_type = $is_warranty_mode ? 'warranty' : clean($_POST['service_type'] ?? 'service');
        $type = clean($_POST['type'] ?? '');
        $brand = clean($_POST['brand'] ?? '');
        $model = clean($_POST['model'] ?? '');
        $submodel = clean($_POST['submodel'] ?? '');
        $serial_number = clean($_POST['serial_number'] ?? '');
    }
    
    // Logic specific fields
    $product_code = clean($_POST['product_code'] ?? '');
    $sales_invoice = clean($_POST['sales_invoice_number'] ?? '');
    $master_invoice = clean($_POST['master_entry_invoice'] ?? '');
    $master_date = clean($_POST['master_entry_date'] ?? '');
    $supplier = clean($_POST['supplier_name'] ?? '');
    
    // Warranty Specifics
    $warranty_end_date = clean($_POST['warranty_end_date'] ?? null);
    $warranty_duration = clean($_POST['warranty_duration'] ?? 0);
    $warranty_period = clean($_POST['warranty_period'] ?? 'months');
    
    // Fallback: If JS didn't populate end_date but we have duration, calculate it here
    if (empty($warranty_end_date) && $warranty_duration > 0) {
        $calc_date = new DateTime();
        if ($warranty_period === 'days') {
            $calc_date->modify("+{$warranty_duration} days");
        } elseif ($warranty_period === 'weeks') {
            $weeks = $warranty_duration * 7;
            $calc_date->modify("+{$weeks} days");
        } elseif ($warranty_period === 'months') {
             $calc_date->modify("+{$warranty_duration} months");
        } elseif ($warranty_period === 'years') {
             $calc_date->modify("+{$warranty_duration} years");
        }
        $warranty_end_date = $calc_date->format('Y-m-d');
    }

    $terms = "$warranty_duration $warranty_period"; // Store as string e.g. "12 months" (Note: terms column removed from DB insert, but useful for logic if needed)
    
    // Standard fields (might be optional in warranty mode)
    if (!$is_multi) {
        $problem = clean($_POST['problem_reported'] ?? 'Garantía Registrada'); 
        $accessories = clean($_POST['accessories'] ?? '-');
        $owner_name = clean($_POST['owner_name'] ?? '');
    }
    $notes = clean($_POST['entry_notes'] ?? '');
    
    $order_id = isset($_POST['order_id']) ? clean($_POST['order_id']) : null;

    // --- CLIENT HANDLING LOGIC ---
    // If client_id is set (selected from autocomplete and not cleared), use it.
    // If client_id is empty, but Name is provided, Create New Client.
    
    // Fields from form
    $c_name = clean($_POST['client_name_input'] ?? '');
    $c_phone = clean($_POST['client_phone'] ?? '');
    $c_email = clean($_POST['client_email'] ?? '');
    $c_tax = clean($_POST['client_tax_id'] ?? '');
    $c_address = clean($_POST['client_address'] ?? '');

    if (empty($client_id) && !empty($c_name)) {
        // Find Existing by Name or Tax ID to prevent duplicates
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE name = ? OR (tax_id = ? AND tax_id != '') LIMIT 1");
        $stmtCheck->execute([$c_name, $c_tax]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $client_id = $existing['id'];
        } else {
            // Create New Client
            $stmtNewC = $pdo->prepare("INSERT INTO clients (name, phone, email, tax_id, address, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtNewC->execute([$c_name, $c_phone, $c_email, $c_tax, $c_address, get_local_datetime()]);
            $client_id = $pdo->lastInsertId();
        }
    } elseif (!empty($client_id)) {
        // Update existing client info?
        // User might have edited the phone number. Let's update it to keep data fresh.
        $stmtC = $pdo->prepare("UPDATE clients SET phone=?, email=?, tax_id=?, address=? WHERE id=?");
        $stmtC->execute([$c_phone, $c_email, $c_tax, $c_address, $client_id]);
    }

    if (empty($client_id)) {
        $error = "Por favor seleccione o registre un cliente.";
    } else {
        try {
            $pdo->beginTransaction();

            $serial_numbers = $_POST['serial_number'] ?? [];
            if (!is_array($serial_numbers)) {
                // Handle single entry or Edit mode (fallback)
                $serial_numbers = [$serial_numbers];
                $brands = [$_POST['brand'] ?? ''];
                $models = [$_POST['model'] ?? ''];
                $submodels = [$_POST['submodel'] ?? ''];
                $types = [$_POST['type'] ?? ''];
                $owners = [$_POST['owner_name'] ?? ''];
                $accessories_list = [$_POST['accessories'] ?? ''];
                $service_types = [$_POST['service_type'] ?? 'service'];
                $problems = [$_POST['problem_reported'] ?? ''];
            } else {
                $brands = $_POST['brand'] ?? [];
                $models = $_POST['model'] ?? [];
                $submodels = $_POST['submodel'] ?? [];
                $types = $_POST['type'] ?? [];
                $owners = $_POST['owner_name'] ?? [];
                $accessories_list = $_POST['accessories'] ?? [];
                $service_types = $_POST['service_type'] ?? [];
                $problems = $_POST['problem_reported'] ?? [];
            }

            $order_ids = [];
            $entry_doc_number = null;

            // SNAPSHOT SIGNATURE
            $stmtSig = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
            $stmtSig->execute([$_SESSION['user_id']]);
            $currentUserSig = $stmtSig->fetchColumn();

            foreach ($serial_numbers as $i => $serial_number) {
                if (empty($serial_number) && empty($brands[$i])) continue;

                $brand_item = clean($brands[$i] ?? '');
                $model_item = clean($models[$i] ?? '');
                $submodel_item = clean($submodels[$i] ?? '');
                $type_item = clean($types[$i] ?? '');
                $owner_item = clean($owners[$i] ?? '');
                $accessories_item = clean($accessories_list[$i] ?? '');
                $problem_item = clean($problems[$i] ?? '');
                $service_type_item = clean($service_types[$i] ?? 'service');
                
                $notes = clean($_POST['entry_notes'] ?? '');
                $invoice_num = clean($_POST['invoice_number'] ?? '');

                // 1. Equipment Logic
                $stmtEqCheck = $pdo->prepare("SELECT id, client_id FROM equipments WHERE serial_number = ? LIMIT 1");
                $stmtEqCheck->execute([$serial_number]);
                $existing_eq = $stmtEqCheck->fetch();

                if ($existing_eq) {
                    $equipment_id = $existing_eq['id'];
                    // Update details
                    $stmtEqUpd = $pdo->prepare("UPDATE equipments SET brand = ?, model = ?, submodel = ?, type = ? " . (empty($existing_eq['client_id']) ? ", client_id = ?" : "") . " WHERE id = ?");
                    if (empty($existing_eq['client_id'])) {
                        $stmtEqUpd->execute([$brand_item, $model_item, $submodel_item, $type_item, $client_id, $equipment_id]);
                    } else {
                        $stmtEqUpd->execute([$brand_item, $model_item, $submodel_item, $type_item, $equipment_id]);
                    }
                } else {
                    $stmtEq = $pdo->prepare("INSERT INTO equipments (client_id, brand, model, submodel, serial_number, type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtEq->execute([$client_id, $brand_item, $model_item, $submodel_item, $serial_number, $type_item, get_local_datetime()]);
                    $equipment_id = $pdo->lastInsertId();
                }

                // 2. Order Logic
                $stmtOrder = $pdo->prepare("INSERT INTO service_orders (equipment_id, client_id, owner_name, invoice_number, service_type, status, problem_reported, accessories_received, entry_notes, entry_date, entry_signature_path, created_at) VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?, ?, ?, ?)");
                $now = get_local_datetime();
                $stmtOrder->execute([$equipment_id, $client_id, $owner_item, $invoice_num, $service_type_item, $problem_item, $accessories_item, $notes, $now, $currentUserSig, $now]);
                $order_id_new = $pdo->lastInsertId();
                $order_ids[] = $order_id_new;

                // 3. History
                $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id) VALUES (?, 'received', 'Equipo ingresado al taller', ?)");
                $stmtHist->execute([$order_id_new, $_SESSION['user_id']]);

                // 4. Sequence/Doc Number
                if ($entry_doc_number === null) {
                    $entry_doc_number = get_next_sequence($pdo, 'entry_doc');
                }
                $pdo->prepare("UPDATE service_orders SET entry_doc_number = ? WHERE id = ?")->execute([$entry_doc_number, $order_id_new]);
            }

            $pdo->commit();
            
            if (!empty($order_ids)) {
                header("Location: print_entry.php?ids=" . implode(',', $order_ids));
                exit;
            } else {
                $error = "No se ingresaron equipos validos.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

$page_title = $is_warranty_mode ? 'Registro de Garantía' : 'Recepción de Equipos';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 1100px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1><?php echo $page_title; ?></h1>
            <p class="text-muted">
                <?php echo $is_warranty_mode 
                    ? 'Complete los datos requeridos para el control de garantías.' 
                    : 'Registra el ingreso de nuevos dispositivos para servicio técnico.'; ?>
            </p>
        </div>

    </div>

    <?php if($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: #fca5a5; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: #6ee7b7; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <style>
        .modern-form-container form {
            display: flex;
            flex-direction: column;
            gap: 1rem; /* Reduced from 2rem */
        }
        .form-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            margin-bottom: 1rem; /* Reduced from 2rem */
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
        }
        .form-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--primary-500);
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-section-header i {
            font-size: 1.25rem;
        }
        .modern-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .col-span-2 {
            grid-column: span 2;
        }
        /* Make inputs look a bit punchier */
        .form-control {
            background-color: var(--bg-body);
            border: 1px solid var(--border-color);
            padding: 0.6rem 1rem;
        }
        .form-control:focus {
            background-color: var(--bg-card);
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .clickable-row:hover {
            background-color: rgba(255, 255, 255, 0.05); /* Light hover effect */
        }
        
        /* Fix Select Arrow Positioning */
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2394a3b8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
            padding-right: 2.5rem;
        }

        /* Unified Search Results Style */
        .search-results {
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            z-index: 1000;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 5px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            max-height: 250px;
            overflow-y: auto;
            display: none;
        }
        .search-results.show {
            display: block;
        }
        .search-option {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        .search-option:last-child {
            border-bottom: none;
        }
        .search-option:hover {
            background: rgba(var(--primary-rgb), 0.1);
            color: var(--primary-light);
        }

        /* Wizard Styles */
        .steps-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem; /* Reduced from 3rem */
            position: relative;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .steps-indicator::before {
            content: '';
            position: absolute;
            top: 1.25rem;
            left: 16%;
            right: 16%;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }
        .progress-line {
            position: absolute;
            top: 1.25rem;
            left: 16%;
            width: 0%;
            height: 2px;
            background: var(--primary-500);
            z-index: 1;
            transition: width 0.3s ease;
        }
        .step-item {
            position: relative;
            z-index: 2;
            padding: 0 1rem;
            text-align: center;
            flex: 1;
        }
        .step-number {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--text-muted);
        }
        .step-item.active .step-number {
            background: var(--primary-500);
            border-color: var(--primary-500);
            color: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }
        .step-item.completed .step-number {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .step-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .step-item.active .step-label {
            color: var(--primary-500);
        }
        .step-container {
            display: none;
            animation: fadeIn 0.4s ease-out;
        }
        .step-container.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .wizard-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .is-invalid {
            border-color: var(--danger) !important;
            background-color: rgba(239, 68, 68, 0.05) !important;
        }
    </style>

    <div class="modern-form-container">
        <!-- Wizard Progress Indicator -->
        <?php if (!$is_warranty_mode): ?>
        <div class="steps-indicator">
            <div class="progress-line" id="wizard-progress"></div>
            <div class="step-item active" id="step-1-indicator">
                <div class="step-number">1</div>
                <div class="step-label">Cliente</div>
            </div>
            <div class="step-item" id="step-2-indicator">
                <div class="step-number">2</div>
                <div class="step-label">Equipo</div>
            </div>
            <div class="step-item" id="step-3-indicator">
                <div class="step-number">3</div>
                <div class="step-label">REVISIÓN</div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" id="entryForm" action="entry.php?<?php echo http_build_query($_GET); ?>">
            <?php if($edit_order): ?>
                <input type="hidden" name="order_id" value="<?php echo $edit_order['id']; ?>">
            <?php endif; ?>
            
            <input type="hidden" name="type" value="<?php echo $edit_order ? $edit_order['type'] : ''; // Default or hidden handler ?>"> 

            <?php if ($is_warranty_mode): ?>
                <!-- WARRANTY GRID LAYOUT (Restored) -->
                <div class="form-section animate-enter" style="margin-top: 1rem;">
                    <div class="form-section-header">
                        <i class="ph ph-shield-check"></i> Detalles de Garantía
                    </div>
                    
                    <div class="modern-grid" style="grid-template-columns: repeat(4, 1fr);">
                        
                        <!-- Row 1: Client & Basic Info -->
                        <div class="form-group" style="grid-column: span 2; position: relative;">
                             <label class="form-label">Cliente *</label>
                             <input type="hidden" name="client_id" id="client_id_hidden_wry" value="<?php echo $edit_order ? $edit_order['client_id'] : ''; ?>">
                             <input type="text" name="client_name_input" id="client_name_input_wry" class="form-control" placeholder="Nombre (Búsqueda inteligente)" required value="<?php echo $edit_order ? htmlspecialchars($edit_order['client_name']) : ''; ?>" autocomplete="off">
                             <div class="search-results" id="client_autocomplete_results_wry" style="width: 100%;"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha</label>
                            <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly>
                        </div>

                        <div class="form-group">
                             <label class="form-label">Factura Venta</label>
                             <input type="text" name="sales_invoice_number" class="form-control" placeholder="No. Factura" value="<?php echo $edit_order['sales_invoice_number'] ?? ''; ?>">
                        </div>

                        <!-- Row 2: Equipment Identification -->
                        <div class="form-group">
                            <label class="form-label">Serie *</label>
                            <input type="text" name="serial_number" id="serial_number_warranty" class="form-control" placeholder="S/N" required value="<?php echo $edit_order['serial_number'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Marca</label>
                            <input type="text" name="brand" class="form-control" placeholder="Ej. HP" required value="<?php echo $edit_order['brand'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="model" class="form-control" placeholder="Ej. Pavilion 15" required value="<?php echo $edit_order['model'] ?? ''; ?>">
                        </div>
                        
                         <div class="form-group">
                            <label class="form-label">Submodelo</label>
                            <input type="text" name="submodel" class="form-control" placeholder="Ej. cx0001la" value="<?php echo $edit_order['submodel'] ?? ''; ?>">
                        </div>

                        <!-- Row 3: Master Data & Supplier -->
                        <div class="form-group">
                            <label class="form-label">Código</label>
                            <input type="text" name="product_code" class="form-control" placeholder="Cód. Producto" required value="<?php echo $edit_order['product_code'] ?? ''; ?>">
                        </div>

                         <div class="form-group">
                            <label class="form-label">Fact. Ingreso Master</label>
                            <input type="text" name="master_entry_invoice" class="form-control" required value="<?php echo $edit_order['master_entry_invoice'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Fecha Master</label>
                            <input type="date" name="master_entry_date" class="form-control" required value="<?php echo $edit_order['master_entry_date'] ?? ''; ?>" style="color-scheme: dark;">
                        </div>
                        
                         <div class="form-group">
                            <label class="form-label">Proveedor</label>
                            <input type="text" name="supplier_name" class="form-control" placeholder="Proveedor Original" required value="<?php echo $edit_order['supplier_name'] ?? ''; ?>">
                        </div>

                        <!-- Row 4: Warranty Duration & Expiration -->
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Tiempo de Garantía</label>
                            <div class="input-group" style="display: flex; align-items: stretch; gap: 0;">
                                <input type="number" name="warranty_duration" id="warranty_duration" class="form-control" placeholder="Cant." min="0" value="0" style="border-top-right-radius: 0; border-bottom-right-radius: 0; flex: 1; border-right: none; margin-right: -1px; z-index: 2; padding-left: 0.5rem;">
                                <select name="warranty_period" id="warranty_period" class="form-control" style="width: auto; flex: 0 0 130px; border-top-left-radius: 0; border-bottom-left-radius: 0; background-color: var(--bg-card); border-left: 1px solid var(--border-color); z-index: 1;">
                                    <option value="days">Días</option>
                                    <option value="weeks">Semanas</option>
                                    <option value="months" selected>Meses</option>
                                    <option value="years">Años</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Fecha Vencimiento</label>
                            <div class="input-group">
                                <input type="date" name="warranty_end_date" id="warranty_end_date" class="form-control" readonly style="background-color: var(--bg-body); cursor: not-allowed; font-weight: 600; color: var(--primary-500); color-scheme: dark;">
                                <i class="ph ph-calendar-check input-icon" style="color: var(--primary-500);"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 1.5rem; margin-bottom: 2rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: 600; border-radius: 8px;">
                        <i class="ph ph-floppy-disk"></i> <?php echo $edit_order ? 'Guardar Cambios' : 'Guardar Registro'; ?>
                    </button>
                </div>
            <?php else: ?>
                <!-- STEP 1: CLIENT DATA -->
                <div class="step-container active" id="step-1">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-user"></i> Datos del Cliente
                        </div>
                        
                        <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <input type="hidden" name="client_id" id="client_id_hidden_std" value="<?php echo $edit_order ? $edit_order['client_id'] : ''; ?>">

                            <div class="form-group col-span-2" style="position: relative;">
                                <label class="form-label">Nombre Completo *</label>
                                <div class="input-group">
                                    <input type="text" name="client_name_input" id="client_name_input_std" class="form-control" placeholder="Ej. Juan Pérez" value="<?php echo $edit_order ? htmlspecialchars($edit_order['client_name']) : ''; ?>" required autocomplete="off">
                                    <i class="ph ph-user input-icon"></i>
                                </div>
                                <div class="search-results" id="client_autocomplete_results_std" style="width: 100%;"></div>
                            </div>

                            <div class="form-group" style="position: relative;">
                                <label class="form-label">Cédula/RUC *</label>
                                <div class="input-group">
                                    <input type="text" name="client_tax_id" id="client_tax_id_std" class="form-control" placeholder="Número de identificación" required value="<?php echo $edit_order ? ($edit_order['tax_id'] ?? '') : ''; ?>" autocomplete="off">
                                    <i class="ph ph-identification-card input-icon"></i>
                                </div>
                                <div class="search-results" id="client_tax_results_std"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Teléfono *</label>
                                <div class="input-group">
                                    <input type="text" name="client_phone" id="client_phone_std" class="form-control" placeholder="Ej. 5555-4444" required value="<?php echo $edit_order ? htmlspecialchars($edit_order['phone'] ?? '') : ''; ?>">
                                    <i class="ph ph-phone input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Correo Electrónico</label>
                                <div class="input-group">
                                    <input type="email" name="client_email" id="client_email_std" class="form-control" placeholder="cliente@ejemplo.com" value="<?php echo $edit_order ? ($edit_order['email'] ?? '') : ''; ?>">
                                    <i class="ph ph-envelope input-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Dirección</label>
                                <div class="input-group">
                                    <input type="text" name="client_address" id="client_address_std" class="form-control" placeholder="Dirección completa" value="<?php echo $edit_order ? ($edit_order['address'] ?? '') : ''; ?>">
                                    <i class="ph ph-map-pin input-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: EQUIPMENT DATA (REPEATER) -->
                <div class="step-container" id="step-2">
                    <!-- Client selected info -->
                    <div style="margin-bottom: 1.5rem;">
                        <label class="form-label" style="color: var(--primary-500); font-weight: 600;"><i class="ph ph-user-check"></i> Cliente Seleccionado</label>
                        <input type="text" id="display_client_name_std" class="form-control" readonly disabled style="background-color: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px dashed var(--border-color); cursor: not-allowed;" value="Seleccionado en el Paso 1">
                    </div>

                    <div id="equipment-container">
                        <!-- Initial Equipment Block -->
                        <div class="form-section equipment-block animate-enter" id="eq-block-0" style="border-left: 4px solid var(--primary-500);">
                            <div class="form-section-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <span><i class="ph ph-desktop"></i> Datos del Equipo #1</span>
                                <button type="button" class="btn btn-sm btn-danger remove-eq-btn" style="display: none; padding: 4px 12px; font-size: 0.8rem; border-radius: 4px;" onclick="removeEquipment(0)">
                                    <i class="ph ph-trash"></i> Eliminar
                                </button>
                            </div>
                            
                            <div class="modern-grid" style="grid-template-columns: repeat(6, 1fr);">
                                <div class="form-group" style="grid-column: span 3;">
                                    <label class="form-label">Serie (S/N) *</label>
                                    <div class="input-group">
                                        <input type="text" name="serial_number[]" class="form-control serial-input" required placeholder="Número de serie" onblur="validateSerial(this)">
                                        <i class="ph ph-barcode input-icon"></i>
                                    </div>
                                    <div class="warranty-status-msg" style="font-size:0.85rem; margin-top:0.4rem; font-weight: 500;"></div>
                                </div>

                                <div class="form-group" style="grid-column: span 3;">
                                    <label class="form-label">Cliente Registrado (S/N)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control registered-client-input" readonly placeholder="Se autocompleta con S/N" style="background-color: rgba(255,255,255,0.05); color: var(--text-muted); cursor: not-allowed; border-style: dashed;">
                                        <i class="ph ph-user input-icon"></i>
                                    </div>
                                </div>

                                 <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Marca *</label>
                                    <div class="input-group">
                                        <input type="text" name="brand[]" class="form-control" required placeholder="Ej. HP, Dell, Apple">
                                        <i class="ph ph-tag input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Modelo *</label>
                                    <div class="input-group">
                                        <input type="text" name="model[]" class="form-control" required placeholder="Ej. Pavilion 15">
                                        <i class="ph ph-laptop input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Tipo *</label>
                                    <div class="input-group">
                                        <input list="type-options" name="type[]" class="form-control" required placeholder="Laptop, PC, Consola...">
                                        <i class="ph ph-monitor input-icon"></i>
                                    </div>
                                </div>

                                <div class="form-group" style="grid-column: span 6;">
                                     <label class="form-label">Accesorios Recibidos *</label>
                                     <div class="input-group">
                                        <input type="text" name="accessories[]" class="form-control" placeholder="Cargador, cables, bolso, etc." required>
                                        <i class="ph ph-plug input-icon"></i>
                                     </div>
                                </div>

                                <div class="form-group" style="grid-column: span 6;">
                                    <label class="form-label">Tipo de Ingreso *</label>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <label class="selection-card active" style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 0.75rem; border: 1.5px solid var(--primary-500); border-radius: 8px; cursor: pointer; transition: all 0.2s; background: rgba(59, 130, 246, 0.05); color: var(--primary-500);">
                                            <input type="radio" name="service_type_0" value="service" checked style="display: none;" onchange="updateRadioSync(this, 0)">
                                            <input type="hidden" name="service_type[]" value="service" id="service_type_hidden_0">
                                            <i class="ph ph-wrench" style="font-size: 1.1rem;"></i>
                                            <span style="font-weight: 500;">Servicio / Reparación</span>
                                        </label>

                                        <label class="selection-card" style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 0.75rem; border: 1.5px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s; background: var(--bg-body);">
                                            <input type="radio" name="service_type_0" value="warranty" style="display: none;" onchange="updateRadioSync(this, 0)">
                                            <i class="ph ph-shield-check" style="font-size: 1.1rem;"></i>
                                            <span style="font-weight: 500;">Garantía</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group" style="grid-column: span 6;">
                                     <label class="form-label">Problema Reportado *</label>
                                     <div class="input-group">
                                        <textarea name="problem_reported[]" class="form-control" rows="2" placeholder="Describe detalladamente lo que el cliente reporta..." required style="padding-left: 2.5rem;"></textarea>
                                        <i class="ph ph-warning-circle input-icon" style="top: 1rem;"></i>
                                     </div>
                                </div>

                                <div class="form-group" style="grid-column: span 3;">
                                    <label class="form-label">Dueño / Propietario (Si aplica)</label>
                                    <div class="input-group">
                                        <input type="text" name="owner_name[]" class="form-control" placeholder="Nombre del usuario final">
                                        <i class="ph ph-user-circle input-icon"></i>
                                    </div>
                                </div>
                                <div class="form-group" style="grid-column: span 3;">
                                    <label class="form-label">Submodelo / Variación</label>
                                    <div class="input-group">
                                        <input type="text" name="submodel[]" class="form-control" placeholder="Opcional">
                                        <i class="ph ph-info input-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="addEquipment()" style="border-style: dashed; background: rgba(59, 130, 246, 0.05); width: 100%; padding: 1rem; color: var(--primary-600); border: 2px dashed var(--primary-300);">
                            <i class="ph ph-plus-circle"></i> <strong>Añadir otro equipo a esta recepción</strong>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: SUMMARY & FINAL DETAILS -->
                <div class="step-container" id="step-3">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-check-square-offset"></i> Revisión Final de Datos
                        </div>
                        
                        <!-- Summary Grid -->
                        <div class="modern-grid" style="grid-template-columns: 1fr; margin-bottom: 1.5rem; gap: 1.5rem;">
                            <!-- Client Summary Card -->
                            <div style="background: var(--bg-body); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
                                <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary-600);">
                                    <i class="ph ph-user-circle" style="font-size: 1.2rem;"></i> Datos del Cliente
                                </h4>
                                <div id="summary-client-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                                    <!-- Populated by JS -->
                                </div>
                            </div>

                            <!-- Equipments Summary Card -->
                            <div style="background: var(--bg-body); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color);">
                                <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary-600);">
                                    <i class="ph ph-desktop" style="font-size: 1.2rem;"></i> Equipos a Ingresar
                                </h4>
                                <div id="summary-equipments-list" style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                                                <th style="padding: 0.5rem;">#</th>
                                                <th style="padding: 0.5rem;">Equipo / Serie</th>
                                                <th style="padding: 0.5rem;">Problema</th>
                                                <th style="padding: 0.5rem;">Tipo</th>
                                            </tr>
                                        </thead>
                                        <tbody id="summary-equipments-tbody">
                                            <!-- Populated by JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="modern-grid" style="grid-template-columns: 1fr 1fr; border-top: 1px dashed var(--border-color); padding-top: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label">Número de Factura (Opcional)</label>
                                <div class="input-group">
                                    <input type="text" name="invoice_number" class="form-control" placeholder="Factura de referencia">
                                    <i class="ph ph-file-text input-icon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Observaciones Generales</label>
                                <div class="input-group">
                                    <input type="text" name="entry_notes" class="form-control" placeholder="Notas sobre el estado físico general...">
                                    <i class="ph ph-note-pencil input-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- NAVIGATION BUTTONS -->
            <?php if (!$is_warranty_mode): ?>
            <div class="wizard-buttons">
                <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-secondary" style="display: none; padding: 0.75rem 1.5rem; border-radius: 8px;">
                    <i class="ph ph-arrow-left"></i> Anterior
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary" style="padding: 0.75rem 2.5rem; border-radius: 8px;">
                    Siguiente <i class="ph ph-arrow-right"></i>
                </button>
                <div id="submitBtnContainer" style="display: none;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; background: var(--success); border-color: var(--success); border-radius: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                        <i class="ph ph-check-circle"></i> Confirmar y Guardar Recepción
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <script>
                let currentStep = 1;
                const isWarrantyMode = <?php echo $is_warranty_mode ? 'true' : 'false'; ?>;
                let equipmentCount = 1;

                function showStep(n) {
                    if (isWarrantyMode) return;
                    
                    const steps = document.querySelectorAll('.step-container');
                    const indicators = document.querySelectorAll('.step-item');
                    
                    steps.forEach((step, i) => {
                        step.classList.toggle('active', i + 1 === n);
                    });

                    indicators.forEach((indicator, i) => {
                        indicator.classList.toggle('active', i + 1 === n);
                        indicator.classList.toggle('completed', i + 1 < n);
                    });

                    // Update progress line
                    const progressLine = document.getElementById('wizard-progress');
                    if (progressLine) {
                        const totalSteps = indicators.length;
                        const percent = ((n - 1) / (totalSteps - 1)) * 100;
                        progressLine.style.width = `calc(${percent}% * 0.68)`; 
                    }

                    // Update Client Display when entering step 2
                    if (n === 2) {
                        if (isWarrantyMode) {
                            const nameWry = document.getElementById('client_name_input_wry') ? document.getElementById('client_name_input_wry').value : '';
                            const displayWry = document.getElementById('display_client_name_wry');
                            if (displayWry) displayWry.value = nameWry || 'No seleccionado';
                        } else {
                            const nameStd = document.getElementById('client_name_input_std') ? document.getElementById('client_name_input_std').value : '';
                            const taxStd = document.getElementById('client_tax_id_std') ? document.getElementById('client_tax_id_std').value : '';
                            const displayStd = document.getElementById('display_client_name_std');
                            if (displayStd) displayStd.value = nameStd ? `${nameStd} ${taxStd ? ' - ' + taxStd : ''}` : 'No seleccionado';
                        }
                    }

                    // Update buttons
                    document.getElementById('prevBtn').style.display = (n === 1) ? 'none' : 'inline-flex';
                    
                    const lastStep = 3; 
                    if (n === lastStep) {
                        document.getElementById('nextBtn').style.display = 'none';
                        document.getElementById('submitBtnContainer').style.display = 'block';
                        updateSummary();
                    } else {
                        document.getElementById('nextBtn').style.display = 'inline-flex';
                        document.getElementById('submitBtnContainer').style.display = 'none';
                    }

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                function nextStep() {
                    const currentStepContainer = document.querySelector(`.step-container#step-${currentStep}`);
                    const inputs = currentStepContainer.querySelectorAll('[required]');
                    let valid = true;

                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.classList.add('is-invalid');
                            valid = false;
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });

                    if (valid) {
                        currentStep++;
                        showStep(currentStep);
                    } else {
                        const firstInvalid = currentStepContainer.querySelector('.is-invalid');
                        if (firstInvalid) firstInvalid.focus();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Campos requeridos',
                            text: 'Por favor complete todos los campos marcados con *',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                }

                function prevStep() {
                    currentStep--;
                    showStep(currentStep);
                }

                // --- REPEATER LOGIC ---
                function addEquipment() {
                    const container = document.getElementById('equipment-container');
                    const index = equipmentCount++;
                    const template = document.getElementById('eq-block-0').cloneNode(true);
                    
                    template.id = `eq-block-${index}`;
                    template.classList.remove('animate-enter');
                    template.style.opacity = '0';
                    template.style.marginTop = '1.5rem';
                    template.querySelector('.form-section-header span').innerHTML = `<i class="ph ph-desktop"></i> Datos del Equipo #${index + 1}`;
                    
                    // Reset inputs
                    template.querySelectorAll('input, textarea').forEach(inp => {
                        if (inp.type !== 'radio' && inp.type !== 'hidden') inp.value = '';
                        inp.classList.remove('is-invalid');
                    });
                    
                    // Reset status msg
                    template.querySelector('.warranty-status-msg').innerHTML = '';

                    // Handle Radio Groups sync for the new block
                    const radios = template.querySelectorAll('input[type="radio"]');
                    radios.forEach(r => {
                        r.name = `service_type_${index}`;
                        r.setAttribute('onchange', `updateRadioSync(this, ${index})`);
                        if (r.value === 'service') {
                            r.checked = true;
                        } else {
                            r.checked = false;
                        }
                    });
                    
                    // Reset selection cards visuals
                    const cards = template.querySelectorAll('.selection-card');
                    cards.forEach(c => {
                        const r = c.querySelector('input[type="radio"]');
                        if (r.value === 'service') {
                            c.classList.add('active');
                            c.style.borderColor = 'var(--primary-500)';
                            c.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
                            c.style.color = 'var(--primary-500)';
                        } else {
                            c.classList.remove('active');
                            c.style.borderColor = 'var(--border-color)';
                            c.style.backgroundColor = 'var(--bg-body)';
                            c.style.color = 'inherit';
                        }
                    });

                    const hiddenType = template.querySelector('input[name="service_type[]"]');
                    hiddenType.id = `service_type_hidden_${index}`;
                    hiddenType.value = 'service';
                    
                    container.appendChild(template);
                    
                    // Animate entry
                    setTimeout(() => {
                        template.style.transition = 'opacity 0.3s ease';
                        template.style.opacity = '1';
                    }, 10);

                    updateRemoveButtons();
                    updateEquipmentNumbers();
                }

                function removeEquipment(index) {
                    const block = document.getElementById(`eq-block-${index}`);
                    if (block) {
                        block.style.opacity = '0';
                        setTimeout(() => {
                            block.remove();
                            updateEquipmentNumbers();
                            updateRemoveButtons();
                        }, 300);
                    }
                }

                function updateEquipmentNumbers() {
                    const blocks = document.querySelectorAll('.equipment-block');
                    blocks.forEach((block, i) => {
                        // Update block ID
                        block.id = `eq-block-${i}`;
                        
                        // Update title
                        block.querySelector('.form-section-header span').innerHTML = `<i class="ph ph-desktop"></i> Datos del Equipo #${i + 1}`;
                        
                        // Update remove button onclick
                        const removeBtn = block.querySelector('.remove-eq-btn');
                        if (removeBtn) {
                            removeBtn.setAttribute('onclick', `removeEquipment(${i})`);
                        }
                        
                        // Update radio groups for service type
                        const radios = block.querySelectorAll('input[type="radio"]');
                        radios.forEach(r => {
                            r.name = `service_type_${i}`;
                            r.setAttribute('onchange', `updateRadioSync(this, ${i})`);
                        });
                        
                        // Update hidden service type input
                        const hiddenType = block.querySelector('input[name="service_type[]"]');
                        if (hiddenType) {
                            hiddenType.id = `service_type_hidden_${i}`;
                        }
                    });
                    
                    // Sync the global counter to the new length to avoid ID collisions on next add
                    equipmentCount = blocks.length;
                }

                function updateRemoveButtons() {
                    const blocks = document.querySelectorAll('.equipment-block');
                    blocks.forEach((block, i) => {
                        const btn = block.querySelector('.remove-eq-btn');
                        if (blocks.length > 1) {
                            btn.style.display = 'inline-flex';
                        } else {
                            btn.style.display = 'none';
                        }
                    });
                }

                function updateRadioSync(radio, index) {
                    const block = radio.closest('.equipment-block');
                    const hidden = block.querySelector('input[name="service_type[]"]');
                    if (hidden) hidden.value = radio.value;
                    
                    // Update visual cards inside this block only
                    const cards = block.querySelectorAll('.selection-card');
                    cards.forEach(c => {
                        c.classList.remove('active');
                        c.style.borderColor = 'var(--border-color)';
                        c.style.backgroundColor = 'var(--bg-body)';
                        c.style.color = 'inherit';
                    });
                    
                    const card = radio.closest('.selection-card');
                    card.classList.add('active');
                    card.style.borderColor = 'var(--primary-500)';
                    card.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
                    card.style.color = 'var(--primary-500)';
                }

                function validateSerial(input) {
                    const serial = input.value.trim();
                    if (serial.length < 3) return;
                    
                    const block = input.closest('.equipment-block');
                    const statusDiv = block.querySelector('.warranty-status-msg');
                    
                    statusDiv.innerHTML = '<span style="color:var(--text-secondary);"><i class="ph ph-spinner ph-spin"></i> Verificando...</span>';

                    fetch('check_warranty.php?serial_number=' + encodeURIComponent(serial))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.data) {
                            if (!block.querySelector('[name="brand[]"]').value) block.querySelector('[name="brand[]"]').value = data.data.brand || '';
                            if (!block.querySelector('[name="model[]"]').value) block.querySelector('[name="model[]"]').value = data.data.model || '';
                            if (!block.querySelector('[name="type[]"]').value) block.querySelector('[name="type[]"]').value = data.data.type || '';
                            
                            const clientInput = block.querySelector('.registered-client-input');
                            if (clientInput) {
                                clientInput.value = data.data.client_name || 'Sin cliente registrado';
                                clientInput.style.color = data.data.client_name ? 'var(--primary-light)' : 'var(--text-muted)';
                            }
                            
                            if (data.status === 'valid') {
                                statusDiv.innerHTML = '<span style="color:var(--success);"><i class="ph ph-check-circle"></i> Garantía Activa</span>';
                            } else if (data.status === 'expired') {
                                statusDiv.innerHTML = '<span style="color:var(--warning);"><i class="ph ph-warning"></i> Garantía Expirada</span>';
                            } else {
                                statusDiv.innerHTML = '<span style="color:var(--text-secondary);"><i class="ph ph-info"></i> Registrado previamente</span>';
                            }
                        } else {
                            statusDiv.innerHTML = '<span style="color:var(--primary-500);"><i class="ph ph-sparkle"></i> Equipo nuevo en taller</span>';
                            const clientInput = block.querySelector('.registered-client-input');
                            if (clientInput) clientInput.value = '';
                        }
                    })
                    .catch(e => {
                        statusDiv.innerHTML = '';
                    });
                }

                function updateSummary() {
                    // Update Client Info
                    const clientName = document.getElementById('client_name_input_std').value;
                    const clientTax = document.getElementById('client_tax_id_std').value;
                    const clientPhone = document.getElementById('client_phone_std').value;
                    const clientEmail = document.getElementById('client_email_std').value;

                    const clientInfoDiv = document.getElementById('summary-client-info');
                    clientInfoDiv.innerHTML = `
                        <div><strong>Nombre:</strong> ${clientName}</div>
                        <div><strong>ID/RUC:</strong> ${clientTax || '-'}</div>
                        <div><strong>Teléfono:</strong> ${clientPhone}</div>
                        <div><strong>Email:</strong> ${clientEmail || '-'}</div>
                    `;

                    // Update Equipments List
                    const equipmentBlocks = document.querySelectorAll('.equipment-block');
                    const tbody = document.getElementById('summary-equipments-tbody');
                    tbody.innerHTML = '';

                    equipmentBlocks.forEach((block, i) => {
                        const serial = block.querySelector('[name="serial_number[]"]').value;
                        const brand = block.querySelector('[name="brand[]"]').value;
                        const model = block.querySelector('[name="model[]"]').value;
                        const problem = block.querySelector('[name="problem_reported[]"]').value;
                        const type = block.querySelector('[name="service_type[]"]').value;
                        const typeLabel = type === 'warranty' ? '<span class="badge badge-warning">Garantía</span>' : '<span class="badge badge-info">Servicio</span>';

                        const row = document.createElement('tr');
                        row.style.borderBottom = '1px solid var(--border-color)';
                        row.innerHTML = `
                            <td style="padding: 0.75rem;">${i + 1}</td>
                            <td style="padding: 0.75rem;">
                                <strong>${brand} ${model}</strong><br>
                                <span class="text-muted" style="font-size: 0.75rem;">S/N: ${serial}</span>
                            </td>
                            <td style="padding: 0.75rem;">${problem}</td>
                            <td style="padding: 0.75rem;">${typeLabel}</td>
                        `;
                        tbody.appendChild(row);
                    });
                }
                
                <?php if (!$is_warranty_mode): ?>
                showStep(currentStep);
                <?php endif; ?>
            </script>

                <!-- DEBUG BANNER -->
                <!-- Debug Banner Removed -->

                <?php if (!$is_warranty_mode): ?>
                <!-- JS Script (Consolidated & Fixed) -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const banner = document.getElementById('debug_banner');
                        
                        function log(msg, color = null) {
                            console.log(msg);
                            if(banner) {
                                banner.innerHTML += " | " + msg;
                                if(color) banner.style.backgroundColor = color;
                            }
                        }

                        try {
                            log("Iniciando Script...");
                            
                            // 1. Load Clients securely
                            const allClients = <?php echo json_encode($clients) ?: '[]'; ?>;
                            log("Clientes: " + allClients.length);

                            // 2. Identify Elements
                            const taxInput = document.getElementById('client_tax_id_std');
                            const taxResultsDiv = document.getElementById('client_tax_results_std');
                            const nameInput = document.getElementById('client_name_input_std');
                            const idInput = document.getElementById('client_id_hidden_std');
                            const resultsDiv = document.getElementById('client_autocomplete_results_std');
                            const clearBtn = document.getElementById('btnClearClient_std');
                            
                            // Warranty Elements
                            const nameInputWry = document.getElementById('client_name_input_wry');
                            const idInputWry = document.getElementById('client_id_hidden_wry');
                            const resultsDivWry = document.getElementById('client_autocomplete_results_wry');

                            // Other inputs for population
                            const phoneInput = document.getElementById('client_phone_std');
                            const emailInput = document.getElementById('client_email_std');
                            const addressInput = document.getElementById('client_address_std');

                            if (!taxInput) {
                                throw new Error("Input Cédula NO encontrado");
                            }
                            
                            
                            log("Inputs OK");
                            // taxInput.style.border = '3px solid blue'; // Ready state
                            // taxInput.style.backgroundColor = '#e6f3ff';

                            // 3. Define Helper Functions
                            function fillClientData(client, isWry = false) {
                                if(!client) return;
                                
                                if(isWry) {
                                    if(nameInputWry) nameInputWry.value = client.name;
                                    if(idInputWry) idInputWry.value = client.id;
                                    if(resultsDivWry) resultsDivWry.classList.remove('show');
                                } else {
                                    if(nameInput) nameInput.value = client.name;
                                    if(idInput) idInput.value = client.id;
                                    if(taxInput) taxInput.value = client.tax_id || '';
                                    if(phoneInput) phoneInput.value = client.phone || '';
                                    if(emailInput) emailInput.value = client.email || '';
                                    if(addressInput) addressInput.value = client.address || '';
                                    
                                    if(clearBtn) clearBtn.style.display = 'block';
                                    
                                    // Hide all dropdowns
                                    if(resultsDiv) resultsDiv.classList.remove('show');
                                    if(taxResultsDiv) taxResultsDiv.classList.remove('show');
                                }
                                
                                log("CLIENTE: " + client.name, "#90EE90");
                            }

                            function createOption(html, onClick) {
                                const div = document.createElement('div');
                                div.className = 'search-option';
                                div.innerHTML = html;
                                div.addEventListener('click', onClick);
                                return div;
                            }

                            // 4. Attach Listeners

                            // --- A. Cédula Search ---
                            if (taxInput) {
                                taxInput.addEventListener('keydown', function(event) {
                                    if (event.key === 'Enter') event.preventDefault();
                                });

                                taxInput.addEventListener('input', function() {
                                    const val = this.value.trim(); 
                                    
                                    if(val.length === 0) {
                                        if(taxResultsDiv) taxResultsDiv.classList.remove('show');
                                        return;
                                    }

                                    const matches = allClients.filter(c => {
                                        const tax = c.tax_id ? c.tax_id.toString().toLowerCase() : '';
                                        return tax.includes(val.toLowerCase());
                                    });
                                    
                                    if(taxResultsDiv) {
                                        taxResultsDiv.innerHTML = '';
                                        if (matches.length > 0) {
                                            matches.slice(0, 5).forEach(c => { 
                                                const html = `<strong>${c.tax_id || 'N/A'}</strong> - ${c.name}`;
                                                const div = createOption(html, () => fillClientData(c));
                                                taxResultsDiv.appendChild(div);
                                            });
                                        } else {
                                            const div = document.createElement('div');
                                            div.style.padding = '12px 16px';
                                            div.style.color = 'var(--text-muted)';
                                            div.innerHTML = `<em>Sin resultados</em>`;
                                            taxResultsDiv.appendChild(div);
                                        }
                                        taxResultsDiv.classList.add('show');
                                    }
                                });
                            }

                            // Hide on click outside
                            document.addEventListener('click', (e) => {
                                if (taxResultsDiv && !taxResultsDiv.contains(e.target) && e.target !== taxInput) {
                                    taxResultsDiv.classList.remove('show');
                                }
                                if (resultsDiv && !resultsDiv.contains(e.target) && e.target !== nameInput) {
                                    resultsDiv.classList.remove('show');
                                }
                                if (resultsDivWry && !resultsDivWry.contains(e.target) && e.target !== nameInputWry) {
                                    resultsDivWry.classList.remove('show');
                                }
                            });


                            // --- B. Name Search (Existing Logic Ported) ---
                            if(nameInput) {
                                nameInput.addEventListener('input', function() {
                                    const val = this.value.toLowerCase().trim();
                                    
                                    // Reset ID if user types anew, unless picking from list
                                    if(idInput) idInput.value = ''; 
                                    
                                    if (clearBtn) {
                                        if(val.length > 0) clearBtn.style.display = 'block';
                                        else clearBtn.style.display = 'none';
                                    }

                                    if (val.length === 0) {
                                        if (resultsDiv) resultsDiv.classList.remove('show');
                                        return;
                                    }

                                    const matches = allClients.filter(c => c.name.toLowerCase().includes(val));
                                    
                                    if (resultsDiv) {
                                        resultsDiv.innerHTML = '';
                                        if (matches.length > 0) {
                                            matches.slice(0, 10).forEach(c => {
                                                const html = `<strong>${c.name}</strong> <span style='font-size:0.8em; opacity: 0.7;'>${c.tax_id || ''}</span>`;
                                                const div = createOption(html, () => fillClientData(c));
                                                resultsDiv.appendChild(div);
                                            });
                                            resultsDiv.classList.add('show');
                                        } else {
                                            resultsDiv.classList.remove('show');
                                        }
                                    }
                                });

                                if(clearBtn) {
                                    clearBtn.addEventListener('click', () => {
                                        nameInput.value = '';
                                        if(idInput) idInput.value = '';
                                        if(taxInput) taxInput.value = '';
                                        if(phoneInput) phoneInput.value = '';
                                        if(emailInput) emailInput.value = '';
                                        if(addressInput) addressInput.value = '';
                                        clearBtn.style.display = 'none';
                                        
                                        // Reset Debug visuals
                                        if(banner) {
                                            banner.innerHTML = "MODO DEPURACIÓN: Limpiado";
                                            banner.style.backgroundColor = "orange";
                                        }

                                    });
                                }

                                // Removed aggressive blur clearing to allow new client entry
                            }

                            // --- C. Warranty Name Search ---
                            if(nameInputWry) {
                                nameInputWry.addEventListener('input', function() {
                                    const val = this.value.toLowerCase().trim();
                                    if(idInputWry) idInputWry.value = ''; 
                                    
                                    if (val.length === 0) {
                                        if (resultsDivWry) resultsDivWry.classList.remove('show');
                                        return;
                                    }

                                    const matches = allClients.filter(c => c.name.toLowerCase().includes(val));
                                    if (resultsDivWry) {
                                        resultsDivWry.innerHTML = '';
                                        if (matches.length > 0) {
                                            matches.slice(0, 10).forEach(c => {
                                                const html = `<strong>${c.name}</strong> <span style='font-size:0.8em; opacity: 0.7;'>${c.tax_id || ''}</span>`;
                                                const div = createOption(html, () => fillClientData(c, true));
                                                resultsDivWry.appendChild(div);
                                            });
                                            resultsDivWry.classList.add('show');
                                        } else {
                                            resultsDivWry.classList.remove('show');
                                        }
                                    }
                                });

                                // Removed aggressive blur clearing to allow new client entry
                            }

                        } catch (e) {
                            console.error(e);
                            if(banner) {
                                banner.innerHTML += " | FATAL JS: " + e.message;
                                banner.style.backgroundColor = "red";
                                banner.style.color = "white";
                            }
                        }
                    });
                </script>
            <?php endif; ?>

            <!-- SUBMIT BUTTON (Hidden, managed by JS Wizard) -->

        </form>
    </div>

    <!-- GLOBAL SCRIPTS (Runs on both Standard and Warranty modes) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Global Auto-fill Script Loaded");

            // --- 1. WARRANTY / SERIAL CHECKER ---
            const verifyButtons = document.querySelectorAll('.btn-verify-serial');
            
            verifyButtons.forEach(btn => {
                const inputId = btn.getAttribute('data-target');
                const input = document.querySelector(inputId);
                
                if(!input) return;
                
                const statusDiv = input.closest('.form-group').querySelector('.warranty-status-msg');

                // Define the Check Function
                const runWarrantyCheck = () => {
                     const serial = input.value.trim();
                     if(serial.length < 2) {
                         // Optional: alert("Por favor ingrese un número de serie.");
                         return;
                     }

                     statusDiv.innerHTML = '<span style="color:var(--text-secondary);"><i class="ph ph-spinner ph-spin"></i> Verificando...</span>';

                     fetch('check_warranty.php?serial_number=' + encodeURIComponent(serial))
                     .then(r => r.json())
                     .then(data => {
                         if(data.success) {
                            // Status Feedback
                            if (data.status === 'valid') {
                                statusDiv.innerHTML = '<span style="color:var(--success); background:rgba(16, 185, 129, 0.1); padding:2px 6px; border-radius:4px;"><i class="ph ph-check-circle"></i> Garantía Activa</span>';
                            } else if (data.status === 'expired') {
                                statusDiv.innerHTML = '<span style="color:var(--warning); background:rgba(245, 158, 11, 0.1); padding:2px 6px; border-radius:4px;"><i class="ph ph-warning"></i> Garantía Expirada</span>';
                            } else if (data.status === 'void') {
                                statusDiv.innerHTML = '<span style="color:var(--danger); background:rgba(239, 68, 68, 0.1); padding:2px 6px; border-radius:4px;"><i class="ph ph-x-circle"></i> Garantía Anulada</span>';
                            } else if (data.status === 'no_warranty') {
                                statusDiv.innerHTML = '<span style="color:var(--text-secondary);"><i class="ph ph-info"></i> Registrado (Sin garantía)</span>';
                            } else {
                                statusDiv.innerHTML = '<span style="color:var(--primary-500);"><i class="ph ph-plus-circle"></i> Nueva serie</span>';
                            }

                            // Auto-fill Logic
                            if (data.data) {
                                console.log("Auto-filling data:", data.data);
                                
                                const fields = {
                                    'brand': data.data.brand,
                                    'model': data.data.model,
                                    'submodel': data.data.submodel,
                                    'type': data.data.type,
                                    'sales_invoice_number': data.data.invoice,
                                    'invoice_number': data.data.invoice
                                };

                                for (const [name, value] of Object.entries(fields)) {
                                    const els = document.querySelectorAll(`input[name="${name}"], select[name="${name}"]`);
                                    els.forEach(el => {
                                        if(el && value) {
                                            el.value = value;
                                        }
                                    });
                                }
                                
                                // Special: Client Display
                                if(data.data.client_name) {
                                    const display = document.getElementById('equipment_client_display');
                                    let owner = data.data.original_owner || data.data.client_name;
                                    if(display && owner) {
                                        display.value = owner;
                                    }
                                }
                            }
                         } else {
                            statusDiv.innerHTML = `<span style="color:var(--danger)">Error: ${data.message}</span>`;
                         }
                     })
                     .catch(e => {
                         console.error(e);
                         statusDiv.innerHTML = '<span style="color:var(--danger)">Error de conexión</span>';
                     });
                };

                // Attach Listeners
                btn.onclick = (e) => {
                    e.preventDefault();
                    runWarrantyCheck();
                };

                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        runWarrantyCheck();
                    }
                });
                
                // Optional: Blur event
                input.addEventListener('blur', function() {
                     if(this.value.length > 3) runWarrantyCheck();
                });
            });

            // --- 2. WARRANTY DATE CALCULATOR ---
            const durationInput = document.getElementById('warranty_duration');
            const periodSelect = document.getElementById('warranty_period');
            const endInput = document.getElementById('warranty_end_date');

            if(durationInput && periodSelect && endInput) {
                function calculateEndDate() {
                    const duration = parseInt(durationInput.value) || 0;
                    const period = periodSelect.value;
                    
                    if(duration === 0) {
                        endInput.value = '';
                        return;
                    }

                    const startDate = new Date(); // Today
                    let endDate = new Date(startDate);

                    if(period === 'days') {
                        endDate.setDate(startDate.getDate() + duration);
                    } else if(period === 'weeks') {
                        endDate.setDate(startDate.getDate() + (duration * 7));
                    } else if(period === 'months') {
                        endDate.setMonth(startDate.getMonth() + duration);
                    } else if(period === 'years') {
                        endDate.setFullYear(startDate.getFullYear() + duration);
                    }
                    
                    // Format YYYY-MM-DD
                    const yyyy = endDate.getFullYear();
                    const mm = String(endDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(endDate.getDate()).padStart(2, '0');
                    
                    endInput.value = `${yyyy}-${mm}-${dd}`;
                }

                durationInput.addEventListener('input', calculateEndDate);
                periodSelect.addEventListener('change', calculateEndDate);
                
                // Trigger immediately in case of pre-filled values or browser history
                calculateEndDate();
            }
        });
    </script>


    <!-- EXPIRED WARRANTIES SECTION -->
    <?php if ($is_warranty_mode && !$edit_order): 
        $stmtExpired = $pdo->query("
            SELECT 
                w.id, w.end_date, w.status, w.product_code, w.sales_invoice_number, w.supplier_name,
                w.master_entry_invoice, w.master_entry_date,
                e.brand, e.model, e.serial_number,
                c.name as client_name
            FROM warranties w
            JOIN equipments e ON w.equipment_id = e.id
            LEFT JOIN clients c ON e.client_id = c.id
            WHERE (w.status = 'expired' OR w.end_date < CURDATE())
            ORDER BY w.end_date DESC
            LIMIT 50
        ");
        $expiredWarranties = $stmtExpired->fetchAll();
        
        if(count($expiredWarranties) > 0):
    ?>
    <div class="card" style="margin-top: 2rem; border-color: var(--danger);">
        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); background: rgba(239, 68, 68, 0.05); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--danger);">Garantías Vencidas</h3>
            <div class="input-group" style="width: 300px;">
                 <input type="text" id="expiredSearch" class="form-control" placeholder="Buscar en garantías..." style="font-size: 0.9rem;">
                 <i class="ph ph-magnifying-glass input-icon"></i>
            </div>
        </div>
        <div class="table-container">
            <table class="table" id="expiredTable">
                <thead>
                    <tr>
                        <th>Fecha Vencimiento</th>
                        <th>Registrado Por</th>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Modelo / Serie</th>
                        <th>Factura</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($expiredWarranties as $w): ?>
                        <tr class="clickable-row" onclick='openWarrantyModal(<?php echo json_encode($w); ?>)'>
                            <td><?php echo date('d/m/Y', strtotime($w['end_date'])); ?></td>
                            <td><?php echo htmlspecialchars($w['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($w['product_code'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($w['client_name'] ?? 'Sin Cliente'); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($w['brand'] . ' ' . $w['model']); ?></div>
                                <div class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($w['serial_number']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($w['sales_invoice_number'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.getElementById('expiredSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#expiredTable tbody tr');
            
            rows.forEach(row => {
               let text = row.innerText.toLowerCase();
               if(text.includes(filter)) {
                   row.style.display = '';
               } else {
                   row.style.display = 'none';
               }
            });
        });
        </script>

    </div>
    <?php endif; endif; ?>
    
    <script>
    function openWarrantyModal(data) {
        document.getElementById('modal_code').value = data.product_code || '';
        document.getElementById('modal_model').value = (data.brand || '') + ' ' + (data.model || '');
        document.getElementById('modal_date').value = data.end_date || ''; 
        document.getElementById('modal_client').value = data.client_name || '';
        document.getElementById('modal_invoice').value = data.sales_invoice_number || '';
        document.getElementById('modal_serial').value = data.serial_number || '';
        document.getElementById('modal_master_invoice').value = data.master_entry_invoice || '';
        document.getElementById('modal_master_date').value = data.master_entry_date || '';
        document.getElementById('modal_supplier').value = data.supplier_name || '';
        
        const modal = document.getElementById('warrantyModal');
        modal.style.display = 'flex';
        
        // Close on click outside (but inside the container)
        modal.onclick = function(e) {
            if(e.target === modal) modal.style.display = 'none';
        }
    }
    </script>

    <div id="warrantyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
        <div class="card" style="width: 90%; max-width: 900px; max-height: 95%; overflow-y: auto; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border: 1px solid var(--border-color); margin: auto;">
            <button onclick="document.getElementById('warrantyModal').style.display = 'none'" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; color: var(--text-secondary); cursor: pointer;">&times;</button>
            
            <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary-500);">
                <i class="ph ph-shield-check" style="font-size: 1.5rem;"></i>
                <h2 style="margin: 0; font-size: 1.25rem;">Detalles de Garantía</h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Código</label>
                    <input type="text" id="modal_code" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Descripción (Modelo)</label>
                    <input type="text" id="modal_model" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Fecha Vencimiento</label>
                    <input type="text" id="modal_date" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Cliente</label>
                    <input type="text" id="modal_client" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Factura Venta</label>
                    <input type="text" id="modal_invoice" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Serie</label>
                    <input type="text" id="modal_serial" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Fact. Ingreso Master</label>
                    <input type="text" id="modal_master_invoice" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Fecha Master</label>
                    <input type="text" id="modal_master_date" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem; color: var(--text-secondary);">Proveedor</label>
                    <input type="text" id="modal_supplier" class="form-control" readonly style="background: var(--bg-body); font-weight: 600;">
                </div>
            </div>
            
            <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                    <button onclick="document.getElementById('warrantyModal').style.display = 'none'" class="btn btn-primary">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
    // System-wide back button fix for forms
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            // Page was restored from cache (User hit Back button)
            const form = document.querySelector('form');
            if(form) form.reset();
            
            // Clear specific fields (safer to clear explicit inputs too)
            document.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(el => {
                 if(!el.hasAttribute('readonly') || el.classList.contains('form-control')) {
                     el.value = '';
                 }
            });

            // Reset hidden inputs like client_id
            document.querySelectorAll('input[type="hidden"]').forEach(el => {
                 // Preserve action or token inputs if they exist (though usually they are fine to reset)
                 // But specifically clear client_id and similar
                 if(el.name === 'client_id' || el.name === 'order_id') {
                     el.value = '';
                 }
            });
            
             // Clear visual states
            document.querySelectorAll('.is-valid, .is-invalid').forEach(el => el.classList.remove('is-valid', 'is-invalid'));
            
            // Hide clear button if present
            const clearBtn = document.getElementById('btnClearClient_std');
            if(clearBtn) clearBtn.style.display = 'none';
        }
    });
</script>
<?php require_once '../../includes/footer.php'; ?>
