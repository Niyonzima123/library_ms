<?php
// ============================================
// Production Database Configuration
// InfinityFree cPanel Database Settings
// ============================================
// STEP 1: Go to InfinityFree cPanel → MySQL Databases
// STEP 2: Create a database (note the full name like epiz_XXXXX_lms)
// STEP 3: Create a user and add it to the database
// STEP 4: Replace the values below with your credentials

$db_host = 'sql205.epizy.com';     // REPLACE: your DB host from cPanel
$db_user = 'epiz_XXXXXXX';         // REPLACE: your DB username
$db_pass = 'YourPassword123';      // REPLACE: your DB password
$db_name = 'epiz_XXXXXXX_lms_db';  // REPLACE: your full DB name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("DB Error: " . $conn->connect_error);
    die("Service temporarily unavailable. Please try again later.");
}

$conn->set_charset("utf8mb4");
?>
