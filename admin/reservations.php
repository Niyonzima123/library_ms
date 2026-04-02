<?php
$page_title = 'Manage Reservations';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/reservations.php');
    }

    if ($action === 'fulfill') {
        $reservation_id = intval($_POST['reservation_id'] ?? 0);

        if ($reservation_id > 0) {
            $stmt = $conn->prepare("SELECT r.*, b.book_name, b.available_copies, b.status AS book_status, u.name AS student_name, u.approval_status, u.max_books_allowed,
                (SELECT COUNT(*) FROM issued_books WHERE user_id = r.user_id AND status IN (?, ?, ?)) as current_books
                FROM reservations r
                JOIN books b ON r.book_id = b.book_id
                JOIN users u ON r.user_id = u.id
                WHERE r.reservation_id = ? AND r.status = 'active'");
            $s_pending = ISSUE_PENDING;
            $s_approved = ISSUE_APPROVED;
            $s_overdue = ISSUE_OVERDUE;
            $stmt->bind_param("iiii", $s_pending, $s_approved, $s_overdue, $reservation_id);
            $stmt->execute();
            $reservation = $stmt->get_result()->fetch_assoc();

            if (!$reservation) {
                setFlashMessage('danger', 'Reservation not found or is not active.');
            } elseif ($reservation['approval_status'] !== STATUS_APPROVED) {
                setFlashMessage('danger', 'Student is not approved. Cannot issue book.');
            } elseif ($reservation['current_books'] >= $reservation['max_books_allowed']) {
                setFlashMessage('danger', 'Student has reached the maximum book limit (' . $reservation['max_books_allowed'] . '). Cannot issue book.');
            } elseif ($reservation['available_copies'] <= 0) {
                setFlashMessage('danger', 'No copies of this book are available.');
            } elseif ($reservation['book_status'] !== 'available') {
                setFlashMessage('danger', 'This book is currently unavailable.');
            } else {
                $conn->begin_transaction();
                try {
                    $issue_date = date('Y-m-d');
                    $due_date = date('Y-m-d', strtotime('+' . DEFAULT_BORROW_DAYS . ' days'));
                    $status = ISSUE_APPROVED;
                    $admin_id = $_SESSION['user_id'];
                    $remarks = 'Issued from reservation #' . $reservation_id;

                    $stmt = $conn->prepare("INSERT INTO issued_books (book_id, user_id, issue_date, due_date, status, issued_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissiis", $reservation['book_id'], $reservation['user_id'], $issue_date, $due_date, $status, $admin_id, $remarks);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?");
                    $stmt->bind_param("i", $reservation['book_id']);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE reservations SET status = 'fulfilled' WHERE reservation_id = ?");
                    $stmt->bind_param("i", $reservation_id);
                    $stmt->execute();

                    $notif_title = 'Reservation Fulfilled';
                    $notif_message = "Your reservation for '{$reservation['book_name']}' has been fulfilled. The book has been issued to you. Due date: " . formatDate($due_date) . ".";
                    $auth->createStudentNotification($reservation['user_id'], $notif_title, $notif_message, STUDENT_URL . '/my_books.php');

                    $auth->logActivity('admin', $_SESSION['user_id'], 'fulfill_reservation', "Fulfilled reservation #{$reservation_id} and issued book '{$reservation['book_name']}' to student {$reservation['student_name']}");

                    $conn->commit();
                    setFlashMessage('success', "Reservation #{$reservation_id} fulfilled. Book '{$reservation['book_name']}' has been issued to {$reservation['student_name']}.");
                    redirect(ADMIN_URL . '/reservations.php');
                } catch (Exception $e) {
                    $conn->rollback();
                    setFlashMessage('danger', 'Failed to fulfill reservation. Please try again.');
                }
            }
        }
        redirect(ADMIN_URL . '/reservations.php');
    }

    if ($action === 'cancel') {
        $reservation_id = intval($_POST['reservation_id'] ?? 0);

        if ($reservation_id > 0) {
            $stmt = $conn->prepare("SELECT r.*, b.book_name, u.name AS student_name FROM reservations r JOIN books b ON r.book_id = b.book_id JOIN users u ON r.user_id = u.id WHERE r.reservation_id = ? AND r.status = 'active'");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $reservation = $stmt->get_result()->fetch_assoc();

            if ($reservation) {
                $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?");
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();

                $auth->logActivity('admin', $_SESSION['user_id'], 'cancel_reservation', "Cancelled reservation #{$reservation_id} for '{$reservation['book_name']}' by student {$reservation['student_name']}");
                $auth->createStudentNotification($reservation['user_id'], 'Reservation Cancelled', "Your reservation for '{$reservation['book_name']}' has been cancelled by the administrator.", STUDENT_URL . '/my_reservations.php');

                setFlashMessage('success', "Reservation #{$reservation_id} has been cancelled.");
            } else {
                setFlashMessage('danger', 'Reservation not found or is not active.');
            }
        }
        redirect(ADMIN_URL . '/reservations.php');
    }

    if ($action === 'expire_old') {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE reservations SET status = 'expired' WHERE status = 'active' AND expiry_date < ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $expired_count = $stmt->affected_rows;

        $auth->logActivity('admin', $_SESSION['user_id'], 'expire_reservations', "Expired {$expired_count} old reservations");

        if ($expired_count > 0) {
            setFlashMessage('success', "{$expired_count} expired reservation(s) have been updated.");
        } else {
            setFlashMessage('info', 'No expired reservations found.');
        }
        redirect(ADMIN_URL . '/reservations.php');
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if ($search !== '') {
    $where_clauses[] = "(u.name LIKE ? OR b.book_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (in_array($status_filter, ['active', 'fulfilled', 'cancelled', 'expired'])) {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Count total records
$count_sql = "SELECT COUNT(*) AS total FROM reservations r
              JOIN books b ON r.book_id = b.book_id
              JOIN users u ON r.user_id = u.id
              {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch reservations
$sql = "SELECT r.reservation_id, r.reservation_date, r.expiry_date, r.status,
               b.book_id, b.book_name, b.book_no, b.available_copies, b.cover_image,
               u.id AS user_id, u.name AS student_name, u.student_id
        FROM reservations r
        JOIN books b ON r.book_id = b.book_id
        JOIN users u ON r.user_id = u.id
        {$where_sql}
        ORDER BY r.reservation_date DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("SELECT status, COUNT(*) AS count FROM reservations GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$stats_map = [];
foreach ($stats as $s) {
    $stats_map[$s['status']] = $s['count'];
}
$total_all = $conn->query("SELECT COUNT(*) AS c FROM reservations")->fetch_assoc()['c'];
$active_count = $stats_map['active'] ?? 0;
$fulfilled_count = $stats_map['fulfilled'] ?? 0;
$cancelled_count = $stats_map['cancelled'] ?? 0;
$expired_count = $stats_map['expired'] ?? 0;

$status_badges = [
    'active' => 'bg-primary',
    'fulfilled' => 'bg-success',
    'cancelled' => 'bg-secondary',
    'expired' => 'bg-danger',
];

function buildQueryString($overrides) {
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    return '?' . http_build_query($params);
}
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-bookmark-star me-2"></i>Manage Reservations</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reservations</li>
            </ol>
        </nav>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-bookmark"></i></div>
        <div class="stat-info"><h3><?php echo $total_all; ?></h3><p>Total</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info"><h3><?php echo $active_count; ?></h3><p>Active</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-info"><h3><?php echo $fulfilled_count; ?></h3><p>Fulfilled</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gray"><i class="bi bi-x-circle"></i></div>
        <div class="stat-info"><h3><?php echo $cancelled_count; ?></h3><p>Cancelled</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-circle"></i></div>
        <div class="stat-info"><h3><?php echo $expired_count; ?></h3><p>Expired</p></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" placeholder="Search by student name or book name..." value="<?php echo sanitize($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="fulfilled" <?php echo $status_filter === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="reservations.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
        <form method="POST" action="" class="d-inline float-end" onsubmit="return confirm('Expire all old reservations?');">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="expire_old">
            <button type="submit" class="btn btn-outline-warning mt-2"><i class="bi bi-clock-history me-1"></i>Expire Old Reservations</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Reservations (<?php echo $total_records; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <i class="bi bi-bookmark-x"></i>
                <h5>No Reservations Found</h5>
                <p class="text-muted"><?php echo ($search || $status_filter !== '') ? 'No results match your filters. Try adjusting your search criteria.' : 'There are no reservations yet.'; ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>#</th>
                            <th>Student</th>
                            <th>Book</th>
                            <th>Reserved On</th>
                            <th>Expires On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sn = $offset + 1;
                        foreach ($reservations as $r):
                            $badge_class = $status_badges[$r['status']] ?? 'bg-secondary';
                        ?>
                            <tr>
                                <td>
                                    <?php if (!empty($r['cover_image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $r['cover_image']; ?>" alt="" style="width:32px;height:44px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div style="width:32px;height:44px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;"><i class="bi bi-book"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $r['reservation_id']; ?></td>
                                <td>
                                    <strong><?php echo sanitize($r['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($r['student_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($r['book_name']); ?></strong><br>
                                    <?php if (!empty($r['book_no'])): ?>
                                        <small class="text-muted">#<?php echo sanitize($r['book_no']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($r['reservation_date']); ?></td>
                                <td>
                                    <?php echo formatDate($r['expiry_date']); ?>
                                    <?php if ($r['status'] === 'active' && $r['expiry_date'] < date('Y-m-d')): ?>
                                        <br><small class="text-danger">Expired</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?php echo $badge_class; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td class="action-btns">
                                    <?php if ($r['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#fulfill_<?php echo $r['reservation_id']; ?>" title="Fulfill & Issue Book">
                                            <i class="bi bi-check-lg"></i> Fulfill
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel_<?php echo $r['reservation_id']; ?>" title="Cancel Reservation">
                                            <i class="bi bi-x-lg"></i> Cancel
                                        </button>

                                        <!-- Fulfill Modal -->
                                        <div class="modal fade" id="fulfill_<?php echo $r['reservation_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Fulfill Reservation</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>This will issue the book to the student and mark the reservation as fulfilled.</p>
                                                        <table class="table table-borderless table-sm">
                                                            <tr><th>Student:</th><td><?php echo sanitize($r['student_name']); ?> (<?php echo sanitize($r['student_id']); ?>)</td></tr>
                                                            <tr><th>Book:</th><td><?php echo sanitize($r['book_name']); ?></td></tr>
                                                            <tr><th>Available Copies:</th><td><?php echo $r['available_copies']; ?></td></tr>
                                                            <tr><th>Issue Date:</th><td><?php echo formatDate(date('Y-m-d')); ?></td></tr>
                                                            <tr><th>Due Date:</th><td><?php echo formatDate(date('Y-m-d', strtotime('+' . DEFAULT_BORROW_DAYS . ' days'))); ?></td></tr>
                                                        </table>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="fulfill">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $r['reservation_id']; ?>">
                                                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Fulfill & Issue</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Cancel Modal -->
                                        <div class="modal fade" id="cancel_<?php echo $r['reservation_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Cancel Reservation</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to cancel this reservation?</p>
                                                        <p><strong><?php echo sanitize($r['student_name']); ?></strong> - <?php echo sanitize($r['book_name']); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="reservation_id" value="<?php echo $r['reservation_id']; ?>">
                                                            <button type="submit" class="btn btn-danger">Yes, Cancel</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="p-3">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildQueryString(['page' => $page - 1]); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo buildQueryString(['page' => $i]); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildQueryString(['page' => $page + 1]); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
include __DIR__ . '/includes/admin_footer.php';
?>