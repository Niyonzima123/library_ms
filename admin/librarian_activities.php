<?php
$page_title = 'Librarian Activities';
require_once __DIR__ . '/includes/admin_header.php';

// Admin-only access guard
if ($_SESSION['role'] !== ROLE_ADMIN) {
    setFlashMessage('danger', 'Access denied. Administrator privileges required.');
    redirect(ADMIN_URL . '/dashboard.php');
}

$csrf_token = generateCSRFToken();

// Handle delete/cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/librarian_activities.php');
    }
    if ($post_action === 'delete_log') {
        $log_id = intval($_POST['log_id'] ?? 0);
        if ($log_id > 0) {
            $stmt = $conn->prepare("DELETE FROM activity_log WHERE log_id = ?");
            $stmt->bind_param("i", $log_id);
            $stmt->execute();
            setFlashMessage('success', 'Log entry deleted.');
        }
        redirect(ADMIN_URL . '/librarian_activities.php');
    }
    if ($post_action === 'cleanup_old') {
        $days = max(1, intval($_POST['days'] ?? 90));
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE user_type = 'librarian' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        setFlashMessage('success', "Deleted {$deleted} librarian log entries older than {$days} days.");
        redirect(ADMIN_URL . '/librarian_activities.php');
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="librarian_activities_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date/Time', 'Librarian', 'Action', 'Description', 'IP Address', 'Device']);
    $stmt = $conn->prepare("SELECT al.*, a.name as librarian_name FROM activity_log al LEFT JOIN admins a ON al.user_id = a.id WHERE al.user_type = 'librarian' ORDER BY al.created_at DESC LIMIT 10000");
    $stmt->execute();
    $export_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($export_data as $row) {
        fputcsv($output, [$row['created_at'], $row['librarian_name'] ?? 'ID: ' . $row['user_id'], $row['action'], $row['description'] ?? '', $row['ip_address'] ?? '', $row['device_info'] ?? '']);
    }
    fclose($output);
    exit();
}

// --- Filters ---
$search = trim($_GET['search'] ?? '');
$librarian_id = $_GET['librarian_id'] ?? '';
$action_type = trim($_GET['action_type'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- Stats Queries ---
// Total Librarians
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM admins WHERE role = 'librarian'");
$stmt->execute();
$stat_total_librarians = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Logins Today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_log WHERE user_type = 'librarian' AND action = 'login' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$stat_logins_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Actions Today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM activity_log WHERE user_type = 'librarian' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$stat_actions_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Active Sessions Now
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM librarian_sessions WHERE is_active = 1");
$stmt->execute();
$stat_active_sessions = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// --- Active Sessions ---
$active_sessions_sql = "
    SELECT ls.session_id, ls.librarian_id, ls.login_time, ls.ip_address,
           a.name AS librarian_name, a.email,
           TIMEDIFF(NOW(), ls.login_time) AS duration
    FROM librarian_sessions ls
    JOIN admins a ON ls.librarian_id = a.id
    WHERE ls.is_active = 1
    ORDER BY ls.login_time DESC
";
$active_sessions_result = $conn->query($active_sessions_sql);
$active_sessions = [];
if ($active_sessions_result) {
    while ($row = $active_sessions_result->fetch_assoc()) {
        $active_sessions[] = $row;
    }
}

// --- Librarians for dropdown ---
$librarians_result = $conn->query("SELECT id, name, email FROM admins WHERE role = 'librarian' ORDER BY name");
$librarians = [];
if ($librarians_result) {
    while ($row = $librarians_result->fetch_assoc()) {
        $librarians[] = $row;
    }
}

// --- Build activity timeline query ---
$where_clauses = [];
$params = [];
$types = '';

// Librarian filter
if ($librarian_id !== '') {
    $where_clauses[] = "al.user_id = ?";
    $params[] = $librarian_id;
    $types .= 'i';
}

// Action type filter
if ($action_type) {
    $where_clauses[] = "al.action = ?";
    $params[] = $action_type;
    $types .= 's';
}

// Date filters
if ($date_from) {
    $where_clauses[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to) {
    $where_clauses[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Search filter
if ($search !== '') {
    $where_clauses[] = "(a.name LIKE ? OR a.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param]);
    $types .= 'ss';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Count query
$count_sql = "SELECT COUNT(*) as total FROM activity_log al JOIN admins a ON al.user_id = a.id" . $where_sql;
$stmt = $conn->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// Fetch activities
$data_sql = "SELECT al.log_id, al.action, al.description, al.ip_address, al.created_at,
                     a.name AS librarian_name, a.email
              FROM activity_log al
              JOIN admins a ON al.user_id = a.id" . $where_sql . "
              ORDER BY al.created_at DESC
              LIMIT ? OFFSET ?";

$stmt = $conn->prepare($data_sql);
$final_params = $params;
$final_types = $types . 'ii';
$final_params[] = $per_page;
$final_params[] = $offset;
if ($final_types) {
    $stmt->bind_param($final_types, ...$final_params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Most Active Librarians (This Month, Top 10) ---
$most_active_sql = "
    SELECT a.id, a.name, a.email, COUNT(*) AS action_count
    FROM activity_log al
    JOIN admins a ON al.user_id = a.id
    WHERE al.user_type = 'librarian'
      AND MONTH(al.created_at) = MONTH(CURDATE())
      AND YEAR(al.created_at) = YEAR(CURDATE())
    GROUP BY a.id, a.name, a.email
    ORDER BY action_count DESC
    LIMIT 10
";
$most_active_result = $conn->query($most_active_sql);
$most_active_librarians = [];
if ($most_active_result) {
    while ($row = $most_active_result->fetch_assoc()) {
        $most_active_librarians[] = $row;
    }
}

// --- Login History (with pagination support via $page) ---
$history_sql = "
    SELECT ls.session_id, ls.login_time, ls.logout_time, ls.ip_address, ls.is_active,
           a.name AS librarian_name, a.email
    FROM librarian_sessions ls
    JOIN admins a ON ls.librarian_id = a.id
    ORDER BY ls.login_time DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param('ii', $per_page, $offset);
$stmt->execute();
$login_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total login sessions for pagination
$total_sessions_result = $conn->query("SELECT COUNT(*) as total FROM librarian_sessions");
$total_sessions = $total_sessions_result->fetch_assoc()['total'] ?? 0;
$total_sessions_pages = ceil($total_sessions / $per_page);

// --- Action type options ---
$action_types = [
    'login' => 'Login',
    'logout' => 'Logout',
    'book_issued' => 'Book Issued',
    'book_returned' => 'Book Returned',
    'book_added' => 'Book Added',
    'book_updated' => 'Book Updated',
    'book_deleted' => 'Book Deleted',
    'student_approved' => 'Student Approved',
    'student_rejected' => 'Student Rejected',
    'ebook_added' => 'E-Book Added',
    'ebook_deleted' => 'E-Book Deleted',
    'settings_changed' => 'Settings Changed',
    'profile_updated' => 'Profile Updated',
];

// Badge colors per action type
$badge_colors = [
    'login' => 'success',
    'logout' => 'secondary',
    'book_issued' => 'primary',
    'book_returned' => 'info',
    'book_added' => 'success',
    'book_updated' => 'warning',
    'book_deleted' => 'danger',
    'student_approved' => 'success',
    'student_rejected' => 'danger',
    'ebook_added' => 'primary',
    'ebook_deleted' => 'danger',
    'settings_changed' => 'dark',
    'profile_updated' => 'info',
];

// Build query string for pagination links
$query_params = [];
if ($search) $query_params['search'] = $search;
if ($librarian_id !== '') $query_params['librarian_id'] = $librarian_id;
if ($action_type) $query_params['action_type'] = $action_type;
if ($date_from) $query_params['date_from'] = $date_from;
if ($date_to) $query_params['date_to'] = $date_to;
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-people-fill me-2"></i>Librarian Activities</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Librarian Activities</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="<?php echo ADMIN_URL; ?>/librarian_activities.php?export=csv" class="btn btn-success btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal"><i class="bi bi-trash me-1"></i>Cleanup Old Logs</button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-person-badge"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_total_librarians); ?></h3>
            <p>Total Librarians</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-box-arrow-in-right"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_logins_today); ?></h3>
            <p>Logins Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-lightning"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_actions_today); ?></h3>
            <p>Actions Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-person-check"></i></div>
        <div class="stat-info">
            <h3><?php echo number_format($stat_active_sessions); ?></h3>
            <p>Active Sessions Now</p>
        </div>
    </div>
</div>

<!-- Active Sessions -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-broadcast me-2"></i>Active Sessions</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($active_sessions)): ?>
            <div class="empty-state">
                <i class="bi bi-person-x"></i>
                <h5>No Active Sessions</h5>
                <p>There are currently no librarians logged in.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Librarian</th>
                            <th>Email</th>
                            <th>Login Time</th>
                            <th>Duration</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_sessions as $session): ?>
                            <tr>
                                <td><strong><?php echo sanitize($session['librarian_name']); ?></strong></td>
                                <td><?php echo sanitize($session['email']); ?></td>
                                <td><?php echo formatDateTime($session['login_time']); ?></td>
                                <td><span class="badge bg-success"><?php echo sanitize($session['duration']); ?></span></td>
                                <td><code><?php echo sanitize($session['ip_address']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="filters-bar">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Name or Email" value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Librarian</label>
                    <select class="form-select" name="librarian_id">
                        <option value="">All Librarians</option>
                        <?php foreach ($librarians as $lib): ?>
                            <option value="<?php echo $lib['id']; ?>" <?php echo $librarian_id !== '' && intval($librarian_id) === intval($lib['id']) ? 'selected' : ''; ?>><?php echo sanitize($lib['name']); ?> (<?php echo sanitize($lib['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                    <a href="librarian_activities.php" class="btn btn-outline-secondary">Clear</a>
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
                <p>No librarian activities match your current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Librarian Name</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $act):
                            $badge_color = $badge_colors[$act['action']] ?? 'secondary';
                            $action_label = $action_types[$act['action']] ?? ucfirst(str_replace('_', ' ', $act['action']));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($act['librarian_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo sanitize($act['email']); ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $badge_color; ?>"><?php echo $action_label; ?></span>
                                </td>
                                <td><?php echo sanitize($act['description'] ?? ''); ?></td>
                                <td><code><?php echo sanitize($act['ip_address'] ?? ''); ?></code></td>
                                <td>
                                    <small class="text-muted"><?php echo formatDateTime($act['created_at']); ?></small>
                                </td>
                                <td class="no-print">
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this log entry?');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_log">
                                        <input type="hidden" name="log_id" value="<?php echo $act['log_id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
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

<!-- Most Active Librarians -->
<div class="card mt-4 mb-4">
    <div class="card-header">
        <h5><i class="bi bi-trophy me-2"></i>Most Active Librarians (This Month - Top 10)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($most_active_librarians)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>No Data</h5>
                <p>No librarian activity recorded this month.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Librarian</th>
                            <th>Email</th>
                            <th>Actions This Month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($most_active_librarians as $lib): ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span class="badge bg-<?php echo $rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'dark'); ?>"><?php echo $rank; ?></span>
                                    <?php else: ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo sanitize($lib['name']); ?></strong></td>
                                <td><?php echo sanitize($lib['email']); ?></td>
                                <td><span class="badge bg-primary"><?php echo number_format($lib['action_count']); ?></span></td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Librarian Login History -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-box-arrow-in-right me-2"></i>Librarian Login History</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($login_history)): ?>
            <div class="empty-state">
                <i class="bi bi-clock"></i>
                <h5>No Login History</h5>
                <p>No login sessions recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Librarian</th>
                            <th>Email</th>
                            <th>Login Time</th>
                            <th>Logout Time</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($login_history as $session): ?>
                            <tr>
                                <td><strong><?php echo sanitize($session['librarian_name']); ?></strong></td>
                                <td><?php echo sanitize($session['email']); ?></td>
                                <td><?php echo formatDateTime($session['login_time']); ?></td>
                                <td>
                                    <?php if ($session['logout_time']): ?>
                                        <?php echo formatDateTime($session['logout_time']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">---</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo sanitize($session['ip_address'] ?? ''); ?></code></td>
                                <td>
                                    <?php if ($session['is_active']): ?>
                                        <span class="status-badge success">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge secondary">Ended</span>
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

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="cleanup_old">
                <div class="modal-header">
                    <h5 class="modal-title" id="cleanupModalLabel"><i class="bi bi-trash me-2"></i>Cleanup Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Delete all librarian activity logs older than a specified number of days.</p>
                    <div class="mb-3">
                        <label class="form-label">Days Old</label>
                        <input type="number" class="form-control" name="days" min="1" value="90" required>
                        <div class="form-text">Logs older than this many days will be permanently deleted.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-trash me-1"></i>Delete Old Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>