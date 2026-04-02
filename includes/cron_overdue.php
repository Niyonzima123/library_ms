<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$sql = "UPDATE issued_books SET status = 3, fine_amount = DATEDIFF(CURDATE(), due_date) * ? WHERE status = 1 AND due_date < CURDATE()";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", FINE_PER_DAY);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
    echo "Overdue check completed. " . $updated . " book(s) marked as overdue.\n";
} else {
    echo "Error preparing statement: " . $conn->error . "\n";
}

$conn->close();
?>
