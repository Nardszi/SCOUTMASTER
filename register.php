<?php
// Load Brevo classes
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;

// Load Composer's autoloader
require 'vendor/autoload.php';

session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name']);
    $school   = trim($_POST['school']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $status   = isset($_POST['status']) ? $_POST['status'] : 'active'; // Default status
    $birthday = $_POST['birthday'];
    $age      = $_POST['age'];
    $gender   = $_POST['gender'];
    $approved = 0;
    $email_verified = 0;
    $verification_code = rand(100000, 999999);

    if (!isset($_POST['terms'])) {
        $error = "You must agree to the Terms and Conditions.";
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    /* ================= IMAGE UPLOAD ================= */
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (!empty($_FILES["profile_picture"]["name"])) {

        $image_name = basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . time() . "_" . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check === false) {
            die("<div class='alert alert-danger'>File is not an image.</div>");
        }

        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
            die("<div class='alert alert-danger'>Only JPG, JPEG, and PNG files allowed.</div>");
        }

        if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            die("<div class='alert alert-danger'>Error uploading file.</div>");
        }

    } elseif (!empty($_POST["captured_image"])) {

        $image_data = $_POST["captured_image"];
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = base64_decode($image_data);

        $target_file = $target_dir . time() . "_captured.png";
        if (!file_put_contents($target_file, $image_data)) {
            die("<div class='alert alert-danger'>Error saving captured image.</div>");
        }

    } else {
        die("<div class='alert alert-danger'>Please upload or take a picture.</div>");
    }

    /* ================= INSERT USER ================= */
    mysqli_begin_transaction($conn);
    try {
        // 1. Validate Membership Card and Scout Fields (Only for Scouts)
        $membership_card = '';
        $scout_type = NULL;
        $position = NULL;
        
        if ($role === 'scout') {
            $membership_card = trim($_POST['membership_card'] ?? '');
            if (empty($membership_card)) {
                throw new Exception("Membership Card Number is required for Scouts.");
            }
            
            // Get scout type (Boy Scout or Outfit Scout)
            $scout_type = trim($_POST['scout_type'] ?? '');
            if (empty($scout_type) || !in_array($scout_type, ['boy_scout', 'outfit_scout'])) {
                throw new Exception("Please select a valid Scout Type (Boy Scout or Outfit Scout).");
            }
            
            // Get position - stored in status field
            $position = trim($_POST['position'] ?? '');
            if (empty($position) || !in_array($position, ['normal_scout', 'platoon_leader', 'troop_leader'])) {
                throw new Exception("Please select a valid Position.");
            }
            
            // Override status with position for scouts
            $status = $position;

            // Check if card exists in admin_scout_archive (Approved)
            $checkCard = mysqli_prepare($conn, "SELECT id FROM admin_scout_archive WHERE membership_card = ? AND archive_status = 'Approved'");
            mysqli_stmt_bind_param($checkCard, "s", $membership_card);
            mysqli_stmt_execute($checkCard);
            $cardResult = mysqli_stmt_get_result($checkCard);
            
            if (mysqli_num_rows($cardResult) === 0) {
                throw new Exception("Invalid Membership Card Number. Please ensure your card is registered and approved in the system.");
            }

            // Check if card is already used in scout_profiles
            $checkUsed = mysqli_prepare($conn, "SELECT id FROM scout_profiles WHERE membership_card = ?");
            mysqli_stmt_bind_param($checkUsed, "s", $membership_card);
            mysqli_stmt_execute($checkUsed);
            if (mysqli_num_rows(mysqli_stmt_get_result($checkUsed)) > 0) {
                throw new Exception("This Membership Card has already been used to register an account.");
            }
        }

        // 2. Insert User
        $query = "
            INSERT INTO users 
            (name, school, email, password, role, status, profile_picture, approved, verification_code, email_verified, membership_card, scout_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            "sssssssisiss",
            $name,
            $school,
            $email,
            $password,
            $role,
            $status,
            $target_file,
            $approved,
            $verification_code,
            $email_verified,
            $membership_card,
            $scout_type
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_error($conn));
        }
        $user_id = mysqli_insert_id($conn);

        // 3. Insert into scout_profiles if role is scout
        if ($role === 'scout') {
            $paid_status = 'Unpaid';
            $stmt_profile = mysqli_prepare($conn, "INSERT INTO scout_profiles (user_id, gender, birthday, age, grade_level, paid_status, membership_card) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_profile, "ississs", $user_id, $gender, $birthday, $age, $status, $paid_status, $membership_card);
            if (!mysqli_stmt_execute($stmt_profile)) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // 4. Clean up pending registrations
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE email = ?");
        mysqli_stmt_bind_param($delete_stmt, "s", $email);
        mysqli_stmt_execute($delete_stmt);

        mysqli_commit($conn);

        // 5. Success Actions (Log & Email)
        logActivity($conn, $user_id, 'User Self-Registration', "New user '$name' registered and is awaiting email verification.");

        // Send Verification Email
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
                .code { font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #28a745; margin: 20px 0; background: #e8f5e9; padding: 15px; border-radius: 5px; display: inline-block; }
                .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin:0;'>Scout Master</h1>
                </div>
                <div class='content'>
                    <h2>Verify Your Account</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Thank you for joining us! Please use the verification code below to complete your registration:</p>
                    <div class='code'>$verification_code</div>
                    <p>If you did not create an account, please ignore this email.</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Scout Master. All rights reserved.
                </div>
            </div>
        </body>
        </html>";

        $sendSmtpEmail = new SendSmtpEmail([
            'subject' => 'Verify your Scout Master Account',
            'sender' => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
            'to' => [['email' => $email, 'name' => $name]],
            'htmlContent' => $emailContent
        ]);

        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            error_log("Email sent successfully to $email. Message ID: " . json_encode($result));
        } catch (Exception $e) {
            // Log detailed error if email fails, but allow registration to proceed
            error_log("Brevo Email Error: " . $e->getMessage());
            error_log("Brevo Response Body: " . $e->getResponseBody());
            $_SESSION['email_error'] = "Registration successful but verification email failed to send. Please contact admin.";
        }

        // Redirect to verification page
        header("Location: verify_email.php?email=" . urlencode($email));
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Scout Master</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include('favicon_header.php'); ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    padding-bottom: 50px;
}

/* --- NAVBAR --- */
.navbar {
    padding: 1rem 1.5rem;
    background: transparent;
    transition: background-color 0.4s ease;
}
.navbar.scrolled {
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(10px);
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

.register-container {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 120px;
}

.register-wrapper {
    width: 100%;
    max-width: 650px;
    position: relative;
    padding-top: 110px; /* half the logo height */
}

.register-logo-float {
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

.register-card {
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

.form-control, .form-select {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}
.form-control:focus, .form-select:focus {
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
.form-select option {
    background-color: #333;
    color: white;
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
}
.btn-custom:hover {
    background-color: #218838;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.image-preview {
    width: 110px;
    height: 110px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.3);
    display: block;
    margin: 10px auto;
}

#video {
    width: 100%;
    border-radius: 10px;
    border: 2px solid rgba(255,255,255,0.3);
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

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    body {
        overflow-y: auto;
        height: auto;
        min-height: 100vh;
        padding-bottom: 30px;
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

    .register-container {
        padding-top: 90px;
        padding-bottom: 30px;
        padding-left: 15px;
        padding-right: 15px;
    }

    .register-wrapper {
        padding-top: 60px;
    }

    .register-card {
        max-width: 100%;
        padding: 80px 20px 30px 20px;
        margin: 0;
        border-radius: 18px;
    }

    .register-logo-float {
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

    .form-control, .form-select {
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

    .row {
        margin-left: 0;
        margin-right: 0;
    }

    .col-md-6 {
        padding-left: 0;
        padding-right: 0;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }

    .mb-4 {
        margin-bottom: 1.25rem !important;
    }

    .image-preview {
        width: 100px;
        height: 100px;
        margin: 8px auto;
    }

    #video {
        max-height: 250px;
        object-fit: cover;
    }

    .form-check-label {
        font-size: 13px;
    }
}

@media (max-width: 576px) {
    body {
        padding-bottom: 25px;
    }

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

    .register-container {
        padding-top: 80px;
        padding-bottom: 25px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .register-card {
        padding: 80px 15px 25px 15px;
        border-radius: 15px;
    }

    .register-logo-float {
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

    .form-control, .form-select {
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

    .mb-4 {
        margin-bottom: 1rem !important;
    }

    .mt-2 {
        margin-top: 0.5rem !important;
    }

    .mt-3 {
        margin-top: 0.875rem !important;
    }

    .image-preview {
        width: 90px;
        height: 90px;
        margin: 6px auto;
    }

    #video {
        max-height: 220px;
    }

    .form-check-label {
        font-size: 12px;
        line-height: 1.4;
    }

    .form-check-input {
        margin-top: 0.15rem;
    }

    .bi {
        font-size: 0.9rem;
    }
}

@media (max-width: 375px) {
    .register-container {
        padding-top: 75px;
    }

    .register-card {
        padding: 20px 12px;
    }

    h2 {
        font-size: 1.35rem;
    }

    .form-control, .form-select, .input-group-text {
        font-size: 12px;
        padding: 9px 10px;
    }

    .btn-custom {
        font-size: 0.9rem;
        padding: 10px;
    }

    .image-preview {
        width: 80px;
        height: 80px;
    }

    #video {
        max-height: 200px;
    }
}
</style>
</head>

<body>

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
                <li class="nav-item"><a class="nav-link " href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link active" href="register.php">Register</a></li>
        </ul>
    </div>
</div>
</nav>

<div class="container register-container">
<div class="register-wrapper">
    <img src="images/homelogo.png" alt="Scout Master Logo" class="register-logo-float">
    <div class="register-card">

    <div class="text-center mb-4">
        <h2 class="fw-bold">Create Account</h2>
        <p class="text-white-50">Join the Scout Master community</p>
    </div>

<?php if (!empty($success)) { ?>
<div class="alert alert-success">
    Registration successful! Please wait for admin approval.
</div>
<?php } ?>

<?php if (!empty($error)) { ?>
<div class="alert alert-danger">
    Error: <?= htmlspecialchars($error) ?>
</div>
<?php } ?>

<form method="POST" enctype="multipart/form-data" id="registerForm">

<div class="mb-3">
    <label class="form-label"><i class="bi bi-person-fill me-2"></i>Full Name</label>
    <input type="text" name="name" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-building-fill me-2"></i>School</label>
    <input type="text" name="school" class="form-control" placeholder="e.g School" required>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-envelope-fill me-2"></i>Email</label>
    <input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-lock-fill me-2"></i> Create Password</label>
    <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" required>
        <span class="input-group-text" onclick="togglePassword('password', this)">
            <i class="bi bi-eye"></i>
        </span>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-lock-fill me-2"></i>Confirm Password</label>
    <div class="input-group">
        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        <span class="input-group-text" onclick="togglePassword('confirm_password', this)">
            <i class="bi bi-eye"></i>
        </span>
    </div>
    <div id="passwordError" class="text-danger mt-1" style="display:none; font-size: 0.9em;">Passwords do not match!</div>
</div>

<div class="row">
<div class="col-md-6 mb-3">
    <label class="form-label"><i class="bi bi-person-badge-fill me-2"></i>Role</label>
    <select name="role" class="form-select">
        <option value="scout">Scout</option>
        <option value="scout_leader">Scout Leader</option>
    </select>
</div>
</div>

<div class="mb-3" id="membership-card-group" style="display:none;">
    <label class="form-label"><i class="bi bi-card-heading me-2"></i>Membership Card Number</label>
    <input type="text" name="membership_card" class="form-control" placeholder="Enter your Membership Card Number">
</div>

<div class="mb-3" id="scout-type-group" style="display:none;">
    <label class="form-label"><i class="bi bi-person-badge me-2"></i>Scout Type</label>
    <select name="scout_type" class="form-select">
        <option value="">Select Scout Type</option>
        <option value="boy_scout">Boy Scout</option>
        <option value="outfit_scout">Outfit Scout</option>
    </select>
</div>

<div class="mb-3" id="position-group" style="display:none;">
    <label class="form-label"><i class="bi bi-award me-2"></i>Position</label>
    <select name="position" class="form-select">
        <option value="">Select Position</option>
        <option value="normal_scout">Normal Scout</option>
        <option value="platoon_leader">Platoon Leader</option>
        <option value="troop_leader">Troop Leader</option>
    </select>
</div>

<!-- Hidden status field for non-scouts -->
<input type="hidden" name="status" id="status-field" value="active">

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label"><i class="bi bi-calendar-event me-2"></i>Birthday</label>
        <input type="date" name="birthday" id="birthday" class="form-control" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label"><i class="bi bi-123 me-2"></i>Age</label>
        <input type="number" name="age" id="age" class="form-control" required readonly>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-gender-ambiguous me-2"></i>Gender</label>
    <select name="gender" class="form-select" required>
        <option value="" disabled selected>Select Gender</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
    </select>
</div>

<div class="mb-3">
    <label class="form-label"><i class="bi bi-image-fill me-2"></i>Upload Profile Picture</label>
    <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewImage(event)">
    <img id="uploadPreview" class="image-preview" src="uploads/default.png">
</div>

<div class="mb-3 text-center">
    <label class="form-label"><i class="bi bi-camera-fill me-2"></i>Or Take a Picture</label>
    <video id="video" autoplay></video>
    <canvas id="canvas" width="320" height="240" hidden></canvas>
    <button type="button" class="btn btn-custom mt-2" id="capture-btn">Capture</button>
    <input type="hidden" name="captured_image" id="captured_image">
</div>

<div class="mb-3 form-check">
    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
    <label class="form-check-label" for="terms">I agree to the <a href="terms_and_conditions.php" target="_blank">Terms and Conditions</a></label>
</div>

<button type="submit" class="btn btn-custom w-100">Register</button>

</form>

<p class="mt-3 text-center">Already have an account? <a href="login.php">Login here</a></p>

</div><!-- end register-card -->
</div><!-- end register-wrapper -->
</div><!-- end register-container -->

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

window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

document.getElementById('registerForm').addEventListener('submit', function(event) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const errorDiv = document.getElementById('passwordError');

    if (password !== confirmPassword) {
        event.preventDefault();
        errorDiv.style.display = 'block';
        document.getElementById('confirm_password').focus();
    } else {
        errorDiv.style.display = 'none';
    }
});

// Real-time password match validation
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const errorDiv = document.getElementById('passwordError');

function checkPasswordMatch() {
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    
    if (confirmPassword.length > 0) {
        if (password !== confirmPassword) {
            errorDiv.style.display = 'block';
            confirmPasswordInput.style.borderColor = '#dc3545';
        } else {
            errorDiv.style.display = 'none';
            confirmPasswordInput.style.borderColor = '#28a745';
        }
    } else {
        errorDiv.style.display = 'none';
        confirmPasswordInput.style.borderColor = '';
    }
}

passwordInput.addEventListener('input', checkPasswordMatch);
confirmPasswordInput.addEventListener('input', checkPasswordMatch);

function previewImage(event) {
    const reader = new FileReader();
    reader.onload = () => document.getElementById('uploadPreview').src = reader.result;
    reader.readAsDataURL(event.target.files[0]);
}

const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const captureBtn = document.getElementById('capture-btn');
const capturedInput = document.getElementById('captured_image');

navigator.mediaDevices.getUserMedia({ video: true })
.then(stream => video.srcObject = stream)
.catch(err => console.log("Camera denied", err));

captureBtn.onclick = () => {
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    const data = canvas.toDataURL('image/png');
    capturedInput.value = data;
    document.getElementById('uploadPreview').src = data;
    alert("Picture captured!");
};

// Auto-calculate age
const birthdayInput = document.getElementById('birthday');
if (birthdayInput) {
    birthdayInput.addEventListener('change', function() {
        const birthday = new Date(this.value);
        if (!isNaN(birthday.getTime())) {
            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const m = today.getMonth() - birthday.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            document.getElementById('age').value = age > 0 ? age : 0;
        }
    });
}

// Toggle Membership Card, Scout Type, and Position fields based on Role
const roleSelect = document.querySelector('select[name="role"]');
const cardGroup = document.getElementById('membership-card-group');
const scoutTypeGroup = document.getElementById('scout-type-group');
const positionGroup = document.getElementById('position-group');
const cardInput = cardGroup.querySelector('input');
const scoutTypeSelect = scoutTypeGroup.querySelector('select');
const positionSelect = positionGroup.querySelector('select');
const statusField = document.getElementById('status-field');

function toggleScoutFields() {
    const isScout = roleSelect.value === 'scout';
    const isScoutLeader = roleSelect.value === 'scout_leader';
    cardGroup.style.display = (isScout || isScoutLeader) ? 'block' : 'none';
    scoutTypeGroup.style.display = isScout ? 'block' : 'none';
    positionGroup.style.display = isScout ? 'block' : 'none';
    cardInput.required = (isScout || isScoutLeader);
    scoutTypeSelect.required = isScout;
    positionSelect.required = isScout;
    
    // Set status field based on role
    if (!isScout) {
        statusField.value = 'active';
    }
}

// Update status field when position changes for scouts
if (positionSelect) {
    positionSelect.addEventListener('change', function() {
        if (roleSelect.value === 'scout') {
            statusField.value = this.value || 'active';
        }
    });
}

roleSelect.addEventListener('change', toggleScoutFields);
toggleScoutFields(); // Run on load
</script>

</body>
</html>
