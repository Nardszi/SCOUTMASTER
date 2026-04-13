<?php
/**
 * PERMISSION CHECKER
 * Check file and directory permissions
 */

session_start();
include('config.php');

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    die("Access denied. Please login first.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Checker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .check-item { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .pass { background: #d4edda; border-left: 4px solid #28a745; }
        .fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔐 Permission Checker</h1>
    <p class="text-muted">Checking file and directory permissions for event updates</p>

    <?php
    $checks = [];
    
    // Check 1: Uploads directory
    $uploads_dir = 'uploads/';
    if (!file_exists($uploads_dir)) {
        $checks[] = [
            'status' => 'fail',
            'title' => 'Uploads Directory',
            'message' => 'Directory does not exist',
            'fix' => 'Create the directory: mkdir uploads && chmod 755 uploads'
        ];
    } else {
        $perms = substr(sprintf('%o', fileperms($uploads_dir)), -4);
        $writable = is_writable($uploads_dir);
        
        $checks[] = [
            'status' => $writable ? 'pass' : 'fail',
            'title' => 'Uploads Directory',
            'message' => "Exists: YES | Writable: " . ($writable ? 'YES' : 'NO') . " | Permissions: $perms",
            'fix' => $writable ? '' : 'Run: chmod 755 uploads/ or chmod 777 uploads/'
        ];
    }
    
    // Check 2: update_event_handler.php
    $handler_file = 'update_event_handler.php';
    if (!file_exists($handler_file)) {
        $checks[] = [
            'status' => 'fail',
            'title' => 'Update Handler File',
            'message' => 'File does not exist',
            'fix' => 'Upload update_event_handler.php to server'
        ];
    } else {
        $perms = substr(sprintf('%o', fileperms($handler_file)), -4);
        $readable = is_readable($handler_file);
        
        $checks[] = [
            'status' => $readable ? 'pass' : 'fail',
            'title' => 'Update Handler File',
            'message' => "Exists: YES | Readable: " . ($readable ? 'YES' : 'NO') . " | Permissions: $perms",
            'fix' => $readable ? '' : 'Run: chmod 644 update_event_handler.php'
        ];
    }
    
    // Check 3: edit_event.php
    $edit_file = 'edit_event.php';
    if (!file_exists($edit_file)) {
        $checks[] = [
            'status' => 'fail',
            'title' => 'Edit Event File',
            'message' => 'File does not exist',
            'fix' => 'Upload edit_event.php to server'
        ];
    } else {
        $perms = substr(sprintf('%o', fileperms($edit_file)), -4);
        $readable = is_readable($edit_file);
        
        $checks[] = [
            'status' => $readable ? 'pass' : 'fail',
            'title' => 'Edit Event File',
            'message' => "Exists: YES | Readable: " . ($readable ? 'YES' : 'NO') . " | Permissions: $perms",
            'fix' => $readable ? '' : 'Run: chmod 644 edit_event.php'
        ];
    }
    
    // Check 4: Database connection
    if ($conn && mysqli_ping($conn)) {
        $checks[] = [
            'status' => 'pass',
            'title' => 'Database Connection',
            'message' => 'Connected successfully',
            'fix' => ''
        ];
    } else {
        $checks[] = [
            'status' => 'fail',
            'title' => 'Database Connection',
            'message' => 'Connection failed: ' . mysqli_connect_error(),
            'fix' => 'Check config.php database credentials'
        ];
    }
    
    // Check 5: Events table
    if ($conn) {
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
        if (mysqli_num_rows($table_check) > 0) {
            $checks[] = [
                'status' => 'pass',
                'title' => 'Events Table',
                'message' => 'Table exists',
                'fix' => ''
            ];
        } else {
            $checks[] = [
                'status' => 'fail',
                'title' => 'Events Table',
                'message' => 'Table does not exist',
                'fix' => 'Import database schema'
            ];
        }
    }
    
    // Check 6: User permissions
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    $checks[] = [
        'status' => 'pass',
        'title' => 'User Session',
        'message' => "User ID: $user_id | Role: $user_role",
        'fix' => ''
    ];
    
    // Check 7: PHP settings
    $upload_max = ini_get('upload_max_filesize');
    $post_max = ini_get('post_max_size');
    $memory_limit = ini_get('memory_limit');
    
    $checks[] = [
        'status' => 'pass',
        'title' => 'PHP Settings',
        'message' => "Upload Max: $upload_max | POST Max: $post_max | Memory: $memory_limit",
        'fix' => ''
    ];
    
    // Display results
    foreach ($checks as $check) {
        $class = $check['status'];
        echo '<div class="check-item ' . $class . '">';
        echo '<h5>' . ($check['status'] === 'pass' ? '✅' : '❌') . ' ' . $check['title'] . '</h5>';
        echo '<p>' . $check['message'] . '</p>';
        if (!empty($check['fix'])) {
            echo '<p><strong>Fix:</strong> <code>' . $check['fix'] . '</code></p>';
        }
        echo '</div>';
    }
    ?>

    <div class="check-item warning">
        <h5>⚠️ InfinityFree Specific Issues</h5>
        <p>If you're on InfinityFree hosting, note these limitations:</p>
        <ul>
            <li>File permissions are automatically managed (you can't change them via FTP)</li>
            <li>Some PHP functions may be restricted</li>
            <li>Database connections might have delays</li>
            <li>File uploads must go to htdocs or public_html directory</li>
        </ul>
    </div>

    <div class="mt-4">
        <h3>📂 Directory Structure</h3>
        <pre><?php
        $files = [
            'edit_event.php',
            'update_event_handler.php',
            'events.php',
            'config.php',
            'uploads/'
        ];
        
        foreach ($files as $file) {
            $exists = file_exists($file) ? '✅' : '❌';
            $type = is_dir($file) ? '[DIR]' : '[FILE]';
            echo "$exists $type $file\n";
        }
        ?></pre>
    </div>

    <div class="mt-4">
        <a href="events.php" class="btn btn-primary">Back to Events</a>
        <a href="debug_event_update.php" class="btn btn-info">Full Debug Page</a>
    </div>
</div>

</body>
</html>
