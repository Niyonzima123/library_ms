<?php
$page_title = 'Activity Log';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/report_activity.php');
    }

    if ($action === 'delete_log') {
        $log_id = intval($_POST['log_id'] ?? 0);
        if ($log_id > 0) {
            $stmt = $conn->prepare("DELETE FROM activity_log WHERE log_id = ?");
            $stmt->bind_param("i", $log_id);
            $stmt->execute();
            setFlashMessage('success', 'Log entry deleted.');
        }
        redirect(ADMIN_URL . '/report_activity.php');
    }

    if ($action === 'delete_old') {
        $days = max(1, intval($_POST['days'] ?? 90));
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        setFlashMessage('success', "Deleted {$deleted} log entries older than {$days} days.");
        redirect(ADMIN_URL . '/report_activity.php');
    }

    if ($action === 'clear_all') {
        $conn->query("TRUNCATE TABLE activity_log");
        setFlashMessage('success', 'All activity logs cleared.');
        redirect(ADMIN_URL . '/report_activity.php');
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date/Time', 'User Type', 'User Name', 'Action', 'Description', 'IP Address', 'Device']);
    
    $where_clauses_exp = ["1=1"];
    $params_exp = [];
    $types_exp = "";
    if (in_array($_GET['user_type'] ?? '', ['admin', 'librarian', 'student'])) {
        $where_clauses_exp[] = "user_type = ?";
        $params_exp[] = $_GET['user_type'];
        $types_exp .= "s";
    }
    $where_exp = implode(" AND ", $where_clauses_exp);
    $export_sql = "SELECT * FROM activity_log WHERE {$where_exp} ORDER BY created_at DESC LIMIT 10000";
    $stmt = $conn->prepare($export_sql);
    if ($types_exp) $stmt->bind_param($types_exp, ...$params_exp);
    $stmt->execute();
    $export_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($export_data as $row) {
        fputcsv($output, [$row['created_at'], $row['user_type'], $row['user_id'], $row['action'], $row['description'] ?? '', $row['ip_address'] ?? '', $row['device_info'] ?? '']);
    }
    fclose($output);
    exit();
}

// Filters
$user_type_filter = $_GET['user_type'] ?? '';
$action_filter = trim($_GET['action'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (in_array($user_type_filter, ['admin', 'librarian', 'student'])) {
    $where_clauses[] = "al.user_type = ?";
    $params[] = $user_type_filter;
    $types .= "s";
}
if ($action_filter) {
    $where_clauses[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}
if ($date_from) {
    $where_clauses[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $where_clauses[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$where = implode(" AND ", $where_clauses);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total
$count_sql = "SELECT COUNT(*) as total FROM activity_log al WHERE {$where}";
$stmt = $conn->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch records
$sql = "SELECT al.*,
    CASE
        WHEN al.user_type = 'admin' THEN (SELECT name FROM admins WHERE id = al.user_id)
        WHEN al.user_type = 'librarian' THEN (SELECT name FROM admins WHERE id = al.user_id)
        WHEN al.user_type = 'student' THEN (SELECT name FROM users WHERE id = al.user_id)
    END as user_name
    FROM activity_log al
    WHERE {$where}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . "ii";
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct actions for filter
$actions_result = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action");
$actions_list = [];
while ($row = $actions_result->fetch_assoc()) {
    $actions_list[] = $row['action'];
}

// Stats
$total_all = $conn->query("SELECT COUNT(*) as c FROM activity_log")->fetch_assoc()['c'];
$total_admin = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE user_type = 'admin'")->fetch_assoc()['c'];
$total_librarian = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE user_type = 'librarian'")->fetch_assoc()['c'];
$total_student = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE user_type = 'student'")->fetch_assoc()['c'];
$today_logs = $conn->query("SELECT COUNT(*) as c FROM activity_log WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];

// Build query string for pagination
$query_params = [];
if ($user_type_filter) $query_params['user_type'] = $user_type_filter;
if ($action_filter) $query_params['action'] = $action_filter;
if ($date_from) $query_params['date_from'] = $date_from;
if ($date_to) $query_params['date_to'] = $date_to;
$query_params['page'] = '';
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-activity me-2"></i>Activity Log</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Activity Log</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <div class="btn-group">
            <a href="<?php echo ADMIN_URL; ?>/report_activity.php?export=csv&<?php echo http_build_query(array_filter($_GET)); ?>" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
            <button class="btn btn-outline-primary" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
        <div class="btn-group ms-2">
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#cleanupModal"><i class="bi bi-trash me-1"></i>Cleanup</button>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearAllModal"><i class="bi bi-trash3 me-1"></i>Clear All</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-list-ul"></i></div>
        <div class="stat-info"><h3><?php echo $total_all; ?></h3><p>Total Logs</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-calendar-day"></i></div>
        <div class="stat-info"><h3><?php echo $today_logs; ?></h3><p>Today</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-shield"></i></div>
        <div class="stat-info"><h3><?php echo $total_admin; ?></h3><p>Admin Actions</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-book"></i></div>
        <div class="stat-info"><h3><?php echo $total_librarian; ?></h3><p>Librarian Actions</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="bi bi-person"></i></div>
        <div class="stat-info"><h3><?php echo $total_student; ?></h3><p>Student Actions</p></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">User Type</label>
                <select class="form-select" name="user_type">
                    <option value="">All</option>
                    <option value="admin" <?php echo $user_type_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="librarian" <?php echo $user_type_filter === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                    <option value="student" <?php echo $user_type_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <select class="form-select" name="action">
                    <option value="">All</option>
                    <?php foreach ($actions_list as $a): ?>
                        <option value="<?php echo sanitize($a); ?>" <?php echo $action_filter === $a ? 'selected' : ''; ?>><?php echo sanitize(str_replace('_', ' ', ucfirst($a))); ?></option>
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
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="report_activity.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Activity Logs (<?php echo $total_records; ?> records)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="bi bi-clipboard-x"></i>
                <h5>No Records Found</h5>
                <p>No activity logs match your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User Type</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Device</th>
                            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?><th style="width:50px"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $log['user_type'] === 'admin' ? 'primary' : 'info'; ?>">
                                        <?php echo ucfirst($log['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo sanitize($log['user_name'] ?? 'ID: ' . $log['user_id']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo sanitize(str_replace('_', ' ', $log['action'])); ?></span></td>
                                <td><?php echo sanitize($log['description'] ?? ''); ?></td>
                                <td><small class="text-muted"><?php echo sanitize($log['ip_address'] ?? 'N/A'); ?></small></td>
                                <td><small class="text-muted"><?php echo sanitize($log['device_info'] ?? '-'); ?></small></td>
                                <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this log entry?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_log">
                                        <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

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

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_old">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Cleanup Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Delete activity logs older than a specified number of days to free up storage.</p>
                    <div class="mb-3">
                        <label class="form-label">Delete logs older than</label>
                        <select name="days" class="form-select">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90" selected>90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-trash me-1"></i>Delete Old Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear All Modal -->
<div class="modal fade" id="clearAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="clear_all">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Clear All Activity Logs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger"><strong>Warning!</strong> This will permanently delete ALL activity logs from the system.</div>
                    <p>Total records that will be deleted: <strong><?php echo $total_all; ?></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i>Clear All Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
