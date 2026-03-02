-- Migration to add missing tables from Adohuken_2.0 branch
-- Created on 2026-03-02

USE `system_taller`;

-- 1. Table for Project Surveys (Levantamientos)
CREATE TABLE IF NOT EXISTS `project_surveys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `general_description` text DEFAULT NULL,
  `scope_activities` text DEFAULT NULL,
  `estimated_time` varchar(100) DEFAULT NULL,
  `personnel_required` varchar(100) DEFAULT NULL,
  `status` enum('draft','submitted','approved') DEFAULT 'draft',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `project_surveys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table for Project Materials
CREATE TABLE IF NOT EXISTS `project_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(50) DEFAULT 'unidades',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  CONSTRAINT `project_materials_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table for Anexos Yazaki
CREATE TABLE IF NOT EXISTS `anexos_yazaki` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `client_name` varchar(255) DEFAULT 'YAZAKI DE NICARAGUA SA',
  `status` enum('draft','generated') DEFAULT 'draft',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `anexos_yazaki_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anexos_yazaki_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table for Anexo Tools (Details)
CREATE TABLE IF NOT EXISTS `anexo_tools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anexo_id` int(11) NOT NULL,
  `row_index` int(11) DEFAULT NULL,
  `tool_id` int(11) DEFAULT NULL,
  `custom_description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  PRIMARY KEY (`id`),
  KEY `anexo_id` (`anexo_id`),
  KEY `tool_id` (`tool_id`),
  CONSTRAINT `anexo_tools_ibfk_1` FOREIGN KEY (`anexo_id`) REFERENCES `anexos_yazaki` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anexo_tools_ibfk_2` FOREIGN KEY (`tool_id`) REFERENCES `tools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
