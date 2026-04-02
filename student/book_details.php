<?php
$page_title = 'Book Details';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];
$book_id = (int)($_GET['id'] ?? 0);
$_issue_approved = ISSUE_APPROVED;
$_issue_overdue = ISSUE_OVERDUE;
$_issue_pending = ISSUE_PENDING;

if ($book_id <= 0) {
    setFlashMessage('danger', 'Invalid book ID.');
    redirect(STUDENT_URL . '/catalog.php');
}

// Fetch book details
$stmt = $conn->prepare("SELECT b.*, a.author_name, a.bio as author_bio, c.cat_name
    FROM books b
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.cat_id = c.cat_id
    WHERE b.book_id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

// Increment view count
if ($book) {
    $conn->query("UPDATE books SET view_count = view_count + 1 WHERE book_id = " . (int)$book_id);
}

if (!$book) {
    setFlashMessage('danger', 'Book not found.');
    redirect(STUDENT_URL . '/catalog.php');
}

// Check if in reading list
$stmt = $conn->prepare("SELECT id FROM reading_list WHERE user_id = ? AND book_id = ?");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$in_reading_list = $stmt->get_result()->num_rows > 0;

// Check if currently borrowed by this student
$stmt = $conn->prepare("SELECT ib.*, b.book_name FROM issued_books ib JOIN books b ON ib.book_id = b.book_id WHERE ib.user_id = ? AND ib.book_id = ? AND ib.status IN (?, ?)");
$stmt->bind_param("iiii", $user_id, $book_id, $_issue_approved, $_issue_overdue);
$stmt->execute();
$current_issue = $stmt->get_result()->fetch_assoc();

// Check if has pending request
$stmt = $conn->prepare("SELECT issue_id FROM issued_books WHERE user_id = ? AND book_id = ? AND status = ?");
$stmt->bind_param("iii", $user_id, $book_id, $_issue_pending);
$stmt->execute();
$pending_request = $stmt->get_result()->fetch_assoc();

// Check if already has an active reservation
$stmt = $conn->prepare("SELECT reservation_id FROM reservations WHERE user_id = ? AND book_id = ? AND status = 'active' AND expiry_date >= CURDATE()");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$has_reservation = $stmt->get_result()->num_rows > 0;

// Get ebooks for this book
$stmt = $conn->prepare("SELECT * FROM ebooks WHERE book_id = ? AND (is_public = 1 OR uploaded_by IS NOT NULL)");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$ebooks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle Add to Reading List
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_reading_list'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } else {
        if ($in_reading_list) {
            $stmt = $conn->prepare("DELETE FROM reading_list WHERE user_id = ? AND book_id = ?");
            $stmt->bind_param("ii", $user_id, $book_id);
            $stmt->execute();
            setFlashMessage('success', 'Book removed from reading list.');
            $in_reading_list = false;
        } else {
            $stmt = $conn->prepare("INSERT INTO reading_list (user_id, book_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $book_id);
            $stmt->execute();
            setFlashMessage('success', 'Book added to reading list.');
            $in_reading_list = true;
        }
    }
    redirect(STUDENT_URL . '/book_details.php?id=' . $book_id);
}

// Handle Request to Borrow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_borrow'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } elseif (!isApproved()) {
        setFlashMessage('danger', 'Your account is not approved yet. Please wait for admin approval.');
    } elseif ($current_issue) {
        setFlashMessage('danger', 'You already have this book issued.');
    } elseif ($pending_request) {
        setFlashMessage('info', 'You already have a pending request for this book.');
    } elseif ($book['available_copies'] <= 0) {
        setFlashMessage('danger', 'No copies of this book are currently available.');
    } else {
        // Check max books
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status IN (?, ?, ?)");
        $s_pending = ISSUE_PENDING;
        $s_approved = ISSUE_APPROVED;
        $s_overdue = ISSUE_OVERDUE;
        $stmt->bind_param("iiii", $user_id, $s_pending, $s_approved, $s_overdue);
        $stmt->execute();
        $current_count = $stmt->get_result()->fetch_assoc()['cnt'];

        $stmt = $conn->prepare("SELECT max_books_allowed FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $max_allowed = $stmt->get_result()->fetch_assoc()['max_books_allowed'];

        if ($current_count >= $max_allowed) {
            setFlashMessage('danger', "You have reached the maximum book limit ({$max_allowed}). Return a book before requesting another.");
        } else {
            $issue_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+' . DEFAULT_BORROW_DAYS . ' days'));
            $status = ISSUE_PENDING;

            $stmt = $conn->prepare("INSERT INTO issued_books (book_id, user_id, issue_date, due_date, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $book_id, $user_id, $issue_date, $due_date, $status);
            if ($stmt->execute()) {
                // Notify admin
                $auth->createAdminNotification('Book Request', "Student {$_SESSION['name']} has requested to borrow '{$book['book_name']}'.", ADMIN_URL . '/issue_book.php');
                $auth->logActivity('student', $user_id, 'request_book', "Requested book: {$book['book_name']}");
                setFlashMessage('success', "Your request to borrow '{$book['book_name']}' has been submitted. Please wait for admin approval.");
                $pending_request = true;
            } else {
                setFlashMessage('danger', 'Failed to submit request. Please try again.');
            }
        }
    }
    redirect(STUDENT_URL . '/book_details.php?id=' . $book_id);
}

// Handle Reserve Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_book'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
    } elseif (!isApproved()) {
        setFlashMessage('danger', 'Your account is not approved yet.');
    } elseif ($has_reservation) {
        setFlashMessage('info', 'You already have an active reservation for this book.');
    } elseif ($book['available_copies'] > 0) {
        setFlashMessage('info', 'This book is available. Please use "Request to Borrow" instead.');
    } else {
        $expiry_date = date('Y-m-d', strtotime('+7 days'));
        $stmt = $conn->prepare("INSERT INTO reservations (book_id, user_id, status, expiry_date) VALUES (?, ?, 'active', ?)");
        $stmt->bind_param("iis", $book_id, $user_id, $expiry_date);
        if ($stmt->execute()) {
            $auth->createAdminNotification('Book Reservation', "Student {$_SESSION['name']} has reserved '{$book['book_name']}'.", ADMIN_URL . '/reservations.php');
            $auth->logActivity('student', $user_id, 'reserve_book', "Reserved book: {$book['book_name']}");
            setFlashMessage('success', "You have successfully reserved '{$book['book_name']}'. We will notify you when it becomes available.");
            $has_reservation = true;
        } else {
            setFlashMessage('danger', 'Failed to reserve the book. Please try again.');
        }
    }
    redirect(STUDENT_URL . '/book_details.php?id=' . $book_id);
}

// Handle e-book download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_ebook'])) {
    $ebook_id = (int)$_POST['ebook_id'];
    $stmt = $conn->prepare("SELECT * FROM ebooks WHERE ebook_id = ? AND book_id = ?");
    $stmt->bind_param("ii", $ebook_id, $book_id);
    $stmt->execute();
    $ebook = $stmt->get_result()->fetch_assoc();

    if ($ebook) {
        // Increment download count
        $stmt = $conn->prepare("UPDATE ebooks SET download_count = download_count + 1 WHERE ebook_id = ?");
        $stmt->bind_param("i", $ebook_id);
        $stmt->execute();

        $auth->logActivity('student', $user_id, 'download_ebook', "Downloaded e-book: {$book['book_name']}");
        redirect(SITE_URL . '/' . $ebook['file_path']);
    }
}

// Related books (same category)
$stmt = $conn->prepare("SELECT b.*, a.author_name FROM books b LEFT JOIN authors a ON b.author_id = a.author_id WHERE b.cat_id = ? AND b.book_id != ? AND b.status = 'available' ORDER BY b.book_name LIMIT 4");
$stmt->bind_param("ii", $book['cat_id'], $book_id);
$stmt->execute();
$related_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$csrf_token = generateCSRFToken();
?>

<!-- Breadcrumb -->
<div class="page-header">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo STUDENT_URL; ?>/catalog.php">Book Catalog</a></li>
                <li class="breadcrumb-item active"><?php echo sanitize($book['book_name']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Book Cover & Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="book-cover mx-auto mb-3" style="width:100%;height:280px;font-size:4rem;border-radius:var(--border-radius);">
                    <?php if ($book['cover_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="<?php echo sanitize($book['book_name']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:var(--border-radius);">
                    <?php else: ?>
                        <i class="bi bi-book"></i>
                    <?php endif; ?>
                </div>

                <!-- Availability -->
                <?php if ($book['available_copies'] > 0): ?>
                    <div class="alert alert-success py-2 mb-3">
                        <i class="bi bi-check-circle me-1"></i>
                        <strong><?php echo $book['available_copies']; ?></strong> of <?php echo $book['total_copies']; ?> copies available
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="bi bi-x-circle me-1"></i>Currently unavailable
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <?php if ($current_issue): ?>
                        <div class="alert alert-info py-2 mb-2">
                            <i class="bi bi-info-circle me-1"></i>You currently have this book. Due: <?php echo formatDate($current_issue['due_date']); ?>
                        </div>
                        <a href="<?php echo STUDENT_URL; ?>/my_books.php" class="btn btn-outline-primary">
                            <i class="bi bi-journal-bookmark me-1"></i>View My Books
                        </a>
                    <?php elseif ($pending_request): ?>
                        <div class="alert alert-warning py-2 mb-2">
                            <i class="bi bi-hourglass-split me-1"></i>Your request is pending admin approval
                        </div>
                    <?php elseif ($has_reservation): ?>
                        <div class="alert alert-info py-2 mb-2">
                            <i class="bi bi-bookmark-check me-1"></i>You have an active reservation for this book
                        </div>
                    <?php elseif (isApproved() && $book['available_copies'] > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" name="request_borrow" class="btn btn-primary w-100">
                                <i class="bi bi-journal-arrow-up me-1"></i>Request to Borrow
                            </button>
                        </form>
                    <?php elseif (isApproved() && $book['available_copies'] <= 0): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" name="reserve_book" class="btn btn-warning w-100">
                                <i class="bi bi-bookmark-plus me-1"></i>Reserve This Book
                            </button>
                        </form>
                    <?php elseif (!isApproved()): ?>
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>Account pending approval. You cannot borrow books yet.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ebooks)): ?>
                        <?php foreach ($ebooks as $eb): ?>
                            <?php if ($eb['is_public']): ?>
                                <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $eb['ebook_id']; ?>" class="btn btn-info w-100">
                                    <i class="bi bi-book-half me-1"></i>Read Book Online
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <a href="<?php echo STUDENT_URL; ?>/read_book.php?book_id=<?php echo $book_id; ?><?php echo !empty($ebooks) ? '&id=' . $ebooks[0]['ebook_id'] : ''; ?>" class="btn btn-outline-dark w-100 mt-2">
                        <i class="bi bi-journal-text me-1"></i>Read & Take Notes
                    </a>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" name="add_to_reading_list" class="btn <?php echo $in_reading_list ? 'btn-success' : 'btn-outline-primary'; ?> w-100">
                            <i class="bi bi-bookmark-<?php echo $in_reading_list ? 'check' : 'plus'; ?> me-1"></i>
                            <?php echo $in_reading_list ? 'In Reading List' : 'Add to Reading List'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- E-Books Section -->
        <?php if (!empty($ebooks)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Available E-Books</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($ebooks as $eb): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong style="font-size:0.88rem;"><?php echo strtoupper($eb['file_type']); ?></strong>
                                    <br><small class="text-muted"><?php echo number_format($eb['file_size'] / 1024, 1); ?> KB</small>
                                </div>
                                <div>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="download_ebook" value="1">
                                        <input type="hidden" name="ebook_id" value="<?php echo $eb['ebook_id']; ?>">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($eb['is_public']): ?>
                                                <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $eb['ebook_id']; ?>" class="btn btn-outline-primary" title="Read Online"><i class="bi bi-book-half"></i></a>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-outline-success" title="Download"><i class="bi bi-download"></i></button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Book Details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="mb-2"><?php echo sanitize($book['book_name']); ?></h3>
                <p class="text-muted mb-3" style="font-size:1.05rem;">
                    <i class="bi bi-person me-1"></i>by <?php echo sanitize($book['author_name']); ?>
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge bg-light text-dark" style="font-size:0.85rem;padding:6px 12px;">
                        <i class="bi bi-tag me-1"></i><?php echo sanitize($book['cat_name']); ?>
                    </span>
                    <span class="badge bg-<?php echo $book['status'] === 'available' ? 'success' : 'secondary'; ?>" style="font-size:0.85rem;padding:6px 12px;">
                        <?php echo ucfirst($book['status']); ?>
                    </span>
                    <?php if ($book['has_ebook']): ?>
                        <span class="badge bg-info" style="font-size:0.85rem;padding:6px 12px;">
                            <i class="bi bi-file-earmark-pdf me-1"></i>E-Book Available
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Popularity Stats -->
                <div class="d-flex flex-wrap gap-3 mb-3 p-3" style="background:#f8f9fa;border-radius:8px;">
                    <div class="text-center">
                        <i class="bi bi-eye text-primary" style="font-size:1.3rem;"></i><br>
                        <strong><?php echo number_format($book['view_count'] ?? 0); ?></strong><br>
                        <small class="text-muted">Views</small>
                    </div>
                    <div class="text-center">
                        <i class="bi bi-download text-success" style="font-size:1.3rem;"></i><br>
                        <?php
                        $dl = $conn->prepare("SELECT COALESCE(SUM(download_count),0) as dl FROM ebooks WHERE book_id = ?");
                        $dl->bind_param("i", $book_id);
                        $dl->execute();
                        $downloads = $dl->get_result()->fetch_assoc()['dl'];
                        ?>
                        <strong><?php echo number_format($downloads); ?></strong><br>
                        <small class="text-muted">Downloads</small>
                    </div>
                    <div class="text-center">
                        <i class="bi bi-journal-bookmark text-info" style="font-size:1.3rem;"></i><br>
                        <strong><?php echo $book['total_copies']; ?></strong><br>
                        <small class="text-muted">Copies</small>
                    </div>
                    <div class="text-center">
                        <i class="bi bi-star text-warning" style="font-size:1.3rem;"></i><br>
                        <?php
                        $issued_count = $conn->prepare("SELECT COUNT(*) as c FROM issued_books WHERE book_id = ?");
                        $issued_count->bind_param("i", $book_id);
                        $issued_count->execute();
                        $times_issued = $issued_count->get_result()->fetch_assoc()['c'];
                        ?>
                        <strong><?php echo number_format($times_issued); ?></strong><br>
                        <small class="text-muted">Times Issued</small>
                    </div>
                </div>

                <?php if ($book['description']): ?>
                    <h6>Description</h6>
                    <p class="text-muted"><?php echo nl2br(sanitize($book['description'])); ?></p>
                    <hr>
                <?php endif; ?>

                <!-- Book Details Table -->
                <h6>Book Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <?php if ($book['isbn']): ?>
                            <tr>
                                <td class="text-muted" style="width:40%;">ISBN</td>
                                <td><strong><?php echo sanitize($book['isbn']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="text-muted">Book No</td>
                                <td><strong><?php echo sanitize($book['book_no']); ?></strong></td>
                            </tr>
                            <?php if ($book['publisher']): ?>
                            <tr>
                                <td class="text-muted">Publisher</td>
                                <td><strong><?php echo sanitize($book['publisher']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($book['publication_year']): ?>
                            <tr>
                                <td class="text-muted">Year</td>
                                <td><strong><?php echo $book['publication_year']; ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <?php if ($book['edition']): ?>
                            <tr>
                                <td class="text-muted" style="width:40%;">Edition</td>
                                <td><strong><?php echo sanitize($book['edition']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($book['pages']): ?>
                            <tr>
                                <td class="text-muted">Pages</td>
                                <td><strong><?php echo $book['pages']; ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($book['rack_location']): ?>
                            <tr>
                                <td class="text-muted">Location</td>
                                <td><strong><?php echo sanitize($book['rack_location']); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($book['book_price'] > 0): ?>
                            <tr>
                                <td class="text-muted">Price</td>
                                <td><strong>&#8377;<?php echo number_format($book['book_price'], 2); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Author Bio -->
                <?php if ($book['author_bio']): ?>
                    <hr>
                    <h6>About the Author</h6>
                    <p class="text-muted" style="font-size:0.9rem;"><?php echo sanitize($book['author_bio']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Books -->
        <?php if (!empty($related_books)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-collection me-2"></i>Related Books</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($related_books as $rb): ?>
                            <div class="col-md-3 col-6">
                                <a href="<?php echo STUDENT_URL; ?>/book_details.php?id=<?php echo $rb['book_id']; ?>" class="text-decoration-none">
                                    <div class="book-card">
                                        <div class="book-cover" style="height:120px;font-size:2rem;">
                                            <i class="bi bi-book"></i>
                                        </div>
                                        <div class="book-info" style="padding:0.8rem;">
                                            <h5 style="font-size:0.85rem;" title="<?php echo sanitize($rb['book_name']); ?>"><?php echo sanitize($rb['book_name']); ?></h5>
                                            <p class="book-author" style="font-size:0.78rem;"><?php echo sanitize($rb['author_name']); ?></p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
