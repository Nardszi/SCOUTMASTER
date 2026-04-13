<?php
// Set default timezone to Asia/Manila to fix time offset issues
date_default_timezone_set('Asia/Manila');

$host = "your_db_host";
$user = "your_db_user";
$password = "your_db_password";
$database = "your_db_name";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// SMTP Configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'your_smtp_host');
    define('SMTP_USER', 'your_smtp_username');  // Replace with your actual email
    define('SMTP_PASS', 'your_smtp_password');  // Replace with your actual app password
    define('SMTP_PORT', 587);
}

// Brevo API Configuration
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', 'your_brevo_api_key');
}

// Brevo Sender Configuration (must be verified in Brevo dashboard)
if (!defined('BREVO_SENDER_EMAIL')) {
    define('BREVO_SENDER_EMAIL', 'your_sender_email');
    define('BREVO_SENDER_NAME', 'your_sender_name');
}

// Activity Logging Function
if (!function_exists('logActivity')) {
    function logActivity($conn, $user_id, $action, $details = '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $details);
        mysqli_stmt_execute($stmt);
    }
}
?>
