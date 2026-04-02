<?php
$page_title = "Authors";
require_once __DIR__ . '/includes/admin_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/authors.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $author_name = trim($_POST['author_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (empty($author_name)) {
            setFlashMessage('danger', 'Author name is required.');
            redirect(ADMIN_URL . '/authors.php');
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO authors (author_name, bio) VALUES (?, ?)");
            $stmt->bind_param("ss", $author_name, $bio);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_author', "Added author: {$author_name}");
                setFlashMessage('success', 'Author added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add author.');
            }
        } else {
            $author_id = (int)($_POST['author_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE authors SET author_name = ?, bio = ? WHERE author_id = ?");
            $stmt->bind_param("ssi", $author_name, $bio, $author_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_author', "Updated author ID: {$author_id}");
                setFlashMessage('success', 'Author updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update author.');
            }
        }
        redirect(ADMIN_URL . '/authors.php');
    }

    if ($action === 'delete') {
        $author_id = (int)($_POST['author_id'] ?? 0);

        // Check if author has books
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM books WHERE author_id = ?");
        $stmt->bind_param("i", $author_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($count > 0) {
            setFlashMessage('danger', "Cannot delete author. It has {$count} book(s) associated with it.");
        } else {
            $stmt = $conn->prepare("DELETE FROM authors WHERE author_id = ?");
            $stmt->bind_param("i", $author_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'delete_author', "Deleted author ID: {$author_id}");
                setFlashMessage('success', 'Author deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete author.');
            }
        }
        redirect(ADMIN_URL . '/authors.php');
    }
}

// Determine if editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$add_new = isset($_GET['action']) && $_GET['action'] === 'add';
$edit_author = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM authors WHERE author_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_author = $stmt->get_result()->fetch_assoc();
}

// Fetch authors with book count
$stmt = $conn->prepare("
    SELECT a.*, (SELECT COUNT(*) FROM books b WHERE b.author_id = a.author_id) as book_count
    FROM authors a
    ORDER BY a.author_name ASC
");
$stmt->execute();
$authors = $stmt->get_result();

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-person-badge me-2"></i>Authors</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Authors</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/authors.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Author</a>
    </div>
</div>

<?php if ($add_new || $edit_author): ?>
<!-- Add/Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-<?php echo $edit_author ? 'pencil' : 'plus-lg'; ?> me-2"></i><?php echo $edit_author ? 'Edit Author' : 'Add New Author'; ?></h5>
        <a href="<?php echo ADMIN_URL; ?>/authors.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo ADMIN_URL; ?>/authors.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_author ? 'edit' : 'add'; ?>">
            <?php if ($edit_author): ?>
                <input type="hidden" name="author_id" value="<?php echo $edit_author['author_id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="author_name" class="form-label">Author Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="author_name" name="author_name" value="<?php echo sanitize($edit_author['author_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="bio" class="form-label">Bio</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo sanitize($edit_author['bio'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> <?php echo $edit_author ? 'Update Author' : 'Add Author'; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Authors Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Bio</th>
                        <th>Books</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($authors->num_rows > 0): ?>
                        <?php $i = 1; while ($author = $authors->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo sanitize($author['author_name']); ?></strong></td>
                                <td><?php echo sanitize(mb_strimwidth($author['bio'] ?? '-', 0, 80, '...')); ?></td>
                                <td><span class="badge bg-info"><?php echo $author['book_count']; ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?php echo ADMIN_URL; ?>/authors.php?edit=<?php echo $author['author_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/authors.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this author?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="author_id" value="<?php echo $author['author_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-person-badge"></i><h5>No Authors</h5><p>No authors have been added yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
