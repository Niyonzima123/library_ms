<?php
$page_title = "Manage Books";
require_once __DIR__ . '/includes/admin_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/books.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $isbn = trim($_POST['isbn'] ?? '');
        $book_name = trim($_POST['book_name'] ?? '');
        $author_id = (int)($_POST['author_id'] ?? 0);
        $cat_id = (int)($_POST['cat_id'] ?? 0);
        $book_no = trim($_POST['book_no'] ?? '');
        $book_price = (float)($_POST['book_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $total_copies = max(1, (int)($_POST['total_copies'] ?? 1));
        $publisher = trim($_POST['publisher'] ?? '');
        $publication_year = (int)($_POST['publication_year'] ?? 0);
        $edition = trim($_POST['edition'] ?? '');
        $pages = (int)($_POST['pages'] ?? 0);
        $rack_location = trim($_POST['rack_location'] ?? '');
        $has_ebook = isset($_POST['has_ebook']) ? 1 : 0;
        $status = $_POST['status'] ?? 'available';

        // Load existing book data for edit
        $existing_book = null;
        if ($action === 'edit') {
            $book_id = (int)($_POST['book_id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $existing_book = $stmt->get_result()->fetch_assoc();
        }

        // Handle cover image upload
        $cover_image = $existing_book['cover_image'] ?? '';
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ALLOWED_IMAGE_TYPES;
            $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed_types)) {
                $cover_dir = COVERS_DIR;
                if (!is_dir($cover_dir)) mkdir($cover_dir, 0755, true);
                $new_name = 'book_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $cover_dir . $new_name)) {
                    // Delete old cover if editing
                    if ($action === 'edit' && !empty($existing_book['cover_image'])) {
                        $old_path = $cover_dir . $existing_book['cover_image'];
                        if (file_exists($old_path)) unlink($old_path);
                    }
                    $cover_image = $new_name;
                }
            }
        }

        if (empty($book_name) || empty($book_no) || $author_id <= 0 || $cat_id <= 0) {
            setFlashMessage('danger', 'Book name, book number, author, and category are required.');
            redirect(ADMIN_URL . '/books.php');
        }

        if ($action === 'add') {
            $available_copies = $total_copies;
            $stmt = $conn->prepare("INSERT INTO books (isbn, book_name, author_id, cat_id, book_no, book_price, description, cover_image, total_copies, available_copies, publisher, publication_year, edition, pages, rack_location, has_ebook, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiissdisississi", $isbn, $book_name, $author_id, $cat_id, $book_no, $book_price, $description, $cover_image, $total_copies, $available_copies, $publisher, $publication_year, $edition, $pages, $rack_location, $has_ebook, $status);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_book', "Added book: {$book_name} ({$book_no})");
                setFlashMessage('success', 'Book added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add book.');
            }
        } else {
            // Get current book data to adjust available copies
            $stmt = $conn->prepare("SELECT total_copies, available_copies FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            $diff = $total_copies - $current['total_copies'];
            $available_copies = max(0, $current['available_copies'] + $diff);

            $stmt = $conn->prepare("UPDATE books SET isbn=?, book_name=?, author_id=?, cat_id=?, book_no=?, book_price=?, description=?, cover_image=?, total_copies=?, available_copies=?, publisher=?, publication_year=?, edition=?, pages=?, rack_location=?, has_ebook=?, status=? WHERE book_id=?");
            $stmt->bind_param("ssiiissdisissisii", $isbn, $book_name, $author_id, $cat_id, $book_no, $book_price, $description, $cover_image, $total_copies, $available_copies, $publisher, $publication_year, $edition, $pages, $rack_location, $has_ebook, $status, $book_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_book', "Updated book ID: {$book_id}");
                setFlashMessage('success', 'Book updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update book.');
            }
        }
        redirect(ADMIN_URL . '/books.php');
    }

    if ($action === 'delete') {
        $book_id = (int)($_POST['book_id'] ?? 0);

        // Check for active issues
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE book_id = ? AND status IN (0, 1, 3)");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($count > 0) {
            setFlashMessage('danger', "Cannot delete book. It has {$count} active issue(s).");
        } else {
            $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'delete_book', "Deleted book ID: {$book_id}");
                setFlashMessage('success', 'Book deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete book.');
            }
        }
        redirect(ADMIN_URL . '/books.php');
    }
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$filter_cat = (int)($_GET['category'] ?? 0);
$filter_status = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(b.book_name LIKE ? OR b.book_no LIKE ? OR b.isbn LIKE ? OR a.author_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}
if ($filter_cat > 0) {
    $where[] = "b.cat_id = ?";
    $params[] = $filter_cat;
    $types .= 'i';
}
if (!empty($filter_status)) {
    $where[] = "b.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch books
$sql = "SELECT b.*, a.author_name, c.cat_name
        FROM books b
        JOIN authors a ON b.author_id = a.author_id
        JOIN categories c ON b.cat_id = c.cat_id
        {$where_clause}
        ORDER BY b.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

// Determine if editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$add_new = isset($_GET['action']) && $_GET['action'] === 'add';
$edit_book = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_book = $stmt->get_result()->fetch_assoc();
}

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-book me-2"></i>Manage Books</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Books</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/books.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add New Book</a>
    </div>
</div>

<!-- Search / Filter Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?php echo ADMIN_URL; ?>/books.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?php echo sanitize($search); ?>" placeholder="Book name, number, ISBN, author...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php
                    $cat_list_filter = $conn->query("SELECT cat_id, cat_name FROM categories ORDER BY cat_name");
                    while ($fc = $cat_list_filter->fetch_assoc()):
                    ?>
                        <option value="<?php echo $fc['cat_id']; ?>" <?php echo $filter_cat == $fc['cat_id'] ? 'selected' : ''; ?>><?php echo sanitize($fc['cat_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $filter_status === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    <option value="lost" <?php echo $filter_status === 'lost' ? 'selected' : ''; ?>>Lost</option>
                    <option value="damaged" <?php echo $filter_status === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($add_new || $edit_book): ?>
<!-- Add/Edit Book Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-<?php echo $edit_book ? 'pencil' : 'plus-lg'; ?> me-2"></i><?php echo $edit_book ? 'Edit Book' : 'Add New Book'; ?></h5>
        <a href="<?php echo ADMIN_URL; ?>/books.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo ADMIN_URL; ?>/books.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_book ? 'edit' : 'add'; ?>">
            <?php if ($edit_book): ?>
                <input type="hidden" name="book_id" value="<?php echo $edit_book['book_id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="isbn" class="form-label">ISBN</label>
                    <input type="text" class="form-control" id="isbn" name="isbn" value="<?php echo sanitize($edit_book['isbn'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="book_name" class="form-label">Book Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="book_name" name="book_name" value="<?php echo sanitize($edit_book['book_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="book_no" class="form-label">Book No <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="book_no" name="book_no" value="<?php echo sanitize($edit_book['book_no'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="author_id" class="form-label">Author <span class="text-danger">*</span></label>
                    <select class="form-select" id="author_id" name="author_id" required>
                        <option value="">Select Author</option>
                        <?php
                        $author_dd = $conn->query("SELECT author_id, author_name FROM authors ORDER BY author_name ASC");
                        while ($a = $author_dd->fetch_assoc()):
                        ?>
                            <option value="<?php echo $a['author_id']; ?>" <?php echo ($edit_book['author_id'] ?? '') == $a['author_id'] ? 'selected' : ''; ?>><?php echo sanitize($a['author_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="cat_id" class="form-label">Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="cat_id" name="cat_id" required>
                        <option value="">Select Category</option>
                        <?php
                        $cat_dd = $conn->query("SELECT cat_id, cat_name FROM categories ORDER BY cat_name ASC");
                        while ($c = $cat_dd->fetch_assoc()):
                        ?>
                            <option value="<?php echo $c['cat_id']; ?>" <?php echo ($edit_book['cat_id'] ?? '') == $c['cat_id'] ? 'selected' : ''; ?>><?php echo sanitize($c['cat_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="book_price" class="form-label">Price</label>
                    <input type="number" step="0.01" class="form-control" id="book_price" name="book_price" value="<?php echo $edit_book['book_price'] ?? '0.00'; ?>">
                </div>
                <div class="col-md-4">
                    <label for="total_copies" class="form-label">Total Copies</label>
                    <input type="number" min="1" class="form-control" id="total_copies" name="total_copies" value="<?php echo $edit_book['total_copies'] ?? 1; ?>">
                </div>
                <div class="col-md-4">
                    <label for="publisher" class="form-label">Publisher</label>
                    <input type="text" class="form-control" id="publisher" name="publisher" value="<?php echo sanitize($edit_book['publisher'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="publication_year" class="form-label">Publication Year</label>
                    <input type="number" min="1900" max="2099" class="form-control" id="publication_year" name="publication_year" value="<?php echo sanitize($edit_book['publication_year'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="edition" class="form-label">Edition</label>
                    <input type="text" class="form-control" id="edition" name="edition" value="<?php echo sanitize($edit_book['edition'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="pages" class="form-label">Pages</label>
                    <input type="number" min="1" class="form-control" id="pages" name="pages" value="<?php echo sanitize($edit_book['pages'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="rack_location" class="form-label">Rack Location</label>
                    <input type="text" class="form-control" id="rack_location" name="rack_location" value="<?php echo sanitize($edit_book['rack_location'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="available" <?php echo ($edit_book['status'] ?? 'available') === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="unavailable" <?php echo ($edit_book['status'] ?? '') === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        <option value="lost" <?php echo ($edit_book['status'] ?? '') === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="damaged" <?php echo ($edit_book['status'] ?? '') === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="has_ebook" name="has_ebook" <?php echo !empty($edit_book['has_ebook']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="has_ebook">Has E-Book</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo sanitize($edit_book['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="cover_image" class="form-label">Cover Image</label>
                    <input type="file" class="form-control" id="cover_image" name="cover_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                    <?php if (!empty($edit_book['cover_image'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $edit_book['cover_image']; ?>" alt="Cover" style="max-height:100px; border-radius:8px;">
                            <small class="text-muted d-block">Current cover. Upload new to replace.</small>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted">Accepted: JPG, PNG, GIF, WEBP</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> <?php echo $edit_book ? 'Update Book' : 'Add Book'; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Books Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Book No</th>
                        <th>Name</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Copies</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($books->num_rows > 0): ?>
                        <?php while ($book = $books->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($book['cover_image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="Cover" class="book-cover-thumb">
                                    <?php else: ?>
                                        <div class="book-cover-placeholder"><i class="bi bi-book"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize($book['book_no']); ?></td>
                                <td>
                                    <strong><?php echo sanitize($book['book_name']); ?></strong>
                                    <?php if ($book['has_ebook']): ?>
                                        <span class="badge bg-success ms-1" title="Has E-Book"><i class="bi bi-file-earmark-pdf"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize($book['author_name']); ?></td>
                                <td><?php echo sanitize($book['cat_name']); ?></td>
                                <td><?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?></td>
                                <td>&#8377;<?php echo number_format($book['book_price'], 2); ?></td>
                                <td><span class="status-badge <?php echo $book['status']; ?>"><?php echo ucfirst($book['status']); ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?php echo ADMIN_URL; ?>/books.php?edit=<?php echo $book['book_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/books.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Are you sure?"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><div class="empty-state"><i class="bi bi-book"></i><h5>No Books</h5><p>No books found. Try adjusting your filters or add a new book.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
