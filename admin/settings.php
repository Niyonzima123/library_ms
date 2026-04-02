<?php
$page_title = 'Settings';
include __DIR__ . '/includes/admin_header.php';

// Admin-only access guard
if ($_SESSION['role'] !== ROLE_ADMIN) {
    setFlashMessage('danger', 'Access denied. Administrator privileges required.');
    redirect(ADMIN_URL . '/dashboard.php');
}

// Fetch statistics
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM books");
$stats['books'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['students'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM issued_books");
$stats['issued_books'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM categories");
$stats['categories'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM authors");
$stats['authors'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM departments");
$stats['departments'] = $result->fetch_assoc()['total'];

// Database info
$db_name = $conn->query("SELECT DATABASE() as db")->fetch_assoc()['db'];
$db_version = $conn->server_info;
$db_charset = $conn->character_set_name();

// Table sizes
$tables = $conn->query("SELECT TABLE_NAME, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) AS size_kb FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db_name' ORDER BY TABLE_ROWS DESC");
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-gear me-2"></i>Settings</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Settings</li>
        </ol>
    </nav>
</div>

<div class="row">
    <!-- Library Information -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-building me-2"></i>Library Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width: 45%;">Library Name</td>
                        <td><strong><?php echo sanitize(SITE_NAME); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Site URL</td>
                        <td><strong><?php echo sanitize(SITE_URL); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Max Books per Student</td>
                        <td><strong><?php echo MAX_BOOKS_PER_STUDENT; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Default Borrow Days</td>
                        <td><strong><?php echo DEFAULT_BORROW_DAYS; ?> days</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Fine per Day (Overdue)</td>
                        <td><strong><?php echo FINE_PER_DAY; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Max Upload Size</td>
                        <td><strong><?php echo round(MAX_FILE_SIZE / (1024 * 1024)); ?> MB</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Allowed E-Book Types</td>
                        <td><strong><?php echo implode(', ', ALLOWED_EBOOK_TYPES); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Allowed Image Types</td>
                        <td><strong><?php echo implode(', ', ALLOWED_IMAGE_TYPES); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Statistics Overview</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-primary"><?php echo $stats['books']; ?></h3>
                            <small class="text-muted">Total Books</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-success"><?php echo $stats['students']; ?></h3>
                            <small class="text-muted">Total Students</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-warning"><?php echo $stats['issued_books']; ?></h3>
                            <small class="text-muted">Issued Books</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-info"><?php echo $stats['categories']; ?></h3>
                            <small class="text-muted">Categories</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-secondary"><?php echo $stats['authors']; ?></h3>
                            <small class="text-muted">Authors</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded text-center">
                            <h3 class="mb-1 text-dark"><?php echo $stats['departments']; ?></h3>
                            <small class="text-muted">Departments</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Links -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Quick Links</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?php echo ADMIN_URL; ?>/books.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-book me-2"></i>Manage Books</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/categories.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-tags me-2"></i>Manage Categories</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/authors.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-badge me-2"></i>Manage Authors</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/departments.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-building me-2"></i>Manage Departments</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/classes.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people me-2"></i>Manage Classes</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/students.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-mortarboard me-2"></i>Manage Students</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-journal-bookmark me-2"></i>Issued Books</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/report_defaulters.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-exclamation-triangle me-2"></i>Defaulter Report</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?php echo ADMIN_URL; ?>/report_activity.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-activity me-2"></i>Activity Log</span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Info & Database -->
    <div class="col-lg-6 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pc-display me-2"></i>System Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted" style="width: 45%;">PHP Version</td>
                        <td><strong><?php echo phpversion(); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">MySQL Version</td>
                        <td><strong><?php echo $db_version; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Server Software</td>
                        <td><strong><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Server OS</td>
                        <td><strong><?php echo PHP_OS; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Upload Max Size</td>
                        <td><strong><?php echo ini_get('upload_max_filesize'); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Memory Limit</td>
                        <td><strong><?php echo ini_get('memory_limit'); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Max Execution Time</td>
                        <td><strong><?php echo ini_get('max_execution_time'); ?>s</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-database me-2"></i>Database Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted" style="width: 45%;">Database Name</td>
                        <td><strong><?php echo sanitize($db_name); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Charset</td>
                        <td><strong><?php echo $db_charset; ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Host</td>
                        <td><strong><?php echo sanitize(DB_HOST); ?></strong></td>
                    </tr>
                </table>

                <?php if ($tables && $tables->num_rows > 0): ?>
                <hr>
                <h6 class="text-muted mb-2">Tables</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Table</th>
                                <th class="text-end">Rows</th>
                                <th class="text-end">Size (KB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $tables->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo sanitize($t['TABLE_NAME']); ?></td>
                                <td class="text-end"><?php echo number_format($t['TABLE_ROWS'] ?? 0); ?></td>
                                <td class="text-end"><?php echo $t['size_kb']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
