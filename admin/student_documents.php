<?php
$page_title = 'Student Documents & Notes';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Handle delete document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/student_documents.php');
    }

    $doc_id = intval($_POST['doc_id'] ?? 0);
    if ($doc_id > 0) {
        $stmt = $conn->prepare("SELECT file_path FROM student_documents WHERE doc_id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();

        if ($doc) {
            $conn->prepare("DELETE FROM student_documents WHERE doc_id = ?")->bind_param("i", $doc_id)->execute();
            if ($doc['file_path'] && file_exists(UPLOAD_DIR . $doc['file_path'])) {
                @unlink(UPLOAD_DIR . $doc['file_path']);
            }
            $auth->logActivity('admin', $_SESSION['user_id'], 'delete_document', "Deleted document #$doc_id");
            setFlashMessage('success', 'Document deleted successfully.');
        }
    }
    redirect(ADMIN_URL . '/student_documents.php?tab=documents');
}

// Handle delete note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_note') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/student_documents.php');
    }

    $note_id = intval($_POST['note_id'] ?? 0);
    if ($note_id > 0) {
        $stmt = $conn->prepare("DELETE FROM student_notes WHERE note_id = ?");
        $stmt->bind_param("i", $note_id);
        $stmt->execute();
        $auth->logActivity('admin', $_SESSION['user_id'], 'delete_note', "Deleted note #$note_id");
        setFlashMessage('success', 'Note deleted successfully.');
    }
    redirect(ADMIN_URL . '/student_documents.php?tab=notes');
}

// Active tab
$active_tab = $_GET['tab'] ?? 'documents';

// Filters
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$type_filter = trim($_GET['type'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Stats
$total_documents = $conn->query("SELECT COUNT(*) as c FROM student_documents")->fetch_assoc()['c'];
$total_notes = $conn->query("SELECT COUNT(*) as c FROM student_notes")->fetch_assoc()['c'];
$storage_result = $conn->query("SELECT COALESCE(SUM(file_size), 0) as total_size FROM student_documents")->fetch_assoc();
$total_storage = $storage_result['total_size'];

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Build document query
$doc_where = ["1=1"];
$doc_params = [];
$doc_types = "";

if ($search) {
    $like = "%{$search}%";
    $doc_where[] = "(u.name LIKE ? OR d.doc_title LIKE ?)";
    $doc_params[] = $like;
    $doc_params[] = $like;
    $doc_types .= "ss";
}
if ($category_filter) {
    $doc_where[] = "d.doc_category = ?";
    $doc_params[] = $category_filter;
    $doc_types .= "s";
}
if ($type_filter) {
    $doc_where[] = "d.file_type = ?";
    $doc_params[] = $type_filter;
    $doc_types .= "s";
}
if ($date_from) {
    $doc_where[] = "DATE(d.created_at) >= ?";
    $doc_params[] = $date_from;
    $doc_types .= "s";
}
if ($date_to) {
    $doc_where[] = "DATE(d.created_at) <= ?";
    $doc_params[] = $date_to;
    $doc_types .= "s";
}

$doc_where_sql = implode(" AND ", $doc_where);
$doc_sql = "SELECT d.*, u.name as student_name, u.student_id as student_code
    FROM student_documents d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE {$doc_where_sql}
    ORDER BY d.created_at DESC";

$doc_stmt = $conn->prepare($doc_sql);
if ($doc_params) {
    $doc_stmt->bind_param($doc_types, ...$doc_params);
}
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build notes query
$note_where = ["1=1"];
$note_params = [];
$note_types = "";

if ($search) {
    $like = "%{$search}%";
    $note_where[] = "(u.name LIKE ? OR n.note_title LIKE ?)";
    $note_params[] = $like;
    $note_params[] = $like;
    $note_types .= "ss";
}
if ($type_filter) {
    $note_where[] = "n.note_type = ?";
    $note_params[] = $type_filter;
    $note_types .= "s";
}
if ($date_from) {
    $note_where[] = "DATE(n.created_at) >= ?";
    $note_params[] = $date_from;
    $note_types .= "s";
}
if ($date_to) {
    $note_where[] = "DATE(n.created_at) <= ?";
    $note_params[] = $date_to;
    $note_types .= "s";
}

$note_where_sql = implode(" AND ", $note_where);
$note_sql = "SELECT n.*, u.name as student_name, u.student_id as student_code, b.book_name
    FROM student_notes n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN books b ON n.book_id = b.book_id
    WHERE {$note_where_sql}
    ORDER BY n.is_pinned DESC, n.created_at DESC";

$note_stmt = $conn->prepare($note_sql);
if ($note_params) {
    $note_stmt->bind_param($note_types, ...$note_params);
}
$note_stmt->execute();
$notes = $note_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct categories and file types for filter dropdowns
$categories = $conn->query("SELECT DISTINCT doc_category FROM student_documents WHERE doc_category IS NOT NULL AND doc_category != '' ORDER BY doc_category")->fetch_all(MYSQLI_ASSOC);
$file_types = $conn->query("SELECT DISTINCT file_type FROM student_documents WHERE file_type IS NOT NULL AND file_type != '' ORDER BY file_type")->fetch_all(MYSQLI_ASSOC);
$note_types_list = $conn->query("SELECT DISTINCT note_type FROM student_notes WHERE note_type IS NOT NULL AND note_type != '' ORDER BY note_type")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-file-earmark-text me-2"></i>Student Documents & Notes</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Documents & Notes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-file-earmark-arrow-up"></i></div>
        <div class="stat-info"><h3><?php echo $total_documents; ?></h3><p>Total Documents</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-journal-text"></i></div>
        <div class="stat-info"><h3><?php echo $total_notes; ?></h3><p>Total Notes</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-hdd"></i></div>
        <div class="stat-info"><h3><?php echo formatFileSize($total_storage); ?></h3><p>Storage Used</p></div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $active_tab === 'documents' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/student_documents.php?tab=documents">
            <i class="bi bi-file-earmark me-1"></i>Documents (<?php echo $total_documents; ?>)
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?php echo $active_tab === 'notes' ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/student_documents.php?tab=notes">
            <i class="bi bi-journal-text me-1"></i>Notes (<?php echo $total_notes; ?>)
        </a>
    </li>
</ul>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="tab" value="<?php echo sanitize($active_tab); ?>">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search student name or title..." value="<?php echo sanitize($search); ?>">
            </div>
            <?php if ($active_tab === 'documents'): ?>
            <div class="col-md-2">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo sanitize($c['doc_category']); ?>" <?php echo $category_filter === $c['doc_category'] ? 'selected' : ''; ?>><?php echo sanitize($c['doc_category']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <select class="form-select" name="type">
                    <option value="">All Types</option>
                    <?php if ($active_tab === 'documents'): ?>
                        <?php foreach ($file_types as $ft): ?>
                            <option value="<?php echo sanitize($ft['file_type']); ?>" <?php echo $type_filter === $ft['file_type'] ? 'selected' : ''; ?>><?php echo strtoupper(sanitize($ft['file_type'])); ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($note_types_list as $nt): ?>
                            <option value="<?php echo sanitize($nt['note_type']); ?>" <?php echo $type_filter === $nt['note_type'] ? 'selected' : ''; ?>><?php echo ucfirst(sanitize($nt['note_type'])); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo sanitize($date_from); ?>" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo sanitize($date_to); ?>" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-1">
                <a href="<?php echo ADMIN_URL; ?>/student_documents.php?tab=<?php echo $active_tab; ?>" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<?php if ($active_tab === 'documents'): ?>
<!-- Documents Tab -->
<div class="card">
    <div class="card-header">
        <h5>Student Documents (<?php echo count($documents); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h5>No Documents Found</h5>
                <p>No student documents match your criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>File Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($doc['student_name'] ?? 'Unknown'); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($doc['student_code'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php echo sanitize($doc['doc_title']); ?>
                                    <?php if ($doc['is_private']): ?>
                                        <span class="badge bg-secondary ms-1"><i class="bi bi-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge"><?php echo sanitize($doc['doc_category'] ?? 'General'); ?></span></td>
                                <td><?php echo strtoupper(sanitize($doc['file_type'] ?? 'N/A')); ?></td>
                                <td><?php echo formatFileSize($doc['file_size'] ?? 0); ?></td>
                                <td><?php echo formatDateTime($doc['created_at']); ?></td>
                                <td class="action-btns">
                                    <?php if ($doc['file_path']): ?>
                                        <a href="<?php echo SITE_URL; ?>/uploads/<?php echo sanitize($doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="<?php echo SITE_URL; ?>/uploads/<?php echo sanitize($doc['file_path']); ?>" download="<?php echo sanitize($doc['file_name']); ?>" class="btn btn-sm btn-outline-success" title="Download"><i class="bi bi-download"></i></a>
                                    <?php endif; ?>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this document? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_document">
                                        <input type="hidden" name="doc_id" value="<?php echo $doc['doc_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Notes Tab -->
<div class="card">
    <div class="card-header">
        <h5>Student Notes (<?php echo count($notes); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <i class="bi bi-journal-x"></i>
                <h5>No Notes Found</h5>
                <p>No student notes match your criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Book</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($note['student_name'] ?? 'Unknown'); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($note['student_code'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if ($note['is_pinned']): ?>
                                        <i class="bi bi-pin-fill text-warning me-1"></i>
                                    <?php endif; ?>
                                    <?php echo sanitize($note['note_title']); ?>
                                    <?php if ($note['is_archived']): ?>
                                        <span class="badge bg-secondary ms-1">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge"><?php echo ucfirst(sanitize($note['note_type'] ?? 'general')); ?></span></td>
                                <td><?php echo sanitize($note['book_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDateTime($note['created_at']); ?></td>
                                <td><?php echo formatDateTime($note['updated_at']); ?></td>
                                <td class="action-btns">
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewNote_<?php echo $note['note_id']; ?>" title="View"><i class="bi bi-eye"></i></button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this note? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_note">
                                        <input type="hidden" name="note_id" value="<?php echo $note['note_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <!-- View Note Modal -->
                            <div class="modal fade" id="viewNote_<?php echo $note['note_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header" <?php if ($note['color']): ?>style="background-color: <?php echo sanitize($note['color']); ?>;"<?php endif; ?>>
                                            <h5 class="modal-title"><?php echo sanitize($note['note_title']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <strong>Student:</strong> <?php echo sanitize($note['student_name'] ?? 'Unknown'); ?>
                                                    &middot; <strong>Type:</strong> <?php echo ucfirst(sanitize($note['note_type'] ?? 'general')); ?>
                                                    <?php if ($note['book_name']): ?>
                                                        &middot; <strong>Book:</strong> <?php echo sanitize($note['book_name']); ?>
                                                    <?php endif; ?>
                                                    &middot; <strong>Created:</strong> <?php echo formatDateTime($note['created_at']); ?>
                                                </small>
                                            </div>
                                            <hr>
                                            <div class="note-content" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"><?php echo sanitize($note['note_content']); ?></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this note? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete_note">
                                                <input type="hidden" name="note_id" value="<?php echo $note['note_id']; ?>">
                                                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete Note</button>
                                            </form>
                                        </div>
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
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
