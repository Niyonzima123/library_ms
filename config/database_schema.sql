-- ============================================
-- University Library Management System Database
-- ============================================

CREATE DATABASE IF NOT EXISTS `lms_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lms_db`;

-- ============================================
-- Table: departments
-- ============================================
CREATE TABLE IF NOT EXISTS `departments` (
  `dept_id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(200) NOT NULL,
  `dept_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`dept_id`),
  UNIQUE KEY `dept_code` (`dept_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: classes
-- ============================================
CREATE TABLE IF NOT EXISTS `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `semester` int(2) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`class_id`),
  KEY `dept_id` (`dept_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: users (students)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `role` enum('student') DEFAULT 'student',
  `card_number` varchar(50) DEFAULT NULL,
  `max_books_allowed` int(3) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `dept_id` (`dept_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: admins
-- ============================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `shift_preference` enum('morning','afternoon','evening','night') DEFAULT 'morning',
  `role` enum('admin','librarian') DEFAULT 'librarian',
  `profile_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: categories
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: authors
-- ============================================
CREATE TABLE IF NOT EXISTS `authors` (
  `author_id` int(11) NOT NULL AUTO_INCREMENT,
  `author_name` varchar(250) NOT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: books
-- ============================================
CREATE TABLE IF NOT EXISTS `books` (
  `book_id` int(11) NOT NULL AUTO_INCREMENT,
  `isbn` varchar(20) DEFAULT NULL,
  `book_name` varchar(250) NOT NULL,
  `author_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  `book_no` varchar(50) NOT NULL,
  `book_price` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `total_copies` int(3) DEFAULT 1,
  `available_copies` int(3) DEFAULT 1,
  `publisher` varchar(200) DEFAULT NULL,
  `publication_year` year(4) DEFAULT NULL,
  `edition` varchar(50) DEFAULT NULL,
  `pages` int(5) DEFAULT NULL,
  `rack_location` varchar(50) DEFAULT NULL,
  `has_ebook` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `status` enum('available','unavailable','lost','damaged') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`book_id`),
  UNIQUE KEY `book_no` (`book_no`),
  KEY `author_id` (`author_id`),
  KEY `cat_id` (`cat_id`),
  CONSTRAINT `books_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE,
  CONSTRAINT `books_ibfk_2` FOREIGN KEY (`cat_id`) REFERENCES `categories` (`cat_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: ebooks
-- ============================================
CREATE TABLE IF NOT EXISTS `ebooks` (
  `ebook_id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ebook_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `ebooks_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: issued_books
-- ============================================
CREATE TABLE IF NOT EXISTS `issued_books` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` int(1) DEFAULT 0 COMMENT '0=pending,1=approved,2=returned,3=overdue,4=return_requested',
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `fine_paid` tinyint(1) DEFAULT 0,
  `issued_by` int(11) DEFAULT NULL,
  `returned_to` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`issue_id`),
  KEY `book_id` (`book_id`),
  KEY `user_id` (`user_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `issued_books_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  CONSTRAINT `issued_books_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `issued_books_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: reservations
-- ============================================
CREATE TABLE IF NOT EXISTS `reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reservation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','fulfilled','cancelled','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reservation_id`),
  KEY `book_id` (`book_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE,
  CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: reading_list
-- ============================================
CREATE TABLE IF NOT EXISTS `reading_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_book` (`user_id`, `book_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `reading_list_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reading_list_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: activity_log
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','librarian','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: notifications
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','librarian','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notif_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: student_documents
-- ============================================
CREATE TABLE IF NOT EXISTS `student_documents` (
  `doc_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `doc_title` varchar(255) NOT NULL,
  `doc_description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `doc_category` enum('note','document','book_review','summary','other') DEFAULT 'document',
  `is_private` tinyint(1) DEFAULT 1,
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
-- ============================================
CREATE TABLE IF NOT EXISTS `student_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
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
  `promoted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`promo_id`),
  KEY `user_id` (`user_id`),
  KEY `promoted_by` (`promoted_by`),
  CONSTRAINT `student_promotions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_promotions_ibfk_2` FOREIGN KEY (`promoted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: librarian_shifts
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
-- Table: password_resets
-- ============================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `user_type` enum('student','admin','librarian') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: student_goals
-- ============================================
CREATE TABLE IF NOT EXISTS `student_goals` (
  `goal_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `goal_title` varchar(255) NOT NULL,
  `goal_description` text DEFAULT NULL,
  `goal_type` enum('academic','reading','skill','career','personal') DEFAULT 'academic',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('active','completed','paused','cancelled') DEFAULT 'active',
  `target_date` date DEFAULT NULL,
  `progress` int(3) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`goal_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `student_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: student_events
-- ============================================
CREATE TABLE IF NOT EXISTS `student_events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `event_type` enum('deadline','exam','assignment','reading','meeting','other') DEFAULT 'other',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `event_date` (`event_date`),
  CONSTRAINT `student_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- 2 Admin/Librarian (password: admin@1234 for admin, librarian@1234 for librarian)
INSERT INTO `admins` (`name`, `email`, `password`, `mobile`, `role`, `hire_date`) VALUES
('System Administrator', 'admin@library.edu', '$2y$10$pzV4qDRHuXNtOPUXl6NOdeIgLMoajYS2AXtdie2g6PxGPyPXgs0z2', '1234567890', 'admin', '2020-01-15'),
('Sarah Librarian', 'librarian@library.edu', '$2y$10$Yczv4BHpsaTWknmcVogXJOwNL9bCey0U8KtemEb8wFhmrlY2WhT4y', '1234567891', 'librarian', '2021-03-10'),
('Mike Assistant', 'mike@library.edu', '$2y$10$Yczv4BHpsaTWknmcVogXJOwNL9bCey0U8KtemEb8wFhmrlY2WhT4y', '1234567892', 'librarian', '2022-06-01'),
('Lisa Manager', 'lisa@library.edu', '$2y$10$Yczv4BHpsaTWknmcVogXJOwNL9bCey0U8KtemEb8wFhmrlY2WhT4y', '1234567893', 'librarian', '2023-01-20');

-- 8 Departments
INSERT INTO `departments` (`dept_name`, `dept_code`, `description`) VALUES
('Computer Science Engineering', 'CSE', 'Department of Computer Science and Engineering'),
('Electronics and Communication', 'ECE', 'Department of Electronics and Communication Engineering'),
('Mechanical Engineering', 'ME', 'Department of Mechanical Engineering'),
('Civil Engineering', 'CE', 'Department of Civil Engineering'),
('Electrical Engineering', 'EE', 'Department of Electrical Engineering'),
('Business Administration', 'BBA', 'Department of Business Administration'),
('Arts and Humanities', 'AH', 'Department of Arts and Humanities'),
('Science', 'SCI', 'Department of Science');

-- Classes for each department (2 classes per dept = 16 classes)
INSERT INTO `classes` (`class_name`, `dept_id`, `semester`, `academic_year`) VALUES
('CSE - 1st Year', 1, 1, '2025-26'),
('CSE - 2nd Year', 1, 3, '2025-26'),
('CSE - 3rd Year', 1, 5, '2025-26'),
('CSE - 4th Year', 1, 7, '2025-26'),
('ECE - 1st Year', 2, 1, '2025-26'),
('ECE - 2nd Year', 2, 3, '2025-26'),
('ECE - 3rd Year', 2, 5, '2025-26'),
('ECE - 4th Year', 2, 7, '2025-26'),
('ME - 1st Year', 3, 1, '2025-26'),
('ME - 2nd Year', 3, 3, '2025-26'),
('CE - 1st Year', 4, 1, '2025-26'),
('CE - 2nd Year', 4, 3, '2025-26'),
('EE - 1st Year', 5, 1, '2025-26'),
('EE - 2nd Year', 5, 3, '2025-26'),
('BBA - 1st Year', 6, 1, '2025-26'),
('BBA - 2nd Year', 6, 3, '2025-26'),
('AH - 1st Year', 7, 1, '2025-26'),
('SCI - 1st Year', 8, 1, '2025-26');

-- 10 Categories
INSERT INTO `categories` (`cat_name`, `description`) VALUES
('Computer Science', 'Books related to Computer Science and Programming'),
('Engineering', 'Engineering textbooks and references'),
('Mathematics', 'Mathematics and Statistics books'),
('Physics', 'Physics and Applied Sciences'),
('Literature', 'Fiction, Non-fiction and Literary works'),
('Business', 'Business, Management and Economics'),
('History', 'History and Social Sciences'),
('Reference', 'Encyclopedias, Dictionaries and Reference materials'),
('Magazine', 'Periodicals and Magazines'),
('Research Papers', 'Academic research papers and journals');

-- 10 Authors
INSERT INTO `authors` (`author_name`, `bio`) VALUES
('Thomas H. Cormen', 'Author of Introduction to Algorithms'),
('Andrew S. Tanenbaum', 'Computer Scientist and author'),
('Dennis Ritchie', 'Creator of C programming language'),
('Brian W. Kernighan', 'Computer Scientist and author'),
('Robert C. Martin', 'Software engineer and author of Clean Code'),
('Martin Kleppmann', 'Author of Designing Data-Intensive Applications'),
('Abraham Silberschatz', 'Author of Database System Concepts'),
('Erich Gamma', 'Co-author of Design Patterns'),
('A.P.J. Abdul Kalam', 'Former President of India, Scientist and Author'),
('Chetan Bhagat', 'Indian author and columnist');

-- 10 Books
INSERT INTO `books` (`isbn`, `book_name`, `author_id`, `cat_id`, `book_no`, `book_price`, `description`, `total_copies`, `available_copies`, `publisher`, `publication_year`, `edition`, `pages`, `rack_location`) VALUES
('978-0262033848', 'Introduction to Algorithms', 1, 1, 'CS-001', 1200.00, 'Comprehensive textbook on algorithms and data structures', 5, 5, 'MIT Press', 2009, '3rd Edition', 1312, 'Rack A1'),
('978-0133594140', 'Modern Operating Systems', 2, 1, 'CS-002', 950.00, 'Operating systems concepts and design', 3, 3, 'Pearson', 2014, '4th Edition', 1064, 'Rack A2'),
('978-0131103627', 'The C Programming Language', 3, 1, 'CS-003', 450.00, 'Classic book on C programming', 4, 4, 'Prentice Hall', 1988, '2nd Edition', 272, 'Rack A1'),
('978-0596517748', 'JavaScript Good Parts', 4, 1, 'CS-004', 350.00, 'Essential JavaScript guide', 3, 3, 'O Reilly Media', 2008, '1st Edition', 176, 'Rack A3'),
('978-0132350884', 'Clean Code', 5, 1, 'CS-005', 850.00, 'A handbook of agile software craftsmanship', 4, 4, 'Prentice Hall', 2008, '1st Edition', 464, 'Rack A2'),
('978-1449373320', 'Designing Data-Intensive Applications', 6, 1, 'CS-006', 1100.00, 'Big ideas behind reliable, scalable systems', 3, 3, 'O Reilly Media', 2017, '1st Edition', 616, 'Rack A3'),
('978-0078028229', 'Database System Concepts', 7, 1, 'CS-007', 980.00, 'Comprehensive database textbook', 5, 5, 'McGraw-Hill', 2019, '7th Edition', 1376, 'Rack B1'),
('978-0201633610', 'Design Patterns', 8, 1, 'CS-008', 750.00, 'Elements of Reusable Object-Oriented Software', 3, 3, 'Addison-Wesley', 1994, '1st Edition', 416, 'Rack A2'),
('978-8129137708', 'Wings of Fire', 9, 5, 'LT-001', 350.00, 'An Autobiography of A.P.J. Abdul Kalam', 5, 5, 'Universities Press', 2014, '1st Edition', 228, 'Rack C1'),
('978-8129115370', 'Five Point Someone', 10, 5, 'LT-002', 195.00, 'What not to do at IIT!', 4, 4, 'Rupa Publications', 2004, '1st Edition', 267, 'Rack C1');

-- 10 Students (password for all: student@1234)
INSERT INTO `users` (`student_id`, `name`, `email`, `password`, `mobile`, `address`, `dept_id`, `class_id`, `approval_status`, `card_number`) VALUES
('STU-2025-001', 'John Doe', 'john@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543210', '123 University Campus', 1, 2, 'approved', 'CARD-2025-001'),
('STU-2025-002', 'Jane Smith', 'jane@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543211', '456 Campus Road', 1, 2, 'approved', 'CARD-2025-002'),
('STU-2025-003', 'Alice Johnson', 'alice@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543212', '789 College Street', 2, 5, 'approved', 'CARD-2025-003'),
('STU-2025-004', 'Bob Williams', 'bob@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543213', '101 Academic Blvd', 2, 6, 'approved', 'CARD-2025-004'),
('STU-2025-005', 'Charlie Brown', 'charlie@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543214', '202 Student Lane', 3, 9, 'approved', 'CARD-2025-005'),
('STU-2025-006', 'Diana Prince', 'diana@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543215', '303 Library Ave', 4, 11, 'approved', 'CARD-2025-006'),
('STU-2025-007', 'Edward Norton', 'edward@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543216', '404 Dorm Block A', 5, 13, 'approved', 'CARD-2025-007'),
('STU-2025-008', 'Fiona Green', 'fiona@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543217', '505 Science Park', 8, 18, 'approved', 'CARD-2025-008'),
('STU-2025-009', 'George Wilson', 'george@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543218', '606 Admin Block', 6, 15, 'approved', 'CARD-2025-009'),
('STU-2025-010', 'Helen Troy', 'helen@library.edu', '$2y$10$Dj0ih1NzsUohEZ4oyFu1uehKfptXCc.DUYADt/jnNNN21O..b0pMC', '9876543219', '707 Arts Hall', 7, 17, 'pending', 'CARD-2025-010');

-- 5 Sample Issued Books
INSERT INTO `issued_books` (`book_id`, `user_id`, `issue_date`, `due_date`, `status`, `issued_by`) VALUES
(1, 1, '2025-03-01', '2025-03-15', 1, 2),
(3, 2, '2025-03-05', '2025-03-19', 1, 2),
(5, 3, '2025-03-10', '2025-03-24', 1, 2),
(7, 4, '2025-03-12', '2025-03-26', 0, NULL),
(9, 5, '2025-02-01', '2025-02-15', 3, 2);

-- 3 Sample Notes
INSERT INTO `student_notes` (`user_id`, `book_id`, `note_title`, `note_content`, `note_type`, `color`, `is_pinned`) VALUES
(1, 1, 'Chapter 1 Notes', 'Key concepts from Introduction to Algorithms - Big O notation, time complexity analysis.', 'book_note', '#d1ecf1', 1),
(2, 3, 'C Pointers Summary', 'Important points about pointers in C programming language. Memory management basics.', 'study_note', '#d4edda', 0),
(3, 5, 'Clean Code Principles', 'Meaningful names, small functions, comments should explain why not what.', 'book_note', '#fff3cd', 1);

-- 2 Sample Goals
INSERT INTO `student_goals` (`user_id`, `goal_title`, `goal_description`, `goal_type`, `priority`, `target_date`, `progress`) VALUES
(1, 'Read 10 Books This Semester', 'Complete reading 10 books from the library before semester ends.', 'reading', 'high', '2026-06-30', 30),
(1, 'Master Data Structures', 'Study and practice all data structure implementations.', 'academic', 'critical', '2026-05-15', 45);

-- 2 Sample Events
INSERT INTO `student_events` (`user_id`, `event_title`, `event_description`, `event_type`, `event_date`, `event_time`) VALUES
(1, 'Mid-term Exam', 'Computer Science mid-term examination.', 'exam', '2026-04-15', '09:00:00'),
(1, 'Library Book Return', 'Return Introduction to Algorithms book.', 'deadline', '2026-04-10', '17:00:00');
