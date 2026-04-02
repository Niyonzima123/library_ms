<?php
// Database Configuration
// Auto-detect: use production config if it exists, otherwise use local XAMPP

$production_config = __DIR__ . '/database_production.php';

if (file_exists($production_config)) {
    // Production (InfinityFree) - credentials in database_production.php
    require_once $production_config;
} else {
    // Local development (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'lms_db');

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
}
?>
