<?php
$page_title = "My Shift Control";
require_once 'includes/librarian_header.php';

// Handle shift toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(SITE_URL . '/librarian/my_shift.php');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'open_shift') {
        // Check if already has active shift
        $check = $conn->prepare("SELECT shift_id FROM librarian_shifts WHERE librarian_id = ? AND shift_date = CURDATE() AND status = 'active'");
        $check->bind_param("i", $_SESSION['user_id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            setFlashMessage('warning', 'You already have an active shift today.');
        } else {
            $now = date('H:i:s');
            $end = date('H:i:s', strtotime('+8 hours'));
            $status = 'active';
            $notes = 'Manually opened';
            $stmt = $conn->prepare("INSERT INTO librarian_shifts (librarian_id, shift_date, shift_start, shift_end, status, notes, created_by) VALUES (?, CURDATE(), ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $_SESSION['user_id'], $now, $end, $status, $notes, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['auto_shift_id'] = $stmt->insert_id;
                $auth->logActivity('librarian', $_SESSION['user_id'], 'shift_open', 'Librarian opened their shift');
                setFlashMessage('success', 'Shift opened successfully at ' . date('h:i A'));
            }
        }
    } elseif ($action === 'close_shift') {
        $shift_id = (int)($_POST['shift_id'] ?? 0);
        if ($shift_id > 0) {
            $close = $conn->prepare("UPDATE librarian_shifts SET status = 'completed', notes = CONCAT(IFNULL(notes,''), ' | Manually closed') WHERE shift_id = ? AND librarian_id = ? AND status = 'active'");
            $close->bind_param("ii", $shift_id, $_SESSION['user_id']);
            if ($close->execute() && $close->affected_rows > 0) {
                $auth->logActivity('librarian', $_SESSION['user_id'], 'shift_close', 'Librarian closed their shift');
                unset($_SESSION['auto_shift_id']);
                setFlashMessage('success', 'Shift closed successfully at ' . date('h:i A'));
            } else {
                setFlashMessage('danger', 'Shift not found or already closed.');
            }
        }
    }
    redirect(SITE_URL . '/librarian/my_shift.php');
}

$csrf_token = generateCSRFToken();

// Get today's shifts
$stmt = $conn->prepare("SELECT * FROM librarian_shifts WHERE librarian_id = ? AND shift_date = CURDATE() ORDER BY shift_start DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$today_shifts = $stmt->get_result();

// Get active shift
$stmt2 = $conn->prepare("SELECT * FROM librarian_shifts WHERE librarian_id = ? AND shift_date = CURDATE() AND status = 'active' LIMIT 1");
$stmt2->bind_param("i", $_SESSION['user_id']);
$stmt2->execute();
$active_shift = $stmt2->get_result()->fetch_assoc();

// Get recent shift history (last 7 days)
$stmt3 = $conn->prepare("SELECT ls.*, DATE_FORMAT(ls.shift_date, '%b %d, %Y') as formatted_date, TIME_FORMAT(ls.shift_start, '%h:%i %p') as start_formatted, TIME_FORMAT(ls.shift_end, '%h:%i %p') as end_formatted 
    FROM librarian_shifts ls WHERE ls.librarian_id = ? AND ls.shift_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY ls.shift_date DESC, ls.shift_start DESC");
$stmt3->bind_param("i", $_SESSION['user_id']);
$stmt3->execute();
$shift_history = $stmt3->get_result();

// Get session history
$stmt4 = $conn->prepare("SELECT *, DATE_FORMAT(login_time, '%b %d %h:%i %p') as login_fmt, DATE_FORMAT(logout_time, '%b %d %h:%i %p') as logout_fmt FROM librarian_sessions WHERE librarian_id = ? ORDER BY login_time DESC LIMIT 10");
$stmt4->bind_param("i", $_SESSION['user_id']);
$stmt4->execute();
$sessions = $stmt4->get_result();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-clock-history me-2"></i>My Shift Control</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/librarian/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Shift</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Current Shift Status -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card <?php echo $active_shift ? 'border-success' : 'border-warning'; ?>">
            <div class="card-body text-center py-4">
                <?php if ($active_shift): ?>
                    <div class="mb-3">
                        <i class="bi bi-clock-fill text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="text-success mb-2">You Are On Shift</h3>
                    <p class="text-muted mb-1">Shift opened at: <strong><?php echo date('h:i A', strtotime($active_shift['shift_start'])); ?></strong></p>
                    <p class="text-muted mb-1">Scheduled end: <strong><?php echo date('h:i A', strtotime($active_shift['shift_end'])); ?></strong></p>
                    <?php if ($active_shift['notes']): ?>
                        <p class="text-muted small">Notes: <?php echo sanitize($active_shift['notes']); ?></p>
                    <?php endif; ?>
                    <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to close your shift?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="close_shift">
                        <input type="hidden" name="shift_id" value="<?php echo $active_shift['shift_id']; ?>">
                        <button type="submit" class="btn btn-danger btn-lg px-5"><i class="bi bi-stop-circle me-2"></i>Close Shift</button>
                    </form>
                <?php else: ?>
                    <div class="mb-3">
                        <i class="bi bi-clock text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="text-warning mb-2">No Active Shift</h3>
                    <p class="text-muted mb-3">You are not currently on shift. Open a shift to start working.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="open_shift">
                        <button type="submit" class="btn btn-success btn-lg px-5"><i class="bi bi-play-circle me-2"></i>Open Shift</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body text-center">
                <i class="bi bi-calendar-check stat-icon text-primary"></i>
                <h6 class="text-muted mt-2">Today's Date</h6>
                <h4><?php echo date('M d, Y'); ?></h4>
                <hr>
                <i class="bi bi-clock stat-icon text-info"></i>
                <h6 class="text-muted mt-2">Current Time</h6>
                <h4 id="live-clock"><?php echo date('h:i:s A'); ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Today's Shifts -->
<div class="card mb-4">
    <div class="card-header"><h5><i class="bi bi-calendar-day me-2"></i>Today's Shifts</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Start</th><th>End</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php if ($today_shifts->num_rows > 0): ?>
                        <?php while ($s = $today_shifts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('h:i A', strtotime($s['shift_start'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($s['shift_end'])); ?></td>
                                <td>
                                    <?php
                                    $status_colors = ['active' => 'success', 'completed' => 'primary', 'scheduled' => 'info', 'absent' => 'danger', 'cancelled' => 'secondary'];
                                    $color = $status_colors[$s['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($s['status']); ?></span>
                                </td>
                                <td><?php echo sanitize($s['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No shifts recorded today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Shift History -->
<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-clock-history me-2"></i>Recent Shift History (7 days)</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($shift_history->num_rows > 0): ?>
                                <?php while ($sh = $shift_history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $sh['formatted_date']; ?></td>
                                        <td><?php echo $sh['start_formatted']; ?></td>
                                        <td><?php echo $sh['end_formatted']; ?></td>
                                        <td><span class="badge bg-<?php echo ($sh['status'] === 'active') ? 'success' : (($sh['status'] === 'completed') ? 'primary' : 'secondary'); ?>"><?php echo ucfirst($sh['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No shift history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-pc-display me-2"></i>Recent Login Sessions</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Login</th><th>Logout</th><th>IP</th></tr></thead>
                        <tbody>
                            <?php if ($sessions->num_rows > 0): ?>
                                <?php while ($sess = $sessions->fetch_assoc()): ?>
                                    <tr>
                                        <td class="small"><?php echo $sess['login_fmt'] ?? date('M d h:i A', strtotime($sess['login_time'])); ?></td>
                                        <td class="small"><?php echo $sess['logout_fmt'] ?? ($sess['is_active'] ? '<span class="badge bg-success">Active</span>' : '-'); ?></td>
                                        <td class="small"><code><?php echo sanitize($sess['ip_address'] ?? '-'); ?></code></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No session history.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live clock
setInterval(function() {
    const now = new Date();
    document.getElementById('live-clock').textContent = now.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
}, 1000);
</script>

<?php require_once 'includes/librarian_footer.php'; ?>
