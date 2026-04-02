<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(ADMIN_URL . '/dashboard.php');
    } else {
        redirect(STUDENT_URL . '/dashboard.php');
    }
}

// Fetch departments for dropdown
$departments = [];
$dept_result = $conn->query("SELECT dept_id, dept_name, dept_code FROM departments ORDER BY dept_name");
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(SITE_URL . '/register.php');
    }

    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $mobile = sanitize($_POST['mobile'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $dept_id = (int)($_POST['dept_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (empty($mobile)) $errors[] = 'Mobile number is required.';
    if ($dept_id === 0) $errors[] = 'Please select a department.';
    if ($class_id === 0) $errors[] = 'Please select a class.';

    if (!empty($errors)) {
        setFlashMessage('danger', implode('<br>', $errors));
        redirect(SITE_URL . '/register.php');
    }

    $result = $auth->registerStudent([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'mobile' => $mobile,
        'address' => $address,
        'dept_id' => $dept_id,
        'class_id' => $class_id,
    ]);

    if ($result['success']) {
        setFlashMessage('success', $result['message']);
        redirect(SITE_URL . '/login.php');
    } else {
        setFlashMessage('danger', $result['message']);
        redirect(SITE_URL . '/register.php');
    }
}

$flash = getFlashMessage();
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-page">
    <!-- Left Side - Registration Form -->
    <div class="auth-left">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <div class="logo">
                    <i class="bi bi-book-half"></i>
                </div>
                <h3>Create Account</h3>
                <p>Register as a student to access the library</p>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter your full name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input type="tel" class="form-control" id="mobile" name="mobile" placeholder="Enter your mobile number" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter your address"></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <select class="form-select" id="dept_id" name="dept_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['dept_id']; ?>"><?php echo sanitize($dept['dept_name']); ?> (<?php echo sanitize($dept['dept_code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Department First</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <i class="bi bi-person-plus me-2"></i>Register
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-muted mb-0" style="font-size: 0.9rem;">
                    Already have an account? <a href="<?php echo SITE_URL; ?>/login.php">Login here</a>
                </p>
            </div>

            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>" class="text-muted" style="font-size: 0.85rem;">
                    <i class="bi bi-arrow-left me-1"></i>Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- Right Side - Branding Panel -->
    <div class="auth-right">
        <div class="auth-right-content">
            <i class="bi bi-book-half"></i>
            <h2>Join Our Library</h2>
            <p>Create your student account to start browsing books, borrowing titles, and accessing our digital collection.</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling?.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        if (icon) icon.className = 'bi bi-eye';
    }
}

// Dynamic class loading based on department selection
document.getElementById('dept_id').addEventListener('change', function() {
    const deptId = this.value;
    const classSelect = document.getElementById('class_id');

    if (deptId) {
        fetch('<?php echo SITE_URL; ?>/get_classes.php?dept_id=' + deptId)
            .then(response => response.json())
            .then(data => {
                classSelect.innerHTML = '<option value="">Select Class</option>';
                data.forEach(function(cls) {
                    classSelect.innerHTML += '<option value="' + cls.class_id + '">' + cls.class_name + '</option>';
                });
            })
            .catch(() => {
                classSelect.innerHTML = '<option value="">Error loading classes</option>';
            });
    } else {
        classSelect.innerHTML = '<option value="">Select Department First</option>';
    }
});

// Client-side validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match.');
        return false;
    }
});
</script>
</body>
</html>
