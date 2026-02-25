<?php
/**
 * Manual Restoration Script V2 - FK aware
 */
require_once 'config/db.php';
echo "<h1>Restauración Manual de Datos Perdidos (V2)</h1>";

try {
    // Step 1: List existing equipment for our clients
    echo "<h2>Equipos disponibles en la base de datos</h2>";
    $stmt = $pdo->query("SELECT e.id, e.client_id, c.name as client_name, e.brand, e.model, e.serial_number FROM equipments e LEFT JOIN clients c ON c.id = e.client_id WHERE UPPER(c.name) LIKE '%ARMANDO%' OR UPPER(c.name) LIKE '%BALLADARES%' OR UPPER(c.name) LIKE '%HUGO%' ORDER BY e.id DESC LIMIT 20");
    $equip_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Equipos encontrados: " . count($equip_rows) . "</p>";
    if ($equip_rows) {
        echo "<table border='1' cellpadding='5'><tr style='background:#ddd'><th>Equip ID</th><th>Client ID</th><th>Cliente</th><th>Marca</th><th>Modelo</th><th>Serie</th></tr>";
        foreach ($equip_rows as $r) {
            echo "<tr><td><b>{$r['id']}</b></td><td>{$r['client_id']}</td><td>{$r['client_name']}</td><td>{$r['brand']}</td><td>{$r['model']}</td><td>{$r['serial_number']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange'>No se encontraron equipos para estos clientes. Se usará FK disable.</p>";
    }

    // Step 2: Records to restore
    $to_restore = [
        ['display_id' => 2,    'owner_name' => 'ARMANDO QUINTANILLA',    'client_search' => 'ARMANDO',    'problem' => 'INSTALACION DE SISTEMA MICROSFT',          'entry_date' => '2026-02-20 14:42:22', 'service_type' => 'service'],
        ['display_id' => 3628, 'owner_name' => 'José Manuel Balladares', 'client_search' => 'BALLADARES', 'problem' => 'CLIENTE INDICA QUE NO RECONOCE VÍDEO.',     'entry_date' => '2026-02-25 09:16:29', 'service_type' => 'service'],
        ['display_id' => 3629, 'owner_name' => 'Hugo Sanchez',           'client_search' => 'HUGO',       'problem' => 'ERROR F02',                                 'entry_date' => '2026-02-25 13:04:09', 'service_type' => 'service'],
        ['display_id' => 3630, 'owner_name' => 'Hugo Sanchez',           'client_search' => 'HUGO',       'problem' => 'ERROR F02',                                 'entry_date' => '2026-02-25 13:07:23', 'service_type' => 'service'],
        ['display_id' => 3631, 'owner_name' => 'Hugo Sanchez',           'client_search' => 'HUGO',       'problem' => 'ERROR F02',                                 'entry_date' => '2026-02-25 14:44:04', 'service_type' => 'service'],
    ];

    echo "<h2>Insertando registros...</h2><ul>";

    // Disable FK checks so we can insert without equipment_id
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($to_restore as $rec) {
        // Skip if display_id already exists
        $exists = $pdo->prepare("SELECT id FROM service_orders WHERE display_id = ?");
        $exists->execute([$rec['display_id']]);
        if ($exists->fetch()) {
            echo "<li style='color:orange'>Omitido #{$rec['display_id']} — ya existe.</li>";
            continue;
        }

        // Find client ID
        $cs = $pdo->prepare("SELECT id FROM clients WHERE UPPER(name) LIKE ? LIMIT 1");
        $cs->execute(["%" . $rec['client_search'] . "%"]);
        $crow = $cs->fetch(PDO::FETCH_ASSOC);
        $client_id = $crow['id'] ?? null;

        // Find equipment for this client (latest one)
        $eq = $pdo->prepare("SELECT id FROM equipments WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $eq->execute([$client_id]);
        $erow = $eq->fetch(PDO::FETCH_ASSOC);
        $equipment_id = $erow['id'] ?? null;

        // Insert
        $stmt = $pdo->prepare("INSERT INTO service_orders 
            (display_id, owner_name, client_id, equipment_id, problem_reported, service_type, status, entry_date)
            VALUES (?, ?, ?, ?, ?, ?, 'received', ?)");
        $stmt->execute([
            $rec['display_id'],
            $rec['owner_name'],
            $client_id,
            $equipment_id,
            $rec['problem'],
            $rec['service_type'],
            $rec['entry_date'],
        ]);

        $new_id = $pdo->lastInsertId();
        echo "<li style='color:green'>✓ Restaurado: <b>#{$rec['display_id']}</b> → DB ID {$new_id} | {$rec['owner_name']} | equipment_id={$equipment_id}</li>";
    }
    echo "</ul>";

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Fix AUTO_INCREMENT
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 7");

    echo "<h2 style='color:green'>¡Restauración Completada!</h2>";
    echo "<p>El próximo equipo nuevo será el <b>#000007</b>.</p>";
    echo "<p><b>Nota:</b> Los campos de equipo están enlazados al último equipo registrado de cada cliente. Verifica en el sistema que sean correctos.</p>";

} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h2 style='color:red'>Error:</h2><p>" . $e->getMessage() . "</p>";
}
