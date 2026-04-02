<?php
$page_title = 'My Books';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];
$_issue_approved = ISSUE_APPROVED;
$_issue_overdue = ISSUE_OVERDUE;
$_issue_pending = ISSUE_PENDING;

$csrf_token = generateCSRFToken();

// Handle return request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_return') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(STUDENT_URL . '/my_books.php');
    }
    $issue_id = intval($_POST['issue_id'] ?? 0);
    if ($issue_id > 0) {
        // Verify this issue belongs to this student
        $stmt = $conn->prepare("SELECT ib.*, b.book_name FROM issued_books ib JOIN books b ON ib.book_id = b.book_id WHERE ib.issue_id = ? AND ib.user_id = ? AND ib.status IN (?, ?)");
        $s_approved = ISSUE_APPROVED;
        $s_overdue = ISSUE_OVERDUE;
        $stmt->bind_param("iiii", $issue_id, $user_id, $s_approved, $s_overdue);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();
        if ($issue) {
            // Mark as pending return (status 4 = return_requested)
            $stmt = $conn->prepare("UPDATE issued_books SET status = 4 WHERE issue_id = ?");
            $stmt->bind_param("i", $issue_id);
            $stmt->execute();
            // Notify admin
            $auth->createAdminNotification('Return Request', "Student {$_SESSION['name']} has requested to return '{$issue['book_name']}'.", ADMIN_URL . '/return_book.php');
            $auth->logActivity('student', $user_id, 'request_return', "Requested return of book: {$issue['book_name']}");
            setFlashMessage('success', "Return request for '{$issue['book_name']}' submitted. Please wait for admin approval.");
        }
    }
    redirect(STUDENT_URL . '/my_books.php');
}

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status IN (?, ?, ?)");
$_issue_return_req = 4;
$stmt->bind_param("iiii", $user_id, $_issue_approved, $_issue_overdue, $_issue_return_req);
$stmt->execute();
$current_count = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status = ?");
$stmt->bind_param("ii", $user_id, $_issue_overdue);
$stmt->execute();
$overdue_count = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(fine_amount), 0) as total FROM issued_books WHERE user_id = ? AND fine_paid = 0 AND fine_amount > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_fines = $stmt->get_result()->fetch_assoc()['total'];

// Currently issued books (approved + overdue)
$stmt = $conn->prepare("SELECT ib.*, b.book_name, b.cover_image, b.book_no, a.author_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    WHERE ib.user_id = ? AND ib.status IN (?, ?, ?)
    ORDER BY ib.due_date ASC");
$_issue_return_req = 4;
$stmt->bind_param("iiii", $user_id, $_issue_approved, $_issue_overdue, $_issue_return_req);
$stmt->execute();
$current_books = $stmt->get_result();

// Pending issue requests
$stmt = $conn->prepare("SELECT ib.*, b.book_name, b.cover_image, a.author_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    WHERE ib.user_id = ? AND ib.status = ?
    ORDER BY ib.created_at DESC");
$stmt->bind_param("ii", $user_id, $_issue_pending);
$stmt->execute();
$pending_books = $stmt->get_result();
?>

<div class="page-header">
    <div>
        <h4>My Books</h4>
        <p class="text-muted mb-0">Manage your borrowed books and requests</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $current_count; ?></h3>
            <p>Currently Borrowed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overdue_count; ?></h3>
            <p>Overdue Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-currency-rupee"></i>
        </div>
        <div class="stat-info">
            <h3>&#8377;<?php echo number_format($total_fines, 2); ?></h3>
            <p>Total Outstanding Fines</p>
        </div>
    </div>
</div>

<!-- Currently Borrowed -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-journal-bookmark"></i> Currently Borrowed Books</h5>
    </div>
    <div class="card-body p-0">
        <?php if ($current_books->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Left / Overdue</th>
                            <th>Fine</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $current_books->fetch_assoc()):
                            $today = new DateTime();
                            $due = new DateTime($book['due_date']);
                            $is_overdue = $today > $due;
                            $days_diff = $today->diff($due)->days;
                            $fine = calculateFine($book['due_date']);
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="book-cover" style="width:40px;height:54px;font-size:0.8rem;border-radius:4px;flex-shrink:0;">
                                            <?php if ($book['cover_image']): ?>
                                                <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <i class="bi bi-book" style="font-size:1rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong style="font-size:0.88rem;"><?php echo sanitize($book['book_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo sanitize($book['author_name']); ?></small>
                                            <br><small class="text-muted">#<?php echo sanitize($book['book_no']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatDate($book['issue_date']); ?></td>
                                <td><?php echo formatDate($book['due_date']); ?></td>
                                <td>
                                    <?php if ($is_overdue): ?>
                                        <span class="text-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $days_diff; ?> day(s) overdue</span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="bi bi-clock"></i> <?php echo $days_diff; ?> day(s) left</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fine > 0): ?>
                                        <span class="text-danger fw-bold">&#8377;<?php echo number_format($fine, 2); ?></span>
                                        <br><small class="text-muted">&#8377;<?php echo FINE_PER_DAY; ?>/day</small>
                                    <?php else: ?>
                                        <span class="text-success">No Fine</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($book['status'] == ISSUE_OVERDUE): ?>
                                        <span class="status-badge overdue"><i class="bi bi-exclamation-triangle"></i> Overdue</span>
                                    <?php elseif ($book['status'] == 4): ?>
                                        <span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Return Requested</span>
                                    <?php else: ?>
                                        <span class="status-badge issued"><i class="bi bi-check-circle"></i> Issued</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($book['status'], [ISSUE_APPROVED, ISSUE_OVERDUE])): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Request to return this book?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="request_return">
                                            <input type="hidden" name="issue_id" value="<?php echo $book['issue_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-return-left"></i> Return</button>
                                        </form>
                                    <?php elseif ($book['status'] == 4): ?>
                                        <small class="text-muted">Waiting for approval</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-journal-x"></i>
                <h5>No Books Currently Borrowed</h5>
                <p>You don't have any books checked out right now.</p>
                <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary btn-sm">Browse Catalog</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pending Requests -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-hourglass-split"></i> Pending Issue Requests</h5>
    </div>
    <div class="card-body p-0">
        <?php if ($pending_books->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $pending_books->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="book-cover" style="width:40px;height:54px;font-size:0.8rem;border-radius:4px;flex-shrink:0;">
                                            <?php if ($book['cover_image']): ?>
                                                <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <i class="bi bi-book" style="font-size:1rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong style="font-size:0.88rem;"><?php echo sanitize($book['book_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo sanitize($book['author_name']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatDateTime($book['created_at']); ?></td>
                                <td><span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Pending Approval</span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5>No Pending Requests</h5>
                <p>You have no pending issue requests.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
