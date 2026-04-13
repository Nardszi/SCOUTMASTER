<?php
session_start();
include('config.php');

$msg = "";
$msg_type = "";
$show_form = true;

$email = $_GET['email'] ?? $_POST['email'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($email) || empty($token)) {
    die("Invalid request.");
}

// Verify Token on Load
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW() AND (is_archived = 0 OR is_archived IS NULL)");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $msg = "Invalid or expired reset link.";
        $msg_type = "danger";
        $show_form = false;
    }
}

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $msg = "Passwords do not match.";
        $msg_type = "danger";
    } else {
        // Verify token again before updating
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW() AND (is_archived = 0 OR is_archived IS NULL)");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $post_result = $stmt->get_result();
        
        if ($post_result->num_rows > 0) {
            $user_row = $post_result->fetch_assoc();
            $uid = $user_row['id'];
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
            $update->bind_param("ss", $hashed, $email);
            
            if ($update->execute()) {
                logActivity($conn, $uid, 'Reset Password', 'Password reset via token');
                $msg = "Password reset successfully! <a href='login.php' class='alert-link'>Login here</a>";
                $msg_type = "success";
                $show_form = false;
            } else {
                $msg = "Database error.";
                $msg_type = "danger";
            }
        } else {
            $msg = "Invalid or expired token.";
            $msg_type = "danger";
            $show_form = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Scout Master</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');
        body {
            background-color: #000;
            color: white;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/picscout1.png') no-repeat center center/cover;
            height: 100vh;
            overflow: hidden;
        }
        .login-container {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            color: white;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .btn-custom {
            background-color: #28a745; color: white; padding: 12px; border-radius: 10px; border: none; width: 100%; font-weight: 600;
        }
        .btn-custom:hover { background-color: #218838; }
        a { color: #28a745; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <h2 class="text-center fw-bold mb-4">Reset Password</h2>
            <?php if ($msg) { echo "<div class='alert alert-$msg_type'>$msg</div>"; } ?>
            
            <?php if ($show_form): ?>
            <form method="POST">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                <button type="submit" class="btn btn-custom">Update Password</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>