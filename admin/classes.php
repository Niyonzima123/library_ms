<?php
$page_title = "Classes";
require_once __DIR__ . '/includes/admin_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/classes.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $class_name = trim($_POST['class_name'] ?? '');
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 0);
        $academic_year = trim($_POST['academic_year'] ?? '');

        if (empty($class_name) || $dept_id <= 0) {
            setFlashMessage('danger', 'Class name and department are required.');
            redirect(ADMIN_URL . '/classes.php');
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO classes (class_name, dept_id, semester, academic_year) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $class_name, $dept_id, $semester, $academic_year);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_class', "Added class: {$class_name}");
                setFlashMessage('success', 'Class added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add class.');
            }
        } else {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE classes SET class_name = ?, dept_id = ?, semester = ?, academic_year = ? WHERE class_id = ?");
            $stmt->bind_param("siisi", $class_name, $dept_id, $semester, $academic_year, $class_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_class', "Updated class ID: {$class_id}");
                setFlashMessage('success', 'Class updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update class.');
            }
        }
        redirect(ADMIN_URL . '/classes.php');
    }

    if ($action === 'delete') {
        $class_id = (int)($_POST['class_id'] ?? 0);

        // Check for students
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($count > 0) {
            setFlashMessage('danger', "Cannot delete class. It has {$count} student(s) enrolled.");
        } else {
            $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->bind_param("i", $class_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'delete_class', "Deleted class ID: {$class_id}");
                setFlashMessage('success', 'Class deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete class.');
            }
        }
        redirect(ADMIN_URL . '/classes.php');
    }
}

// Determine if editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$add_new = isset($_GET['action']) && $_GET['action'] === 'add';
$edit_class = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_class = $stmt->get_result()->fetch_assoc();
}

// Fetch classes with department name and student count
$stmt = $conn->prepare("
    SELECT cl.*, d.dept_name,
        (SELECT COUNT(*) FROM users u WHERE u.class_id = cl.class_id) as student_count
    FROM classes cl
    JOIN departments d ON cl.dept_id = d.dept_id
    ORDER BY d.dept_name ASC, cl.class_name ASC
");
$stmt->execute();
$classes = $stmt->get_result();

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-people me-2"></i>Classes</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Classes</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/classes.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Class</a>
    </div>
</div>

<?php if ($add_new || $edit_class): ?>
<!-- Add/Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-<?php echo $edit_class ? 'pencil' : 'plus-lg'; ?> me-2"></i><?php echo $edit_class ? 'Edit Class' : 'Add New Class'; ?></h5>
        <a href="<?php echo ADMIN_URL; ?>/classes.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo ADMIN_URL; ?>/classes.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_class ? 'edit' : 'add'; ?>">
            <?php if ($edit_class): ?>
                <input type="hidden" name="class_id" value="<?php echo $edit_class['class_id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="class_name" class="form-label">Class Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="class_name" name="class_name" value="<?php echo sanitize($edit_class['class_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="dept_id" name="dept_id" required>
                        <option value="">Select Department</option>
                        <?php
                        $dept_dd = $conn->query("SELECT dept_id, dept_name, dept_code FROM departments ORDER BY dept_name ASC");
                        while ($d = $dept_dd->fetch_assoc()):
                        ?>
                            <option value="<?php echo $d['dept_id']; ?>" <?php echo ($edit_class['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : ''; ?>><?php echo sanitize($d['dept_name']); ?> (<?php echo sanitize($d['dept_code']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="semester" class="form-label">Semester</label>
                    <input type="number" min="1" max="10" class="form-control" id="semester" name="semester" value="<?php echo sanitize($edit_class['semester'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="e.g. 2025-26" value="<?php echo sanitize($edit_class['academic_year'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> <?php echo $edit_class ? 'Update Class' : 'Add Class'; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Classes Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Class Name</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($classes->num_rows > 0): ?>
                        <?php $i = 1; while ($cl = $classes->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo sanitize($cl['class_name']); ?></strong></td>
                                <td><?php echo sanitize($cl['dept_name']); ?></td>
                                <td><?php echo $cl['semester'] ? sanitize($cl['semester']) : '-'; ?></td>
                                <td><?php echo sanitize($cl['academic_year'] ?? '-'); ?></td>
                                <td><span class="badge bg-success"><?php echo $cl['student_count']; ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?php echo ADMIN_URL; ?>/classes.php?edit=<?php echo $cl['class_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/classes.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?php echo $cl['class_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i><h5>No Classes</h5><p>No classes have been added yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
