<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];

if ($role == 'scout') {
    header('Location: dashboard_scout.php');
} else {
    header('Location: dashboard_admin_leader.php');
}
exit();
?>
