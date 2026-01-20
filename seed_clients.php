<?php
// seed_clients.php
require_once 'config/db.php';

$clients = [
    ['Carlos Gómez', '10203040', '5555-1234', 'carlos.gomez@mail.com', 'Av. Central 123, Ciudad'],
    ['Maria Rodriguez', '40302010', '5555-5678', 'maria.rodriguez@mail.com', 'Calle Flores 45, Ciudad'],
    ['Tecnología S.A.', '20102030401', '2222-3333', 'contacto@tecnologia.com', 'Polígono Industrial Nave 5'],
    ['Juan Perez', '11223344', '5555-9876', 'juan.perez@mail.com', 'Residencial Los Alamos casa #4'],
    ['Ana Martinez', '55667788', '5555-1111', 'ana.martinez@mail.com', 'Barrio El Centro, frente al parque'],
    ['Luis Hernandez', '99887766', '5555-2222', 'luis.h@mail.com', 'Condominio Las Palmas, Apto 3B'],
    ['Empresa XYZ', '20505050501', '2222-0000', 'admin@xyz.com', 'Centro Financiero Torre 2, Piso 10'],
    ['Sofia Lopez', '12341234', '5555-3333', 'sofia.lopez@mail.com', 'Colonia San Francisco, Pje 2'],
    ['Miguel Angel', '87654321', '5555-4444', 'miguel.angel@mail.com', 'Urbanización La Cima, Block C'],
    ['Elena Torres', '43218765', '5555-5555', 'elena.torres@mail.com', 'Boulevard Los Próceres #100']
];

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO clients (name, tax_id, phone, email, address) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($clients as $client) {
        $stmt->execute($client);
    }
    
    $pdo->commit();
    echo "<h1>Éxito</h1><p>Se han insertado 10 clientes de prueba correctamente.</p>";
    echo "<a href='modules/clients/index.php'>Ir a Clientes</a>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
