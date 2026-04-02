<?php
$page_title = 'E-Book Management';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();

// Ensure upload directory exists
if (!is_dir(EBOOKS_DIR)) {
    mkdir(EBOOKS_DIR, 0755, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/ebooks.php');
    }

    $book_id = intval($_POST['book_id'] ?? 0);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $errors = [];

    if ($book_id <= 0) $errors[] = 'Please select a book.';

    if (!isset($_FILES['ebook_file']) || $_FILES['ebook_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid file to upload.';
    } else {
        $file = $_FILES['ebook_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ALLOWED_EBOOK_TYPES)) {
            $errors[] = 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EBOOK_TYPES);
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.';
        }
    }

    if (empty($errors)) {
        // Check if book exists
        $stmt = $conn->prepare("SELECT book_id, book_name FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();

        if (!$book) {
            $errors[] = 'Selected book not found.';
        }
    }

    if (empty($errors)) {
        $filename = 'ebook_' . $book_id . '_' . time() . '.' . $ext;
        $filepath = EBOOKS_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $db_path = 'uploads/ebooks/' . $filename;
            $file_size = $file['size'];
            $admin_id = $_SESSION['user_id'];

            $stmt = $conn->prepare("INSERT INTO ebooks (book_id, file_path, file_type, file_size, uploaded_by, is_public) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiii", $book_id, $db_path, $ext, $file_size, $admin_id, $is_public);
            $stmt->execute();

            // Update book has_ebook flag
            $stmt = $conn->prepare("UPDATE books SET has_ebook = 1 WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();

            $auth->logActivity('admin', $_SESSION['user_id'], 'upload_ebook', "Uploaded e-book for '{$book['book_name']}'");

            // Notify all students if public
            if ($is_public) {
                $auth->notifyAllStudents(
                    'New E-Book Available',
                    "A new e-book '{$book['book_name']}' is now available to read online.",
                    STUDENT_URL . '/read_book.php?id=' . $stmt->insert_id
                );
            }

            setFlashMessage('success', 'E-book uploaded successfully.');
        } else {
            setFlashMessage('danger', 'Failed to upload file. Please try again.');
        }
    } else {
        setFlashMessage('danger', implode('<br>', array_map('sanitize', $errors)));
    }
    redirect(ADMIN_URL . '/ebooks.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/ebooks.php');
    }

    $ebook_id = intval($_POST['ebook_id'] ?? 0);
    $stmt = $conn->prepare("SELECT e.*, b.book_name FROM ebooks e JOIN books b ON e.book_id = b.book_id WHERE e.ebook_id = ?");
    $stmt->bind_param("i", $ebook_id);
    $stmt->execute();
    $ebook = $stmt->get_result()->fetch_assoc();

    if ($ebook) {
        $file_path = dirname(__DIR__) . '/' . $ebook['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $stmt = $conn->prepare("DELETE FROM ebooks WHERE ebook_id = ?");
        $stmt->bind_param("i", $ebook_id);
        $stmt->execute();

        // Check if book still has ebooks
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM ebooks WHERE book_id = ?");
        $stmt->bind_param("i", $ebook['book_id']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['c'] == 0) {
            $stmt = $conn->prepare("UPDATE books SET has_ebook = 0 WHERE book_id = ?");
            $stmt->bind_param("i", $ebook['book_id']);
            $stmt->execute();
        }

        $auth->logActivity('admin', $_SESSION['user_id'], 'delete_ebook', "Deleted e-book for '{$ebook['book_name']}'");
        setFlashMessage('success', 'E-book deleted successfully.');
    }
    redirect(ADMIN_URL . '/ebooks.php');
}

// Handle toggle public
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_public') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/ebooks.php');
    }

    $ebook_id = intval($_POST['ebook_id'] ?? 0);
    
    // Check current status before toggling
    $stmt = $conn->prepare("SELECT e.is_public, b.book_name FROM ebooks e JOIN books b ON e.book_id = b.book_id WHERE e.ebook_id = ?");
    $stmt->bind_param("i", $ebook_id);
    $stmt->execute();
    $ebook_info = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("UPDATE ebooks SET is_public = NOT is_public WHERE ebook_id = ?");
    $stmt->bind_param("i", $ebook_id);
    $stmt->execute();

    // Notify students if just made public
    if ($ebook_info && !$ebook_info['is_public']) {
        $auth->notifyAllStudents(
            'New E-Book Available',
            "The e-book '{$ebook_info['book_name']}' is now available to read online.",
            STUDENT_URL . '/read_book.php?id=' . $ebook_id
        );
    }

    setFlashMessage('success', 'E-book visibility updated.');
    redirect(ADMIN_URL . '/ebooks.php');
}

// Get all ebooks
$stmt = $conn->prepare("SELECT e.*, b.book_name, b.isbn, a.name as uploader_name
    FROM ebooks e
    JOIN books b ON e.book_id = b.book_id
    LEFT JOIN admins a ON e.uploaded_by = a.id
    ORDER BY e.created_at DESC");
$stmt->execute();
$ebooks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get books without ebooks for upload form
$stmt = $conn->prepare("SELECT book_id, book_name FROM books WHERE status = 'available' ORDER BY book_name");
$stmt->execute();
$books_for_upload = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$total_ebooks = count($ebooks);
$total_public = 0;
$total_downloads = 0;
$total_views = 0;
foreach ($ebooks as $e) {
    if ($e['is_public']) $total_public++;
    $total_downloads += $e['download_count'];
    $total_views += $e['view_count'];
}
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-file-earmark-pdf me-2"></i>E-Book Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">E-Books</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-upload me-1"></i>Upload E-Book</button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-file-earmark-pdf"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_ebooks; ?></h3>
            <p>Total E-Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-globe"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_public; ?></h3>
            <p>Public</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="bi bi-download"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_downloads; ?></h3>
            <p>Downloads</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-eye"></i></div>
        <div class="stat-info">
            <h3><?php echo $total_views; ?></h3>
            <p>Views</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>E-Books (<?php echo $total_ebooks; ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($ebooks)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h5>No E-Books</h5>
                <p>No e-books have been uploaded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Book Name</th>
                            <th>File Type</th>
                            <th>File Size</th>
                            <th>Downloads</th>
                            <th>Views</th>
                            <th>Public</th>
                            <th>Uploaded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ebooks as $e): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($e['book_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($e['isbn'] ?? ''); ?></small>
                                </td>
                                <td><span class="badge bg-<?php echo $e['file_type'] === 'pdf' ? 'danger' : 'secondary'; ?>"><?php echo strtoupper($e['file_type']); ?></span></td>
                                <td><?php echo number_format($e['file_size'] / 1024, 1); ?> KB</td>
                                <td><?php echo $e['download_count']; ?></td>
                                <td><?php echo $e['view_count']; ?></td>
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="toggle_public">
                                        <input type="hidden" name="ebook_id" value="<?php echo $e['ebook_id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $e['is_public'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                                            <?php echo $e['is_public'] ? '<i class="bi bi-check-circle"></i> Yes' : '<i class="bi bi-x-circle"></i> No'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td><?php echo sanitize($e['uploader_name'] ?? 'N/A'); ?></td>
                                <td class="action-btns">
                                    <a href="<?php echo SITE_URL . '/' . $e['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                    <a href="<?php echo SITE_URL . '/' . $e['file_path']; ?>" download class="btn btn-sm btn-outline-success" title="Download"><i class="bi bi-download"></i></a>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this e-book?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="ebook_id" value="<?php echo $e['ebook_id']; ?>">
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

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="upload">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload E-Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Book <span class="text-danger">*</span></label>
                        <select class="form-select" name="book_id" required>
                            <option value="">-- Select Book --</option>
                            <?php foreach ($books_for_upload as $b): ?>
                                <option value="<?php echo $b['book_id']; ?>"><?php echo sanitize($b['book_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-Book File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="ebook_file" accept=".pdf,.epub,.mobi" required>
                        <div class="form-text">Allowed: PDF, EPUB, MOBI. Max: <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1">
                        <label class="form-check-label" for="is_public">Make publicly accessible to all students</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
