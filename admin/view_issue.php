<?php
$page_title = 'View Issue Details';
require_once 'includes/admin_header.php';

$issue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($issue_id <= 0) {
    setFlashMessage('danger', 'Invalid issue ID.');
    redirect(ADMIN_URL . '/issued_books.php');
}

$sql = "SELECT ib.*,
               b.book_name, b.book_no, b.isbn, b.cover_image, b.total_copies, b.available_copies,
               a.author_name,
               c.cat_name,
               u.student_id, u.name AS student_name, u.email AS student_email, u.mobile AS student_mobile, u.card_number,
               admin_issued.name AS issued_by_name,
               admin_returned.name AS returned_to_name
        FROM issued_books ib
        LEFT JOIN books b ON ib.book_id = b.book_id
        LEFT JOIN authors a ON b.author_id = a.author_id
        LEFT JOIN categories c ON b.cat_id = c.cat_id
        LEFT JOIN users u ON ib.user_id = u.id
        LEFT JOIN admins admin_issued ON ib.issued_by = admin_issued.id
        LEFT JOIN admins admin_returned ON ib.returned_to = admin_returned.id
        WHERE ib.issue_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $issue_id);
$stmt->execute();
$issue = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$issue) {
    setFlashMessage('danger', 'Issued book record not found.');
    redirect(ADMIN_URL . '/issued_books.php');
}

$status_labels = [
    0 => ['label' => 'Pending', 'class' => 'bg-warning text-dark'],
    1 => ['label' => 'Issued', 'class' => 'bg-primary'],
    2 => ['label' => 'Returned', 'class' => 'bg-success'],
    3 => ['label' => 'Overdue', 'class' => 'bg-danger'],
];

$status_info = $status_labels[$issue['status']] ?? ['label' => 'Unknown', 'class' => 'bg-secondary'];

$today = new DateTime();
$due_date = new DateTime($issue['due_date']);
$days_remaining = null;
$days_overdue = null;

if ($issue['status'] != ISSUE_RETURNED) {
    if ($today > $due_date) {
        $days_overdue = $today->diff($due_date)->days;
    } else {
        $days_remaining = $today->diff($due_date)->days;
    }
} else if ($issue['return_date']) {
    $return_date = new DateTime($issue['return_date']);
    if ($return_date > $due_date) {
        $days_overdue = $return_date->diff($due_date)->days;
    }
}

$cover_path = !empty($issue['cover_image']) && file_exists(UPLOAD_DIR . 'covers/' . $issue['cover_image'])
    ? SITE_URL . '/uploads/covers/' . $issue['cover_image']
    : null;
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-journal-bookmark me-2"></i>Issue Details</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/issued_books.php">Issued Books</a></li>
                <li class="breadcrumb-item active">View Details</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column - Book Card -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if ($cover_path): ?>
                    <img src="<?php echo $cover_path; ?>" alt="<?php echo sanitize($issue['book_name']); ?>" class="img-fluid rounded mb-3" style="max-height: 300px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 250px;">
                        <i class="bi bi-book text-muted" style="font-size: 5rem;"></i>
                    </div>
                <?php endif; ?>
                <h5 class="mb-1"><?php echo sanitize($issue['book_name']); ?></h5>
                <p class="text-muted mb-2">
                    <i class="bi bi-person-badge me-1"></i><?php echo sanitize($issue['author_name'] ?? 'N/A'); ?>
                </p>
                <p class="text-muted mb-2">
                    <i class="bi bi-tag me-1"></i><?php echo sanitize($issue['cat_name'] ?? 'N/A'); ?>
                </p>
            </div>
            <div class="card-footer bg-transparent">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block">ISBN</small>
                        <strong><?php echo sanitize($issue['isbn'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Book No</small>
                        <strong><?php echo sanitize($issue['book_no'] ?? 'N/A'); ?></strong>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-12">
                        <small class="text-muted d-block">Copies Available</small>
                        <strong><?php echo (int)$issue['available_copies']; ?> / <?php echo (int)$issue['total_copies']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Details -->
    <div class="col-lg-8">
        <!-- Issue Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Issue Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Issue ID</label>
                        <strong>#<?php echo $issue['issue_id']; ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Status</label>
                        <span class="status-badge <?php echo $status_info['class']; ?>"><?php echo $status_info['label']; ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Issue Date</label>
                        <strong><?php echo $issue['issue_date'] ? formatDate($issue['issue_date']) : 'N/A'; ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Due Date</label>
                        <strong><?php echo $issue['due_date'] ? formatDate($issue['due_date']) : 'N/A'; ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Return Date</label>
                        <strong><?php echo $issue['return_date'] ? formatDate($issue['return_date']) : '<span class="text-muted">Not returned</span>'; ?></strong>
                    </div>
                    <div class="col-md-4">
                        <?php if ($days_overdue !== null): ?>
                            <label class="text-muted small d-block">Days Overdue</label>
                            <strong class="text-danger"><?php echo $days_overdue; ?> day<?php echo $days_overdue != 1 ? 's' : ''; ?></strong>
                        <?php elseif ($days_remaining !== null): ?>
                            <label class="text-muted small d-block">Days Remaining</label>
                            <strong class="<?php echo $days_remaining <= 3 ? 'text-warning' : 'text-success'; ?>"><?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?></strong>
                        <?php else: ?>
                            <label class="text-muted small d-block">Duration</label>
                            <strong class="text-muted">-</strong>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person me-2"></i>Student Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Student ID</label>
                        <strong><?php echo sanitize($issue['student_id'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Name</label>
                        <strong><?php echo sanitize($issue['student_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Email</label>
                        <strong><?php echo sanitize($issue['student_email'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Mobile</label>
                        <strong><?php echo sanitize($issue['student_mobile'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Card Number</label>
                        <strong><?php echo sanitize($issue['card_number'] ?? 'N/A'); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($issue['fine_amount'] > 0 || $days_overdue !== null): ?>
        <!-- Fine Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Fine Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Fine Amount</label>
                        <strong class="text-danger"><?php echo number_format($issue['fine_amount'], 2); ?></strong>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Fine Status</label>
                        <?php if ($issue['fine_paid']): ?>
                            <span class="status-badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="status-badge bg-danger">Unpaid</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small d-block">Days Overdue</label>
                        <strong><?php echo $days_overdue !== null ? $days_overdue : 0; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Issued By / Returned To Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Handled By</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Issued By</label>
                        <strong><?php echo sanitize($issue['issued_by_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Returned To</label>
                        <strong><?php echo sanitize($issue['returned_to_name'] ?? ($issue['status'] == ISSUE_RETURNED ? 'N/A' : '-')); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($issue['remarks'])): ?>
        <!-- Remarks Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Remarks</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo sanitize($issue['remarks']); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print();">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Issued Books
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
