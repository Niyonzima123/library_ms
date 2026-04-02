<?php
$page_title = 'Students';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle edit student details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/students.php');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $student_id_new = trim($_POST['student_id'] ?? '');
    $card_number_new = trim($_POST['card_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $max_books = max(1, intval($_POST['max_books_allowed'] ?? 3));
    $dept_id = intval($_POST['dept_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);

    if ($user_id <= 0 || empty($student_id_new) || empty($name)) {
        setFlashMessage('danger', 'Student ID and Name are required.');
        redirect(ADMIN_URL . '/students.php');
    }

    // Check if student_id is unique (if changed)
    $check = $conn->prepare("SELECT id FROM users WHERE student_id = ? AND id != ?");
    $check->bind_param("si", $student_id_new, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        setFlashMessage('danger', 'Registration Number already in use by another student.');
        redirect(ADMIN_URL . '/students.php');
    }

    // Check if card_number is unique (if provided)
    if (!empty($card_number_new)) {
        $check2 = $conn->prepare("SELECT id FROM users WHERE card_number = ? AND id != ?");
        $check2->bind_param("si", $card_number_new, $user_id);
        $check2->execute();
        if ($check2->get_result()->num_rows > 0) {
            setFlashMessage('danger', 'Card Number already in use by another student.');
            redirect(ADMIN_URL . '/students.php');
        }
    }

    $stmt = $conn->prepare("UPDATE users SET student_id = ?, card_number = ?, name = ?, mobile = ?, address = ?, max_books_allowed = ?, dept_id = ?, class_id = ? WHERE id = ?");
    $dept_param = $dept_id > 0 ? $dept_id : null;
    $class_param = $class_id > 0 ? $class_id : null;
    $stmt->bind_param("sssssiiii", $student_id_new, $card_number_new, $name, $mobile, $address, $max_books, $dept_param, $class_param, $user_id);
    $stmt->execute();

    $auth->logActivity('admin', $_SESSION['user_id'], 'update_student', "Updated student #$user_id (Reg: $student_id_new, Card: $card_number_new)");
    setFlashMessage('success', 'Student details updated successfully.');
    redirect(ADMIN_URL . '/students.php');
}

// Handle enable/disable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/students.php');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if ($user_id > 0 && in_array($new_status, [STATUS_APPROVED, STATUS_REJECTED])) {
        $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();

        $auth->logActivity('admin', $_SESSION['user_id'], 'toggle_student_status', "Set student #$user_id status to $new_status");

        if ($new_status === STATUS_APPROVED) {
            $auth->createStudentNotification($user_id, 'Account Re-enabled', 'Your account has been re-enabled. You can now access the library.', STUDENT_URL . '/dashboard.php');
        }

        setFlashMessage('success', 'Student status updated.');
    }
    redirect(ADMIN_URL . '/students.php');
}

// Filters
$search = trim($_GET['search'] ?? '');
$dept_filter = intval($_GET['dept'] ?? 0);
$class_filter = intval($_GET['class'] ?? 0);
$status_filter = $_GET['status'] ?? '';

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search) {
    $like = "%{$search}%";
    $where_clauses[] = "(u.name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}
if ($dept_filter > 0) {
    $where_clauses[] = "u.dept_id = ?";
    $params[] = $dept_filter;
    $types .= "i";
}
if ($class_filter > 0) {
    $where_clauses[] = "u.class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}
if (in_array($status_filter, [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED])) {
    $where_clauses[] = "u.approval_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where = implode(" AND ", $where_clauses);

$sql = "SELECT u.id, u.student_id, u.name, u.email, u.mobile, u.approval_status, u.card_number, u.max_books_allowed, u.created_at,
    d.dept_name, c.class_name,
    (SELECT COUNT(*) FROM issued_books WHERE user_id = u.id AND status IN (?, ?)) as current_books
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE {$where}
    ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$s_approved = ISSUE_APPROVED;
$s_overdue = ISSUE_OVERDUE;
$all_params = array_merge([$s_approved, $s_overdue], $params);
$all_types = "ii" . $types;
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get departments and classes for filters
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);
$classes = $conn->query("SELECT * FROM classes ORDER BY class_name")->fetch_all(MYSQLI_ASSOC);

// Stats
$total_students = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$approved_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE approval_status = 'approved'")->fetch_assoc()['c'];
$pending_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE approval_status = 'pending'")->fetch_assoc()['c'];
$rejected_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE approval_status = 'rejected'")->fetch_assoc()['c'];
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-mortarboard me-2"></i>Students</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Students</li>
            </ol>
        </nav>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people"></i></div>
        <div class="stat-info"><h3><?php echo $total_students; ?></h3><p>Total</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-info"><h3><?php echo $approved_count; ?></h3><p>Approved</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info"><h3><?php echo $pending_count; ?></h3><p>Pending</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
        <div class="stat-info"><h3><?php echo $rejected_count; ?></h3><p>Rejected</p></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search name, ID, email..." value="<?php echo sanitize($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="dept">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['dept_id']; ?>" <?php echo $dept_filter == $d['dept_id'] ? 'selected' : ''; ?>><?php echo sanitize($d['dept_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="class">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" <?php echo $class_filter == $c['class_id'] ? 'selected' : ''; ?>><?php echo sanitize($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="students.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Students (<?php echo count($students); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>No Students Found</h5>
                <p>No students match your criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Books Issued</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo sanitize($s['student_id']); ?></td>
                                <td>
                                    <strong><?php echo sanitize($s['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($s['mobile'] ?? ''); ?></small>
                                </td>
                                <td><?php echo sanitize($s['email']); ?></td>
                                <td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($s['class_name'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge <?php echo $s['approval_status']; ?>"><?php echo ucfirst($s['approval_status']); ?></span></td>
                                <td><?php echo $s['current_books']; ?>/<?php echo $s['max_books_allowed']; ?></td>
                                <td class="action-btns">
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#view_<?php echo $s['id']; ?>" title="View"><i class="bi bi-eye"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit_<?php echo $s['id']; ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <?php if ($s['approval_status'] === STATUS_APPROVED): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Disable this student?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                            <input type="hidden" name="new_status" value="rejected">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Disable"><i class="bi bi-slash-circle"></i></button>
                                        </form>
                                    <?php elseif ($s['approval_status'] === STATUS_REJECTED): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Enable this student?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                            <input type="hidden" name="new_status" value="approved">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Enable"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- View Modal -->
                            <div class="modal fade" id="view_<?php echo $s['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Student Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <table class="table table-borderless table-sm">
                                                <tr><th width="35%">Student ID:</th><td><?php echo sanitize($s['student_id']); ?></td></tr>
                                                <tr><th>Name:</th><td><?php echo sanitize($s['name']); ?></td></tr>
                                                <tr><th>Email:</th><td><?php echo sanitize($s['email']); ?></td></tr>
                                                <tr><th>Mobile:</th><td><?php echo sanitize($s['mobile'] ?? 'N/A'); ?></td></tr>
                                                <tr><th>Card Number:</th><td><?php echo sanitize($s['card_number'] ?? 'N/A'); ?></td></tr>
                                                <tr><th>Department:</th><td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td></tr>
                                                <tr><th>Class:</th><td><?php echo sanitize($s['class_name'] ?? 'N/A'); ?></td></tr>
                                                <tr><th>Status:</th><td><span class="status-badge <?php echo $s['approval_status']; ?>"><?php echo ucfirst($s['approval_status']); ?></span></td></tr>
                                                <tr><th>Books Issued:</th><td><?php echo $s['current_books']; ?>/<?php echo $s['max_books_allowed']; ?></td></tr>
                                                <tr><th>Registered:</th><td><?php echo formatDate($s['created_at']); ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="edit_<?php echo $s['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" action="">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="update_student">
                                            <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Registration Number <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="student_id" value="<?php echo sanitize($s['student_id']); ?>" required>
                                                        <small class="form-text text-muted">Must be unique across all students.</small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Card Number</label>
                                                        <input type="text" class="form-control" name="card_number" value="<?php echo sanitize($s['card_number'] ?? ''); ?>" placeholder="e.g. CARD-2025-001">
                                                        <small class="form-text text-muted">Library card number. Leave blank if not assigned.</small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="name" value="<?php echo sanitize($s['name']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Mobile</label>
                                                        <input type="text" class="form-control" name="mobile" value="<?php echo sanitize($s['mobile'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Department</label>
                                                        <select class="form-select" name="dept_id">
                                                            <option value="0">-- Not Assigned --</option>
                                                            <?php foreach ($departments as $d): ?>
                                                                <option value="<?php echo $d['dept_id']; ?>" <?php echo ($s['dept_name'] ?? '') == $d['dept_name'] ? 'selected' : ''; ?>><?php echo sanitize($d['dept_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Class</label>
                                                        <select class="form-select" name="class_id">
                                                            <option value="0">-- Not Assigned --</option>
                                                            <?php foreach ($classes as $c): ?>
                                                                <option value="<?php echo $c['class_id']; ?>" <?php echo ($s['class_name'] ?? '') == $c['class_name'] ? 'selected' : ''; ?>><?php echo sanitize($c['class_name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Max Books Allowed</label>
                                                        <input type="number" class="form-control" name="max_books_allowed" value="<?php echo $s['max_books_allowed']; ?>" min="1" max="20">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Address</label>
                                                        <textarea class="form-control" name="address" rows="2" placeholder="Student address..."></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
