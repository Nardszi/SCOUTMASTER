<?php
/**
 * Migration Script: Update scout_type ENUM to include all position types
 * This allows both old values (boy_scout, outfit_scout) and new values (platoon_leader, troop_leader)
 */

include('config.php');

echo "<h2>Database Migration: Update Scout Type ENUM</h2>";
echo "<hr>";

// Modify the ENUM to include all values
$alter_query = "ALTER TABLE users 
                MODIFY COLUMN scout_type ENUM('boy_scout', 'outfit_scout', 'platoon_leader', 'troop_leader') NULL";

if (mysqli_query($conn, $alter_query)) {
    echo "<p style='color: green;'>✓ Successfully updated scout_type ENUM to include all position types!</p>";
    echo "<p>Now supports: Boy Scout, Outfit Scout, Platoon Leader, Troop Leader</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to update ENUM: " . mysqli_error($conn) . "</p>";
}

echo "<hr>";
echo "<h3>Current scout_type values in database:</h3>";
$check_query = "SELECT scout_type, COUNT(*) as count FROM users WHERE scout_type IS NOT NULL GROUP BY scout_type";
$result = mysqli_query($conn, $check_query);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Scout Type</th><th>Count</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['scout_type']) . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No scouts with scout_type set yet.</p>";
}

echo "<hr>";
echo "<h3>Table Structure:</h3>";
$describe_query = "SHOW COLUMNS FROM users LIKE 'scout_type'";
$describe_result = mysqli_query($conn, $describe_query);

if ($row = mysqli_fetch_assoc($describe_result)) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
    echo "</table>";
}

echo "<hr>";
echo "<p><strong>Migration Complete!</strong></p>";
echo "<p style='color: red;'><strong>IMPORTANT:</strong> Delete this file (update_scout_type_enum.php) after running it.</p>";

mysqli_close($conn);
?>
