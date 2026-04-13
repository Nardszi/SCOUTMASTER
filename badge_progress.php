<?php
session_start();
include('config.php');

// 1. Security & Setup: Ensure user is a scout and a valid badge ID is provided.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: merit_badges.php'); // Redirect to a badge list page
    exit();
}

$scout_id = $_SESSION['user_id'];
$badge_id = (int)$_GET['id'];

// 2. Fetch Badge Details
$badge_stmt = mysqli_prepare($conn, "SELECT * FROM merit_badges WHERE id = ?");
mysqli_stmt_bind_param($badge_stmt, "i", $badge_id);
mysqli_stmt_execute($badge_stmt);
$badge_result = mysqli_stmt_get_result($badge_stmt);
$badge = mysqli_fetch_assoc($badge_result);

if (!$badge) {
    header('Location: merit_badges.php'); // Badge not found
    exit();
}

// 3. Get or Create the main progress tracker for this scout and badge
$sbp_id = null;
$sbp_stmt = mysqli_prepare($conn, "SELECT id FROM scout_badge_progress WHERE scout_id = ? AND merit_badge_id = ?");
mysqli_stmt_bind_param($sbp_stmt, "ii", $scout_id, $badge_id);
mysqli_stmt_execute($sbp_stmt);
$sbp_result = mysqli_stmt_get_result($sbp_stmt);
if ($sbp_row = mysqli_fetch_assoc($sbp_result)) {
    $sbp_id = $sbp_row['id'];
} else {
    // First time viewing, create the progress record to "start" the badge
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO scout_badge_progress (scout_id, merit_badge_id, status, date_started) VALUES (?, ?, 'in_progress', NOW())");
    mysqli_stmt_bind_param($insert_stmt, "ii", $scout_id, $badge_id);
    mysqli_stmt_execute($insert_stmt);
    $sbp_id = mysqli_insert_id($conn);
}

// 4. Handle Form Submission to update progress
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Proof Deletion
    if (isset($_POST['delete_proof_req_id'])) {
        $del_req_id = (int)$_POST['delete_proof_req_id'];
        
        // Check if the requirement exists and is NOT approved yet
        $check_del_stmt = mysqli_prepare($conn, "SELECT proof_file, date_approved FROM scout_requirement_progress WHERE scout_badge_progress_id = ? AND requirement_id = ?");
        mysqli_stmt_bind_param($check_del_stmt, "ii", $sbp_id, $del_req_id);
        mysqli_stmt_execute($check_del_stmt);
        $del_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check_del_stmt));

        if ($del_row && $del_row['date_approved'] === null && !empty($del_row['proof_file'])) {
            // Delete file from server
            if (file_exists($del_row['proof_file'])) {
                unlink($del_row['proof_file']);
            }
            // Update Database to remove file path
            $update_del_stmt = mysqli_prepare($conn, "UPDATE scout_requirement_progress SET proof_file = NULL WHERE scout_badge_progress_id = ? AND requirement_id = ?");
            mysqli_stmt_bind_param($update_del_stmt, "ii", $sbp_id, $del_req_id);
            mysqli_stmt_execute($update_del_stmt);
            logActivity($conn, $scout_id, 'Delete Proof', "Deleted proof for requirement ID $del_req_id");
        }
    }

    $submitted_reqs = isset($_POST['requirements']) ? array_map('intval', $_POST['requirements']) : [];

    // Get all requirements for this badge to process updates for checked and unchecked boxes
    $all_reqs_stmt = mysqli_prepare($conn, "SELECT id FROM badge_requirements WHERE merit_badge_id = ?");
    mysqli_stmt_bind_param($all_reqs_stmt, "i", $badge_id);
    mysqli_stmt_execute($all_reqs_stmt);
    $all_reqs_result = mysqli_stmt_get_result($all_reqs_stmt);

    while ($req_row = mysqli_fetch_assoc($all_reqs_result)) {
        $req_id = $req_row['id'];
        $is_checked = in_array($req_id, $submitted_reqs);

        // Check if the requirement is already approved by a leader. If so, it cannot be changed.
        $check_approved_stmt = mysqli_prepare($conn, "SELECT id FROM scout_requirement_progress WHERE scout_badge_progress_id = ? AND requirement_id = ? AND date_approved IS NOT NULL");
        mysqli_stmt_bind_param($check_approved_stmt, "ii", $sbp_id, $req_id);
        mysqli_stmt_execute($check_approved_stmt);
        $is_approved = mysqli_stmt_get_result($check_approved_stmt)->num_rows > 0;

        if (!$is_approved) {
            $is_completed_val = $is_checked ? 1 : 0;
            // Use INSERT ... ON DUPLICATE KEY UPDATE for an efficient upsert.
            // This requires a UNIQUE key on (scout_badge_progress_id, requirement_id).
            $upsert_sql = "
                INSERT INTO scout_requirement_progress (scout_badge_progress_id, requirement_id, is_completed)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE is_completed = VALUES(is_completed), rejection_comment = NULL";
            $upsert_stmt = mysqli_prepare($conn, $upsert_sql);
            mysqli_stmt_bind_param($upsert_stmt, "iii", $sbp_id, $req_id, $is_completed_val);
            mysqli_stmt_execute($upsert_stmt);
        }

        // Handle File Upload for Proof
        if (isset($_FILES['proof_files']['name'][$req_id]) && $_FILES['proof_files']['error'][$req_id] == 0) {
            $target_dir = "uploads/proofs/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
            
            $file_ext = strtolower(pathinfo($_FILES['proof_files']['name'][$req_id], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = time() . "_" . $scout_id . "_" . $req_id . "." . $file_ext;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['proof_files']['tmp_name'][$req_id], $target_file)) {
                    $file_update_stmt = mysqli_prepare($conn, "UPDATE scout_requirement_progress SET proof_file = ? WHERE scout_badge_progress_id = ? AND requirement_id = ?");
                    mysqli_stmt_bind_param($file_update_stmt, "sii", $target_file, $sbp_id, $req_id);
                    mysqli_stmt_execute($file_update_stmt);
                }
            }
        }
    }
    logActivity($conn, $scout_id, 'Update Badge Progress', "Updated progress for badge ID $badge_id");
    // Redirect to prevent form resubmission and show a success message
    header("Location: badge_progress.php?id=" . $badge_id . "&updated=true");
    exit();
}

// 5. Fetch all requirements with their current progress for display
$requirements = [];
$approved_count = 0;
$req_stmt = mysqli_prepare($conn, "
    SELECT
        br.id,
        br.requirement_number,
        br.description,
        srp.is_completed,
        srp.proof_file,
        srp.date_approved,
        srp.rejection_comment,
        approver.name as approver_name
    FROM badge_requirements br
    LEFT JOIN scout_requirement_progress srp ON br.id = srp.requirement_id AND srp.scout_badge_progress_id = ?
    LEFT JOIN users approver ON srp.approved_by_id = approver.id
    WHERE br.merit_badge_id = ?
    ORDER BY br.id
");
mysqli_stmt_bind_param($req_stmt, "ii", $sbp_id, $badge_id);
mysqli_stmt_execute($req_stmt);
$req_result = mysqli_stmt_get_result($req_stmt);
while ($row = mysqli_fetch_assoc($req_result)) {
    $requirements[] = $row;
    if ($row['date_approved'] !== null) { // Only count leader-approved as completed for the progress bar
        $approved_count++;
    }
}

$total_reqs = count($requirements);
$progress_percentage = $total_reqs > 0 ? round(($approved_count / $total_reqs) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($badge['name']); ?> Progress</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: -1; pointer-events: none; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 0; }
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
        
        /* Fix: Ensure main content is interactive */
        .main > * { 
            position: relative; 
            z-index: auto; 
            pointer-events: auto; 
        }
        
        .glass { 
            background: rgba(255,255,255,0.1); 
            backdrop-filter: blur(12px); 
            border-radius: 20px; 
            padding: 30px; 
            border: 1px solid rgba(255,255,255,0.15); 
            flex: 1; 
            margin-bottom: 20px; 
            position: relative; 
            z-index: auto; 
            pointer-events: auto; 
        }
        
        /* All interactive elements - ensure they're clickable */
        button, a, input, select, textarea, .badge-card, .requirement-item, .list-group-item, .form-check-input, .form-check-label {
            position: relative;
            pointer-events: auto !important;
            touch-action: manipulation;
            cursor: pointer;
        }
        
        /* Ensure form elements are interactive */
        .form-control, .form-select, input[type="file"], input[type="checkbox"] {
            pointer-events: auto !important;
            touch-action: manipulation;
        }
        
        /* Fix logout modal z-index */
        .modal { z-index: 99999 !important; }
        .modal-backdrop {
            z-index: 99998 !important;
        }
        
        #logoutModal,
        .modal.show {
            z-index: 99999 !important;
        }
        
        .modal-backdrop.show {
            z-index: 99998 !important;
        }
        
        /* Ensure modal elements are clickable */
        .modal,
        .modal-dialog,
        .modal-content,
        .modal-header,
        .modal-body,
        .modal-footer {
            pointer-events: auto !important;
        }
        .btn-close { filter: brightness(0) invert(1); opacity: 1; cursor: pointer; pointer-events: auto !important; }
        
        .progress-bar {
            background-color: #28a745;
            transition: width 0.6s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        #progressBarText {
            display: inline;
        }
        .requirement-list .list-group-item {
            background-color: rgba(0,0,0,0.3);
            border-color: rgba(255,255,255,0.2);
            color: white;
            margin-bottom: 10px;
            border-radius: 10px;
        }
        .requirement-list .list-group-item.approved {
            background-color: rgba(40, 167, 69, 0.2);
            border-left: 5px solid #28a745;
        }
        .requirement-list .list-group-item.rejected {
            background-color: rgba(220, 53, 69, 0.15);
            border-left: 5px solid #dc3545;
        }
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        .badge-header-icon {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.2));
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

        /* Badge header wrapper */
        .badge-header-wrapper {
            position: relative;
        }

        /* Desktop: Buttons at top right */
        .header-buttons-top {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .header-buttons-top .btn {
            white-space: nowrap;
        }

        /* Desktop specific positioning */
        @media (min-width: 769px) {
            .badge-header-wrapper {
                position: relative;
            }

            .header-buttons-top {
                position: absolute;
                top: 0;
                right: 0;
                margin-bottom: 0;
            }

            .badge-header-wrapper .d-flex {
                padding-right: 0;
            }
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
                padding: 15px;
                padding-top: 70px; /* Add space for fixed navbar */
                z-index: 1 !important;
                pointer-events: auto !important;
            }
            
            body.sidebar-collapsed .main {
                margin-left: 0 !important;
            }
            
            /* Ensure all content is touchable on mobile */
            .glass {
                padding: 20px 15px;
                margin-bottom: 15px;
                border-radius: 15px;
                pointer-events: auto !important;
                touch-action: auto !important;
            }

            /* Make sure all interactive elements work */
            .glass * {
                pointer-events: auto !important;
            }

            /* Ensure buttons and inputs are touchable */
            button, a, input, select, textarea, .btn, .form-control, .form-select, .list-group-item, .modal, .modal-dialog, .modal-content, .modal-header, .modal-body, .modal-footer, .modal-backdrop {
                pointer-events: auto !important;
                touch-action: manipulation !important;
            }

            /* Mobile: Buttons stack vertically */
            .header-buttons-top {
                position: relative;
                flex-direction: column;
                width: 100%;
                margin-bottom: 15px;
                pointer-events: auto !important;
            }

            .header-buttons-top .btn {
                width: 100%;
                pointer-events: auto !important;
                touch-action: manipulation !important;
            }
            
            /* Badge Header */
            .badge-header-icon {
                width: 60px;
                height: 60px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            h3 {
                font-size: 1.3rem;
            }
            
            h5 {
                font-size: 1.1rem;
            }
            
            /* Header layout - stack on mobile */
            .badge-header-wrapper .d-flex {
                flex-direction: column;
                text-align: center;
            }
            
            .badge-header-wrapper .d-flex .badge-header-icon {
                margin-right: 0 !important;
                margin-bottom: 15px;
            }
            
            /* Progress bars */
            .progress {
                height: 20px;
                font-size: 14px;
            }
            
            /* Buttons - touch friendly */
            .btn {
                padding: 12px 20px;
                font-size: 15px;
                min-height: 44px;
                touch-action: manipulation;
            }
            
            .btn-sm {
                padding: 8px 15px;
                font-size: 13px;
                min-height: 38px;
            }
            
            .btn-submit {
                width: 100%;
                margin-top: 10px;
                padding: 14px;
                font-size: 16px;
            }
            
            /* Requirements list */
            .requirement-list .list-group-item {
                padding: 15px 12px;
                font-size: 14px;
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }
            
            .requirement-list .list-group-item > div {
                width: 100%;
            }
            
            .requirement-list .list-group-item .form-check {
                width: 100%;
            }
            
            .requirement-list .list-group-item .alert {
                width: 100% !important;
                margin-left: 0 !important;
                margin-top: 10px;
            }
            
            /* File inputs and action buttons */
            .requirement-list .list-group-item input[type="file"] {
                width: 100% !important;
                margin-top: 8px;
                margin-bottom: 8px;
            }
            
            .requirement-list .list-group-item .btn-outline-info,
            .requirement-list .list-group-item .btn-outline-danger {
                width: 100%;
                margin: 5px 0;
            }
            
            .requirement-list .list-group-item .badge {
                display: block;
                width: fit-content;
                margin-top: 8px;
            }
            
            .requirement-list .list-group-item small {
                display: block;
                text-align: left !important;
                margin-top: 5px;
            }
            
            /* Form controls - touch friendly */
            .form-control,
            .form-select,
            textarea {
                font-size: 16px;
                padding: 12px 15px;
                min-height: 44px;
            }
            
            .form-control-sm {
                font-size: 14px;
                padding: 10px 12px;
                min-height: 38px;
            }
            
            .form-check-input {
                width: 20px;
                height: 20px;
                margin-top: 2px;
            }
            
            .form-check-label {
                font-size: 14px;
                line-height: 1.5;
            }
            
            /* Alert messages */
            .alert {
                font-size: 14px;
                padding: 12px;
            }
            
            /* Modal adjustments */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            /* Badges */
            .badge {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            /* Border adjustments */
            .border-bottom {
                padding-bottom: 15px !important;
                margin-bottom: 20px !important;
            }
        }
            
            /* Badge Header */
            .badge-header-icon {
                width: 60px;
                height: 60px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            h3 {
                font-size: 1.3rem;
            }
            
            h5 {
                font-size: 1.1rem;
            }
            
            /* Button groups - stack on mobile */
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }
            
            .btn-group .btn {
                width: 100%;
                margin: 0;
            }
            
            /* Header layout - stack on mobile */
            .d-flex.align-items-center.mb-4 {
                flex-direction: column;
                text-align: center;
            }
            
            .d-flex.align-items-center.mb-4 .badge-header-icon {
                margin-right: 0 !important;
                margin-bottom: 15px;
            }
            
            .d-flex.justify-content-between.align-items-start {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            
            /* Progress bars */
            .progress {
                height: 20px;
                font-size: 14px;
            }
            
            /* Buttons - touch friendly */
            .btn {
                padding: 12px 20px;
                font-size: 15px;
                min-height: 44px;
                touch-action: manipulation;
            }
            
            .btn-sm {
                padding: 8px 15px;
                font-size: 13px;
                min-height: 38px;
            }
            
            .btn-submit {
                width: 100%;
                margin-top: 10px;
                padding: 14px;
                font-size: 16px;
            }
            
            /* Requirements list */
            .requirement-list .list-group-item {
                padding: 15px 12px;
                font-size: 14px;
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }
            
            .requirement-list .list-group-item > div {
                width: 100%;
            }
            
            .requirement-list .list-group-item .form-check {
                width: 100%;
            }
            
            .requirement-list .list-group-item .alert {
                width: 100% !important;
                margin-left: 0 !important;
                margin-top: 10px;
            }
            
            /* File inputs and action buttons */
            .requirement-list .list-group-item input[type="file"] {
                width: 100% !important;
                margin-top: 8px;
                margin-bottom: 8px;
            }
            
            .requirement-list .list-group-item .btn-outline-info,
            .requirement-list .list-group-item .btn-outline-danger {
                width: 100%;
                margin: 5px 0;
            }
            
            .requirement-list .list-group-item .badge {
                display: block;
                width: fit-content;
                margin-top: 8px;
            }
            
            .requirement-list .list-group-item small {
                display: block;
                text-align: left !important;
                margin-top: 5px;
            }
            
            /* Form controls - touch friendly */
            .form-control,
            .form-select,
            textarea {
                font-size: 16px;
                padding: 12px 15px;
                min-height: 44px;
            }
            
            .form-control-sm {
                font-size: 14px;
                padding: 10px 12px;
                min-height: 38px;
            }
            
            .form-check-input {
                width: 20px;
                height: 20px;
                margin-top: 2px;
            }
            
            .form-check-label {
                font-size: 14px;
                line-height: 1.5;
            }
            
            /* Alert messages */
            .alert {
                font-size: 14px;
                padding: 12px;
            }
            
            /* Modal adjustments */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }
            
            /* Badges */
            .badge {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            /* Border adjustments */
            .border-bottom {
                padding-bottom: 15px !important;
                margin-bottom: 20px !important;
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
                padding: 65px 10px 10px; /* Adjust top padding for smaller navbar */
            }
            
            .glass {
                padding: 15px 10px;
                border-radius: 12px;
            }
            
            .badge-header-icon {
                width: 50px;
                height: 50px;
            }
            
            h1 {
                font-size: 1.3rem;
            }
            
            h3 {
                font-size: 1.2rem;
            }
            
            h5 {
                font-size: 1rem;
            }
            
            .btn {
                font-size: 14px;
                padding: 10px 15px;
            }
            
            .btn-sm {
                font-size: 12px;
                padding: 6px 12px;
            }
            
            .btn-submit {
                font-size: 15px;
                padding: 12px;
            }
            
            .requirement-list .list-group-item {
                padding: 12px 10px;
                font-size: 13px;
            }
            
            .form-check-input {
                width: 18px;
                height: 18px;
            }
            
            .form-check-label {
                font-size: 13px;
            }
            
            .progress {
                height: 18px;
                font-size: 12px;
            }
            
            .badge {
                font-size: 11px;
                padding: 5px 8px;
            }
            
            .alert {
                font-size: 13px;
                padding: 10px;
            }
        }

        @media (max-width: 375px) {
            .main {
                padding: 70px 8px 8px;
            }
            
            .glass {
                padding: 12px 8px;
            }
            
            h1 {
                font-size: 1.2rem;
            }
            
            h3 {
                font-size: 1.1rem;
            }
            
            .btn {
                font-size: 13px;
                padding: 9px 12px;
            }
            
            .requirement-list .list-group-item {
                padding: 10px 8px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">

        <div class="glass">
            
            <?php if(isset($_GET['updated'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Your progress has been saved!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Badge Header -->
            <div class="badge-header-wrapper mb-4 border-bottom border-secondary pb-4">
                <!-- Buttons at top right on desktop -->
                <div class="header-buttons-top">
                    <?php if ($progress_percentage == 100): ?>
                        <a href="generate_certificate.php?badge_id=<?php echo $badge_id; ?>" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-award me-2"></i>View Certificate</a>
                    <?php endif; ?>
                    <a href="merit_badges.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-2"></i>Back to Badges</a>
                </div>
                
                <!-- Badge info -->
                <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png'); ?>" class="badge-header-icon me-4">
                    <div class="flex-grow-1">
                        <h1 class="fw-bold mb-1"><?php echo htmlspecialchars($badge['name']); ?></h1>
                        <?php if ($badge['is_eagle_required']): ?>
                            <span class="badge bg-warning text-dark mb-2"><i class="fas fa-star me-1"></i> Eagle Required</span>
                        <?php endif; ?>
                        <p class="text-white-50 mb-0"><?php echo htmlspecialchars($badge['description']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-5">
                <h5 class="mb-2">Overall Progress (Leader Approved)</h5>
                <div class="progress" style="height: 25px;">
                    <div id="mainProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" data-width="<?php echo $progress_percentage; ?>" aria-valuenow="<?php echo $progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        <span id="progressBarText">0%</span>
                    </div>
                </div>
            </div>

            <!-- Requirements List -->
            <h3 class="mb-4">Requirements</h3>
            <form method="POST" enctype="multipart/form-data">
                <ul class="list-group requirement-list">
                    <?php foreach ($requirements as $req): ?>
                        <?php
                            $is_approved = $req['date_approved'] !== null;
                            $is_scout_completed = $req['is_completed'] == 1 && !$is_approved;
                            $is_rejected = !empty($req['rejection_comment']);
                            $item_class = $is_approved ? 'approved' : ($is_rejected ? 'rejected' : '');
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center <?php echo $item_class; ?>">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requirements[]" value="<?php echo $req['id']; ?>" id="req-<?php echo $req['id']; ?>"
                                    <?php if ($is_approved || $is_scout_completed) echo 'checked'; ?>
                                    <?php if ($is_approved) echo 'disabled'; ?>
                                >
                                <label class="form-check-label" for="req-<?php echo $req['id']; ?>">
                                    <strong class="me-2"><?php echo htmlspecialchars($req['requirement_number']); ?>:</strong>
                                    <?php echo htmlspecialchars($req['description']); ?>
                                </label>
                            </div>
                            <?php if ($is_rejected): ?>
                                <div class="alert alert-danger py-2 px-3 ms-3 w-50 mb-0">
                                    <strong>Feedback:</strong> <?php echo htmlspecialchars($req['rejection_comment']); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <?php if (!empty($req['proof_file'])): ?>
                                    <a href="<?php echo htmlspecialchars($req['proof_file']); ?>" target="_blank" class="btn btn-sm btn-outline-info me-2"><i class="fas fa-paperclip"></i> View Proof</a>
                                    <?php if (!$is_approved): ?>
                                        <button type="submit" name="delete_proof_req_id" value="<?php echo $req['id']; ?>" class="btn btn-sm btn-outline-danger me-2" onclick="return confirm('Are you sure you want to delete this proof?');"><i class="fas fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (!$is_approved): ?>
                                    <input type="file" name="proof_files[<?php echo $req['id']; ?>]" class="form-control form-control-sm d-inline-block w-auto" style="width: 200px;">
                                <?php endif; ?>

                                <?php if ($is_approved): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Approved</span>
                                    <small class="d-block text-white-50 text-end mt-1">by <?php echo htmlspecialchars($req['approver_name']); ?> on <?php echo date('M j, Y', strtotime($req['date_approved'])); ?></small>
                                <?php elseif ($is_scout_completed): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Pending Approval</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Started</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-save me-2"></i> Save My Progress
                    </button>
                </div>
            </form>

        </div>
        <?php include('footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Progress bar animation
    const progressBar = document.getElementById('mainProgressBar');
    const progressBarText = document.getElementById('progressBarText');

    if (progressBar && progressBarText) {
        const targetWidth = parseInt(progressBar.getAttribute('data-width'));

        // Animate the bar width after a short delay
        setTimeout(() => {
            progressBar.style.width = targetWidth + '%';
        }, 300);

        // Animate the number to match the bar's animation
        let current = 0;
        const duration = 600;
        const stepTime = 15;
        const increment = targetWidth / (duration / stepTime);

        const timer = setInterval(() => {
            current += increment;
            if (current >= targetWidth) {
                clearInterval(timer);
                current = targetWidth;
                if (targetWidth === 100) {
                    confetti({
                        particleCount: 150,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }
            }
            progressBarText.textContent = Math.round(current) + '%';
        }, stepTime);
    }

    // Sidebar toggle functionality
    const body = document.body;
    const toggleButton = document.getElementById('sidebar-toggle');
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    console.log('Toggle elements:', {
        toggleButton: !!toggleButton,
        mobileMenuToggle: !!mobileMenuToggle,
        sidebar: !!sidebar,
        sidebarOverlay: !!sidebarOverlay
    });

    // Desktop toggle function
    const toggleSidebar = () => {
        console.log('Desktop toggle clicked');
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
        
        const toggleIcon = toggleButton?.querySelector('i');
        if (toggleIcon) {
            if (body.classList.contains('sidebar-collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
    };

    // Mobile menu toggle
    const toggleMobileMenu = () => {
        console.log('Mobile toggle clicked');
        if (sidebar && sidebarOverlay) {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
            
            const icon = mobileMenuToggle?.querySelector('i');
            if (icon) {
                if (sidebar.classList.contains('mobile-open')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        }
    };

    // Close mobile menu
    const closeMobileMenu = () => {
        console.log('Closing mobile menu');
        sidebar?.classList.remove('mobile-open');
        sidebarOverlay?.classList.remove('active');
        const icon = mobileMenuToggle?.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    };

    // Check for saved state in localStorage on page load
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
        const toggleIcon = toggleButton?.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }
    }

    // Event listener for the toggle button (desktop)
    if (toggleButton) {
        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        console.log('Desktop toggle listener attached');
    }

    // Event listener for mobile menu toggle
    if (mobileMenuToggle) {
        // Remove any existing listeners
        const newMobileToggle = mobileMenuToggle.cloneNode(true);
        mobileMenuToggle.parentNode.replaceChild(newMobileToggle, mobileMenuToggle);
        
        newMobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Mobile toggle button clicked!');
            toggleMobileMenu();
        });
        
        // Also add touch event for better mobile support
        newMobileToggle.addEventListener('touchstart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Mobile toggle button touched!');
            toggleMobileMenu();
        }, { passive: false });
        
        console.log('Mobile toggle listener attached');
    }

    // Event listener for overlay click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileMenu);
        sidebarOverlay.addEventListener('touchstart', closeMobileMenu);
    }

    // Close mobile menu when clicking a link
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });

    // Debug: Log when page is ready
    console.log('Badge progress page loaded, sidebar toggle initialized');
});
</script>
</body>
</html>

<style>
/* Additional mobile fix for requirement numbers */
@media (max-width: 768px) {
    .requirement-list .list-group-item .form-check-label {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: flex-start !important;
        width: 100% !important;
        line-height: 1.6 !important;
    }
    
    .requirement-list .list-group-item .form-check-label strong {
        flex-shrink: 0 !important;
        min-width: 40px !important;
        margin-right: 8px !important;
        display: inline-block !important;
    }
    
    /* Progress bar mobile fix */
    .progress {
        height: 30px !important;
    }
    
    .progress-bar {
        font-size: 13px !important;
    }
}

@media (max-width: 576px) {
    .requirement-list .list-group-item .form-check-label strong {
        min-width: 35px !important;
        font-size: 13px !important;
    }
    
    /* Progress bar small mobile fix */
    .progress {
        height: 28px !important;
    }
    
    .progress-bar {
        font-size: 12px !important;
    }
}
</style>
