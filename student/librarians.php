<?php
$page_title = 'Library Staff & Hours';
include 'includes/student_header.php';

// Get all active librarians with their current shift status
$stmt = $conn->prepare("SELECT a.id, a.name, a.email, a.profile_image, a.shift_preference,
    ls.shift_start, ls.shift_end, ls.status as shift_status, ls.shift_id
    FROM admins a
    LEFT JOIN librarian_shifts ls ON a.id = ls.librarian_id AND ls.shift_date = CURDATE() AND ls.status = 'active'
    WHERE a.role = 'librarian' AND a.is_active = 1 AND a.deleted_at IS NULL
    ORDER BY ls.status DESC, a.name ASC");
$stmt->execute();
$librarians = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count librarians on duty
$on_duty = 0;
foreach ($librarians as $l) {
    if ($l['shift_status'] === 'active') $on_duty++;
}

// Get today's schedule (all shifts for today)
$stmt2 = $conn->prepare("SELECT ls.*, a.name FROM librarian_shifts ls JOIN admins a ON ls.librarian_id = a.id WHERE ls.shift_date = CURDATE() ORDER BY ls.shift_start ASC");
$stmt2->execute();
$today_shifts = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Get weekly schedule
$stmt3 = $conn->prepare("SELECT ls.*, a.name, DAYNAME(ls.shift_date) as day_name FROM librarian_shifts ls JOIN admins a ON ls.librarian_id = a.id WHERE ls.shift_date >= CURDATE() AND ls.shift_date < DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY ls.shift_date ASC, ls.shift_start ASC");
$stmt3->execute();
$weekly_shifts = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-people me-2"></i>Library Staff & Hours</h4>
        <p class="text-muted mb-0">View librarian availability and library working hours</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people"></i></div>
        <div class="stat-info"><h3><?php echo count($librarians); ?></h3><p>Total Staff</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-info"><h3><?php echo $on_duty; ?></h3><p>On Duty Now</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-clock"></i></div>
        <div class="stat-info"><h3>Mon-Fri</h3><p>8:00 AM - 8:00 PM</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info"><h3>Sat</h3><p>9:00 AM - 5:00 PM</p></div>
    </div>
</div>

<!-- Librarian Cards -->
<div class="card mb-4">
    <div class="card-header"><h5><i class="bi bi-person-badge me-2"></i>Staff Members</h5></div>
    <div class="card-body">
        <?php if (empty($librarians)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>No Staff Found</h5>
                <p>No librarians are currently registered.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($librarians as $lib): ?>
                    <div class="col-md-4 col-6">
                        <div class="card h-100 <?php echo $lib['shift_status'] === 'active' ? 'border-success' : ''; ?>" style="<?php echo $lib['shift_status'] === 'active' ? 'border-width:2px;' : ''; ?>">
                            <div class="card-body text-center">
                                <div class="mx-auto mb-2" style="width:60px;height:60px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                                    <?php if ($lib['profile_image']): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $lib['profile_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <i class="bi bi-person-circle" style="font-size:2rem;color:#999;"></i>
                                    <?php endif; ?>
                                </div>
                                <h6 class="mb-1"><?php echo sanitize($lib['name']); ?></h6>
                                <?php if ($lib['shift_status'] === 'active'): ?>
                                    <span class="badge bg-success mb-2"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>On Shift</span>
                                    <br><small class="text-muted">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('h:i A', strtotime($lib['shift_start'])); ?> - <?php echo date('h:i A', strtotime($lib['shift_end'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="badge bg-secondary mb-2"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Off Duty</span>
                                    <br><small class="text-muted">Not currently available</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Today's Schedule -->
<div class="card mb-4">
    <div class="card-header"><h5><i class="bi bi-calendar-day me-2"></i>Today's Schedule</h5></div>
    <div class="card-body p-0">
        <?php if (empty($today_shifts)): ?>
            <div class="empty-state"><p class="text-muted py-3">No shifts scheduled for today.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Staff Member</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($today_shifts as $shift): ?>
                            <tr>
                                <td><strong><?php echo sanitize($shift['name']); ?></strong></td>
                                <td><?php echo date('h:i A', strtotime($shift['shift_start'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($shift['shift_end'])); ?></td>
                                <td>
                                    <?php
                                    $s_colors = ['active' => 'success', 'completed' => 'primary', 'scheduled' => 'info', 'absent' => 'danger'];
                                    $s_color = $s_colors[$shift['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $s_color; ?>"><?php echo ucfirst($shift['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Weekly Schedule -->
<div class="card mb-4">
    <div class="card-header"><h5><i class="bi bi-calendar-week me-2"></i>This Week's Schedule</h5></div>
    <div class="card-body p-0">
        <?php if (empty($weekly_shifts)): ?>
            <div class="empty-state"><p class="text-muted py-3">No shifts scheduled for this week.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Day</th><th>Date</th><th>Staff Member</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($weekly_shifts as $ws): ?>
                            <tr>
                                <td><?php echo $ws['day_name']; ?></td>
                                <td><?php echo formatDate($ws['shift_date']); ?></td>
                                <td><?php echo sanitize($ws['name']); ?></td>
                                <td><?php echo date('h:i A', strtotime($ws['shift_start'])); ?> - <?php echo date('h:i A', strtotime($ws['shift_end'])); ?></td>
                                <td><span class="badge bg-<?php echo ($ws['status'] === 'active') ? 'success' : (($ws['status'] === 'completed') ? 'primary' : 'info'); ?>"><?php echo ucfirst($ws['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Library Hours -->
<div class="card">
    <div class="card-header"><h5><i class="bi bi-building me-2"></i>Library Operating Hours</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-borderless">
                <tbody>
                    <tr><td style="width:40%"><strong>Monday - Friday</strong></td><td>8:00 AM - 8:00 PM</td></tr>
                    <tr><td><strong>Saturday</strong></td><td>9:00 AM - 5:00 PM</td></tr>
                    <tr><td><strong>Sunday</strong></td><td class="text-danger">Closed</td></tr>
                    <tr><td><strong>Public Holidays</strong></td><td class="text-muted">Closed</td></tr>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Note:</strong> Library staff availability may vary. Check the staff section above for real-time availability.
        </div>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
