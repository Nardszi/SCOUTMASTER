<?php
session_start();
include('config.php');

// Ensure only Scout Leaders can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['scout_leader', 'admin'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['event_id'], $_POST['scout_id'])) {
    $event_id = $_POST['event_id'];
    $scout_id = $_POST['scout_id'];

    // Fetch event title for logging
    $event_query = mysqli_prepare($conn, "SELECT event_title FROM events WHERE id = ?");
    mysqli_stmt_bind_param($event_query, "i", $event_id);
    mysqli_stmt_execute($event_query);
    $event_result = mysqli_stmt_get_result($event_query);
    $event_title = mysqli_fetch_assoc($event_result)['event_title'] ?? 'Unknown Event';

    // Delete the attendance record
    $delete_query = "DELETE FROM event_attendance WHERE event_id = ? AND scout_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "ii", $event_id, $scout_id);

    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['user_id'], 'Remove Attendee', "Removed scout ID $scout_id from event '$event_title' (ID: $event_id)");
        header("Location: view_event_attendees.php?removed=success");
        exit();
    } else {
        echo "Error removing scout from event.";
    }
} else {
    echo "Invalid request.";
}
?>
