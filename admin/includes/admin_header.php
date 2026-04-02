<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth->requireAdmin();

// Auto-detect overdue books
require_once __DIR__ . '/../../includes/auto_overdue.php';

// Get unread notifications count
$notif_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_type = 'admin' AND user_id = ? AND is_read = 0");
$notif_query->bind_param("i", $_SESSION['user_id']);
$notif_query->execute();
$unread_notifs = $notif_query->get_result()->fetch_assoc()['count'];

// Get recent notifications
$recent_notifs = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'admin' AND user_id = ? ORDER BY created_at DESC LIMIT 5");
$recent_notifs->bind_param("i", $_SESSION['user_id']);
$recent_notifs->execute();
$notifications = $recent_notifs->get_result();

include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <!-- Admin Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="bi bi-book-half"></i>
                <span>Library Admin</span>
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
                <span class="user-role badge bg-<?php echo $_SESSION['role'] === 'admin' ? 'danger' : 'info'; ?>">
                    <?php echo ucfirst($_SESSION['role']); ?>
                </span>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
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
            <li class="nav-divider">Library Operations</li>
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
                <a href="<?php echo ADMIN_URL; ?>/ebooks.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ebooks.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                    <span>E-Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/reservations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reservations.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bookmark-star"></i>
                    <span>Reservations</span>
                    <?php
                    $active_res = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE status = 'active'")->fetch_assoc()['c'];
                    if ($active_res > 0): ?>
                        <span class="badge bg-warning"><?php echo $active_res; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/return_approvals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'return_approvals.php' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-return-left"></i>
                    <span>Return Approvals</span>
                    <?php
                    $pending_returns = $conn->query("SELECT COUNT(*) as c FROM issued_books WHERE status = 4")->fetch_assoc()['c'];
                    if ($pending_returns > 0): ?>
                        <span class="badge bg-danger"><?php echo $pending_returns; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-divider">User Management</li>
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
                    $pending_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE approval_status = 'pending'")->fetch_assoc()['c'];
                    if ($pending_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/promote_student.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'promote_student.php' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-up-circle"></i>
                    <span>Promote Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/student_documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'student_documents.php' ? 'active' : ''; ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Student Documents</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
            <li class="nav-divider">Librarian Management</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/manage_librarians.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_librarians.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Manage Librarians</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/librarian_shifts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'librarian_shifts.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-week"></i>
                    <span>Librarian Shifts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/librarian_activities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'librarian_activities.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-workspace"></i>
                    <span>Librarian Activities</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-divider">Reports</li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/report_defaulters.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report_defaulters.php' ? 'active' : ''; ?>">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Defaulter List</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/report_activity.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report_activity.php' ? 'active' : ''; ?>">
                    <i class="bi bi-activity"></i>
                    <span>Activity Log</span>
                </a>
            </li>
            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/student_activities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'student_activities.php' ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Student Activities</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="<?php echo ADMIN_URL; ?>/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Reports & Analytics</span>
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
                        <div class="dropdown-header">
                            <h6>Notifications</h6>
                        </div>
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <a href="<?php echo $notif['link'] ?: '#'; ?>" class="dropdown-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <div class="notif-icon"><i class="bi bi-bell"></i></div>
                                <div class="notif-content">
                                    <strong><?php echo sanitize($notif['title']); ?></strong>
                                    <p><?php echo sanitize($notif['message']); ?></p>
                                    <small><?php echo formatDateTime($notif['created_at']); ?></small>
                                </div>
                            </a>
                        <?php endwhile; ?>
                        <div class="dropdown-footer">
                            <a href="<?php echo ADMIN_URL; ?>/notifications.php">View All</a>
                        </div>
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
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="<?php echo ADMIN_URL; ?>/profile.php" class="dropdown-item"><i class="bi bi-person"></i> Profile</a>
                        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                        <a href="<?php echo ADMIN_URL; ?>/settings.php" class="dropdown-item"><i class="bi bi-gear"></i> Settings</a>
                        <?php endif; ?>
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
