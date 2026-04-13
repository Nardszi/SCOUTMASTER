<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Profile Picture Upload
if (isset($_POST['upload_pfp'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        $new_filename = time() . "_" . $user_id . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_types)) {
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $target_file, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    logActivity($conn, $user_id, 'Update Profile Picture', 'Uploaded new profile picture');
                    $message = "Profile picture updated successfully.";
                } else {
                    $error = "Database error.";
                }
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    } else {
        $error = "Please select a file.";
    }
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (password_verify($current_password, $row['password'])) {
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $new_hashed, $user_id);
                if (mysqli_stmt_execute($update_stmt)) {
                    logActivity($conn, $user_id, 'Change Password', 'Password changed successfully');
                    $message = "Password changed successfully.";
                } else {
                    $error = "Error updating password.";
                }
            } else {
                $error = "Incorrect current password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            background: url("images/wall3.jpg") no-repeat center center/cover fixed; 
            color: white; 
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }
        body::before { 
            content: ""; 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.55); 
            z-index: -1; 
            pointer-events: none;
        }
        .wrapper { 
            display: flex; 
            min-height: 100vh; 
            position: relative; 
            z-index: 0; 
        }
        .main { 
            flex: 1; 
            margin-left: 240px; 
            padding: 30px; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            transition: margin-left 0.3s ease-in-out;
            z-index: 1;
        }
        body.sidebar-collapsed .main { 
            margin-left: 80px; 
        }
        .main > * { 
            position: relative; 
            z-index: 1; 
            pointer-events: auto;
        }
        .glass { 
            background: rgba(255,255,255,0.15); 
            backdrop-filter: blur(12px); 
            border-radius: 20px; 
            padding: 30px; 
            border: 1px solid rgba(255,255,255,0.15); 
            margin-bottom: 20px;
            pointer-events: auto;
        }
        .form-control, .form-select { 
            background: rgba(255,255,255,0.1); 
            border: 1px solid rgba(255,255,255,0.2); 
            color: white; 
        }
        .form-control::placeholder {
            color: rgba(255,255,255,0.5);
        }
        .form-control:focus, .form-select:focus { 
            background: rgba(255,255,255,0.2); 
            color: white; 
            border-color: #28a745; 
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        .form-label {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        .btn-primary { 
            background-color: #28a745; 
            border-color: #28a745;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-primary:hover { 
            background-color: #218838; 
            border-color: #1e7e34;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        .alert {
            border-radius: 12px;
            border: none;
        }
        h2, h4 {
            color: white;
            font-weight: 700;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Mobile menu toggle button */
            .mobile-menu-toggle {
                display: flex !important;
                position: fixed !important;
                top: 15px !important;
                left: 15px !important;
                z-index: 99999 !important;
                width: 44px !important;
                height: 44px !important;
                background: linear-gradient(135deg, #28a745, #1e7e34) !important;
                border: none !important;
                border-radius: 10px !important;
                cursor: pointer !important;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4) !important;
                align-items: center !important;
                justify-content: center !important;
                pointer-events: auto !important;
            }
            
            .mobile-menu-toggle i {
                font-size: 18px !important;
            }
            
            .main {
                margin-left: 0 !important;
                padding: 70px 15px 15px;
            }
            
            body.sidebar-collapsed .main {
                margin-left: 0 !important;
            }
            
            .glass {
                padding: 20px 15px;
                border-radius: 15px;
            }
            
            h2 {
                font-size: 24px;
                margin-bottom: 20px;
            }
            
            h4 {
                font-size: 18px;
                margin-bottom: 15px;
            }
            
            /* Stack columns vertically on mobile */
            .row > .col-md-6 {
                margin-bottom: 30px;
            }
            
            .row > .col-md-6:last-child {
                margin-bottom: 0;
            }
            
            /* Form controls - better touch targets */
            .form-control,
            .form-select {
                font-size: 16px;
                padding: 12px 15px;
                min-height: 44px;
                border-radius: 10px;
            }
            
            .form-label {
                font-size: 14px;
                margin-bottom: 8px;
            }
            
            /* Buttons - touch friendly */
            .btn {
                padding: 12px 20px;
                font-size: 16px;
                min-height: 44px;
                width: 100%;
                border-radius: 10px;
                touch-action: manipulation;
            }
            
            /* Alerts */
            .alert {
                font-size: 14px;
                padding: 12px 15px;
                margin-bottom: 15px;
            }
            
            /* Ensure all interactive elements are touchable */
            button, a, input, select, textarea {
                pointer-events: auto !important;
                touch-action: manipulation !important;
            }
        }
        
        @media (max-width: 576px) {
            .mobile-menu-toggle {
                width: 40px !important;
                height: 40px !important;
                top: 12px !important;
                left: 12px !important;
            }
            
            .mobile-menu-toggle i {
                font-size: 16px !important;
            }
            
            .main {
                padding: 65px 10px 10px;
            }
            
            .glass {
                padding: 15px 12px;
                border-radius: 12px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            h4 {
                font-size: 16px;
            }
            
            .form-control,
            .form-select {
                font-size: 16px;
                padding: 10px 12px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <?php include('navbar.php'); ?>
        <div class="container">
            <div class="glass">
                <h2 class="mb-4"><i class="fas fa-cog me-2"></i>Account Settings</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <?php if ($_SESSION['role'] !== 'scout'): ?>
                    <div class="col-md-6">
                        <h4 class="mb-3">Change Profile Picture</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Select Image</label>
                                <input type="file" name="profile_picture" class="form-control" required>
                            </div>
                            <button type="submit" name="upload_pfp" class="btn btn-primary">Update Picture</button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <div class="<?php echo ($_SESSION['role'] === 'scout') ? 'col-12' : 'col-md-6'; ?>">
                        <h4 class="mb-3">Change Password</h4>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>