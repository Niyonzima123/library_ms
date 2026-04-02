<?php
session_start();
require_once __DIR__ . '/config/database.php';
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

$active_tab = 'student';
$show_register = false;
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid CSRF token. Please try again.');
        redirect(SITE_URL . '/login.php');
    }

    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $active_tab = sanitize($_POST['login_type'] ?? 'student');

        if (empty($email) || empty($password)) {
            setFlashMessage('danger', 'Please fill in all fields.');
            redirect(SITE_URL . '/login.php');
        }

        if ($active_tab === 'admin' || $active_tab === 'librarian') {
            $result = $auth->adminLogin($email, $password);
        } else {
            $result = $auth->studentLogin($email, $password);
        }

            if ($result['success']) {
                setFlashMessage('success', 'Welcome back, ' . $_SESSION['name'] . '!');
                if ($active_tab === 'admin' || $active_tab === 'librarian') {
                    if ($_SESSION['role'] === 'librarian') {
                        redirect(SITE_URL . '/librarian/dashboard.php');
                    }
                    redirect(ADMIN_URL . '/dashboard.php');
            } else {
                redirect(STUDENT_URL . '/dashboard.php');
            }
        }

        setFlashMessage('danger', $result['message'] ?? 'Invalid email or password.');
        redirect(SITE_URL . '/login.php');
    }

    if ($action === 'register') {
        $show_register = true;
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $mobile = sanitize($_POST['mobile'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $dept_id = intval($_POST['dept_id'] ?? 0);
        $class_id = intval($_POST['class_id'] ?? 0);

        $old = [
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'address' => $address,
            'dept_id' => $dept_id,
            'class_id' => $class_id,
        ];

        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($mobile)) {
            $errors[] = 'Mobile number is required.';
        }
        if ($dept_id <= 0) {
            $errors[] = 'Please select a department.';
        }
        if ($class_id <= 0) {
            $errors[] = 'Please select a class.';
        }

        if (empty($errors)) {
            $data = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'mobile' => $mobile,
                'address' => $address,
                'dept_id' => $dept_id,
                'class_id' => $class_id,
            ];
            $result = $auth->registerStudent($data);

            if ($result['success']) {
                setFlashMessage('success', $result['message']);
                redirect(SITE_URL . '/login.php');
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

$flash = getFlashMessage();
$csrf_token = generateCSRFToken();

// Check for tab parameter
if (isset($_GET['tab']) && $_GET['tab'] === 'admin') {
    $active_tab = 'admin';
}
if (isset($_GET['register'])) {
    $show_register = true;
}

// Load departments for registration
$departments_result = $conn->query("SELECT * FROM departments ORDER BY dept_name");
$departments = [];
if ($departments_result) {
    while ($row = $departments_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Load all classes for registration (filtered by dept via JS)
$classes_result = $conn->query("SELECT c.*, d.dept_name FROM classes c LEFT JOIN departments d ON c.dept_id = d.dept_id ORDER BY c.class_name");
$classes = [];
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $classes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
        <!-- Left Side - Forms -->
        <div class="auth-left" style="overflow-y:auto; align-items:flex-start; padding-top:2rem;">
            <div class="auth-card" style="max-width:480px;">
                <div class="auth-header">
                    <div class="logo">
                        <i class="bi bi-book-half"></i>
                    </div>
                    <h3 id="form-title"><?php echo $show_register ? 'Create Account' : 'Welcome Back'; ?></h3>
                    <p id="form-subtitle"><?php echo $show_register ? 'Register for a new student account' : 'Sign in to continue to your library account'; ?></p>
                </div>

                <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

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

                <!-- Login Section -->
                <div id="login-section" style="<?php echo $show_register ? 'display:none;' : ''; ?>">
                    <!-- Login Type Tabs -->
                    <ul class="nav nav-pills nav-justified mb-4" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_tab === 'student' ? 'active' : ''; ?>" id="student-tab" data-bs-toggle="tab" data-bs-target="#student-pane" type="button" role="tab">
                                <i class="bi bi-mortarboard me-1"></i> Student
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_tab === 'librarian' ? 'active' : ''; ?>" id="librarian-tab" data-bs-toggle="tab" data-bs-target="#librarian-pane" type="button" role="tab">
                                <i class="bi bi-book me-1"></i> Librarian
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_tab === 'admin' ? 'active' : ''; ?>" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-pane" type="button" role="tab">
                                <i class="bi bi-shield-lock me-1"></i> Admin
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="loginTabContent">
                        <!-- Student Login -->
                        <div class="tab-pane fade <?php echo $active_tab === 'student' ? 'show active' : ''; ?>" id="student-pane" role="tabpanel">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="login_type" value="student">
                                <div class="mb-3">
                                    <label for="student-email" class="form-label">Email or Card Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                        <input type="text" class="form-control" id="student-email" name="email" placeholder="student@library.edu or your card number" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="student-password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="student-password" name="password" placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('student-password', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login as Student
                                </button>
                            </form>
                        </div>

                        <!-- Librarian Login -->
                        <div class="tab-pane fade <?php echo $active_tab === 'librarian' ? 'show active' : ''; ?>" id="librarian-pane" role="tabpanel">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="login_type" value="librarian">
                                <div class="mb-3">
                                    <label for="librarian-email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="librarian-email" name="email" placeholder="librarian@library.edu" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="librarian-password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="librarian-password" name="password" placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('librarian-password', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                                    <i class="bi bi-book me-2"></i>Login as Librarian
                                </button>
                            </form>
                        </div>

                        <!-- Admin Login -->
                        <div class="tab-pane fade <?php echo $active_tab === 'admin' ? 'show active' : ''; ?>" id="admin-pane" role="tabpanel">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="login_type" value="admin">
                                <div class="mb-3">
                                    <label for="admin-email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="admin-email" name="email" placeholder="admin@library.edu" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="admin-password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="admin-password" name="password" placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('admin-password', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                                    <i class="bi bi-shield-lock me-2"></i>Login as Admin
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted mb-2" style="font-size: 0.9rem;">
                            <a href="<?php echo SITE_URL; ?>/forgot_password.php">Forgot your password?</a>
                        </p>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">
                            Don't have an account? <a href="#" onclick="showSection('register'); return false;">Register here</a>
                        </p>
                    </div>
                </div>

                <!-- Registration Section -->
                <div id="register-section" style="<?php echo !$show_register ? 'display:none;' : ''; ?>">
                    <form method="POST" action="" novalidate id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="register">

                        <div class="mb-3">
                            <label for="reg-name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="reg-name" name="name" placeholder="Enter your full name" value="<?php echo sanitize($old['name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reg-email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="reg-email" name="email" placeholder="student@library.edu" value="<?php echo sanitize($old['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="reg-password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="reg-password" name="password" placeholder="Min 6 characters" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('reg-password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reg-confirm-password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="reg-confirm-password" name="confirm_password" placeholder="Re-enter password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('reg-confirm-password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reg-mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                <input type="tel" class="form-control" id="reg-mobile" name="mobile" placeholder="Enter your mobile number" value="<?php echo sanitize($old['mobile'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reg-address" class="form-label">Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <textarea class="form-control" id="reg-address" name="address" rows="2" placeholder="Enter your address"><?php echo sanitize($old['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="reg-dept" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="reg-dept" name="dept_id" required onchange="filterClasses()">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>" <?php echo (isset($old['dept_id']) && $old['dept_id'] == $dept['dept_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($dept['dept_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reg-class" class="form-label">Class <span class="text-danger">*</span></label>
                                <select class="form-select" id="reg-class" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $cls): ?>
                                    <option value="<?php echo $cls['class_id']; ?>" data-dept="<?php echo $cls['dept_id']; ?>" <?php echo (isset($old['class_id']) && $old['class_id'] == $cls['class_id']) ? 'selected' : ''; ?> style="display:none;">
                                        <?php echo sanitize($cls['class_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 mt-2">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">
                            Already have an account? <a href="#" onclick="showSection('login'); return false;">Login here</a>
                        </p>
                    </div>
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
// All classes data for filtering
const allClasses = <?php echo json_encode($classes); ?>;

function showSection(section) {
    const loginSection = document.getElementById('login-section');
    const registerSection = document.getElementById('register-section');
    const formTitle = document.getElementById('form-title');
    const formSubtitle = document.getElementById('form-subtitle');

    if (section === 'register') {
        loginSection.style.display = 'none';
        registerSection.style.display = 'block';
        formTitle.textContent = 'Create Account';
        formSubtitle.textContent = 'Register for a new student account';
        history.replaceState(null, '', '<?php echo SITE_URL; ?>/login.php?register=1');
    } else {
        loginSection.style.display = 'block';
        registerSection.style.display = 'none';
        formTitle.textContent = 'Welcome Back';
        formSubtitle.textContent = 'Sign in to continue to your library account';
        history.replaceState(null, '', '<?php echo SITE_URL; ?>/login.php');
    }
}

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

function filterClasses() {
    const deptId = document.getElementById('reg-dept').value;
    const classSelect = document.getElementById('reg-class');
    const options = classSelect.querySelectorAll('option');

    classSelect.value = '';

    options.forEach(function(opt) {
        if (opt.value === '') {
            opt.style.display = '';
            return;
        }
        if (deptId && opt.getAttribute('data-dept') === deptId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

// Initialize class filter on page load if dept is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.getElementById('reg-dept');
    if (deptSelect && deptSelect.value) {
        filterClasses();
    }
});
</script>
</body>
</html>
