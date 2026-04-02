-- ============================================
-- Library Management System - Database Update
-- Add Student Documents, Notes, Promotions
-- ============================================

USE `lms_db`;

-- ============================================
-- Table: student_documents
-- Students can upload their own documents, notes, and files
-- ============================================
CREATE TABLE IF NOT EXISTS `student_documents` (
  `doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL COMMENT 'Optional: linked to a specific book',
  `doc_title` varchar(255) NOT NULL,
  `doc_description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `doc_category` enum('note','document','book_review','summary','other') DEFAULT 'document',
  `is_private` tinyint(1) DEFAULT 1 COMMENT '1=only student sees, 0=admin can see',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`doc_id`),
  KEY `user_id` (`user_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_documents_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: student_notes
-- Students can write and save personal notes, book reviews, summaries
-- ============================================
CREATE TABLE IF NOT EXISTS `student_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL COMMENT 'Optional: note linked to a specific book',
  `note_title` varchar(255) NOT NULL,
  `note_content` longtext NOT NULL,
  `note_type` enum('personal','book_note','book_review','summary','study_note') DEFAULT 'personal',
  `color` varchar(20) DEFAULT '#ffffff',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `user_id` (`user_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `student_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_notes_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: student_promotions
-- Track student class changes and promotions
-- ============================================
CREATE TABLE IF NOT EXISTS `student_promotions` (
  `promo_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_class_id` int(11) DEFAULT NULL,
  `to_class_id` int(11) NOT NULL,
  `from_dept_id` int(11) DEFAULT NULL,
  `to_dept_id` int(11) DEFAULT NULL,
  `promotion_type` enum('promotion','transfer','demotion','initial') DEFAULT 'promotion',
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` int(2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `promoted_by` int(11) DEFAULT NULL COMMENT 'Admin who promoted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`promo_id`),
  KEY `user_id` (`user_id`),
  KEY `promoted_by` (`promoted_by`),
  CONSTRAINT `student_promotions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_promotions_ibfk_2` FOREIGN KEY (`promoted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
