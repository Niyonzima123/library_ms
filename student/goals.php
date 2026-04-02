<?php
$page_title = 'My Goals & Plans';
include 'includes/student_header.php';
$user_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Verify user exists in database
$user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
if ($user_check->get_result()->num_rows === 0) {
    setFlashMessage('danger', 'Your account was not found. Please log in again.');
    redirect(SITE_URL . '/includes/logout.php');
}

$goal_types = [
    'academic'   => 'Academic',
    'reading'    => 'Reading',
    'skill'      => 'Skill',
    'career'     => 'Career',
    'personal'   => 'Personal',
];

$goal_priorities = [
    'low'      => 'Low',
    'medium'   => 'Medium',
    'high'     => 'High',
    'critical' => 'Critical',
];

$priority_badges = [
    'low'      => 'bg-secondary',
    'medium'   => 'bg-info',
    'high'     => 'bg-warning',
    'critical' => 'bg-danger',
];

$event_types = [
    'deadline'   => 'Deadline',
    'exam'       => 'Exam',
    'assignment' => 'Assignment',
    'reading'    => 'Reading',
    'meeting'    => 'Meeting',
    'other'      => 'Other',
];

$event_icons = [
    'deadline'   => 'bi-calendar-x',
    'exam'       => 'bi-pencil-square',
    'assignment' => 'bi-clipboard-check',
    'reading'    => 'bi-book',
    'meeting'    => 'bi-people',
    'other'      => 'bi-calendar-event',
];

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
            redirect(STUDENT_URL . '/goals.php');
        }

    // ── Add Goal ───────────────────────────────────────────────────────────
    if ($action === 'add_goal') {
        $goal_title       = trim($_POST['goal_title'] ?? '');
        $goal_description = trim($_POST['goal_description'] ?? '');
        $goal_type        = sanitize($_POST['goal_type'] ?? 'academic');
        $priority         = sanitize($_POST['priority'] ?? 'medium');
        $target_date      = sanitize($_POST['target_date'] ?? '');

        if (empty($goal_title)) {
            setFlashMessage('danger', 'Goal title is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (!array_key_exists($goal_type, $goal_types)) {
            $goal_type = 'academic';
        }
        if (!array_key_exists($priority, $goal_priorities)) {
            $priority = 'medium';
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_goals (user_id, goal_title, goal_description, goal_type, priority, target_date, progress, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 'active', NOW(), NOW())"
        );
        $stmt->bind_param('isssss', $user_id, $goal_title, $goal_description, $goal_type, $priority, $target_date);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Goal added successfully.');
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Edit Goal ──────────────────────────────────────────────────────────
    if ($action === 'edit_goal') {
        $goal_id          = (int)($_POST['goal_id'] ?? 0);
        $goal_title       = trim($_POST['goal_title'] ?? '');
        $goal_description = trim($_POST['goal_description'] ?? '');
        $goal_type        = sanitize($_POST['goal_type'] ?? 'academic');
        $priority         = sanitize($_POST['priority'] ?? 'medium');
        $target_date      = sanitize($_POST['target_date'] ?? '');

        if (empty($goal_title)) {
            setFlashMessage('danger', 'Goal title is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (!array_key_exists($goal_type, $goal_types)) {
            $goal_type = 'academic';
        }
        if (!array_key_exists($priority, $goal_priorities)) {
            $priority = 'medium';
        }

        $stmt = $conn->prepare(
            "UPDATE student_goals SET goal_title=?, goal_description=?, goal_type=?, priority=?, target_date=?, updated_at=NOW()
             WHERE goal_id=? AND user_id=?"
        );
        $stmt->bind_param('sssssii', $goal_title, $goal_description, $goal_type, $priority, $target_date, $goal_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            setFlashMessage('success', 'Goal updated successfully.');
        } else {
            setFlashMessage('danger', 'Goal not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Delete Goal ────────────────────────────────────────────────────────
    if ($action === 'delete_goal') {
        $goal_id = (int)($_POST['goal_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM student_goals WHERE goal_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $goal_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Goal deleted successfully.');
        } else {
            setFlashMessage('danger', 'Goal not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Update Progress ────────────────────────────────────────────────────
    if ($action === 'update_progress') {
        $goal_id  = (int)($_POST['goal_id'] ?? 0);
        $progress = min(100, max(0, (int)($_POST['progress'] ?? 0)));

        $stmt = $conn->prepare(
            "UPDATE student_goals SET progress=?, updated_at=NOW() WHERE goal_id=? AND user_id=?"
        );
        $stmt->bind_param('iii', $progress, $goal_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Progress updated successfully.');
        } else {
            setFlashMessage('danger', 'Goal not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Complete Goal ──────────────────────────────────────────────────────
    if ($action === 'complete_goal') {
        $goal_id = (int)($_POST['goal_id'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE student_goals SET status='completed', progress=100, updated_at=NOW() WHERE goal_id=? AND user_id=?"
        );
        $stmt->bind_param('ii', $goal_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Goal marked as completed!');
        } else {
            setFlashMessage('danger', 'Goal not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Add Event ──────────────────────────────────────────────────────────
    if ($action === 'add_event') {
        $event_title       = trim($_POST['event_title'] ?? '');
        $event_description = trim($_POST['event_description'] ?? '');
        $event_type        = sanitize($_POST['event_type'] ?? 'other');
        $event_date        = sanitize($_POST['event_date'] ?? '');
        $event_time        = sanitize($_POST['event_time'] ?? '');

        if (empty($event_title)) {
            setFlashMessage('danger', 'Event title is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (empty($event_date)) {
            setFlashMessage('danger', 'Event date is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (!array_key_exists($event_type, $event_types)) {
            $event_type = 'other';
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_events (user_id, event_title, event_description, event_type, event_date, event_time, is_completed, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())"
        );
        $stmt->bind_param('isssss', $user_id, $event_title, $event_description, $event_type, $event_date, $event_time);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Event added successfully.');
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Edit Event ─────────────────────────────────────────────────────────
    if ($action === 'edit_event') {
        $event_id         = (int)($_POST['event_id'] ?? 0);
        $event_title      = trim($_POST['event_title'] ?? '');
        $event_description = trim($_POST['event_description'] ?? '');
        $event_type       = sanitize($_POST['event_type'] ?? 'other');
        $event_date       = sanitize($_POST['event_date'] ?? '');
        $event_time       = sanitize($_POST['event_time'] ?? '');

        if (empty($event_title)) {
            setFlashMessage('danger', 'Event title is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (empty($event_date)) {
            setFlashMessage('danger', 'Event date is required.');
            redirect(STUDENT_URL . '/goals.php');
        }
        if (!array_key_exists($event_type, $event_types)) {
            $event_type = 'other';
        }

        $stmt = $conn->prepare(
            "UPDATE student_events SET event_title=?, event_description=?, event_type=?, event_date=?, event_time=?, updated_at=NOW()
             WHERE event_id=? AND user_id=?"
        );
        $stmt->bind_param('sssssii', $event_title, $event_description, $event_type, $event_date, $event_time, $event_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            setFlashMessage('success', 'Event updated successfully.');
        } else {
            setFlashMessage('danger', 'Event not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Delete Event ───────────────────────────────────────────────────────
    if ($action === 'delete_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM student_events WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $event_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Event deleted successfully.');
        } else {
            setFlashMessage('danger', 'Event not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }

    // ── Complete Event ─────────────────────────────────────────────────────
    if ($action === 'complete_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE student_events SET is_completed=1, updated_at=NOW() WHERE event_id=? AND user_id=?"
        );
        $stmt->bind_param('ii', $event_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Event marked as completed!');
        } else {
            setFlashMessage('danger', 'Event not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/goals.php');
    }
    } catch (mysqli_sql_exception $e) {
        error_log('Goals error: ' . $e->getMessage());
        setFlashMessage('danger', 'A database error occurred. Please try again.');
        redirect(STUDENT_URL . '/goals.php');
    }
}

// ── Stats Queries ────────────────────────────────────────────────────────────

// Active goals count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_goals WHERE user_id = ? AND status = 'active'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$active_goals = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Completed goals count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_goals WHERE user_id = ? AND status = 'completed'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$completed_goals = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Upcoming events (next 7 days)
$stmt = $conn->prepare(
    "SELECT COUNT(*) as cnt FROM student_events
     WHERE user_id = ? AND is_completed = 0 AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$upcoming_events = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Overdue events (past date, not completed)
$stmt = $conn->prepare(
    "SELECT COUNT(*) as cnt FROM student_events
     WHERE user_id = ? AND is_completed = 0 AND event_date < CURDATE()"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$overdue_events = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Fetch Goals ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM student_goals WHERE user_id = ? ORDER BY
     FIELD(status, 'active', 'completed'),
     FIELD(priority, 'critical', 'high', 'medium', 'low'),
     target_date ASC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch Upcoming Events ───────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM student_events
     WHERE user_id = ? AND event_date >= CURDATE()
     ORDER BY event_date ASC, event_time ASC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch Past/Overdue Events ───────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM student_events
     WHERE user_id = ? AND event_date < CURDATE() AND is_completed = 0
     ORDER BY event_date DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$overdue_events_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group events by date
$events_by_date = [];
foreach ($events as $ev) {
    $events_by_date[$ev['event_date']][] = $ev;
}
$overdue_by_date = [];
foreach ($overdue_events_list as $ev) {
    $overdue_by_date[$ev['event_date']][] = $ev;
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4>My Goals & Plans</h4>
        <p class="text-muted mb-0">Track goals, plan events, and manage your time</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#goalModal" onclick="resetGoalForm()">
            <i class="bi bi-plus-circle"></i> Add Goal
        </button>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetEventForm()">
            <i class="bi bi-calendar-plus"></i> Add Event
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-bullseye"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $active_goals; ?></h3>
            <p>Active Goals</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $completed_goals; ?></h3>
            <p>Completed Goals</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-calendar-event"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $upcoming_events; ?></h3>
            <p>Upcoming (7 days)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overdue_events; ?></h3>
            <p>Overdue Events</p>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Goals -->
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-bullseye"></i> My Goals (<?php echo count($goals); ?>)</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#goalModal" onclick="resetGoalForm()">
                    <i class="bi bi-plus"></i> Add
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($goals)): ?>
                    <div class="empty-state">
                        <i class="bi bi-bullseye"></i>
                        <h5>No Goals Yet</h5>
                        <p>Set your first goal to start tracking your progress.</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#goalModal" onclick="resetGoalForm()">
                            <i class="bi bi-plus-circle"></i> Add Goal
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($goals as $goal): ?>
                        <div class="card mb-3 <?php echo $goal['status'] === 'completed' ? 'border-success' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php echo sanitize($goal['goal_title']); ?>
                                            <span class="badge <?php echo $priority_badges[$goal['priority']] ?? 'bg-secondary'; ?> ms-1" style="font-size:0.7rem;">
                                                <?php echo ucfirst($goal['priority']); ?>
                                            </span>
                                            <span class="badge bg-primary ms-1" style="font-size:0.7rem;">
                                                <?php echo sanitize($goal_types[$goal['goal_type']] ?? ucfirst($goal['goal_type'])); ?>
                                            </span>
                                            <?php if ($goal['status'] === 'completed'): ?>
                                                <span class="badge bg-success ms-1" style="font-size:0.7rem;">
                                                    <i class="bi bi-check"></i> Completed
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                    </div>
                                </div>

                                <?php if (!empty($goal['goal_description'])): ?>
                                    <div class="mb-2">
                                        <p class="text-muted mb-1" style="font-size:0.85rem; cursor:pointer;" onclick="toggleGoalDetail(<?php echo $goal['goal_id']; ?>)">
                                            <i class="bi bi-chevron-down text-primary" id="goal-chevron-<?php echo $goal['goal_id']; ?>" style="font-size:0.7rem;"></i>
                                            <?php echo sanitize(mb_strimwidth($goal['goal_description'], 0, 150, '...')); ?>
                                        </p>
                                        <div id="goal-detail-<?php echo $goal['goal_id']; ?>" style="display:none; background:#f8f9fa; padding:12px; border-radius:6px; border-left:3px solid #0d6efd;">
                                            <div style="white-space:pre-wrap; line-height:1.7; font-size:0.9rem; font-family:Georgia,serif;"><?php echo sanitize($goal['goal_description']); ?></div>
                                            <?php if (!empty($goal['target_date'])): ?>
                                                <div class="mt-2 text-muted small">
                                                    <i class="bi bi-calendar"></i> Target Date: <?php echo formatDate($goal['goal_date'] ?? $goal['target_date']); ?>
                                                    | <i class="bi bi-clock"></i> Created: <?php echo formatDate($goal['created_at']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($goal['target_date'])): ?>
                                    <div class="mb-2">
                                        <small class="<?php echo ($goal['status'] === 'active' && strtotime($goal['target_date']) < strtotime('today')) ? 'text-danger' : 'text-muted'; ?>">
                                            <i class="bi bi-calendar"></i> Target: <?php echo formatDate($goal['target_date']); ?>
                                            <?php if ($goal['status'] === 'active' && strtotime($goal['target_date']) < strtotime('today')): ?>
                                                (Overdue)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <?php if ($goal['status'] === 'active'): ?>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar <?php
                                                if ($goal['progress'] >= 75) echo 'bg-success';
                                                elseif ($goal['progress'] >= 50) echo 'bg-info';
                                                elseif ($goal['progress'] >= 25) echo 'bg-warning';
                                                else echo 'bg-danger';
                                            ?>" style="width: <?php echo (int)$goal['progress']; ?>%;"></div>
                                        </div>
                                        <small class="text-muted" style="min-width:35px;"><?php echo (int)$goal['progress']; ?>%</small>
                                    </div>
                                <?php endif; ?>

                                <div class="action-btns border-top pt-2">
                                    <?php if ($goal['status'] === 'active'): ?>
                                        <!-- Update Progress -->
                                        <form method="POST" class="d-inline" onsubmit="return promptProgress(this);">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="update_progress">
                                            <input type="hidden" name="goal_id" value="<?php echo (int)$goal['goal_id']; ?>">
                                            <input type="hidden" name="progress" value="0" class="progress-input">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Update Progress">
                                                <i class="bi bi-graph-up"></i>
                                            </button>
                                        </form>
                                        <!-- Complete -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Mark this goal as completed?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="complete_goal">
                                            <input type="hidden" name="goal_id" value="<?php echo (int)$goal['goal_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Complete">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Edit -->
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                        onclick="editGoal(
                                            <?php echo (int)$goal['goal_id']; ?>,
                                            <?php echo json_encode($goal['goal_title']); ?>,
                                            <?php echo json_encode($goal['goal_description']); ?>,
                                            '<?php echo $goal['goal_type']; ?>',
                                            '<?php echo $goal['priority']; ?>',
                                            '<?php echo $goal['target_date']; ?>'
                                        )">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Delete -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this goal?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_goal">
                                        <input type="hidden" name="goal_id" value="<?php echo (int)$goal['goal_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Calendar Events -->
    <div class="col-lg-5 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar-event"></i> Upcoming Events</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetEventForm()">
                    <i class="bi bi-plus"></i> Add
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <h5>No Upcoming Events</h5>
                        <p>Add events to plan your schedule.</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetEventForm()">
                            <i class="bi bi-calendar-plus"></i> Add Event
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($events_by_date as $date => $day_events): ?>
                        <div class="px-3 pt-3">
                            <h6 class="text-primary mb-2">
                                <i class="bi bi-calendar3"></i> <?php echo formatDate($date); ?>
                                <?php if ($date === date('Y-m-d')): ?>
                                    <span class="badge bg-success" style="font-size:0.65rem;">Today</span>
                                <?php elseif ($date === date('Y-m-d', strtotime('+1 day'))): ?>
                                    <span class="badge bg-info" style="font-size:0.65rem;">Tomorrow</span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($day_events as $ev): ?>
                                <div class="list-group-item <?php echo $ev['is_completed'] ? 'bg-light' : ''; ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="mt-1">
                                            <i class="bi <?php echo $event_icons[$ev['event_type']] ?? 'bi-calendar-event'; ?> text-primary" style="font-size:1.1rem;"></i>
                                        </div>
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <strong style="font-size:0.9rem;" class="<?php echo $ev['is_completed'] ? 'text-decoration-line-through text-muted' : ''; ?>">
                                                    <?php echo sanitize($ev['event_title']); ?>
                                                </strong>
                                                <span class="badge bg-light text-dark ms-2" style="font-size:0.65rem;">
                                                    <?php echo sanitize($event_types[$ev['event_type']] ?? ucfirst($ev['event_type'])); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($ev['event_time'])): ?>
                                                <small class="text-muted"><i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($ev['event_time'])); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($ev['event_description'])): ?>
                                                <p class="mb-1 text-muted" style="font-size:0.82rem;">
                                                    <?php echo sanitize(mb_strimwidth($ev['event_description'], 0, 80, '...')); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="action-btns">
                                                <button type="button" class="btn btn-sm btn-outline-info" title="View Details" data-bs-toggle="modal" data-bs-target="#viewEvent_<?php echo $ev['event_id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (!$ev['is_completed']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Mark this event as completed?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="complete_event">
                                                        <input type="hidden" name="event_id" value="<?php echo (int)$ev['event_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Complete">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                                    onclick="editEvent(
                                                        <?php echo (int)$ev['event_id']; ?>,
                                                        <?php echo json_encode($ev['event_title']); ?>,
                                                        <?php echo json_encode($ev['event_description']); ?>,
                                                        '<?php echo $ev['event_type']; ?>',
                                                        '<?php echo $ev['event_date']; ?>',
                                                        '<?php echo $ev['event_time']; ?>'
                                                    )">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?php echo (int)$ev['event_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <!-- View Event Modal -->
                                            <div class="modal fade" id="viewEvent_<?php echo $ev['event_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Event Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <h5><?php echo sanitize($ev['event_title']); ?></h5>
                                                            <div class="d-flex gap-2 mb-3">
                                                                <span class="badge bg-<?php echo $ev['is_completed'] ? 'success' : 'primary'; ?>"><?php echo $ev['is_completed'] ? 'Completed' : 'Pending'; ?></span>
                                                                <span class="badge bg-info"><?php echo sanitize($event_types[$ev['event_type']] ?? ucfirst($ev['event_type'])); ?></span>
                                                            </div>
                                                            <table class="table table-borderless table-sm">
                                                                <tr><th style="width:35%">Date:</th><td><?php echo formatDate($ev['event_date']); ?></td></tr>
                                                                <?php if (!empty($ev['event_time'])): ?>
                                                                <tr><th>Time:</th><td><?php echo date('h:i A', strtotime($ev['event_time'])); ?></td></tr>
                                                                <?php endif; ?>
                                                            </table>
                                                            <?php if (!empty($ev['event_description'])): ?>
                                                                <h6>Description</h6>
                                                                <p style="white-space:pre-wrap;"><?php echo sanitize($ev['event_description']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($overdue_events_list)): ?>
        <div class="card">
            <div class="card-header bg-danger-subtle">
                <h5 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Overdue Events</h5>
            </div>
            <div class="card-body p-0">
                <?php foreach ($overdue_by_date as $date => $day_events): ?>
                    <div class="px-3 pt-2">
                        <small class="text-danger fw-bold"><i class="bi bi-calendar-x"></i> <?php echo formatDate($date); ?></small>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($day_events as $ev): ?>
                            <div class="list-group-item list-group-item-danger-subtle">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="mt-1">
                                        <i class="bi <?php echo $event_icons[$ev['event_type']] ?? 'bi-calendar-event'; ?> text-danger" style="font-size:1rem;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong style="font-size:0.88rem;"><?php echo sanitize($ev['event_title']); ?></strong>
                                        <span class="badge bg-danger ms-1" style="font-size:0.6rem;">
                                            <?php echo sanitize($event_types[$ev['event_type']] ?? ucfirst($ev['event_type'])); ?>
                                        </span>
                                        <div class="action-btns mt-1">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this overdue event as completed?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="complete_event">
                                                <input type="hidden" name="event_id" value="<?php echo (int)$ev['event_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Complete">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this event?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete_event">
                                                <input type="hidden" name="event_id" value="<?php echo (int)$ev['event_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add / Edit Goal Modal -->
<div class="modal fade" id="goalModal" tabindex="-1" aria-labelledby="goalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_goal" id="goalModalAction">
                <input type="hidden" name="goal_id" value="0" id="goalModalId">

                <div class="modal-header">
                    <h5 class="modal-title" id="goalModalLabel">Add Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="goal_title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="goal_title" name="goal_title" required maxlength="255" placeholder="e.g. Read 5 books this semester">
                    </div>
                    <div class="mb-3">
                        <label for="goal_description" class="form-label">Description</label>
                        <textarea class="form-control" id="goal_description" name="goal_description" rows="3" placeholder="Describe your goal in detail..."></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="goal_type" class="form-label">Type</label>
                            <select class="form-select" id="goal_type" name="goal_type">
                                <?php foreach ($goal_types as $val => $label): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <?php foreach ($goal_priorities as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $val === 'medium' ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="target_date" class="form-label">Target Date</label>
                        <input type="date" class="form-control" id="target_date" name="target_date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="goalSubmitBtn">
                        <i class="bi bi-plus-circle"></i> Add Goal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add / Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_event" id="eventModalAction">
                <input type="hidden" name="event_id" value="0" id="eventModalId">

                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Add Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="event_title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="event_title" name="event_title" required maxlength="255" placeholder="e.g. Database Exam">
                    </div>
                    <div class="mb-3">
                        <label for="event_description" class="form-label">Description</label>
                        <textarea class="form-control" id="event_description" name="event_description" rows="3" placeholder="Event details..."></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="event_type" class="form-label">Type</label>
                            <select class="form-select" id="event_type" name="event_type">
                                <?php foreach ($event_types as $val => $label): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="event_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="event_time" class="form-label">Time</label>
                        <input type="time" class="form-control" id="event_time" name="event_time">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="eventSubmitBtn">
                        <i class="bi bi-calendar-plus"></i> Add Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetGoalForm() {
    document.getElementById('goalModalAction').value = 'add_goal';
    document.getElementById('goalModalId').value = '0';
    document.getElementById('goalModalLabel').textContent = 'Add Goal';
    document.getElementById('goalSubmitBtn').innerHTML = '<i class="bi bi-plus-circle"></i> Add Goal';
    document.getElementById('goal_title').value = '';
    document.getElementById('goal_description').value = '';
    document.getElementById('goal_type').value = 'academic';
    document.getElementById('priority').value = 'medium';
    document.getElementById('target_date').value = '';
}

function editGoal(goalId, title, description, goalType, priority, targetDate) {
    document.getElementById('goalModalAction').value = 'edit_goal';
    document.getElementById('goalModalId').value = goalId;
    document.getElementById('goalModalLabel').textContent = 'Edit Goal';
    document.getElementById('goalSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
    document.getElementById('goal_title').value = title;
    document.getElementById('goal_description').value = description;
    document.getElementById('goal_type').value = goalType;
    document.getElementById('priority').value = priority;
    document.getElementById('target_date').value = targetDate;

    var modal = new bootstrap.Modal(document.getElementById('goalModal'));
    modal.show();
}

function resetEventForm() {
    document.getElementById('eventModalAction').value = 'add_event';
    document.getElementById('eventModalId').value = '0';
    document.getElementById('eventModalLabel').textContent = 'Add Event';
    document.getElementById('eventSubmitBtn').innerHTML = '<i class="bi bi-calendar-plus"></i> Add Event';
    document.getElementById('event_title').value = '';
    document.getElementById('event_description').value = '';
    document.getElementById('event_type').value = 'other';
    document.getElementById('event_date').value = '';
    document.getElementById('event_time').value = '';
}

function editEvent(eventId, title, description, eventType, eventDate, eventTime) {
    document.getElementById('eventModalAction').value = 'edit_event';
    document.getElementById('eventModalId').value = eventId;
    document.getElementById('eventModalLabel').textContent = 'Edit Event';
    document.getElementById('eventSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
    document.getElementById('event_title').value = title;
    document.getElementById('event_description').value = description;
    document.getElementById('event_type').value = eventType;
    document.getElementById('event_date').value = eventDate;
    document.getElementById('event_time').value = eventTime;

    var modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
}

function promptProgress(form) {
    var current = prompt('Enter progress percentage (0-100):', '50');
    if (current === null) return false;
    var val = parseInt(current, 10);
    if (isNaN(val) || val < 0 || val > 100) {
        alert('Please enter a number between 0 and 100.');
        return false;
    }
    form.querySelector('.progress-input').value = val;
    return true;
}

function toggleGoalDetail(goalId) {
    var detail = document.getElementById('goal-detail-' + goalId);
    var chevron = document.getElementById('goal-chevron-' + goalId);
    if (detail.style.display === 'none') {
        detail.style.display = 'block';
        chevron.className = 'bi bi-chevron-up text-primary';
    } else {
        detail.style.display = 'none';
        chevron.className = 'bi bi-chevron-down text-primary';
    }
}
</script>

<?php include 'includes/student_footer.php'; ?>

