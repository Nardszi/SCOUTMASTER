<?php
session_start();
require 'config.php'; // Ensure you have a database connection file

// Check if the user is a Scout Leader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout_leader') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scout_name = trim($_POST['scout_name']);
    $registration_status = $_POST['registration_status'];
    $age = intval($_POST['age']);
    $sex = $_POST['sex'];
    $membership_card = trim($_POST['membership_card']);
    $highest_rank = trim($_POST['highest_rank']);
    $years_in_scouting = intval($_POST['years_in_scouting']);
    $unit_no = trim($_POST['unit_no']);
    $local_council = trim($_POST['local_council']);
    $troop_id = $_SESSION['troop_id']; // Assuming the Scout Leader is linked to a troop

    $stmt = $conn->prepare("INSERT INTO scouts (name, registration_status, age, sex, membership_card, highest_rank, years_in_scouting, unit_no, local_council, troop_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssissi", $scout_name, $registration_status, $age, $sex, $membership_card, $highest_rank, $years_in_scouting, $unit_no, $local_council, $troop_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Scout registered successfully.";
    } else {
        $_SESSION['error_message'] = "Error registering scout.";
    }
    
    header("Location: manage_scoutsTL.php");
    exit();
}
?>
