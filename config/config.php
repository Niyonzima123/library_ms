<?php
// Production: disable error display, enable logging
if (!defined('LOCAL_DEV')) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'University Library Management System');
define('SITE_URL', 'http://localhost/library_ms');
define('ADMIN_URL', SITE_URL . '/admin');
define('STUDENT_URL', SITE_URL . '/student');

// File Upload Configuration
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('EBOOKS_DIR', UPLOAD_DIR . 'ebooks/');
define('COVERS_DIR', UPLOAD_DIR . 'covers/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB max file size
define('ALLOWED_EBOOK_TYPES', ['pdf', 'epub', 'mobi']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Library Configuration
define('MAX_BOOKS_PER_STUDENT', 3);
define('DEFAULT_BORROW_DAYS', 14);
define('FINE_PER_DAY', 5); // Fine in currency per day overdue

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_LIBRARIAN', 'librarian');
define('ROLE_STUDENT', 'student');

// Approval Status
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// Book Issue Status
define('ISSUE_PENDING', 0);
define('ISSUE_APPROVED', 1);
define('ISSUE_RETURNED', 2);
define('ISSUE_OVERDUE', 3);
define('ISSUE_RETURN_REQUESTED', 4);

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_LIBRARIAN);
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_STUDENT;
}

function isApproved() {
    return isset($_SESSION['approval_status']) && $_SESSION['approval_status'] === STATUS_APPROVED;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    $valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        // Regenerate token after successful verification to prevent reuse
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

function calculateFine($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    if ($today > $due) {
        $days = $today->diff($due)->days;
        return $days * FINE_PER_DAY;
    }
    return 0;
}
?>
