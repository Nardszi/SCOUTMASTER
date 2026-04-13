<?php
session_start();
include('config.php');

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $code = $_POST['code'];

    // Check if email and code match
    $query = "SELECT id, name FROM users WHERE email = ? AND verification_code = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        $user_id = $user['id'];
        // Verify the user
        $update = "UPDATE users SET email_verified = 1 WHERE email = ?";
        $stmt_update = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt_update, "s", $email);
        
        if (mysqli_stmt_execute($stmt_update)) {
            logActivity($conn, $user_id, 'Email Verification', 'User verified email successfully.');
            $msg = "Email verified successfully! Redirecting to login...";
            $msg_type = "success";
            header("refresh:2;url=login.php");
        } else {
            $msg = "Database error occurred.";
            $msg_type = "danger";
        }
    } else {
        $msg = "Invalid email or verification code.";
        $msg_type = "danger";
    }
}

$email_val = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Scout Master</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 40px;
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
            padding: 12px 15px;
            font-size: 1rem;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: none;
            border-color: #28a745;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
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
            width: 100%;
        }
        .btn-custom:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .alert {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1.5rem;
        }
        h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .text-white-50 {
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 0.95rem;
        }
        a {
            color: #28a745;
            text-decoration: none;
            transition: color 0.3s;
        }
        a:hover {
            color: #218838;
            text-decoration: underline;
        }
        .verification-icon {
            font-size: 3.5rem;
            color: #28a745;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .code-input {
            text-align: center;
            letter-spacing: 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .info-box {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            body {
                overflow-y: auto;
                height: auto;
                min-height: 100vh;
                background-attachment: scroll;
            }

            .login-container {
                height: auto;
                min-height: 100vh;
                padding: 30px 15px;
            }

            .login-card {
                max-width: 100%;
                padding: 30px 20px;
                margin: 0;
                border-radius: 18px;
            }

            .verification-icon {
                font-size: 3rem;
                margin-bottom: 0.875rem;
            }

            h2 {
                font-size: 1.75rem;
                margin-bottom: 0.4rem;
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

            .code-input {
                font-size: 1.3rem;
                letter-spacing: 0.4rem;
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

            .info-box {
                font-size: 0.85rem;
                padding: 10px;
                margin-bottom: 1rem;
            }

            p {
                font-size: 14px;
            }

            .mb-3 {
                margin-bottom: 1rem !important;
            }

            .mb-4 {
                margin-bottom: 1.25rem !important;
            }

            .mt-3 {
                margin-top: 1rem !important;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 25px 10px;
            }

            .login-card {
                padding: 25px 15px;
                border-radius: 15px;
            }

            .verification-icon {
                font-size: 2.5rem;
                margin-bottom: 0.75rem;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 0.3rem;
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

            .code-input {
                font-size: 1.2rem;
                letter-spacing: 0.3rem;
            }

            .btn-custom {
                font-size: 0.95rem;
                padding: 11px;
            }

            .alert {
                font-size: 12px;
                padding: 8px;
            }

            .info-box {
                font-size: 0.8rem;
                padding: 8px;
            }

            p {
                font-size: 13px;
            }

            .mb-3 {
                margin-bottom: 0.875rem !important;
            }

            .mb-4 {
                margin-bottom: 1rem !important;
            }

            .mt-3 {
                margin-top: 0.875rem !important;
            }

            .bi {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 375px) {
            .login-container {
                padding: 20px 8px;
            }

            .login-card {
                padding: 20px 12px;
            }

            .verification-icon {
                font-size: 2.2rem;
            }

            h2 {
                font-size: 1.35rem;
            }

            .form-control {
                font-size: 12px;
                padding: 9px 10px;
            }

            .code-input {
                font-size: 1.1rem;
                letter-spacing: 0.25rem;
            }

            .btn-custom {
                font-size: 0.9rem;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="bi bi-envelope-check-fill verification-icon"></i>
                <h2 class="fw-bold">Verify Your Email</h2>
                <p class="text-white-50">Enter the 6-digit code sent to your email</p>
            </div>

            <div class="info-box">
                <i class="bi bi-info-circle-fill me-2"></i>
                <small>Check your inbox and spam folder for the verification code.</small>
            </div>

            <?php if ($msg) { echo "<div class='alert alert-$msg_type'>$msg</div>"; } ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope-fill me-2"></i>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= $email_val ?>" placeholder="your@email.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-shield-lock-fill me-2"></i>Verification Code</label>
                    <input type="text" name="code" class="form-control code-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                    <small class="text-white-50 d-block mt-2">
                        <i class="bi bi-clock-fill me-1"></i>Code expires in 1 hour
                    </small>
                </div>
                <button type="submit" class="btn btn-custom">
                    <i class="bi bi-check-circle-fill me-2"></i>Verify Email
                </button>
            </form>

            <p class="mt-3 text-center">
                <a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
            </p>
        </div>
    </div>

    <script>
        // Auto-format verification code input
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    </script>
</body>
</html>