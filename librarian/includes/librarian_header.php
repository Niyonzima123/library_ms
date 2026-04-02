<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require librarian role
$auth->requireLogin();
if (!isAdmin()) {
    redirect(STUDENT_URL . '/dashboard.php');
}
if ($_SESSION['role'] !== 'librarian' && $_SESSION['role'] !== 'admin') {
    redirect(SITE_URL . '/login.php');
}

// Auto-detect overdue books
require_once __DIR__ . '/../../includes/auto_overdue.php';

// Auto-shift management: open shift on login, auto-close old forgotten shifts
if (!isset($_SESSION['shift_managed'])) {
    // Close any stale active shifts from previous days
    $close_stale = $conn->prepare("UPDATE librarian_shifts SET status = 'completed' WHERE librarian_id = ? AND status = 'active' AND shift_date < CURDATE()");
    $close_stale->bind_param("i", $_SESSION['user_id']);
    $close_stale->execute();
    
    // Check if there's already an active shift today
    $check = $conn->prepare("SELECT shift_id FROM librarian_shifts WHERE librarian_id = ? AND shift_date = CURDATE() AND status = 'active'");
    $check->bind_param("i", $_SESSION['user_id']);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
        // Auto-create and open a shift
        $now = date('H:i:s');
        $end_default = date('H:i:s', strtotime('+8 hours'));
        $status = 'active';
        $created_by = $_SESSION['user_id'];
        $notes = 'Auto-opened on login';
        $stmt = $conn->prepare("INSERT INTO librarian_shifts (librarian_id, shift_date, shift_start, shift_end, status, notes, created_by) VALUES (?, CURDATE(), ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $_SESSION['user_id'], $now, $end_default, $status, $notes, $created_by);
        $stmt->execute();
        $_SESSION['auto_shift_id'] = $stmt->insert_id;
    }
    $_SESSION['shift_managed'] = true;
}

// Log session start
if (!isset($_SESSION['session_logged'])) {
    $stmt = $conn->prepare("INSERT INTO librarian_sessions (librarian_id, ip_address, user_agent) VALUES (?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt->bind_param("iss", $_SESSION['user_id'], $ip, $ua);
    $stmt->execute();
    $_SESSION['session_logged'] = true;
    $_SESSION['lib_session_id'] = $stmt->insert_id;
}

// Get unread notifications
$notif_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_type = 'admin' AND user_id = ? AND is_read = 0");
$notif_query->bind_param("i", $_SESSION['user_id']);
$notif_query->execute();
$unread_notifs = $notif_query->get_result()->fetch_assoc()['count'];

$recent_notifs = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'admin' AND user_id = ? ORDER BY created_at DESC LIMIT 5");
$recent_notifs->bind_param("i", $_SESSION['user_id']);
$recent_notifs->execute();
$notifications = $recent_notifs->get_result();

include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <!-- Librarian Sidebar -->
    <nav class="sidebar" id="sidebar" style="background: linear-gradient(180deg, #1a365d 0%, #2d3748 100%);">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="bi bi-book-half"></i>
                <span>Librarian Portal</span>
            </div>
            <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php if ($_SESSION['profile_image']): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $_SESSION['profile_image']; ?>" alt="Avatar">
                <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo sanitize($_SESSION['name']); ?></span>
                <span class="badge bg-info"><?php echo ucfirst($_SESSION['role']); ?></span>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>/librarian/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-divider">Book Management</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'books.php' ? 'active' : ''; ?>">
                    <i class="bi bi-book"></i>
                    <span>Manage Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                    <i class="bi bi-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/authors.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'authors.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Authors</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/departments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : ''; ?>">
                    <i class="bi bi-building"></i>
                    <span>Departments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/classes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Classes</span>
                </a>
            </li>
            <li class="nav-divider">Circulation</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/issue_book.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'issue_book.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-arrow-up"></i>
                    <span>Issue Book</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/return_book.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'return_book.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-arrow-down"></i>
                    <span>Return Book</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'issued_books.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-bookmark"></i>
                    <span>Issued Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/return_approvals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'return_approvals.php' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-return-left"></i>
                    <span>Return Approvals</span>
                    <?php
                    $lib_returns = $conn->query("SELECT COUNT(*) as c FROM issued_books WHERE status = 4")->fetch_assoc()['c'];
                    if ($lib_returns > 0): ?>
                        <span class="badge bg-danger"><?php echo $lib_returns; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/reservations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reservations.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bookmark-star"></i>
                    <span>Reservations</span>
                    <?php
                    $lib_active_res = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE status = 'active'")->fetch_assoc()['c'];
                    if ($lib_active_res > 0): ?>
                        <span class="badge bg-warning"><?php echo $lib_active_res; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/ebooks.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ebooks.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                    <span>E-Books</span>
                </a>
            </li>
            <li class="nav-divider">Student Management</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
                    <i class="bi bi-mortarboard"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/approve_students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'approve_students.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-check"></i>
                    <span>Approve Students</span>
                    <?php
                    $lib_pending = $conn->query("SELECT COUNT(*) as c FROM users WHERE approval_status = 'pending'")->fetch_assoc()['c'];
                    if ($lib_pending > 0): ?>
                        <span class="badge bg-danger"><?php echo $lib_pending; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-divider">Reports</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/report_defaulters.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report_defaulters.php' ? 'active' : ''; ?>">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Defaulter List</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Reports & Analytics</span>
                </a>
            </li>
            <li class="nav-divider">My Shift</li>
            <li class="nav-item">
                <a href="<?php echo SITE_URL; ?>/librarian/my_shift.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_shift.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>My Shift Control</span>
                    <?php
                    // Check if there's an active shift
                    $active_shift_check = $conn->prepare("SELECT COUNT(*) as c FROM librarian_shifts WHERE librarian_id = ? AND shift_date = CURDATE() AND status = 'active'");
                    $active_shift_check->bind_param("i", $_SESSION['user_id']);
                    $active_shift_check->execute();
                    $has_active = $active_shift_check->get_result()->fetch_assoc()['c'];
                    if ($has_active > 0): ?>
                        <span class="badge bg-success">On Shift</span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="navbar-search d-none d-md-block">
                <form action="<?php echo ADMIN_URL; ?>/books.php" method="GET">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search books, students...">
                    </div>
                </form>
            </div>
            
            <div class="navbar-actions">
                <div class="dropdown">
                    <button class="btn btn-link nav-icon" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread_notifs > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="dropdown-header"><h6>Notifications</h6></div>
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <a href="<?php echo $notif['link'] ?: '#'; ?>" class="dropdown-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <strong><?php echo sanitize($notif['title']); ?></strong>
                                <p><?php echo sanitize(substr($notif['message'], 0, 60)); ?></p>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-link nav-user-btn" data-bs-toggle="dropdown">
                        <div class="nav-avatar">
                            <?php if ($_SESSION['profile_image']): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $_SESSION['profile_image']; ?>" alt="">
                            <?php else: ?>
                                <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                        </div>
                        <span class="d-none d-md-inline"><?php echo sanitize($_SESSION['name']); ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="<?php echo ADMIN_URL; ?>/profile.php" class="dropdown-item"><i class="bi bi-person"></i> Profile</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo SITE_URL; ?>/includes/logout.php" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
