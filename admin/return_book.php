<?php
$page_title = 'Return Book';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle return action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/return_book.php');
    }

    $issue_id = intval($_POST['issue_id'] ?? 0);
    $fine_paid = isset($_POST['fine_paid']) ? 1 : 0;

    if ($issue_id <= 0) {
        setFlashMessage('danger', 'Invalid issue ID.');
        redirect(ADMIN_URL . '/return_book.php');
    }

    $stmt = $conn->prepare("SELECT ib.*, b.book_name, u.name as student_name
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN users u ON ib.user_id = u.id
        WHERE ib.issue_id = ? AND ib.status IN (?, ?)");
    $status_approved = ISSUE_APPROVED;
    $status_overdue = ISSUE_OVERDUE;
    $stmt->bind_param("iii", $issue_id, $status_approved, $status_overdue);
    $stmt->execute();
    $issue = $stmt->get_result()->fetch_assoc();

    if (!$issue) {
        setFlashMessage('danger', 'Issued book record not found or already returned.');
        redirect(ADMIN_URL . '/return_book.php');
    }

    $conn->begin_transaction();
    try {
        $return_date = date('Y-m-d');
        $fine_amount = calculateFine($issue['due_date']);
        $new_status = ISSUE_RETURNED;
        $returned_to = $_SESSION['user_id'];

        $stmt = $conn->prepare("UPDATE issued_books SET return_date = ?, status = ?, fine_amount = ?, fine_paid = ?, returned_to = ? WHERE issue_id = ?");
        $stmt->bind_param("sidsii", $return_date, $new_status, $fine_amount, $fine_paid, $returned_to, $issue_id);
        $stmt->execute();

        // Increment available copies
        $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
        $stmt->bind_param("i", $issue['book_id']);
        $stmt->execute();

        // Create notification for student
        $notif_title = 'Book Returned';
        $notif_message = "The book '{$issue['book_name']}' has been returned successfully.";
        if ($fine_amount > 0) {
            $notif_message .= " Fine: " . number_format($fine_amount, 2) . ($fine_paid ? " (Paid)" : " (Pending)");
        }
        $auth->createStudentNotification($issue['user_id'], $notif_title, $notif_message, STUDENT_URL . '/my_books.php');

        // Log activity
        $auth->logActivity('admin', $_SESSION['user_id'], 'return_book', "Returned book '{$issue['book_name']}' from student {$issue['student_name']}");

        $conn->commit();
        setFlashMessage('success', "Book '{$issue['book_name']}' has been returned successfully." . ($fine_amount > 0 ? " Fine: " . number_format($fine_amount, 2) : ""));
        redirect(ADMIN_URL . '/return_book.php');
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('danger', 'Failed to process return. Please try again.');
        redirect(ADMIN_URL . '/return_book.php');
    }
}

// Handle fine payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_fine') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/return_book.php');
    }

    $issue_id = intval($_POST['issue_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE issued_books SET fine_paid = 1 WHERE issue_id = ?");
    $stmt->bind_param("i", $issue_id);
    $stmt->execute();

    $auth->logActivity('admin', $_SESSION['user_id'], 'pay_fine', "Marked fine as paid for issue #$issue_id");
    setFlashMessage('success', 'Fine marked as paid.');
    redirect(ADMIN_URL . '/return_book.php');
}

// Search
$search = trim($_GET['search'] ?? '');

$issued_books = [];
if ($search) {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT ib.*, b.book_name, b.isbn, b.cover_image, u.name as student_name, u.student_id
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN users u ON ib.user_id = u.id
        WHERE ib.status IN (?, ?) AND (u.name LIKE ? OR u.student_id LIKE ? OR b.book_name LIKE ?)
        ORDER BY ib.due_date ASC");
    $s_approved = ISSUE_APPROVED;
    $s_overdue = ISSUE_OVERDUE;
    $stmt->bind_param("iisss", $s_approved, $s_overdue, $like, $like, $like);
    $stmt->execute();
    $issued_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT ib.*, b.book_name, b.isbn, b.cover_image, u.name as student_name, u.student_id
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN users u ON ib.user_id = u.id
        WHERE ib.status IN (?, ?)
        ORDER BY ib.due_date ASC");
    $s_approved = ISSUE_APPROVED;
    $s_overdue = ISSUE_OVERDUE;
    $stmt->bind_param("ii", $s_approved, $s_overdue);
    $stmt->execute();
    $issued_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Mark overdue books
$conn->query("UPDATE issued_books SET status = " . ISSUE_OVERDUE . " WHERE status = " . ISSUE_APPROVED . " AND due_date < CURDATE()");
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-journal-arrow-down me-2"></i>Return Book</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Return Book</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search by Student Name, Student ID, or Book Name" value="<?php echo sanitize($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                <?php if ($search): ?>
                    <a href="return_book.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($issued_books)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-journal-check"></i>
                <h5>No Outstanding Books</h5>
                <p><?php echo $search ? 'No issued books match your search.' : 'All books have been returned.'; ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h5>Outstanding Books (<?php echo count($issued_books); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:50px"></th>
                            <th>Student</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Late</th>
                            <th>Fine</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issued_books as $ib):
                            $is_overdue = strtotime($ib['due_date']) < strtotime(date('Y-m-d'));
                            $days_overdue = $is_overdue ? floor((time() - strtotime($ib['due_date'])) / 86400) : 0;
                            $fine = $days_overdue * FINE_PER_DAY;
                            $actual_fine = max($fine, $ib['fine_amount']);
                        ?>
                            <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                <td>
                                    <?php if (!empty($ib['cover_image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $ib['cover_image']; ?>" alt="" style="width:35px;height:50px;object-fit:cover;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                                    <?php else: ?>
                                        <div style="width:35px;height:50px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;"><i class="bi bi-book"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($ib['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($ib['student_id']); ?></small>
                                </td>
                                <td><?php echo sanitize($ib['book_name']); ?></td>
                                <td><?php echo formatDate($ib['issue_date']); ?></td>
                                <td>
                                    <?php echo formatDate($ib['due_date']); ?>
                                    <?php if ($is_overdue): ?>
                                        <br><small class="text-danger"><i class="bi bi-exclamation-circle"></i> Overdue</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $days_overdue; ?></td>
                                <td>
                                    <?php if ($actual_fine > 0): ?>
                                        <span class="text-danger fw-bold">&#8377;<?php echo number_format($actual_fine, 2); ?></span>
                                        <?php if ($ib['fine_paid']): ?>
                                            <span class="status-badge available ms-1">Paid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ib['status'] == ISSUE_OVERDUE): ?>
                                        <span class="status-badge overdue">Overdue</span>
                                    <?php else: ?>
                                        <span class="status-badge issued">Issued</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns">
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Confirm return of this book?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="issue_id" value="<?php echo $ib['issue_id']; ?>">
                                        <?php if ($actual_fine > 0 && !$ib['fine_paid']): ?>
                                            <div class="form-check form-check-inline mb-1">
                                                <input class="form-check-input" type="checkbox" name="fine_paid" id="fine_paid_<?php echo $ib['issue_id']; ?>" value="1">
                                                <label class="form-check-label" for="fine_paid_<?php echo $ib['issue_id']; ?>">Fine Paid</label>
                                            </div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Return</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
