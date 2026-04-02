<?php
$page_title = 'Defaulter Report';
include __DIR__ . '/includes/admin_header.php';

// Mark overdue books
$conn->query("UPDATE issued_books SET status = " . ISSUE_OVERDUE . " WHERE status = " . ISSUE_APPROVED . " AND due_date < CURDATE()");

// Handle export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="defaulter_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['#', 'Student Name', 'Student ID', 'Email', 'Mobile', 'Department', 'Class', 'Book', 'ISBN', 'Issue Date', 'Due Date', 'Days Overdue', 'Fine Amount', 'Fine Status']);
    
    $stmt = $conn->prepare("SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.fine_amount, ib.fine_paid, ib.user_id,
        b.book_name, b.isbn,
        u.name as student_name, u.student_id, u.email, u.mobile,
        d.dept_name, c.class_name
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN users u ON ib.user_id = u.id
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE ib.status IN (?, ?) AND ib.due_date < CURDATE()
        ORDER BY ib.due_date ASC");
    $s1 = ISSUE_APPROVED; $s2 = ISSUE_OVERDUE;
    $stmt->bind_param("ii", $s1, $s2);
    $stmt->execute();
    $export_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $i = 1;
    foreach ($export_data as $d) {
        $days = floor((time() - strtotime($d['due_date'])) / 86400);
        $fine = max($days * FINE_PER_DAY, $d['fine_amount']);
        fputcsv($output, [$i++, $d['student_name'], $d['student_id'], $d['email'], $d['mobile'], $d['dept_name'] ?? 'N/A', $d['class_name'] ?? 'N/A', $d['book_name'], $d['isbn'] ?? '', $d['issue_date'], $d['due_date'], $days, number_format($fine, 2), $d['fine_paid'] ? 'Paid' : 'Unpaid']);
    }
    fclose($output);
    exit();
}

// Get all overdue/defaulter records
$stmt = $conn->prepare("SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.fine_amount, ib.fine_paid, ib.status, ib.user_id,
    b.book_name, b.isbn,
    u.name as student_name, u.student_id, u.email, u.mobile, u.card_number,
    d.dept_name, c.class_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    JOIN users u ON ib.user_id = u.id
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE ib.status IN (?, ?) AND ib.due_date < CURDATE()
    ORDER BY ib.due_date ASC");
$s_approved = ISSUE_APPROVED;
$s_overdue = ISSUE_OVERDUE;
$stmt->bind_param("ii", $s_approved, $s_overdue);
$stmt->execute();
$defaulters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate stats
$total_defaulters_set = [];
$total_fine = 0;
foreach ($defaulters as $d) {
    $uid = $d['user_id'] ?? 0;
    $total_defaulters_set[$uid] = true;
    $days = floor((time() - strtotime($d['due_date'])) / 86400);
    $calculated_fine = $days * FINE_PER_DAY;
    $total_fine += max($calculated_fine, $d['fine_amount']);
}
$total_defaulters = count($total_defaulters_set);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-exclamation-triangle me-2"></i>Defaulter Report</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Defaulter Report</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/report_defaulters.php?export=csv" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        <button class="btn btn-outline-primary" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print Report</button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_defaulters; ?></h3>
            <p>Total Defaulters</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-journal-x"></i></div>
        <div class="stat-info">
            <h3><?php echo count($defaulters); ?></h3>
            <p>Overdue Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-currency-dollar"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($total_fine, 2); ?></h3>
            <p>Total Fine Amount</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Overdue Books (<?php echo count($defaulters); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($defaulters)): ?>
            <div class="empty-state">
                <i class="bi bi-emoji-smile"></i>
                <h5>No Defaulters</h5>
                <p>All books have been returned on time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Department</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Fine Amount</th>
                            <th>Fine Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($defaulters as $d):
                            $days_overdue = floor((time() - strtotime($d['due_date'])) / 86400);
                            $calculated_fine = $days_overdue * FINE_PER_DAY;
                            $actual_fine = max($calculated_fine, $d['fine_amount']);
                        ?>
                            <tr class="table-danger">
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo sanitize($d['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($d['email']); ?></small>
                                </td>
                                <td><?php echo sanitize($d['student_id']); ?></td>
                                <td><?php echo sanitize($d['dept_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo sanitize($d['book_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($d['isbn'] ?? ''); ?></small>
                                </td>
                                <td><?php echo formatDate($d['issue_date']); ?></td>
                                <td><?php echo formatDate($d['due_date']); ?></td>
                                <td><span class="badge bg-danger"><?php echo $days_overdue; ?> days</span></td>
                                <td class="fw-bold text-danger"><?php echo number_format($actual_fine, 2); ?></td>
                                <td>
                                    <?php if ($d['fine_paid']): ?>
                                        <span class="status-badge available">Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge overdue">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
