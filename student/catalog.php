<?php
$page_title = 'Book Catalog';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];

// Handle Add to Reading List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_reading_list'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } else {
        $book_id = (int)$_POST['book_id'];
        // Check if already in reading list
        $stmt = $conn->prepare("SELECT id FROM reading_list WHERE user_id = ? AND book_id = ?");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlashMessage('info', 'Book is already in your reading list.');
        } else {
            $stmt = $conn->prepare("INSERT INTO reading_list (user_id, book_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $book_id);
            if ($stmt->execute()) {
                setFlashMessage('success', 'Book added to reading list.');
            } else {
                setFlashMessage('danger', 'Failed to add book to reading list.');
            }
        }
    }
    redirect(STUDENT_URL . '/catalog.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$author_id = (int)($_GET['author'] ?? 0);
$availability = $_GET['availability'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["b.status = 'available'"];
$params = [];
$types = '';

if ($search) {
    $where[] = "(b.book_name LIKE ? OR a.author_name LIKE ? OR b.isbn LIKE ? OR b.book_no LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if ($category_id) {
    $where[] = "b.cat_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

if ($author_id) {
    $where[] = "b.author_id = ?";
    $params[] = $author_id;
    $types .= 'i';
}

if ($availability === 'available') {
    $where[] = "b.available_copies > 0";
}

$where_clause = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM books b LEFT JOIN authors a ON b.author_id = a.author_id WHERE {$where_clause}";
$stmt = $conn->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_books = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_books / $per_page);

// Fetch books
$sql = "SELECT b.*, a.author_name, c.cat_name, e.ebook_id as public_ebook_id
    FROM books b
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.cat_id = c.cat_id
    LEFT JOIN ebooks e ON b.book_id = e.book_id AND e.is_public = 1
    WHERE {$where_clause}
    ORDER BY b.book_name ASC
    LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types .= 'ii';
$params[] = $per_page;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$books = $stmt->get_result();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY cat_name");

// Get authors for filter
$authors_list = $conn->query("SELECT * FROM authors ORDER BY author_name");

// Build query string for pagination
$query_params = $_GET;
unset($query_params['page']);
$base_query = http_build_query($query_params);
$base_url = STUDENT_URL . '/catalog.php' . ($base_query ? '?' . $base_query . '&' : '?');
?>

<div class="page-header">
    <div>
        <h4>Book Catalog</h4>
        <p class="text-muted mb-0">Browse and discover books in our library</p>
    </div>
</div>

<!-- Search Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?php echo sanitize($search); ?>" placeholder="Title, Author, ISBN, Book No...">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['cat_id']; ?>" <?php echo $category_id == $cat['cat_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['cat_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Author</label>
                    <select class="form-select" name="author">
                        <option value="">All Authors</option>
                        <?php while ($auth = $authors_list->fetch_assoc()): ?>
                            <option value="<?php echo $auth['author_id']; ?>" <?php echo $author_id == $auth['author_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($auth['author_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Availability</label>
                    <select class="form-select" name="availability">
                        <option value="">All Books</option>
                        <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                        <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results info -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted" style="font-size:0.88rem;">Showing <?php echo $books->num_rows; ?> of <?php echo $total_books; ?> books</span>
</div>

<!-- Books Grid -->
<?php if ($books->num_rows > 0): ?>
    <div class="books-grid">
        <?php while ($book = $books->fetch_assoc()): ?>
            <div class="book-card">
                <div class="book-cover <?php echo $book['cover_image'] ? 'has-image' : ''; ?>">
                    <?php if ($book['cover_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="<?php echo sanitize($book['book_name']); ?>">
                    <?php else: ?>
                        <i class="bi bi-book"></i>
                    <?php endif; ?>
                    <?php if ($book['has_ebook']): ?>
                        <span class="ebook-badge"><i class="bi bi-file-earmark-pdf"></i> E-Book</span>
                    <?php endif; ?>
                </div>
                <div class="book-info">
                    <h5 title="<?php echo sanitize($book['book_name']); ?>"><?php echo sanitize($book['book_name']); ?></h5>
                    <p class="book-author"><i class="bi bi-person"></i> <?php echo sanitize($book['author_name']); ?></p>
                    <span class="badge bg-light text-dark" style="font-size:0.72rem;"><?php echo sanitize($book['cat_name']); ?></span>
                    <div class="book-meta">
                        <span class="copies">
                            <i class="bi bi-eye"></i> <?php echo number_format($book['view_count'] ?? 0); ?>
                        </span>
                        <span class="copies">
                            <i class="bi bi-stack"></i>
                            <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?> available
                        </span>
                        <?php if ($book['available_copies'] > 0): ?>
                            <span class="status-badge available">Available</span>
                        <?php else: ?>
                            <span class="status-badge unavailable">Unavailable</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="book-actions">
                    <?php if (!empty($book['public_ebook_id'])): ?>
                        <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $book['public_ebook_id']; ?>" class="btn btn-info btn-sm flex-fill">
                            <i class="bi bi-book-half"></i> Read
                        </a>
                    <?php endif; ?>
                    <form method="POST" class="d-inline flex-fill">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                        <button type="submit" name="add_to_reading_list" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-bookmark-plus"></i> Save
                        </button>
                    </form>
                    <a href="<?php echo STUDENT_URL; ?>/book_details.php?id=<?php echo $book['book_id']; ?>" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-eye"></i> Details
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $base_url . 'page=' . ($page - 1); ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url . 'page=' . $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $base_url . 'page=' . ($page + 1); ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-search"></i>
                <h5>No Books Found</h5>
                <p>No books match your search criteria. Try adjusting your filters.</p>
                <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary btn-sm">Clear Filters</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/student_footer.php'; ?>
