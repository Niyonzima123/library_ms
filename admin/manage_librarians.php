<?php
require_once 'includes/admin_header.php';

// Admin-only access guard
if ($_SESSION['role'] !== ROLE_ADMIN) {
    setFlashMessage('danger', 'Access denied. Administrator privileges required.');
    redirect(ADMIN_URL . '/dashboard.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/manage_librarians.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $mobile = sanitize($_POST['mobile'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $role = sanitize($_POST['role'] ?? 'librarian');
            $shift_preference = sanitize($_POST['shift_preference'] ?? '');
            $hire_date = sanitize($_POST['hire_date'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                setFlashMessage('error', 'Name, email, and password are required.');
                redirect(ADMIN_URL . '/manage_librarians.php');
            }

            if (!in_array($role, ['librarian', 'admin'])) {
                $role = 'librarian';
            }

            $check = $conn->prepare("SELECT id FROM admins WHERE email = ? AND deleted_at IS NULL");
            $check->bind_param('s', $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $check->close();
                setFlashMessage('error', 'An account with this email already exists.');
                redirect(ADMIN_URL . '/manage_librarians.php');
            }
            $check->close();

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (name, email, password, mobile, phone, address, role, shift_preference, hire_date, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param('sssssssss', $name, $email, $hashed, $mobile, $phone, $address, $role, $shift_preference, $hire_date);

            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();
                $auth->logActivity('admin', $_SESSION['user_id'], 'add_librarian', 'Added new librarian: ' . $name);
                setFlashMessage('success', 'Librarian added successfully.');
            } else {
                $stmt->close();
                setFlashMessage('error', 'Failed to add librarian.');
            }
            redirect(ADMIN_URL . '/manage_librarians.php');
            break;

        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $mobile = sanitize($_POST['mobile'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $role = sanitize($_POST['role'] ?? 'librarian');
            $shift_preference = sanitize($_POST['shift_preference'] ?? '');
            $hire_date = sanitize($_POST['hire_date'] ?? '');

            if (empty($name) || empty($email) || $id <= 0) {
                setFlashMessage('error', 'Name and email are required.');
                redirect(ADMIN_URL . '/manage_librarians.php');
            }

            if (!in_array($role, ['librarian', 'admin'])) {
                $role = 'librarian';
            }

            $check = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ? AND deleted_at IS NULL");
            $check->bind_param('si', $email, $id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $check->close();
                setFlashMessage('error', 'Another account with this email already exists.');
                redirect(ADMIN_URL . '/manage_librarians.php');
            }
            $check->close();

            $password = $_POST['password'] ?? '';
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET name=?, email=?, password=?, mobile=?, phone=?, address=?, role=?, shift_preference=?, hire_date=? WHERE id=?");
                $stmt->bind_param('sssssssssi', $name, $email, $hashed, $mobile, $phone, $address, $role, $shift_preference, $hire_date, $id);
            } else {
                $stmt = $conn->prepare("UPDATE admins SET name=?, email=?, mobile=?, phone=?, address=?, role=?, shift_preference=?, hire_date=? WHERE id=?");
                $stmt->bind_param('ssssssssi', $name, $email, $mobile, $phone, $address, $role, $shift_preference, $hire_date, $id);
            }

            if ($stmt->execute()) {
                $stmt->close();
                $auth->logActivity('admin', $_SESSION['user_id'], 'update_librarian', 'Updated librarian ID: ' . $id);
                setFlashMessage('success', 'Librarian updated successfully.');
            } else {
                $stmt->close();
                setFlashMessage('error', 'Failed to update librarian.');
            }
            redirect(ADMIN_URL . '/manage_librarians.php');
            break;

        case 'activate':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE admins SET is_active = 1, deleted_at = NULL WHERE id = ?");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'activate_librarian', 'Activated librarian ID: ' . $id);
                    setFlashMessage('success', 'Librarian activated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to activate librarian.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/manage_librarians.php');
            break;

        case 'deactivate':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE admins SET is_active = 0 WHERE id = ?");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'deactivate_librarian', 'Deactivated librarian ID: ' . $id);
                    setFlashMessage('success', 'Librarian deactivated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to deactivate librarian.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/manage_librarians.php');
            break;

        case 'soft_delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE admins SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $auth->logActivity('admin', $_SESSION['user_id'], 'delete_librarian', 'Soft deleted librarian ID: ' . $id);
                    setFlashMessage('success', 'Librarian deleted successfully.');
                } else {
                    setFlashMessage('error', 'Failed to delete librarian.');
                }
                $stmt->close();
            }
            redirect(ADMIN_URL . '/manage_librarians.php');
            break;
    }
}

// Fetch filters
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$where = "role = 'librarian'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if ($status === 'active') {
    $where .= " AND is_active = 1 AND deleted_at IS NULL";
} elseif ($status === 'inactive') {
    $where .= " AND is_active = 0 AND deleted_at IS NULL";
} elseif ($status === 'deleted') {
    $where .= " AND deleted_at IS NOT NULL";
} else {
    $where .= " AND deleted_at IS NULL";
}

// Stats
$totalResult = $conn->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'librarian' AND deleted_at IS NULL");
$totalLibrarians = $totalResult->fetch_assoc()['cnt'];

$activeResult = $conn->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'librarian' AND is_active = 1 AND deleted_at IS NULL");
$activeLibrarians = $activeResult->fetch_assoc()['cnt'];

$inactiveResult = $conn->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'librarian' AND is_active = 0 AND deleted_at IS NULL");
$inactiveLibrarians = $inactiveResult->fetch_assoc()['cnt'];

$deletedResult = $conn->query("SELECT COUNT(*) as cnt FROM admins WHERE role = 'librarian' AND deleted_at IS NOT NULL");
$deletedLibrarians = $deletedResult->fetch_assoc()['cnt'];

// Fetch librarians
$sql = "SELECT * FROM admins WHERE {$where} ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$librarians = [];
while ($row = $result->fetch_assoc()) {
    $librarians[] = $row;
}
$stmt->close();

$csrfToken = generateCSRFToken();
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Librarians</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#librarianModal" onclick="resetForm()">
            <i class="fas fa-plus"></i> Add Librarian
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Librarians</h5>
                    <p class="card-text display-6"><?php echo $totalLibrarians; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Active</h5>
                    <p class="card-text display-6"><?php echo $activeLibrarians; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Inactive</h5>
                    <p class="card-text display-6"><?php echo $inactiveLibrarians; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Deleted</h5>
                    <p class="card-text display-6"><?php echo $deletedLibrarians; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or mobile..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All (Non-Deleted)</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="deleted" <?php echo $status === 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i> Filter</button>
                    <a href="manage_librarians.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Librarians Table -->
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Shift Preference</th>
                        <th>Hire Date</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($librarians) === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">No librarians found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($librarians as $lib): ?>
                            <tr>
                                <td><?php echo $lib['id']; ?></td>
                                <td><?php echo htmlspecialchars($lib['name']); ?></td>
                                <td><?php echo htmlspecialchars($lib['email']); ?></td>
                                <td><?php echo htmlspecialchars($lib['mobile'] ?? ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $lib['role'] === 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($lib['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($lib['deleted_at']): ?>
                                        <span class="badge bg-dark">Deleted</span>
                                    <?php elseif ($lib['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($lib['shift_preference'] ?? ''); ?></td>
                                <td><?php echo $lib['hire_date'] ? formatDate($lib['hire_date']) : ''; ?></td>
                                <td><?php echo $lib['created_at'] ? formatDate($lib['created_at']) : ''; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info" title="View" data-bs-toggle="modal" data-bs-target="#viewModal" onclick="viewLibrarian(<?php echo htmlspecialchars(json_encode($lib)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$lib['deleted_at']): ?>
                                            <button class="btn btn-warning" title="Edit" data-bs-toggle="modal" data-bs-target="#librarianModal" onclick="editLibrarian(<?php echo htmlspecialchars(json_encode($lib)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($lib['is_active']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this librarian?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="id" value="<?php echo $lib['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary" title="Deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Activate this librarian?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="id" value="<?php echo $lib['id']; ?>">
                                                    <button type="submit" class="btn btn-success" title="Activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this librarian? This action can be reversed by an administrator.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="id" value="<?php echo $lib['id']; ?>">
                                                <button type="submit" class="btn btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="librarianModal" tabindex="-1" aria-labelledby="librarianModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="librarianForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="librarianModalLabel">Add Librarian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="formName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="formEmail" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="password" name="password" id="formPassword" class="form-control">
                            <small class="text-muted" id="passwordHint" style="display:none;">Leave blank to keep current password.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="formRole" class="form-select">
                                <option value="librarian">Librarian</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" id="formMobile" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="formPhone" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="formAddress" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shift Preference</label>
                            <select name="shift_preference" id="formShift" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="evening">Evening</option>
                                <option value="night">Night</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" id="formHireDate" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="formSubmitBtn">Add Librarian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">Librarian Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-borderless">
                    <tr><th>ID:</th><td id="viewId"></td></tr>
                    <tr><th>Name:</th><td id="viewName"></td></tr>
                    <tr><th>Email:</th><td id="viewEmail"></td></tr>
                    <tr><th>Mobile:</th><td id="viewMobile"></td></tr>
                    <tr><th>Phone:</th><td id="viewPhone"></td></tr>
                    <tr><th>Address:</th><td id="viewAddress"></td></tr>
                    <tr><th>Role:</th><td id="viewRole"></td></tr>
                    <tr><th>Status:</th><td id="viewStatus"></td></tr>
                    <tr><th>Shift:</th><td id="viewShift"></td></tr>
                    <tr><th>Hire Date:</th><td id="viewHireDate"></td></tr>
                    <tr><th>Created:</th><td id="viewCreated"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('librarianModalLabel').textContent = 'Add Librarian';
    document.getElementById('formSubmitBtn').textContent = 'Add Librarian';
    document.getElementById('librarianForm').reset();
    document.getElementById('formPassword').required = true;
    document.getElementById('passwordRequired').style.display = '';
    document.getElementById('passwordHint').style.display = 'none';
}

function editLibrarian(lib) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = lib.id;
    document.getElementById('librarianModalLabel').textContent = 'Edit Librarian';
    document.getElementById('formSubmitBtn').textContent = 'Update Librarian';
    document.getElementById('formName').value = lib.name;
    document.getElementById('formEmail').value = lib.email;
    document.getElementById('formPassword').value = '';
    document.getElementById('formPassword').required = false;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHint').style.display = '';
    document.getElementById('formMobile').value = lib.mobile || '';
    document.getElementById('formPhone').value = lib.phone || '';
    document.getElementById('formAddress').value = lib.address || '';
    document.getElementById('formRole').value = lib.role || 'librarian';
    document.getElementById('formShift').value = lib.shift_preference || '';
    document.getElementById('formHireDate').value = lib.hire_date || '';
}

function viewLibrarian(lib) {
    document.getElementById('viewId').textContent = lib.id;
    document.getElementById('viewName').textContent = lib.name;
    document.getElementById('viewEmail').textContent = lib.email;
    document.getElementById('viewMobile').textContent = lib.mobile || '-';
    document.getElementById('viewPhone').textContent = lib.phone || '-';
    document.getElementById('viewAddress').textContent = lib.address || '-';
    document.getElementById('viewRole').textContent = lib.role ? lib.role.charAt(0).toUpperCase() + lib.role.slice(1) : '-';
    if (lib.deleted_at) {
        document.getElementById('viewStatus').innerHTML = '<span class="badge bg-dark">Deleted</span>';
    } else if (lib.is_active == 1) {
        document.getElementById('viewStatus').innerHTML = '<span class="badge bg-success">Active</span>';
    } else {
        document.getElementById('viewStatus').innerHTML = '<span class="badge bg-secondary">Inactive</span>';
    }
    document.getElementById('viewShift').textContent = lib.shift_preference || '-';
    document.getElementById('viewHireDate').textContent = lib.hire_date || '-';
    document.getElementById('viewCreated').textContent = lib.created_at || '-';
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
