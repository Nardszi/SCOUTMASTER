<?php
session_start();
include('config.php');

/* Ensure Scout Leader is logged in */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header("Location: dashboard.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

/* HANDLE CREATE EVENT FROM MODAL */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $event_title = trim($_POST['event_title']);
    $event_date = $_POST['event_date'];
    $event_location = trim($_POST['event_location']);
    $event_description = trim($_POST['event_description']);

    /* IMAGE UPLOAD */
    $event_image = "uploads/default_event.png";
    if (!empty($_FILES['event_image']['name'])) {
        $target_dir = "uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $image_name = time() . "_" . basename($_FILES['event_image']['name']);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                $event_image = $target_file;
            }
        }
    }

    $status = ($_SESSION['role'] === 'admin') ? 'approved' : 'pending';

    $insert_query = "INSERT INTO events 
                    (event_title, event_description, event_date, event_location, event_image, scout_leader_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param(
        $stmt,
        "sssssis",
        $event_title,
        $event_description,
        $event_date,
        $event_location,
        $event_image,
        $user_id,
        $status
    );

    if (mysqli_stmt_execute($stmt)) {
        $event_id = mysqli_insert_id($conn);
        logActivity($conn, $user_id, 'Create Event', "Created event '$event_title' (ID: $event_id)");
        $_SESSION['success'] = ($_SESSION['role'] === 'admin') ? "Event created successfully." : "Event created successfully and sent for admin approval.";
    } else {
        $_SESSION['error'] = "Failed to create event.";
    }
    
    header("Location: events.php");
    exit();
}

/* HANDLE UPDATE EVENT FROM MODAL - SAME AS edit_event_simple.php */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event']) && isset($_POST['event_id'])) {
    
    $event_id = (int)$_POST['event_id'];
    $title = mysqli_real_escape_string($conn, trim($_POST['event_title']));
    $location = mysqli_real_escape_string($conn, trim($_POST['event_location']));
    $description = mysqli_real_escape_string($conn, trim($_POST['event_description']));
    $date = mysqli_real_escape_string($conn, $_POST['event_date']);
    
    if (empty($title) || empty($location) || empty($description) || empty($date)) {
        $_SESSION['error'] = "All fields are required";
    } else {
        // Get current event
        $check = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
        $current = mysqli_fetch_assoc($check);
        
        if (!$current) {
            $_SESSION['error'] = "Event not found";
        } elseif ($user_role !== 'admin' && $current['scout_leader_id'] != $user_id) {
            $_SESSION['error'] = "No permission";
        } else {
            $image = $current['event_image'];
            
            // Handle image
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === 0) {
                $allowed = array('jpg', 'jpeg', 'png', 'gif');
                $filename = $_FILES['event_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $newname = time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $target = 'uploads/' . $newname;
                    
                    if (!is_dir('uploads')) {
                        @mkdir('uploads', 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target)) {
                        if ($image && $image !== 'uploads/default_event.png' && file_exists($image)) {
                            @unlink($image);
                        }
                        $image = $target;
                    }
                }
            }
            
            $status = ($user_role === 'admin') ? 'approved' : 'pending';
            
            $sql = "UPDATE events SET 
                    event_title = '$title',
                    event_location = '$location',
                    event_description = '$description',
                    event_date = '$date',
                    event_image = '$image',
                    status = '$status'
                    WHERE id = $event_id";
            
            if (mysqli_query($conn, $sql)) {
                // Log activity
                $log_query = "INSERT INTO activity_log (user_id, action, details, timestamp) 
                              VALUES ($user_id, 'Update Event', 'Updated event ID $event_id', NOW())";
                @mysqli_query($conn, $log_query);
                
                $_SESSION['success'] = "Event updated successfully!";
            } else {
                $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
            }
        }
    }
    
    header("Location: events.php");
    exit();
}

$filter_my = isset($_GET['filter']) && $_GET['filter'] === 'my';

/* Fetch events */
$events_query = "
    SELECT 
        e.id, e.event_title, COALESCE(e.event_description, '') AS event_description,
        e.event_date, e.event_image, e.status, e.event_location, u.name AS creator_name, e.scout_leader_id,
        (SELECT COUNT(*) FROM event_attendance ea WHERE ea.event_id = e.id) as attendee_count
    FROM events e
    LEFT JOIN users u ON e.scout_leader_id = u.id
";

if ($filter_my) {
    $events_query .= " WHERE e.scout_leader_id = $user_id";
}

$events_query .= " ORDER BY e.event_date DESC";
$events_result = mysqli_query($conn, $events_query);

/* Fetch all events into an array to use for both table and modals */
$events_list = [];
$upcoming_events = [];
$past_events = [];
$current_date = date('Y-m-d');

if ($events_result) {
    while ($row = mysqli_fetch_assoc($events_result)) {
        $events_list[] = $row;
        if ($row['event_date'] >= $current_date) {
            $upcoming_events[] = $row;
        } else {
            $past_events[] = $row;
        }
    }
}

/* Sort upcoming events ASC (closest date first) */
usort($upcoming_events, function($a, $b) {
    return strtotime($a['event_date']) - strtotime($b['event_date']);
});

/* Fetch pending events for Admin */
$pending_events_result = null;
if ($_SESSION['role'] === 'admin') {
    $pending_query = "SELECT events.*, users.name AS creator_name 
                      FROM events 
                      LEFT JOIN users ON events.scout_leader_id = users.id 
                      WHERE events.status = 'pending' 
                      ORDER BY events.event_date ASC";
    $pending_events_result = mysqli_query($conn, $pending_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Events</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
    background:url("images/wall3.jpg") no-repeat center center/cover;
    background-attachment: fixed;
    position:relative;
    display:flex;
    flex-direction:column;
    transition: margin-left 0.3s ease-in-out;
}

body.sidebar-collapsed .main {
    margin-left: 80px;
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

.event-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 6px;
}

/* Bootstrap modal fix */
.modal { z-index: 99999 !important; }
.modal-backdrop { z-index: 99998 !important; }

/* Modal specific styling for visibility */
.modal-content {
    background-color: #fff;
    color: #212529;
    border-radius: 10px;
}
.modal-header {
    border-bottom: 1px solid #dee2e6;
}
.modal-footer {
    border-top: 1px solid #dee2e6;
}

/* Logout modal override */
#logoutModal .modal-content {
    background: rgba(20, 20, 40, 0.95) !important;
    color: white !important;
    border: 1px solid rgba(255,255,255,0.15);
}
#logoutModal .modal-header,
#logoutModal .modal-footer {
    border-color: rgba(255,255,255,0.1) !important;
}
</style>
</head>
<body>

<div class="wrapper">

<!-- Sidebar -->
<?php include('sidebar.php'); ?>

<!-- Main Content -->
<div class="main">

    <!-- Top Navbar -->
    <?php include('navbar.php'); ?>

    <div class="glass">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title"><i class="bi bi-calendar-event-fill"></i> Manage Events</h2>
        <div>
            <?php if ($_SESSION['role'] === 'admin') { ?>
                <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#approveEventsModal">
                    <i class="bi bi-check-circle"></i> Approve Events
                    <?php if($pending_events_result && mysqli_num_rows($pending_events_result) > 0): ?>
                        <span class="badge bg-danger"><?= mysqli_num_rows($pending_events_result) ?></span>
                    <?php endif; ?>
                </button>
            <?php } ?>

            <div class="btn-group me-2">
                <a href="events.php" class="btn btn-<?= !$filter_my ? 'primary' : 'outline-light' ?>">All Events</a>
                <a href="events.php?filter=my" class="btn btn-<?= $filter_my ? 'primary' : 'outline-light' ?>">My Events</a>
            </div>

            <a href="view_events.php" class="btn btn-primary me-2">
                <i class="bi bi-eye"></i> View Events
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleEventModal">
                <i class="bi bi-plus-circle"></i> Schedule Event
            </button>
        </div>
    </div>

    <p class="mb-4">
        Manage all events you created as a Scout Leader. View event descriptions,
        check admin approval status, edit details, or delete events when necessary.
    </p>

    <!-- ALERTS -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- UPCOMING EVENTS -->
    <h4 class="text-primary mb-3"><i class="bi bi-calendar-event"></i> Upcoming Events</h4>
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-5">
            <thead class="text-center">
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php if(count($upcoming_events) > 0): ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <tr class="searchable-row">
                        <!-- IMAGE -->
                        <td class="text-center">
                            <?php
                            $img = (!empty($event['event_image']) && file_exists($event['event_image']))
                                ? $event['event_image']
                                : 'images/default_event.png';
                            ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="event-img">
                        </td>

                        <!-- TITLE -->
                        <td><?= htmlspecialchars($event['event_title']) ?></td>

                        <!-- LOCATION -->
                        <td><?= htmlspecialchars($event['event_location'] ?? 'Not specified') ?></td>

                        <!-- DESCRIPTION -->
                        <td style="max-width:300px;">
                            <?= !empty($event['event_description'])
                                ? nl2br(htmlspecialchars($event['event_description']))
                                : '<span>No description</span>'; ?>
                        </td>

                        <!-- DATE -->
                        <td class="text-center"><?= date("F j, Y", strtotime($event['event_date'])) ?></td>

                        <!-- CREATED BY -->
                        <td class="text-center"><?= htmlspecialchars($event['creator_name'] ?? 'N/A') ?></td>

                        <!-- STATUS -->
                        <td class="text-center">
                            <?php
                            if ($event['status'] === 'approved') {
                                echo '<span class="badge bg-success">Approved</span>';
                            } elseif ($event['status'] === 'rejected') {
                                echo '<span class="badge bg-danger">Rejected</span>';
                            } else {
                                echo '<span class="badge bg-warning text-dark">Pending</span>';
                            }
                            ?>
                        </td>

                        <!-- ACTIONS -->
                        <td class="text-center">
                            <?php if ($event['scout_leader_id'] == $user_id || $_SESSION['role'] === 'admin'): ?>
                            <a href="view_event_attendees.php?highlight=<?= $event['id'] ?>" class="btn btn-info btn-sm mb-1 text-white">
                                <i class="bi bi-people-fill"></i> Attendees <span class="badge bg-light text-dark ms-1"><?= $event['attendee_count'] ?></span>
                            </a>
                            <a href="edit_event_simple.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-sm mb-1">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                            <button type="button" class="btn btn-danger btn-sm mb-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteModal<?= $event['id'] ?>">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">
                        No upcoming events found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ARCHIVE EVENTS (Admin only) -->
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'scout_leader'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="text-muted mb-0"><i class="bi bi-archive"></i> Archive Events</h4>
            <input type="text" id="archiveSearch" class="form-control form-control-sm" style="width: 250px;" placeholder="Search archive...">
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="text-center">
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($past_events) > 0): ?>
                    <?php foreach ($past_events as $event): ?>
                        <tr class="searchable-row archive-row">
                            <td class="text-center">
                                <?php
                                $img = (!empty($event['event_image']) && file_exists($event['event_image']))
                                    ? $event['event_image']
                                    : 'images/default_event.png';
                                ?>
                                <img src="<?= htmlspecialchars($img) ?>" class="event-img">
                            </td>
                            <td><?= htmlspecialchars($event['event_title']) ?></td>
                            <td><?= htmlspecialchars($event['event_location'] ?? 'Not specified') ?></td>
                            <td style="max-width:300px;">
                                <?= !empty($event['event_description'])
                                    ? nl2br(htmlspecialchars($event['event_description']))
                                    : '<span>No description</span>'; ?>
                            </td>
                            <td class="text-center"><?= date("F j, Y", strtotime($event['event_date'])) ?></td>
                            <td class="text-center"><?= htmlspecialchars($event['creator_name'] ?? 'N/A') ?></td>
                            <td class="text-center">
                                <?php if ($event['status'] === 'approved') { echo '<span class="badge bg-success">Approved</span>'; } elseif ($event['status'] === 'rejected') { echo '<span class="badge bg-danger">Rejected</span>'; } else { echo '<span class="badge bg-warning text-dark">Pending</span>'; } ?>
                            </td>
                            <td class="text-center">
                                <?php if ($event['scout_leader_id'] == $user_id || $_SESSION['role'] === 'admin'): ?>
                                <a href="view_event_attendees.php?highlight=<?= $event['id'] ?>" class="btn btn-info btn-sm mb-1 text-white">
                                    <i class="bi bi-people-fill"></i> Attendees <span class="badge bg-light text-dark ms-1"><?= $event['attendee_count'] ?></span>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal<?= $event['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center">No past events found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>

    <?php include('footer.php'); ?>
</div>

<!-- Modals for Events (Delete & Edit) - Placed outside table to fix z-index issues -->
<?php foreach ($events_list as $event): ?>
    <!-- DELETE MODAL -->
    <div class="modal fade" id="deleteModal<?= $event['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 16px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #c0392b, #e74c3c); border: none; padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Event
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="delete_event_new.php">
                    <div class="modal-body text-center py-4">
                        <p class="mb-1" style="font-size: 1rem; color: rgba(255,255,255,0.7);">You are about to delete</p>
                        <p class="fw-bold" style="font-size: 1.1rem; color: #e74c3c;"><?= htmlspecialchars($event['event_title']) ?></p>
                        <p class="mb-0" style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer justify-content-center" style="border-top: 1px solid rgba(255,255,255,0.1); gap: 1rem;">
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit functionality now opens in popup window -->
<?php endforeach; ?>

<!-- Schedule Event Modal -->
<div class="modal fade" id="scheduleEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Schedule New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="events.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="create_event" value="1">
                    <div class="mb-3">
                        <label class="form-label">Event Title</label>
                        <input type="text" name="event_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Location</label>
                        <input type="text" name="event_location" class="form-control" placeholder="Enter event location" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Description</label>
                        <textarea name="event_description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Date</label>
                        <input type="date" name="event_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Image</label>
                        <input type="file" name="event_image" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle"></i> Create Event
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Approve Events Modal -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="approveEventsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content text-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Pending Events Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Submitted By</th>
                                <th>Details</th>
                                <th>Actions</th>    
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($pending_events_result && mysqli_num_rows($pending_events_result) > 0): ?>
                            <?php while($p_event = mysqli_fetch_assoc($pending_events_result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p_event['event_title']) ?></td>
                                    <td><?= date('M d, Y', strtotime($p_event['event_date'])) ?></td>
                                    <td><?= htmlspecialchars($p_event['creator_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#desc<?= $p_event['id'] ?>">
                                            View Info
                                        </button>
                                        <div class="collapse mt-2" id="desc<?= $p_event['id'] ?>">
                                            <small><?= nl2br(htmlspecialchars($p_event['event_description'])) ?></small>
                                            <?php if(!empty($p_event['event_image'])): ?>
                                                <br><img src="<?= htmlspecialchars($p_event['event_image']) ?>" style="max-width:100px; margin-top:5px;" class="rounded">
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <form action="process_event_approval.php" method="POST">
                                                <input type="hidden" name="event_id" value="<?= $p_event['id'] ?>">
                                                <input type="hidden" name="action" value="approved">
                                                <button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form action="process_event_approval.php" method="POST">
                                                <input type="hidden" name="event_id" value="<?= $p_event['id'] ?>">
                                                <input type="hidden" name="action" value="rejected">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Reject"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No pending events found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

// Search functionality for Archive Events
const archiveSearchInput = document.getElementById("archiveSearch");
if (archiveSearchInput) {
    archiveSearchInput.addEventListener("keyup", function() {
        let input = this.value.toLowerCase();
        let rows = document.querySelectorAll(".archive-row");
        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        });
    });
}

// Page loaded successfully
document.addEventListener('DOMContentLoaded', function() {
    console.log('Events page loaded successfully');
});
</script>
</body>
</html>
