<?php
$page_title = 'Borrowing History';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];
$_issue_returned = ISSUE_RETURNED;

// Filter
$status_filter = $_GET['status'] ?? '';

// Build query conditions
$where = "ib.user_id = ?";
$params = [$user_id];
$types = 'i';

if ($status_filter !== '') {
    $where .= " AND ib.status = ?";
    $params[] = (int)$status_filter;
    $types .= 'i';
}

// Summary stats
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_borrowed = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status = ?");
$stmt->bind_param("ii", $user_id, $_issue_returned);
$stmt->execute();
$total_returned = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(fine_amount), 0) as total FROM issued_books WHERE user_id = ? AND fine_paid = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_fines_paid = $stmt->get_result()->fetch_assoc()['total'];

// Fetch history
$sql = "SELECT ib.*, b.book_name, b.cover_image, b.book_no, a.author_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    WHERE {$where}
    ORDER BY ib.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();

function getStatusBadge($status) {
    switch ($status) {
        case ISSUE_PENDING:
            return '<span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Pending</span>';
        case ISSUE_APPROVED:
            return '<span class="status-badge issued"><i class="bi bi-check-circle"></i> Approved</span>';
        case ISSUE_RETURNED:
            return '<span class="status-badge returned"><i class="bi bi-arrow-return-left"></i> Returned</span>';
        case ISSUE_OVERDUE:
            return '<span class="status-badge overdue"><i class="bi bi-exclamation-triangle"></i> Overdue</span>';
        default:
            return '<span class="status-badge">Unknown</span>';
    }
}
?>

<div class="page-header">
    <div>
        <h4>Borrowing History</h4>
        <p class="text-muted mb-0">View all your past and current book borrowings</p>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-journal-bookmark"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_borrowed; ?></h3>
            <p>Total Borrowed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-arrow-return-left"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_returned; ?></h3>
            <p>Total Returned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-currency-rupee"></i>
        </div>
        <div class="stat-info">
            <h3>&#8377;<?php echo number_format($total_fines_paid, 2); ?></h3>
            <p>Total Fines Paid</p>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Filter by Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="<?php echo ISSUE_PENDING; ?>" <?php echo $status_filter === (string)ISSUE_PENDING ? 'selected' : ''; ?>>Pending</option>
                    <option value="<?php echo ISSUE_APPROVED; ?>" <?php echo $status_filter === (string)ISSUE_APPROVED ? 'selected' : ''; ?>>Approved</option>
                    <option value="<?php echo ISSUE_RETURNED; ?>" <?php echo $status_filter === (string)ISSUE_RETURNED ? 'selected' : ''; ?>>Returned</option>
                    <option value="<?php echo ISSUE_OVERDUE; ?>" <?php echo $status_filter === (string)ISSUE_OVERDUE ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="<?php echo STUDENT_URL; ?>/history.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history"></i> Borrowing Records</h5>
        <span class="text-muted" style="font-size:0.85rem;"><?php echo $history->num_rows; ?> record(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if ($history->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                            <th>Fine Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="book-cover" style="width:36px;height:48px;font-size:0.7rem;border-radius:4px;flex-shrink:0;">
                                            <?php if ($row['cover_image']): ?>
                                                <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $row['cover_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <i class="bi bi-book" style="font-size:0.9rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong style="font-size:0.85rem;"><?php echo sanitize($row['book_name']); ?></strong>
                                            <br><small class="text-muted">#<?php echo sanitize($row['book_no']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $row['issue_date'] ? formatDate($row['issue_date']) : '-'; ?></td>
                                <td><?php echo $row['due_date'] ? formatDate($row['due_date']) : '-'; ?></td>
                                <td><?php echo $row['return_date'] ? formatDate($row['return_date']) : '<span class="text-muted">-</span>'; ?></td>
                                <td><?php echo getStatusBadge($row['status']); ?></td>
                                <td>
                                    <?php if ($row['fine_amount'] > 0): ?>
                                        <span class="text-danger fw-bold">&#8377;<?php echo number_format($row['fine_amount'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['fine_amount'] > 0): ?>
                                        <?php if ($row['fine_paid']): ?>
                                            <span class="status-badge approved"><i class="bi bi-check"></i> Paid</span>
                                        <?php else: ?>
                                            <span class="status-badge pending"><i class="bi bi-clock"></i> Unpaid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <h5>No History Found</h5>
                <p><?php echo $status_filter !== '' ? 'No records match this filter.' : 'You haven\'t borrowed any books yet.'; ?></p>
                <?php if ($status_filter !== ''): ?>
                    <a href="<?php echo STUDENT_URL; ?>/history.php" class="btn btn-outline-primary btn-sm">Clear Filter</a>
                <?php else: ?>
                    <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary btn-sm">Browse Catalog</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
