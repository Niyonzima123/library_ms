<?php
$page_title = 'Issued Books';
require_once 'includes/admin_header.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if ($search !== '') {
    $where_clauses[] = "(u.name LIKE ? OR u.student_id LIKE ? OR b.book_name LIKE ? OR b.book_no LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if ($status_filter !== '' && in_array($status_filter, ['0', '1', '2', '3'])) {
    $where_clauses[] = "ib.status = ?";
    $params[] = (int)$status_filter;
    $types .= 'i';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

$count_sql = "SELECT COUNT(*) AS total FROM issued_books ib
              LEFT JOIN users u ON ib.user_id = u.id
              LEFT JOIN books b ON ib.book_id = b.book_id
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

$sql = "SELECT ib.*, 
               b.book_name, b.book_no,
               u.name AS student_name, u.student_id,
               a.name AS admin_name
        FROM issued_books ib
        LEFT JOIN books b ON ib.book_id = b.book_id
        LEFT JOIN users u ON ib.user_id = u.id
        LEFT JOIN admins a ON ib.issued_by = a.id
        {$where_sql}
        ORDER BY ib.issue_date DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Handle approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/issued_books.php');
    }
    $issue_id = intval($_POST['issue_id'] ?? 0);
    if ($issue_id > 0) {
        $stmt = $conn->prepare("UPDATE issued_books SET status = 1 WHERE issue_id = ? AND status = 0");
        $stmt->bind_param("i", $issue_id);
        $stmt->execute();
        $auth->logActivity($_SESSION['role'], $_SESSION['user_id'], 'approve_issue', "Approved book issue #$issue_id");
        setFlashMessage('success', 'Book issue approved.');
    }
    redirect(ADMIN_URL . '/issued_books.php');
}

// Handle return action (from this page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/issued_books.php');
    }
    $issue_id = intval($_POST['issue_id'] ?? 0);
    if ($issue_id > 0) {
        $stmt = $conn->prepare("SELECT ib.*, b.book_name FROM issued_books ib JOIN books b ON ib.book_id = b.book_id WHERE ib.issue_id = ? AND ib.status IN (1, 3)");
        $stmt->bind_param("i", $issue_id);
        $stmt->execute();
        $issue = $stmt->get_result()->fetch_assoc();
        if ($issue) {
            $conn->begin_transaction();
            try {
                $return_date = date('Y-m-d');
                $fine = calculateFine($issue['due_date']);
                $status = 2;
                $returned_to = $_SESSION['user_id'];
                $stmt = $conn->prepare("UPDATE issued_books SET return_date = ?, status = ?, fine_amount = ?, returned_to = ? WHERE issue_id = ?");
                $stmt->bind_param("sidii", $return_date, $status, $fine, $returned_to, $issue_id);
                $stmt->execute();
                $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
                $stmt->bind_param("i", $issue['book_id']);
                $stmt->execute();
                $auth->logActivity($_SESSION['role'], $_SESSION['user_id'], 'return_book', "Returned book '{$issue['book_name']}'");
                $conn->commit();
                setFlashMessage('success', "Book returned." . ($fine > 0 ? " Fine: &#8377;" . number_format($fine, 2) : ""));
            } catch (Exception $e) {
                $conn->rollback();
                setFlashMessage('danger', 'Failed to return book.');
            }
        }
    }
    redirect(ADMIN_URL . '/issued_books.php');
}

$status_labels = [
    0 => ['label' => 'Pending', 'class' => 'pending'],
    1 => ['label' => 'Issued', 'class' => 'issued'],
    2 => ['label' => 'Returned', 'class' => 'returned'],
    3 => ['label' => 'Overdue', 'class' => 'overdue'],
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
    <h2>Issued Books <span class="badge bg-secondary"><?php echo $total_records; ?></span></h2>
</div>

<div class="filters-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search by student name, ID, book name or number..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Pending</option>
                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Issued</option>
                <option value="2" <?php echo $status_filter === '2' ? 'selected' : ''; ?>>Returned</option>
                <option value="3" <?php echo $status_filter === '3' ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="issued_books.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Book</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                        <th>Paid</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sn = $offset + 1;
                    while ($row = $result->fetch_assoc()):
                        $status_info = $status_labels[$row['status']] ?? ['label' => 'Unknown', 'class' => 'bg-secondary'];
                        $student_display = htmlspecialchars($row['student_name'] ?? 'N/A');
                        if (!empty($row['student_id'])) {
                            $student_display .= ' <small class="text-muted">(' . htmlspecialchars($row['student_id']) . ')</small>';
                        }
                        $book_display = htmlspecialchars($row['book_name'] ?? 'N/A');
                        if (!empty($row['book_no'])) {
                            $book_display .= ' <small class="text-muted">#' . htmlspecialchars($row['book_no']) . '</small>';
                        }
                    ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td><?php echo $student_display; ?></td>
                        <td><?php echo $book_display; ?></td>
                        <td><?php echo $row['issue_date'] ? date('M d, Y', strtotime($row['issue_date'])) : 'N/A'; ?></td>
                        <td><?php echo $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'N/A'; ?></td>
                        <td><?php echo $row['return_date'] ? date('M d, Y', strtotime($row['return_date'])) : '<span class="text-muted">-</span>'; ?></td>
                        <td><span class="status-badge <?php echo $status_info['class']; ?>"><?php echo $status_info['label']; ?></span>
<?php if ($row['status'] == 3): ?>
    <br><small class="text-danger"><i class="bi bi-exclamation-circle"></i> <?php 
        $days_late = floor((time() - strtotime($row['due_date'])) / 86400);
        echo $days_late . ' days';
    ?></small>
<?php endif; ?>
</td>
                        <td><?php echo $row['fine_amount'] > 0 ? '&#8377;' . number_format($row['fine_amount'], 2) : '<span class="text-muted">-</span>'; ?></td>
                        <td><?php echo $row['fine_amount'] > 0 ? ($row['fine_paid'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') : '<span class="text-muted">-</span>'; ?></td>
                        <td class="action-btns">
                            <?php if (in_array($row['status'], [1, 3])): // Approved or Overdue ?>
                                <form method="POST" action="<?php echo ADMIN_URL; ?>/return_book.php" class="d-inline" onsubmit="return confirm('Mark this book as returned?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="return">
                                    <input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Return"><i class="bi bi-arrow-return-left"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($row['status'] == 0): // Pending ?>
                                <form method="POST" action="<?php echo ADMIN_URL; ?>/issued_books.php" class="d-inline" onsubmit="return confirm('Approve this issue?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="issue_id" value="<?php echo $row['issue_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                            <?php endif; ?>
                            <a href="view_issue.php?id=<?php echo $row['issue_id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
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

        <?php else: ?>
        <div class="empty-state">
            <h5>No issued books found</h5>
            <p class="text-muted"><?php echo ($search || $status_filter !== '') ? 'No results match your filters. Try adjusting your search criteria.' : 'There are no issued books yet.'; ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
require_once 'includes/admin_footer.php';
?>
