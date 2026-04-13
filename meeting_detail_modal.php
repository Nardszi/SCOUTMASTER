<?php
session_start();
include('config.php');
$id = intval($_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM meetings WHERE id=$id");
$meeting = mysqli_fetch_assoc($query);
if($meeting){
    echo "<p><strong>Title:</strong> ".htmlspecialchars($meeting['title'])."</p>";
    echo "<p><strong>Date:</strong> ".htmlspecialchars($meeting['scheduled_at'])."</p>";
    echo "<p><strong>Description:</strong> ".htmlspecialchars($meeting['meeting_topic'])."</p>";

    // Fetch participants who joined the meeting from activity logs
    $part_query = "SELECT DISTINCT u.name 
                   FROM activity_logs al 
                   JOIN users u ON al.user_id = u.id 
                   WHERE al.action = 'Join Meeting' AND al.details LIKE '%(ID: $id)'";
    $part_result = mysqli_query($conn, $part_query);

    echo "<hr class='border-secondary'>";
    echo "<h6 class='fw-bold'><i class='bi bi-people-fill me-2'></i>Participants</h6>";
    if (mysqli_num_rows($part_result) > 0) {
        echo "<ul class='list-unstyled mb-0'>";
        while ($p = mysqli_fetch_assoc($part_result)) {
            echo "<li class='mb-1'><i class='bi bi-check-circle-fill text-success me-2'></i>" . htmlspecialchars($p['name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='text-white-50 small'>No participants have joined yet.</p>";
    }
}else{
    echo "<p>Meeting not found.</p>";
}
?>
