<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'troop_leader')) {
    header('Location: dashboard.php');
    exit();
}

// Fetch user data
$users_query = "SELECT name, email, role FROM users";
$result = mysqli_query($conn, $users_query);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users_list.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Name', 'Email', 'Role']); // CSV Column Headers

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
