<?php
$page_title = "Librarian Dashboard";
require_once __DIR__ . '/includes/librarian_header.php';

// Stats queries
$stats = [];

// Total Books (sum of total_copies)
$stmt = $conn->prepare("SELECT SUM(total_copies) as total FROM books");
$stmt->execute();
$stats['total_books'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Total Students (approved)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE approval_status = 'approved'");
$stmt->execute();
$stats['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Books Issued Today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE DATE(issue_date) = CURDATE()");
$stmt->execute();
$stats['issued_today'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Overdue Books
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE status = 3 OR (status = 1 AND due_date < CURDATE())");
$stmt->execute();
$stats['overdue_books'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Active Reservations
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE status = 'active'");
$stmt->execute();
$stats['active_reservations'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pending Approvals
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending'");
$stmt->execute();
$stats['pending_approvals'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Recent Issues (last 10)
$stmt = $conn->prepare("
    SELECT ib.*, b.book_name, b.book_no, u.name as student_name, u.student_id as stu_id
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    JOIN users u ON ib.user_id = u.id
    ORDER BY ib.created_at DESC LIMIT 10
");
$stmt->execute();
$recent_issues = $stmt->get_result();

// Today's Activity
$stmt = $conn->prepare("
    SELECT action, description, created_at
    FROM activity_log
    WHERE DATE(created_at) = CURDATE()
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$today_activity = $stmt->get_result();

// Today's returns count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE DATE(return_date) = CURDATE()");
$stmt->execute();
$stats['returned_today'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Today's new students
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['new_students_today'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-speedometer2 me-2"></i>Librarian Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <span class="text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo date('l, M d, Y'); ?></span>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-book"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_books']); ?></h3>
            <p>Total Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="bi bi-people"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_students']); ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-journal-arrow-up"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['issued_today']); ?></h3>
            <p>Issued Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['overdue_books']); ?></h3>
            <p>Overdue Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-bookmark-star"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['active_reservations']); ?></h3>
            <p>Active Reservations</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="<?php echo ADMIN_URL; ?>/issue_book.php" class="quick-action-card">
                <i class="bi bi-journal-arrow-up"></i>
                <span>Issue Book</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/return_book.php" class="quick-action-card">
                <i class="bi bi-journal-arrow-down"></i>
                <span>Return Book</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/books.php" class="quick-action-card">
                <i class="bi bi-book"></i>
                <span>Manage Books</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/approve_students.php" class="quick-action-card">
                <i class="bi bi-person-check"></i>
                <span>Approve Students</span>
                <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $stats['pending_approvals']; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<!-- Pending Approvals Alert -->
<?php if ($stats['pending_approvals'] > 0): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-person-exclamation fs-4 me-3"></i>
        <div class="flex-grow-1">
            <strong><?php echo $stats['pending_approvals']; ?> student<?php echo $stats['pending_approvals'] > 1 ? 's' : ''; ?></strong> pending approval. Review and approve new registrations.
        </div>
        <a href="<?php echo ADMIN_URL; ?>/approve_students.php" class="btn btn-warning btn-sm">Review Now</a>
    </div>
<?php endif; ?>

<!-- Main Content Row -->
<div class="row g-4">
    <!-- Recent Issues -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-journal-bookmark me-2"></i>Recent Issues</h5>
                <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_issues->num_rows > 0): ?>
                                <?php while ($issue = $recent_issues->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo sanitize($issue['student_name']); ?>
                                            <br><small class="text-muted"><?php echo sanitize($issue['stu_id']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo sanitize($issue['book_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo sanitize($issue['book_no']); ?></small>
                                        </td>
                                        <td><?php echo formatDate($issue['issue_date']); ?></td>
                                        <td><?php echo formatDate($issue['due_date']); ?></td>
                                        <td>
                                            <?php
                                            $status_map = [0 => 'pending', 1 => 'approved', 2 => 'returned', 3 => 'overdue'];
                                            $status_labels = [0 => 'Pending', 1 => 'Issued', 2 => 'Returned', 3 => 'Overdue'];
                                            $s = $issue['status'];
                                            if ($s == 1 && $issue['due_date'] < date('Y-m-d')) {
                                                $s = 3;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_map[$s] ?? 'pending'; ?>"><?php echo $status_labels[$s] ?? 'Unknown'; ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="bi bi-journal-bookmark"></i>
                                            <h5>No Records</h5>
                                            <p>No books have been issued yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Activity -->
    <div class="col-lg-5">
        <!-- Today's Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-clipboard-data me-2"></i>Today's Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="py-2">
                            <h4 class="mb-0 text-success"><?php echo $stats['issued_today']; ?></h4>
                            <small class="text-muted">Issued</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="py-2">
                            <h4 class="mb-0 text-primary"><?php echo $stats['returned_today']; ?></h4>
                            <small class="text-muted">Returned</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="py-2">
                            <h4 class="mb-0 text-info"><?php echo $stats['new_students_today']; ?></h4>
                            <small class="text-muted">New Students</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Activity Log -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-activity me-2"></i>Today's Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($today_activity->num_rows > 0): ?>
                                <?php while ($log = $today_activity->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php echo $log['action'] === 'login' ? 'info' : ($log['action'] === 'issue' ? 'success' : ($log['action'] === 'return' ? 'primary' : 'secondary')); ?>"><?php echo sanitize($log['action']); ?></span></td>
                                        <td><?php echo sanitize(substr($log['description'] ?? '', 0, 50)); ?></td>
                                        <td><small><?php echo formatDateTime($log['created_at']); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <i class="bi bi-activity"></i>
                                            <h5>No Activity</h5>
                                            <p>No activity recorded today.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/librarian_footer.php';
?>
