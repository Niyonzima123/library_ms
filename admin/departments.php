<?php
$page_title = "Departments";
require_once __DIR__ . '/includes/admin_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/departments.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $dept_code = trim($_POST['dept_code'] ?? '');
        $dept_name = trim($_POST['dept_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($dept_code) || empty($dept_name)) {
            setFlashMessage('danger', 'Department code and name are required.');
            redirect(ADMIN_URL . '/departments.php');
        }

        if ($action === 'add') {
            // Check for duplicate code
            $stmt = $conn->prepare("SELECT dept_id FROM departments WHERE dept_code = ?");
            $stmt->bind_param("s", $dept_code);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                setFlashMessage('danger', 'Department code already exists.');
                redirect(ADMIN_URL . '/departments.php');
            }

            $stmt = $conn->prepare("INSERT INTO departments (dept_code, dept_name, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $dept_code, $dept_name, $description);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_department', "Added department: {$dept_name} ({$dept_code})");
                setFlashMessage('success', 'Department added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add department.');
            }
        } else {
            $dept_id = (int)($_POST['dept_id'] ?? 0);

            // Check for duplicate code (excluding self)
            $stmt = $conn->prepare("SELECT dept_id FROM departments WHERE dept_code = ? AND dept_id != ?");
            $stmt->bind_param("si", $dept_code, $dept_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                setFlashMessage('danger', 'Department code already exists.');
                redirect(ADMIN_URL . '/departments.php');
            }

            $stmt = $conn->prepare("UPDATE departments SET dept_code = ?, dept_name = ?, description = ? WHERE dept_id = ?");
            $stmt->bind_param("sssi", $dept_code, $dept_name, $description, $dept_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_department', "Updated department ID: {$dept_id}");
                setFlashMessage('success', 'Department updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update department.');
            }
        }
        redirect(ADMIN_URL . '/departments.php');
    }

    if ($action === 'delete') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);

        // Check for classes
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM classes WHERE dept_id = ?");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $class_count = $stmt->get_result()->fetch_assoc()['cnt'];

        // Check for students
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE dept_id = ?");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $student_count = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($class_count > 0 || $student_count > 0) {
            setFlashMessage('danger', "Cannot delete department. It has {$class_count} class(es) and {$student_count} student(s) associated.");
        } else {
            $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
            $stmt->bind_param("i", $dept_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'delete_department', "Deleted department ID: {$dept_id}");
                setFlashMessage('success', 'Department deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete department.');
            }
        }
        redirect(ADMIN_URL . '/departments.php');
    }
}

// Determine if editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$add_new = isset($_GET['action']) && $_GET['action'] === 'add';
$edit_dept = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM departments WHERE dept_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_dept = $stmt->get_result()->fetch_assoc();
}

// Fetch departments with class count and student count
$stmt = $conn->prepare("
    SELECT d.*,
        (SELECT COUNT(*) FROM classes cl WHERE cl.dept_id = d.dept_id) as class_count,
        (SELECT COUNT(*) FROM users u WHERE u.dept_id = d.dept_id) as student_count
    FROM departments d
    ORDER BY d.dept_name ASC
");
$stmt->execute();
$departments = $stmt->get_result();

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-building me-2"></i>Departments</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Departments</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/departments.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Department</a>
    </div>
</div>

<?php if ($add_new || $edit_dept): ?>
<!-- Add/Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-<?php echo $edit_dept ? 'pencil' : 'plus-lg'; ?> me-2"></i><?php echo $edit_dept ? 'Edit Department' : 'Add New Department'; ?></h5>
        <a href="<?php echo ADMIN_URL; ?>/departments.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo ADMIN_URL; ?>/departments.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_dept ? 'edit' : 'add'; ?>">
            <?php if ($edit_dept): ?>
                <input type="hidden" name="dept_id" value="<?php echo $edit_dept['dept_id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="dept_code" class="form-label">Department Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="dept_code" name="dept_code" value="<?php echo sanitize($edit_dept['dept_code'] ?? ''); ?>" required maxlength="20">
                </div>
                <div class="col-md-4">
                    <label for="dept_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="dept_name" name="dept_name" value="<?php echo sanitize($edit_dept['dept_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?php echo sanitize($edit_dept['description'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> <?php echo $edit_dept ? 'Update Department' : 'Add Department'; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Departments Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Classes</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments->num_rows > 0): ?>
                        <?php $i = 1; while ($dept = $departments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><span class="badge bg-primary"><?php echo sanitize($dept['dept_code']); ?></span></td>
                                <td><strong><?php echo sanitize($dept['dept_name']); ?></strong></td>
                                <td><?php echo sanitize($dept['description'] ?? '-'); ?></td>
                                <td><span class="badge bg-info"><?php echo $dept['class_count']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $dept['student_count']; ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?php echo ADMIN_URL; ?>/departments.php?edit=<?php echo $dept['dept_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/departments.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this department?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="dept_id" value="<?php echo $dept['dept_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-building"></i><h5>No Departments</h5><p>No departments have been added yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
