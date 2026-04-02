-- ============================================
-- Library Management System - Librarian Update
-- Librarian Management, Shifts, Reports
-- ============================================

USE `lms_db`;

-- ============================================
-- Table: librarian_shifts
-- Track librarian work shifts
-- ============================================
CREATE TABLE IF NOT EXISTS `librarian_shifts` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `librarian_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `status` enum('scheduled','active','completed','absent','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shift_id`),
  KEY `librarian_id` (`librarian_id`),
  KEY `shift_date` (`shift_date`),
  CONSTRAINT `librarian_shifts_ibfk_1` FOREIGN KEY (`librarian_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `librarian_shifts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: librarian_sessions
-- Track login/logout sessions for librarians
-- ============================================
CREATE TABLE IF NOT EXISTS `librarian_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `librarian_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`session_id`),
  KEY `librarian_id` (`librarian_id`),
  CONSTRAINT `librarian_sessions_ibfk_1` FOREIGN KEY (`librarian_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: reports_history
-- Track generated reports
-- ============================================
CREATE TABLE IF NOT EXISTS `reports_history` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` varchar(50) NOT NULL,
  `report_format` enum('pdf','excel','pptx') NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `parameters` text DEFAULT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_by_type` enum('admin','librarian') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `generated_by` (`generated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Update admins table: add soft delete
-- ============================================
ALTER TABLE `admins` ADD COLUMN `deleted_at` timestamp NULL DEFAULT NULL AFTER `is_active`;
ALTER TABLE `admins` ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `mobile`;
ALTER TABLE `admins` ADD COLUMN `address` text DEFAULT NULL AFTER `phone`;
ALTER TABLE `admins` ADD COLUMN `hire_date` date DEFAULT NULL AFTER `address`;
ALTER TABLE `admins` ADD COLUMN `shift_preference` enum('morning','afternoon','evening','night') DEFAULT 'morning' AFTER `hire_date`;

-- Sample librarian shifts
INSERT INTO `librarian_shifts` (`librarian_id`, `shift_date`, `shift_start`, `shift_end`, `status`, `created_by`) VALUES
(2, CURDATE(), '08:00:00', '14:00:00', 'scheduled', 1),
(2, CURDATE(), '14:00:00', '20:00:00', 'scheduled', 1);
