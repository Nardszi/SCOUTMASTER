<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

/* Fetch pending meetings */
$query = "SELECT meetings.*, users.name AS created_by_name
          FROM meetings
          LEFT JOIN users ON meetings.created_by = users.id
          WHERE meetings.status = 'pending'
          ORDER BY meetings.created_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Approve Meetings</title>
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Pending Meetings</h3>
        <a href="dashboard.php" class="btn btn-secondary">⬅ Back</a>
    </div>

    <table class="table table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
                <th>Submitted By</th>
                <th width="20%">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?= htmlspecialchars($row['title']); ?></td>
                <td><?= htmlspecialchars($row['meeting_topic']); ?></td>
                <td><?= $row['scheduled_at']; ?></td>
                <td><?= htmlspecialchars($row['created_by_name'] ?? 'N/A'); ?></td>
                <td>
                    <form action="process_meeting.php" method="POST" class="d-inline">
                        <input type="hidden" name="meeting_id" value="<?= $row['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn btn-success btn-sm">Approve</button>
                    </form>

                    <form action="process_meeting.php" method="POST" class="d-inline">
                        <input type="hidden" name="meeting_id" value="<?= $row['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn btn-danger btn-sm">Reject</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

</body>
</html>
