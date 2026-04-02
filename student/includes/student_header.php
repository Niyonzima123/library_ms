<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth->requireLogin();
if (!isStudent()) {
    redirect(ADMIN_URL . '/dashboard.php');
}

// Get unread notifications count
$notif_query = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_type = 'student' AND user_id = ? AND is_read = 0");
$notif_query->bind_param("i", $_SESSION['user_id']);
$notif_query->execute();
$unread_notifs = $notif_query->get_result()->fetch_assoc()['count'];

// Get reading list count
$cart_count = $conn->prepare("SELECT COUNT(*) as count FROM reading_list WHERE user_id = ?");
$cart_count->bind_param("i", $_SESSION['user_id']);
$cart_count->execute();
$cart_total = $cart_count->get_result()->fetch_assoc()['count'];

include __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <!-- Student Sidebar -->
    <nav class="sidebar student-sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="bi bi-book-half"></i>
                <span>Library Portal</span>
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
                <span class="user-id"><?php echo sanitize($_SESSION['student_id']); ?></span>
                <?php if (!isApproved()): ?>
                    <span class="badge bg-warning">Pending Approval</span>
                <?php else: ?>
                    <span class="badge bg-success">Approved</span>
                <?php endif; ?>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'catalog.php' ? 'active' : ''; ?>">
                    <i class="bi bi-collection"></i>
                    <span>Book Catalog</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/my_books.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_books.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-bookmark"></i>
                    <span>My Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/ebooks.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ebooks.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                    <span>E-Books Library</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/reading_list.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reading_list.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bookshelf"></i>
                    <span>Reading List</span>
                    <?php if ($cart_total > 0): ?>
                        <span class="badge bg-primary"><?php echo $cart_total; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Borrowing History</span>
                </a>
            </li>
            <li class="nav-divider">My Work</li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/notes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>My Notes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                    <span>My Documents</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/goals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'goals.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bullseye"></i>
                    <span>My Goals & Plans</span>
                </a>
            </li>
            <li class="nav-divider">Account</li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo STUDENT_URL; ?>/librarians.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'librarians.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Library Staff</span>
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
                <form action="<?php echo STUDENT_URL; ?>/catalog.php" method="GET">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search books by title, author, ISBN...">
                    </div>
                </form>
            </div>
            
            <div class="navbar-actions">
                <a href="<?php echo STUDENT_URL; ?>/reading_list.php" class="btn btn-link nav-icon">
                    <i class="bi bi-bookshelf"></i>
                    <?php if ($cart_total > 0): ?>
                        <span class="badge bg-primary"><?php echo $cart_total; ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-link nav-icon" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($unread_notifs > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="dropdown-header"><h6>Notifications</h6></div>
                        <?php
                        $notifs = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'student' AND user_id = ? ORDER BY created_at DESC LIMIT 5");
                        $notifs->bind_param("i", $_SESSION['user_id']);
                        $notifs->execute();
                        $notif_result = $notifs->get_result();
                        while ($notif = $notif_result->fetch_assoc()): ?>
                            <a href="<?php echo $notif['link'] ?: '#'; ?>" class="dropdown-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <strong><?php echo sanitize($notif['title']); ?></strong>
                                <p><?php echo sanitize(substr($notif['message'], 0, 60)); ?>...</p>
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
                        <a href="<?php echo STUDENT_URL; ?>/profile.php" class="dropdown-item"><i class="bi bi-person"></i> My Profile</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo SITE_URL; ?>/includes/logout.php" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="page-content">
            <?php if (isStudent() && !isApproved()): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Account Pending Approval:</strong> Your registration is awaiting admin approval. You can browse books but cannot issue them until approved.
                </div>
            <?php endif; ?>
            
            <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
