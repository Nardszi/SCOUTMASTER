<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout_leader') {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch leader details
$query = "SELECT users.name, users.email, users.profile_picture, users.membership_card,
                 troops.troop_name, troops.id AS troop_id 
          FROM users 
          LEFT JOIN troops ON users.id = troops.scout_leader_id 
          WHERE users.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Ensure troop name exists
$troop_name = isset($user['troop_name']) ? $user['troop_name'] : 'Not Assigned';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);

    $update_query = "UPDATE users SET name = ?, email = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $user_id);
    mysqli_stmt_execute($stmt);
    
    // Update session name automatically
    $_SESSION['name'] = $name;
    
    logActivity($conn, $user_id, 'Update Profile', 'Updated name/email');
    header("Location: scout_leader_profile.php?updated=true");
    exit();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $target_dir = "uploads/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Check if file was uploaded without errors
    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Upload failed with error code: " . $_FILES['profile_picture']['error'];
        header("Location: scout_leader_profile.php");
        exit();
    }
    
    $image_name = time() . "_" . basename($_FILES['profile_picture']['name']);  
    $target_file = $target_dir . $image_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    // Validate file type
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type! Only JPG, JPEG, PNG & GIF allowed.";
        header("Location: scout_leader_profile.php");
        exit();
    }
    
    // Validate file size (max 5MB)
    if ($_FILES['profile_picture']['size'] > 5000000) {
        $_SESSION['error'] = "File is too large! Maximum size is 5MB.";
        header("Location: scout_leader_profile.php");
        exit();
    }
    
    // Validate it's actually an image
    $check = getimagesize($_FILES['profile_picture']['tmp_name']);
    if ($check === false) {
        $_SESSION['error'] = "File is not a valid image.";
        header("Location: scout_leader_profile.php");
        exit();
    }

    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
        // Delete old profile picture if it exists and is not the default
        if (!empty($user['profile_picture']) && 
            $user['profile_picture'] !== 'images/default_profile.png' && 
            file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }
        
        $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $target_file, $user_id);
        mysqli_stmt_execute($stmt);
        logActivity($conn, $user_id, 'Update Profile Picture', 'Uploaded new profile picture');
        $_SESSION['success'] = "Profile picture updated successfully!";
        header("Location: scout_leader_profile.php");
        exit();
    } else {
        $_SESSION['error'] = "Error uploading image. Please check folder permissions.";
        header("Location: scout_leader_profile.php");
        exit();
    }
}

// Fetch assigned scouts
$scouts_query = "SELECT name FROM users WHERE troop_id = ?";
$stmt = mysqli_prepare($conn, $scouts_query);
mysqli_stmt_bind_param($stmt, "i", $user['troop_id']);
mysqli_stmt_execute($stmt);
$scouts_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scout Leader Profile</title>
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body {
    background: #000;
    font-family: 'Poppins', sans-serif;
    margin:0; padding:0;
    color: white;
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.main{
    flex:1;
    margin-left: 240px;
    padding:30px;
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/wall3.jpg') no-repeat center center/cover;
    position:relative;
    display:flex;
    flex-direction:column;
    transition: margin-left 0.3s ease-in-out;
}

body.sidebar-collapsed .main {
    margin-left: 80px;
}

.main::before {
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.2);
    z-index:0;
}

.main > * {
    position:relative;
    z-index:1;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(10px);
    border-radius:25px;
    padding:30px;
    margin-bottom:20px;
    max-width: 900px;
    margin: 0 auto;
}

.profile-img {
    width: 180px;
    height: 180px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid #28a745;
    margin-bottom: 20px;
    box-shadow: 0 0 25px rgba(40, 167, 69, 0.5);
}

.form-label {
    font-weight: 500;
    color: rgba(0, 0, 0, 0.8);
}

.form-control, .form-control[readonly] {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    border-radius: 10px;
    padding: 10px;
}
.form-control:focus {
    background: rgba(0,0,0,0.4);
    color: white;
    box-shadow: none;
    border-color: #28a745;
}
.form-control::file-selector-button {
    background: #28a745;
    border: none;
    color: white;
    border-radius: 5px;
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.2s;
}
.form-control::file-selector-button:hover {
    background: #218838;
}

.btn-submit {
    background: #28a745;
    color: white;
    border: none;
    border-radius: 20px;
    padding: 10px 25px;
    font-weight: 600;
    transition: background 0.3s;
}
.btn-submit:hover {
    background: #218838;
}

.list-group-item {
    background: rgba(0,0,0,0.25);
    border: none;
    color: white;
    margin-bottom: 8px;
    border-radius: 10px;
}

.alert {
    border-radius: 15px;
    border: none;
}

.alert-success {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.alert-danger {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.btn-close {
    filter: brightness(0) invert(1);
}
</style>

</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <div class="main">
        <!-- Top Navbar -->
        <?php include('navbar.php'); ?>

        <div class="glass">
            <h1 class="text-center mb-5" style="font-weight: 800;">LEADER PROFILE</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Profile updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-5">
                <div class="col-md-4 text-center">
                    <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'images/default_profile.png'; ?>" 
                         class="profile-img" alt="Profile Picture">

                    <!-- Profile Picture Upload -->
                    <form action="" method="post" enctype="multipart/form-data" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label">Change Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                            <small class="text-white-50 d-block mt-1">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                        </div>
                        <button type="submit" class="btn btn-submit w-100">Upload</button>
                    </form>
                </div>

                <div class="col-md-8">
                    <h4 class="mb-4">Profile Information</h4>
                    <!-- Editable Profile Info -->
                    <form action="" method="post" class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Membership Card</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['membership_card'] ?? 'Not Assigned'); ?>" readonly>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-submit">Update Profile</button>
                    </form>

                    <hr class="my-4" style="border-color: rgba(255,255,255,0.2);">

                    <h4 class="mb-3">Troop Information</h4>
                    <p><strong>Troop Name:</strong> <?= htmlspecialchars($troop_name); ?></p>

                    <h5 class="mt-4">Assigned Scouts</h5>
                    <ul class="list-group">
                        <?php if (mysqli_num_rows($scouts_result) > 0): ?>
                            <?php while ($scout = mysqli_fetch_assoc($scouts_result)): ?>
                                <li class="list-group-item"><?= htmlspecialchars($scout['name']); ?></li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="list-group-item">No assigned scouts.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
