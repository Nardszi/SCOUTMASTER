<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'boyscout_db1';
$username = 'root'; // Change this if needed
$password = ''; // Change this if needed

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

$query = isset($_GET['q']) ? '%' . $conn->real_escape_string($_GET['q']) . '%' : '%';

$sql = "SELECT id, name, rank FROM users WHERE role = 'scout' AND name LIKE ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $query);
$stmt->execute();
$result = $stmt->get_result();

$scouts = [];
while ($row = $result->fetch_assoc()) {
    $scouts[] = $row; // Now returning 'id' as well
}

$stmt->close();
$conn->close();

echo json_encode($scouts);
?>
