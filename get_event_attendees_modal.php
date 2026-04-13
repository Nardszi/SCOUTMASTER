<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit();
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Fetch attendees
$query = "SELECT ea.name, ea.scout_rank, u.profile_picture 
          FROM event_attendance ea 
          LEFT JOIN users u ON ea.scout_id = u.id 
          WHERE ea.event_id = ? 
          ORDER BY ea.name ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo '<ul class="list-group list-group-flush">';
    while ($row = mysqli_fetch_assoc($result)) {
        $img = !empty($row['profile_picture']) && file_exists($row['profile_picture']) 
            ? htmlspecialchars($row['profile_picture']) 
            : 'images/default_profile.png';
            
        echo '<li class="list-group-item bg-transparent d-flex align-items-center px-0">';
        echo '<img src="' . $img . '" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover; border: 1px solid #dee2e6;">';
        echo '<div>';
        echo '<div class="fw-bold">' . htmlspecialchars($row['name']) . '</div>';
        if (!empty($row['scout_rank'])) {
            echo '<small class="text-muted">' . htmlspecialchars($row['scout_rank']) . '</small>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<div class="text-center text-muted py-3"><i class="bi bi-people display-4 d-block mb-2"></i>No attendees yet.</div>';
}
?>
