<?php
session_start();
include('config.php');

// 1. Security: Ensure user is logged in and is a scout
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_event_id'], $_POST['user_id'])) {
    $event_id = (int)$_POST['join_event_id'];
    $scout_id = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $scout_rank = trim($_POST['scout_rank']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $reason = trim($_POST['reason']);
    $requirements = trim($_POST['requirements']);
    $role = $_SESSION['role'];

    // 2. Handle waiver file upload
    $waiver_file_path = null;
    if (isset($_FILES['waiver_file']) && $_FILES['waiver_file']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/waivers/";
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                $_SESSION['error'] = "Failed to create upload directory. Please contact administrator.";
                header("Location: view_events.php");
                exit();
            }
        }
        
        // Validate file type
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['waiver_file']['type'];
        $file_extension = strtolower(pathinfo($_FILES['waiver_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $_SESSION['error'] = "Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.";
            header("Location: view_events.php");
            exit();
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['waiver_file']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = "File size too large. Maximum size is 5MB.";
            header("Location: view_events.php");
            exit();
        }
        
        $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($_FILES['waiver_file']['name']));
        $target_file = $target_dir . time() . "_" . $scout_id . "_" . $safe_filename;

        if (move_uploaded_file($_FILES['waiver_file']['tmp_name'], $target_file)) {
            $waiver_file_path = $target_file;
        } else {
            $_SESSION['error'] = "Error uploading waiver file. Please try again or contact administrator.";
            header("Location: view_events.php");
            exit();
        }
    } elseif (isset($_FILES['waiver_file']) && $_FILES['waiver_file']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle specific upload errors
        $error_message = "File upload error: ";
        switch ($_FILES['waiver_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message .= "File is too large.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message .= "File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message .= "Missing temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message .= "Failed to write file to disk.";
                break;
            default:
                $error_message .= "Unknown error occurred.";
        }
        $_SESSION['error'] = $error_message;
        header("Location: view_events.php");
        exit();
    } else {
        $_SESSION['error'] = "Parent waiver file is required. Please select a file to upload.";
        header("Location: view_events.php");
        exit();
    }

    // 3. Insert into event_attendance table
    $query = "INSERT INTO event_attendance (event_id, scout_id, name, address, scout_rank, emergency_contact, reason, requirements, waiver_file, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iissssssss", $event_id, $scout_id, $name, $address, $scout_rank, $emergency_contact, $reason, $requirements, $waiver_file_path, $role);

    if (mysqli_stmt_execute($stmt)) {
        // 4. Log the activity
        // Fetch event title for a more descriptive log message
        $event_title_query = mysqli_prepare($conn, "SELECT event_title FROM events WHERE id = ?");
        mysqli_stmt_bind_param($event_title_query, "i", $event_id);
        mysqli_stmt_execute($event_title_query);
        $event_title_result = mysqli_stmt_get_result($event_title_query);
        $event_title = mysqli_fetch_assoc($event_title_result)['event_title'] ?? 'Unknown Event';

        logActivity($conn, $scout_id, 'Join Event', "Joined event: '$event_title' (ID: $event_id)");

        $_SESSION['success'] = "Successfully joined the event: " . htmlspecialchars($event_title);
    } else {
        $_SESSION['error'] = "Error joining event: " . mysqli_error($conn);
    }

    header("Location: view_events.php");
    exit();

} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: view_events.php");
    exit();
}
?>