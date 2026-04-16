-- sql/migrations/2026_04_14_schedule_and_lifecycle.sql

USE `system_taller`;

-- 1. Create schedule_events table
CREATE TABLE IF NOT EXISTS `schedule_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `tech_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `survey_id` int(11) DEFAULT NULL COMMENT 'Link to Levantamiento',
  `service_order_id` int(11) DEFAULT NULL COMMENT 'Link to OS/Garantía',
  `status` enum('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
  `color` varchar(20) DEFAULT '#6366f1',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tech_id` (`tech_id`),
  KEY `survey_id` (`survey_id`),
  KEY `service_order_id` (`service_order_id`),
  CONSTRAINT `schedule_events_ibfk_1` FOREIGN KEY (`tech_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedule_events_ibfk_2` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedule_events_ibfk_3` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Update viaticos table to link with surveys
ALTER TABLE `viaticos` ADD COLUMN `survey_id` int(11) DEFAULT NULL AFTER `project_title`;
ALTER TABLE `viaticos` ADD CONSTRAINT `viaticos_survey_fk` FOREIGN KEY (`survey_id`) REFERENCES `project_surveys` (`id`) ON DELETE SET NULL;
ALTER TABLE `viaticos` ADD INDEX (`survey_id`);
