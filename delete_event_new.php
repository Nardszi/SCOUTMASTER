<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: dashboard.php');
    exit();
}

// Accept both POST and GET
$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method == "POST" || $request_method == "GET") {
    
    // Get event_id from POST or GET
    if ($request_method == "POST") {
        $event_id_raw = isset($_POST['event_id']) ? $_POST['event_id'] : 'NOT SET';
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $debug = "POST - Raw: $event_id_raw, Parsed: $event_id";
    } else {
        $event_id_raw = isset($_GET['event_id']) ? $_GET['event_id'] : 'NOT SET';
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        $debug = "GET - Raw: $event_id_raw, Parsed: $event_id";
    }
    
    if ($event_id <= 0) {
        $_SESSION['error'] = "Invalid event ID. Debug: $debug";
        header("Location: events.php");
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if ($user_role === 'admin') {
        $query = "DELETE FROM events WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $event_id);
    } else {
        $query = "DELETE FROM events WHERE id = ? AND scout_leader_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            logActivity($conn, $user_id, 'Delete Event', "Deleted event ID $event_id");
            $_SESSION['success'] = "Event deleted successfully!";
        } else {
            $_SESSION['error'] = "Event not found or you don't have permission to delete it.";
        }
    } else {
        $_SESSION['error'] = "Error deleting event.";
    }
    
    mysqli_stmt_close($stmt);
    header("Location: events.php");
    exit();
    
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: events.php");
    exit();
}
?>
