<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

// Auto-close librarian shift on logout
if (isLoggedIn() && isset($_SESSION['role']) && ($_SESSION['role'] === 'librarian' || $_SESSION['role'] === 'admin')) {
    // Close auto-opened shift
    if (isset($_SESSION['auto_shift_id'])) {
        $close = $conn->prepare("UPDATE librarian_shifts SET status = 'completed', notes = CONCAT(IFNULL(notes,''), ' | Auto-closed on logout') WHERE shift_id = ? AND status = 'active'");
        $close->bind_param("i", $_SESSION['auto_shift_id']);
        $close->execute();
    }
    // Also close any other active shift for today
    $close_all = $conn->prepare("UPDATE librarian_shifts SET status = 'completed', notes = CONCAT(IFNULL(notes,''), ' | Auto-closed on logout') WHERE librarian_id = ? AND shift_date = CURDATE() AND status = 'active'");
    $close_all->bind_param("i", $_SESSION['user_id']);
    $close_all->execute();
    
    // Close session tracking
    if (isset($_SESSION['lib_session_id'])) {
        $close_sess = $conn->prepare("UPDATE librarian_sessions SET logout_time = NOW(), is_active = 0 WHERE session_id = ?");
        $close_sess->bind_param("i", $_SESSION['lib_session_id']);
        $close_sess->execute();
    }
}

$auth->logout();
setFlashMessage('success', 'You have been logged out successfully.');
redirect(SITE_URL . '/login.php');
?>