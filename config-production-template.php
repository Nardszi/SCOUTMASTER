<?php
/**
 * PRODUCTION CONFIGURATION FILE
 * 
 * Instructions:
 * 1. Copy this file and rename to config.php on your production server
 * 2. Update all values with your actual production credentials
 * 3. Set file permissions to 644 or 600 for security
 */

// Set default timezone
date_default_timezone_set('Asia/Manila');

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Get these from your hosting cPanel → MySQL Databases
$host = "localhost";                    // Usually 'localhost' on shared hosting
$user = "your_cpanel_username_dbuser"; // Your database username
$password = "your_secure_password";     // Your database password
$database = "your_cpanel_username_db";  // Your database name

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    // In production, log error instead of displaying
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Unable to connect to database. Please contact support.");
}

// ============================================
// EMAIL CONFIGURATION (SMTP)
// ============================================
// For Gmail: Use App Password (not your regular password)
// Enable 2FA and generate App Password at: https://myaccount.google.com/apppasswords
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');      // Your Gmail address
define('SMTP_PASS', 'your-app-password-here');    // Gmail App Password (16 characters)
define('SMTP_PORT', 587);

// ============================================
// BREVO API CONFIGURATION
// ============================================
// Get your API key from: https://app.brevo.com/settings/keys/api
define('BREVO_API_KEY', 'your-brevo-api-key-here');

// ============================================
// SECURITY SETTINGS
// ============================================
// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 1 if using HTTPS

// Error reporting - DISABLE in production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Log errors to file

// ============================================
// FILE UPLOAD SETTINGS
// ============================================
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// ============================================
// APPLICATION SETTINGS
// ============================================
define('SITE_NAME', 'Boy Scout Management System');
define('SITE_URL', 'https://yourdomain.com'); // Your actual domain
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// ============================================
// ACTIVITY LOGGING FUNCTION
// ============================================
if (!function_exists('logActivity')) {
    function logActivity($conn, $user_id, $action, $details = '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $details);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Sanitize user input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function has_role($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

/**
 * Redirect if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirect if not specific role
 */
function require_role($required_role) {
    if (!has_role($required_role)) {
        header('Location: dashboard.php');
        exit();
    }
}

// ============================================
// MAINTENANCE MODE (Optional)
// ============================================
// Set to true to enable maintenance mode
define('MAINTENANCE_MODE', false);

if (MAINTENANCE_MODE && !isset($_SESSION['is_admin'])) {
    // Show maintenance page
    http_response_code(503);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Maintenance Mode</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f0f0f0; }
            h1 { color: #333; }
            p { color: #666; }
        </style>
    </head>
    <body>
        <h1>🔧 System Maintenance</h1>
        <p>We are currently performing scheduled maintenance.</p>
        <p>Please check back in a few minutes.</p>
    </body>
    </html>
    ');
}

// ============================================
// NOTES FOR DEPLOYMENT
// ============================================
/*
 * BEFORE GOING LIVE:
 * 
 * 1. Update all credentials above
 * 2. Set error_reporting(0) and display_errors to 0
 * 3. Enable HTTPS (SSL certificate)
 * 4. Set proper file permissions:
 *    - config.php: 644 or 600
 *    - uploads/: 755 or 777
 * 5. Test all features thoroughly
 * 6. Set up automated backups
 * 7. Configure email settings
 * 8. Test password reset functionality
 * 
 * SECURITY CHECKLIST:
 * - [ ] Changed default database password
 * - [ ] Using HTTPS (SSL)
 * - [ ] Error display disabled
 * - [ ] File permissions set correctly
 * - [ ] Sensitive files protected
 * - [ ] Regular backups configured
 * - [ ] Email notifications working
 * - [ ] All credentials updated
 */
?>
