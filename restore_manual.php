<?php
/**
 * Manual Restoration Script
 * Recreates 5 lost service orders from confirmed data
 */
require_once 'config/db.php';
echo "<h1>Restauración Manual de Datos Perdidos</h1>";

try {
    // Step 1: Look up the client IDs we need
    echo "<p>Buscando clientes...</p>";

    $clients = [];
    $names_to_find = ['ARMANDO', 'JOSE MANUEL', 'BALLADARES', 'HUGO'];
    foreach ($names_to_find as $n) {
        $s = $pdo->prepare("SELECT id, name FROM clients WHERE UPPER(name) LIKE ? LIMIT 1");
        $s->execute(["%$n%"]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) $clients[$n] = $row;
    }

    // Show what was found
    echo "<h3>Clientes encontrados:</h3><ul>";
    foreach ($clients as $k => $v) echo "<li>$k => ID {$v['id']}: {$v['name']}</li>";
    echo "</ul>";

    // Get the current max ID to ensure we don't conflict
    $maxId = $pdo->query("SELECT MAX(id) FROM service_orders")->fetchColumn();
    echo "<p>Max ID actual: $maxId</p>";

    // Records to restore (from the confirmation screenshot)
    // These are what we confirmed before any deletions
    $to_restore = [
        [
            'display_id'     => 2,
            'owner_name'     => 'ARMANDO QUINTANILLA',
            'client_search'  => 'ARMANDO',
            'problem'        => 'INSTALACION DE SISTEMA MICROSFT',
            'entry_date'     => '2026-02-20 14:42:22',
            'service_type'   => 'service',
            'status'         => 'received',
        ],
        [
            'display_id'     => 3628,
            'owner_name'     => 'José Manuel Balladares',
            'client_search'  => 'BALLADARES',
            'problem'        => 'CLIENTE INDICA QUE NO RECONOCE VÍDEO.',
            'entry_date'     => '2026-02-25 09:16:29',
            'service_type'   => 'service',
            'status'         => 'received',
        ],
        [
            'display_id'     => 3629,
            'owner_name'     => 'Hugo Sanchez',
            'client_search'  => 'HUGO',
            'problem'        => 'ERROR F02',
            'entry_date'     => '2026-02-25 13:04:09',
            'service_type'   => 'service',
            'status'         => 'received',
        ],
        [
            'display_id'     => 3630,
            'owner_name'     => 'Hugo Sanchez',
            'client_search'  => 'HUGO',
            'problem'        => 'ERROR F02',
            'entry_date'     => '2026-02-25 13:07:23',
            'service_type'   => 'service',
            'status'         => 'received',
        ],
        [
            'display_id'     => 3631,
            'owner_name'     => 'Hugo Sanchez',
            'client_search'  => 'HUGO',
            'problem'        => 'ERROR F02',
            'entry_date'     => '2026-02-25 14:44:04',
            'service_type'   => 'service',
            'status'         => 'received',
        ],
    ];

    echo "<h2>Insertando registros restaurados...</h2><ul>";
    foreach ($to_restore as $rec) {
        // Check if display_id already exists
        $exists = $pdo->prepare("SELECT id FROM service_orders WHERE display_id = ?");
        $exists->execute([$rec['display_id']]);
        if ($exists->fetch()) {
            echo "<li style='color:orange'>Omitido display_id #{$rec['display_id']} - ya existe.</li>";
            continue;
        }

        // Find client ID
        $client_id = null;
        $cs = $pdo->prepare("SELECT id FROM clients WHERE UPPER(name) LIKE ? LIMIT 1");
        $cs->execute(["%" . $rec['client_search'] . "%"]);
        $crow = $cs->fetch(PDO::FETCH_ASSOC);
        if ($crow) $client_id = $crow['id'];

        // Insert
        $stmt = $pdo->prepare("INSERT INTO service_orders 
            (display_id, owner_name, client_id, problem_reported, service_type, status, entry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $rec['display_id'],
            $rec['owner_name'],
            $client_id,
            $rec['problem'],
            $rec['service_type'],
            $rec['status'],
            $rec['entry_date'],
        ]);

        $new_id = $pdo->lastInsertId();
        echo "<li style='color:green'>Restaurado: display_id #{$rec['display_id']} -> DB ID {$new_id} | {$rec['owner_name']}</li>";
    }
    echo "</ul>";

    // Fix AUTO_INCREMENT so next order is 7
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 7");

    echo "<h2 style='color:green'>¡Restauración Completada!</h2>";
    echo "<p>Verifica los datos en el sistema. Los campos de equipo y otros detalles pueden necesitar actualización manual.</p>";
    echo "<p>El próximo equipo nuevo será el <b>#000007</b>.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error:</h2><p>" . $e->getMessage() . "</p>";
}
