<?php
$page_title = 'Issue Book';
include __DIR__ . '/includes/admin_header.php';

$csrf_token = generateCSRFToken();
$_issue_pending = ISSUE_PENDING;
$_issue_approved = ISSUE_APPROVED;
$_issue_overdue = ISSUE_OVERDUE;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/issue_book.php');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $book_id = intval($_POST['book_id'] ?? 0);
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+' . DEFAULT_BORROW_DAYS . ' days'));
    $remarks = trim($_POST['remarks'] ?? '');

    $errors = [];

    if ($user_id <= 0) $errors[] = 'Please select a student.';
    if ($book_id <= 0) $errors[] = 'Please select a book.';

    if (empty($errors)) {
        // Check student approval status
        $stmt = $conn->prepare("SELECT u.id, u.name, u.approval_status, u.max_books_allowed,
            (SELECT COUNT(*) FROM issued_books WHERE user_id = u.id AND status IN (?, ?, ?)) as current_books
            FROM users u WHERE u.id = ?");
        $stmt->bind_param("iiii", $_issue_pending, $_issue_approved, $_issue_overdue, $user_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();

        if (!$student) {
            $errors[] = 'Student not found.';
        } elseif ($student['approval_status'] !== STATUS_APPROVED) {
            $errors[] = 'Student is not approved. Cannot issue book.';
        } elseif ($student['current_books'] >= $student['max_books_allowed']) {
            $errors[] = 'Student has reached the maximum book limit (' . $student['max_books_allowed'] . ').';
        }

        if (empty($errors)) {
            // Check book availability
            $stmt = $conn->prepare("SELECT book_id, book_name, available_copies, status FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $book = $stmt->get_result()->fetch_assoc();

            if (!$book) {
                $errors[] = 'Book not found.';
            } elseif ($book['available_copies'] <= 0) {
                $errors[] = 'No copies of this book are available.';
            } elseif ($book['status'] !== 'available') {
                $errors[] = 'This book is currently unavailable.';
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insert issued_books record
            $stmt = $conn->prepare("INSERT INTO issued_books (book_id, user_id, issue_date, due_date, status, issued_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $status = ISSUE_APPROVED;
            $admin_id = $_SESSION['user_id'];
            $stmt->bind_param("iissiis", $book_id, $user_id, $issue_date, $due_date, $status, $admin_id, $remarks);
            $stmt->execute();

            // Decrement available copies
            $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();

            // Create notification for student
            $notif_title = 'Book Issued';
            $notif_message = "The book '{$book['book_name']}' has been issued to you. Due date: " . formatDate($due_date) . ".";
            $auth->createStudentNotification($user_id, $notif_title, $notif_message, STUDENT_URL . '/my_books.php');

            // Log activity
            $auth->logActivity('admin', $_SESSION['user_id'], 'issue_book', "Issued book '{$book['book_name']}' to student {$student['name']}");

            $conn->commit();
            setFlashMessage('success', "Book '{$book['book_name']}' has been issued to {$student['name']} successfully.");
            redirect(ADMIN_URL . '/issue_book.php');
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('danger', 'Failed to issue book. Please try again.');
            redirect(ADMIN_URL . '/issue_book.php');
        }
    }
}

// Handle student search
$search_student = trim($_GET['search_student'] ?? '');
$selected_user_id = intval($_GET['user_id'] ?? 0);
$selected_book_id = intval($_GET['book_id'] ?? 0);

$search_results = [];
$selected_student = null;
$selected_book = null;
$book_search = trim($_GET['search_book'] ?? '');
$book_results = [];

if ($search_student && !$selected_user_id) {
    $like = "%{$search_student}%";
    $stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.mobile, u.approval_status, u.card_number, u.max_books_allowed,
        d.dept_name, c.class_name,
        (SELECT COUNT(*) FROM issued_books WHERE user_id = u.id AND status IN (?, ?, ?)) as current_books
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE u.student_id LIKE ? OR u.name LIKE ? OR u.card_number LIKE ? OR u.email LIKE ?
        ORDER BY u.name LIMIT 20");
    $stmt->bind_param("iiissss", $_issue_pending, $_issue_approved, $_issue_overdue, $like, $like, $like, $like);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($selected_user_id) {
    $stmt = $conn->prepare("SELECT u.id, u.student_id, u.name, u.email, u.mobile, u.approval_status, u.card_number, u.max_books_allowed,
        d.dept_name, c.class_name,
        (SELECT COUNT(*) FROM issued_books WHERE user_id = u.id AND status IN (?, ?, ?)) as current_books
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        WHERE u.id = ?");
    $stmt->bind_param("iiii", $_issue_pending, $_issue_approved, $_issue_overdue, $selected_user_id);
    $stmt->execute();
    $selected_student = $stmt->get_result()->fetch_assoc();
}

if ($selected_user_id && $book_search && !$selected_book_id) {
    $like = "%{$book_search}%";
    $stmt = $conn->prepare("SELECT b.book_id, b.book_name, b.isbn, b.book_no, b.available_copies, b.total_copies, b.status, b.cover_image,
        a.author_name
        FROM books b
        LEFT JOIN authors a ON b.author_id = a.author_id
        WHERE (b.book_name LIKE ? OR b.isbn LIKE ? OR b.book_no LIKE ?) AND b.available_copies > 0 AND b.status = 'available'
        ORDER BY b.book_name LIMIT 20");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $book_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($selected_book_id) {
    $stmt = $conn->prepare("SELECT b.*, a.author_name FROM books b LEFT JOIN authors a ON b.author_id = a.author_id WHERE b.book_id = ?");
    $stmt->bind_param("i", $selected_book_id);
    $stmt->execute();
    $selected_book = $stmt->get_result()->fetch_assoc();
}

// Fetch recently issued books (last 20)
$recent_stmt = $conn->prepare("SELECT ib.issue_id, ib.issue_date, ib.due_date, ib.status, ib.fine_amount,
    b.book_name, b.isbn,
    u.name as student_name, u.student_id
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    JOIN users u ON ib.user_id = u.id
    ORDER BY ib.issue_id DESC LIMIT 20");
$recent_stmt->execute();
$recent_issued = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <div>
        <h4><i class="bi bi-journal-arrow-up me-2"></i>Issue Book</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Issue Book</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo sanitize($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Step 1: Search Student -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-person-search me-2"></i>Step 1: Select Student</h5>
            </div>
            <div class="card-body">
                <?php if (!$selected_student): ?>
                    <form method="GET" action="">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="search_student" placeholder="Search by Student ID, Name, Email, or Card Number" value="<?php echo sanitize($search_student); ?>">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>

                    <?php if (!empty($search_results)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Books</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($search_results as $s): ?>
                                        <tr>
                                            <td><?php echo sanitize($s['student_id']); ?></td>
                                            <td><?php echo sanitize($s['name']); ?></td>
                                            <td><?php echo sanitize($s['dept_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $s['current_books']; ?>/<?php echo $s['max_books_allowed']; ?></td>
                                            <td><span class="status-badge <?php echo $s['approval_status']; ?>"><?php echo ucfirst($s['approval_status']); ?></span></td>
                                            <td>
                                                <a href="?user_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">Select</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($search_student): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-person-x fs-1"></i>
                            <p class="mt-2">No students found matching your search.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?php echo sanitize($selected_student['name']); ?></h6>
                            <small class="text-muted">
                                ID: <?php echo sanitize($selected_student['student_id']); ?> |
                                Card: <?php echo sanitize($selected_student['card_number'] ?? 'N/A'); ?>
                            </small>
                        </div>
                        <a href="issue_book.php" class="btn btn-sm btn-outline-secondary">Change</a>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><small class="text-muted">Email:</small><br><?php echo sanitize($selected_student['email']); ?></div>
                        <div class="col-6"><small class="text-muted">Mobile:</small><br><?php echo sanitize($selected_student['mobile']); ?></div>
                        <div class="col-6"><small class="text-muted">Department:</small><br><?php echo sanitize($selected_student['dept_name'] ?? 'N/A'); ?></div>
                        <div class="col-6"><small class="text-muted">Class:</small><br><?php echo sanitize($selected_student['class_name'] ?? 'N/A'); ?></div>
                        <div class="col-6"><small class="text-muted">Books Issued:</small><br><?php echo $selected_student['current_books']; ?>/<?php echo $selected_student['max_books_allowed']; ?></div>
                        <div class="col-6"><small class="text-muted">Status:</small><br><span class="status-badge <?php echo $selected_student['approval_status']; ?>"><?php echo ucfirst($selected_student['approval_status']); ?></span></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Step 2: Search Book -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-book me-2"></i>Step 2: Select Book</h5>
            </div>
            <div class="card-body">
                <?php if (!$selected_student): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-arrow-left fs-1"></i>
                        <p class="mt-2">Please select a student first.</p>
                    </div>
                <?php elseif (!$selected_book): ?>
                    <form method="GET" action="">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="search_book" placeholder="Search by Book Name, ISBN, or Book No" value="<?php echo sanitize($book_search); ?>">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>

                    <?php if (!empty($book_results)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>Available</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($book_results as $b): ?>
                                        <tr>
                                            <td style="width:45px">
                                                <?php if (!empty($b['cover_image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $b['cover_image']; ?>" alt="" style="width:35px;height:50px;object-fit:cover;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                                                <?php else: ?>
                                                    <div style="width:35px;height:50px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:0.8rem;"><i class="bi bi-book"></i></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo sanitize($b['book_name']); ?></td>
                                            <td><?php echo sanitize($b['author_name'] ?? 'N/A'); ?></td>
                                            <td><span class="status-badge available"><?php echo $b['available_copies']; ?>/<?php echo $b['total_copies']; ?></span></td>
                                            <td>
                                                <a href="?user_id=<?php echo $selected_user_id; ?>&book_id=<?php echo $b['book_id']; ?>" class="btn btn-sm btn-primary">Select</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($book_search): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-book fs-1"></i>
                            <p class="mt-2">No available books found matching your search.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="d-flex align-items-center mb-3">
                    <?php if (!empty($selected_book['cover_image'])): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/covers/<?php echo $selected_book['cover_image']; ?>" alt="" style="width:50px;height:70px;object-fit:cover;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.15);margin-right:12px;">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><?php echo sanitize($selected_book['book_name']); ?></h6>
                            <small class="text-muted">ISBN: <?php echo sanitize($selected_book['isbn'] ?? 'N/A'); ?></small>
                        </div>
                        <a href="?user_id=<?php echo $selected_user_id; ?>" class="btn btn-sm btn-outline-secondary">Change</a>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><small class="text-muted">Author:</small><br><?php echo sanitize($selected_book['author_name'] ?? 'N/A'); ?></div>
                        <div class="col-6"><small class="text-muted">Available:</small><br><span class="status-badge available"><?php echo $selected_book['available_copies']; ?>/<?php echo $selected_book['total_copies']; ?></span></div>
                        <div class="col-6"><small class="text-muted">Rack:</small><br><?php echo sanitize($selected_book['rack_location'] ?? 'N/A'); ?></div>
                        <div class="col-6"><small class="text-muted">Price:</small><br><?php echo number_format($selected_book['book_price'], 2); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Issue Form -->
<?php if ($selected_student && $selected_book): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-check-circle me-2"></i>Step 3: Confirm Issue</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                <input type="hidden" name="book_id" value="<?php echo $selected_book_id; ?>">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Issue Date</label>
                        <input type="date" class="form-control" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-d', strtotime('+' . DEFAULT_BORROW_DAYS . ' days')); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Borrow Period</label>
                        <p class="form-control-plaintext"><?php echo DEFAULT_BORROW_DAYS; ?> days (Fine: <?php echo FINE_PER_DAY; ?>/day if overdue)</p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks (Optional)</label>
                    <textarea class="form-control" name="remarks" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="issue_book.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Issue Book</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Recently Issued Books -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Recently Issued Books</h5>
        <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent_issued)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-journal-x fs-1"></i>
                <p class="mt-2">No books have been issued yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Book</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_issued as $ib):
                            $status_labels = [ISSUE_PENDING => 'Pending', ISSUE_APPROVED => 'Approved', ISSUE_RETURNED => 'Returned', ISSUE_OVERDUE => 'Overdue'];
                            $status_classes = [ISSUE_PENDING => 'pending', ISSUE_APPROVED => 'issued', ISSUE_RETURNED => 'returned', ISSUE_OVERDUE => 'overdue'];
                            $label = $status_labels[$ib['status']] ?? 'Unknown';
                            $class = $status_classes[$ib['status']] ?? '';
                        ?>
                            <tr>
                                <td>
                                    <?php echo sanitize($ib['student_name']); ?><br>
                                    <small class="text-muted"><?php echo sanitize($ib['student_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($ib['book_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($ib['isbn'] ?? ''); ?></small>
                                </td>
                                <td><?php echo formatDate($ib['issue_date']); ?></td>
                                <td><?php echo formatDate($ib['due_date']); ?></td>
                                <td><span class="status-badge <?php echo $class; ?>"><?php echo $label; ?></span></td>
                                <td class="action-btns">
                                    <?php if (in_array($ib['status'], [ISSUE_APPROVED, ISSUE_OVERDUE])): ?>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/issued_books.php" class="d-inline" onsubmit="return confirm('Mark this book as returned?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="issue_id" value="<?php echo $ib['issue_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Return"><i class="bi bi-arrow-return-left"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($ib['status'] == ISSUE_PENDING): ?>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/issued_books.php" class="d-inline" onsubmit="return confirm('Approve this issue?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="issue_id" value="<?php echo $ib['issue_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?php echo ADMIN_URL; ?>/issued_books.php" class="btn btn-sm btn-outline-info" title="View Details"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
