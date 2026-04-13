<?php
// Load Brevo classes
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;

require 'vendor/autoload.php';
session_start();
include('config.php');

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name, is_archived FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Do not allow password reset for archived accounts
        if (isset($row['is_archived']) && $row['is_archived'] == 1) {
            // For security, give a generic message.
            $msg = "Email address not found.";
            $msg_type = "danger";
        } else {
        $token = bin2hex(random_bytes(32));
        // Token expires in 1 hour
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Update user with token
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $update->bind_param("sss", $token, $expires, $email);

        if ($update->execute()) {
            // Send Email via Brevo
            // Use dynamic URL instead of hardcoded localhost
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $domain = $_SERVER['HTTP_HOST'];
            $resetLink = $protocol . "://" . $domain . "/reset_password?email=" . urlencode($email) . "&token=" . $token;

            $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', BREVO_API_KEY);
            $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

            $emailContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 20px auto; background: #ffffff; padding: 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
                    .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
                    .content { padding: 30px; text-align: center; color: #333; }
                    .btn { display: inline-block; background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #777; }
                    .link-text { word-break: break-all; color: #28a745; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin:0;'>Scout Master</h1>
                    </div>
                    <div class='content'>
                        <h2>Reset Your Password</h2>
                        <p>Hello <strong>" . htmlspecialchars($row['name']) . "</strong>,</p>
                        <p>You requested a password reset. Click the button below to create a new password:</p>
                        <a href='$resetLink' class='btn'>Reset Password</a>
                        <p style='margin-top: 20px; font-size: 14px;'>Or copy and paste this link into your browser:</p>
                        <p class='link-text'>$resetLink</p>
                        <p><em>This link expires in 1 hour.</em></p>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Scout Master. All rights reserved.
                    </div>
                </div>
            </body>
            </html>";

            $sendSmtpEmail = new SendSmtpEmail([
                'subject' => 'Reset your Password',
                'sender' => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
                'to' => [['email' => $email, 'name' => $row['name']]],
                'htmlContent' => $emailContent
            ]);

            try {
                $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
                error_log("Password reset email sent to $email");
                $msg = "A reset link has been sent to your email.";
                $msg_type = "success";
            } catch (Exception $e) {
                error_log("Failed to send password reset email: " . $e->getMessage());
                if (method_exists($e, 'getResponseBody')) {
                    error_log("Brevo Response: " . $e->getResponseBody());
                }
                $msg = "Error sending email: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
        }
    } else {
        // For security, we usually don't say if email doesn't exist, but for now:
        $msg = "Email address not found.";
        $msg_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Scout Master</title>
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
            max-width: 400px;
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
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: none;
            border-color: #28a745;
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
        a {
            color: #28a745;
            text-decoration: none;
            transition: color 0.3s;
        }
        a:hover {
            color: #218838;
            text-decoration: underline;
        }
        .alert {
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1.5rem;
        }
        h2 {
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .text-white-50 {
            color: rgba(255, 255, 255, 0.7) !important;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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

            h2 {
                font-size: 1.75rem;
                margin-bottom: 0.875rem;
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

            h2 {
                font-size: 1.5rem;
                margin-bottom: 0.75rem;
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

            .mb-4 {
                margin-bottom: 1rem !important;
            }

            .mt-3 {
                margin-top: 0.875rem !important;
            }
        }

        @media (max-width: 375px) {
            .login-container {
                padding: 20px 8px;
            }

            .login-card {
                padding: 20px 12px;
            }

            h2 {
                font-size: 1.35rem;
            }

            .form-control {
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
    <div class="container login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="bi bi-key-fill" style="font-size: 3rem; color: #28a745; margin-bottom: 1rem;"></i>
                <h2 class="fw-bold">Forgot Password</h2>
                <p class="text-white-50">Enter your email to receive a reset link</p>
            </div>
            <?php if ($msg) { echo "<div class='alert alert-$msg_type'>$msg</div>"; } ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope-fill me-2"></i>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                <button type="submit" class="btn btn-custom">Send Reset Link</button>
            </form>
            <p class="mt-3 text-center"><a href="login.php"><i class="bi bi-arrow-left me-1"></i>Back to Login</a></p>
        </div>
    </div>
</body>
</html>