<?php
include('config.php');

$name = "Admin";
$email = "admiin@example.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";
$approved = 1;

$query = "INSERT INTO users (name, email, password, role, approved) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $password, $role, $approved);

if (mysqli_stmt_execute($stmt)) {
    echo "Admin user created successfully!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
