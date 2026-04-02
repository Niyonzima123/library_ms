<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(ADMIN_URL . '/dashboard.php');
    } else {
        redirect(STUDENT_URL . '/dashboard.php');
    }
}

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;
$token_valid = false;
$email = '';
$user_type = '';

if (empty($token)) {
    $errors[] = 'No reset token provided. Please request a new password reset.';
} else {
    // Validate token
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $errors[] = 'Invalid or expired reset token. Please request a new password reset.';
    } else {
        $reset_record = $result->fetch_assoc();
        $token_valid = true;
        $email = $reset_record['email'];
        $user_type = $reset_record['user_type'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(SITE_URL . '/reset_password.php?token=' . $token);
    }

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $errors = ['Password must be at least 6 characters.'];
    } elseif ($password !== $confirm_password) {
        $errors = ['Passwords do not match.'];
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($user_type === 'student') {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        } else {
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
        }
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            // Delete the used token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();

            // Delete all other tokens for this email
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = ?");
            $stmt->bind_param("ss", $email, $user_type);
            $stmt->execute();

            $success = true;
        } else {
            $errors = ['Failed to update password. Please try again.'];
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">

    <!-- Landing Navbar -->
    <nav class="landing-navbar">
        <div class="navbar-container">
            <a href="<?php echo SITE_URL; ?>" class="navbar-brand">
                <i class="bi bi-book-half"></i>
                University Library
            </a>
            <div class="d-flex align-items-center gap-4">
                <ul class="nav-links mb-0">
                    <li><a href="<?php echo SITE_URL; ?>">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/#features">Features</a></li>
                </ul>
                <div class="landing-cta">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm">Login</a>
                    <a href="<?php echo SITE_URL; ?>/login.php?register=1" class="btn btn-primary btn-sm">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Auth Content -->
    <div style="width:100%; display:flex; min-height:100vh; padding-top:70px;">
        <!-- Left Side - Form -->
        <div class="auth-left" style="overflow-y:auto; align-items:flex-start; padding-top:2rem;">
            <div class="auth-card" style="max-width:480px;">
                <div class="auth-header">
                    <div class="logo">
                        <i class="bi bi-lock-fill"></i>
                    </div>
                    <h3><?php echo $success ? 'Password Reset!' : ($token_valid ? 'Set New Password' : 'Reset Error'); ?></h3>
                    <p><?php echo $success ? 'Your password has been updated successfully' : ($token_valid ? 'Enter your new password below' : 'There was a problem with your reset token'); ?></p>
                </div>

                <?php if ($success): ?>
                <!-- Success Message -->
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Password Updated</h5>
                    <p class="mb-0">Your password has been changed successfully. You can now log in with your new password.</p>
                </div>

                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                </a>

                <?php elseif (!empty($errors)): ?>
                <!-- Error Messages -->
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo sanitize($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <?php if (!$token_valid): ?>
                <a href="<?php echo SITE_URL; ?>/forgot_password.php" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Request New Reset Token
                </a>
                <?php endif; ?>

                <?php elseif ($token_valid): ?>
                <!-- Reset Form -->
                <div class="alert alert-info mb-3" role="alert">
                    <i class="bi bi-info-circle me-1"></i>
                    Resetting password for: <strong><?php echo sanitize($email); ?></strong>
                    <span class="badge bg-<?php echo $user_type === 'admin' ? 'danger' : 'primary'; ?> ms-1">
                        <?php echo ucfirst($user_type); ?>
                    </span>
                </div>

                <form method="POST" action="" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                        <i class="bi bi-check-lg me-2"></i>Reset Password
                    </button>
                </form>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="text-muted" style="font-size: 0.9rem;">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Side - Branding Panel -->
        <div class="auth-right">
            <div class="auth-right-content">
                <i class="bi bi-book-half"></i>
                <h2>University Library</h2>
                <p>Access thousands of books, manage your borrowings, and explore our digital collection from anywhere.</p>
                <div style="margin-top: 2rem; display: flex; gap: 2rem; justify-content: center;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700;">10,000+</div>
                        <div style="font-size: 0.85rem; opacity: 0.8;">Books</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700;">500+</div>
                        <div style="font-size: 0.85rem; opacity: 0.8;">Students</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.8rem; font-weight: 700;">50+</div>
                        <div style="font-size: 0.85rem; opacity: 0.8;">Categories</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
