USE `lms_db`;

CREATE TABLE IF NOT EXISTS `student_goals` (
  `goal_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `goal_title` varchar(255) NOT NULL,
  `goal_description` text DEFAULT NULL,
  `goal_type` enum('academic','reading','skill','career','personal') DEFAULT 'academic',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('active','completed','paused','cancelled') DEFAULT 'active',
  `target_date` date DEFAULT NULL,
  `progress` int(3) DEFAULT 0 COMMENT '0-100 percentage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`goal_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `student_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `event_type` enum('deadline','exam','assignment','reading','meeting','other') DEFAULT 'other',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `reminder_date` date DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `event_date` (`event_date`),
  CONSTRAINT `student_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_academic_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` int(2) DEFAULT NULL,
  `target_books_read` int(5) DEFAULT 12,
  `target_gpa` decimal(3,2) DEFAULT NULL,
  `study_hours_per_week` int(3) DEFAULT 20,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `student_academic_plans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
