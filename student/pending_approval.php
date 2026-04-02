<?php
$page_title = 'Pending Approval';
include 'includes/student_header.php';

// Auto-redirect if already approved
if (isApproved()) {
    redirect(STUDENT_URL . '/dashboard.php');
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT u.*, d.dept_name, c.class_name
    FROM users u
    LEFT JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<div class="page-header">
    <div>
        <h4>Account Status</h4>
        <p class="text-muted mb-0">Your registration is being reviewed</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body text-center py-5">
                <!-- Animated waiting indicator -->
                <div class="mb-4">
                    <div class="mx-auto" style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg, #fef3c7, #fbbf24);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-hourglass-split" style="font-size:3rem;color:#92400e;" id="waiting-icon"></i>
                    </div>
                </div>

                <h3 class="mb-3" style="color:var(--warning);">
                    <i class="bi bi-exclamation-triangle"></i> Awaiting Admin Approval
                </h3>
                <p class="text-muted mb-4" style="font-size:1rem;max-width:500px;margin:0 auto;">
                    Your account registration is currently pending approval from the library administrator.
                    You will be able to borrow books once your account is approved.
                </p>

                <!-- Registration Details -->
                <div class="card bg-light border-0 text-start mx-auto" style="max-width:450px;">
                    <div class="card-body">
                        <h6 class="card-title mb-3"><i class="bi bi-person-badge"></i> Registration Details</h6>
                        <div class="d-flex justify-content-between mb-2" style="font-size:0.9rem;">
                            <span class="text-muted">Name</span>
                            <strong><?php echo sanitize($user['name']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2" style="font-size:0.9rem;">
                            <span class="text-muted">Student ID</span>
                            <strong><?php echo sanitize($user['student_id']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2" style="font-size:0.9rem;">
                            <span class="text-muted">Email</span>
                            <strong><?php echo sanitize($user['email']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2" style="font-size:0.9rem;">
                            <span class="text-muted">Department</span>
                            <strong><?php echo sanitize($user['dept_name'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2" style="font-size:0.9rem;">
                            <span class="text-muted">Class</span>
                            <strong><?php echo sanitize($user['class_name'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:0.9rem;">
                            <span class="text-muted">Card Number</span>
                            <strong><?php echo sanitize($user['card_number'] ?? 'N/A'); ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between" style="font-size:0.9rem;">
                            <span class="text-muted">Status</span>
                            <span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-muted mb-3" style="font-size:0.88rem;">
                        In the meantime, you can still browse the library catalog.
                    </p>
                    <a href="<?php echo STUDENT_URL; ?>/catalog.php" class="btn btn-primary">
                        <i class="bi bi-collection"></i> Browse Catalog
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
#waiting-icon {
    animation: bounce 1.5s ease-in-out infinite;
}
</style>

<script>
// Auto-refresh every 30 seconds to check if approved
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include 'includes/student_footer.php'; ?>
