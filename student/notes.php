<?php
$page_title = 'My Notes';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];

$note_types = [
    'personal'     => 'Personal',
    'book_note'    => 'Book Note',
    'book_review'  => 'Book Review',
    'summary'      => 'Summary',
    'study_note'   => 'Study Note',
];

$note_colors = [
    '#ffffff' => 'White',
    '#fff3cd' => 'Yellow',
    '#d1ecf1' => 'Blue',
    '#d4edda' => 'Green',
    '#f8d7da' => 'Red',
    '#e2d5f1' => 'Purple',
    '#ffe0cc' => 'Orange',
];

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(STUDENT_URL . '/notes.php');
    }

    // ── Add Note ───────────────────────────────────────────────────────────
    if ($action === 'add') {
        $note_title   = trim($_POST['note_title'] ?? '');
        $note_content = trim($_POST['note_content'] ?? '');
        $note_type    = sanitize($_POST['note_type'] ?? 'personal');
        $book_id      = !empty($_POST['book_id']) ? (int)$_POST['book_id'] : null;
        $color        = sanitize($_POST['color'] ?? '#ffffff');
        $is_pinned    = isset($_POST['is_pinned']) ? 1 : 0;

        if (!array_key_exists($note_type, $note_types)) {
            $note_type = 'personal';
        }
        if (!array_key_exists($color, $note_colors)) {
            $color = '#ffffff';
        }
        if (empty($note_title)) {
            setFlashMessage('danger', 'Note title is required.');
            redirect(STUDENT_URL . '/notes.php');
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_notes (user_id, book_id, note_title, note_content, note_type, color, is_pinned, is_archived, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())"
        );
        $null_book = $book_id === null ? null : $book_id;
        $stmt->bind_param('iissssi', $user_id, $null_book, $note_title, $note_content, $note_type, $color, $is_pinned);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Note created successfully.');
        redirect(STUDENT_URL . '/notes.php');
    }

    // ── Edit Note ──────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $note_id      = (int)($_POST['note_id'] ?? 0);
        $note_title   = trim($_POST['note_title'] ?? '');
        $note_content = trim($_POST['note_content'] ?? '');
        $note_type    = sanitize($_POST['note_type'] ?? 'personal');
        $book_id      = !empty($_POST['book_id']) ? (int)$_POST['book_id'] : null;
        $color        = sanitize($_POST['color'] ?? '#ffffff');
        $is_pinned    = isset($_POST['is_pinned']) ? 1 : 0;

        if (!array_key_exists($note_type, $note_types)) {
            $note_type = 'personal';
        }
        if (!array_key_exists($color, $note_colors)) {
            $color = '#ffffff';
        }
        if (empty($note_title)) {
            setFlashMessage('danger', 'Note title is required.');
            redirect(STUDENT_URL . '/notes.php');
        }

        $stmt = $conn->prepare(
            "UPDATE student_notes SET note_title=?, note_content=?, note_type=?, book_id=?, color=?, is_pinned=?, updated_at=NOW()
             WHERE note_id=? AND user_id=?"
        );
        $null_book = $book_id === null ? null : $book_id;
        $stmt->bind_param('ssssiiii', $note_title, $note_content, $note_type, $null_book, $color, $is_pinned, $note_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            setFlashMessage('success', 'Note updated successfully.');
        } else {
            setFlashMessage('danger', 'Note not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/notes.php');
    }

    // ── Delete Note ────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $note_id = (int)($_POST['note_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM student_notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Note deleted successfully.');
        } else {
            setFlashMessage('danger', 'Note not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/notes.php');
    }

    // ── Toggle Pin ─────────────────────────────────────────────────────────
    if ($action === 'toggle_pin') {
        $note_id = (int)($_POST['note_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE student_notes SET is_pinned = NOT is_pinned, updated_at=NOW() WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Note pin status updated.');
        } else {
            setFlashMessage('danger', 'Note not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/notes.php');
    }

    // ── Toggle Archive ─────────────────────────────────────────────────────
    if ($action === 'toggle_archive') {
        $note_id = (int)($_POST['note_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE student_notes SET is_archived = NOT is_archived, updated_at=NOW() WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $note_id, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Note archive status updated.');
        } else {
            setFlashMessage('danger', 'Note not found or access denied.');
        }
        $stmt->close();
        redirect(STUDENT_URL . '/notes.php');
    }
}

// ── Download Notes ──────────────────────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === 'text') {
    $dl_note_id = (int)($_GET['id'] ?? 0);
    if ($dl_note_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM student_notes WHERE note_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $dl_note_id, $user_id);
        $stmt->execute();
        $dl_note = $stmt->get_result()->fetch_assoc();
        if ($dl_note) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="note_' . preg_replace('/[^a-zA-Z0-9]/', '_', $dl_note['note_title']) . '.txt"');
            echo "Title: " . $dl_note['note_title'] . "\n";
            echo "Type: " . ($note_types[$dl_note['note_type']] ?? $dl_note['note_type']) . "\n";
            echo "Date: " . $dl_note['created_at'] . "\n";
            echo str_repeat("=", 50) . "\n\n";
            echo $dl_note['note_content'];
            exit();
        }
    }
}

// ── Fetch filters ────────────────────────────────────────────────────────────
$search        = sanitize($_GET['search'] ?? '');
$filter_type   = sanitize($_GET['note_type'] ?? '');
$show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';

// ── Fetch stats ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_notes WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$total_notes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_notes WHERE user_id = ? AND is_pinned = 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$pinned_notes = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_notes WHERE user_id = ? AND is_archived = 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$archived_notes = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_notes WHERE user_id = ? AND note_type IN ('book_note', 'book_review')");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$book_notes = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Fetch books for dropdown ─────────────────────────────────────────────────
$books = [];
$stmt = $conn->prepare("SELECT book_id, book_name FROM books ORDER BY book_name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();

// ── Fetch notes ──────────────────────────────────────────────────────────────
$where  = "WHERE n.user_id = ?";
$params = [$user_id];
$types  = 'i';

if ($search !== '') {
    $where .= " AND (n.note_title LIKE ? OR n.note_content LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($filter_type !== '' && array_key_exists($filter_type, $note_types)) {
    $where .= " AND n.note_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if (!$show_archived) {
    $where .= " AND n.is_archived = 0";
}

$sql = "SELECT n.*, b.book_name
        FROM student_notes n
        LEFT JOIN books b ON n.book_id = b.book_id
        {$where}
        ORDER BY n.is_pinned DESC, n.updated_at DESC";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4>My Notes</h4>
        <p class="text-muted mb-0">Create and manage your personal and book notes</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal" onclick="resetNoteForm()">
            <i class="bi bi-plus-circle"></i> Create Note
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-journal-text"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_notes; ?></h3>
            <p>Total Notes</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-pin-angle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pinned_notes; ?></h3>
            <p>Pinned Notes</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-archive"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $archived_notes; ?></h3>
            <p>Archived Notes</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="bi bi-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $book_notes; ?></h3>
            <p>Book Notes</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search notes..." value="<?php echo sanitize($search); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Note Type</label>
            <select name="note_type" class="form-select">
                <option value="">All Types</option>
                <?php foreach ($note_types as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filter_type === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <div class="form-check mt-4">
                <input type="checkbox" name="archived" value="1" class="form-check-input" id="showArchived" <?php echo $show_archived ? 'checked' : ''; ?>>
                <label class="form-check-label" for="showArchived">Show Archived</label>
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
        </div>
    </form>
</div>

<!-- Notes Grid -->
<?php if (empty($notes)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-journal-plus"></i>
                <h5>No Notes Found</h5>
                <p>Create your first note to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal" onclick="resetNoteForm()">
                    <i class="bi bi-plus-circle"></i> Create Note
                </button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($notes as $note): ?>
            <div class="col-12 mb-3">
                <div class="card" id="note-card-<?php echo $note['note_id']; ?>" style="border-left: 5px solid <?php echo sanitize($note['color']); ?>; transition: all 0.3s ease;">
                    <div class="card-body">
                        <!-- Note Header (always visible) -->
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1" style="cursor:pointer;" onclick="toggleNoteRead(<?php echo $note['note_id']; ?>)">
                                <h6 class="mb-1">
                                    <?php if ($note['is_pinned']): ?>
                                        <i class="bi bi-pin-fill text-warning"></i>
                                    <?php endif; ?>
                                    <?php echo sanitize($note['note_title']); ?>
                                    <i class="bi bi-chevron-down text-muted ms-1" id="chevron-<?php echo $note['note_id']; ?>"></i>
                                </h6>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-<?php echo ($note['note_type'] === 'book_note' || $note['note_type'] === 'book_review') ? 'info' : 'primary'; ?>" style="font-size:0.7rem;">
                                        <?php echo sanitize($note_types[$note['note_type']] ?? ucfirst($note['note_type'])); ?>
                                    </span>
                                    <?php if ($note['book_name']): ?>
                                        <small class="text-muted"><i class="bi bi-book"></i> <?php echo sanitize($note['book_name']); ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted"><i class="bi bi-clock"></i> <?php echo formatDate($note['created_at']); ?></small>
                                    <?php if ($note['is_archived']): ?>
                                        <span class="badge bg-secondary" style="font-size:0.65rem;">Archived</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted mb-0 mt-1" style="font-size:0.82rem;">
                                    <?php echo sanitize(mb_strimwidth($note['note_content'], 0, 150, '...')); ?>
                                </p>
                            </div>
                            <div class="action-btns ms-3">
                                <a href="<?php echo STUDENT_URL; ?>/notes.php?download=text&id=<?php echo $note['note_id']; ?>" class="btn btn-sm btn-outline-success" title="Download"><i class="bi bi-download"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                    onclick="editNote(<?php echo (int)$note['note_id']; ?>, <?php echo json_encode($note['note_title']); ?>, <?php echo json_encode($note['note_content']); ?>, '<?php echo $note['note_type']; ?>', <?php echo $note['book_id'] ? (int)$note['book_id'] : 'null'; ?>, '<?php echo $note['color']; ?>', <?php echo (int)$note['is_pinned']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this note?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="note_id" value="<?php echo (int)$note['note_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>

                        <!-- Expandable Document Content (inline, no popup) -->
                        <div id="note-content-<?php echo $note['note_id']; ?>" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid #e0e0e0;">
                            <div style="background:#fafafa; padding:20px; border-radius:8px; border:1px solid #e8e8e8;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="badge bg-primary"><?php echo sanitize($note_types[$note['note_type']] ?? ucfirst($note['note_type'])); ?></span>
                                        <?php if ($note['book_name']): ?>
                                            <span class="badge bg-info ms-1"><i class="bi bi-book me-1"></i><?php echo sanitize($note['book_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="<?php echo STUDENT_URL; ?>/notes.php?download=text&id=<?php echo $note['note_id']; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Download</a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Toggle pin?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="toggle_pin">
                                            <input type="hidden" name="note_id" value="<?php echo (int)$note['note_id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $note['is_pinned'] ? 'btn-warning' : 'btn-outline-warning'; ?>" title="<?php echo $note['is_pinned'] ? 'Unpin' : 'Pin'; ?>"><i class="bi bi-pin-<?php echo $note['is_pinned'] ? 'fill' : 'angle'; ?>"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Archive?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="toggle_archive">
                                            <input type="hidden" name="note_id" value="<?php echo (int)$note['note_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Archive"><i class="bi bi-archive"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <div style="white-space:pre-wrap; line-height:1.9; font-size:0.95rem; font-family:'Georgia', serif; color:#333; min-height:100px;"><?php echo sanitize($note['note_content']); ?></div>
                                <div class="text-end mt-3">
                                    <small class="text-muted">Created: <?php echo formatDateTime($note['note_created_at'] ?? $note['created_at']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add / Edit Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add" id="modalAction">
                <input type="hidden" name="note_id" value="0" id="modalNoteId">

                <div class="modal-header">
                    <h5 class="modal-title" id="noteModalLabel">Create Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="note_title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="note_title" name="note_title" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="note_content" class="form-label">Content</label>
                        <textarea class="form-control" id="note_content" name="note_content" rows="8"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="note_type" class="form-label">Note Type</label>
                            <select class="form-select" id="note_type" name="note_type">
                                <?php foreach ($note_types as $val => $label): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="book_id" class="form-label">Link to Book (optional)</label>
                            <select class="form-select" id="book_id" name="book_id">
                                <option value="">-- None --</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?php echo (int)$book['book_id']; ?>"><?php echo sanitize($book['book_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="color" class="form-label">Color</label>
                            <select class="form-select" id="color" name="color">
                                <?php foreach ($note_colors as $hex => $label): ?>
                                    <option value="<?php echo $hex; ?>" data-color="<?php echo $hex; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="colorPreview" class="mt-2" style="width:100%;height:24px;border-radius:4px;border:1px solid #dee2e6;background:#ffffff;"></div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_pinned" name="is_pinned" value="1">
                                <label class="form-check-label" for="is_pinned"><i class="bi bi-pin-angle"></i> Pin this note</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="bi bi-plus-circle"></i> Create Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetNoteForm() {
    document.getElementById('modalAction').value = 'add';
    document.getElementById('modalNoteId').value = '0';
    document.getElementById('noteModalLabel').textContent = 'Create Note';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-plus-circle"></i> Create Note';
    document.getElementById('note_title').value = '';
    document.getElementById('note_content').value = '';
    document.getElementById('note_type').value = 'personal';
    document.getElementById('book_id').value = '';
    document.getElementById('color').value = '#ffffff';
    document.getElementById('is_pinned').checked = false;
    document.getElementById('colorPreview').style.background = '#ffffff';
}

function editNote(noteId, title, content, noteType, bookId, color, isPinned) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalNoteId').value = noteId;
    document.getElementById('noteModalLabel').textContent = 'Edit Note';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
    document.getElementById('note_title').value = title;
    document.getElementById('note_content').value = content;
    document.getElementById('note_type').value = noteType;
    document.getElementById('book_id').value = bookId || '';
    document.getElementById('color').value = color;
    document.getElementById('is_pinned').checked = isPinned == 1;
    document.getElementById('colorPreview').style.background = color;

    var modal = new bootstrap.Modal(document.getElementById('noteModal'));
    modal.show();
}

document.getElementById('color').addEventListener('change', function() {
    document.getElementById('colorPreview').style.background = this.value;
});

function toggleNoteRead(noteId) {
    var content = document.getElementById('note-content-' + noteId);
    var chevron = document.getElementById('chevron-' + noteId);
    var card = document.getElementById('note-card-' + noteId);
    if (content.style.display === 'none') {
        content.style.display = 'block';
        chevron.className = 'bi bi-chevron-up text-muted ms-1';
        card.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
        card.style.background = '#fff';
    } else {
        content.style.display = 'none';
        chevron.className = 'bi bi-chevron-down text-muted ms-1';
        card.style.boxShadow = '';
        card.style.background = '';
    }
}
</script>

<?php include 'includes/student_footer.php'; ?>
