<?php
$page_title = 'Notifications';
include __DIR__ . '/includes/admin_header.php';

$admin_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/notifications.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read' && !empty($_POST['notif_id'])) {
        $notif_id = (int)$_POST['notif_id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_type = 'admin' AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $admin_id);
        $stmt->execute();
        setFlashMessage('success', 'Notification marked as read.');
        redirect(ADMIN_URL . '/notifications.php');
    }

    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        setFlashMessage('success', 'All notifications marked as read.');
        redirect(ADMIN_URL . '/notifications.php');
    }

    if ($action === 'delete' && !empty($_POST['notif_id'])) {
        $notif_id = (int)$_POST['notif_id'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notif_id = ? AND user_type = 'admin' AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $admin_id);
        $stmt->execute();
        setFlashMessage('success', 'Notification deleted.');
        redirect(ADMIN_URL . '/notifications.php');
    }
}

// Pagination
$per_page = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_type = 'admin' AND user_id = ?");
$count_stmt->bind_param("i", $admin_id);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total / $per_page));

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'admin' AND user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $admin_id, $per_page, $offset);
$stmt->execute();
$notifs = $stmt->get_result();

// Unread count for badge
$unread_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_type = 'admin' AND user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $admin_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['cnt'];

// Helper: time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bell me-2"></i>Notifications</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Notifications</li>
            </ol>
        </nav>
    </div>
    <?php if ($unread_count > 0): ?>
    <form method="POST" action="" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check-all me-1"></i>Mark All as Read (<?php echo $unread_count; ?>)
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if ($notifs->num_rows === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash display-4 text-muted"></i>
                <p class="text-muted mt-2">No notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php while ($n = $notifs->fetch_assoc()): ?>
                    <div class="list-group-item list-group-item-action d-flex align-items-start <?php echo $n['is_read'] ? '' : 'bg-light border-start border-primary border-4'; ?>">
                        <div class="flex-shrink-0 me-3">
                            <?php if (!$n['is_read']): ?>
                                <span class="badge bg-primary rounded-pill">&nbsp;</span>
                            <?php else: ?>
                                <span class="text-muted"><i class="bi bi-check2-circle"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <?php if ($n['link']): ?>
                                <a href="<?php echo $n['link']; ?>" class="text-decoration-none">
                                    <h6 class="mb-1 <?php echo $n['is_read'] ? 'text-muted' : ''; ?>"><?php echo sanitize($n['title']); ?></h6>
                                </a>
                            <?php else: ?>
                                <h6 class="mb-1 <?php echo $n['is_read'] ? 'text-muted' : ''; ?>"><?php echo sanitize($n['title']); ?></h6>
                            <?php endif; ?>
                            <p class="mb-1 <?php echo $n['is_read'] ? 'text-muted' : ''; ?>"><?php echo sanitize($n['message']); ?></p>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo timeAgo($n['created_at']); ?></small>
                        </div>
                        <div class="flex-shrink-0 ms-2 d-flex gap-1">
                            <?php if (!$n['is_read']): ?>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notif_id" value="<?php echo $n['notif_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as read">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this notification?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notif_id" value="<?php echo $n['notif_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
