<?php
// check_prod_data.php
// Access: http://omnidesk.free.nf/check_prod_data.php
require_once 'config/db.php';

echo "<h1>Inspección de IDs 3 y 4</h1>";
try {
    $stmt = $pdo->query("SELECT id, problem_reported, entry_date FROM service_orders WHERE id IN (3, 4)");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "No se encontraron los IDs 3 y 4.";
    } else {
        echo "<table border='1'><tr><th>ID</th><th>Problema</th><th>Fecha</th></tr>";
        foreach ($rows as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['problem_reported']}</td><td>{$row['entry_date']}</td></tr>";
        }
        echo "</table>";
    }
    echo "<p>Si estos registros son de prueba, podemos moverlos para liberar los números #3 y #4.</p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
