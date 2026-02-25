<?php
// search_related.php - Search related tables for lost order data
require_once 'config/db.php';
echo "<h1>Búsqueda en Tablas Relacionadas</h1>";
try {
    // Search equipment linked to clients
    $names = ['ARMANDO', 'JOSE', 'HUGO', 'BALLADARES'];
    foreach ($names as $name) {
        echo "<h3>Registros de equipo para: $name</h3>";
        $stmt = $pdo->prepare("SELECT e.*, c.name as client_name FROM equipment e 
                               LEFT JOIN clients c ON e.client_id = c.id 
                               WHERE UPPER(c.name) LIKE ?
                               ORDER BY e.id DESC LIMIT 10");
        $stmt->execute(["%" . $name . "%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            echo "<table border='1' cellpadding='5'>
                  <tr style='background:#ddd'><th>ID Equipo</th><th>Client ID</th><th>Cliente</th><th>Marca</th><th>Modelo</th><th>Serie</th></tr>";
            foreach ($rows as $r) {
                echo "<tr>
                        <td>{$r['id']}</td>
                        <td>{$r['client_id']}</td>
                        <td>{$r['client_name']}</td>
                        <td>{$r['brand']}</td>
                        <td>{$r['model']}</td>
                        <td>{$r['serial_number']}</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No se encontraron equipos para '$name'</p>";
        }
    }
    
    // Also show full client data
    echo "<h2>Clientes encontrados para Armando, Jose, Hugo</h2>";
    $stmt = $pdo->query("SELECT id, name, phone, email FROM clients WHERE UPPER(name) LIKE '%ARMANDO%' OR UPPER(name) LIKE '%JOSE MANUEL%' OR UPPER(name) LIKE '%HUGO%' ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>
          <tr style='background:#ddd'><th>Client ID</th><th>Nombre</th><th>Teléfono</th><th>Email</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['id']}</td><td>{$r['name']}</td><td>{$r['phone']}</td><td>{$r['email']}</td></tr>";
    }
    echo "</table>";
    
    // Check warranty table for any linked records
    echo "<h2>Garantías existentes</h2>";
    $stmt = $pdo->query("SELECT w.*, c.name as client_name FROM warranties w LEFT JOIN clients c ON w.client_id = c.id ORDER BY w.id DESC LIMIT 20");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Total garantías: " . count($rows) . "</p>";
    echo "<table border='1' cellpadding='5'>
          <tr style='background:#ddd'><th>ID</th><th>Service Order ID</th><th>Client ID</th><th>Cliente</th><th>Estado</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>
                <td>{$r['id']}</td>
                <td>{$r['service_order_id']}</td>
                <td>{$r['client_id']}</td>
                <td>{$r['client_name']}</td>
                <td>{$r['status']}</td>
              </tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
