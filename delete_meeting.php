<?php
session_start();
include('config.php');

// Ensure only Scout Leaders and Admins can delete
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['meeting_id'])) {
    $meeting_id = intval($_POST['meeting_id']); // Ensure it is an integer
    $user_id = $_SESSION['user_id'];

    // Check if the logged-in user created this meeting
    $query = "SELECT * FROM meetings WHERE id = ? AND created_by = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $meeting_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Delete the meeting
        $delete_query = "DELETE FROM meetings WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $meeting_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            logActivity($conn, $user_id, 'Delete Meeting', "Deleted meeting ID $meeting_id");
            $_SESSION['success'] = "Meeting deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting meeting!";
        }
    } else {
        $_SESSION['error'] = "You are not authorized to delete this meeting.";
    }

    header("Location: schedule_meeting.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request!";
    header("Location: schedule_meeting.php");
    exit();
}
?>
