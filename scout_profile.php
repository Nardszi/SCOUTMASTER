    <?php
    session_start();
    include('config.php');

    // Check if the user is a scout
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
        header('Location: login.php');
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // Handle profile picture upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
        $target_dir = "uploads/";
        $file_name = basename($_FILES['profile_picture']['name']);
        $target_file = $target_dir . $user_id . "_" . $file_name; // Rename file with user ID
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Allow only image files
        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                // Update profile picture in database
                $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $target_file, $user_id);
                mysqli_stmt_execute($stmt);
                logActivity($conn, $user_id, 'Update Profile Picture', 'Uploaded new profile picture');
            }
        }
    }

    // Handle profile information update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
        $name = htmlspecialchars($_POST['name']);
        $email = htmlspecialchars($_POST['email']);

        $update_query = "UPDATE users SET name = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $user_id);
        mysqli_stmt_execute($stmt);
        logActivity($conn, $user_id, 'Update Profile', 'Updated name/email');
        
        // Refresh the page to reflect changes
        header("Location: scout_profile.php?updated=true");
        exit();
    }

    // Fetch user details
    $query = "SELECT users.name, users.email, users.rank, users.profile_picture, 
                    troops.troop_name 
            FROM users 
            LEFT JOIN troops ON users.troop_id = troops.id 
            WHERE users.id = ?";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    // Fetch earned badges
    $badges_query = "SELECT mb.id, mb.name, mb.icon_path, sbp.date_completed 
                     FROM scout_badge_progress sbp 
                     JOIN merit_badges mb ON sbp.merit_badge_id = mb.id 
                     WHERE sbp.scout_id = ? AND sbp.status = 'completed'
                     ORDER BY sbp.date_completed DESC";
    $b_stmt = mysqli_prepare($conn, $badges_query);
    mysqli_stmt_bind_param($b_stmt, "i", $user_id);
    mysqli_stmt_execute($b_stmt);
    $badges_res = mysqli_stmt_get_result($b_stmt);
    $earned_badges = [];
    while($b_row = mysqli_fetch_assoc($badges_res)){
        $earned_badges[] = $b_row;
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Scout Profile</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <style>
        /* BASE */
        body{
            font-family: 'Poppins', sans-serif;
            margin:0;
            font-family:'Segoe UI',sans-serif;
            min-height:100vh;
            background:#000;
            color:white;
        }

        /* LAYOUT */
        .wrapper{
            display:flex;
            min-height:100vh;
            position: relative;
            z-index: 0;
        }

        /* MAIN BACKGROUND */
        .main{
            flex:1;
            margin-left: 240px;
            position: relative;
            z-index: 1;
        }
            padding:30px;
            background:url("images/wall.png") no-repeat center center/cover;
            position:relative;
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
            background:rgba(0,0,0,0.55);
            z-index:-1;
            pointer-events:none;
        }

        .main > *{
            position:relative;
            z-index:1;
        }

        /* GLASS */
        .glass{
            background:rgba(255,255,255,0.15);
            backdrop-filter:blur(10px);
            border-radius:25px;
            padding:30px;
            margin-bottom:20px;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
            pointer-events: auto;
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
            color: rgba(255,255,255,0.8);
        }

        .form-control {
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
            position: relative;
            z-index: 10;
            pointer-events: auto;
            touch-action: manipulation;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #218838;
        }

        /* All buttons and links */
        button, a, input[type="submit"], input[type="button"], input[type="file"] {
            position: relative;
            z-index: 10;
            pointer-events: auto;
            touch-action: manipulation;
            cursor: pointer;
        }

        /* Completed Badges Styles */
        .completed-badge-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: white;
            transition: transform 0.2s ease-in-out;
            height: 100%;
            padding: 15px 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
        }
        .completed-badge-item:hover {
            transform: scale(1.05);
            background: rgba(0,0,0,0.3);
        }
        .completed-badge-icon {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 8px;
            filter: drop-shadow(0 0 5px rgba(40, 167, 69, 0.5));
        }
        .completed-badge-item .name {
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.2;
            margin-top: auto;
        }
        .completed-badge-item .date {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.6);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .main {
                margin-left: 0 !important;
                padding: 70px 15px 15px;
                z-index: 0;
            }
            
            body.sidebar-collapsed .main {
                margin-left: 0 !important;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .glass {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            /* Profile header */
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-name {
                font-size: 1.8rem;
            }
            
            .profile-rank {
                font-size: 1rem;
            }
            
            /* Info cards */
            .info-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .info-label {
                font-size: 0.85rem;
            }
            
            .info-value {
                font-size: 1rem;
            }
            
            /* Badge grid */
            .badge-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 10px;
            }
            
            .completed-badge-icon {
                width: 50px;
                height: 50px;
            }
            
            .completed-badge-item .name {
                font-size: 0.75rem;
            }
            
            .completed-badge-item .date {
                font-size: 0.7rem;
            }
            
            /* Buttons - Make them larger and more touch-friendly */
            .btn {
                padding: 12px 20px;
                font-size: 16px;
                min-height: 44px;
                touch-action: manipulation;
            }
            
            /* Form controls - Better touch targets */
            .form-control,
            .form-select {
                font-size: 16px;
                padding: 12px 15px;
                min-height: 44px;
            }
            
            /* Modal adjustments */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }

            /* Ensure all clickable elements are touch-friendly */
            a, button, input[type="submit"], input[type="button"] {
                min-height: 44px;
                min-width: 44px;
                touch-action: manipulation;
            }

            /* File input styling */
            input[type="file"] {
                padding: 10px;
                font-size: 14px;
            }
        }

        @media (max-width: 576px) {
            .main {
                padding: 60px 10px 10px;
            }

            .page-title {
                font-size: 20px;
            }
            
            .glass {
                padding: 12px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-name {
                font-size: 1.5rem;
            }
            
            .profile-rank {
                font-size: 0.9rem;
            }
            
            .badge-grid {
                grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
                gap: 8px;
            }
            
            .completed-badge-icon {
                width: 45px;
                height: 45px;
            }
            
            .btn {
                font-size: 15px;
                padding: 10px 15px;
            }

            .form-control,
            .form-select {
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

        <div class="glass">
            <h1 class="text-center mb-5" style="font-weight: 800;">EDIT YOUR PROFILE</h1>
            
            <div class="row g-5">
                <div class="col-md-4 text-center">
                    <!-- Profile Picture -->
                    <?php if (!empty($user['profile_picture'])) { ?>
                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" class="profile-img" alt="Profile Picture">
                    <?php } else { ?>
                        <img src="images/default_profile.png" class="profile-img" alt="Default Profile Picture">
                    <?php } ?>

                    <!-- Upload Profile Picture Form -->
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Change Picture</label>
                            <input type="file" name="profile_picture" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-submit w-100">Upload</button>
                    </form>
                </div>

                <div class="col-md-8">
                    <h4 class="mb-4">Profile Information</h4>
                    <!-- Profile Update Form -->
                    <form action="" method="post">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-submit">Update Profile</button>
                    </form>

                    <hr class="my-4" style="border-color: rgba(255,255,255,0.2);">

                    <h5 class="mt-4">Troop Information</h5>
                    <p><strong>Troop Name:</strong> <?php echo htmlspecialchars($user['troop_name'] ?? "Not Assigned"); ?></p>
                    <p><strong>Rank:</strong> <?php echo htmlspecialchars($user['rank'] ?? "Unranked"); ?></p>

                    <hr class="my-4" style="border-color: rgba(255,255,255,0.2);">

                    <h5 class="mt-4 mb-3">Earned Merit Badges</h5>
                    <?php if (count($earned_badges) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($earned_badges as $badge): ?>
                                <div class="col-6 col-sm-4 col-md-3 d-flex">
                                    <a href="badge_progress.php?id=<?= $badge['id'] ?>" class="completed-badge-item w-100" title="Completed on <?= date('M j, Y', strtotime($badge['date_completed'])) ?>">
                                        <img src="<?= htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png') ?>" alt="<?= htmlspecialchars($badge['name']) ?>" class="completed-badge-icon">
                                        <span class="name"><?= htmlspecialchars($badge['name']) ?></span>
                                        <span class="date"><?= date('M j, Y', strtotime($badge['date_completed'])) ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-white-50">No merit badges earned yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
    </body>
    </html>
