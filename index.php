<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(ADMIN_URL . '/dashboard.php');
    } else {
        redirect(STUDENT_URL . '/dashboard.php');
    }
}

$page_title = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="landing-page">

    <!-- Navbar -->
    <nav class="landing-navbar">
        <div class="navbar-container">
            <a href="<?php echo SITE_URL; ?>" class="navbar-brand">
                <i class="bi bi-book-half"></i>
                University Library
            </a>
            <div class="d-flex align-items-center gap-4">
                <ul class="nav-links mb-0">
                    <li><a href="#features">Features</a></li>
                </ul>
                <div class="landing-cta">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-primary">Login</a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Your Digital Library Experience</h1>
                <p>A modern library management system designed for universities. Browse thousands of books, manage borrowings, track fines, and access e-books — all from one seamless platform.</p>
                <div class="hero-buttons">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-person-plus me-2"></i>Register
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <h3>10,000+</h3>
                        <p>Books</p>
                    </div>
                    <div class="hero-stat">
                        <h3>500+</h3>
                        <p>Students</p>
                    </div>
                    <div class="hero-stat">
                        <h3>50+</h3>
                        <p>Categories</p>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-illustration">
                    <i class="bi bi-book"></i>
                    <span>University Library</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="section-header">
            <h2>Why Choose Our Library?</h2>
            <p>Everything you need for a seamless library experience, powered by modern technology.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon blue">
                    <i class="bi bi-journal-bookmark"></i>
                </div>
                <h4>Book Catalog</h4>
                <p>Browse our extensive collection of books across all departments and categories with powerful search.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon green">
                    <i class="bi bi-tablet"></i>
                </div>
                <h4>E-Books</h4>
                <p>Access digital copies of textbooks and reference materials anytime, anywhere on your devices.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon orange">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <h4>Easy Borrowing</h4>
                <p>Request and borrow books with a few clicks. Track your borrow history and due dates effortlessly.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon red">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <h4>Fine Management</h4>
                <p>Transparent fine tracking for overdue books. View and manage your fines from your dashboard.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon cyan">
                    <i class="bi bi-bell"></i>
                </div>
                <h4>Notifications</h4>
                <p>Stay updated with real-time notifications for due dates, approvals, and library announcements.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon purple">
                    <i class="bi bi-bar-chart-line"></i>
                </div>
                <h4>Reports</h4>
                <p>Comprehensive reports and analytics for library usage, popular books, and borrowing trends.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <h4><i class="bi bi-book-half"></i> University Library</h4>
                <p>A modern library management system designed to simplify library operations and enhance the reading experience for students and faculty.</p>
            </div>
            <div class="footer-links">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/login.php">Login</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/register.php">Register</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h6>Library</h6>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/login.php">Browse Books</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h6>Contact</h6>
                <ul>
                    <li><a href="mailto:library@university.edu"><i class="bi bi-envelope me-1"></i> library@university.edu</a></li>
                    <li><a href="#"><i class="bi bi-telephone me-1"></i> +1 234 567 890</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</span>
            <span>Powered by University Library</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
