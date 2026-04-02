<?php
require_once 'includes/admin_header.php';

// Admin-only access guard
if ($_SESSION['role'] !== ROLE_ADMIN) {
    setFlashMessage('danger', 'Access denied. Administrator privileges required.');
    redirect(ADMIN_URL . '/dashboard.php');
}

$validStatuses = ['scheduled', 'active', 'completed', 'absent', 'cancelled'];
$statusBadgeClasses = [
    'scheduled'  => 'bg-primary',
    'active'     => 'bg-success',
    'completed'  => 'bg-secondary',
    'absent'     => 'bg-danger',
    'cancelled'  => 'bg-warning text-dark',
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/librarian_shifts.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $librarian_id = (int)($_POST['librarian_id'] ?? 0);
            $shift_date = sanitize($_POST['shift_date'] ?? '');
            $shift_start = sanitize($_POST['shift_start'] ?? '');
            $shift_end = sanitize($_POST['shift_end'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $status = sanitize($_POST['status'] ?? 'scheduled');

            if ($librarian_id <= 0 || empty($shift_date) || empty($shift_start) || empty($shift_end)) {
                setFlashMessage('error', 'Librarian, date, start time, and end time are required.');
                redirect(ADMIN_URL . '/librarian_shifts.php');
            }

            if (!in_array($status, $validStatuses)) {
                $status = 'scheduled';
            }

            $stmt = $conn->prepare("INSERT INTO librarian_shifts (librarian_id, shift_date, shift_start, shift_end, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('isssssi', $librarian_id, $shift_date, $shift_start, $shift_end, $status, $notes, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $stmt->close();
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_shift', 'Added shift for librarian ID: ' . $librarian_id . ' on ' . $shift_date);
                setFlashMessage('success', 'Shift added successfully.');
            } else {
                $stmt->close();
                setFlashMessage('error', 'Failed to add shift.');
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;

        case 'edit':
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            $librarian_id = (int)($_POST['librarian_id'] ?? 0);
            $shift_date = sanitize($_POST['shift_date'] ?? '');
            $shift_start = sanitize($_POST['shift_start'] ?? '');
            $shift_end = sanitize($_POST['shift_end'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $status = sanitize($_POST['status'] ?? 'scheduled');

            if ($shift_id <= 0 || $librarian_id <= 0 || empty($shift_date) || empty($shift_start) || empty($shift_end)) {
                setFlashMessage('error', 'All fields are required.');
                redirect(ADMIN_URL . '/librarian_shifts.php');
            }

            if (!in_array($status, $validStatuses)) {
                $status = 'scheduled';
            }

            $stmt = $conn->prepare("UPDATE librarian_shifts SET librarian_id=?, shift_date=?, shift_start=?, shift_end=?, status=?, notes=? WHERE shift_id=?");
            $stmt->bind_param('isssssi', $librarian_id, $shift_date, $shift_start, $shift_end, $status, $notes, $shift_id);

            if ($stmt->execute()) {
                $stmt->close();
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_shift', 'Updated shift ID: ' . $shift_id);
                setFlashMessage('success', 'Shift updated successfully.');
            } else {
                $stmt->close();
                setFlashMessage('error', 'Failed to update shift.');
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;

        case 'delete':
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            if ($shift_id > 0) {
                $stmt = $conn->prepare("DELETE FROM librarian_shifts WHERE shift_id = ?");
                $stmt->bind_param('i', $shift_id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'delete_shift', 'Deleted shift ID: ' . $shift_id);
                    setFlashMessage('success', 'Shift deleted successfully.');
                } else {
                    setFlashMessage('error', 'Failed to delete shift.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;

        case 'mark_active':
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            if ($shift_id > 0) {
                $stmt = $conn->prepare("UPDATE librarian_shifts SET status = 'active' WHERE shift_id = ?");
                $stmt->bind_param('i', $shift_id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'shift_active', 'Marked shift ID ' . $shift_id . ' as active');
                    setFlashMessage('success', 'Shift marked as active.');
                } else {
                    setFlashMessage('error', 'Failed to update shift status.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;

        case 'mark_completed':
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            if ($shift_id > 0) {
                $stmt = $conn->prepare("UPDATE librarian_shifts SET status = 'completed' WHERE shift_id = ?");
                $stmt->bind_param('i', $shift_id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'shift_completed', 'Marked shift ID ' . $shift_id . ' as completed');
                    setFlashMessage('success', 'Shift marked as completed.');
                } else {
                    setFlashMessage('error', 'Failed to update shift status.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;

        case 'mark_absent':
            $shift_id = (int)($_POST['shift_id'] ?? 0);
            if ($shift_id > 0) {
                $stmt = $conn->prepare("UPDATE librarian_shifts SET status = 'absent' WHERE shift_id = ?");
                $stmt->bind_param('i', $shift_id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'shift_absent', 'Marked shift ID ' . $shift_id . ' as absent');
                    setFlashMessage('success', 'Shift marked as absent.');
                } else {
                    setFlashMessage('error', 'Failed to update shift status.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/librarian_shifts.php');
            break;
    }
}

// Fetch librarians for dropdown
$librariansResult = $conn->query("SELECT id, name, email FROM admins WHERE role = 'librarian' AND is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
$librarians = [];
while ($row = $librariansResult->fetch_assoc()) {
    $librarians[] = $row;
}

// Stats
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$monthStart = date('Y-m-01');

$todayShiftsResult = $conn->query("SELECT COUNT(*) as cnt FROM librarian_shifts WHERE shift_date = '{$today}'");
$todayShifts = $todayShiftsResult->fetch_assoc()['cnt'];

$activeNowResult = $conn->query("SELECT COUNT(*) as cnt FROM librarian_shifts WHERE shift_date = '{$today}' AND status = 'active'");
$activeNow = $activeNowResult->fetch_assoc()['cnt'];

$scheduledWeekResult = $conn->query("SELECT COUNT(*) as cnt FROM librarian_shifts WHERE shift_date BETWEEN '{$weekStart}' AND '{$weekEnd}' AND status = 'scheduled'");
$scheduledWeek = $scheduledWeekResult->fetch_assoc()['cnt'];

$absentMonthResult = $conn->query("SELECT COUNT(*) as cnt FROM librarian_shifts WHERE shift_date >= '{$monthStart}' AND status = 'absent'");
$absentMonth = $absentMonthResult->fetch_assoc()['cnt'];

// Today's schedule
$todayStmt = $conn->prepare("SELECT ls.*, a.name as librarian_name FROM librarian_shifts ls JOIN admins a ON ls.librarian_id = a.id WHERE ls.shift_date = ? ORDER BY ls.shift_start ASC");
$todayStmt->bind_param('s', $today);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();
$todaySchedule = [];
while ($row = $todayResult->fetch_assoc()) {
    $todaySchedule[] = $row;
}
$todayStmt->close();

// Weekly calendar data (Monday to Sunday)
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', strtotime("monday this week +{$i} days"));
    $weekDays[$day] = [];
}

$weekStmt = $conn->prepare("SELECT ls.*, a.name as librarian_name FROM librarian_shifts ls JOIN admins a ON ls.librarian_id = a.id WHERE ls.shift_date BETWEEN ? AND ? ORDER BY ls.shift_date ASC, ls.shift_start ASC");
$weekStmt->bind_param('ss', $weekStart, $weekEnd);
$weekStmt->execute();
$weekResult = $weekStmt->get_result();
while ($row = $weekResult->fetch_assoc()) {
    $weekDays[$row['shift_date']][] = $row;
}
$weekStmt->close();

$csrfToken = generateCSRFToken();
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Librarian Shifts</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick="resetForm()">
            <i class="fas fa-plus"></i> Add Shift
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Today's Shifts</h5>
                    <p class="card-text display-6"><?php echo $todayShifts; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Active Now</h5>
                    <p class="card-text display-6"><?php echo $activeNow; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Scheduled This Week</h5>
                    <p class="card-text display-6"><?php echo $scheduledWeek; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Absent This Month</h5>
                    <p class="card-text display-6"><?php echo $absentMonth; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Today's Schedule <small class="text-muted"><?php echo formatDate($today); ?></small></h5>
        </div>
        <div class="card-body table-responsive">
            <?php if (count($todaySchedule) === 0): ?>
                <p class="text-muted text-center mb-0">No shifts scheduled for today.</p>
            <?php else: ?>
                <table class="table table-bordered table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Librarian</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todaySchedule as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['librarian_name']); ?></td>
                                <td><?php echo date('g:i A', strtotime($shift['shift_start'])) . ' - ' . date('g:i A', strtotime($shift['shift_end'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $statusBadgeClasses[$shift['status']] ?? 'bg-secondary'; ?>">
                                        <?php echo ucfirst($shift['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($shift['notes'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($shift['status'] === 'scheduled'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this shift as active?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="mark_active">
                                                <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Start Shift">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($shift['status'] === 'active'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this shift as completed?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="mark_completed">
                                                <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                <button type="submit" class="btn btn-secondary" title="Complete Shift">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($shift['status'], ['scheduled', 'active'])): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this librarian as absent?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="mark_absent">
                                                <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                <button type="submit" class="btn btn-danger" title="Mark Absent">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn btn-warning" title="Edit" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick='<?php echo "editShift(" . htmlspecialchars(json_encode($shift)) . ")"; ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Weekly Calendar -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Weekly Calendar</h5>
            <span class="text-muted"><?php echo formatDate($weekStart) . ' - ' . formatDate($weekEnd); ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $i = 0;
                foreach ($weekDays as $dayDate => $dayShifts):
                    $isToday = ($dayDate === $today);
                ?>
                    <div class="col-md mb-2">
                        <div class="card <?php echo $isToday ? 'border-primary' : ''; ?>">
                            <div class="card-header text-center <?php echo $isToday ? 'bg-primary text-white' : 'bg-light'; ?>">
                                <strong><?php echo $dayNames[$i]; ?></strong><br>
                                <small><?php echo formatDate($dayDate); ?></small>
                            </div>
                            <div class="card-body p-2" style="min-height: 120px; max-height: 250px; overflow-y: auto;">
                                <?php if (count($dayShifts) === 0): ?>
                                    <p class="text-muted text-center small mb-0 mt-2">No shifts</p>
                                <?php else: ?>
                                    <?php foreach ($dayShifts as $shift): ?>
                                        <div class="mb-1 p-2 border rounded">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <small class="fw-bold"><?php echo htmlspecialchars($shift['librarian_name']); ?></small>
                                                <span class="badge <?php echo $statusBadgeClasses[$shift['status']] ?? 'bg-secondary'; ?> badge-sm">
                                                    <?php echo ucfirst($shift['status']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('g:iA', strtotime($shift['shift_start'])) . '-' . date('g:iA', strtotime($shift['shift_end'])); ?>
                                            </small>
                                            <?php if ($shift['notes']): ?>
                                                <br><small class="text-muted fst-italic"><?php echo htmlspecialchars($shift['notes']); ?></small>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <button class="btn btn-sm btn-outline-warning py-0 px-1" title="Edit" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick='<?php echo "editShift(" . htmlspecialchars(json_encode($shift)) . ")"; ?>'>
                                                    <i class="fas fa-edit fa-xs"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this shift?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete">
                                                        <i class="fas fa-trash fa-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        </div>
    </div>

    <!-- All Shifts Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> All Shifts</h5>
        </div>
        <div class="card-body table-responsive">
            <?php
            $allStmt = $conn->prepare("SELECT ls.*, a.name as librarian_name FROM librarian_shifts ls JOIN admins a ON ls.librarian_id = a.id ORDER BY ls.shift_date DESC, ls.shift_start ASC LIMIT 100");
            $allStmt->execute();
            $allResult = $allStmt->get_result();
            $allShifts = [];
            while ($row = $allResult->fetch_assoc()) {
                $allShifts[] = $row;
            }
            $allStmt->close();
            ?>
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Librarian</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allShifts) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No shifts found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allShifts as $shift): ?>
                            <tr>
                                <td><?php echo $shift['shift_id']; ?></td>
                                <td><?php echo htmlspecialchars($shift['librarian_name']); ?></td>
                                <td><?php echo formatDate($shift['shift_date']); ?></td>
                                <td><?php echo date('g:i A', strtotime($shift['shift_start'])) . ' - ' . date('g:i A', strtotime($shift['shift_end'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $statusBadgeClasses[$shift['status']] ?? 'bg-secondary'; ?>">
                                        <?php echo ucfirst($shift['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($shift['notes'] ?? ''); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-warning" title="Edit" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick='<?php echo "editShift(" . htmlspecialchars(json_encode($shift)) . ")"; ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="shift_id" value="<?php echo $shift['shift_id']; ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="shiftForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="shift_id" id="formShiftId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="shiftModalLabel">Add Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Librarian <span class="text-danger">*</span></label>
                        <select name="librarian_id" id="formLibrarian" class="form-select" required>
                            <option value="">-- Select Librarian --</option>
                            <?php foreach ($librarians as $lib): ?>
                                <option value="<?php echo $lib['id']; ?>"><?php echo htmlspecialchars($lib['name']); ?> (<?php echo htmlspecialchars($lib['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shift Date <span class="text-danger">*</span></label>
                        <input type="date" name="shift_date" id="formDate" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="shift_start" id="formStart" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="shift_end" id="formEnd" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="formStatus" class="form-select">
                            <option value="scheduled">Scheduled</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="absent">Absent</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="formNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="formSubmitBtn">Add Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formShiftId').value = '';
    document.getElementById('shiftModalLabel').textContent = 'Add Shift';
    document.getElementById('formSubmitBtn').textContent = 'Add Shift';
    document.getElementById('shiftForm').reset();
}

function editShift(shift) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formShiftId').value = shift.shift_id;
    document.getElementById('shiftModalLabel').textContent = 'Edit Shift';
    document.getElementById('formSubmitBtn').textContent = 'Update Shift';
    document.getElementById('formLibrarian').value = shift.librarian_id;
    document.getElementById('formDate').value = shift.shift_date;
    document.getElementById('formStart').value = shift.shift_start.substring(0, 5);
    document.getElementById('formEnd').value = shift.shift_end.substring(0, 5);
    document.getElementById('formStatus').value = shift.status;
    document.getElementById('formNotes').value = shift.notes || '';
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
