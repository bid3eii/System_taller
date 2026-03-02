/* 
Migration: Viáticos Module
Date: 02-03-2026
Description: Create tables for dynamic matrix-style travel expenses 
*/

-- Main Viaticos header table
CREATE TABLE IF NOT EXISTS `viaticos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `created_by` int(11) NOT NULL,
  `status` enum('draft','submitted','paid') DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columns: Which technicians are assigned to this viatico
CREATE TABLE IF NOT EXISTS `viatico_columns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `tech_id` int(11) DEFAULT NULL,
  `tech_name` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rows: The concepts being billed (food, transport, dynamic inputs)
CREATE TABLE IF NOT EXISTS `viatico_concepts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `type` enum('predetermined','custom') NOT NULL,
  `category` enum('food','transport','other') NOT NULL,
  `label` varchar(100) NOT NULL,
  `display_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cells: The intersection of columns and rows containing the amount spent
CREATE TABLE IF NOT EXISTS `viatico_amounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatico_id` int(11) NOT NULL,
  `concept_id` int(11) NOT NULL,
  `column_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`viatico_id`) REFERENCES `viaticos` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`concept_id`) REFERENCES `viatico_concepts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`column_id`) REFERENCES `viatico_columns` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_cell` (`viatico_id`, `concept_id`, `column_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Module Permissions
INSERT IGNORE INTO permissions (code, description) VALUES ('module_viaticos', 'Acceso módulo Viáticos');
INSERT IGNORE INTO permissions (code, description) VALUES ('module_viaticos_add', 'Crear Viático');
INSERT IGNORE INTO permissions (code, description) VALUES ('module_viaticos_edit', 'Editar Viático');
INSERT IGNORE INTO permissions (code, description) VALUES ('module_viaticos_delete', 'Eliminar Viático');

-- Also grant to Admin (Role 1)
INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions WHERE code LIKE 'module_viaticos%';
