<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['role']) && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_LIBRARIAN)) {
    $fine_per_day = FINE_PER_DAY;
    $sql = "UPDATE issued_books SET status = 3, fine_amount = DATEDIFF(CURDATE(), due_date) * ? WHERE status = 1 AND due_date < CURDATE()";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $fine_per_day);
        $stmt->execute();
        $stmt->close();
    }
}
?>
