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

$errors = [];
$success = false;
$reset_token = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(SITE_URL . '/forgot_password.php');
    }

    $email = sanitize($_POST['email'] ?? '');
    $user_type = sanitize($_POST['user_type'] ?? 'student');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!in_array($user_type, ['student', 'admin', 'librarian'])) {
        $errors[] = 'Invalid account type.';
    }

    if (empty($errors)) {
        // Check if email exists in the appropriate table
        if ($user_type === 'student') {
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        } else {
            $stmt = $conn->prepare("SELECT id, name FROM admins WHERE email = ? AND is_active = 1 AND deleted_at IS NULL");
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = 'No account found with that email address.';
        } else {
            $user = $result->fetch_assoc();
            $user_name = $user['name'];

            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Invalidate any existing tokens for this email
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND user_type = ?");
            $stmt->bind_param("ss", $email, $user_type);
            $stmt->execute();

            // Store new token
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $reset_token, $user_type, $expires_at);

            if ($stmt->execute()) {
                $success = true;
                $reset_link = SITE_URL . '/reset_password.php?token=' . $reset_token;
            } else {
                $errors[] = 'Failed to generate reset token. Please try again.';
            }
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
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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
                        <i class="bi bi-key-fill"></i>
                    </div>
                    <h3>Forgot Password</h3>
                    <p>Enter your email to receive a password reset token</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                        <li><?php echo sanitize($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <!-- Success: Show Token -->
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Reset Token Generated</h5>
                    <p class="mb-2">A password reset token has been generated for <strong><?php echo sanitize($_POST['email'] ?? ''); ?></strong>.</p>
                    <p class="mb-0 small">This token expires in 1 hour.</p>
                </div>

                <div class="card border-primary mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-shield-lock me-1"></i> Your Reset Token
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Since email is not configured, use this token directly:</p>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="resetToken" value="<?php echo sanitize($reset_token); ?>" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyToken()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>

                <a href="<?php echo $reset_link; ?>" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-arrow-right me-2"></i>Proceed to Reset Password
                </a>

                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/forgot_password.php" class="text-muted" style="font-size: 0.9rem;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Request another token
                    </a>
                </div>

                <?php else: ?>
                <!-- Request Form -->
                <form method="POST" action="" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Account Type -->
                    <div class="mb-3">
                        <label class="form-label">Account Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="user_type" id="typeStudent" value="student" checked>
                                <label class="form-check-label" for="typeStudent">
                                    <i class="bi bi-mortarboard me-1"></i> Student
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="user_type" id="typeAdmin" value="admin">
                                <label class="form-check-label" for="typeAdmin">
                                    <i class="bi bi-shield-lock me-1"></i> Admin
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="user_type" id="typeLibrarian" value="librarian">
                                <label class="form-check-label" for="typeLibrarian">
                                    <i class="bi bi-book me-1"></i> Librarian
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your registered email" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                        <i class="bi bi-send me-2"></i>Send Reset Token
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
function copyToken() {
    const tokenInput = document.getElementById('resetToken');
    tokenInput.select();
    navigator.clipboard.writeText(tokenInput.value);
}
</script>
</body>
</html>
