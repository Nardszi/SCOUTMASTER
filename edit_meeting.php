<?php
session_start();
include('config.php');

// Ensure only Scout Leaders and Admins can access this page
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if a meeting ID is provided
if (!isset($_GET['meeting_id']) || empty($_GET['meeting_id'])) {
    $_SESSION['error'] = "Invalid meeting ID.";
    header("Location: view_meetings.php");
    exit();
}

$meeting_id = $_GET['meeting_id'];

// Fetch meeting details
$query = "SELECT * FROM meetings WHERE id = ? AND created_by = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $meeting_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$meeting = mysqli_fetch_assoc($result);

if (!$meeting) {
    $_SESSION['error'] = "Meeting not found or you don't have permission to edit it.";
    header("Location: view_meetings.php");
    exit();
}

// Fetch my scouts (for selection)
$my_scouts = [];
$s_query = "SELECT u.id, u.name FROM users u JOIN troops t ON u.troop_id = t.id WHERE t.scout_leader_id = ? AND u.role = 'scout'";
$s_stmt = mysqli_prepare($conn, $s_query);
mysqli_stmt_bind_param($s_stmt, "i", $user_id);
mysqli_stmt_execute($s_stmt);
$s_res = mysqli_stmt_get_result($s_stmt);
while($row = mysqli_fetch_assoc($s_res)) { $my_scouts[] = $row; }

// Fetch currently allowed users for this meeting
$current_allowed = [];
if (($meeting['allowed_role'] ?? '') === 'specific') {
    $ca_q = mysqli_query($conn, "SELECT user_id FROM meeting_allowed_users WHERE meeting_id = $meeting_id");
    while($ca_row = mysqli_fetch_assoc($ca_q)) { 
        $current_allowed[] = $ca_row['user_id']; 
    }
}

// Handle Meeting Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['meeting_topic']) && isset($_POST['meeting_date'])) {
    $meeting_topic = $_POST['meeting_topic'];
    $meeting_date = date('Y-m-d H:i:s', strtotime($_POST['meeting_date']));
     $meeting_end_date = !empty($_POST['meeting_end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['meeting_end_date'])) : null;
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $allowed_role = $_POST['allowed_role'] ?? 'all';

    $update_query = "UPDATE meetings SET title = ?, scheduled_at = ?, end_at = ?, is_private = ?, allowed_role = ? WHERE id = ? AND created_by = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sssisii", $meeting_topic, $meeting_date, $meeting_end_date, $is_private, $allowed_role, $meeting_id, $user_id);

    if (mysqli_stmt_execute($update_stmt)) {
        // Update specific scouts
        mysqli_query($conn, "DELETE FROM meeting_allowed_users WHERE meeting_id = $meeting_id");
        if ($allowed_role === 'specific' && !empty($_POST['specific_scouts'])) {
            $ins_u = mysqli_prepare($conn, "INSERT INTO meeting_allowed_users (meeting_id, user_id) VALUES (?, ?)");
            foreach ($_POST['specific_scouts'] as $uid) {
                mysqli_stmt_bind_param($ins_u, "ii", $meeting_id, $uid);
                mysqli_stmt_execute($ins_u);
            }
        }

        $_SESSION['success'] = "Meeting updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating meeting!";
    }
    header("Location: view_meetings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meeting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">Edit Meeting</div>
            <div class="card-body">
                <?php
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                    unset($_SESSION['error']);
                }
                ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Meeting Topic</label>
                        <input type="text" class="form-control" name="meeting_topic" value="<?php echo htmlspecialchars($meeting['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meeting Date & Time</label>
                        <input type="datetime-local" class="form-control" name="meeting_date" value="<?php echo date('Y-m-d\TH:i', strtotime($meeting['scheduled_at'])); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date & Time (Optional)</label>
                        <input type="datetime-local" class="form-control" name="meeting_end_date" value="<?php echo !empty($meeting['end_at']) ? date('Y-m-d\TH:i', strtotime($meeting['end_at'])) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Who can join?</label>
                        <select name="allowed_role" class="form-select" onchange="toggleScoutList(this)">
                            <option value="all" <?php echo ($meeting['allowed_role'] ?? 'all') == 'all' ? 'selected' : ''; ?>>Everyone</option>
                            <option value="scout" <?php echo ($meeting['allowed_role'] ?? '') == 'scout' ? 'selected' : ''; ?>>Scouts Only</option>
                            <option value="scout_leader" <?php echo ($meeting['allowed_role'] ?? '') == 'scout_leader' ? 'selected' : ''; ?>>Scout Leaders Only</option>
                            <option value="specific" <?php echo ($meeting['allowed_role'] ?? '') == 'specific' ? 'selected' : ''; ?>>Specific Scouts (My Troop)</option>
                        </select>
                    </div>
                    <div id="scoutList" class="mb-3 border p-2 rounded" style="display: <?php echo ($meeting['allowed_role'] ?? '') == 'specific' ? 'block' : 'none'; ?>; max-height: 200px; overflow-y: auto;">
                        <label class="form-label small text-muted">Select Scouts:</label>
                        <?php foreach ($my_scouts as $scout): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="specific_scouts[]" value="<?= $scout['id'] ?>" id="scout_<?= $scout['id'] ?>" <?= in_array($scout['id'], $current_allowed) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="scout_<?= $scout['id'] ?>"><?= htmlspecialchars($scout['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($my_scouts)) echo "<small class='text-danger'>No scouts found in your troop.</small>"; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_private" value="1" id="is_private_edit" <?php echo !empty($meeting['is_private']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_private_edit">
                            Enable Lobby (Moderator must approve joins)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Meeting</button>
                </form>
            </div>
        </div>
        <a href="view_meetings.php" class="btn btn-secondary mt-3">Back to Meetings</a>
    </div>
    <script>
        function toggleScoutList(select) {
            const target = document.getElementById('scoutList');
            target.style.display = (select.value === 'specific') ? 'block' : 'none';
        }
    </script>
</body>
</html>
