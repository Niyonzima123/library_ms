<?php
$page_title = 'Student Dashboard';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];
$_issue_approved = ISSUE_APPROVED;
$_issue_overdue = ISSUE_OVERDUE;
$_issue_pending = ISSUE_PENDING;

// Currently Borrowed count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status IN (?, ?)");
$stmt->bind_param("iii", $user_id, $_issue_approved, $_issue_overdue);
$stmt->execute();
$currently_borrowed = $stmt->get_result()->fetch_assoc()['cnt'];

// Overdue count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ? AND status = ?");
$stmt->bind_param("ii", $user_id, $_issue_overdue);
$stmt->execute();
$overdue_count = $stmt->get_result()->fetch_assoc()['cnt'];

// Total borrowed (all time)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM issued_books WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_borrowed = $stmt->get_result()->fetch_assoc()['cnt'];

// Reading list count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM reading_list WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reading_list_count = $stmt->get_result()->fetch_assoc()['cnt'];

// Pending fines
$stmt = $conn->prepare("SELECT COALESCE(SUM(fine_amount), 0) as total_fines FROM issued_books WHERE user_id = ? AND fine_paid = 0 AND fine_amount > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_fines = $stmt->get_result()->fetch_assoc()['total_fines'];

// Currently borrowed books (limit 5)
$stmt = $conn->prepare("SELECT ib.*, b.book_name, b.cover_image, a.author_name
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    WHERE ib.user_id = ? AND ib.status IN (?, ?)
    ORDER BY ib.due_date ASC LIMIT 5");
$stmt->bind_param("iii", $user_id, $_issue_approved, $_issue_overdue);
$stmt->execute();
$borrowed_books = $stmt->get_result();

// Recent notifications (limit 5)
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_type = 'student' AND user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_notifs = $stmt->get_result();

// Recently added e-books (limit 4)
$recent_ebooks = $conn->prepare("SELECT e.ebook_id, e.file_type, e.file_size, e.view_count, e.download_count, b.book_name, b.cover_image, a.author_name, c.cat_name
    FROM ebooks e
    JOIN books b ON e.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.cat_id = c.cat_id
    WHERE e.is_public = 1
    ORDER BY e.created_at DESC LIMIT 4");
$recent_ebooks->execute();
$new_ebooks = $recent_ebooks->get_result();

// Total available e-books count
$ebook_count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM ebooks WHERE is_public = 1");
$ebook_count_stmt->execute();
$total_ebooks = $ebook_count_stmt->get_result()->fetch_assoc()['cnt'];
?>

<div class="page-header">
    <div>
        <h4>Welcome back, <?php echo sanitize($_SESSION['name']); ?>!</h4>
        <p class="text-muted mb-0">Here's your library activity overview</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $currently_borrowed; ?></h3>
            <p>Currently Borrowed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $overdue_count; ?></h3>
            <p>Overdue Books</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan">
            <i class="bi bi-journal-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_borrowed; ?></h3>
            <p>Total Borrowed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-bookmark-heart"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $reading_list_count; ?></h3>
            <p>Reading List</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-currency-rupee"></i>
        </div>
        <div class="stat-info">
            <h3>&#8377;<?php echo number_format($pending_fines, 2); ?></h3>
            <p>Pending Fines</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="quick-action-card">
                <i class="bi bi-collection"></i>
                <span>Browse Catalog</span>
            </a>
            <a href="<?php echo STUDENT_URL; ?>/my_books.php" class="quick-action-card">
                <i class="bi bi-journal-bookmark"></i>
                <span>My Books</span>
            </a>
            <a href="<?php echo STUDENT_URL; ?>/reading_list.php" class="quick-action-card">
                <i class="bi bi-bookshelf"></i>
                <span>Reading List</span>
            </a>
            <a href="<?php echo STUDENT_URL; ?>/ebooks.php" class="quick-action-card">
                <i class="bi bi-file-earmark-pdf"></i>
                <span>E-Books</span>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Currently Borrowed Books -->
    <div class="col-lg-7 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-journal-bookmark"></i> Currently Borrowed</h5>
                <a href="<?php echo STUDENT_URL; ?>/my_books.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($borrowed_books->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($book = $borrowed_books->fetch_assoc()):
                                    $today = new DateTime();
                                    $due = new DateTime($book['due_date']);
                                    $is_overdue = $today > $due;
                                    $days_diff = $today->diff($due)->days;
                                    $fine = calculateFine($book['due_date']);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="book-cover" style="width:36px;height:48px;font-size:0.8rem;border-radius:4px;flex-shrink:0;">
                                                    <?php if ($book['cover_image']): ?>
                                                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $book['cover_image']; ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">
                                                    <?php else: ?>
                                                        <i class="bi bi-book" style="font-size:1rem;"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong style="font-size:0.88rem;"><?php echo sanitize($book['book_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo sanitize($book['author_name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="<?php echo $is_overdue ? 'text-danger' : 'text-muted'; ?>">
                                                <?php echo formatDate($book['due_date']); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                                <br><small class="text-danger"><?php echo $days_diff; ?> day(s) overdue</small>
                                            <?php else: ?>
                                                <br><small class="text-muted"><?php echo $days_diff; ?> day(s) left</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_overdue): ?>
                                                <span class="status-badge overdue">Overdue</span>
                                                <?php if ($fine > 0): ?>
                                                    <br><small class="text-danger mt-1 d-block">Fine: &#8377;<?php echo number_format($fine, 2); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-badge issued">Issued</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-x"></i>
                        <h5>No Books Borrowed</h5>
                        <p>You don't have any books currently borrowed.</p>
                        <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary btn-sm">Browse Catalog</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="col-lg-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="bi bi-bell"></i> Recent Notifications</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_notifs->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($notif = $recent_notifs->fetch_assoc()): ?>
                            <a href="<?php echo $notif['link'] ?: '#'; ?>" class="list-group-item list-group-item-action <?php echo !$notif['is_read'] ? 'bg-light' : ''; ?>">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="notif-icon">
                                        <i class="bi bi-bell"></i>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <strong style="font-size:0.85rem;"><?php echo sanitize($notif['title']); ?></strong>
                                        <p class="mb-1" style="font-size:0.82rem;color:var(--gray-500);"><?php echo sanitize($notif['message']); ?></p>
                                        <small style="font-size:0.75rem;color:var(--gray-400);"><?php echo formatDateTime($notif['created_at']); ?></small>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                        <span class="badge bg-primary rounded-pill" style="font-size:0.6rem;">New</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-bell-slash"></i>
                        <h5>No Notifications</h5>
                        <p>You're all caught up!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recently Added E-Books -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-file-earmark-pdf"></i> Recently Added E-Books <span class="badge bg-info"><?php echo $total_ebooks; ?> available</span></h5>
        <a href="<?php echo STUDENT_URL; ?>/ebooks.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <?php if ($new_ebooks->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($eb = $new_ebooks->fetch_assoc()):
                    $file_size = $eb['file_size'] ? round($eb['file_size'] / 1024 / 1024, 2) . ' MB' : 'N/A';
                ?>
                    <div class="col-md-3 col-6">
                        <div class="book-card h-100">
                            <div class="book-cover <?php echo $eb['cover_image'] ? 'has-image' : ''; ?>" style="height:140px;">
                                <?php if ($eb['cover_image']): ?>
                                    <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $eb['cover_image']; ?>" alt="<?php echo sanitize($eb['book_name']); ?>">
                                <?php else: ?>
                                    <i class="bi bi-file-earmark-pdf"></i>
                                <?php endif; ?>
                                <span class="ebook-badge"><i class="bi bi-file-earmark-pdf"></i> <?php echo strtoupper(sanitize($eb['file_type'])); ?></span>
                            </div>
                            <div class="book-info" style="padding:0.7rem;">
                                <h5 style="font-size:0.82rem;" title="<?php echo sanitize($eb['book_name']); ?>"><?php echo sanitize($eb['book_name']); ?></h5>
                                <p class="book-author" style="font-size:0.72rem;"><i class="bi bi-person"></i> <?php echo sanitize($eb['author_name']); ?></p>
                            </div>
                            <div class="book-actions" style="padding:0 0.7rem 0.7rem;">
                                <a href="<?php echo STUDENT_URL; ?>/read_book.php?id=<?php echo $eb['ebook_id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-book-half"></i> Read Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-3">
                <i class="bi bi-file-earmark-x" style="font-size:2rem;"></i>
                <p class="mt-2 mb-0">No e-books available yet. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
