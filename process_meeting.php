<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$meeting_id = intval($_POST['meeting_id']);
$action = $_POST['action'];

if ($action === 'approve') {
    mysqli_query($conn, "UPDATE meetings SET status = 'approved' WHERE id = $meeting_id");
}

if ($action === 'reject') {
    mysqli_query($conn, "DELETE FROM meetings WHERE id = $meeting_id");
}

header('Location: approve_meetings.php');
exit();
