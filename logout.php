<?php
session_start();
include('config.php');

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET last_activity = '1970-01-01 00:00:00' WHERE id = $uid");
}

session_destroy();
header("Location: homepage.php");
exit();
?>
