<?php
require_once 'includes/student_header.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Ensure uploads/documents directory exists
$upload_dir = UPLOAD_DIR . 'documents/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        // ── Upload / Edit document ───────────────────────────────────────────
        if ($action === 'upload' || $action === 'edit') {
            $doc_title       = sanitize($_POST['doc_title'] ?? '');
            $doc_description = sanitize($_POST['doc_description'] ?? '');
            $doc_category    = sanitize($_POST['doc_category'] ?? 'other');
            $book_id         = !empty($_POST['book_id']) ? (int)$_POST['book_id'] : null;
            $is_private      = isset($_POST['is_private']) ? 1 : 0;
            $doc_id          = $action === 'edit' ? (int)($_POST['doc_id'] ?? 0) : 0;

            $valid_categories = ['note', 'document', 'book_review', 'summary', 'other'];
            if (!in_array($doc_category, $valid_categories)) {
                $doc_category = 'other';
            }

            if (empty($doc_title)) {
                $errors[] = 'Document title is required.';
            }

            // Validate existing doc ownership on edit
            if ($action === 'edit') {
                $stmt = $conn->prepare("SELECT file_name, file_path FROM student_documents WHERE doc_id = ? AND user_id = ?");
                $stmt->bind_param('ii', $doc_id, $user_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$existing) {
                    $errors[] = 'Document not found or access denied.';
                }
            }

            // File handling
            $file_name   = '';
            $file_path   = '';
            $file_type   = '';
            $file_size   = 0;
            $has_new_file = isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK;

            if ($has_new_file) {
                $file_name   = basename($_FILES['document']['name']);
                $file_type   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $file_size   = $_FILES['document']['size'];
                $allowed_ext = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];

                if (!in_array($file_type, $allowed_ext)) {
                    $errors[] = 'File type not allowed. Allowed: ' . implode(', ', $allowed_ext);
                }

                if ($file_size > MAX_FILE_SIZE) {
                    $errors[] = 'File size exceeds the maximum allowed limit.';
                }
            } elseif ($action === 'upload') {
                $errors[] = 'Please select a file to upload.';
            }

            if (empty($errors)) {
                if ($has_new_file) {
                    $unique_name = uniqid($user_id . '_', true) . '.' . $file_type;
                    $destination = $upload_dir . $unique_name;

                    if (!move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
                        $errors[] = 'Failed to save the uploaded file.';
                    } else {
                        $file_path = $destination;
                    }
                }

                if (empty($errors)) {
                    if ($action === 'upload') {
                        $stmt = $conn->prepare(
                            "INSERT INTO student_documents (user_id, doc_title, doc_description, file_name, file_path, file_type, file_size, doc_category, book_id, is_private, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                        );
                        $stmt->bind_param('issssissii', $user_id, $doc_title, $doc_description, $file_name, $file_path, $file_type, $file_size, $doc_category, $book_id, $is_private);
                        $stmt->execute();
                        $stmt->close();
                        setFlashMessage('success', 'Document uploaded successfully.');
                    } else {
                        // Delete old file if new one uploaded
                        if ($has_new_file && !empty($existing['file_path']) && file_exists($existing['file_path'])) {
                            unlink($existing['file_path']);
                        }

                        if ($has_new_file) {
                            $stmt = $conn->prepare(
                                "UPDATE student_documents SET doc_title=?, doc_description=?, file_name=?, file_path=?, file_type=?, file_size=?, doc_category=?, book_id=?, is_private=?, updated_at=NOW() WHERE doc_id=? AND user_id=?"
                            );
                            $stmt->bind_param('ssssisiiiii', $doc_title, $doc_description, $file_name, $file_path, $file_type, $file_size, $doc_category, $book_id, $is_private, $doc_id, $user_id);
                        } else {
                            $stmt = $conn->prepare(
                                "UPDATE student_documents SET doc_title=?, doc_description=?, doc_category=?, book_id=?, is_private=?, updated_at=NOW() WHERE doc_id=? AND user_id=?"
                            );
                            $stmt->bind_param('sssiiii', $doc_title, $doc_description, $doc_category, $book_id, $is_private, $doc_id, $user_id);
                        }
                        $stmt->execute();
                        $stmt->close();
                        setFlashMessage('success', 'Document updated successfully.');
                    }

                    redirect(STUDENT_URL . '/documents.php');
                }
            }
        }

        // ── Delete document ──────────────────────────────────────────────────
        if ($action === 'delete') {
            $doc_id = (int)($_POST['doc_id'] ?? 0);

            $stmt = $conn->prepare("SELECT file_path FROM student_documents WHERE doc_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $doc_id, $user_id);
            $stmt->execute();
            $doc = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($doc) {
                if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
                    unlink($doc['file_path']);
                }
                $stmt = $conn->prepare("DELETE FROM student_documents WHERE doc_id = ? AND user_id = ?");
                $stmt->bind_param('ii', $doc_id, $user_id);
                $stmt->execute();
                $stmt->close();
                setFlashMessage('success', 'Document deleted successfully.');
            } else {
                setFlashMessage('error', 'Document not found or access denied.');
            }

            redirect(STUDENT_URL . '/documents.php');
        }
    }
}

// ── Handle View document ────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'view') {
    $doc_id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT file_name, file_path, file_type FROM student_documents WHERE doc_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $doc_id, $user_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    if ($doc && file_exists($doc['file_path'])) {
        $mime_types = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/jpeg', 'gif' => 'image/gif',
            'txt' => 'text/plain',
        ];
        $ext = strtolower($doc['file_type']);
        $mime = $mime_types[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $doc['file_name'] . '"');
        readfile($doc['file_path']);
        exit();
    } else {
        setFlashMessage('danger', 'File not found.');
        redirect(STUDENT_URL . '/documents.php');
    }
}

// ── Handle Download ──────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $doc_id = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT file_name, file_path, file_type FROM student_documents WHERE doc_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $doc_id, $user_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($doc && file_exists($doc['file_path'])) {
        $mime_types = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt'  => 'text/plain',
            'rtf'  => 'application/rtf',
            'zip'  => 'application/zip',
            'rar'  => 'application/x-rar-compressed',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'odt'  => 'application/vnd.oasis.opendocument.text',
        ];

        $mime = $mime_types[$doc['file_type']] ?? 'application/octet-stream';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit;
    } else {
        setFlashMessage('error', 'File not found or access denied.');
        redirect(STUDENT_URL . '/documents.php');
    }
}

// ── Fetch filters ────────────────────────────────────────────────────────────
$search   = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$filter_book = (int)($_GET['book_id'] ?? 0);

// ── Fetch stats ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(file_size), 0) as total_size FROM student_documents WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$category_counts = [];
$stmt = $conn->prepare("SELECT doc_category, COUNT(*) as cnt FROM student_documents WHERE user_id = ? GROUP BY doc_category");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $category_counts[$row['doc_category']] = $row['cnt'];
}
$stmt->close();

// ── Fetch books for dropdowns ────────────────────────────────────────────────
$books = [];
$stmt = $conn->prepare("SELECT book_id, book_name FROM books ORDER BY book_name ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}
$stmt->close();

// ── Fetch documents ──────────────────────────────────────────────────────────
$where  = "WHERE d.user_id = ?";
$params = [$user_id];
$types  = 'i';

if ($search !== '') {
    $where .= " AND (d.doc_title LIKE ? OR d.doc_description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($category !== '') {
    $where .= " AND d.doc_category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($filter_book > 0) {
    $where .= " AND d.book_id = ?";
    $params[] = $filter_book;
    $types .= 'i';
}

$sql = "SELECT d.*, b.book_name
        FROM student_documents d
        LEFT JOIN books b ON d.book_id = b.book_id
        {$where}
        ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token = generateCSRFToken();

$category_labels = [
    'note'        => 'Note',
    'document'    => 'Document',
    'book_review' => 'Book Review',
    'summary'     => 'Summary',
    'other'       => 'Other',
];

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<!-- Page Header -->
<div class="page-header">
    <h1>My Documents</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#documentModal" onclick="resetUploadForm()">
        <i class="fas fa-upload"></i> Upload Document
    </button>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3><?= (int)$stats['total'] ?></h3>
                <p class="text-muted mb-0">Total Documents</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3><?= formatFileSize($stats['total_size']) ?></h3>
                <p class="text-muted mb-0">Total Size</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3><?= (int)($category_counts['note'] ?? 0) ?></h3>
                <p class="text-muted mb-0">Notes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h3><?= (int)($category_counts['book_review'] ?? 0) ?></h3>
                <p class="text-muted mb-0">Book Reviews</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filters-bar">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search documents..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($category_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $category === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Book</label>
            <select name="book_id" class="form-select">
                <option value="">All Books</option>
                <?php foreach ($books as $book): ?>
                    <option value="<?= (int)$book['book_id'] ?>" <?= $filter_book === (int)$book['book_id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['book_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </form>
</div>

<!-- Documents Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Documents</h5>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="bi bi-folder2-open fa-3x text-muted mb-3"></i>
                <h5>No documents found</h5>
                <p class="text-muted">Upload your first document to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Book</th>
                            <th>File Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $i => $doc): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="<?= STUDENT_URL; ?>/documents.php?action=view&id=<?= (int)$doc['doc_id'] ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-file-earmark-<?php echo ($doc['file_type'] === 'pdf') ? 'pdf' : 'text'; ?> me-1"></i>
                                        <?= htmlspecialchars($doc['doc_title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($doc['doc_category']) ?>">
                                        <?= htmlspecialchars($category_labels[$doc['doc_category']] ?? ucfirst($doc['doc_category'])) ?>
                                    </span>
                                </td>
                                <td><?= $doc['book_name'] ? htmlspecialchars($doc['book_name']) : '<span class="text-muted">-</span>' ?></td>
                                <td><?= strtoupper(htmlspecialchars($doc['file_type'])) ?></td>
                                <td><?= formatFileSize($doc['file_size']) ?></td>
                                <td><?= formatDate($doc['created_at']) ?></td>
                                <td class="action-btns">
                                    <a href="<?= STUDENT_URL; ?>/documents.php?action=view&id=<?= (int)$doc['doc_id'] ?>" class="btn btn-sm btn-outline-info" title="View" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= STUDENT_URL; ?>/documents.php?action=download&id=<?= (int)$doc['doc_id'] ?>" class="btn btn-sm btn-outline-success" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                        onclick="editDocument(<?= (int)$doc['doc_id'] ?>, '<?= htmlspecialchars(addslashes($doc['doc_title'])) ?>', '<?= htmlspecialchars(addslashes($doc['doc_description'])) ?>', '<?= $doc['doc_category'] ?>', <?= $doc['book_id'] ? (int)$doc['book_id'] : 'null' ?>, <?= (int)$doc['is_private'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="doc_id" value="<?= (int)$doc['doc_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<!-- Upload / Edit Modal -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="upload" id="modalAction">
                <input type="hidden" name="doc_id" value="0" id="modalDocId">

                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalLabel">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="doc_title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="doc_title" name="doc_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="doc_description" class="form-label">Description</label>
                        <textarea class="form-control" id="doc_description" name="doc_description" rows="3"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="doc_category" class="form-label">Category</label>
                            <select class="form-select" id="doc_category" name="doc_category">
                                <?php foreach ($category_labels as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="book_id" class="form-label">Link to Book (optional)</label>
                            <select class="form-select" id="book_id" name="book_id">
                                <option value="">-- None --</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?= (int)$book['book_id'] ?>"><?= htmlspecialchars($book['book_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="document_file" class="form-label">File <span id="fileRequired" class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document_file" name="document">
                        <small class="text-muted">Allowed: pdf, doc, docx, txt, rtf, odt, xls, xlsx, ppt, pptx, jpg, png, gif, zip, rar</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_private" name="is_private" value="1" checked>
                        <label class="form-check-label" for="is_private">Private (only visible to me)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetUploadForm() {
    document.getElementById('modalAction').value = 'upload';
    document.getElementById('modalDocId').value = '0';
    document.getElementById('documentModalLabel').textContent = 'Upload Document';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-upload"></i> Upload';
    document.getElementById('doc_title').value = '';
    document.getElementById('doc_description').value = '';
    document.getElementById('doc_category').value = 'note';
    document.getElementById('book_id').value = '';
    document.getElementById('document_file').value = '';
    document.getElementById('is_private').checked = true;
    document.getElementById('document_file').required = true;
    document.getElementById('fileRequired').style.display = '';
}

function editDocument(docId, title, description, category, bookId, isPrivate) {
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('modalDocId').value = docId;
    document.getElementById('documentModalLabel').textContent = 'Edit Document';
    document.getElementById('modalSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Save Changes';
    document.getElementById('doc_title').value = title;
    document.getElementById('doc_description').value = description;
    document.getElementById('doc_category').value = category;
    document.getElementById('book_id').value = bookId || '';
    document.getElementById('document_file').value = '';
    document.getElementById('is_private').checked = isPrivate === 1;
    document.getElementById('document_file').required = false;
    document.getElementById('fileRequired').style.display = 'none';

    var modal = new bootstrap.Modal(document.getElementById('documentModal'));
    modal.show();
}
</script>

<?php require_once 'includes/student_footer.php'; ?>
