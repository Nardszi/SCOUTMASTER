<?php
session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $query = "SELECT id, name, password, role, approved, email_verified, is_archived FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            // Check if the user is archived
            if (isset($user['is_archived']) && $user['is_archived'] == 1) {
                $error = "Your account has been archived and cannot be accessed.";
            }
            // Then check other conditions
            elseif ($user['email_verified'] == 0) {
                $error = "Please verify your email first. <a href='verify_email.php?email=" . urlencode($email) . "' class='alert-link'>Enter Code</a>";
            } elseif ($user['approved'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Log the login activity
                logActivity($conn, $user['id'], 'Login', 'User logged in successfully');
                
                // Redirect based on role
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Your account is awaiting admin approval.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Scout Master</title>
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');

        body {
            background-color: #000;
            color: white;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/wall3.jpg') no-repeat center center/cover;
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        /* --- NAVBAR --- */
        .navbar {
            padding: 1rem 1.5rem;
            background: transparent;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
            transition: color 0.3s;
            color: rgba(255,255,255,0.8) !important;
        }
        .nav-link:hover, .nav-link.active {
            color: #28a745 !important;
            text-shadow: 0 0 10px rgba(40, 167, 69, 0.8);
        }
        .login-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 15px;
            box-sizing: border-box;
        }
        .login-wrapper {
            width: 100%;
            max-width: 400px;
            position: relative;
            padding-top: 110px;
            margin: 0 auto;
        }
        .login-logo-float {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 220px;
            height: 220px;
            object-fit: contain;
            z-index: 10;
            filter: drop-shadow(0 4px 16px rgba(0,0,0,0.5));
        }
        .login-card {
            width: 100%;
            padding: 130px 40px 40px 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            color: white;
            animation: fadeInUp 1s ease-out;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: none;
            border-color: #28a745;
        }
        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
        }
        .input-group-text:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .btn-custom {
            background-color: #28a745;
            color: white;
            padding: 12px;
            font-size: 1.1em;
            font-weight: 600;
            border-radius: 10px;
            transition: 0.3s;
            border: none;
            animation: pulse 2s infinite;
        }
        .btn-custom:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .form-label {
            font-weight: 500;
        }
        a {
            color: #28a745;
            text-decoration: none;
        }
        a:hover {
            color: #218838;
            text-decoration: underline;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .login-logo {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            body {
                overflow-y: auto;
                height: auto;
                min-height: 100vh;
                background-attachment: scroll;
            }

            .navbar {
                padding: 0.75rem 1rem;
            }

            .navbar-brand {
                font-size: 1.2rem;
            }

            .nav-link {
                font-size: 14px;
                padding: 0.5rem 1rem;
            }

            .navbar-collapse {
                background: rgba(0, 0, 0, 0.95);
                padding: 1rem;
                border-radius: 10px;
                margin-top: 1rem;
            }

            .login-container {
                height: auto;
                min-height: 100vh;
                padding: 100px 15px 30px;
            }

            .login-wrapper {
                padding-top: 60px;
            }

            .login-card {
                max-width: 100%;
                padding: 80px 20px 30px 20px;
                margin: 0;
                border-radius: 18px;
            }

            .login-logo-float {
                width: 180px;
                height: 180px;
            }

            h2 {
                font-size: 1.75rem;
                margin-bottom: 0.5rem;
            }

            .text-white-50 {
                font-size: 0.9rem;
            }

            .form-label {
                font-size: 14px;
                margin-bottom: 0.4rem;
            }

            .form-control {
                font-size: 14px;
                padding: 12px 15px;
            }

            .input-group-text {
                padding: 12px 15px;
            }

            .btn-custom {
                font-size: 1rem;
                padding: 12px;
            }

            .alert {
                font-size: 13px;
                padding: 10px;
                margin-bottom: 1rem;
            }

            p {
                font-size: 14px;
            }

            .mb-3 {
                margin-bottom: 1rem !important;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                padding: 0.5rem 0.75rem;
            }

            .navbar-brand {
                font-size: 1.1rem;
            }

            .nav-link {
                font-size: 13px;
                padding: 0.4rem 0.75rem;
            }

            .navbar-collapse {
                padding: 0.75rem;
                margin-top: 0.75rem;
            }

            .login-container {
                padding: 90px 10px 25px;
            }

            .login-card {
                padding: 80px 15px 25px 15px;
                border-radius: 15px;
            }

            .login-logo-float {
                width: 160px;
                height: 160px;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 0.4rem;
            }

            .text-white-50 {
                font-size: 0.85rem;
            }

            .form-label {
                font-size: 13px;
                margin-bottom: 0.3rem;
            }

            .form-control {
                font-size: 13px;
                padding: 10px 12px;
            }

            .input-group-text {
                padding: 10px 12px;
            }

            .btn-custom {
                font-size: 0.95rem;
                padding: 11px;
            }

            .alert {
                font-size: 12px;
                padding: 8px;
            }

            p {
                font-size: 13px;
            }

            .mb-3 {
                margin-bottom: 0.875rem !important;
            }

            .mt-3 {
                margin-top: 0.875rem !important;
            }
        }

        @media (max-width: 375px) {
            .login-logo-float {
                width: 150px;
                height: 150px;
            }

            h2 {
                font-size: 1.35rem;
            }

            .form-control, .input-group-text {
                font-size: 12px;
                padding: 9px 10px;
            }

            .btn-custom {
                font-size: 0.9rem;
                padding: 10px;
            }
        }
    </style>
</head>
<body>


   <!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="homepage.php">
            SCOUT MASTER
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php#events">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php#about">About</a></li>
                <li class="nav-item"><a class="nav-link active" href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
            </ul>
        </div>
    </div>
</nav>

    <!-- LOGIN FORM -->
    <div class="login-container">
        <div class="login-wrapper">
            <img src="images/homelogo.png" alt="Scout Master Logo" class="login-logo-float">
            <div class="login-card">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Welcome Back</h2>
                    <p class="text-white-50">Login to your account</p>
                </div>

            <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope-fill me-2"></i>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock-fill me-2"></i>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required>
                        <span class="input-group-text" onclick="togglePassword('password', this)">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn btn-custom w-100">Login</button>
            </form>

            <p class="mt-3 text-center"><a href="forgot_password.php">Forgot Password?</a></p>
            <p class="mt-3 text-center">Don't have an account? <a href="register.php">Register here</a></p>
        </div><!-- end login-card -->
        </div><!-- end login-wrapper -->
    </div><!-- end login-container -->

    <script>
        function togglePassword(inputId, iconSpan) {
            const input = document.getElementById(inputId);
            const icon = iconSpan.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
