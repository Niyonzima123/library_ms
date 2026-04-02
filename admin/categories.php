<?php
$page_title = "Categories";
require_once __DIR__ . '/includes/admin_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(ADMIN_URL . '/categories.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $cat_name = trim($_POST['cat_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($cat_name)) {
            setFlashMessage('danger', 'Category name is required.');
            redirect(ADMIN_URL . '/categories.php');
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO categories (cat_name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $cat_name, $description);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_category', "Added category: {$cat_name}");
                setFlashMessage('success', 'Category added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add category.');
            }
        } else {
            $cat_id = (int)($_POST['cat_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE categories SET cat_name = ?, description = ? WHERE cat_id = ?");
            $stmt->bind_param("ssi", $cat_name, $description, $cat_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'edit_category', "Updated category ID: {$cat_id}");
                setFlashMessage('success', 'Category updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update category.');
            }
        }
        redirect(ADMIN_URL . '/categories.php');
    }

    if ($action === 'delete') {
        $cat_id = (int)($_POST['cat_id'] ?? 0);

        // Check if category has books
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM books WHERE cat_id = ?");
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($count > 0) {
            setFlashMessage('danger', "Cannot delete category. It has {$count} book(s) associated with it.");
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE cat_id = ?");
            $stmt->bind_param("i", $cat_id);
            if ($stmt->execute()) {
                $auth->logActivity('admin', $_SESSION['user_id'], 'delete_category', "Deleted category ID: {$cat_id}");
                setFlashMessage('success', 'Category deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete category.');
            }
        }
        redirect(ADMIN_URL . '/categories.php');
    }
}

// Determine if editing
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$add_new = isset($_GET['action']) && $_GET['action'] === 'add';
$edit_category = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE cat_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_category = $stmt->get_result()->fetch_assoc();
}

// Fetch categories with book count
$stmt = $conn->prepare("
    SELECT c.*, (SELECT COUNT(*) FROM books b WHERE b.cat_id = c.cat_id) as book_count
    FROM categories c
    ORDER BY c.cat_name ASC
");
$stmt->execute();
$categories = $stmt->get_result();

$csrf_token = generateCSRFToken();
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h4><i class="bi bi-tags me-2"></i>Categories</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Categories</li>
            </ol>
        </nav>
    </div>
    <div class="page-header-actions">
        <a href="<?php echo ADMIN_URL; ?>/categories.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Category</a>
    </div>
</div>

<?php if ($add_new || $edit_category): ?>
<!-- Add/Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-<?php echo $edit_category ? 'pencil' : 'plus-lg'; ?> me-2"></i><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h5>
        <a href="<?php echo ADMIN_URL; ?>/categories.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo ADMIN_URL; ?>/categories.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit' : 'add'; ?>">
            <?php if ($edit_category): ?>
                <input type="hidden" name="cat_id" value="<?php echo $edit_category['cat_id']; ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cat_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cat_name" name="cat_name" value="<?php echo sanitize($edit_category['cat_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?php echo sanitize($edit_category['description'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Categories Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Books</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories->num_rows > 0): ?>
                        <?php $i = 1; while ($cat = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo sanitize($cat['cat_name']); ?></strong></td>
                                <td><?php echo sanitize($cat['description'] ?? '-'); ?></td>
                                <td><span class="badge bg-info"><?php echo $cat['book_count']; ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?php echo ADMIN_URL; ?>/categories.php?edit=<?php echo $cat['cat_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="<?php echo ADMIN_URL; ?>/categories.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="cat_id" value="<?php echo $cat['cat_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-tags"></i><h5>No Categories</h5><p>No categories have been added yet.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
