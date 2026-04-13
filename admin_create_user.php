<?php
session_start();
include('config.php');

// Allow only Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Handle User Creation/Registration by Admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $school = $_POST['school'];
    $membership_card = $_POST['membership_card'] ?? NULL;
    $approved = 1;
    $email_verified = 1; // Admin created, so pre-verify

    $status = $_POST['status'] ?? 'Active'; // For non-scouts, default to Active

    // Auto-generate membership card if role is scout and not provided
    if ($role === 'scout' && empty($membership_card)) {
        $date = date('Ymd');
        do {
            $rand = mt_rand(1000, 9999);
            $membership_card = 'SCOUT-' . $date . '-' . $rand;
            $check = $conn->prepare("SELECT id FROM users WHERE membership_card = ?");
            $check->bind_param("s", $membership_card);
            $check->execute();
            $check->store_result();
        } while ($check->num_rows > 0);
        $check->close();
    }

    // Check for duplicate email
    $check_email_query = "SELECT id FROM users WHERE email = ?";
    $check_stmt = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    if (mysqli_stmt_get_result($check_stmt)->num_rows > 0) {
        $_SESSION['error'] = "User with this email already exists.";
        header("Location: manage_scouts.php"); // Redirect back to form
        exit();
    } else {
        $insert_query = "INSERT INTO users (name, email, password, role, school, approved, email_verified, status, membership_card) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "sssssiiss", $name, $email, $password, $role, $school, $approved, $email_verified, $status, $membership_card);

        if (mysqli_stmt_execute($insert_stmt)) {
            $new_user_id = mysqli_insert_id($conn);
            logActivity($conn, $_SESSION['user_id'], 'Create User', "Created user '$name' (ID: $new_user_id)");
            if ($role === 'scout' && isset($membership_card) && !isset($_POST['membership_card'])) {
                $_SESSION['success'] = "User created successfully. Generated Membership Card: $membership_card";
            } else {
                $_SESSION['success'] = "User created successfully.";
            }
        } else {
            $_SESSION['error'] = "Error creating user. Please ensure the database is up to date.";
        }
    }

    // Always redirect back to the manage users page
    header("Location: manage_scouts.php");
    exit();
}