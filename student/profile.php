<?php
$page_title = 'My Profile';
include 'includes/student_header.php';

$user_id = $_SESSION['user_id'];

// Fetch full user data
$stmt = $conn->prepare("SELECT u.*, d.dept_name, c.class_name
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$profile_error = '';
$profile_success = '';
$password_error = '';
$password_success = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $profile_error = 'Invalid CSRF token.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $mobile = sanitize($_POST['mobile'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $card_number = sanitize(trim($_POST['card_number'] ?? ''));

        if (empty($name)) {
            $profile_error = 'Name is required.';
        } elseif (!empty($card_number)) {
            // Check if card number is already used by another student
            $stmt = $conn->prepare("SELECT id FROM users WHERE card_number = ? AND id != ?");
            $stmt->bind_param("si", $card_number, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $profile_error = 'This Card Number is already in use by another student. Please choose a different one.';
            }
        }

        if (empty($profile_error)) {
            // Handle profile image upload
            $profile_image = $user['profile_image'];
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $profile_error = 'Invalid image format. Allowed: JPG, PNG, GIF, WebP.';
                } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
                    $profile_error = 'Image size must be less than 2MB.';
                } else {
                    $upload_dir = dirname(__DIR__) . '/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $new_name = 'student_' . $user_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_name)) {
                        // Remove old image
                        if ($profile_image && file_exists($upload_dir . $profile_image)) {
                            unlink($upload_dir . $profile_image);
                        }
                        $profile_image = $new_name;
                    } else {
                        $profile_error = 'Failed to upload image.';
                    }
                }
            }

            if (empty($profile_error)) {
                // Sync card_number with student_id
                $student_id_sync = !empty($card_number) ? $card_number : $user['student_id'];
                $stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ?, address = ?, card_number = ?, student_id = ?, profile_image = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $name, $mobile, $address, $card_number, $student_id_sync, $profile_image, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['name'] = $name;
                    $_SESSION['profile_image'] = $profile_image;
                    $profile_success = 'Profile updated successfully.';
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT u.*, d.dept_name, c.class_name FROM users u LEFT JOIN departments d ON u.dept_id = d.dept_id LEFT JOIN classes c ON u.class_id = c.class_id WHERE u.id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $profile_error = 'Failed to update profile.';
                }
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $password_error = 'Invalid CSRF token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $password_error = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password'])) {
            $password_error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $password_error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $password_error = 'New password and confirmation do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $password_success = 'Password changed successfully.';
            } else {
                $password_error = 'Failed to change password.';
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h4>My Profile</h4>
        <p class="text-muted mb-0">View and manage your account details</p>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="mx-auto mb-3" style="width:130px;height:160px;border-radius:8px;border:3px solid #e0e0e0;background:#f5f5f5;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $user['profile_image']; ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle" style="font-size:4rem;color:white;"></i>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?php echo sanitize($user['name']); ?></h5>
                <p class="text-muted mb-1" style="font-size:0.85rem;">
                    <i class="bi bi-person-badge me-1"></i>Reg: <strong><?php echo sanitize($user['student_id']); ?></strong>
                </p>
                <p class="text-muted mb-3" style="font-size:0.85rem;"><?php echo sanitize($user['email']); ?></p>

                <?php if ($user['approval_status'] === 'approved'): ?>
                    <span class="badge bg-success mb-3" style="font-size:0.8rem;padding:6px 14px;"><i class="bi bi-check-circle"></i> Approved</span>
                <?php elseif ($user['approval_status'] === 'pending'): ?>
                    <span class="badge bg-warning mb-3" style="font-size:0.8rem;padding:6px 14px;"><i class="bi bi-hourglass-split"></i> Pending Approval</span>
                <?php else: ?>
                    <span class="badge bg-danger mb-3" style="font-size:0.8rem;padding:6px 14px;"><i class="bi bi-x-circle"></i> Rejected</span>
                <?php endif; ?>

                <hr>
                <div class="text-start">
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.88rem;">
                        <span class="text-muted"><i class="bi bi-building"></i> Department</span>
                        <strong><?php echo sanitize($user['dept_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.88rem;">
                        <span class="text-muted"><i class="bi bi-mortarboard"></i> Class</span>
                        <strong><?php echo sanitize($user['class_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.88rem;">
                        <span class="text-muted"><i class="bi bi-credit-card"></i> Card Number</span>
                        <strong>
                            <?php echo sanitize($user['card_number'] ?? 'Not Set'); ?>
                            <a href="#edit-profile-section" class="ms-1 text-primary" style="font-size:0.75rem;" title="Change Card Number"><i class="bi bi-pencil-square"></i></a>
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2" style="font-size:0.88rem;">
                        <span class="text-muted"><i class="bi bi-phone"></i> Mobile</span>
                        <strong><?php echo sanitize($user['mobile'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:0.88rem;">
                        <span class="text-muted"><i class="bi bi-book"></i> Max Books</span>
                        <strong><?php echo $user['max_books_allowed']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="card mb-4" id="edit-profile-section">
            <div class="card-header">
                <h5><i class="bi bi-pencil-square"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <?php if ($profile_error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $profile_error; ?></div>
                <?php endif; ?>
                <?php if ($profile_success): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $profile_success; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                            <small class="form-text">Email cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" class="form-control" name="mobile" value="<?php echo sanitize($user['mobile']); ?>" placeholder="Enter mobile number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Card Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                <input type="text" class="form-control" name="card_number" value="<?php echo sanitize($user['card_number'] ?? ''); ?>" placeholder="Enter your preferred card number">
                            </div>
                            <small class="form-text">Choose a unique card number for identification. Leave blank to keep current.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Profile Image</label>
                            <input type="file" class="form-control" name="profile_image" accept="image/*">
                            <small class="form-text">Max 2MB. Formats: JPG, PNG, GIF, WebP</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3" placeholder="Enter your address"><?php echo sanitize($user['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Update Profile</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-shield-lock"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <?php if ($password_error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $password_error; ?></div>
                <?php endif; ?>
                <?php if ($password_success): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $password_success; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="change_password" value="1">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                            <small class="form-text">Minimum 6 characters.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> Change Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/student_footer.php'; ?>
