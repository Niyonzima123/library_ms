<?php
$page_title = 'Promote Students';
include __DIR__ . '/includes/admin_header.php';

// Admin-only access guard
if ($_SESSION['role'] !== ROLE_ADMIN) {
    setFlashMessage('danger', 'Access denied. Administrator privileges required.');
    redirect(ADMIN_URL . '/dashboard.php');
}

$csrf_token = generateCSRFToken();

// Handle promote/transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'promote_student') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/promote_student.php');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $to_class_id = intval($_POST['to_class_id'] ?? 0);
    $to_dept_id = intval($_POST['to_dept_id'] ?? 0);
    $promotion_type = trim($_POST['promotion_type'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $semester = intval($_POST['semester'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($user_id <= 0 || $to_class_id <= 0 || $to_dept_id <= 0 || empty($promotion_type) || empty($academic_year)) {
        setFlashMessage('danger', 'Please fill in all required fields.');
        redirect(ADMIN_URL . '/promote_student.php');
    }

    if (!in_array($promotion_type, ['promotion', 'transfer', 'demotion', 'initial'])) {
        setFlashMessage('danger', 'Invalid promotion type.');
        redirect(ADMIN_URL . '/promote_student.php');
    }

    // Fetch current student info
    $stmt = $conn->prepare("SELECT id, name, student_id, class_id, dept_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        setFlashMessage('danger', 'Student not found.');
        redirect(ADMIN_URL . '/promote_student.php');
    }

    $from_class_id = $student['class_id'];
    $from_dept_id = $student['dept_id'];
    $promoted_by = $_SESSION['user_id'];

    // Update user's class and department
    $stmt = $conn->prepare("UPDATE users SET class_id = ?, dept_id = ? WHERE id = ?");
    $stmt->bind_param("iii", $to_class_id, $to_dept_id, $user_id);
    $stmt->execute();

    // Insert promotion record
    $stmt = $conn->prepare("INSERT INTO student_promotions (user_id, from_class_id, to_class_id, from_dept_id, to_dept_id, promotion_type, academic_year, semester, remarks, promoted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiississi", $user_id, $from_class_id, $to_class_id, $from_dept_id, $to_dept_id, $promotion_type, $academic_year, $semester, $remarks, $promoted_by);
    $stmt->execute();

    // Fetch new class and dept names for notification
    $stmt = $conn->prepare("SELECT c.class_name, d.dept_name FROM classes c JOIN departments d ON c.dept_id = d.dept_id WHERE c.class_id = ?");
    $stmt->bind_param("i", $to_class_id);
    $stmt->execute();
    $new_info = $stmt->get_result()->fetch_assoc();
    $new_class_name = $new_info ? $new_info['class_name'] : 'N/A';
    $new_dept_name = $new_info ? $new_info['dept_name'] : 'N/A';

    // Notify student
    $type_label = ucfirst($promotion_type);
    $notif_title = "Class {$type_label}";
    $notif_message = "You have been {$promotion_type}d to {$new_class_name} ({$new_dept_name}) for Academic Year {$academic_year}.";
    if ($remarks) {
        $notif_message .= " Remarks: {$remarks}";
    }
    $auth->createStudentNotification($user_id, $notif_title, $notif_message, STUDENT_URL . '/dashboard.php');

    $auth->logActivity('admin', $_SESSION['user_id'], 'promote_student', "{$type_label} of student {$student['name']} ({$student['student_id']}) to {$new_class_name}");

    setFlashMessage('success', "Student '{$student['name']}' has been {$promotion_type}d successfully.");
    redirect(ADMIN_URL . '/promote_student.php');
}

// Fetch students for search
$search = trim($_GET['search'] ?? '');
$students = [];
if ($search) {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.class_id, u.dept_id, d.dept_name, c.class_name
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE u.approval_status = 'approved' AND (u.name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)
        ORDER BY u.name ASC
        LIMIT 20");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get selected student for form
$selected_student = null;
$selected_id = intval($_GET['student_id'] ?? 0);
if ($selected_id > 0) {
    $stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.class_id, u.dept_id, d.dept_name, c.class_name
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE u.id = ? AND u.approval_status = 'approved'");
    $stmt->bind_param("i", $selected_id);
    $stmt->execute();
    $selected_student = $stmt->get_result()->fetch_assoc();
}

// Get departments
$departments = $conn->query("SELECT dept_id, dept_name, dept_code FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);

// Get all classes
$all_classes = $conn->query("SELECT class_id, class_name, dept_id, semester, academic_year FROM classes ORDER BY class_name")->fetch_all(MYSQLI_ASSOC);

// Promotion history
$stmt = $conn->prepare("SELECT sp.*, u.name as student_name, u.student_id as student_code,
    fc.class_name as from_class_name, tc.class_name as to_class_name,
    fd.dept_name as from_dept_name, td.dept_name as to_dept_name,
    a.name as promoted_by_name
    FROM student_promotions sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN classes fc ON sp.from_class_id = fc.class_id
    LEFT JOIN classes tc ON sp.to_class_id = tc.class_id
    LEFT JOIN departments fd ON sp.from_dept_id = fd.dept_id
    LEFT JOIN departments td ON sp.to_dept_id = td.dept_id
    LEFT JOIN admins a ON sp.promoted_by = a.id
    ORDER BY sp.created_at DESC
    LIMIT 50");
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-arrow-up-circle me-2"></i>Promote Students</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Promote Students</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Search Student -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-search me-2"></i>Search Student</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Search by Name, Student ID, or Email</label>
                <input type="text" class="form-control" name="search" placeholder="Enter name, student ID, or email..." value="<?php echo sanitize($search); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                <a href="<?php echo ADMIN_URL; ?>/promote_student.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>

        <?php if ($search && !empty($students)): ?>
            <div class="table-responsive mt-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Class</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo sanitize($s['student_id']); ?></td>
                                <td><?php echo sanitize($s['name']); ?></td>
                                <td><?php echo sanitize($s['email']); ?></td>
                                <td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($s['class_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="<?php echo ADMIN_URL; ?>/promote_student.php?student_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-arrow-up-circle me-1"></i>Select</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($search): ?>
            <div class="empty-state mt-3">
                <i class="bi bi-person-x"></i>
                <h5>No Students Found</h5>
                <p>No approved students match your search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selected_student): ?>
<!-- Promotion Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-arrow-up-circle me-2"></i>Promote / Transfer Student</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="promote_student">
            <input type="hidden" name="user_id" value="<?php echo $selected_student['id']; ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Student</label>
                    <input type="text" class="form-control" value="<?php echo sanitize($selected_student['name'] . ' (' . $selected_student['student_id'] . ')'); ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Department</label>
                    <input type="text" class="form-control" value="<?php echo sanitize($selected_student['dept_name'] ?? 'N/A'); ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Current Class</label>
                    <input type="text" class="form-control" value="<?php echo sanitize($selected_student['class_name'] ?? 'N/A'); ?>" disabled>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">New Department <span class="text-danger">*</span></label>
                    <select class="form-select" name="to_dept_id" id="to_dept_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['dept_id']; ?>"><?php echo sanitize($d['dept_name']); ?> (<?php echo sanitize($d['dept_code']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New Class <span class="text-danger">*</span></label>
                    <select class="form-select" name="to_class_id" id="to_class_id" required>
                        <option value="">Select Department First</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Promotion Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="promotion_type" required>
                        <option value="">Select Type</option>
                        <option value="promotion">Promotion</option>
                        <option value="transfer">Transfer</option>
                        <option value="demotion">Demotion</option>
                        <option value="initial">Initial</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="academic_year" placeholder="e.g. 2025-26" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Semester</label>
                    <input type="number" class="form-control" name="semester" min="1" max="10" placeholder="e.g. 3">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" name="remarks" placeholder="Optional remarks">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to promote/transfer this student?');"><i class="bi bi-check-lg me-1"></i>Promote Student</button>
                    <a href="<?php echo ADMIN_URL; ?>/promote_student.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Promotion History -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Promotion History</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($promotions)): ?>
            <div class="empty-state">
                <i class="bi bi-arrow-up-circle"></i>
                <h5>No Records</h5>
                <p>No promotions or transfers have been recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>From Class</th>
                            <th>To Class</th>
                            <th>Type</th>
                            <th>Academic Year</th>
                            <th>Promoted By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promotions as $p): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($p['student_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($p['student_code']); ?></small>
                                </td>
                                <td>
                                    <?php echo sanitize($p['from_class_name'] ?? 'N/A'); ?>
                                    <?php if ($p['from_dept_name']): ?>
                                        <br><small class="text-muted"><?php echo sanitize($p['from_dept_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo sanitize($p['to_class_name'] ?? 'N/A'); ?>
                                    <?php if ($p['to_dept_name']): ?>
                                        <br><small class="text-muted"><?php echo sanitize($p['to_dept_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?php echo $p['promotion_type']; ?>"><?php echo ucfirst($p['promotion_type']); ?></span></td>
                                <td><?php echo sanitize($p['academic_year']); ?></td>
                                <td><?php echo sanitize($p['promoted_by_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDateTime($p['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('to_dept_id')?.addEventListener('change', function() {
    const deptId = this.value;
    const classSelect = document.getElementById('to_class_id');
    classSelect.innerHTML = '<option value="">Loading...</option>';

    if (!deptId) {
        classSelect.innerHTML = '<option value="">Select Department First</option>';
        return;
    }

    fetch('<?php echo ADMIN_URL; ?>/get_classes.php?dept_id=' + deptId)
        .then(res => res.json())
        .then(data => {
            classSelect.innerHTML = '<option value="">Select Class</option>';
            data.forEach(function(cls) {
                const opt = document.createElement('option');
                opt.value = cls.class_id;
                opt.textContent = cls.class_name;
                classSelect.appendChild(opt);
            });
        })
        .catch(() => {
            classSelect.innerHTML = '<option value="">Error loading classes</option>';
        });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
