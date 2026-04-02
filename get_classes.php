<?php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$dept_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

if ($dept_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT class_id, class_name FROM classes WHERE dept_id = ? ORDER BY class_name");
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

echo json_encode($classes);
?>
