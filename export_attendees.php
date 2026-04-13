<?php
session_start();
include('config.php');

// Ensure only Scout Leaders can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['scout_leader', 'admin'])) {
    header('Location: dashboard.php');
    exit();
}

if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $scout_leader_id = $_SESSION['user_id'];

    // Verify event belongs to scout leader
    $query_event = "SELECT event_title FROM events WHERE id = ? AND scout_leader_id = ?";
    $stmt_event = mysqli_prepare($conn, $query_event);
    mysqli_stmt_bind_param($stmt_event, "ii", $event_id, $scout_leader_id);
    mysqli_stmt_execute($stmt_event);
    $result_event = mysqli_stmt_get_result($stmt_event);
    $event = mysqli_fetch_assoc($result_event);

    if (!$event) {
        die("Event not found or access denied.");
    }

    $filename = "Attendees_" . preg_replace('/[^a-zA-Z0-9]/', '_', $event['event_title']) . "_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, ['Scout Name', 'Email', 'Address', 'Rank', 'Emergency Contact', 'Reason', 'Requirements']);

    // Fetch attendees
    $query_attendees = "SELECT 
                            event_attendance.name AS attendee_name, 
                            users.email,
                            event_attendance.address,
                            event_attendance.scout_rank,
                            event_attendance.emergency_contact,
                            event_attendance.reason,
                            event_attendance.requirements
                        FROM event_attendance 
                        JOIN users ON event_attendance.scout_id = users.id 
                        WHERE event_attendance.event_id = ?";
    
    $stmt_attendees = mysqli_prepare($conn, $query_attendees);
    mysqli_stmt_bind_param($stmt_attendees, "i", $event_id);
    mysqli_stmt_execute($stmt_attendees);
    $result_attendees = mysqli_stmt_get_result($stmt_attendees);

    while ($row = mysqli_fetch_assoc($result_attendees)) {
        fputcsv($output, [
            $row['attendee_name'],
            $row['email'],
            $row['address'],
            $row['scout_rank'],
            $row['emergency_contact'],
            $row['reason'],
            $row['requirements']
        ]);
    }

    fclose($output);
    exit();
} else {
    header('Location: view_event_attendees.php');
    exit();
}
?>