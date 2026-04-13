<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Events Page Diagnostic</h1>";

// Test 1: PHP is working
echo "<h2>✅ PHP is working</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Test 2: Session
session_start();
echo "<h2>Session Check</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in: ID = " . $_SESSION['user_id'] . ", Role = " . $_SESSION['role'] . "<br>";
} else {
    echo "❌ User NOT logged in<br>";
    echo "<a href='login.php'>Go to Login</a><br>";
}

// Test 3: Config file
echo "<h2>Config File</h2>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    include('config.php');
    if (isset($conn)) {
        echo "✅ Database connection variable exists<br>";
        if (mysqli_ping($conn)) {
            echo "✅ Database connection is active<br>";
        } else {
            echo "❌ Database connection is not active<br>";
        }
    } else {
        echo "❌ Database connection variable not set<br>";
    }
} else {
    echo "❌ config.php not found<br>";
}

// Test 4: Check if events.php file exists and is readable
echo "<h2>Events.php File</h2>";
if (file_exists('events.php')) {
    echo "✅ events.php exists<br>";
    if (is_readable('events.php')) {
        echo "✅ events.php is readable<br>";
        $filesize = filesize('events.php');
        echo "File size: " . number_format($filesize) . " bytes<br>";
    } else {
        echo "❌ events.php is NOT readable<br>";
    }
} else {
    echo "❌ events.php not found<br>";
}

// Test 5: Try to access events.php
echo "<h2>Access Test</h2>";
echo "<a href='events.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Click to Access events.php</a><br><br>";

// Test 6: Memory and execution limits
echo "<h2>Server Limits</h2>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . " seconds<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";

// Test 7: Check for syntax errors in events.php
echo "<h2>Syntax Check</h2>";
echo "⚠️ exec() function is disabled on this hosting - cannot check syntax automatically<br>";
echo "But if this page loads, PHP syntax is generally working fine.<br>";

echo "<hr>";
echo "<h2>Navigation</h2>";
echo "<a href='dashboard_admin_leader.php'>Dashboard</a> | ";
echo "<a href='test_event_creation.php'>Test Event Creation</a> | ";
echo "<a href='check_events.php'>Refresh This Page</a>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 20px; background: #e9ecef; padding: 10px; border-radius: 5px; }
    a { color: #007bff; text-decoration: none; font-weight: bold; }
    a:hover { text-decoration: underline; }
    pre { background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
</style>
