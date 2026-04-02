<?php
$page_title = 'Reading List';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];

// Handle Remove from Reading List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_list'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } else {
        $item_id = (int)$_POST['item_id'];
        $stmt = $conn->prepare("DELETE FROM reading_list WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $item_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            setFlashMessage('success', 'Book removed from reading list.');
        } else {
            setFlashMessage('danger', 'Failed to remove book.');
        }
    }
    redirect(STUDENT_URL . '/reading_list.php');
}

// Fetch reading list
$stmt = $conn->prepare("SELECT rl.id as item_id, rl.added_at, b.*, a.author_name, c.cat_name, e.ebook_id as public_ebook_id
    FROM reading_list rl
    JOIN books b ON rl.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.cat_id = c.cat_id
    LEFT JOIN ebooks e ON b.book_id = e.book_id AND e.is_public = 1
    WHERE rl.user_id = ?
    ORDER BY rl.added_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reading_list = $stmt->get_result();
?>

<div class="page-header">
    <div>
        <h4>Reading List</h4>
        <p class="text-muted mb-0">Your saved books for later</p>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Books
        </a>
    </div>
</div>

<?php if ($reading_list->num_rows > 0): ?>
    <div class="books-grid">
        <?php while ($book = $reading_list->fetch_assoc()): ?>
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
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge bg-light text-dark" style="font-size:0.72rem;"><?php echo sanitize($book['cat_name']); ?></span>
                    </div>
                    <div class="book-meta">
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
                    <small class="text-muted d-block mt-2"><i class="bi bi-clock"></i> Added <?php echo formatDate($book['added_at']); ?></small>
                </div>
                <div class="book-actions">
                    <?php if (!empty($book['public_ebook_id'])): ?>
                        <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $book['public_ebook_id']; ?>" class="btn btn-info btn-sm flex-fill">
                            <i class="bi bi-book-half"></i> Read
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo STUDENT_URL; ?>/book_details.php?id=<?php echo $book['book_id']; ?>" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <form method="POST" class="d-inline flex-fill" onsubmit="return confirm('Remove this book from your reading list?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="item_id" value="<?php echo $book['item_id']; ?>">
                        <button type="submit" name="remove_from_list" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-bookshelf"></i>
                <h5>Your Reading List is Empty</h5>
                <p>Save books to your reading list to keep track of what you want to read next.</p>
                <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary">
                    <i class="bi bi-collection"></i> Browse Catalog
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/student_footer.php'; ?>
