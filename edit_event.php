<?php
session_start();
include('config.php');

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get event ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Event ID is required.";
    header("Location: events.php");
    exit();
}

$event_id = intval($_GET['id']);

// Fetch event
$query = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    $_SESSION['error'] = "Event not found.";
    header("Location: events.php");
    exit();
}

// Check permissions
if ($user_role !== 'admin' && $event['scout_leader_id'] != $user_id) {
    $_SESSION['error'] = "You don't have permission to edit this event.";
    header("Location: events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event</title>
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
        .edit-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            color: #333;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .current-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="edit-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square"></i> Edit Event</h2>
            <a href="events.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Events
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="update_event_handler.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

            <div class="mb-3">
                <label for="event_title" class="form-label">
                    <i class="bi bi-card-heading"></i> Event Title <span class="text-danger">*</span>
                </label>
                <input type="text" 
                       class="form-control" 
                       id="event_title" 
                       name="event_title" 
                       value="<?= htmlspecialchars($event['event_title']) ?>" 
                       required
                       maxlength="200">
            </div>

            <div class="mb-3">
                <label for="event_location" class="form-label">
                    <i class="bi bi-geo-alt"></i> Event Location <span class="text-danger">*</span>
                </label>
                <input type="text" 
                       class="form-control" 
                       id="event_location" 
                       name="event_location" 
                       value="<?= htmlspecialchars($event['event_location'] ?? '') ?>" 
                       required
                       maxlength="200">
            </div>

            <div class="mb-3">
                <label for="event_description" class="form-label">
                    <i class="bi bi-text-paragraph"></i> Event Description <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" 
                          id="event_description" 
                          name="event_description" 
                          rows="5" 
                          required
                          maxlength="1000"><?= htmlspecialchars($event['event_description']) ?></textarea>
                <small class="text-muted">Maximum 1000 characters</small>
            </div>

            <div class="mb-3">
                <label for="event_date" class="form-label">
                    <i class="bi bi-calendar-event"></i> Event Date <span class="text-danger">*</span>
                </label>
                <input type="date" 
                       class="form-control" 
                       id="event_date" 
                       name="event_date" 
                       value="<?= htmlspecialchars($event['event_date']) ?>" 
                       required>
            </div>

            <div class="mb-3">
                <label for="event_image" class="form-label">
                    <i class="bi bi-image"></i> Event Image (Optional)
                </label>
                
                <?php if (!empty($event['event_image']) && file_exists($event['event_image'])): ?>
                    <div class="mb-3">
                        <p class="mb-2"><strong>Current Image:</strong></p>
                        <img src="<?= htmlspecialchars($event['event_image']) ?>" 
                             class="current-image img-thumbnail" 
                             alt="Current event image">
                    </div>
                <?php endif; ?>

                <input type="file" 
                       class="form-control" 
                       id="event_image" 
                       name="event_image" 
                       accept="image/jpeg,image/jpg,image/png,image/gif">
                <small class="text-muted">
                    Accepted: JPG, PNG, GIF (Max 10MB). Leave blank to keep current image.
                </small>
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                <?php if ($user_role === 'admin'): ?>
                    As an admin, your changes will be approved immediately.
                <?php else: ?>
                    Your changes will be sent for admin approval.
                <?php endif; ?>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Update Event
                </button>
                <a href="events.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
