<?php
session_start();
include('config.php');

// Allow only Admins to delete users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Unauthorized access.";
    header('Location: manage_scouts.php');
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: manage_scouts.php");
    exit();
}

$user_id = intval($_GET['id']);

// Prevent deleting Admins
$query = "SELECT role, name FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: manage_scouts.php");
    exit();
}

if ($user['role'] == 'admin') {
    $_SESSION['error'] = "You cannot delete another Admin.";
    header("Location: manage_scouts.php");
    exit();
}

// Check if the archive columns exist
$check_column_query = "SHOW COLUMNS FROM `users` LIKE 'is_archived'";
$check_column_result = mysqli_query($conn, $check_column_query);
$column_exists = mysqli_num_rows($check_column_result) > 0;

if ($column_exists) {
    // Archive the user instead of deleting. This is a soft delete.
    $archive_query = "UPDATE users SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $archive_query);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $user_id);
    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['user_id'], 'Archive User', "Archived user '{$user['name']}' (ID: $user_id)");
        $_SESSION['success'] = "User archived successfully.";
        header("Location: manage_scouts.php?view=archive");
        exit();
    } else {
        $_SESSION['error'] = "Failed to archive user.";
    }
} else {
    $_SESSION['error'] = "Archive feature is not available. Please run the database update. User was not modified.";
}

header("Location: manage_scouts.php");
exit();
?>
