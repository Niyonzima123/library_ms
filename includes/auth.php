<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // Student Login (accepts email or card number)
    public function studentLogin($emailOrCard, $password) {
        $stmt = $this->conn->prepare("SELECT u.*, d.dept_name, c.class_name 
            FROM users u 
            LEFT JOIN departments d ON u.dept_id = d.dept_id 
            LEFT JOIN classes c ON u.class_id = c.class_id 
            WHERE u.email = ? OR u.card_number = ?");
        $stmt->bind_param("ss", $emailOrCard, $emailOrCard);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = ROLE_STUDENT;
                $_SESSION['approval_status'] = $user['approval_status'];
                $_SESSION['dept_name'] = $user['dept_name'];
                $_SESSION['class_name'] = $user['class_name'];
                $_SESSION['profile_image'] = $user['profile_image'];
                
                // Log activity
                $this->logActivity('student', $user['id'], 'login', 'Student logged in');
                
                return ['success' => true, 'user' => $user];
            }
        }
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Admin/Librarian Login
    public function adminLogin($email, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM admins WHERE email = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['name'] = $admin['name'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role'] = $admin['role'];
                $_SESSION['profile_image'] = $admin['profile_image'];
                
                $user_type = $admin['role'];
                $this->logActivity($user_type, $admin['id'], 'login', ucfirst($user_type) . ' logged in');
                
                // Track librarian session
                if ($admin['role'] === 'librarian') {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    $stmt2 = $this->conn->prepare("INSERT INTO librarian_sessions (librarian_id, ip_address, user_agent) VALUES (?, ?, ?)");
                    $stmt2->bind_param("iss", $admin['id'], $ip, $ua);
                    $stmt2->execute();
                    $_SESSION['lib_session_id'] = $stmt2->insert_id;
                }
                
                return ['success' => true, 'user' => $admin];
            }
        }
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Student Registration
    public function registerStudent($data) {
        // Check if email exists
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Generate student ID and card number
        $student_id = $this->generateStudentId();
        $card_number = $this->generateCardNumber();
        
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->conn->prepare("INSERT INTO users (student_id, name, email, password, mobile, address, dept_id, class_id, card_number, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssssss", 
            $student_id, 
            $data['name'], 
            $data['email'], 
            $hashed_password, 
            $data['mobile'], 
            $data['address'],
            $data['dept_id'],
            $data['class_id'],
            $card_number
        );
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Create notification for admin
            $this->createAdminNotification('New Registration', 
                "New student {$data['name']} ({$student_id}) has registered and is awaiting approval.", 
                SITE_URL . '/admin/approve_students.php');
            
            $this->logActivity('student', $user_id, 'register', 'New student registered');
            
            return ['success' => true, 'message' => 'Registration successful! Please wait for admin approval.', 'student_id' => $student_id, 'card_number' => $card_number];
        }
        
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
    
    // Generate unique student ID
    private function generateStudentId() {
        $year = date('Y');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE student_id LIKE ?");
        $pattern = "STU-{$year}-%";
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['count'] + 1;
        return sprintf("STU-%s-%03d", $year, $count);
    }
    
    // Generate unique card number
    private function generateCardNumber() {
        $year = date('Y');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM users WHERE card_number LIKE ?");
        $pattern = "CARD-{$year}-%";
        $stmt->bind_param("s", $pattern);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['count'] + 1;
        return sprintf("CARD-%s-%03d", $year, $count);
    }
    
    // Log Activity
    public function logActivity($user_type, $user_id, $action, $description = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $device = $this->parseDevice($ua);
        $stmt = $this->conn->prepare("INSERT INTO activity_log (user_type, user_id, action, description, ip_address, device_info) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $user_type, $user_id, $action, $description, $ip, $device);
        $stmt->execute();
    }
    
    // Parse device info from user agent
    private function parseDevice($ua) {
        $device = 'Unknown';
        if (strpos($ua, 'Windows') !== false) $device = 'Windows';
        elseif (strpos($ua, 'Mac') !== false) $device = 'Mac';
        elseif (strpos($ua, 'Linux') !== false) $device = 'Linux';
        elseif (strpos($ua, 'Android') !== false) $device = 'Android';
        elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) $device = 'iOS';
        
        $browser = 'Unknown';
        if (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
        elseif (strpos($ua, 'Edg') !== false) $browser = 'Edge';
        elseif (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
        elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
        
        return $device . ' / ' . $browser;
    }
    
    // Create Admin Notification
    public function createAdminNotification($title, $message, $link = '') {
        // Get all admins
        $admins = $this->conn->query("SELECT id FROM admins WHERE is_active = 1");
        while ($admin = $admins->fetch_assoc()) {
            $stmt = $this->conn->prepare("INSERT INTO notifications (user_type, user_id, title, message, link) VALUES ('admin', ?, ?, ?, ?)");
            $stmt->bind_param("isss", $admin['id'], $title, $message, $link);
            $stmt->execute();
        }
    }
    
    // Create Student Notification
    public function createStudentNotification($user_id, $title, $message, $link = '') {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_type, user_id, title, message, link) VALUES ('student', ?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $message, $link);
        $stmt->execute();
    }
    
    // Notify All Approved Students
    public function notifyAllStudents($title, $message, $link = '') {
        $students = $this->conn->query("SELECT id FROM users WHERE approval_status = 'approved'");
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_type, user_id, title, message, link) VALUES ('student', ?, ?, ?, ?)");
        while ($student = $students->fetch_assoc()) {
            $stmt->bind_param("isss", $student['id'], $title, $message, $link);
            $stmt->execute();
        }
    }
    
    // Logout
    public function logout() {
        if (isLoggedIn()) {
            $user_type = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';
            if (!in_array($user_type, ['admin', 'librarian', 'student'])) {
                $user_type = 'student';
            }
            $this->logActivity($user_type, $_SESSION['user_id'], 'logout', ucfirst($user_type) . ' logged out');
            
            // Close librarian session
            if (isset($_SESSION['lib_session_id'])) {
                $stmt = $this->conn->prepare("UPDATE librarian_sessions SET logout_time = NOW(), is_active = 0 WHERE session_id = ?");
                $stmt->bind_param("i", $_SESSION['lib_session_id']);
                $stmt->execute();
            }
        }
        session_destroy();
    }
    
    // Require Login
    public function requireLogin() {
        if (!isLoggedIn()) {
            redirect(SITE_URL . '/login.php');
        }
    }
    
    // Require Admin
    public function requireAdmin() {
        $this->requireLogin();
        if (!isAdmin()) {
            redirect(SITE_URL . '/student/dashboard.php');
        }
    }
    
    // Require Approved Student
    public function requireApproved() {
        $this->requireLogin();
        if (isStudent() && !isApproved()) {
            redirect(SITE_URL . '/student/pending_approval.php');
        }
    }
}

$auth = new Auth($conn);
?>