<?php
session_start();
include('config.php');

/* Allow only admin */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$event_id = $_POST['event_id'] ?? null;
$action   = $_POST['action'] ?? null;

if (!$event_id || !$action) {
    header("Location: approve_events.php");
    exit();
}

// Fetch event title for logging
$event_title_query = mysqli_prepare($conn, "SELECT event_title FROM events WHERE id = ?");
mysqli_stmt_bind_param($event_title_query, "i", $event_id);
mysqli_stmt_execute($event_title_query);
$event_title_res = mysqli_stmt_get_result($event_title_query);
$event_title = mysqli_fetch_assoc($event_title_res)['event_title'] ?? "ID: $event_id";

/* APPROVE EVENT */
if ($action === 'approved') {

    $stmt = mysqli_prepare($conn, "UPDATE events SET status = 'approved' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    logActivity($conn, $_SESSION['user_id'], 'Approve Event', "Approved event: '$event_title'");

/* REJECT EVENT → DELETE */
} elseif ($action === 'rejected') {

    $stmt = mysqli_prepare($conn, "DELETE FROM events WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    logActivity($conn, $_SESSION['user_id'], 'Reject Event', "Rejected event: '$event_title'");
}

header("Location: events.php");
exit();
