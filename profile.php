<?php
session_start();
include('config.php');

/* =======================
   ACCESS CONTROL
======================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

/* =======================
   HANDLE PROFILE PICTURE UPLOAD
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = 'uploads/profile_pictures/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['profile_picture'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if ($file_error === 0) {
        if (in_array($file_ext, $allowed_extensions)) {
            if ($file_size <= 5000000) { // 5MB max
                // Generate unique filename
                $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $file_destination = $upload_dir . $new_file_name;
                
                // Delete old profile picture if exists
                $old_pic_query = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE id = ?");
                mysqli_stmt_bind_param($old_pic_query, "i", $user_id);
                mysqli_stmt_execute($old_pic_query);
                $old_pic_result = mysqli_stmt_get_result($old_pic_query);
                $old_pic_data = mysqli_fetch_assoc($old_pic_result);
                
                if (!empty($old_pic_data['profile_picture']) && file_exists($old_pic_data['profile_picture'])) {
                    unlink($old_pic_data['profile_picture']);
                }
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_destination)) {
                    // Update database
                    $update_pic = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update_pic, "si", $file_destination, $user_id);
                    
                    if (mysqli_stmt_execute($update_pic)) {
                        $_SESSION['success'] = "Profile picture updated successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to update profile picture in database.";
                    }
                } else {
                    $_SESSION['error'] = "Failed to upload file.";
                }
            } else {
                $_SESSION['error'] = "File size must be less than 5MB.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file.";
    }
    
    header("Location: profile.php");
    exit();
}

/* =======================
   HANDLE PROFILE UPDATE
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_email = trim($_POST['email']);
    $new_school = trim($_POST['school']);
    
    // Validate inputs
    if (empty($new_name) || empty($new_email)) {
        $_SESSION['error'] = "Name and email are required.";
    } else {
        // Check if email is already taken by another user
        $check_email = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($check_email, "si", $new_email, $user_id);
        mysqli_stmt_execute($check_email);
        $email_result = mysqli_stmt_get_result($check_email);
        
        if (mysqli_num_rows($email_result) > 0) {
            $_SESSION['error'] = "Email address is already in use by another account.";
        } else {
            // Update user information
            $update_query = "UPDATE users SET name = ?, email = ?, school = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sssi", $new_name, $new_email, $new_school, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $_SESSION['success'] = "Profile updated successfully!";
                $_SESSION['name'] = $new_name; // Update session name
                header("Location: profile.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update profile. Please try again.";
            }
        }
    }
}

/* =======================
   FETCH USER DATA
======================= */
$query = "
    SELECT 
        users.name,
        users.email,
        users.school,
        users.membership_card,
        users.profile_picture,
        users.scout_type,
        troops.troop_name,
        leaders.name AS troop_leader,
        ranks.rank_name
    FROM users
    LEFT JOIN troops ON users.troop_id = troops.id
    LEFT JOIN users AS leaders ON troops.scout_leader_id = leaders.id
    LEFT JOIN ranks ON users.rank_id = ranks.id
    WHERE users.id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

/* =======================
   FETCH EVENTS ATTENDED
======================= */
$events_query = "
    SELECT e.event_title, e.event_date
    FROM event_attendance ea
    JOIN events e ON ea.event_id = e.id
    WHERE ea.scout_id = ?
";

$stmt = mysqli_prepare($conn, $events_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$events_result = mysqli_stmt_get_result($stmt);

/* =======================
   FETCH COMPLETED BADGES
======================= */
$badges_query = "
    SELECT mb.id, mb.name, mb.icon_path, sbp.date_completed
    FROM scout_badge_progress sbp
    JOIN merit_badges mb ON sbp.merit_badge_id = mb.id
    WHERE sbp.scout_id = ? AND sbp.status = 'completed'
    ORDER BY sbp.date_completed DESC
";
$stmt = mysqli_prepare($conn, $badges_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$badges_result = mysqli_stmt_get_result($stmt);
$completed_badges = [];
while ($row = mysqli_fetch_assoc($badges_result)) {
    $completed_badges[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scout Profile</title>
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* BASE */
body{
    margin: 0;
    font-family: 'Inter', sans-serif;
    min-height:100vh;
    background:#000;
    color:white;
}

/* LAYOUT */
.wrapper{
    display:flex;
    min-height:100vh;
}

/* MAIN BACKGROUND */
.main{
    flex: 1;
    margin-left: 240px;
    padding: 30px;
    background:url("images/wall3.jpg") no-repeat center center/cover;
    position: relative;
    display:flex;
    flex-direction:column;
    transition: margin-left 0.3s ease-in-out;
}

body.sidebar-collapsed .main {
    margin-left: 80px;
}

/* OVERLAY */
.main::before{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.65);
    z-index:0;
}

.main > *{
    position:relative;
    z-index:1;
}

/* GLASS */
.glass{
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
    backdrop-filter:blur(20px);
    border-radius:24px;
    padding:40px;
    margin-bottom:24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.profile-header {
    text-align: center;
    padding-bottom: 30px;
    margin-bottom: 40px;
    border-bottom: 2px solid rgba(40, 167, 69, 0.3);
    position: relative;
}

.profile-img-container {
    position: relative;
    display: inline-block;
    margin-bottom: 20px;
}

.profile-img {
    width: 180px;
    height: 180px;
    object-fit: cover;
    border-radius: 50%;
    border: 5px solid #28a745;
    box-shadow: 0 0 40px rgba(40, 167, 69, 0.6), 0 8px 24px rgba(0, 0, 0, 0.4);
    transition: all 0.3s;
}

.profile-img:hover {
    transform: scale(1.05);
    box-shadow: 0 0 60px rgba(40, 167, 69, 0.8), 0 12px 32px rgba(0, 0, 0, 0.5);
}

.profile-name {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 15px 0;
    background: linear-gradient(135deg, #fff, #28a745);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.profile-rank {
    font-size: 1.1rem;
    font-weight: 700;
    color: #28a745;
    display: inline-block;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.25), rgba(30, 126, 52, 0.15));
    padding: 10px 24px;
    border-radius: 50px;
    margin-top: 10px;
    border: 2px solid rgba(40, 167, 69, 0.4);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    transition: all 0.3s;
}

.profile-rank:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.5);
}

.section-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s;
}

.section-card:hover {
    border-color: rgba(40, 167, 69, 0.3);
    box-shadow: 0 8px 24px rgba(40, 167, 69, 0.15);
    transform: translateY(-2px);
}

.profile-section-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 2px solid #28a745;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #28a745;
}

.profile-section-title i {
    font-size: 1.6rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 24px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border-left: 3px solid #28a745;
    transition: all 0.3s;
}

.info-item:hover {
    background: rgba(40, 167, 69, 0.1);
    transform: translateX(5px);
}

.info-item i {
    font-size: 1.8rem;
    color: #28a745;
    width: 50px;
    text-align: center;
    margin-right: 16px;
    flex-shrink: 0;
}

.info-label {
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 4px;
}

.info-value {
    font-size: 1.05rem;
    font-weight: 500;
    color: white;
}

.list-group-item {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.08), rgba(30, 126, 52, 0.04));
    border: 1px solid rgba(40, 167, 69, 0.2);
    color: white;
    margin-bottom: 10px;
    border-radius: 12px;
    transition: all 0.3s;
    padding: 16px;
}

.list-group-item:hover {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.15), rgba(30, 126, 52, 0.08));
    transform: translateX(8px);
    border-color: rgba(40, 167, 69, 0.4);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

/* Completed Badges Styles */
.completed-badge-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    text-decoration: none;
    color: white;
    transition: all 0.3s;
    height: 100%;
    padding: 20px 12px;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(30, 126, 52, 0.05));
    border-radius: 16px;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.completed-badge-item:hover {
    transform: translateY(-8px) scale(1.05);
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.2), rgba(30, 126, 52, 0.1));
    border-color: rgba(40, 167, 69, 0.4);
    box-shadow: 0 12px 32px rgba(40, 167, 69, 0.3);
    color: white;
}

.completed-badge-icon {
    width: 70px;
    height: 70px;
    object-fit: contain;
    margin-bottom: 12px;
    filter: drop-shadow(0 0 10px rgba(40, 167, 69, 0.6));
    transition: all 0.3s;
}

.completed-badge-item:hover .completed-badge-icon {
    filter: drop-shadow(0 0 20px rgba(40, 167, 69, 0.9));
    transform: scale(1.1);
}

.completed-badge-item .name {
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.3;
    margin-top: auto;
    margin-bottom: 6px;
}

.completed-badge-item .date {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
    font-weight: 500;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.15), rgba(30, 126, 52, 0.08));
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    border: 1px solid rgba(40, 167, 69, 0.3);
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 32px rgba(40, 167, 69, 0.3);
    border-color: rgba(40, 167, 69, 0.5);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #28a745;
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
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
        padding: 10px;
        padding-top: 70px;
    }
    
    body.sidebar-collapsed .main {
        margin-left: 0 !important;
    }
    
    .glass {
        padding: 15px 12px;
        border-radius: 14px;
        margin-bottom: 10px;
    }
    
    /* Profile Header - Horizontal Compact Layout */
    .profile-header {
        display: flex;
        align-items: center;
        text-align: left;
        padding-bottom: 12px;
        margin-bottom: 15px;
        gap: 15px;
    }
    
    .profile-img-container {
        margin-bottom: 0;
        flex-shrink: 0;
    }
    
    .profile-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .profile-img {
        width: 80px;
        height: 80px;
        border-width: 3px;
    }
    
    .profile-name {
        font-size: 1.3rem;
        margin-bottom: 5px;
        line-height: 1.2;
        text-align: left;
    }
    
    .profile-rank {
        font-size: 0.75rem;
        padding: 4px 12px;
        margin-top: 5px;
        display: inline-block;
        align-self: flex-start;
    }
    
    /* Section Cards - Minimal Padding */
    .section-card {
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .profile-section-title {
        font-size: 0.95rem;
        margin-bottom: 10px;
        padding-bottom: 6px;
        gap: 6px;
    }
    
    .profile-section-title i {
        font-size: 1rem;
    }
    
    /* Info Items - Compact Grid */
    .info-item {
        flex-direction: row;
        align-items: center;
        padding: 8px 10px;
        margin-bottom: 6px;
        border-radius: 8px;
    }
    
    .info-item i {
        font-size: 1.2rem;
        width: 30px;
        margin-right: 10px;
        margin-bottom: 0;
    }
    
    .info-label {
        font-size: 0.65rem;
        margin-bottom: 1px;
    }
    
    .info-value {
        font-size: 0.8rem;
        line-height: 1.2;
    }
    
    /* Stats Grid - 2 Columns Compact */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .stat-card {
        padding: 12px 10px;
        border-radius: 10px;
    }
    
    .stat-number {
        font-size: 1.6rem;
        margin-bottom: 3px;
    }
    
    .stat-label {
        font-size: 0.65rem;
        letter-spacing: 0.3px;
    }
    
    /* List Items - Minimal */
    .list-group-item {
        padding: 8px 10px;
        font-size: 0.8rem;
        margin-bottom: 5px;
        border-radius: 8px;
    }
    
    /* Badges Grid - 4 Columns */
    .row.g-3 {
        row-gap: 0.6rem !important;
        column-gap: 0.4rem !important;
    }
    
    .completed-badge-item {
        padding: 8px 5px;
        border-radius: 10px;
    }
    
    .completed-badge-icon {
        width: 42px;
        height: 42px;
        margin-bottom: 5px;
    }
    
    .completed-badge-item .name {
        font-size: 0.7rem;
        line-height: 1.15;
        margin-bottom: 2px;
    }
    
    .completed-badge-item .date {
        font-size: 0.6rem;
    }
    
    /* Limit visible items */
    .list-group-item:nth-child(n+4) {
        display: none;
    }
    
    /* Single column layout on mobile */
    .row > .col-lg-6 {
        margin-bottom: 0;
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
        padding: 8px;
        padding-top: 65px;
    }
    
    .glass {
        padding: 12px 10px;
        border-radius: 12px;
        margin-bottom: 8px;
    }
    
    /* Profile Header - Ultra Compact Horizontal */
    .profile-header {
        padding-bottom: 10px;
        margin-bottom: 12px;
        gap: 12px;
    }
    
    .profile-img {
        width: 70px;
        height: 70px;
        border-width: 2px;
    }
    
    .profile-name {
        font-size: 1.15rem;
        margin-bottom: 4px;
    }
    
    .profile-rank {
        font-size: 0.7rem;
        padding: 3px 10px;
        margin-top: 4px;
    }
    
    /* Section Cards - Ultra Minimal */
    .section-card {
        padding: 10px 8px;
        margin-bottom: 8px;
        border-radius: 8px;
    }
    
    .profile-section-title {
        font-size: 0.85rem;
        margin-bottom: 8px;
        padding-bottom: 5px;
        gap: 5px;
    }
    
    .profile-section-title i {
        font-size: 0.9rem;
    }
    
    /* Info Items - Ultra Compact */
    .info-item {
        padding: 6px 8px;
        margin-bottom: 5px;
        border-radius: 6px;
    }
    
    .info-item i {
        font-size: 1.1rem;
        width: 26px;
        margin-right: 8px;
    }
    
    .info-label {
        font-size: 0.6rem;
        margin-bottom: 1px;
    }
    
    .info-value {
        font-size: 0.75rem;
        line-height: 1.2;
    }
    
    /* Stats - 2 Columns Ultra Compact */
    .stats-grid {
        gap: 6px;
        margin-bottom: 10px;
    }
    
    .stat-card {
        padding: 10px 8px;
        border-radius: 8px;
    }
    
    .stat-number {
        font-size: 1.4rem;
        margin-bottom: 2px;
    }
    
    .stat-label {
        font-size: 0.6rem;
        letter-spacing: 0.2px;
    }
    
    /* List Items - Ultra Minimal */
    .list-group-item {
        padding: 6px 8px;
        font-size: 0.75rem;
        margin-bottom: 4px;
        border-radius: 6px;
        line-height: 1.3;
    }
    
    /* Badges - 4 Columns Ultra Compact */
    .row.g-3 {
        row-gap: 0.4rem !important;
        column-gap: 0.3rem !important;
    }
    
    .completed-badge-item {
        padding: 6px 3px;
        border-radius: 8px;
    }
    
    .completed-badge-icon {
        width: 38px;
        height: 38px;
        margin-bottom: 4px;
    }
    
    .completed-badge-item .name {
        font-size: 0.65rem;
        line-height: 1.1;
        margin-bottom: 2px;
    }
    
    .completed-badge-item .date {
        font-size: 0.55rem;
    }
    
    /* Limit visible items more aggressively */
    .list-group-item:nth-child(n+3) {
        display: none;
    }
    
    /* Show only first 8 badges in 2 rows */
    .completed-badge-item:nth-child(n+9) {
        display: none;
    }
    
    /* Add "View More" hint */
    .section-card:has(.list-group-item:nth-child(3)) .profile-section-title::after {
        content: " (Top 2)";
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 400;
    }
    
    .section-card:has(.completed-badge-item:nth-child(9)) .profile-section-title::after {
        content: " (Top 8)";
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 400;
    }
}

/* Extra small devices */
@media (max-width: 375px) {
    .main {
        padding: 6px;
        padding-top: 62px;
    }
    
    .glass {
        padding: 10px 8px;
    }
    
    .profile-img {
        width: 65px;
        height: 65px;
    }
    
    .profile-name {
        font-size: 1.05rem;
    }
    
    .profile-rank {
        font-size: 0.65rem;
        padding: 3px 8px;
    }
    
    .section-card {
        padding: 8px 6px;
    }
    
    .stat-number {
        font-size: 1.3rem;
    }
    
    .completed-badge-icon {
        width: 35px;
        height: 35px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main">
        <!-- Top Navbar -->
        <?php include('navbar.php'); ?>

        <div class="container-fluid">
            <div class="glass">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-img-container">
                        <?php if (!empty($user['profile_picture'])) { ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']); ?>" class="profile-img" alt="Profile Picture">
                        <?php } else { ?>
                            <img src="images/default_profile.png" class="profile-img" alt="Default Profile Picture">
                        <?php } ?>
                        <!-- Upload Button Overlay -->
                        <button type="button" class="btn btn-success btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                                style="width: 40px; height: 40px; padding: 0; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.5);"
                                data-bs-toggle="modal" data-bs-target="#uploadPictureModal"
                                title="Change Profile Picture">
                            <i class="bi bi-camera-fill"></i>
                        </button>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?= htmlspecialchars($user['name']); ?></h2>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <span class="profile-rank">
                                <i class="bi bi-award-fill me-2"></i><?= htmlspecialchars($user['rank_name'] ?? 'Unranked'); ?>
                            </span>
                            <?php if (!empty($user['scout_type'])): ?>
                                <span class="profile-rank" style="background: linear-gradient(135deg, rgba(13, 202, 240, 0.25), rgba(8, 145, 178, 0.15)); border-color: rgba(13, 202, 240, 0.4); color: #0dcaf0;">
                                    <i class="bi bi-person-badge me-2"></i><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['scout_type']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= mysqli_num_rows($events_result); ?></div>
                        <div class="stat-label"><i class="bi bi-calendar-check me-2"></i>Events Attended</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count($completed_badges); ?></div>
                        <div class="stat-label"><i class="bi bi-award me-2"></i>Badges Earned</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= !empty($user['troop_name']) ? '1' : '0'; ?></div>
                        <div class="stat-label"><i class="bi bi-people me-2"></i>Troop Assigned</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="section-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="profile-section-title mb-0">
                                    <i class="bi bi-person-circle"></i>
                                    Personal Information
                                </h4>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                </button>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-envelope-fill"></i>
                                <div>
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?= htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($user['scout_type'])): ?>
                            <div class="info-item">
                                <i class="bi bi-person-badge-fill"></i>
                                <div>
                                    <div class="info-label">Scout Position</div>
                                    <div class="info-value"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['scout_type']))); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <i class="bi bi-building"></i>
                                <div>
                                    <div class="info-label">School</div>
                                    <div class="info-value"><?= htmlspecialchars($user['school'] ?? 'Not Provided'); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-card-heading"></i>
                                <div>
                                    <div class="info-label">Membership Card</div>
                                    <div class="info-value"><?= htmlspecialchars($user['membership_card'] ?? 'Not Assigned'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <h4 class="profile-section-title">
                                <i class="bi bi-people-fill"></i>
                                Troop Information
                            </h4>
                            <div class="info-item">
                                <i class="bi bi-flag-fill"></i>
                                <div>
                                    <div class="info-label">Troop Name</div>
                                    <div class="info-value"><?= htmlspecialchars($user['troop_name'] ?? "Not Assigned"); ?></div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-person-badge"></i>
                                <div>
                                    <div class="info-label">Troop Leader</div>
                                    <div class="info-value"><?= htmlspecialchars($user['troop_leader'] ?? "Not Assigned"); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="section-card">
                            <h4 class="profile-section-title">
                                <i class="bi bi-calendar-event"></i>
                                Events Attended
                            </h4>
                            <ul class="list-group">
                                <?php 
                                mysqli_data_seek($events_result, 0); // Reset pointer
                                if(mysqli_num_rows($events_result) > 0){
                                    while ($event = mysqli_fetch_assoc($events_result)) { ?>
                                        <li class="list-group-item">
                                            <i class="bi bi-calendar-check me-2"></i>
                                            <strong><?= htmlspecialchars($event['event_title']); ?></strong>
                                            <br>
                                            <small class="text-white-50 ms-4"><?= date("M d, Y", strtotime($event['event_date'])); ?></small>
                                        </li>
                                <?php } } else { ?>
                                    <li class="list-group-item text-center text-white-50">
                                        <i class="bi bi-calendar-x me-2"></i>No events attended yet.
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="section-card">
                            <h4 class="profile-section-title">
                                <i class="bi bi-trophy-fill"></i>
                                Completed Merit Badges
                            </h4>
                            <?php if (count($completed_badges) > 0): ?>
                                <div class="row g-3">
                                    <?php foreach ($completed_badges as $badge): ?>
                                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 d-flex">
                                            <a href="badge_progress.php?id=<?= $badge['id'] ?>" class="completed-badge-item w-100" title="Completed on <?= date('M j, Y', strtotime($badge['date_completed'])) ?>">
                                                <img src="<?= htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png') ?>" alt="<?= htmlspecialchars($badge['name']) ?>" class="completed-badge-icon">
                                                <span class="name"><?= htmlspecialchars($badge['name']) ?></span>
                                                <span class="date"><?= date('M j, Y', strtotime($badge['date_completed'])) ?></span>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-award" style="font-size: 4rem; color: rgba(255,255,255,0.3);"></i>
                                    <p class="text-white-50 mt-3">No merit badges earned yet. Start working on your first badge!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Profile Picture Modal -->
<div class="modal fade" id="uploadPictureModal" tabindex="-1" aria-labelledby="uploadPictureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #2c3e50; color: white; border: 1px solid rgba(40, 167, 69, 0.3);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(40, 167, 69, 0.3);">
                <h5 class="modal-title" id="uploadPictureModalLabel">
                    <i class="bi bi-camera me-2"></i>Change Profile Picture
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($user['profile_picture'])) { ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']); ?>" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #28a745;" alt="Current Profile Picture">
                        <?php } else { ?>
                            <img src="images/default_profile.png" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #28a745;" alt="Default Profile Picture">
                        <?php } ?>
                    </div>
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">
                            <i class="bi bi-image me-2"></i>Select New Picture
                        </label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                        <small class="text-white-50 d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>Allowed formats: JPG, JPEG, PNG, GIF (Max 5MB)
                        </small>
                    </div>
                    <div id="imagePreview" class="text-center" style="display: none;">
                        <p class="text-white-50 mb-2">Preview:</p>
                        <img id="previewImg" src="" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #28a745;" alt="Preview">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(40, 167, 69, 0.3);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Upload Picture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #2c3e50; color: white; border: 1px solid rgba(40, 167, 69, 0.3);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(40, 167, 69, 0.3);">
                <h5 class="modal-title" id="editProfileModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="profile.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-person me-2"></i>Full Name
                        </label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-2"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="school" class="form-label">
                            <i class="bi bi-building me-2"></i>School
                        </label>
                        <input type="text" class="form-control" id="school" name="school" value="<?= htmlspecialchars($user['school'] ?? ''); ?>" placeholder="Enter your school name">
                    </div>
                    <div class="alert alert-info" style="background: rgba(13, 202, 240, 0.1); border: 1px solid rgba(13, 202, 240, 0.3); color: #0dcaf0;">
                        <i class="bi bi-info-circle me-2"></i>
                        <small>Your membership card and troop information can only be changed by your troop leader or admin.</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(40, 167, 69, 0.3);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" name="update_profile" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Image preview functionality
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});
</script>
</body>
</html>
