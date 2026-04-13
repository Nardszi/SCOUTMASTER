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
$query_my_scouts = "SELECT users.id, users.name, users.email, users.troop_id, troops.troop_name, ranks.rank_name, users.rank_id
                    FROM users 
                    LEFT JOIN troops ON users.troop_id = troops.id 
                    LEFT JOIN ranks ON users.rank_id = ranks.id
                    WHERE users.role = 'scout' AND troops.scout_leader_id = ? $archive_condition";

$query_all_scouts = "SELECT users.id, users.name, users.email, users.troop_id, troops.troop_name, ranks.rank_name, users.rank_id
                     FROM users 
                     LEFT JOIN troops ON users.troop_id = troops.id 
                     LEFT JOIN ranks ON users.rank_id = ranks.id
                     WHERE users.role = 'scout' $archive_condition";

$query = $view_all ? $query_all_scouts : $query_my_scouts;
$stmt = mysqli_prepare($conn, $query);

if (!$view_all) {
    mysqli_stmt_bind_param($stmt, "i", $scout_leader_id);
}

$search_query = isset($_GET['search']) ? trim($_GET['search']) : "";

mysqli_stmt_execute($stmt);
$scouts = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scouts</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="content">
    <div class="container mt-4 d-flex justify-content-between align-items-center">
        <h2>Manage Scouts</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerScoutModal">Register Scout</button>
    </div>
</div>

<!-- Register Scout Modal -->
<div class="modal fade" id="registerScoutModal" tabindex="-1" aria-labelledby="registerScoutModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registerScoutModalLabel">Register Scout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="register_scout.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Surname, Given Name, M.I.</label>
                        <input type="text" class="form-control" name="scout_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Registration Status</label>
                        <select class="form-control" name="registration_status" required>
                            <option value="N">New</option>
                            <option value="RR">Re-registering</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" class="form-control" name="age" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sex</label>
                        <select class="form-control" name="sex" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membership Card No.</label>
                        <input type="text" class="form-control" name="membership_card" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Highest Rank Earned</label>
                        <input type="text" class="form-control" name="highest_rank">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Years in Scouting</label>
                        <input type="number" class="form-control" name="years_in_scouting">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit No.</label>
                        <input type="text" class="form-control" name="unit_no">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Local Council</label>
                        <input type="text" class="form-control" name="local_council">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>