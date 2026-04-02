<?php
$page_title = 'E-Books Library';
include 'includes/student_header.php';

// Handle view/download increment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ebook_action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(STUDENT_URL . '/ebooks.php');
    }
    $ebook_id = (int)$_POST['ebook_id'];
    $action = $_POST['ebook_action'];

    if ($action === 'view') {
        $stmt = $conn->prepare("UPDATE ebooks SET view_count = view_count + 1 WHERE ebook_id = ?");
        $stmt->bind_param("i", $ebook_id);
        $stmt->execute();
    } elseif ($action === 'download') {
        $stmt = $conn->prepare("UPDATE ebooks SET download_count = download_count + 1 WHERE ebook_id = ?");
        $stmt->bind_param("i", $ebook_id);
        $stmt->execute();
    }
}

// Fetch e-books
$stmt = $conn->prepare("SELECT e.*, b.book_name, b.cover_image, b.description, a.author_name, c.cat_name
    FROM ebooks e
    JOIN books b ON e.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.cat_id = c.cat_id
    WHERE e.is_public = 1
    ORDER BY b.book_name ASC");
$stmt->execute();
$ebooks = $stmt->get_result();
?>

<div class="page-header">
    <div>
        <h4>E-Books Library</h4>
        <p class="text-muted mb-0">Browse and read digital books online</p>
    </div>
</div>

<?php if ($ebooks->num_rows > 0): ?>
    <div class="books-grid">
        <?php while ($eb = $ebooks->fetch_assoc()):
            $file_size = $eb['file_size'] ? round($eb['file_size'] / 1024 / 1024, 2) . ' MB' : 'N/A';
        ?>
            <div class="book-card">
                <div class="book-cover <?php echo $eb['cover_image'] ? 'has-image' : ''; ?>">
                    <?php if ($eb['cover_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $eb['cover_image']; ?>" alt="<?php echo sanitize($eb['book_name']); ?>">
                    <?php else: ?>
                        <i class="bi bi-file-earmark-pdf"></i>
                    <?php endif; ?>
                    <span class="ebook-badge"><i class="bi bi-file-earmark-pdf"></i> <?php echo strtoupper(sanitize($eb['file_type'])); ?></span>
                </div>
                <div class="book-info">
                    <h5 title="<?php echo sanitize($eb['book_name']); ?>"><?php echo sanitize($eb['book_name']); ?></h5>
                    <p class="book-author"><i class="bi bi-person"></i> <?php echo sanitize($eb['author_name']); ?></p>
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge bg-light text-dark" style="font-size:0.72rem;"><?php echo sanitize($eb['cat_name']); ?></span>
                        <span class="badge bg-info" style="font-size:0.72rem;"><?php echo strtoupper(sanitize($eb['file_type'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:0.78rem;">
                        <span><i class="bi bi-hdd"></i> <?php echo $file_size; ?></span>
                        <span><i class="bi bi-eye"></i> <?php echo $eb['view_count']; ?></span>
                        <span><i class="bi bi-download"></i> <?php echo $eb['download_count']; ?></span>
                    </div>
                </div>
                <div class="book-actions">
                    <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $eb['ebook_id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="bi bi-book-half"></i> Read
                    </a>
                    <form method="POST" class="d-inline flex-fill">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="ebook_id" value="<?php echo $eb['ebook_id']; ?>">
                        <input type="hidden" name="ebook_action" value="download">
                        <a href="<?php echo SITE_URL; ?>/uploads/ebooks/<?php echo sanitize($eb['file_path']); ?>" class="btn btn-primary btn-sm w-100" download>
                            <i class="bi bi-download"></i> Download
                        </a>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                <h5>No E-Books Available</h5>
                <p>There are no e-books available at the moment. Check back later!</p>
                <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary btn-sm">Browse Catalog</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/student_footer.php'; ?>
