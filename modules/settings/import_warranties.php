<?php
// modules/settings/import_warranties.php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    die("Acceso denegado.");
}

$error = '';
$success = '';

// Handle AJAX Process Chunk
if (isset($_GET['action']) && $_GET['action'] === 'process_chunk') {
    header('Content-Type: application/json');
    $offset = intval($_POST['offset'] ?? 0);
    $limit = 200; // Sync with UI recommendation for better performance on free hosting
    $file_path = '../../temp_import.csv';

    if (!file_exists($file_path)) {
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado.']);
        exit;
    }

    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'No se pudo abrir el archivo.']);
        exit;
    }

    // 1. Get Header and Detect Delimiter (only on first call or just once)
    $header_line = fgets($handle);
    if (!$header_line) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Archivo vacío.']);
        exit;
    }

    // Auto-detect delimiter
    $tab_count = substr_count($header_line, "\t");
    $semi_count = substr_count($header_line, ';');
    $comma_count = substr_count($header_line, ',');
    $delimiter = ($tab_count >= $semi_count && $tab_count >= $comma_count && $tab_count > 0) ? "\t" : ($semi_count > $comma_count ? ';' : ',');

    // Parse headers
    $raw_headers = explode($delimiter, trim($header_line, "\r\n"));
    $headers = [];
    foreach ($raw_headers as $h) {
        $h = trim($h, " \t\n\r\0\x0B\"");
        $h = preg_replace('/^\x{FEFF}/u', '', $h); // Remove BOM
        $headers[] = $h;
    }
    $header_map = array_flip($headers);

    // 2. Skip to offset (considering we already read the header)
    $current_line_idx = 0;
    while ($current_line_idx < $offset && fgets($handle) !== false) {
        $current_line_idx++;
    }

    // 3. Process Chunk in a SINGLE TRANSACTION
    $processed = 0;
    $errors = [];
    $count = 0;
    
    try {
        $pdo->beginTransaction();
        
        while ($count < $limit && ($line = fgets($handle)) !== false) {
            $line = trim($line, "\r\n");
            if ($line === '') continue;
            
            $count++;
            $cols = explode($delimiter, $line);
            foreach ($cols as &$val) $val = trim($val, " \t\n\r\0\x0B\"");
            unset($val);

            $data = [];
            foreach ($header_map as $key => $idx) {
                $data[$key] = $cols[$idx] ?? '';
            }

            // --- Business Logic ---
            $serial = trim($data['Serie'] ?? '');
            $product_code = trim($data['Codigo'] ?? '');
            $sales_invoice = trim($data['FACTURA DE VENTA'] ?? '');
            
            if (empty($serial)) {
                $errors[] = "Fila " . ($offset + $count) . ": Serie vacía.";
                continue;
            }

            // Duplicate Check
            $stmtDup = $pdo->prepare("SELECT w.id FROM warranties w JOIN equipments e ON w.equipment_id = e.id WHERE e.serial_number = ? AND w.product_code = ? AND w.sales_invoice_number = ? LIMIT 1");
            $stmtDup->execute([$serial, $product_code, $sales_invoice]);
            if ($stmtDup->fetch()) {
                $processed++; 
                continue; 
            }

            // Client
            $client_name = trim($data['CLIENTE'] ?? '');
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
            $stmt->execute([$client_name]);
            $client_id = $stmt->fetchColumn();

            if (!$client_id) {
                $c_phone = trim($data['CONTACTO'] ?? '');
                $c_tax = trim($data['RUC'] ?? '');
                $c_address = trim($data['DIRECCIÓN'] ?? $data['DIRECCION'] ?? '');
                $is_tp = empty($client_name) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, tax_id, address, is_third_party, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_name, $c_phone, $c_tax, $c_address, $is_tp, get_local_datetime()]);
                $client_id = $pdo->lastInsertId();
            } else {
                $c_phone = trim($data['CONTACTO'] ?? '');
                $c_tax = trim($data['RUC'] ?? '');
                $c_address = trim($data['DIRECCIÓN'] ?? $data['DIRECCION'] ?? '');
                $pdo->prepare("UPDATE clients SET phone = IF(phone IS NULL OR phone = '', ?, phone), tax_id = IF(tax_id IS NULL OR tax_id = '', ?, tax_id), address = IF(address IS NULL OR address = '', ?, address), is_third_party = 0 WHERE id = ?")->execute([$c_phone, $c_tax, $c_address, $client_id]);
            }

            // Equipment
            $desc = trim($data['Equipo'] ?? $data['Descripcion'] ?? '');
            $brand = !empty($desc) ? $desc : 'Desconocido';
            $stmt = $pdo->prepare("SELECT id FROM equipments WHERE serial_number = ? LIMIT 1");
            $stmt->execute([$serial]);
            $equipment_id = $stmt->fetchColumn();

            if (!$equipment_id) {
                $stmt = $pdo->prepare("INSERT INTO equipments (client_id, brand, model, submodel, serial_number, type, created_at) VALUES (?, ?, ?, ?, ?, 'PC', ?)");
                $stmt->execute([$client_id, $brand, '', '', $serial, get_local_datetime()]);
                $equipment_id = $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare("UPDATE equipments SET brand = IF(brand IS NULL OR brand = '', ?, brand) WHERE id = ?");
                $stmt->execute([$brand, $equipment_id]);
            }

            // Dates
            $parse_date = function($d) {
                if (empty($d)) return null;
                $d = str_replace('-', '/', $d);
                $p = explode('/', $d);
                if (count($p) == 3 && strlen($p[0]) <= 2 && strlen($p[2]) == 4) return "{$p[2]}-{$p[1]}-{$p[0]}";
                $ts = strtotime($d);
                return $ts ? date('Y-m-d', $ts) : null;
            };

            $entry_date_base = $parse_date(trim($data['FECHA'] ?? '')) ?: date('Y-m-d');
            $entry_date = $entry_date_base . ' ' . date('H:i:s');
            
            // Service Order
            $stmt = $pdo->prepare("INSERT INTO service_orders (equipment_id, client_id, invoice_number, service_type, status, problem_reported, entry_date, created_at) VALUES (?, ?, ?, 'warranty', 'received', 'Garantía Registrada', ?, ?)");
            $stmt->execute([$equipment_id, $client_id, $sales_invoice, $entry_date, get_local_datetime()]);
            $order_id = $pdo->lastInsertId();

            // Warranty
            $master_invoice = trim($data['Factura Proveedor'] ?? $data['Factura'] ?? '');
            $master_date_raw = trim($data['Fecha Proveedor'] ?? $data['Fecha2'] ?? '');
            $master_date = $parse_date($master_date_raw);
            $supplier = trim($data['Proveedor'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO warranties (service_order_id, equipment_id, product_code, sales_invoice_number, master_entry_invoice, master_entry_date, supplier_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([$order_id, $equipment_id, $product_code, $sales_invoice, $master_invoice, $master_date, $supplier, get_local_datetime()]);

            $processed++;
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error en lote: ' . $e->getMessage()]);
        exit;
    }

    $is_done = (($line = fgets($handle)) === false);
    fclose($handle);

    echo json_encode([
        'success' => true, 
        'processed' => $processed, 
        'errors' => $errors, 
        'done' => $is_done,
        'next_offset' => $offset + $count
    ]);
    exit;
}
// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES['csv_file']['tmp_name'], '../../temp_import.csv');
        $success = "Archivo subido correctamente. Listo para procesar.";
        
        // Peek at count - simple line count
        $file_lines = file('../../temp_import.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total_records = count($file_lines) - 1; // Minus headers
    } else {
        $error = "Error al subir el archivo.";
    }
}

$page_title = "Importación Masiva de Bodega";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1><i class="ph ph-file-csv" style="color: var(--primary-500);"></i> Importación Masiva</h1>
        <p class="text-muted">Sube tu archivo CSV con el formato especificado para registrar equipos en lote a la bodega.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        
        <div class="card" style="margin-top: 2rem; border: 1px solid var(--primary-500); background: rgba(59, 130, 246, 0.05);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="margin: 0; color: var(--primary-400);">Registros detectados: <?php echo $total_records; ?></h3>
                    <p style="margin: 0.5rem 0 0; font-size: 0.9rem;">El proceso se realizará en lotes de 200 para evitar bloqueos del servidor.</p>
                </div>
                <button id="btnStartProcess" class="btn btn-primary" style="padding: 1rem 2rem; font-weight: 600;">
                    <i class="ph ph-play-circle"></i> Iniciar Importación
                </button>
            </div>
            
            <div id="progressContainer" style="margin-top: 1.5rem; display: none;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span id="progressText">Procesando... 0%</span>
                    <span id="processedCount">0 / <?php echo $total_records; ?></span>
                </div>
                <div style="height: 10px; background: var(--bg-body); border-radius: 5px; overflow: hidden; border: 1px solid var(--border-color);">
                    <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--primary-500), var(--primary-400)); transition: width 0.3s;"></div>
                </div>
            </div>
        </div>

        <div id="errorLog" class="card" style="margin-top: 1.5rem; display: none; border-color: var(--danger);">
            <h4 style="color: var(--danger); margin-top: 0;"><i class="ph ph-warning"></i> Errores encontrados:</h4>
            <div id="errorList" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; color: #fca5a5;"></div>
        </div>

        <script>
            let total = <?php echo $total_records; ?>;
            let currentOffset = 0;
            let totalProcessed = 0;

            document.getElementById('btnStartProcess').addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Procesando...';
                document.getElementById('progressContainer').style.display = 'block';
                processNextChunk();
            });

            function processNextChunk() {
                const formData = new FormData();
                formData.append('offset', currentOffset);

                fetch('import_warranties.php?action=process_chunk', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        totalProcessed += data.processed;
                        currentOffset = data.next_offset;
                        
                        // Show errors
                        if (data.errors.length > 0) {
                            const errorLog = document.getElementById('errorLog');
                            const errorList = document.getElementById('errorList');
                            errorLog.style.display = 'block';
                            data.errors.forEach(err => {
                                errorList.innerHTML += `<div>• ${err}</div>`;
                            });
                        }

                        // Update Progress
                        let percent = Math.min(100, Math.round((currentOffset / total) * 100));
                        document.getElementById('progressBar').style.width = percent + '%';
                        document.getElementById('progressText').innerText = `Procesando... ${percent}%`;
                        document.getElementById('processedCount').innerText = `${Math.min(currentOffset, total)} / ${total}`;

                        if (data.done || currentOffset >= total) {
                            completeImport();
                        } else {
                            processNextChunk();
                        }
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error crítico de red. El servidor respondió con error.");
                });
            }

            function completeImport() {
                const btn = document.getElementById('btnStartProcess');
                btn.innerHTML = '<i class="ph ph-check-circle"></i> Importación Completada';
                btn.style.backgroundColor = 'var(--success)';
                btn.style.borderColor = 'var(--success)';
                
                setTimeout(() => {
                    document.getElementById('importSuccessModal').style.display = 'flex';
                }, 800);
            }
        </script>

        <!-- Modal de éxito personalizado -->
        <div id="importSuccessModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); animation: fadeIn 0.3s ease;">
            <div style="background:var(--bg-card, #1a2332); border:1px solid var(--border-color, #2d3748); border-radius:16px; padding:2.5rem; max-width:420px; width:90%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.5); animation: slideUp 0.4s ease;">
                <div style="width:72px; height:72px; background:linear-gradient(135deg, #22c55e, #16a34a); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; box-shadow:0 8px 24px rgba(34,197,94,0.3);">
                    <i class="ph ph-check-fat" style="font-size:2.2rem; color:white;"></i>
                </div>
                <h3 style="margin:0 0 0.5rem; font-size:1.35rem; color:var(--text-primary, #fff);">¡Importación Exitosa!</h3>
                <p style="margin:0 0 2rem; color:var(--text-muted, #94a3b8); font-size:0.95rem; line-height:1.5;">Todos los registros han sido procesados correctamente.</p>
                <div style="display:flex; gap:0.75rem; justify-content:center;">
                    <a href="../warranties/database.php" class="btn btn-primary" style="padding:0.75rem 1.5rem; font-weight:600; border-radius:10px;">
                        <i class="ph ph-database"></i> Ver Registros
                    </a>
                    <button onclick="document.getElementById('importSuccessModal').style.display='none'" class="btn btn-secondary" style="padding:0.75rem 1.5rem; border-radius:10px;">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
        <style>
            @keyframes fadeIn { from { opacity:0 } to { opacity:1 } }
            @keyframes slideUp { from { opacity:0; transform:translateY(30px) } to { opacity:1; transform:translateY(0) } }
        </style>

    <?php else: ?>
        <div class="card">
            <form action="import_warranties.php" method="POST" enctype="multipart/form-data">
                <div class="form-group" style="padding: 2rem; border: 2px dashed var(--border-color); border-radius: 12px; text-align: center; background: rgba(255,255,255,0.02);">
                    <i class="ph ph-cloud-arrow-up" style="font-size: 3rem; color: var(--primary-500); margin-bottom: 1rem; display: block;"></i>
                    <label class="form-label" style="font-size: 1.1rem;">Selecciona tu archivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required style="display: block; margin: 1.5rem auto;">
                    <p class="text-muted" style="font-size: 0.85rem;">Columnas esperadas: Codigo, Descripcion, FECHA, CLIENTE, CONTACTO, RUC, DIRECCIÓN, FACTURA DE VENTA, Serie, Factura Proveedor, Fecha Proveedor, Proveedor.</p>
                </div>
                <div style="margin-top: 1.5rem; text-align: right;">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-upload-simple"></i> Subir y Analizar
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-top: 3rem; background: transparent; border-style: dotted;">
        <h4 style="margin-top: 0;"><i class="ph ph-info"></i> Consideraciones:</h4>
        <ul style="font-size: 0.9rem; line-height: 1.6; color: var(--text-muted);">
            <li>El sistema buscará clientes por nombre para no duplicarlos.</li>
            <li>El sistema buscará equipos por número de serie para no duplicarlos.</li>
            <li>La columna **Equipo** (o Descripcion) se guardará de forma unificada.</li>
            <li>Los campos de fecha (FECHA, Fecha2) deben tener un formato válido (ej. 2026-02-20 o 20/02/2026).</li>
        </ul>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
