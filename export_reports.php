<?php
session_start();
include 'config.php';

// Only Admin can export reports
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// gather summary values
$values = [];
$queries = [
    'Total Users' => "SELECT COUNT(*) as total FROM users",
    'Total Schools' => "SELECT COUNT(DISTINCT school) as total FROM users WHERE school IS NOT NULL AND school != '' AND school != 'N/A'",
    'Badge Progress Records' => "SELECT COUNT(*) as total FROM scout_badge_progress",
    'Badge Requirements Completed' => "SELECT COUNT(DISTINCT scout_badge_progress_id) as completed FROM scout_requirement_progress WHERE date_approved IS NOT NULL",
    'Approved Events' => "SELECT COUNT(*) as total FROM events WHERE status = 'approved'",
    'Total Attendees' => "SELECT COUNT(*) as total FROM event_attendance",
    'Scouts Registered' => "SELECT COUNT(*) as total FROM users WHERE role = 'scout'",
    'Batch Registrations' => "SELECT COUNT(*) as total FROM admin_scout_archive",
];

foreach ($queries as $label => $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $values[$label] = current($row) ?? 0;
    } else {
        $values[$label] = 0;
    }
}

// output CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="admin_summary.csv"');
$output = fopen('php://output','w');
fputcsv($output, ['Metric','Value']);
foreach ($values as $metric => $val) {
    fputcsv($output, [$metric, $val]);
}
fclose($output);
exit();
