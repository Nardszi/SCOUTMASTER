<?php
session_start();
include('config.php');

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader', 'scout'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch events the user has already joined
$joined_events = [];
$attendance_query = "SELECT event_id FROM event_attendance WHERE scout_id = ?";
$stmt_attendance = mysqli_prepare($conn, $attendance_query);
mysqli_stmt_bind_param($stmt_attendance, "i", $user_id);
mysqli_stmt_execute($stmt_attendance);
$attendance_result = mysqli_stmt_get_result($stmt_attendance);
while ($row = mysqli_fetch_assoc($attendance_result)) {
    $joined_events[] = $row['event_id'];
}

// Fetch approved events (including today's events)
$current_date = date('Y-m-d');
$query = "SELECT events.*, users.name AS created_by_name,
          (SELECT COUNT(*) FROM event_attendance WHERE event_attendance.event_id = events.id) AS attendee_count
          FROM events
          LEFT JOIN users ON events.scout_leader_id = users.id
          WHERE events.status = 'approved' AND events.event_date >= '$current_date'
          ORDER BY events.event_date ASC";
$result = mysqli_query($conn, $query);

// Collect events for modal generation after main content
$events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Events</title>
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
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
    position:relative;
    background:url("images/wall3.jpg") no-repeat center center/cover;
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

/* BUTTONS */
.btn{
    border-radius:20px;
}

.btn-join {
    background-color: #28a745;
    color: white;
    font-weight: bold;
    padding: 8px 12px;
    border-radius: 8px;
    transition: 0.3s;
}
.btn-join:hover {
    background-color: #218838;
}

/* Cards */
.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s;
    background: rgba(255,255,255,0.9);
    box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
    height: 100%;
    display: flex;
    flex-direction: column;
    color: black;
}
.card:hover {
    transform: scale(1.02);
}
.card-img-top {
    width: 100%;
    height: 220px;
    object-fit: cover;
}
.card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.card-text {
    flex: 1;
    overflow: hidden;
    margin-bottom: 10px;
}

/* Responsive */
@media (min-width: 768px) {
    .col-md-6 { display: flex; }
}

/* Bootstrap modal fix */
.modal { z-index: 99999 !important; }
.modal-backdrop { z-index: 99998 !important; }
.btn-close { filter: brightness(0) invert(1); opacity: 1; cursor: pointer; pointer-events: auto !important; }

/* Modal specific styling for visibility */
.modal-content {
    background-color: #fff; /* Solid white background for modal content */
    color: #212529; /* Default dark text color for contrast */
    border-radius: 10px; /* Slightly rounded corners */
}
.modal-header {
    border-bottom: 1px solid #dee2e6; /* Light border for header */
}

/* Card Animation */
.animate-card {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}
.animate-card.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Calendar View Styles */
#calendarView {
    display: none;
}
#calendarView.active {
    display: block;
}
#cardView.active {
    display: block;
}
#cardView {
    display: none;
}

.fc {
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 15px;
    color: #000;
}
.fc .fc-button {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.fc .fc-button:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}
.fc-event {
    cursor: pointer;
    border-radius: 4px;
}
.fc-daygrid-event {
    white-space: normal !important;
}

/* Mobile Calendar Styles */
@media (max-width: 768px) {
    .fc {
        padding: 10px;
        border-radius: 12px;
    }

    /* Calendar toolbar */
    .fc .fc-toolbar {
        flex-direction: column;
        gap: 10px;
        padding: 10px 5px;
    }

    .fc .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        width: 100%;
    }

    .fc .fc-toolbar-title {
        font-size: 1.2rem;
    }

    /* Calendar buttons */
    .fc .fc-button {
        padding: 6px 12px;
        font-size: 13px;
    }

    .fc .fc-button-group {
        display: flex;
    }

    /* Calendar grid */
    .fc .fc-daygrid-day-number {
        font-size: 12px;
        padding: 4px;
    }

    .fc .fc-col-header-cell {
        font-size: 11px;
        padding: 8px 2px;
    }

    .fc .fc-daygrid-day-frame {
        min-height: 60px;
    }

    /* Calendar events */
    .fc-event {
        font-size: 11px;
        padding: 2px 4px;
        margin-bottom: 2px;
    }

    .fc-event-title {
        font-size: 11px;
        line-height: 1.2;
    }

    /* Hide event time on mobile for cleaner look */
    .fc-event-time {
        display: none;
    }

    /* Scrollable calendar on mobile */
    .fc-scroller {
        overflow-x: auto !important;
    }
}

@media (max-width: 576px) {
    .fc {
        padding: 8px;
    }

    .fc .fc-toolbar-title {
        font-size: 1rem;
    }

    .fc .fc-button {
        padding: 5px 10px;
        font-size: 12px;
    }

    .fc .fc-daygrid-day-number {
        font-size: 11px;
        padding: 2px;
    }

    .fc .fc-col-header-cell {
        font-size: 10px;
        padding: 6px 1px;
    }

    .fc .fc-daygrid-day-frame {
        min-height: 50px;
    }

    .fc-event {
        font-size: 10px;
        padding: 1px 3px;
    }

    .fc-event-title {
        font-size: 10px;
    }

    /* Stack toolbar buttons vertically on very small screens */
    .fc .fc-toolbar-chunk:first-child .fc-button-group {
        flex-direction: column;
        width: 100%;
    }

    .fc .fc-toolbar-chunk:first-child .fc-button {
        width: 100%;
        margin-bottom: 5px;
    }
}

.view-toggle-btn {
    border-radius: 20px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s;
}
.view-toggle-btn.active {
    background-color: #0d6efd;
    color: white;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Mobile menu toggle button */
    .mobile-menu-toggle {
        display: flex !important;
        position: fixed !important;
        top: 15px !important;
        left: 15px !important;
        z-index: 99999 !important;
        width: 44px !important;
        height: 44px !important;
        background: linear-gradient(135deg, #28a745, #1e7e34) !important;
        border: none !important;
        border-radius: 10px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4) !important;
        align-items: center !important;
        justify-content: center !important;
        pointer-events: auto !important;
    }
    
    .mobile-menu-toggle i {
        font-size: 18px !important;
    }
    
    .main {
        margin-left: 0 !important;
        padding: 70px 15px 15px;
    }
    
    body.sidebar-collapsed .main {
        margin-left: 0 !important;
    }
    
    .page-title {
        font-size: 24px;
        margin-bottom: 15px;
    }
    
    .glass {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    /* Stack buttons vertically on mobile */
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .view-toggle-btn {
        padding: 10px 15px;
        font-size: 14px;
    }
    
    /* Card adjustments */
    .card-img-top {
        height: 180px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .card-title {
        font-size: 18px;
    }
    
    .card-text {
        font-size: 14px;
    }
    
    /* Button adjustments - larger for touch */
    .btn {
        padding: 10px 15px;
        font-size: 14px;
        min-height: 44px;
        touch-action: manipulation;
    }
    
    /* Calendar mobile view */
    .fc {
        padding: 10px;
        font-size: 12px;
    }
    
    .fc-toolbar {
        flex-direction: column;
        gap: 10px;
    }
    
    .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
    }
    
    /* Modal adjustments */
    .modal-dialog {
        margin: 10px;
    }
    
    .modal-body {
        padding: 15px;
    }
    
    .modal-body .row {
        flex-direction: column;
    }
    
    .modal-body .col-md-5,
    .modal-body .col-md-7 {
        width: 100%;
        max-width: 100%;
    }

    /* Form controls - better touch targets */
    .form-control,
    .form-select {
        font-size: 16px;
        padding: 12px 15px;
        min-height: 44px;
    }

    /* Ensure all interactive elements are touchable */
    a, button, input[type="submit"], input[type="button"], .card {
        min-height: 44px;
        touch-action: manipulation;
    }
}

@media (max-width: 576px) {
    .mobile-menu-toggle {
        width: 40px !important;
        height: 40px !important;
        top: 12px !important;
        left: 12px !important;
    }
    
    .mobile-menu-toggle i {
        font-size: 16px !important;
    }
    
    .page-title {
        font-size: 20px;
    }
    
    .col-12 {
        padding-left: 8px;
        padding-right: 8px;
    }
    
    .card {
        margin-bottom: 15px;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .view-toggle-btn {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 5px;
    }

    .btn {
        font-size: 13px;
        padding: 8px 12px;
    }
}

/* Mobile Modal Styles */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }

    .modal-dialog-centered {
        min-height: calc(100% - 2rem);
    }

    .modal-content {
        border-radius: 12px;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-title {
        font-size: 1.1rem;
    }

    .modal-body {
        padding: 1rem;
        font-size: 14px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .modal-body img {
        max-width: 100%;
        height: auto;
    }

    .modal-body .row {
        margin: 0;
    }

    .modal-body .col-md-5,
    .modal-body .col-md-7 {
        padding: 0;
        margin-bottom: 1rem;
    }

    .modal-footer {
        padding: 0.875rem 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .modal-footer .btn {
        flex: 1;
        min-width: 120px;
    }

    /* Large modals on mobile */
    .modal-lg {
        max-width: calc(100% - 1rem);
        margin: 0.5rem;
    }

    /* Date inputs on mobile */
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"],
    input[type="file"] {
        font-size: 16px !important;
        padding: 12px 15px;
        min-height: 44px;
    }
}

@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }

    .modal-header {
        padding: 0.875rem;
    }

    .modal-title {
        font-size: 1rem;
    }

    .modal-body {
        padding: 0.875rem;
        font-size: 13px;
    }

    .modal-footer {
        padding: 0.75rem 0.875rem;
        flex-direction: column;
    }

    .modal-footer .btn {
        width: 100%;
        margin: 0;
    }

    .modal-footer .badge {
        width: 100%;
        margin-bottom: 0.5rem;
    }
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
            <h2 class="page-title"><i class="bi bi-calendar-event"></i> Upcoming Events</h2>
            <div class="d-flex gap-2 align-items-center">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-light view-toggle-btn active" id="cardViewBtn" onclick="switchView('card')">
                        <i class="bi bi-grid-3x3-gap"></i> Card View
                    </button>
                    <button type="button" class="btn btn-outline-light view-toggle-btn" id="calendarViewBtn" onclick="switchView('calendar')">
                        <i class="bi bi-calendar3"></i> Calendar View
                    </button>
                </div>
                <?php if ($_SESSION['role'] !== 'scout'): ?>
                <a href="events.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Messages -->
        <?php
        if (isset($_SESSION['success'])) { echo '<div class="alert alert-success">'.$_SESSION['success'].'</div>'; unset($_SESSION['success']); } 
        if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">'.$_SESSION['error'].'</div>'; unset($_SESSION['error']); }
        ?>

        <!-- Card View -->
        <div id="cardView" class="active">
            <div class="row g-4">
                <?php foreach ($events as $event) { ?>
                    <div class="col-12 col-md-6 col-lg-4 d-flex searchable-item animate-card">
                        <div class="card w-100">
                            <?php
                            $image_path = $event['event_image'];
                            if (!empty($image_path) && !file_exists($image_path)) {
                                $image_path = 'uploads/' . $image_path;
                            }
                            if (!empty($image_path) && file_exists($image_path)) {
                                echo '<img src="' . htmlspecialchars($image_path) . '" class="card-img-top" alt="Event Image">';
                            } else {
                                echo '<img src="images/default_event.png" class="card-img-top" alt="No Image">';
                            }
                            ?>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($event['event_title']); ?></h5>
                                    <?php if ($event['event_date'] == date('Y-m-d')): ?>
                                        <span class="badge bg-danger ms-2">
                                            <i class="bi bi-broadcast"></i> ONGOING
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="card-text"><strong>Date:</strong> <?= htmlspecialchars($event['event_date']); ?></p>
                                <p class="card-text"><strong>Location:</strong> <?= htmlspecialchars($event['event_location'] ?? 'Not specified'); ?></p>
                                <p class="card-text"><strong>Attendees:</strong> 
                                    <a href="#" class="text-decoration-none" onclick="showAttendees(<?= $event['id'] ?>); return false;">
                                        <span class="badge bg-info text-dark" style="cursor: pointer;" title="View Attendees"><?= $event['attendee_count'] ?></span>
                                    </a>
                                </p>
                                <p class="card-text"><strong>Created by:</strong> <?= htmlspecialchars($event['created_by_name'] ?? 'N/A'); ?></p
                                <p class="card-text"><?= nl2br(htmlspecialchars(substr($event['event_description'], 0, 100))); ?><?= strlen($event['event_description']) > 100 ? '...' : ''; ?></p>
                                <div class="d-flex gap-2 mt-auto">
                                    <button class="btn btn-info flex-grow-1" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $event['id']; ?>">
                                        <i class="bi bi-info-circle"></i> View Details
                                    </button>
                                    <?php if (in_array($event['id'], $joined_events)) { ?>
                                        <button type="button" class="btn btn-danger flex-grow-1" data-bs-toggle="modal" data-bs-target="#leaveEventModal<?= $event['id'] ?>">
                                            <i class="fas fa-sign-out-alt"></i> Leave
                                        </button>
                                    <?php } else { ?>
                                        <button class="btn btn-join flex-grow-1" data-bs-toggle="modal" data-bs-target="#joinModal<?= $event['id']; ?>">
                                            <i class="fas fa-user-plus"></i> Join
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Calendar View -->
        <div id="calendarView">
            <div id="calendar"></div>
        </div>
    </div>
    <?php include('footer.php'); ?>
</div>
</div>

<!-- Attendees Modal -->
<div class="modal fade" id="attendeesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content text-dark">
            <div class="modal-header">
                <h5 class="modal-title">Event Attendees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="attendeesModalBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modals -->
<?php foreach ($events as $event) { ?>
<div class="modal fade" id="detailsModal<?= $event['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content text-dark">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-event"></i> Event Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <?php
                        $image_path = $event['event_image'];
                        if (!empty($image_path) && !file_exists($image_path)) {
                            $image_path = 'uploads/' . $image_path;
                        }
                        if (!empty($image_path) && file_exists($image_path)) {
                            echo '<img src="' . htmlspecialchars($image_path) . '" class="img-fluid rounded mb-3" alt="Event Image">';
                        } else {
                            echo '<img src="images/default_event.png" class="img-fluid rounded mb-3" alt="No Image">';
                        }
                        ?>
                    </div>
                    <div class="col-md-7">
                        <h4 class="mb-3"><?= htmlspecialchars($event['event_title']); ?></h4>
                        
                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-calendar3"></i> Event Date</h6>
                            <p class="ms-3"><?= htmlspecialchars($event['event_date']); ?></p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-geo-alt"></i> Location</h6>
                            <p class="ms-3"><?= htmlspecialchars($event['event_location'] ?? 'Not specified'); ?></p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-person-badge"></i> Organized By</h6>
                            <p class="ms-3"><?= htmlspecialchars($event['created_by_name'] ?? 'N/A'); ?></p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-people"></i> Attendees</h6>
                            <p class="ms-3">
                                <a href="#" class="text-decoration-none" onclick="showAttendees(<?= $event['id'] ?>); return false;">
                                    <span class="badge bg-info text-dark" style="cursor: pointer; font-size: 1rem;" title="View Attendees">
                                        <?= $event['attendee_count'] ?> registered
                                    </span>
                                </a>
                            </p>
                        </div>

                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-file-text"></i> Description</h6>
                            <p class="ms-3"><?= nl2br(htmlspecialchars($event['event_description'])); ?></p>
                        </div>

                        <?php if (!empty($event['event_requirements'])) { ?>
                        <div class="mb-3">
                            <h6 class="text-muted"><i class="bi bi-list-check"></i> Requirements</h6>
                            <p class="ms-3"><?= nl2br(htmlspecialchars($event['event_requirements'])); ?></p>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (in_array($event['id'], $joined_events)) { ?>
                    <span class="badge bg-success me-auto" style="font-size: 1rem;">
                        <i class="fas fa-check-circle"></i> You are registered for this event
                    </span>
                    <form action="leave_event.php" method="POST">
                        <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#leaveEventModal<?= $event['id'] ?>" data-bs-dismiss="modal">
                            <i class="fas fa-sign-out-alt"></i> Leave Event
                        </button>
                    </form>
                <?php } else { ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#joinModal<?= $event['id']; ?>" data-bs-dismiss="modal">
                        <i class="fas fa-user-plus"></i> Join This Event
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- Join Event Modals -->
<?php foreach ($events as $event) { ?>
<div class="modal fade" id="joinModal<?= $event['id']; ?>" tabindex="-1" aria-labelledby="joinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content text-dark">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Join Event: <?= htmlspecialchars($event['event_title']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="join_event.php" enctype="multipart/form-data" id="joinForm<?= $event['id']; ?>">
                    <input type="hidden" name="join_event_id" value="<?= $event['id']; ?>">
                    <input type="hidden" name="user_id" value="<?= $user_id; ?>">

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address *</label>
                        <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($_SESSION['address'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-medal"></i> Scout Rank *</label>
                        <input type="text" class="form-control" name="scout_rank" value="<?= htmlspecialchars($_SESSION['rank'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-phone"></i> Emergency Contact *</label>
                        <input type="text" class="form-control" name="emergency_contact" value="<?= htmlspecialchars($_SESSION['emergency_contact'] ?? ''); ?>" required placeholder="Name and Phone Number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-comment"></i> Reason for Joining *</label>
                        <textarea class="form-control" name="reason" rows="2" required placeholder="Why do you want to join this event?"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-list"></i> Additional Requirements</label>
                        <textarea class="form-control" name="requirements" rows="2" placeholder="Any special requirements or notes (optional)"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-file-upload"></i> Parent Waiver *</label>
                        <input type="file" class="form-control" name="waiver_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted d-block mt-1">
                            <a href="waiver_template.php" target="_blank" class="text-primary">
                                <i class="fas fa-download"></i> Download Waiver Form
                            </a> - Sign and upload (PDF, JPG, PNG)
                        </small>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check-circle"></i> Confirm Join
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
// Calendar initialization
let calendar;
const eventsData = <?= json_encode(array_map(function($event) use ($joined_events) {
    return [
        'id' => $event['id'],
        'title' => $event['event_title'],
        'start' => $event['event_date'],
        'description' => $event['event_description'],
        'location' => $event['event_location'] ?? 'Not specified',
        'attendees' => $event['attendee_count'],
        'organizer' => $event['created_by_name'] ?? 'N/A',
        'joined' => in_array($event['id'], $joined_events),
        'backgroundColor' => in_array($event['id'], $joined_events) ? '#28a745' : '#0d6efd',
        'borderColor' => in_array($event['id'], $joined_events) ? '#218838' : '#0b5ed7'
    ];
}, $events)); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    // Detect if mobile
    const isMobile = window.innerWidth <= 768;
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: isMobile ? 'listWeek' : 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: isMobile ? 'dayGridMonth,listWeek' : 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: eventsData,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const eventId = info.event.id;
            const modal = new bootstrap.Modal(document.getElementById('detailsModal' + eventId));
            modal.show();
        },
        eventContent: function(arg) {
            let html = '<div class="fc-event-main-frame">';
            html += '<div class="fc-event-title-container">';
            html += '<div class="fc-event-title fc-sticky">';
            if (arg.event.extendedProps.joined) {
                html += '<i class="fas fa-check-circle me-1"></i>';
            }
            html += arg.event.title;
            html += '</div></div></div>';
            return { html: html };
        },
        height: 'auto',
        eventDisplay: 'block',
        // Mobile-specific settings
        aspectRatio: isMobile ? 1 : 1.35,
        handleWindowResize: true,
        windowResize: function(view) {
            if (window.innerWidth <= 768) {
                calendar.changeView('listWeek');
            } else {
                calendar.changeView('dayGridMonth');
            }
        }
    });
});

// View switching
function switchView(view) {
    const cardView = document.getElementById('cardView');
    const calendarView = document.getElementById('calendarView');
    const cardBtn = document.getElementById('cardViewBtn');
    const calendarBtn = document.getElementById('calendarViewBtn');
    
    if (view === 'calendar') {
        cardView.classList.remove('active');
        calendarView.classList.add('active');
        cardBtn.classList.remove('active');
        calendarBtn.classList.add('active');
        
        // Render calendar when switching to calendar view
        if (calendar) {
            setTimeout(() => calendar.render(), 100);
        }
    } else {
        cardView.classList.add('active');
        calendarView.classList.remove('active');
        cardBtn.classList.add('active');
        calendarBtn.classList.remove('active');
    }
}

// Search functionality for the navbar
document.getElementById("globalSearch").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let items = document.querySelectorAll(".searchable-item");
    items.forEach(item => {
        let text = item.innerText.toLowerCase();
        if (text.includes(input)) {
            item.style.display = "flex"; // This is a flex container
        } else {
            item.style.display = "none";
        }
    });
});

function showAttendees(eventId) {
    const modalBody = document.getElementById('attendeesModalBody');
    modalBody.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    const modal = new bootstrap.Modal(document.getElementById('attendeesModal'));
    modal.show();

    fetch('get_event_attendees_modal.php?event_id=' + eventId)
        .then(response => response.text())
        .then(data => {
            modalBody.innerHTML = data;
        })
        .catch(error => {
            modalBody.innerHTML = '<p class="text-danger text-center">Error loading attendees.</p>';
        });
}

// Animation Observer
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    const cards = document.querySelectorAll('.animate-card');
    cards.forEach((card, index) => {
        card.style.transitionDelay = `${(index % 3) * 100}ms`; // Stagger effect
        observer.observe(card);
    });
});
</script>
<!-- Leave Event Modals -->
<?php foreach ($events as $event): ?>
<?php if (in_array($event['id'], $joined_events)): ?>
<div class="modal fade" id="leaveEventModal<?= $event['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(20,20,20,0.97);border:1px solid rgba(255,255,255,0.15);color:white;border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;padding:1.25rem 1.5rem;">
                <h5 class="modal-title" style="color:white;"><i class="fas fa-sign-out-alt me-2"></i> Leave Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="leave_event.php" method="POST">
                <div class="modal-body text-center py-4" style="background:rgba(20,20,20,0.97);">
                    <p class="mb-1" style="color:rgba(255,255,255,0.7);">You are about to leave</p>
                    <p class="fw-bold" style="font-size:1.1rem;color:#e74c3c;"><?= htmlspecialchars($event['event_title']) ?></p>
                    <p class="mb-0" style="font-size:0.85rem;color:rgba(255,255,255,0.5);">You can rejoin later if the event is still open.</p>
                </div>
                <div class="modal-footer justify-content-center" style="border-top:1px solid rgba(255,255,255,0.1);background:rgba(20,20,20,0.97);gap:1rem;">
                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fas fa-sign-out-alt me-1"></i> Yes, Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

</body>
</html>
