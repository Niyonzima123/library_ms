<?php
$page_title = 'Student Activities';
require_once __DIR__ . '/includes/admin_header.php';
$csrf_token = generateCSRFToken();

// Handle delete/cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/student_activities.php');
    }
    if ($post_action === 'delete_log') {
        $log_id = intval($_POST['log_id'] ?? 0);
        if ($log_id > 0) {
            $stmt = $conn->prepare("DELETE FROM activity_log WHERE log_id = ?");
            $stmt->bind_param("i", $log_id);
            $stmt->execute();
            setFlashMessage('success', 'Log entry deleted.');
        }
        redirect(ADMIN_URL . '/student_activities.php');
    }
    if ($post_action === 'cleanup_old') {
        $days = max(1, intval($_POST['days'] ?? 90));
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE user_type = 'student' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        setFlashMessage('success', "Deleted {$deleted} student log entries older than {$days} days.");
        redirect(ADMIN_URL . '/student_activities.php');
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_activities_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date/Time', 'Student', 'Action', 'Description', 'IP Address', 'Device']);
    $stmt = $conn->prepare("SELECT al.*, u.name as student_name FROM activity_log al LEFT JOIN users u ON al.user_id = u.id WHERE al.user_type = 'student' ORDER BY al.created_at DESC LIMIT 10000");
    $stmt->execute();
    $export_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($export_data as $row) {
        fputcsv($output, [$row['created_at'], $row['student_name'] ?? 'ID: ' . $row['user_id'], $row['action'], $row['description'] ?? '', $row['ip_address'] ?? '', $row['device_info'] ?? '']);
    }
    fclose($output);
    exit();
}

// --- Filters ---
$search = trim($_GET['search'] ?? '');
$action_type = trim($_GET['action_type'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- Stats Queries ---
// Total Logins Today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_log WHERE user_type = 'student' AND action = 'login' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$stat_logins_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Books Issued This Week
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM issued_books WHERE YEARWEEK(issue_date, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->execute();
$stat_issued_week = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Notes Created This Week
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_notes WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->execute();
$stat_notes_week = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Documents Uploaded This Week
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_documents WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->execute();
$stat_docs_week = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// --- Build UNION ALL query for activity timeline ---
$where_clauses = [];
$params = [];
$types = '';

// Student search filter (applied to all subqueries)
if ($search !== '') {
    $where_clauses[] = "(u.name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

// Date filters
$date_where = '';
$date_params = [];
$date_types = '';
if ($date_from) {
    $date_where .= " AND DATE(t.activity_time) >= ?";
    $date_params[] = $date_from;
    $date_types .= 's';
}
if ($date_to) {
    $date_where .= " AND DATE(t.activity_time) <= ?";
    $date_params[] = $date_to;
    $date_types .= 's';
}

// Action type filter
$action_where = '';
if ($action_type) {
    $action_where = " AND t.activity_type = ?";
}

// Student join filter
$student_join = $search !== '' ? "JOIN users u ON t.user_id = u.id" : "JOIN users u ON t.user_id = u.id";

// Subqueries for the UNION
$subqueries = [];

// 1. Logins/Logouts from activity_log
$subqueries[] = "
    SELECT al.log_id AS source_id, 'student' AS user_type, al.user_id, al.action AS activity_type,
           al.description, al.created_at AS activity_time
    FROM activity_log al
    WHERE al.user_type = 'student' AND al.action IN ('login','logout')
";

// 2. Book issues/returns from issued_books
$subqueries[] = "
    SELECT ib.issue_id AS source_id, 'student' AS user_type, ib.user_id,
           CASE
               WHEN ib.status = 2 THEN 'book_returned'
               ELSE 'book_issued'
           END AS activity_type,
           CONCAT('Book \"', b.book_name, '\" ', CASE WHEN ib.status = 2 THEN 'returned' ELSE 'issued' END) AS description,
           COALESCE(ib.updated_at, ib.created_at) AS activity_time
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
";

// 3. Notes created from student_notes
$subqueries[] = "
    SELECT sn.note_id AS source_id, 'student' AS user_type, sn.user_id,
           'note_created' AS activity_type,
           CONCAT('Created note: \"', sn.note_title, '\" (', sn.note_type, ')') AS description,
           sn.created_at AS activity_time
    FROM student_notes sn
";

// 4. Documents uploaded from student_documents
$subqueries[] = "
    SELECT sd.doc_id AS source_id, 'student' AS user_type, sd.user_id,
           'document_uploaded' AS activity_type,
           CONCAT('Uploaded document: \"', sd.doc_title, '\" (', sd.doc_category, ')') AS description,
           sd.created_at AS activity_time
    FROM student_documents sd
";

// 5. Reading list additions from reading_list
$subqueries[] = "
    SELECT rl.id AS source_id, 'student' AS user_type, rl.user_id,
           'reading_list_added' AS activity_type,
           CONCAT('Added \"', b.book_name, '\" to reading list') AS description,
           rl.added_at AS activity_time
    FROM reading_list rl
    JOIN books b ON rl.book_id = b.book_id
";

$union_sql = implode(" UNION ALL ", $subqueries);

// Full query with student join and filters
$full_sql = "
    SELECT t.source_id, t.user_type, t.user_id, t.activity_type, t.description, t.activity_time,
           u.name AS student_name, u.student_id AS stu_id
    FROM ($union_sql) t
    JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

if ($search !== '') {
    $full_sql .= " AND (u.name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
}
if ($action_type) {
    $full_sql .= " AND t.activity_type = ?";
}
if ($date_from) {
    $full_sql .= " AND DATE(t.activity_time) >= ?";
}
if ($date_to) {
    $full_sql .= " AND DATE(t.activity_time) <= ?";
}

// Count query
$count_sql = "SELECT COUNT(*) as total FROM ($full_sql) AS counted";
$stmt = $conn->prepare($count_sql);
$all_params = [];
$all_types = '';
if ($search !== '') {
    $all_params = array_merge($all_params, [$search_param, $search_param, $search_param]);
    $all_types .= 'sss';
}
if ($action_type) {
    $all_params[] = $action_type;
    $all_types .= 's';
}
if ($date_from) {
    $all_params[] = $date_from;
    $all_types .= 's';
}
if ($date_to) {
    $all_params[] = $date_to;
    $all_types .= 's';
}
if ($all_types) {
    $stmt->bind_param($all_types, ...$all_params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// Final query with ordering and pagination
$full_sql .= " ORDER BY t.activity_time DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($full_sql);
$final_params = $all_params;
$final_types = $all_types . 'ii';
$final_params[] = $per_page;
$final_params[] = $offset;
if ($final_types) {
    $stmt->bind_param($final_types, ...$final_params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Most Active Students (Top 10) ---
$most_active_sql = "
    SELECT u.id, u.name, u.student_id, COUNT(*) AS activity_count
    FROM (
        SELECT user_id, created_at AS activity_time FROM activity_log WHERE user_type = 'student'
        UNION ALL
        SELECT user_id, COALESCE(updated_at, created_at) AS activity_time FROM issued_books
        UNION ALL
        SELECT user_id, created_at AS activity_time FROM student_notes
        UNION ALL
        SELECT user_id, created_at AS activity_time FROM student_documents
        UNION ALL
        SELECT user_id, added_at AS activity_time FROM reading_list
    ) all_acts
    JOIN users u ON all_acts.user_id = u.id
    GROUP BY u.id, u.name, u.student_id
    ORDER BY activity_count DESC
    LIMIT 10
";
$most_active_result = $conn->query($most_active_sql);
$most_active_students = [];
if ($most_active_result) {
    while ($row = $most_active_result->fetch_assoc()) {
        $most_active_students[] = $row;
    }
}

// --- Action type options ---
$action_types = [
    'login' => 'Login',
    'logout' => 'Logout',
    'book_issued' => 'Book Issued',
    'book_returned' => 'Book Returned',
    'note_created' => 'Note Created',
    'document_uploaded' => 'Document Uploaded',
    'reading_list_added' => 'Reading List Added',
];

// Badge colors per activity type
$badge_colors = [
    'login' => 'success',
    'logout' => 'secondary',
    'book_issued' => 'primary',
    'book_returned' => 'info',
    'note_created' => 'warning',
    'document_uploaded' => 'dark',
    'reading_list_added' => 'purple',
];

// Build query string for pagination links
$query_params = [];
if ($search) $query_params['search'] = $search;
if ($action_type) $query_params['action_type'] = $action_type;
if ($date_from) $query_params['date_from'] = $date_from;
if ($date_to) $query_params['date_to'] = $date_to;
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-activity me-2"></i>Student Activities</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Student Activities</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/student_activities.php?export=csv&<?php echo http_build_query(array_filter($_GET)); ?>" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        <button class="btn btn-outline-primary" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print</button>
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#cleanupModal"><i class="bi bi-trash me-1"></i>Cleanup</button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-box-arrow-in-right"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_logins_today); ?></h3>
            <p>Logins Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-journal-arrow-up"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_issued_week); ?></h3>
            <p>Books Issued This Week</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-sticky"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_notes_week); ?></h3>
            <p>Notes Created This Week</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-file-earmark-arrow-up"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_docs_week); ?></h3>
            <p>Documents Uploaded This Week</p>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="filters-bar">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search Student</label>
                    <input type="text" class="form-control" name="search" placeholder="Name, Student ID or Email" value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action Type</label>
                    <select class="form-select" name="action_type">
                        <option value="">All Activities</option>
                        <?php foreach ($action_types as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $action_type === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo sanitize($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo sanitize($date_to); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                    <a href="student_activities.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activity Timeline -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Activity Timeline (<?php echo number_format($total_records); ?> records)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($activities)): ?>
            <div class="empty-state">
                <i class="bi bi-activity"></i>
                <h5>No Activities Found</h5>
                <p>No student activities match your current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Student</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $act):
                            $badge_color = $badge_colors[$act['activity_type']] ?? 'secondary';
                            $action_label = $action_types[$act['activity_type']] ?? ucfirst(str_replace('_', ' ', $act['activity_type']));
                        ?>
                            <tr>
                                <td>
                                    <small class="text-muted"><?php echo formatDateTime($act['activity_time']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($act['student_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo sanitize($act['stu_id']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $badge_color; ?>"><?php echo $action_label; ?></span>
                                </td>
                                <td><?php echo sanitize($act['description'] ?? ''); ?></td>
                                <td>
                                    <?php if (isset($act['source_id']) && $act['activity_type'] === 'login' || $act['activity_type'] === 'logout'): ?>
                                        <form method="POST" onsubmit="return confirm('Delete this log entry?');" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete_log">
                                            <input type="hidden" name="log_id" value="<?php echo intval($act['source_id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
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

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination-wrapper">
        <nav>
            <ul class="pagination">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page - 1])); ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page + 1])); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- Most Active Students -->
<div class="card mt-4">
    <div class="card-header">
        <h5><i class="bi bi-trophy me-2"></i>Most Active Students (Top 10)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($most_active_students)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>No Data</h5>
                <p>No student activity recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Total Activities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($most_active_students as $student): ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span class="badge bg-<?php echo $rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'dark'); ?>"><?php echo $rank; ?></span>
                                    <?php else: ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo sanitize($student['name']); ?></strong></td>
                                <td><?php echo sanitize($student['student_id']); ?></td>
                                <td><span class="badge bg-primary"><?php echo number_format($student['activity_count']); ?></span></td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="cleanup_old">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Student Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Delete logs older than</label>
                    <select name="days" class="form-select">
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">180 days</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Delete Old Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>