<?php
$page_title = 'Return Approvals';
require_once __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/return_approvals.php');
    }

    $action = $_POST['action'] ?? '';
    $issue_id = intval($_POST['issue_id'] ?? 0);

    if ($issue_id <= 0) {
        setFlashMessage('danger', 'Invalid issue ID.');
        redirect(ADMIN_URL . '/return_approvals.php');
    }

    if ($action === 'approve_return') {
        $stmt = $conn->prepare("SELECT ib.*, b.book_name, u.name AS student_name, u.email AS student_email
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.book_id
            JOIN users u ON ib.user_id = u.id
            WHERE ib.issue_id = ? AND ib.status = ?");
        $status_requested = ISSUE_RETURN_REQUESTED;
        $stmt->bind_param("ii", $issue_id, $status_requested);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();

        if (!$issue) {
            setFlashMessage('danger', 'Return request not found or already processed.');
            redirect(ADMIN_URL . '/return_approvals.php');
        }

        $conn->begin_transaction();
        try {
            $return_date = date('Y-m-d');
            $fine_amount = calculateFine($issue['due_date']);
            $new_status = ISSUE_RETURNED;
            $returned_to = $_SESSION['user_id'];

            $stmt = $conn->prepare("UPDATE issued_books SET return_date = ?, status = ?, fine_amount = ?, returned_to = ? WHERE issue_id = ?");
            $stmt->bind_param("sidsi", $return_date, $new_status, $fine_amount, $returned_to, $issue_id);
            $stmt->execute();

            // Increment available copies
            $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
            $stmt->bind_param("i", $issue['book_id']);
            $stmt->execute();

            // Notify student
            $notif_message = "Your return request for '{$issue['book_name']}' has been approved. The book has been marked as returned.";
            if ($fine_amount > 0) {
                $notif_message .= " Fine: \u20B9" . number_format($fine_amount, 2);
            }
            $auth->createStudentNotification($issue['user_id'], 'Return Approved', $notif_message, STUDENT_URL . '/my_books.php');

            // Log activity
            $auth->logActivity('admin', $_SESSION['user_id'], 'approve_return', "Approved return of '{$issue['book_name']}' from student {$issue['student_name']}");

            $conn->commit();
            setFlashMessage('success', "Return approved. '{$issue['book_name']}' has been marked as returned." . ($fine_amount > 0 ? " Fine: \u20B9" . number_format($fine_amount, 2) : ""));
            redirect(ADMIN_URL . '/return_approvals.php');
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('danger', 'Failed to approve return. Please try again.');
            redirect(ADMIN_URL . '/return_approvals.php');
        }
    }

    if ($action === 'reject_return') {
        $stmt = $conn->prepare("SELECT ib.*, b.book_name, u.name AS student_name
            FROM issued_books ib
            JOIN books b ON ib.book_id = b.book_id
            JOIN users u ON ib.user_id = u.id
            WHERE ib.issue_id = ? AND ib.status = ?");
        $status_requested = ISSUE_RETURN_REQUESTED;
        $stmt->bind_param("ii", $issue_id, $status_requested);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();

        if (!$issue) {
            setFlashMessage('danger', 'Return request not found or already processed.');
            redirect(ADMIN_URL . '/return_approvals.php');
        }

        $conn->begin_transaction();
        try {
            // Revert status back to approved (1)
            $new_status = ISSUE_APPROVED;
            $stmt = $conn->prepare("UPDATE issued_books SET status = ? WHERE issue_id = ?");
            $stmt->bind_param("ii", $new_status, $issue_id);
            $stmt->execute();

            // Notify student
            $reason = trim($_POST['reason'] ?? '');
            $notif_message = "Your return request for '{$issue['book_name']}' has been rejected.";
            if ($reason) {
                $notif_message .= " Reason: " . $reason;
            }
            $auth->createStudentNotification($issue['user_id'], 'Return Rejected', $notif_message, STUDENT_URL . '/my_books.php');

            // Log activity
            $auth->logActivity('admin', $_SESSION['user_id'], 'reject_return', "Rejected return of '{$issue['book_name']}' from student {$issue['student_name']}");

            $conn->commit();
            setFlashMessage('success', "Return request for '{$issue['book_name']}' has been rejected.");
            redirect(ADMIN_URL . '/return_approvals.php');
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('danger', 'Failed to reject return. Please try again.');
            redirect(ADMIN_URL . '/return_approvals.php');
        }
    }

    redirect(ADMIN_URL . '/return_approvals.php');
}

// Fetch pending return requests
$stmt = $conn->prepare("SELECT ib.*, b.book_name, b.cover_image, b.book_no, a.author_name,
    u.name AS student_name, u.student_id, u.email AS student_email
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    JOIN users u ON ib.user_id = u.id
    WHERE ib.status = ?
    ORDER BY ib.created_at DESC");
$status_requested = ISSUE_RETURN_REQUESTED;
$stmt->bind_param("i", $status_requested);
$stmt->execute();
$return_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$pending_returns_count = count($return_requests);

$today = date('Y-m-d');
$stmt_today = $conn->prepare("SELECT COUNT(*) AS cnt FROM issued_books WHERE status = ? AND return_date = ?");
$status_returned = ISSUE_RETURNED;
$stmt_today->bind_param("is", $status_returned, $today);
$stmt_today->execute();
$returns_today_count = $stmt_today->get_result()->fetch_assoc()['cnt'];
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-journal-check me-2"></i>Return Approvals</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Return Approvals</li>
            </ol>
        </nav>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info"><h3><?php echo $pending_returns_count; ?></h3><p>Pending Returns</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-info"><h3><?php echo $returns_today_count; ?></h3><p>Returns Today</p></div>
    </div>
</div>

<?php if ($pending_returns_count > 0): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <strong><?php echo $pending_returns_count; ?> return request<?php echo $pending_returns_count > 1 ? 's' : ''; ?> awaiting approval</strong>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Pending Return Requests (<?php echo $pending_returns_count; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($return_requests)): ?>
            <div class="empty-state">
                <i class="bi bi-journal-check"></i>
                <h5>All Clear!</h5>
                <p>No pending return requests at this time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:50px"></th>
                            <th>Student</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Fine</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return_requests as $req):
                            $is_overdue = strtotime($req['due_date']) < strtotime(date('Y-m-d'));
                            $days_overdue = $is_overdue ? floor((time() - strtotime($req['due_date'])) / 86400) : 0;
                            $fine = $days_overdue * FINE_PER_DAY;
                            $actual_fine = max($fine, $req['fine_amount']);
                        ?>
                            <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                <td>
                                    <?php if (!empty($req['cover_image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $req['cover_image']; ?>" alt="" style="width:35px;height:50px;object-fit:cover;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                                    <?php else: ?>
                                        <div style="width:35px;height:50px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;"><i class="bi bi-book"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($req['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($req['student_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($req['book_name']); ?></strong><br>
                                    <?php if (!empty($req['book_no'])): ?>
                                        <small class="text-muted">#<?php echo sanitize($req['book_no']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($req['author_name'])): ?>
                                        <br><small class="text-muted">by <?php echo sanitize($req['author_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($req['issue_date']); ?></td>
                                <td>
                                    <?php echo formatDate($req['due_date']); ?>
                                    <?php if ($is_overdue): ?>
                                        <br><small class="text-danger"><i class="bi bi-exclamation-circle"></i> Overdue (<?php echo $days_overdue; ?> days)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($actual_fine > 0): ?>
                                        <span class="text-danger fw-bold">&#8377;<?php echo number_format($actual_fine, 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns">
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Approve this return request? The book will be marked as returned.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="approve_return">
                                        <input type="hidden" name="issue_id" value="<?php echo $req['issue_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#reject_<?php echo $req['issue_id']; ?>"><i class="bi bi-x-lg me-1"></i>Reject</button>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="reject_<?php echo $req['issue_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="reject_return">
                                                    <input type="hidden" name="issue_id" value="<?php echo $req['issue_id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Return Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to reject the return request from <strong><?php echo sanitize($req['student_name']); ?></strong> (<?php echo sanitize($req['student_id']); ?>)?</p>
                                                        <p>Book: <strong><?php echo sanitize($req['book_name']); ?></strong></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Reason (optional)</label>
                                                            <textarea class="form-control" name="reason" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Return</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
require_once __DIR__ . '/includes/admin_footer.php';
?>
