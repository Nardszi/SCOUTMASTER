<?php
session_start();
include('config.php');

if (isset($_SESSION['user_id'])) {
    // Log the logout activity
    logActivity($conn, $_SESSION['user_id'], 'Logout', 'User logged out');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>