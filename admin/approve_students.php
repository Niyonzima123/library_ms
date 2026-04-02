<?php
$page_title = 'Approve Students';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/approve_students.php');
    }

    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        setFlashMessage('danger', 'Invalid user ID.');
        redirect(ADMIN_URL . '/approve_students.php');
    }

    $stmt = $conn->prepare("SELECT id, name, student_id, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        setFlashMessage('danger', 'Student not found.');
        redirect(ADMIN_URL . '/approve_students.php');
    }

    if ($action === 'approve') {
        $new_status = STATUS_APPROVED;
        $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();

        $auth->createStudentNotification($user_id, 'Registration Approved', 'Congratulations! Your registration has been approved. You can now access the library.', STUDENT_URL . '/dashboard.php');
        $auth->logActivity('admin', $_SESSION['user_id'], 'approve_student', "Approved student {$student['name']} ({$student['student_id']})");

        setFlashMessage('success', "Student '{$student['name']}' has been approved.");
    } elseif ($action === 'reject') {
        $new_status = STATUS_REJECTED;
        $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        $stmt->execute();

        $reason = trim($_POST['reason'] ?? '');
        $notif_msg = 'Your registration has been rejected.';
        if ($reason) {
            $notif_msg .= ' Reason: ' . $reason;
        }
        $auth->createStudentNotification($user_id, 'Registration Rejected', $notif_msg, '');
        $auth->logActivity('admin', $_SESSION['user_id'], 'reject_student', "Rejected student {$student['name']} ({$student['student_id']})");

        setFlashMessage('success', "Student '{$student['name']}' has been rejected.");
    }

    redirect(ADMIN_URL . '/approve_students.php');
}

// Get pending students
$stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.mobile, u.approval_status, u.card_number, u.created_at,
    d.dept_name, c.class_name
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.approval_status = 'pending'
    ORDER BY u.created_at ASC");
$stmt->execute();
$pending_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recently approved/rejected
$stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.approval_status, u.updated_at,
    d.dept_name, c.class_name
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.approval_status IN ('approved', 'rejected')
    ORDER BY u.updated_at DESC
    LIMIT 20");
$stmt->execute();
$recent_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pending_count = count($pending_students);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-person-check me-2"></i>Approve Students</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Approve Students</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($pending_count > 0): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <strong><?php echo $pending_count; ?> student<?php echo $pending_count > 1 ? 's' : ''; ?> awaiting approval</strong>
    </div>
<?php endif; ?>

<!-- Pending Students -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-hourglass-split me-2"></i>Pending Approval (<?php echo $pending_count; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pending_students)): ?>
            <div class="empty-state">
                <i class="bi bi-check-circle"></i>
                <h5>All Clear!</h5>
                <p>No students are pending approval.</p>
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
                            <th>Registered On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_students as $s): ?>
                            <tr>
                                <td><?php echo sanitize($s['student_id']); ?></td>
                                <td>
                                    <strong><?php echo sanitize($s['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($s['mobile'] ?? ''); ?></small>
                                </td>
                                <td><?php echo sanitize($s['email']); ?></td>
                                <td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($s['class_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDate($s['created_at']); ?></td>
                                <td class="action-btns">
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Approve this student?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#reject_<?php echo $s['id']; ?>"><i class="bi bi-x-lg me-1"></i>Reject</button>
                                </td>
                            </tr>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="reject_<?php echo $s['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to reject <strong><?php echo sanitize($s['name']); ?></strong> (<?php echo sanitize($s['student_id']); ?>)?</p>
                                                <div class="mb-3">
                                                    <label class="form-label">Reason (optional)</label>
                                                    <textarea class="form-control" name="reason" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject Student</button>
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

<!-- Recently Processed -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Recently Processed</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent_students)): ?>
            <div class="empty-state">
                <i class="bi bi-clock"></i>
                <h5>No Records</h5>
                <p>No students have been processed yet.</p>
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
                            <th>Processed On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_students as $s): ?>
                            <tr>
                                <td><?php echo sanitize($s['student_id']); ?></td>
                                <td><?php echo sanitize($s['name']); ?></td>
                                <td><?php echo sanitize($s['email']); ?></td>
                                <td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($s['class_name'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge <?php echo $s['approval_status']; ?>"><?php echo ucfirst($s['approval_status']); ?></span></td>
                                <td><?php echo formatDate($s['updated_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
