SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `system_taller`
--
CREATE DATABASE IF NOT EXISTS `system_taller` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `system_taller`;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'Administrador, Supervisor, Técnico, Recepción, Almacén',
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Supervisor', 'Gestión operativa y supervisión'),
(3, 'Técnico', 'Realización de trabajos y asignaciones'),
(4, 'Recepción', 'Registro de entrada y entrega de equipos'),
(5, 'Almacén', 'Gestión de herramientas e inventario');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin User (Password: admin123) - You should change this hash
INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `role_id`, `status`) VALUES
(1, 'admin', '$2y$12$PoUWb11Az7FF.cqrYaCl1.3yTT5wjIuH9V1Zdsac.F6RT2zAz1Fpi', 'admin@taller.com', 1, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL COMMENT 'DNI, RUC or equivalent',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL COMMENT 'Laptop, Desktop, Printer, etc.',
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `service_orders`
--

CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `assigned_tech_id` int(11) DEFAULT NULL,
  `status` enum('received','diagnosing','pending_approval','in_repair','ready','delivered','cancelled') DEFAULT 'received',
  `problem_reported` text DEFAULT NULL,
  `accessories_received` text DEFAULT NULL,
  `entry_notes` text DEFAULT NULL,
  `entry_date` datetime DEFAULT current_timestamp(),
  `entry_signature_path` varchar(255) DEFAULT NULL,
  `diagnosis_notes` text DEFAULT NULL,
  `work_done` text DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `final_cost` decimal(10,2) DEFAULT 0.00,
  `exit_date` datetime DEFAULT NULL,
  `exit_signature_path` varchar(255) DEFAULT NULL,
  `authorized_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `service_order_history`
--

CREATE TABLE `service_order_history` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tools`
--

CREATE TABLE `tools` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL COMMENT 'Internal inventory code',
  `status` enum('available','in_use','maintenance','broken','lost') DEFAULT 'available',
  `condition_notes` text DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tool_loans`
--

CREATE TABLE `tool_loans` (
  `id` int(11) NOT NULL,
  `tool_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Technician borrowing the tool',
  `loan_date` datetime DEFAULT current_timestamp(),
  `return_date` datetime DEFAULT NULL,
  `status` enum('active','returned') DEFAULT 'active',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `warranties`
--

CREATE TABLE `warranties` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) DEFAULT NULL COMMENT 'If warranty is for a repair',
  `equipment_id` int(11) DEFAULT NULL COMMENT 'If warranty is for an equipment sale/item',
  `supplier_name` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','expired','void','claimed') DEFAULT 'active',
  `docs_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user_custom_modules`
--

CREATE TABLE `user_custom_modules` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Overrides for specific users to access/block modules';

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL UNIQUE,
  `description` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tool_assignments`
--

CREATE TABLE `tool_assignments` (
  `id` int(11) NOT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(150) NOT NULL COMMENT 'Nombre del encargado',
  `technician_1` varchar(150) DEFAULT NULL,
  `technician_2` varchar(150) DEFAULT NULL,
  `technician_3` varchar(150) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `status` enum('pending','returned') DEFAULT 'pending',
  `created_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tool_assignment_items`
--

CREATE TABLE `tool_assignment_items` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `tool_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('assigned','returned') DEFAULT 'assigned',
  `return_confirmed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `diagnosis_images`
--

CREATE TABLE `diagnosis_images` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `system_sequences`
--

CREATE TABLE `system_sequences` (
  `id` int(11) NOT NULL,
  `sequence_name` varchar(50) NOT NULL,
  `current_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

ALTER TABLE `roles` ADD PRIMARY KEY (`id`);
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD KEY `role_id` (`role_id`);
ALTER TABLE `clients` ADD PRIMARY KEY (`id`);
ALTER TABLE `equipments` ADD PRIMARY KEY (`id`), ADD KEY `client_id` (`client_id`);
ALTER TABLE `service_orders` ADD PRIMARY KEY (`id`), ADD KEY `equipment_id` (`equipment_id`), ADD KEY `client_id` (`client_id`), ADD KEY `assigned_tech_id` (`assigned_tech_id`), ADD KEY `authorized_by_user_id` (`authorized_by_user_id`);
ALTER TABLE `service_order_history` ADD PRIMARY KEY (`id`), ADD KEY `service_order_id` (`service_order_id`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `tools` ADD PRIMARY KEY (`id`);
ALTER TABLE `tool_loans` ADD PRIMARY KEY (`id`), ADD KEY `tool_id` (`tool_id`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `warranties` ADD PRIMARY KEY (`id`), ADD KEY `service_order_id` (`service_order_id`), ADD KEY `equipment_id` (`equipment_id`);
ALTER TABLE `user_custom_modules` ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `permissions` ADD PRIMARY KEY (`id`);
ALTER TABLE `role_permissions` ADD PRIMARY KEY (`role_id`,`permission_id`), ADD KEY `permission_id` (`permission_id`);
ALTER TABLE `audit_logs` ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`), ADD KEY `table_record` (`table_name`,`record_id`);
ALTER TABLE `tool_assignments` ADD PRIMARY KEY (`id`);
ALTER TABLE `tool_assignment_items` ADD PRIMARY KEY (`id`), ADD KEY `assignment_id` (`assignment_id`), ADD KEY `tool_id` (`tool_id`);
ALTER TABLE `site_settings` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `setting_key` (`setting_key`);
ALTER TABLE `diagnosis_images` ADD PRIMARY KEY (`id`), ADD KEY `service_order_id` (`service_order_id`);
ALTER TABLE `system_sequences` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `sequence_name` (`sequence_name`);

--
-- AUTO_INCREMENT for dumped tables
--

ALTER TABLE `roles` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `clients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `equipments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_orders` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_order_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tools` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tool_loans` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `warranties` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_custom_modules` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `permissions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `audit_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tool_assignments` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tool_assignment_items` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `site_settings` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `diagnosis_images` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `system_sequences` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
ALTER TABLE `equipments` ADD CONSTRAINT `equipments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
ALTER TABLE `service_orders` ADD CONSTRAINT `service_orders_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`), ADD CONSTRAINT `service_orders_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`), ADD CONSTRAINT `service_orders_ibfk_3` FOREIGN KEY (`assigned_tech_id`) REFERENCES `users` (`id`), ADD CONSTRAINT `service_orders_ibfk_4` FOREIGN KEY (`authorized_by_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `service_order_history` ADD CONSTRAINT `service_order_history_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `service_order_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `tool_loans` ADD CONSTRAINT `tool_loans_ibfk_1` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`), ADD CONSTRAINT `tool_loans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `warranties` ADD CONSTRAINT `warranties_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`), ADD CONSTRAINT `warranties_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`);
ALTER TABLE `user_custom_modules` ADD CONSTRAINT `user_custom_modules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `role_permissions` ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `tool_assignment_items` ADD CONSTRAINT `tool_assignment_items_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `tool_assignments` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `tool_assignment_items_ibfk_2` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`);
ALTER TABLE `diagnosis_images` ADD CONSTRAINT `diagnosis_images_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
