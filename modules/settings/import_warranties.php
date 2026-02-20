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
    $limit = 500; // Chunk size
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

    // Skip to offset
    $headers = fgetcsv($handle); // Read headers
    for ($i = 0; $i < $offset; $i++) {
        if (fgetcsv($handle) === false) break;
    }

    $processed = 0;
    $errors = [];

    // Map Headers (Case Insensitive)
    $header_map = [];
    foreach($headers as $idx => $h) {
        $header_map[trim($h)] = $idx;
    }

    $count = 0;
    while (($row = fgetcsv($handle)) !== false && $count < $limit) {
        $count++;
        
        $data = [];
        foreach($header_map as $key => $idx) {
            $data[$key] = $row[$idx] ?? '';
        }

        try {
            // 1. Duplicate Handling (Check if Serial + Product Code + Invoice already exists)
            $serial = trim($data['Serie'] ?? '');
            $product_code = trim($data['Codigo'] ?? '');
            $sales_invoice = trim($data['FACTURA DE VENTA'] ?? '');
            
            if (!empty($serial) && !empty($product_code)) {
                $stmtDup = $pdo->prepare("
                    SELECT w.id 
                    FROM warranties w
                    JOIN equipments e ON w.equipment_id = e.id
                    WHERE e.serial_number = ? AND w.product_code = ? AND w.sales_invoice_number = ?
                    LIMIT 1
                ");
                $stmtDup->execute([$serial, $product_code, $sales_invoice]);
                if ($stmtDup->fetch()) {
                    $processed++; // Treat as processed (skipped)
                    continue; 
                }
            }

            $pdo->beginTransaction();

            // 2. Client Handling
            $client_name = trim($data['CLIENTE'] ?? '');
            if (empty($client_name)) {
                throw new Exception("Nombre de cliente vacío en fila " . ($offset + $count));
            }

            $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
            $stmt->execute([$client_name]);
            $client_id = $stmt->fetchColumn();

            if (!$client_id) {
                $stmt = $pdo->prepare("INSERT INTO clients (name, created_at) VALUES (?, ?)");
                $stmt->execute([$client_name, get_local_datetime()]);
                $client_id = $pdo->lastInsertId();
            }

            // 2. Equipment Handling
            $serial = trim($data['Serie'] ?? '');
            $desc = trim($data['Descripcion'] ?? '');
            
            // Split description: Marca Modelo Submodelo
            $parts = explode(' ', $desc);
            $brand = !empty($parts[0]) ? $parts[0] : 'Genérica';
            $model = count($parts) > 1 ? $parts[1] : 'Modelo';
            $submodel = count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : '';

            if (empty($serial)) {
                throw new Exception("Serie vacía para el cliente $client_name");
            }

            $stmt = $pdo->prepare("SELECT id FROM equipments WHERE serial_number = ? LIMIT 1");
            $stmt->execute([$serial]);
            $equipment_id = $stmt->fetchColumn();

            if (!$equipment_id) {
                $stmt = $pdo->prepare("INSERT INTO equipments (client_id, brand, model, submodel, serial_number, type, created_at) VALUES (?, ?, ?, ?, ?, 'PC', ?)");
                $stmt->execute([$client_id, $brand, $model, $submodel, $serial, get_local_datetime()]);
                $equipment_id = $pdo->lastInsertId();
            } else {
                // Update submodel if it was empty
                $stmt = $pdo->prepare("UPDATE equipments SET submodel = IF(submodel IS NULL OR submodel = '', ?, submodel) WHERE id = ?");
                $stmt->execute([$submodel, $equipment_id]);
            }

            // 3. Service Order Handling
            $entry_date_raw = trim($data['FECHA'] ?? '');
            
            // Robust date parsing (Handles DD/MM/YYYY and YYYY-MM-DD)
            $parse_date = function($d) {
                if (empty($d)) return null;
                $d = str_replace('-', '/', $d);
                $p = explode('/', $d);
                if (count($p) == 3 && strlen($p[0]) <= 2 && strlen($p[2]) == 4) {
                    return "{$p[2]}-{$p[1]}-{$p[0]}"; // DD/MM/YYYY -> YYYY-MM-DD
                }
                $ts = strtotime($d);
                return $ts ? date('Y-m-d', $ts) : null;
            };

            $entry_date_base = $parse_date($entry_date_raw) ?: date('Y-m-d');
            $entry_date = $entry_date_base . ' ' . date('H:i:s');
            
            $sales_invoice = trim($data['FACTURA DE VENTA'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO service_orders (equipment_id, client_id, invoice_number, service_type, status, problem_reported, entry_date, created_at) VALUES (?, ?, ?, 'warranty', 'received', 'Garantía Registrada', ?, ?)");
            $stmt->execute([$equipment_id, $client_id, $sales_invoice, $entry_date, get_local_datetime()]);
            $order_id = $pdo->lastInsertId();

            // 4. Warranty Handling
            $product_code = trim($data['Codigo'] ?? '');
            $master_invoice = trim($data['Factura'] ?? '');
            $master_date_raw = trim($data['Fecha2'] ?? '');
            $master_date = $parse_date($master_date_raw);
            $supplier = trim($data['Proveedor'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO warranties (service_order_id, equipment_id, product_code, sales_invoice_number, master_entry_invoice, master_entry_date, supplier_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([$order_id, $equipment_id, $product_code, $sales_invoice, $master_invoice, $master_date, $supplier, get_local_datetime()]);

            $pdo->commit();
            $processed++;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Fila " . ($offset + $count) . ": " . $e->getMessage();
        }
    }

    fclose($handle);

    $is_done = ($count < $limit);
    if ($is_done && file_exists($file_path)) {
        // unlink($file_path); // Optionally delete file when done
    }

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
        
        // Peek at count
        $line_count = 0;
        $handle = fopen('../../temp_import.csv', 'r');
        while (fgetcsv($handle) !== false) $line_count++;
        fclose($handle);
        $total_records = $line_count - 1; // Minus headers
    } else {
        $error = "Error al subir el archivo.";
    }
}

$page_title = "Importación Masiva de Garantías";
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="animate-enter" style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1><i class="ph ph-file-csv" style="color: var(--primary-500);"></i> Importación Masiva</h1>
        <p class="text-muted">Sube tu archivo CSV con el formato especificado para registrar garantías en lote.</p>
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
                    if(confirm("¿Deseas ir a ver los registros de garantía ahora?")) {
                        window.location.href = '../warranties/index.php';
                    }
                }, 1000);
            }
        </script>

    <?php else: ?>
        <div class="card">
            <form action="import_warranties.php" method="POST" enctype="multipart/form-data">
                <div class="form-group" style="padding: 2rem; border: 2px dashed var(--border-color); border-radius: 12px; text-align: center; background: rgba(255,255,255,0.02);">
                    <i class="ph ph-cloud-arrow-up" style="font-size: 3rem; color: var(--primary-500); margin-bottom: 1rem; display: block;"></i>
                    <label class="form-label" style="font-size: 1.1rem;">Selecciona tu archivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required style="display: block; margin: 1.5rem auto;">
                    <p class="text-muted" style="font-size: 0.85rem;">Asegúrate de que las columnas coincidan: Codigo, Descripcion, FECHA, CLIENTE, FACTURA DE VENTA, Serie, Factura, Fecha2, Proveedor.</p>
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
            <li>La **Descripción** se dividirá: 1ª palabra (Marca), 2ª palabra (Modelo), resto (Submodelo).</li>
            <li>Los campos de fecha (FECHA, Fecha2) deben tener un formato válido (ej. 2026-02-20 o 20/02/2026).</li>
        </ul>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
