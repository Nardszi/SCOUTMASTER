<?php
session_start();
include('config.php');

/* Allow only admin */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

/* Fetch pending events */
$query = "SELECT events.*, users.name AS created_by_name
          FROM events
          LEFT JOIN users ON events.created_by = users.id
          WHERE events.status = 'pending'
          ORDER BY events.created_at DESC";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Events</title>
<?php include('favicon_header.php'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
/* BASE */
body{
    margin:0;
    font-family:'Segoe UI',sans-serif;
    min-height:100vh;
    background:#000;
    color:white;
}

/* LAYOUT */
.wrapper{
    display:flex;
    min-height:100vh;
}

/* MAIN BACKGROUND */
.main{
    flex:1;
    margin-left: 240px;
    padding:30px;
    background:url("images/wall.png") no-repeat center center/cover;
    position:relative;
    display:flex;
    flex-direction:column;
}

/* OVERLAY */
.main::before{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.55);
    z-index:0;
}

.main > *{
    position:relative;
    z-index:1;
}

/* GLASS */
.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(10px);
    border-radius:20px;
    padding:20px;
    margin-bottom:20px;
}

/* HEADER */
.page-title{
    font-size:36px;
    font-weight:800;
    margin-bottom:20px;
}

/* TABLE */
.table{
    color:white;
}

.table thead{
    background:rgba(0,0,0,0.7);
}

.table-striped tbody tr:nth-of-type(odd){
    background-color:rgba(255,255,255,0.05);
}

.table td, .table th{
    vertical-align:middle;
}

/* BUTTONS */
.btn{
    border-radius:20px;
}

/* Sidebar Styles */
.sidebar{
    width:240px;
    background:#0b3d91;
    padding:20px;
}

.sidebar img{
    width:90px;
    display:block;
    margin:0 auto 12px;
}

.sidebar a{
    display:block;
    padding:10px;
    margin:8px 0;
    border-radius:5px;
    background:transparent;
    color:white;
    text-align:left;
    padding-left:20px;
    font-weight:600;
    text-decoration:none;
}

.sidebar a:hover{
    background:#006400;
}
</style>
</head>

<body>

<div class="wrapper">

    <!-- SIDEBAR -->
    <?php include('sidebar.php'); ?>

    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- Top Navbar -->
        <?php include('navbar.php'); ?>

        <div class="glass">
        <h2 class="page-title">Pending Events for Approval</h2>

        <div class="table-responsive">
            <table class="table table-striped align-middle text-center">
                <thead>
                    <tr>
                        <th>Event Title</th>
                        <th>Date</th>
                        <th>Submitted By</th>
                        <th width="150">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr class="searchable-row">
                            <td><?= htmlspecialchars($row['event_title']) ?></td>
                            <td><?= date('M d, Y', strtotime($row['event_date'])) ?></td>
                            <td><?= htmlspecialchars($row['created_by_name'] ?? 'N/A') ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal<?= $row['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>

                        <!-- MODAL -->
                        <div class="modal fade" id="modal<?= $row['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content text-dark">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?= htmlspecialchars($row['event_title']) ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if(!empty($row['event_image'])): ?>
                                            <div class="text-center mb-3">
                                                <img src="uploads/<?= htmlspecialchars($row['event_image']) ?>" class="img-fluid rounded" style="max-height:300px;">
                                            </div>
                                        <?php endif; ?>
                                        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($row['event_description'])) ?></p>
                                        <p><strong>Date:</strong> <?= date('M d, Y', strtotime($row['event_date'])) ?></p>
                                        <p><strong>Submitted By:</strong> <?= htmlspecialchars($row['created_by_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="modal-footer justify-content-between">
                                        <form action="process_event_approval.php" method="POST">
                                            <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="action" value="approved">
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                                        </form>
                                        <form action="process_event_approval.php" method="POST">
                                            <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="action" value="rejected">
                                            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No pending events.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        </div>
        <?php include('footer.php'); ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality for the navbar
document.getElementById("globalSearch").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let rows = document.querySelectorAll(".searchable-row");
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>
</body>
</html>
