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
$stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
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
    
    $service_type = $is_warranty_mode ? 'warranty' : clean($_POST['service_type']);
    $type = clean($_POST['type']);
    $brand = clean($_POST['brand']);
    $model = clean($_POST['model']);
    $submodel = clean($_POST['submodel'] ?? '');
    $serial_number = clean($_POST['serial_number']);
    
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
    $problem = clean($_POST['problem_reported'] ?? 'Garantía Registrada'); 
    $accessories = clean($_POST['accessories'] ?? '-');
    $notes = clean($_POST['entry_notes'] ?? '');
    $owner_name = clean($_POST['owner_name'] ?? '');
    
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
        // Find Existing by Name
        $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
        $stmtCheck->execute([$c_name]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $client_id = $existing['id'];
        } else {
            // New client creation disabled per user request
            $client_id = null;
        }
    } elseif (!empty($client_id)) {
        // Update existing client info?
        // User might have edited the phone number. Let's update it to keep data fresh.
        $stmtC = $pdo->prepare("UPDATE clients SET phone=?, email=?, tax_id=?, address=? WHERE id=?");
        $stmtC->execute([$c_phone, $c_email, $c_tax, $c_address, $client_id]);
    }

    if (empty($client_id) || empty($brand) || empty($model) || empty($serial_number)) {
        $error = "Por favor complete los campos obligatorios del equipo.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($order_id) {
                // Update Equipment - Define owner ONLY if it's warranty registration or a 'Garantía' type order
                // OR if the equipment doesn't have an owner yet.
                $is_warranty_action = ($is_warranty_mode || $service_type === 'warranty');
                
                // Get current equipment owner to check if it's empty
                $stmtEqCheck = $pdo->prepare("SELECT client_id FROM equipments WHERE id = (SELECT equipment_id FROM service_orders WHERE id = ?)");
                $stmtEqCheck->execute([$order_id]);
                $current_owner = $stmtEqCheck->fetchColumn();

                if (empty($current_owner)) {
                    $stmtEq = $pdo->prepare("UPDATE equipments SET client_id = ?, brand = ?, model = ?, submodel = ?, serial_number = ?, type = ? WHERE id = (SELECT equipment_id FROM service_orders WHERE id = ?)");
                    $stmtEq->execute([$client_id, $brand, $model, $submodel, $serial_number, $type, $order_id]);
                } else {
                    // Standard repair on existing equipment: preserve owner, just update details
                    $stmtEq = $pdo->prepare("UPDATE equipments SET brand = ?, model = ?, submodel = ?, serial_number = ?, type = ? WHERE id = (SELECT equipment_id FROM service_orders WHERE id = ?)");
                    $stmtEq->execute([$brand, $model, $submodel, $serial_number, $type, $order_id]);
                }

                // Update Order
                // For warranties, we might strictly use sales_invoice as the main reference or user supplied 'invoice_number'
                // Let's use sales_invoice as the primary ref if provided
                $main_ref = $is_warranty_mode ? $sales_invoice : $invoice_number;
                
                $stmtOrder = $pdo->prepare("UPDATE service_orders SET client_id = ?, owner_name = ?, invoice_number = ?, problem_reported = ?, accessories_received = ?, entry_notes = ? WHERE id = ?");
                $stmtOrder->execute([$client_id, $owner_name, $main_ref, $problem, $accessories, $notes, $order_id]);

                // Update Warranty Record
                if ($is_warranty_mode) {
                    // Check if warranty record exists
                    $stmtWCheck = $pdo->prepare("SELECT id FROM warranties WHERE service_order_id = ?");
                    $stmtWCheck->execute([$order_id]);
                    if($stmtWCheck->fetch()) {
                        $stmtW = $pdo->prepare("UPDATE warranties SET product_code=?, sales_invoice_number=?, master_entry_invoice=?, master_entry_date=?, supplier_name=?, notes=?, end_date=?, duration_months=?, terms=? WHERE service_order_id=?");
                        $stmtW->execute([$product_code, $sales_invoice, $master_invoice, $master_date, $supplier, $notes, $warranty_end_date, $warranty_duration, $terms, $order_id]);
                    } else {
                        $stmtW = $pdo->prepare("INSERT INTO warranties (service_order_id, equipment_id, product_code, sales_invoice_number, master_entry_invoice, master_entry_date, supplier_name, notes, status, end_date, duration_months, terms) VALUES (?, (SELECT equipment_id FROM service_orders WHERE id=?), ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)");
                        $stmtW->execute([$order_id, $order_id, $product_code, $sales_invoice, $master_invoice, $master_date, $supplier, $notes, $warranty_end_date, $warranty_duration, $terms]);
                    }
                    
                    // Log
                    $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', 'Datos de garantía actualizados', ?, ?)");
                    $stmtHist->execute([$order_id, $_SESSION['user_id'], get_local_datetime()]);
                } else {
                    // Standard service Update log...
                     $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'updated', 'Orden actualizada', ?, ?)");
                     $stmtHist->execute([$order_id, $_SESSION['user_id'], get_local_datetime()]);
                }
                
                $pdo->commit();
                $pdo->commit();
                
                if ($is_warranty_mode) {
                     // For warranty, stay on page
                     $success = "Datos de garantía actualizados correctamente.";
                } else {
                     // For service, redirect to print entry
                     header("Location: print_entry.php?id=" . $order_id);
                     exit;
                }

            } else {
                // INSERT NEW
                // First, check if equipment with this Serial Number already exists
                $stmtEqCheck = $pdo->prepare("SELECT id, client_id FROM equipments WHERE serial_number = ? LIMIT 1");
                $stmtEqCheck->execute([$serial_number]);
                $existing_eq = $stmtEqCheck->fetch();

                if ($existing_eq) {
                    $equipment_id = $existing_eq['id'];
                    $is_warranty_action = ($is_warranty_mode || $service_type === 'warranty');
                    
                    if (empty($existing_eq['client_id'])) {
                        $stmtEqUpd = $pdo->prepare("UPDATE equipments SET client_id = ?, brand = ?, model = ?, submodel = ?, type = ? WHERE id = ?");
                        $stmtEqUpd->execute([$client_id, $brand, $model, $submodel, $type, $equipment_id]);
                    } else {
                        // Standard repair on existing equipment: preserve owner, just update details
                        $stmtEqUpd = $pdo->prepare("UPDATE equipments SET brand = ?, model = ?, submodel = ?, type = ? WHERE id = ?");
                        $stmtEqUpd->execute([$brand, $model, $submodel, $type, $equipment_id]);
                    }
                } else {
                    // Truly new equipment
                    $stmtEq = $pdo->prepare("INSERT INTO equipments (client_id, brand, model, submodel, serial_number, type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtEq->execute([$client_id, $brand, $model, $submodel, $serial_number, $type, get_local_datetime()]);
                    $equipment_id = $pdo->lastInsertId();
                }
                
                $main_ref = $is_warranty_mode ? $sales_invoice : $invoice_number;

                // SNAPSHOT SIGNATURE: Fetch current user's signature path
                $stmtSig = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
                $stmtSig->execute([$_SESSION['user_id']]);
                $currentUserSig = $stmtSig->fetchColumn();

                $stmtOrder = $pdo->prepare("INSERT INTO service_orders (equipment_id, client_id, owner_name, invoice_number, service_type, status, problem_reported, accessories_received, entry_notes, entry_date, entry_signature_path, created_at) VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?, ?, ?, ?)");
                $now = get_local_datetime();
                $stmtOrder->execute([$equipment_id, $client_id, $owner_name, $main_ref, $service_type, $problem, $accessories, $notes, $now, $currentUserSig, $now]);
                $order_id = $pdo->lastInsertId();

                if ($is_warranty_mode) {
                    $stmtW = $pdo->prepare("INSERT INTO warranties (service_order_id, equipment_id, product_code, sales_invoice_number, master_entry_invoice, master_entry_date, supplier_name, notes, status, end_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
                    $stmtW->execute([$order_id, $equipment_id, $product_code, $sales_invoice, $master_invoice, $master_date, $supplier, $notes, $warranty_end_date, get_local_datetime()]);
                    
                    $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'received', 'Garantía Registrada', ?, ?)");
                    $stmtHist->execute([$order_id, $_SESSION['user_id'], get_local_datetime()]);
                    $success = "Garantía registrada correctamente.";
                } else {
                    $stmtHist = $pdo->prepare("INSERT INTO service_order_history (service_order_id, action, notes, user_id, created_at) VALUES (?, 'received', 'Equipo ingresado al taller', ?, ?)");
                    $stmtHist->execute([$order_id, $_SESSION['user_id'], get_local_datetime()]);
                    $success = "El equipo ha sido ingresado correctamente (Orden #$order_id).";
                }

                $pdo->commit();
                
                // Redirect logic
                if ($is_warranty_mode) {
                    // Stay on page with success message
                    // We already set $success variable which will be displayed
                } else {
                    // For standard service, redirect to Print Entry
                    header("Location: print_entry.php?id=" . $order_id);
                    exit;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
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
                <div class="step-label">Detalles</div>
            </div>
        </div>

        <form method="POST" id="entryForm" action="entry.php?<?php echo http_build_query($_GET); ?>">
            <?php if($edit_order): ?>
                <input type="hidden" name="order_id" value="<?php echo $edit_order['id']; ?>">
            <?php endif; ?>
            
            <input type="hidden" name="type" value="<?php echo $edit_order ? $edit_order['type'] : ''; // Default or hidden handler ?>"> 

            <?php if ($is_warranty_mode): ?>
                <!-- STEP 1: CLIENT & BASIC -->
                <div class="step-container active" id="step-1">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-user"></i> Datos del Cliente y Factura
                        </div>
                        
                        <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group" style="position: relative;">
                                <label class="form-label">Cliente *</label>
                                <input type="hidden" name="client_id" id="client_id_hidden_wry" value="<?php echo $edit_order ? $edit_order['client_id'] : ''; ?>">
                                <input type="text" name="client_name_input" id="client_name_input_wry" class="form-control" placeholder="Nombre (Búsqueda inteligente)" required value="<?php echo $edit_order ? htmlspecialchars($edit_order['client_name']) : ''; ?>" autocomplete="off">
                                <div class="search-results" id="client_autocomplete_results_wry" style="width: 100%;"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Factura Venta</label>
                                <input type="text" name="sales_invoice_number" class="form-control" placeholder="No. Factura" value="<?php echo $edit_order['sales_invoice_number'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Proveedor</label>
                                <input type="text" name="supplier_name" class="form-control" placeholder="Proveedor Original" required value="<?php echo $edit_order['supplier_name'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Fecha de Registro</label>
                                <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly style="background: var(--bg-body);">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: EQUIPMENT & MASTER -->
                <div class="step-container" id="step-2">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-desktop"></i> Identificación del Equipo
                        </div>
                        
                        <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group">
                                <label class="form-label">Serie (S/N) *</label>
                                <input type="text" name="serial_number" id="serial_number_warranty" class="form-control" placeholder="S/N" required value="<?php echo $edit_order['serial_number'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Código de Producto *</label>
                                <input type="text" name="product_code" class="form-control" placeholder="Cód. Producto" required value="<?php echo $edit_order['product_code'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Marca *</label>
                                <input type="text" name="brand" class="form-control" placeholder="Ej. HP" required value="<?php echo $edit_order['brand'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Modelo *</label>
                                <input type="text" name="model" class="form-control" placeholder="Ej. Pavilion 15" required value="<?php echo $edit_order['model'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Submodelo</label>
                                <input type="text" name="submodel" class="form-control" placeholder="Ej. cx0001la" value="<?php echo $edit_order['submodel'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tipo</label>
                                <input list="type-options" name="type" class="form-control" value="<?php echo $edit_order ? htmlspecialchars($edit_order['type']) : ''; ?>" placeholder="Laptop, PC, etc.">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-database"></i> Datos Master
                        </div>
                        <div class="modern-grid">
                            <div class="form-group">
                                <label class="form-label">Fact. Ingreso Master *</label>
                                <input type="text" name="master_entry_invoice" class="form-control" required value="<?php echo $edit_order['master_entry_invoice'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Fecha Ingreso Master *</label>
                                <input type="date" name="master_entry_date" class="form-control" required value="<?php echo $edit_order['master_entry_date'] ?? ''; ?>" style="color-scheme: dark;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: WARRANTY TERMS -->
                <div class="step-container" id="step-3">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-calendar-check"></i> Términos de Garantía
                        </div>
                        
                        <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group">
                                <label class="form-label">Tiempo de Garantía</label>
                                <div class="input-group" style="display: flex; align-items: stretch; gap: 0;">
                                    <input type="number" name="warranty_duration" id="warranty_duration" class="form-control" placeholder="Cant." min="0" value="0" style="border-top-right-radius: 0; border-bottom-right-radius: 0; flex: 1; border-right: none; margin-right: -1px; z-index: 2;">
                                    <select name="warranty_period" id="warranty_period" class="form-control" style="width: auto; flex: 0 0 130px; border-top-left-radius: 0; border-bottom-left-radius: 0; background-color: var(--bg-card); border-left: 1px solid var(--border-color); z-index: 1;">
                                        <option value="days">Días</option>
                                        <option value="weeks">Semanas</option>
                                        <option value="months" selected>Meses</option>
                                        <option value="years">Años</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Fecha Vencimiento</label>
                                <div class="input-group">
                                    <input type="date" name="warranty_end_date" id="warranty_end_date" class="form-control" readonly style="background-color: var(--bg-body); cursor: not-allowed; font-weight: 600; color: var(--primary-500); color-scheme: dark;">
                                    <i class="ph ph-calendar-check input-icon" style="color: var(--primary-500);"></i>
                                </div>
                            </div>

                            <div class="form-group col-span-2">
                                <label class="form-label">Notas Adicionales</label>
                                <textarea name="entry_notes" class="form-control" rows="3" placeholder="Información extra sobre la garantía..."><?php echo $edit_order['entry_notes'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
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

                <!-- STEP 2: EQUIPMENT DATA -->
                <div class="step-container" id="step-2">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-desktop"></i> Datos del Equipo
                        </div>
                        
                        <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group">
                                <label class="form-label">Serie (S/N) *</label>
                                <div style="display: flex; align-items: center; gap: 0;">
                                    <div class="input-group" style="flex: 1;">
                                        <input type="text" name="serial_number" id="serial_number_std" class="form-control" required value="<?php echo $edit_order['serial_number'] ?? ''; ?>" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                    </div>
                                    <button type="button" class="btn-verify-serial" data-target="#serial_number_std" title="Verificar Garantía" style="border: 1px solid var(--border-color); border-left: none; background: var(--bg-card); cursor: pointer; color: var(--primary-500); display: flex; align-items: center; padding: 0.625rem 1rem; border-top-right-radius: 6px; border-bottom-right-radius: 6px; height: 100%;">
                                        <i class="ph ph-magnifying-glass"></i>
                                    </button>
                                </div>
                                <div class="warranty-status-msg" style="font-size:0.85rem; margin-top:0.4rem;"></div>
                            </div>

                             <div class="form-group">
                                <label class="form-label">Cliente del Equipo</label>
                                <input type="text" id="equipment_client_display" class="form-control" readonly placeholder="Se rrellenará solo" value="<?php echo $edit_order['client_name_display'] ?? ''; ?>" style="background: var(--bg-body);">
                            </div>

                             <div class="form-group">
                                <label class="form-label">Dueño / Propietario</label>
                                <div class="input-group">
                                    <input type="text" name="owner_name" id="owner_name_std" class="form-control" placeholder="Si es diferente al cliente" value="<?php echo $edit_order['owner_name'] ?? ''; ?>">
                                    <i class="ph ph-user-circle input-icon"></i>
                                </div>
                            </div>

                             <div class="form-group">
                                <label class="form-label">Marca *</label>
                                <input type="text" name="brand" class="form-control" required value="<?php echo $edit_order['brand'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Modelo *</label>
                                <input type="text" name="model" class="form-control" required value="<?php echo $edit_order['model'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tipo *</label>
                                <input list="type-options" name="type" class="form-control" required value="<?php echo $edit_order ? htmlspecialchars($edit_order['type']) : ''; ?>">
                            </div>
                            
                             <div class="form-group col-span-2">
                                 <label class="form-label">Accesorios Recibidos *</label>
                                 <input type="text" name="accessories" class="form-control" placeholder="Cargador, cables, etc." required value="<?php echo $edit_order['accessories_received'] ?? ''; ?>">
                             </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: SERVICE DETAILS -->
                <div class="step-container" id="step-3">
                    <div class="form-section">
                        <div class="form-section-header">
                            <i class="ph ph-wrench"></i> Detalles del Servicio
                        </div>
                        
                         <div class="modern-grid" style="grid-template-columns: repeat(2, 1fr);">
                             <div class="form-group col-span-2">
                                <label class="form-label">Tipo de Ingreso *</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <label class="selection-card" id="card-service" style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius); cursor: pointer; transition: all 0.2s; background: var(--bg-body);">
                                        <input type="radio" name="service_type" value="service" required style="display: none;">
                                        <i class="ph ph-wrench" style="font-size: 1.25rem;"></i>
                                        <span style="font-weight: 500;">Servicio / Reparación</span>
                                    </label>

                                    <label class="selection-card" id="card-warranty" style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; padding: 1rem; border: 1px solid var(--border-color); border-radius: var(--radius); cursor: pointer; transition: all 0.2s; background: var(--bg-body);">
                                        <input type="radio" name="service_type" value="warranty" required style="display: none;">
                                        <i class="ph ph-shield-check" style="font-size: 1.25rem;"></i>
                                        <span style="font-weight: 500;">Garantía</span>
                                    </label>
                                </div>
                             </div>
                             
                              <div class="form-group col-span-2">
                                 <label class="form-label">Problema Reportado *</label>
                                 <textarea name="problem_reported" class="form-control" rows="3" placeholder="Describe la falla..." required><?php echo $edit_order['problem_reported'] ?? ''; ?></textarea>
                             </div>
                            
                             <div class="form-group">
                                <label class="form-label">Factura (Opcional)</label>
                                <input type="text" name="invoice_number" class="form-control" value="<?php echo $edit_order['invoice_number'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group col-span-2">
                                <label class="form-label">Observaciones de Ingreso</label>
                                <textarea name="entry_notes" class="form-control" rows="2" placeholder="Notas sobre el estado físico..."><?php echo $edit_order['entry_notes'] ?? ''; ?></textarea>
                            </div>
                         </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- NAVIGATION BUTTONS -->
            <div class="wizard-buttons">
                <button type="button" id="prevBtn" onclick="prevStep()" class="btn btn-secondary" style="display: none; padding: 0.75rem 1.5rem;">
                    <i class="ph ph-arrow-left"></i> Anterior
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" id="nextBtn" onclick="nextStep()" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                    Siguiente <i class="ph ph-arrow-right"></i>
                </button>
                <div id="submitBtnContainer" style="display: none;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; background: var(--success); border-color: var(--success);">
                        <i class="ph ph-floppy-disk"></i> <?php echo $edit_order ? 'Guardar Cambios' : 'Finalizar Registro'; ?>
                    </button>
                </div>
            </div>

            <script>
                let currentStep = 1;

                function showStep(n) {
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
                        const percent = ((n - 1) / (indicators.length - 1)) * 100;
                        progressLine.style.width = `calc(${percent}% * 0.68)`; // 0.68 matches the 16% left/right gap (100 - 32 = 68)
                    }

                    // Update buttons
                    document.getElementById('prevBtn').style.display = (n === 1) ? 'none' : 'inline-flex';
                    
                    if (n === 3) {
                        document.getElementById('nextBtn').style.display = 'none';
                        document.getElementById('submitBtnContainer').style.display = 'block';
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

                    // Explicit validation for Client Step (Step 1)
                    if (currentStep === 1) {
                        const cid = document.getElementById('client_id_hidden_std') || document.getElementById('client_id_hidden_wry');
                        if (cid && !cid.value) {
                            valid = false;
                            const nameInp = document.getElementById('client_name_input_std') || document.getElementById('client_name_input_wry');
                            if(nameInp) nameInp.classList.add('is-invalid');
                        }
                    }

                    if (valid) {
                        currentStep++;
                        showStep(currentStep);
                    } else {
                        // Optional: Notification or feedback
                        const firstInvalid = currentStepContainer.querySelector('.is-invalid');
                        if (firstInvalid) firstInvalid.focus();
                    }
                }

                function prevStep() {
                    currentStep--;
                    showStep(currentStep);
                }

                // Selection Card Logic
                document.querySelectorAll('input[name="service_type"]').forEach(r => {
                    r.addEventListener('change', e => {
                        document.querySelectorAll('.selection-card').forEach(c => {
                             c.style.borderColor = 'var(--border-color)';
                             c.style.backgroundColor = 'var(--bg-body)';
                             c.querySelector('i').style.color = 'inherit';
                        });
                        if(r.checked) {
                            const card = r.closest('.selection-card');
                            card.style.borderColor = 'var(--primary-500)';
                            card.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
                            card.querySelector('i').style.color = 'var(--primary-500)';
                        }
                    });
                    if(r.checked) r.dispatchEvent(new Event('change'));
                });
                showStep(currentStep);
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

                                // Forced Selection: Clear if no ID on blur
                                nameInput.addEventListener('blur', function() {
                                    setTimeout(() => {
                                        if(!idInput.value) {
                                            nameInput.value = '';
                                            if(taxInput) taxInput.value = '';
                                            if(phoneInput) phoneInput.value = '';
                                            if(emailInput) emailInput.value = '';
                                            if(addressInput) addressInput.value = '';
                                        }
                                    }, 200);
                                });
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
