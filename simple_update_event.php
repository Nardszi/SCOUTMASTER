<?php
/**
 * SIMPLIFIED EVENT UPDATE FOR INFINITYFREE
 * Works around InfinityFree hosting limitations
 */

session_start();
include('config.php');

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    die("Access denied");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    
    $event_id = intval($_POST['event_id']);
    $event_title = mysqli_real_escape_string($conn, trim($_POST['event_title']));
    $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
    $event_location = mysqli_real_escape_string($conn, trim($_POST['event_location']));
    $event_description = mysqli_real_escape_string($conn, trim($_POST['event_description']));
    
    // Validate
    if (empty($event_title) || empty($event_date) || empty($event_location) || empty($event_description)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: simple_update_event.php?id=$event_id");
        exit();
    }
    
    // Get current event
    $check_query = "SELECT * FROM events WHERE id = $event_id";
    $check_result = mysqli_query($conn, $check_query);
    $current_event = mysqli_fetch_assoc($check_result);
    
    if (!$current_event) {
        $_SESSION['error'] = "Event not found.";
        header("Location: events.php");
        exit();
    }
    
    // Check permission
    if ($user_role !== 'admin' && $current_event['scout_leader_id'] != $user_id) {
        $_SESSION['error'] = "No permission to edit this event.";
        header("Location: events.php");
        exit();
    }
    
    $event_image = $current_event['event_image'];
    
    // Handle image upload (simplified)
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0755, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_name = time() . "_" . rand(1000, 9999) . "." . $ext;
            $target_file = $target_dir . $new_name;
            
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                // Delete old image
                if ($event_image && $event_image !== "uploads/default_event.png" && file_exists($event_image)) {
                    @unlink($event_image);
                }
                $event_image = $target_file;
            }
        }
    }
    
    $status = ($user_role === 'admin') ? 'approved' : 'pending';
    
    // Simple UPDATE query without prepared statements (InfinityFree sometimes has issues with prepared statements)
    $update_query = "UPDATE events SET 
        event_title = '$event_title',
        event_description = '$event_description',
        event_date = '$event_date',
        event_location = '$event_location',
        event_image = '$event_image',
        status = '$status'
        WHERE id = $event_id";
    
    if (mysqli_query($conn, $update_query)) {
        $affected = mysqli_affected_rows($conn);
        
        // Log activity
        $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) 
                      VALUES ($user_id, 'Update Event', 'Updated event ID $event_id', NOW())";
        @mysqli_query($conn, $log_query);
        
        if ($affected > 0) {
            $_SESSION['success'] = ($user_role === 'admin') 
                ? "Event updated successfully!" 
                : "Event updated and sent for approval!";
        } else {
            $_SESSION['success'] = "No changes detected.";
        }
        
        header("Location: events.php");
        exit();
    } else {
        $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
        header("Location: simple_update_event.php?id=$event_id");
        exit();
    }
}

// Get event to edit
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Event ID required.";
    header("Location: events.php");
    exit();
}

$event_id = intval($_GET['id']);
$query = "SELECT * FROM events WHERE id = $event_id";
$result = mysqli_query($conn, $query);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    $_SESSION['error'] = "Event not found.";
    header("Location: events.php");
    exit();
}

// Check permission
if ($user_role !== 'admin' && $event['scout_leader_id'] != $user_id) {
    $_SESSION['error'] = "No permission to edit this event.";
    header("Location: events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Simple Version</title>
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .edit-card {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .current-image {
            max-width: 200px;
            border-radius: 10px;
            margin: 10px 0;
        }
        .btn-custom {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="edit-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-pencil-square text-primary"></i> Edit Event</h2>
        <a href="events.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
        <input type="hidden" name="update_event" value="1">

        <div class="mb-3">
            <label class="form-label">
                <i class="bi bi-card-heading"></i> Event Title <span class="text-danger">*</span>
            </label>
            <input type="text" 
                   name="event_title" 
                   class="form-control" 
                   value="<?= htmlspecialchars($event['event_title']) ?>" 
                   required
                   maxlength="200">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="bi bi-geo-alt"></i> Location <span class="text-danger">*</span>
            </label>
            <input type="text" 
                   name="event_location" 
                   class="form-control" 
                   value="<?= htmlspecialchars($event['event_location'] ?? '') ?>" 
                   required
                   maxlength="200">
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="bi bi-text-paragraph"></i> Description <span class="text-danger">*</span>
            </label>
            <textarea name="event_description" 
                      class="form-control" 
                      rows="5" 
                      required
                      maxlength="1000"><?= htmlspecialchars($event['event_description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="bi bi-calendar-event"></i> Date <span class="text-danger">*</span>
            </label>
            <input type="date" 
                   name="event_date" 
                   class="form-control" 
                   value="<?= htmlspecialchars($event['event_date']) ?>" 
                   required>
        </div>

        <div class="mb-3">
            <label class="form-label">
                <i class="bi bi-image"></i> Event Image (Optional)
            </label>
            
            <?php if (!empty($event['event_image']) && file_exists($event['event_image'])): ?>
                <div class="mb-2">
                    <p class="mb-1"><strong>Current Image:</strong></p>
                    <img src="<?= htmlspecialchars($event['event_image']) ?>" 
                         class="current-image img-thumbnail" 
                         alt="Current">
                </div>
            <?php endif; ?>

            <input type="file" 
                   name="event_image" 
                   class="form-control" 
                   accept="image/jpeg,image/jpg,image/png,image/gif">
            <small class="text-muted">JPG, PNG, GIF only. Leave blank to keep current image.</small>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <?php if ($user_role === 'admin'): ?>
                As admin, changes are approved immediately.
            <?php else: ?>
                Changes will be sent for admin approval.
            <?php endif; ?>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-custom">
                <i class="bi bi-check-circle"></i> Update Event
            </button>
            <a href="events.php" class="btn btn-secondary btn-custom">
                <i class="bi bi-x-circle"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
