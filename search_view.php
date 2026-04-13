<?php
require 'config.php'; // Ensure you have a database connection file

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p class='text-danger text-center mt-4'>Invalid scout ID.</p>";
    exit;
}

$scout_id = intval($_GET['id']);

// Fetch scout details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $scout_id);
$stmt->execute();
$result = $stmt->get_result();

if ($scout = $result->fetch_assoc()) {
    $scout_name = htmlspecialchars($scout['name']);
    $scout_rank = htmlspecialchars($scout['rank']);
} else {
    echo "<p class='text-danger text-center mt-4'>Scout not found.</p>";
    exit;
}

// Fetch events attended
$stmt = $conn->prepare("SELECT events.event_title FROM event_attendance 
                        JOIN events ON event_attendance.event_id = events.id 
                        WHERE event_attendance.scout_id = ?");
$stmt->bind_param("i", $scout_id);
$stmt->execute();
$events_result = $stmt->get_result();
$events = $events_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #d2b48c; /* Matching homepage background */
        }
        .profile-container {
            max-width: 600px;
            margin: auto;
            margin-top: 50px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .back-btn {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container profile-container">
        <div class="card p-4 text-center">
            <h2 class="mb-3 text-primary">Scout Profile</h2>
            <h3 class="fw-bold"> <?php echo $scout_name; ?> </h3>
            <p class="text-muted"><strong>Rank:</strong> <?php echo $scout_rank; ?></p>
            <h4 class="mt-4">Events Attended</h4>
            <ul class="list-group mt-2">
                <?php if (!empty($events)) {
                    foreach ($events as $event) {
                        echo "<li class='list-group-item'>" . htmlspecialchars($event['event_title']) . "</li>";
                    }
                } else {
                    echo "<li class='list-group-item text-muted'>No events attended.</li>";
                } ?>
            </ul>
            <div class="back-btn">
                <a href="homepage.php" class="btn btn-success mt-3">Back to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>