<?php
$page_title = 'Profile';
include __DIR__ . '/includes/admin_header.php';

$admin_id = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Fetch current admin data
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    setFlashMessage('danger', 'Admin account not found.');
    redirect(ADMIN_URL . '/dashboard.php');
}

$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(ADMIN_URL . '/profile.php');
    }

    $action = $_POST['action'] ?? '';

    // Update Profile Info
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        if (empty($name)) {
            $errors[] = 'Name is required.';
        }

        if (empty($mobile)) {
            $errors[] = 'Mobile number is required.';
        }

        // Handle profile image upload
        $profile_image = $admin['profile_image'];
        if (!empty($_FILES['profile_image']['name'])) {
            $file = $_FILES['profile_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) {
                $errors[] = 'Invalid image type. Allowed: ' . implode(', ', $allowed);
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = 'File size exceeds the maximum limit.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload failed.';
            } else {
                $new_name = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
                $destination = UPLOAD_DIR . $new_name;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old image if exists and not default
                    if ($admin['profile_image'] && file_exists(UPLOAD_DIR . $admin['profile_image'])) {
                        unlink(UPLOAD_DIR . $admin['profile_image']);
                    }
                    $profile_image = $new_name;
                } else {
                    $errors[] = 'Failed to save uploaded image.';
                }
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE admins SET name = ?, mobile = ?, profile_image = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $mobile, $profile_image, $admin_id);
            $stmt->execute();

            // Update session variables
            $_SESSION['name'] = $name;
            $_SESSION['profile_image'] = $profile_image;

            // Refresh admin data
            $admin['name'] = $name;
            $admin['mobile'] = $mobile;
            $admin['profile_image'] = $profile_image;

            setFlashMessage('success', 'Profile updated successfully.');
            redirect(ADMIN_URL . '/profile.php');
        }
    }

    // Change Password
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current_password, $admin['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $admin_id);
            $stmt->execute();

            $auth->logActivity('admin', $admin_id, 'password_change', 'Admin changed password');

            setFlashMessage('success', 'Password changed successfully.');
            redirect(ADMIN_URL . '/profile.php');
        }
    }
}
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-person me-2"></i>Profile</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Profile</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sanitize($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Info Card -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if ($admin['profile_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $admin['profile_image']; ?>" alt="Profile" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle display-1 text-muted"></i>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?php echo sanitize($admin['name']); ?></h5>
                <span class="badge bg-<?php echo $admin['role'] === 'admin' ? 'danger' : 'info'; ?>"><?php echo ucfirst($admin['role']); ?></span>
                <hr>
                <div class="text-start">
                    <p class="mb-2"><i class="bi bi-envelope me-2 text-muted"></i><?php echo sanitize($admin['email']); ?></p>
                    <p class="mb-2"><i class="bi bi-phone me-2 text-muted"></i><?php echo sanitize($admin['mobile']); ?></p>
                    <p class="mb-0"><i class="bi bi-toggle-on me-2 text-muted"></i>Status: <span class="badge bg-<?php echo $admin['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($admin['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" value="<?php echo sanitize($admin['email']); ?>" disabled>
                            <small class="text-muted">Email cannot be changed.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo sanitize($admin['mobile']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <small class="text-muted">Allowed: JPG, PNG, GIF, WebP. Max 50MB.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lock me-2"></i>Change Password</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key me-1"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
