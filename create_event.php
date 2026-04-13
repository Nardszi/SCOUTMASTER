<?php
session_start();
include('config.php');

/* Allow only Scout Leaders */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header("Location: dashboard.php");
    exit();
}
$scout_leader_id = $_SESSION['user_id'];
$event_id = isset($_REQUEST['event_id']) ? intval($_REQUEST['event_id']) : null;

/* Initialize variables */
$event_title = "";
$event_date = "";
$event_description = "";
$event_image = "";
$event_location = "";

/* FETCH EVENT FOR EDITING */
if ($event_id) {
    $query = "SELECT * FROM events WHERE id = ? AND scout_leader_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $event_id, $scout_leader_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);

    if (!$event) {
        $_SESSION['error'] = "Event not found or access denied.";
        header("Location: events.php");
        exit();
    }

    $event_title = $event['event_title'];
    $event_date = $event['event_date'];
    $event_description = $event['event_description'];
    $event_image = $event['event_image'];
    $event_location = $event['event_location'] ?? '';
}

/* HANDLE CREATE / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $event_title = trim($_POST['event_title']);
    $event_date = $_POST['event_date'];
    $event_location = trim($_POST['event_location']);

    // ✅ FIXED: CORRECT NAME
    $event_description = trim($_POST['event_description']);

    /* IMAGE UPLOAD */
    if (!empty($_FILES['event_image']['name'])) {

        $target_dir = "uploads/";
        $image_name = time() . "_" . basename($_FILES['event_image']['name']);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($imageFileType, $allowed_types)) {
            $_SESSION['error'] = "Invalid image type.";
            header("Location: events.php");
            exit();
        }

        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {

            if ($event_id && !empty($event_image) && file_exists($event_image) && $event_image !== "uploads/default_event.png") {
                unlink($event_image);
            }

            $event_image = $target_file;
        }
    } else {
        if (!$event_id) {
            $event_image = "uploads/default_event.png";
        }
    }

    $status = ($_SESSION['role'] === 'admin') ? 'approved' : 'pending';

    /* UPDATE EVENT */
    if ($event_id) {

        $update_query = "UPDATE events 
                         SET event_title = ?, 
                             event_description = ?, 
                             event_date = ?, 
                             event_location = ?,
                             event_image = ?, 
                             status = ?
                         WHERE id = ? AND scout_leader_id = ?";

        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssii",
            $event_title,
            $event_description,
            $event_date,
            $event_location,
            $event_image,
            $status,
            $event_id,
            $scout_leader_id
        );

        logActivity($conn, $scout_leader_id, 'Update Event', "Updated event ID $event_id");
        $_SESSION['success'] = ($_SESSION['role'] === 'admin') ? "Event updated successfully." : "Event updated successfully and sent for admin approval.";

    } else {

        /* CREATE EVENT */
        $insert_query = "INSERT INTO events 
                        (event_title, event_description, event_date, event_location, event_image, scout_leader_id, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param(
            $stmt,
            "ssssis",
            $event_title,
            $event_description,
            $event_date,
            $event_location,
            $event_image,
            $scout_leader_id,
            $status
        );

        // We will log after execution to get ID if needed, but here we don't have ID easily before exec.
        $_SESSION['success'] = ($_SESSION['role'] === 'admin') ? "Event created successfully." : "Event created successfully and sent for admin approval.";
    }

    if (mysqli_stmt_execute($stmt)) {
        if (!$event_id) { $event_id = mysqli_insert_id($conn); logActivity($conn, $scout_leader_id, 'Create Event', "Created event '$event_title' (ID: $event_id)"); }
        header("Location: events.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to save event.";
        header("Location: events.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $event_id ? "Edit Event" : "Create Event" ?></title>
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: url("images/wall3.jpg") no-repeat center center/cover fixed;
            min-height: 100vh;
            color: white;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        .container {
            position: relative;
            z-index: 1;
        }
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #000;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn {
            border-radius: 20px;
            padding: 10px 25px;
            font-weight: 600;
        }
        h2 {
            font-weight: 800;
            margin-bottom: 25px;
        }
        .img-thumbnail {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="glass">
        <h2><i class="bi bi-calendar-event"></i> <?= $event_id ? "Edit Event" : "Create Event" ?></h2>

    <form method="post" enctype="multipart/form-data">

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-card-heading"></i> Event Title</label>
            <input type="text" name="event_title" class="form-control"
                   value="<?= htmlspecialchars($event_title) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-text-paragraph"></i> Event Description</label>
            <textarea name="event_description" class="form-control" rows="4" required><?= htmlspecialchars($event_description) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-calendar-date"></i> Event Date</label>
            <input type="date" name="event_date" class="form-control"
                   value="<?= htmlspecialchars($event_date) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-geo-alt"></i> Event Location</label>
            <input type="text" name="event_location" class="form-control"
                   value="<?= htmlspecialchars($event_location) ?>" placeholder="Enter event location" required>
        </div>

        <div class="mb-3">
            <label class="form-label"><i class="bi bi-image"></i> Event Image</label>
            <input type="file" name="event_image" class="form-control">
            <?php if ($event_image) { ?>
                <img src="<?= htmlspecialchars($event_image) ?>" class="img-thumbnail mt-2" width="200">
            <?php } ?>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle"></i> <?= $event_id ? "Update Event" : "Create Event" ?>
        </button>

        <a href="events.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Events
        </a>
    </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
