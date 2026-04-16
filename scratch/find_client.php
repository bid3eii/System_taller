<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT id FROM clients WHERE name = 'Bodega - Inventario'");
echo "ID: " . ($stmt->fetchColumn() ?: 'Not found');
