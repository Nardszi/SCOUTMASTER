<?php
/**
 * STANDALONE EVENT UPDATE HANDLER WITH DETAILED LOGGING
 * This file handles event updates separately for better debugging
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('config.php');

// Create a log file for debugging
$log_file = 'update_log.txt';
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

write_log("=== UPDATE EVENT HANDLER STARTED ===");
write_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
write_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    write_log("ERROR: Access denied - User not logged in or wrong role");
    $_SESSION['error'] = "Access denied. Please login.";
    header("Location: login.php");
    exit();
}

write_log("User ID: " . $_SESSION['user_id'] . " | Role: " . $_SESSION['role']);

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    write_log("ERROR: Not a POST request - redirecting silently");
    // Silently redirect to events page if accessed directly (not an error, just wrong access method)
    header("Location: events.php");
    exit();
}

write_log("POST request confirmed");

// Check if event_id is provided
if (!isset($_POST['event_id']) || empty($_POST['event_id'])) {
    write_log("ERROR: event_id not provided in POST data");
    write_log("POST data: " . print_r($_POST, true));
    $_SESSION['error'] = "Event ID is required.";
    header("Location: events.php");
    exit();
}

// Get form data
$event_id = intval($_POST['event_id']);
$event_title = trim($_POST['event_title'] ?? '');
$event_date = $_POST['event_date'] ?? '';
$event_location = trim($_POST['event_location'] ?? '');
$event_description = trim($_POST['event_description'] ?? '');
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

write_log("Event ID: $event_id");
write_log("Event Title: $event_title");
write_log("Event Date: $event_date");
write_log("Event Location: $event_location");
write_log("Description Length: " . strlen($event_description));

// Validate required fields
if (empty($event_title) || empty($event_date) || empty($event_location) || empty($event_description)) {
    write_log("ERROR: Required fields missing");
    $_SESSION['error'] = "All fields are required.";
    header("Location: events.php");
    exit();
}

write_log("All required fields present");

// Fetch current event data
$fetch_query = "SELECT event_image, scout_leader_id FROM events WHERE id = ?";
$fetch_stmt = mysqli_prepare($conn, $fetch_query);

if (!$fetch_stmt) {
    write_log("ERROR: Failed to prepare fetch statement - " . mysqli_error($conn));
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    header("Location: events.php");
    exit();
}

mysqli_stmt_bind_param($fetch_stmt, "i", $event_id);
mysqli_stmt_execute($fetch_stmt);
$result = mysqli_stmt_get_result($fetch_stmt);
$current_event = mysqli_fetch_assoc($result);

// Check if event exists
if (!$current_event) {
    write_log("ERROR: Event not found with ID: $event_id");
    $_SESSION['error'] = "Event not found.";
    header("Location: events.php");
    exit();
}

write_log("Event found - Owner ID: " . $current_event['scout_leader_id']);

// Check permissions
if ($user_role !== 'admin' && $current_event['scout_leader_id'] != $user_id) {
    write_log("ERROR: Permission denied - User $user_id trying to edit event owned by " . $current_event['scout_leader_id']);
    $_SESSION['error'] = "You don't have permission to edit this event.";
    header("Location: events.php");
    exit();
}

write_log("Permission check passed");

// Handle image upload
$event_image = $current_event['event_image']; // Keep current image by default
write_log("Current image: $event_image");

if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
    write_log("New image uploaded - Processing...");
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        write_log("Creating uploads directory...");
        if (mkdir($target_dir, 0755, true)) {
            write_log("Uploads directory created");
        } else {
            write_log("ERROR: Failed to create uploads directory");
        }
    }
    
    $file_extension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    write_log("File extension: $file_extension");
    
    // Validate file type
    if (in_array($file_extension, $allowed_extensions)) {
        // Generate unique filename
        $new_filename = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        write_log("Target file: $target_file");
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            write_log("Image uploaded successfully");
            
            // Delete old image if it's not the default
            if (!empty($current_event['event_image']) && 
                file_exists($current_event['event_image']) && 
                $current_event['event_image'] !== "uploads/default_event.png") {
                if (@unlink($current_event['event_image'])) {
                    write_log("Old image deleted: " . $current_event['event_image']);
                } else {
                    write_log("WARNING: Could not delete old image");
                }
            }
            
            $event_image = $target_file;
        } else {
            write_log("ERROR: Failed to move uploaded file");
        }
    } else {
        write_log("ERROR: Invalid file extension: $file_extension");
    }
} else {
    if (isset($_FILES['event_image'])) {
        write_log("No new image uploaded - Error code: " . $_FILES['event_image']['error']);
    } else {
        write_log("No image file in request");
    }
}

// Determine status
$status = ($user_role === 'admin') ? 'approved' : 'pending';
write_log("Status: $status");

// Prepare update query
$update_query = "UPDATE events 
                 SET event_title = ?, 
                     event_description = ?, 
                     event_date = ?, 
                     event_location = ?,
                     event_image = ?, 
                     status = ?
                 WHERE id = ?";

write_log("Preparing UPDATE query...");

$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    write_log("ERROR: Failed to prepare update statement - " . mysqli_error($conn));
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    header("Location: events.php");
    exit();
}

write_log("UPDATE statement prepared successfully");

// Bind parameters
mysqli_stmt_bind_param(
    $update_stmt,
    "ssssssi",
    $event_title,
    $event_description,
    $event_date,
    $event_location,
    $event_image,
    $status,
    $event_id
);

write_log("Parameters bound - Executing query...");

// Execute update
if (mysqli_stmt_execute($update_stmt)) {
    $affected_rows = mysqli_stmt_affected_rows($update_stmt);
    write_log("Query executed successfully - Affected rows: $affected_rows");
    
    // Log activity
    logActivity($conn, $user_id, 'Update Event', "Updated event ID $event_id: '$event_title'");
    write_log("Activity logged");
    
    if ($affected_rows > 0) {
        $_SESSION['success'] = ($user_role === 'admin') 
            ? "Event updated successfully." 
            : "Event updated and sent for admin approval.";
        write_log("SUCCESS: Event updated");
    } else {
        $_SESSION['success'] = "No changes were made to the event.";
        write_log("WARNING: No rows affected (data might be identical)");
    }
} else {
    write_log("ERROR: Query execution failed - " . mysqli_stmt_error($update_stmt));
    $_SESSION['error'] = "Failed to update event: " . mysqli_error($conn);
}

mysqli_stmt_close($update_stmt);

write_log("=== UPDATE EVENT HANDLER COMPLETED ===\n");

// Redirect back to events page
header("Location: events.php");
exit();
?>
