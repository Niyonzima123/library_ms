<?php
// ============================================
// Production Database Configuration
// InfinityFree Hosting
// ============================================
// Host: book-store-ms.rf.gd
// Database: if0_41563530_lms_db

$db_host = 'sql312.infinityfree.com';
$db_user = 'if0_41563530';
$db_pass = 'IScMWxZvqVBnCn';
$db_name = 'if0_41563530_lms_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("DB Error: " . $conn->connect_error);
    die("Service temporarily unavailable. Please try again later.");
}

$conn->set_charset("utf8mb4");
?>
