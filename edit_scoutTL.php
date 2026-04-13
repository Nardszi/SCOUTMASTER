<?php
session_start();
include('config.php');

// Allow only Scout Leaders to access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout_leader') {
    header('Location: dashboard.php');
    exit();
}

$scout_leader_id = $_SESSION['user_id'];
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

// Check for is_archived column to filter out archived scouts
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'is_archived'");
$has_archive = mysqli_num_rows($check_col) > 0;
$archive_condition = $has_archive ? " AND (users.is_archived = 0 OR users.is_archived IS NULL)" : "";

// Fetch scouts
$query_my_scouts = "SELECT users.id, users.name, users.email, users.troop_id, troops.troop_name 
                    FROM users 
                    LEFT JOIN troops ON users.troop_id = troops.id 
                    WHERE users.role = 'scout' AND troops.scout_leader_id = ? $archive_condition";

$query_all_scouts = "SELECT users.id, users.name, users.email, users.troop_id, troops.troop_name 
                     FROM users 
                     LEFT JOIN troops ON users.troop_id = troops.id 
                     WHERE users.role = 'scout' $archive_condition";

$query = $view_all ? $query_all_scouts : $query_my_scouts;
$stmt = mysqli_prepare($conn, $query);

if (!$view_all) {
    mysqli_stmt_bind_param($stmt, "i", $scout_leader_id);
}

mysqli_stmt_execute($stmt);
$scouts = mysqli_stmt_get_result($stmt);

// Fetch available troops
$troop_query = "SELECT id, troop_name FROM troops WHERE scout_leader_id = ?";
$troop_stmt = mysqli_prepare($conn, $troop_query);
mysqli_stmt_bind_param($troop_stmt, "i", $scout_leader_id);
mysqli_stmt_execute($troop_stmt);
$troops = mysqli_stmt_get_result($troop_stmt);
$troop_options = mysqli_fetch_all($troops, MYSQLI_ASSOC);

// Handle troop assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_troop'])) {
    $scout_id = $_POST['scout_id'];
    $troop_id = $_POST['troop_id'];

    $assign_query = "UPDATE users SET troop_id = ? WHERE id = ? AND role = 'scout'";
    $assign_stmt = mysqli_prepare($conn, $assign_query);
    mysqli_stmt_bind_param($assign_stmt, "ii", $troop_id, $scout_id);
    mysqli_stmt_execute($assign_stmt);

    header("Location: manage_scoutsTL.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scouts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { display: flex; }
        .sidebar { width: 250px; height: 100vh; background-color: #343a40; color: white; padding: 20px; position: fixed; left: 0; top: 0; }
        .sidebar h4 { text-align: center;}
        .sidebar a { display: block; color: white; padding: 10px; text-decoration: none; border-radius: 5px; transition: 0.3s; }
        .sidebar a:hover { background-color: #495057; }
        .content { flex-grow: 1; padding: 50px; margin-left: 260px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center">
            <h4>Boy Scout Management</h4>
        </div>
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_scoutsTL.php">Manage Scouts</a>
        <a href="schedule_meeting.php">Schedule Meeting</a>
        <a href="events.php"><i class="fas fa-calendar-alt"></i> Manage Events</a>
        <a href="view_meetings.php">View Meetings</a>
        <a href="video_meeting.php" class="fas fa-user-check">Start Video Meeting</a>
        <a href="view_events.php">View Events</a>
        <a href="logout.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container mt-4">
            <h2>Manage Scouts</h2>
            <a href="manage_scoutsTL.php?view=my" class="btn btn-<?php echo !$view_all ? 'primary' : 'secondary'; ?>">My Scouts</a>
            <a href="manage_scoutsTL.php?view=all" class="btn btn-<?php echo $view_all ? 'primary' : 'secondary'; ?>">All Scouts</a>
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Troop</th>
                        <th>Assign Troop</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($scouts)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['troop_name'] ?? 'Unassigned'); ?></td>
                            <td>
                                <form method="post" class="d-flex">
                                    <input type="hidden" name="scout_id" value="<?php echo $row['id']; ?>">
                                    <select name="troop_id" class="form-select" required>
                                        <option value="">Select Troop</option>
                                        <?php foreach ($troop_options as $troop) { ?>
                                            <option value="<?php echo $troop['id']; ?>"> <?php echo htmlspecialchars($troop['troop_name']); ?> </option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" name="assign_troop" class="btn btn-success ms-2">Assign</button>
                                </form>
                            </td>
                            <td>
                                <a href="edit_scout.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
