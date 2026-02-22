-- Create tools inventory table
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('available', 'assigned', 'maintenance', 'lost') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create tool assignments table
CREATE TABLE IF NOT EXISTS tool_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    assigned_to VARCHAR(255) NOT NULL,
    technician_1 VARCHAR(255),
    technician_2 VARCHAR(255),
    technician_3 VARCHAR(255),
    delivery_date DATE NOT NULL,
    return_date DATE,
    observations TEXT,
    status ENUM('pending', 'delivered', 'returned') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create tool assignment items table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS tool_assignment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    tool_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'delivered', 'returned') DEFAULT 'pending',
    delivery_confirmed BOOLEAN DEFAULT FALSE,
    return_confirmed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (assignment_id) REFERENCES tool_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample tools based on the image
INSERT INTO tools (name, description, quantity, status) VALUES
('Cargador de Baterías', 'Cargador de baterías para herramientas', 2, 'available'),
('Cizalladora', 'Cizalladora industrial', 1, 'available'),
('Estuche Milwake', 'Estuche de herramientas Milwake', 3, 'available'),
('Ponchadora de Impacto', 'Ponchadora de impacto', 1, 'available'),
('Generador de Tono', 'Generador de tono para cableado', 2, 'available'),
('Tester de Red', 'Tester de red', 2, 'available'),
('Desforrador', 'Desforrador de cables', 3, 'available'),
('Navaja Linea', 'Navaja de línea', 5, 'available'),
('Tenaza Picuda', 'Tenaza picuda', 4, 'available'),
('Alicate', 'Alicate multiuso', 5, 'available'),
('Tenaza Corte Diagonal', 'Tenaza de corte diagonal', 3, 'available'),
('Cola de Zorro', 'Sierra cola de zorro', 2, 'available'),
('Desarmador de Estrella', 'Desarmador de estrella', 6, 'available'),
('Desarmador de Ranura', 'Desarmador de ranura', 6, 'available'),
('Etiquetadora Brother', 'Etiquetadora Brother', 1, 'available'),
('Taladro Alámbrico', 'Taladro alámbrico', 2, 'available'),
('Taladro Inalámbrico', 'Taladro inalámbrico', 2, 'available'),
('Torno de Seguridad', 'Torno de seguridad', 1, 'available'),
('Laptop con su Cargador', 'Laptop con cargador incluido', 3, 'available'),
('Escalera de 6 pies', 'Escalera de 6 pies', 2, 'available'),
('Escalera de 8 pies', 'Escalera de 8 pies', 1, 'available'),
('Sacabocados', 'Sacabocados', 2, 'available'),
('Soplete', 'Soplete', 1, 'available'),
('Extensión Eléctrica', 'Extensión eléctrica', 4, 'available'),
('Extensión UPS', 'Extensión UPS', 2, 'available'),
('Multímetro', 'Multímetro digital', 3, 'available'),
('Llave perra', 'Llave perra ajustable', 4, 'available'),
('Martillo', 'Martillo', 3, 'available');
