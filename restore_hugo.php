<?php
/**
 * Restore Hugo's Records - V3
 * Creates placeholder equipment for Hugo and then inserts 3629, 3630, 3631
 */
require_once 'config/db.php';
echo "<h1>Restauración de Registros Hugo Sanchez (V3)</h1>";

try {
    // Find Hugo's client_id
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE UPPER(name) LIKE '%HUGO%' LIMIT 1");
    $stmt->execute();
    $hugo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hugo) {
        echo "<p style='color:red'>No se encontró el cliente Hugo Sanchez.</p>";

        // Create him if needed
        $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES ('Hugo Sanchez')");
        $stmt->execute();
        $hugo_id = $pdo->lastInsertId();
        echo "<p>Cliente Hugo Sanchez creado con ID: $hugo_id</p>";
    } else {
        $hugo_id = $hugo['id'];
        echo "<p>Hugo Sanchez encontrado — Client ID: <b>$hugo_id</b></p>";
    }

    // Check if Hugo already has equipment
    $stmt = $pdo->prepare("SELECT id, brand, model FROM equipments WHERE client_id = ? LIMIT 1");
    $stmt->execute([$hugo_id]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($eq) {
        $equipment_id = $eq['id'];
        echo "<p>Equipo existente para Hugo: ID <b>{$equipment_id}</b> — {$eq['brand']} {$eq['model']}</p>";
    } else {
        // Create a placeholder equipment entry
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $stmt = $pdo->prepare("INSERT INTO equipments (client_id, brand, model, serial_number) VALUES (?, 'Pendiente', 'Pendiente', 'N/A')");
        $stmt->execute([$hugo_id]);
        $equipment_id = $pdo->lastInsertId();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "<p style='color:orange'>Equipo placeholder creado para Hugo — ID: <b>{$equipment_id}</b>. Recuerda actualizar la marca/modelo en el sistema.</p>";
    }

    // Records to restore
    $hugo_records = [
        ['display_id' => 3629, 'entry_date' => '2026-02-25 13:04:09'],
        ['display_id' => 3630, 'entry_date' => '2026-02-25 13:07:23'],
        ['display_id' => 3631, 'entry_date' => '2026-02-25 14:44:04'],
    ];

    echo "<ul>";
    foreach ($hugo_records as $rec) {
        // Skip if already exists
        $exists = $pdo->prepare("SELECT id FROM service_orders WHERE display_id = ?");
        $exists->execute([$rec['display_id']]);
        if ($exists->fetch()) {
            echo "<li style='color:orange'>Omitido #{$rec['display_id']} — ya existe.</li>";
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO service_orders 
            (display_id, owner_name, client_id, equipment_id, problem_reported, service_type, status, entry_date)
            VALUES (?, 'Hugo Sanchez', ?, ?, 'ERROR F02', 'service', 'received', ?)");
        $stmt->execute([
            $rec['display_id'],
            $hugo_id,
            $equipment_id,
            $rec['entry_date'],
        ]);
        $new_id = $pdo->lastInsertId();
        echo "<li style='color:green'>✓ Restaurado: <b>#{$rec['display_id']}</b> → DB ID {$new_id} | Hugo Sanchez</li>";
    }
    echo "</ul>";

    // Fix AUTO_INCREMENT
    $pdo->exec("ALTER TABLE service_orders AUTO_INCREMENT = 7");

    echo "<h2 style='color:green'>¡Restauración Completada!</h2>";
    echo "<p>Verifica en el sistema que los 6 registros estén visibles: #2, #3627, #3628, #3629, #3630, #3631</p>";
    echo "<p>El próximo equipo nuevo será el <b>#000007</b>.</p>";

} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<h2 style='color:red'>Error: </h2><p>" . $e->getMessage() . "</p>";
}
