<?php
$page_title = "Dashboard";
require_once __DIR__ . '/includes/admin_header.php';

// Stats queries
$stats = [];

// Total Books (sum of total_copies)
$stmt = $conn->prepare("SELECT SUM(total_copies) as total FROM books");
$stmt->execute();
$stats['total_books'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Available Books (sum of available_copies)
$stmt = $conn->prepare("SELECT SUM(available_copies) as total FROM books");
$stmt->execute();
$stats['available_books'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Total Students
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE approval_status = 'approved'");
$stmt->execute();
$stats['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pending Approvals
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending'");
$stmt->execute();
$stats['pending_approvals'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Books Issued This Month
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE MONTH(issue_date) = MONTH(CURRENT_DATE()) AND YEAR(issue_date) = YEAR(CURRENT_DATE())");
$stmt->execute();
$stats['issued_this_month'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Overdue Books
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE status = 3 OR (status = 1 AND due_date < CURDATE())");
$stmt->execute();
$stats['overdue_books'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Recent Issued Books (last 10)
$stmt = $conn->prepare("
    SELECT ib.*, b.book_name, b.book_no, u.name as student_name, u.student_id as stu_id
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    JOIN users u ON ib.user_id = u.id
    ORDER BY ib.created_at DESC LIMIT 10
");
$stmt->execute();
$recent_issues = $stmt->get_result();

// Recent Activity Log (last 10)
$stmt = $conn->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_activity = $stmt->get_result();

// Chart Data: Books by Category
$stmt = $conn->prepare("
    SELECT c.cat_name, COUNT(b.book_id) as book_count
    FROM categories c
    LEFT JOIN books b ON c.cat_id = b.cat_id
    GROUP BY c.cat_id, c.cat_name
    HAVING book_count > 0
    ORDER BY book_count DESC
");
$stmt->execute();
$cat_result = $stmt->get_result();
$chart_labels = [];
$chart_data = [];
$chart_colors = ['#4361ee', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#7c3aed', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];
while ($row = $cat_result->fetch_assoc()) {
    $chart_labels[] = $row['cat_name'];
    $chart_data[] = (int)$row['book_count'];
}

// Chart Data: Monthly Issues (last 6 months)
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(issue_date, '%b %Y') as month_label,
           MONTH(issue_date) as m, YEAR(issue_date) as y,
           COUNT(*) as issue_count
    FROM issued_books
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(issue_date), MONTH(issue_date)
    ORDER BY y, m
");
$stmt->execute();
$month_result = $stmt->get_result();
$month_labels = [];
$month_data = [];
while ($row = $month_result->fetch_assoc()) {
    $month_labels[] = $row['month_label'];
    $month_data[] = (int)$row['issue_count'];
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-book"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['total_books']); ?></h3>
            <p>Total Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-book-half"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['available_books']); ?></h3>
            <p>Available Books</p>
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
        <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['pending_approvals']); ?></h3>
            <p>Pending Approvals</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-journal-arrow-up"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['issued_this_month']); ?></h3>
            <p>Issued This Month</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stats['overdue_books']); ?></h3>
            <p>Overdue Books</p>
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
            <a href="<?php echo ADMIN_URL; ?>/books.php?action=add" class="quick-action-card">
                <i class="bi bi-plus-circle"></i>
                <span>Add Book</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/approve_students.php" class="quick-action-card">
                <i class="bi bi-person-check"></i>
                <span>Approve Students</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/report_defaulters.php" class="quick-action-card">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>View Reports</span>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/categories.php" class="quick-action-card">
                <i class="bi bi-tags"></i>
                <span>Manage Categories</span>
            </a>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart me-2"></i>Books by Category</h5>
            </div>
            <div class="card-body">
                <?php if (count($chart_data) > 0): ?>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-pie-chart"></i>
                        <h5>No Data</h5>
                        <p>No books available to display chart.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-bar-chart me-2"></i>Monthly Issues</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-journal-bookmark me-2"></i>Recent Issued Books</h5>
                <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Student</th>
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
                                            <strong><?php echo sanitize($issue['book_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo sanitize($issue['book_no']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo sanitize($issue['student_name']); ?>
                                            <br><small class="text-muted"><?php echo sanitize($issue['stu_id']); ?></small>
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
                                <tr><td colspan="5"><div class="empty-state"><i class="bi bi-journal-bookmark"></i><h5>No Records</h5><p>No books have been issued yet.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                <a href="<?php echo ADMIN_URL; ?>/report_activity.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                            <?php if ($recent_activity->num_rows > 0): ?>
                                <?php while ($log = $recent_activity->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php echo $log['user_type'] === 'admin' ? 'primary' : 'info'; ?>"><?php echo sanitize($log['action']); ?></span></td>
                                        <td><?php echo sanitize(substr($log['description'] ?? '', 0, 50)); ?></td>
                                        <td><small><?php echo formatDateTime($log['created_at']); ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3"><div class="empty-state"><i class="bi bi-activity"></i><h5>No Activity</h5><p>No recent activity recorded.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script>
document.addEventListener("DOMContentLoaded", function() {
    var catCtx = document.getElementById("categoryChart");
    if (catCtx) {
        new Chart(catCtx, {
            type: "doughnut",
            data: {
                labels: ' . json_encode($chart_labels) . ',
                datasets: [{
                    data: ' . json_encode($chart_data) . ',
                    backgroundColor: ' . json_encode(array_slice($chart_colors, 0, count($chart_labels))) . ',
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "bottom", labels: { padding: 15, usePointStyle: true, font: { size: 12 } } }
                }
            }
        });
    }

    var monthCtx = document.getElementById("monthlyChart");
    if (monthCtx) {
        new Chart(monthCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($month_labels) . ',
                datasets: [{
                    label: "Books Issued",
                    data: ' . json_encode($month_data) . ',
                    backgroundColor: "rgba(67, 97, 238, 0.8)",
                    borderColor: "#4361ee",
                    borderWidth: 1,
                    borderRadius: 6,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>';

require_once __DIR__ . '/includes/admin_footer.php';
?>
