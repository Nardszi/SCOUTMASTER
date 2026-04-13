<?php
session_start();
include('config.php');

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Fetch my scouts if I am a leader (for the selection list)
$my_scouts = [];
if ($user_role == 'scout_leader') {
    $s_query = "SELECT u.id, u.name FROM users u JOIN troops t ON u.troop_id = t.id WHERE t.scout_leader_id = ? AND u.role = 'scout'";
    $s_stmt = mysqli_prepare($conn, $s_query);
    mysqli_stmt_bind_param($s_stmt, "i", $user_id);
    mysqli_stmt_execute($s_stmt);
    $s_res = mysqli_stmt_get_result($s_stmt);
    while($row = mysqli_fetch_assoc($s_res)) { $my_scouts[] = $row; }
}

// Auto-archive and delete meetings immediately after they finish
// Archive old meetings before deleting
$retention_condition = "COALESCE(end_at, scheduled_at + INTERVAL 2 HOUR) < NOW()";
@mysqli_query($conn, "INSERT INTO meeting_archive (original_id, title, meeting_link, scheduled_at, created_by, meeting_topic) SELECT id, title, meeting_link, scheduled_at, created_by, meeting_topic FROM meetings WHERE $retention_condition AND id NOT IN (SELECT original_id FROM meeting_archive WHERE original_id IS NOT NULL)");
$delete_old_meetings_query = "DELETE FROM meetings WHERE $retention_condition";
@mysqli_query($conn, $delete_old_meetings_query);
$deleted_count = mysqli_affected_rows($conn);
if ($deleted_count > 0 && $user_id) {
    @logActivity($conn, $user_id, 'Auto-Archive Meetings', "Archived and deleted $deleted_count completed meetings.");
}

// Handle Create Meeting
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['meeting_topic'], $_POST['meeting_date']) && !isset($_POST['update_meeting_id']) && in_array($user_role, ['admin', 'scout_leader'])) {
    $meeting_topic = $_POST['meeting_topic'];
    $meeting_date  = date('Y-m-d H:i:s', strtotime($_POST['meeting_date']));
    $meeting_end_date = !empty($_POST['meeting_end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['meeting_end_date'])) : null;
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $allowed_role = $_POST['allowed_role'] ?? 'all';
    $meeting_link = "https://meet.jit.si/" . uniqid("scoutmeeting_");

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO meetings (title, scheduled_at, end_at, created_by, meeting_link, meeting_topic, is_private, allowed_role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sssisiss", $meeting_topic, $meeting_date, $meeting_end_date, $user_id, $meeting_link, $meeting_topic, $is_private, $allowed_role);

    if (mysqli_stmt_execute($stmt)) {
        $new_meeting_id = mysqli_insert_id($conn);
        // Handle specific scouts
        if ($allowed_role === 'specific' && !empty($_POST['specific_scouts'])) {
            $ins_u = mysqli_prepare($conn, "INSERT INTO meeting_allowed_users (meeting_id, user_id) VALUES (?, ?)");
            foreach ($_POST['specific_scouts'] as $uid) {
                mysqli_stmt_bind_param($ins_u, "ii", $new_meeting_id, $uid);
                mysqli_stmt_execute($ins_u);
            }
        }
        logActivity($conn, $user_id, 'Schedule Meeting', "Scheduled meeting: $meeting_topic");
        $_SESSION['success'] = "Meeting scheduled successfully!";
    } else {
        $_SESSION['error'] = "Error scheduling meeting!";
    }
    header("Location: view_meetings.php");
    exit();
}

// Handle Delete Meeting
if (isset($_GET['delete_meeting_id']) && in_array($user_role, ['admin', 'scout_leader'])) {
    $delete_id = $_GET['delete_meeting_id'];

    // Check ownership
    $check = mysqli_prepare($conn, "SELECT id FROM meetings WHERE id = ? AND created_by = ?");
    mysqli_stmt_bind_param($check, "ii", $delete_id, $user_id);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($res) > 0) {
        // Archive before deleting
        $archive_stmt = mysqli_prepare($conn, "INSERT INTO meeting_archive (original_id, title, meeting_link, scheduled_at, created_by, meeting_topic) SELECT id, title, meeting_link, scheduled_at, created_by, meeting_topic FROM meetings WHERE id = ?");
        mysqli_stmt_bind_param($archive_stmt, "i", $delete_id);
        mysqli_stmt_execute($archive_stmt);

        $del = mysqli_prepare($conn, "DELETE FROM meetings WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $delete_id);
        mysqli_stmt_execute($del);
        logActivity($conn, $user_id, 'Delete Meeting', "Deleted meeting ID: $delete_id");
        $_SESSION['success'] = "Meeting deleted successfully.";
    } else {
        $_SESSION['error'] = "You are not authorized to delete this meeting.";
    }
    header("Location: view_meetings.php");
    exit();
}

// Handle Update Meeting
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_meeting_id']) && in_array($user_role, ['admin', 'scout_leader'])) {
    $id = $_POST['update_meeting_id'];
    $topic = $_POST['meeting_topic'];
    $date = date('Y-m-d H:i:s', strtotime($_POST['meeting_date']));
    $end_date = !empty($_POST['meeting_end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['meeting_end_date'])) : null;
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $allowed_role = $_POST['allowed_role'] ?? 'all';
    
    // Check ownership
    $check = mysqli_prepare($conn, "SELECT id FROM meetings WHERE id = ? AND created_by = ?");
    mysqli_stmt_bind_param($check, "ii", $id, $user_id);
    mysqli_stmt_execute($check);
    if (mysqli_num_rows(mysqli_stmt_get_result($check)) > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE meetings SET title = ?, scheduled_at = ?, end_at = ?, meeting_topic = ?, is_private = ?, allowed_role = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssisi", $topic, $date, $end_date, $topic, $is_private, $allowed_role, $id);
        mysqli_stmt_execute($stmt);
        
        // Update specific scouts
        mysqli_query($conn, "DELETE FROM meeting_allowed_users WHERE meeting_id = $id");
        if ($allowed_role === 'specific' && !empty($_POST['specific_scouts'])) {
            $ins_u = mysqli_prepare($conn, "INSERT INTO meeting_allowed_users (meeting_id, user_id) VALUES (?, ?)");
            foreach ($_POST['specific_scouts'] as $uid) {
                mysqli_stmt_bind_param($ins_u, "ii", $id, $uid);
                mysqli_stmt_execute($ins_u);
            }
        }
        logActivity($conn, $user_id, 'Update Meeting', "Updated meeting ID: $id");
        $_SESSION['success'] = "Meeting updated successfully!";
    }
    header("Location: view_meetings.php");
    exit();
}

$filter_my = isset($_GET['filter']) && $_GET['filter'] === 'my';
$view_archive = isset($_GET['view']) && $_GET['view'] === 'archive';

$archived_meetings = [];
$calendar_events_json = '[]';
$ongoing_meetings = [];
$upcoming_meetings = [];
$past_meetings = [];

if ($view_archive) {
    $query = "SELECT ma.*, users.name AS leader_name 
              FROM meeting_archive ma 
              LEFT JOIN users ON ma.created_by = users.id ";
    if ($filter_my) {
        $query .= " WHERE ma.created_by = $user_id ";
    }
    $query .= " ORDER BY ma.archived_at DESC";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $archived_meetings[] = $row;
    }
} else {
    // Only fetch meetings that haven't ended yet (ongoing or upcoming)
    $query = "SELECT meetings.id, meetings.title, meetings.scheduled_at, meetings.end_at, meetings.meeting_link, meetings.created_by, meetings.is_private, meetings.allowed_role, users.name AS leader_name 
          FROM meetings 
          JOIN users ON meetings.created_by = users.id 
          WHERE COALESCE(meetings.end_at, meetings.scheduled_at + INTERVAL 2 HOUR) >= NOW() ";

    if ($filter_my) {
        $query .= " AND meetings.created_by = $user_id ";
    }

    $query .= " ORDER BY meetings.scheduled_at ASC";
    $result = mysqli_query($conn, $query);

    $ongoing_meetings = [];
    $upcoming_meetings = [];
    $calendar_events = [];
    $current_time = time();

    while ($row = mysqli_fetch_assoc($result)) {
        $meeting_time = strtotime($row['scheduled_at']);
        // Assume meeting is ongoing if current time is between start time and start time + 2 hours
        $end_time = !empty($row['end_at']) ? strtotime($row['end_at']) : $meeting_time + (2 * 60 * 60);

        // Calculate Duration
        $duration_seconds = $end_time - $meeting_time;
        $d_hours = floor($duration_seconds / 3600);
        $d_minutes = floor(($duration_seconds % 3600) / 60);
        $duration_display = [];
        if ($d_hours > 0) $duration_display[] = $d_hours . " hr";
        if ($d_minutes > 0) $duration_display[] = $d_minutes . " min";
        $row['duration_display'] = !empty($duration_display) ? implode(' ', $duration_display) : '0 min';

        if ($current_time >= $meeting_time && $current_time <= $end_time) {
            // Meeting is currently ongoing
            $ongoing_meetings[] = $row;
            $color = '#dc3545'; // Red for ongoing
        } elseif ($meeting_time > $current_time) {
            // Meeting hasn't started yet - truly upcoming
            $upcoming_meetings[] = $row;
            $color = '#0d6efd'; // Blue for upcoming
        }
        // If meeting has ended ($current_time > $end_time), don't add it to any array

        $calendar_events[] = [
            'title' => $row['title'],
            'start' => date('Y-m-d\TH:i:s', strtotime($row['scheduled_at'])),
            'end'   => !empty($row['end_at']) ? date('Y-m-d\TH:i:s', strtotime($row['end_at'])) : null,
            'color' => $color,
        ];
    }
    $calendar_events_json = json_encode($calendar_events);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Meetings</title>
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
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

/* Modal specific styling for visibility */
.modal { z-index: 99999 !important; }
.modal-backdrop { z-index: 99998 !important; }
.modal-content {
    background-color: #fff;
    color: #212529;
}
.btn-close {
    filter: brightness(0) invert(1);
    opacity: 1;
    cursor: pointer;
    pointer-events: auto !important;
}
    border-radius: 10px; /* Slightly rounded corners */
}
.modal-header {
    border-bottom: 1px solid #dee2e6; /* Light border for header */
}

#calendar a {
    text-decoration: none;
    color: inherit;
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
    
    /* Stack buttons and filters */
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 10px;
        align-items: stretch !important;
    }
    
    .btn-group {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-group .btn {
        width: 100%;
        border-radius: 10px !important;
        margin-bottom: 5px;
    }
    
    /* Card adjustments */
    .card {
        margin-bottom: 15px;
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
    
    /* Button adjustments */
    .btn {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    /* Table responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Mobile-friendly table layout */
    table {
        font-size: 12px;
    }
    
    table th,
    table td {
        padding: 8px 5px;
        white-space: normal;
        vertical-align: top;
    }
    
    /* Hide less critical columns on mobile */
    table th:nth-child(2),
    table td:nth-child(2),
    table th:nth-child(4),
    table td:nth-child(4) {
        display: none;
    }
    
    /* Make meeting title more prominent */
    .meeting-title-cell {
        font-weight: 600;
        font-size: 13px;
    }
    
    /* Stack action buttons vertically in ongoing meetings */
    .table td .d-flex.gap-2 {
        flex-direction: column;
        gap: 5px !important;
        width: 100%;
    }
    
    .table td .d-flex.gap-2 .btn {
        width: 100%;
        padding: 8px 10px;
        font-size: 12px;
        white-space: nowrap;
    }
    
    /* Ongoing meetings - make buttons more compact */
    .table td .d-flex.gap-2 .btn-sm {
        padding: 6px 8px;
        font-size: 11px;
    }
    
    /* Icons in buttons */
    .table td .d-flex.gap-2 .btn i {
        font-size: 11px;
    }
    
    /* Badge adjustments */
    .badge {
        font-size: 10px;
        padding: 3px 6px;
        display: inline-block;
        margin-top: 3px;
    }
    
    /* Icons in table cells */
    .bi-record-circle-fill,
    .bi-lock-fill {
        font-size: 12px;
    }
    
    /* Calendar mobile view */
    #calendar {
        font-size: 12px;
        padding: 10px !important;
    }
    
    .fc {
        background: white;
    }
    
    .fc-toolbar {
        flex-direction: column;
        gap: 10px;
        padding: 10px 5px;
    }
    
    .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        width: 100%;
    }

    .fc-toolbar-title {
        font-size: 1.2rem;
    }

    /* Calendar buttons */
    .fc .fc-button {
        padding: 6px 12px;
        font-size: 13px;
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

    .fc-event-time {
        display: none;
    }
    
    /* Modal adjustments */
    .modal-dialog {
        margin: 10px;
    }
    
    .modal-body {
        padding: 15px;
    }
    
    .modal-body .mb-3 {
        margin-bottom: 15px;
    }
    
    /* Badge and status adjustments */
    .badge {
        font-size: 11px;
        padding: 4px 8px;
    }
}

@media (max-width: 576px) {
    #calendar {
        padding: 8px !important;
    }

    .fc-toolbar-title {
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

    /* Stack toolbar buttons vertically */
    .fc .fc-toolbar-chunk:first-child .fc-button-group {
        flex-direction: column;
        width: 100%;
    }

    .fc .fc-toolbar-chunk:first-child .fc-button {
        width: 100%;
        margin-bottom: 5px;
    }
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
    
    .btn {
        font-size: 13px;
        padding: 6px 10px;
    }
    
    /* Further optimize table for very small screens */
    table {
        font-size: 11px;
    }
    
    table th,
    table td {
        padding: 6px 4px;
    }
    
    /* Show only essential columns */
    table th:nth-child(3),
    table td:nth-child(3) {
        font-size: 10px;
    }
    
    /* Make action buttons even more compact */
    .table td .d-flex.gap-2 .btn {
        padding: 6px 8px;
        font-size: 11px;
    }
    
    .table td .d-flex.gap-2 .btn-sm {
        padding: 5px 6px;
        font-size: 10px;
    }
    
    /* Smaller icons */
    .table td .d-flex.gap-2 .btn i {
        font-size: 10px;
    }
    
    .bi-record-circle-fill,
    .bi-lock-fill {
        font-size: 11px;
    }
}
</style>
</head>
<body>

<div class="wrapper">

<?php include('sidebar.php'); ?>

<div class="main">
    <!-- Top Navbar -->
    <?php include('navbar.php'); ?>

    <div class="glass">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="page-title"><i class="bi bi-calendar-event"></i> Meetings</h2>
            <?php if (in_array($user_role, ['admin', 'scout_leader'])): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="bi bi-plus-circle"></i> Schedule Meeting
                </button>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])) { ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php } ?>
        <?php if (isset($_SESSION['error'])) { ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php } ?>

        <!-- Filters & View Toggle -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <?php if (in_array($user_role, ['admin', 'scout_leader'])): ?>
            <div class="btn-group">
                <a href="view_meetings.php" class="btn btn-<?= !$filter_my ? 'primary' : 'outline-light' ?>">All Meetings</a>
                <a href="view_meetings.php?filter=my" class="btn btn-<?= $filter_my ? 'primary' : 'outline-light' ?>">My Meetings</a>
                <a href="view_meetings.php?view=archive" class="btn btn-<?= $view_archive ? 'warning' : 'outline-warning' ?>"><i class="bi bi-archive"></i> Archive</a>
            </div>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-light active" id="btnListView"><i class="bi bi-list"></i> List</button>
                <?php if (!$view_archive): ?>
                    <button type="button" class="btn btn-outline-light" id="btnCalendarView"><i class="bi bi-calendar3"></i> Calendar</button>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div id="listView">

    <?php if ($view_archive): ?>
        <div class="glass">
            <h4 class="text-warning mb-3"><i class="bi bi-archive-fill"></i> Archived Meetings</h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Meeting Topic</th>
                            <th>Initiator</th>
                            <th>Scheduled Date</th>
                            <th>Archived Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($archived_meetings)): ?>
                            <?php foreach ($archived_meetings as $meeting): ?>
                                <tr>
                                    <td><?= htmlspecialchars($meeting['title']); ?></td>
                                    <td><?= htmlspecialchars($meeting['leader_name'] ?? 'Unknown'); ?></td>
                                    <td><?= date("F j, Y, g:i A", strtotime($meeting['scheduled_at'])); ?></td>
                                    <td><?= date("F j, Y, g:i A", strtotime($meeting['archived_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">No archived meetings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>

    <!-- Ongoing Meetings Section -->
    <?php if (!empty($ongoing_meetings)): ?>
    <div class="glass">
        <h4 class="text-danger mb-3"><i class="bi bi-camera-video-fill"></i> Ongoing Meetings</h4>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Meeting Topic</th>
                        <th>Initiator</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ongoing_meetings as $meeting): ?>
                            <tr class="searchable-row">
                                <td> 
                                    <i class="bi bi-record-circle-fill text-danger me-2"></i><?= htmlspecialchars($meeting['title']); ?>
                                    <?php if ($meeting['is_private']): ?>
                                        <i class="bi bi-lock-fill text-warning ms-2" title="Lobby Enabled"></i>
                                    <?php endif; ?>
                                    <?php if (isset($meeting['allowed_role']) && $meeting['allowed_role'] !== 'all'): ?>
                                        <span class="badge bg-info ms-2" title="Restricted to <?= ucwords(str_replace('_', ' ', $meeting['allowed_role'])) ?>">
                                            <?php 
                                                if ($meeting['allowed_role'] == 'scout') {
                                                    echo 'Scouts Only';
                                                } elseif ($meeting['allowed_role'] == 'scout_leader') {
                                                    echo 'Leaders Only';
                                                } elseif ($meeting['allowed_role'] == 'specific') {
                                                    echo 'Specific Scouts';
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($meeting['leader_name']); ?></td>
                                <td><?= date("F j, Y, g:i A", strtotime($meeting['scheduled_at'])); ?></td>
                                <td><?= $meeting['duration_display']; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php 
                                            $is_creator = ($user_id == $meeting['created_by']);
                                            $allowed_role = $meeting['allowed_role'] ?? 'all';
                                            $can_join = false;

                                            // Check if user can join
                                            if ($user_role === 'admin' || $is_creator) {
                                                // Admins and creators can always join
                                                $can_join = true;
                                            } elseif ($allowed_role === 'all') {
                                                // Everyone can join
                                                $can_join = true;
                                            } elseif ($allowed_role === 'scout' && $user_role === 'scout') {
                                                // Scouts can join scout-only meetings
                                                $can_join = true;
                                            } elseif ($allowed_role === 'scout_leader' && $user_role === 'scout_leader') {
                                                // Leaders can join leader-only meetings
                                                $can_join = true;
                                            } elseif ($allowed_role === 'specific') {
                                                // Check if user is in the allowed list
                                                $check_allowed = mysqli_query($conn, "SELECT 1 FROM meeting_allowed_users WHERE meeting_id = {$meeting['id']} AND user_id = $user_id");
                                                if ($check_allowed && mysqli_num_rows($check_allowed) > 0) {
                                                    $can_join = true;
                                                }
                                            }

                                            $btn_text = $is_creator ? "Start Meeting" : "Join Meeting";
                                            $btn_class = $is_creator ? "btn-primary" : "btn-success";
                                        ?>
                                        <?php if ($can_join): ?>
                                        <a href="video_meeting.php?id=<?= $meeting['id']; ?>&topic=<?= urlencode($meeting['title']); ?>" class="btn <?= $btn_class ?> btn-sm"><?= $btn_text ?></a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled title="Restricted Meeting">Restricted</button>
                                        <?php endif; ?>
                                        <?php if ($is_creator): ?>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editMeetingModal<?= $meeting['id']; ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMeetingModal<?= $meeting['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Meetings Section -->
    <div class="glass">
        <h4 class="text-primary mb-3"><i class="bi bi-calendar-check"></i> Upcoming Meetings</h4>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Meeting Topic</th>
                        <th>Initiator</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($upcoming_meetings)): ?>
                        <?php foreach ($upcoming_meetings as $meeting): ?>
                            <tr class="searchable-row upcoming-meeting-row" data-time="<?= strtotime($meeting['scheduled_at']) * 1000 ?>">
                                <td class="meeting-title-cell">
                                    <?= htmlspecialchars($meeting['title']); ?>
                                    <?php if ($meeting['is_private']): ?>
                                        <i class="bi bi-lock-fill text-warning ms-2" title="Lobby Enabled"></i>
                                    <?php endif; ?>
                                    <?php if (isset($meeting['allowed_role']) && $meeting['allowed_role'] !== 'all'): ?>
                                        <span class="badge bg-info ms-2" title="Restricted to <?= ucwords(str_replace('_', ' ', $meeting['allowed_role'])) ?>">
                                            <?php 
                                                if ($meeting['allowed_role'] == 'scout') {
                                                    echo 'Scouts Only';
                                                } elseif ($meeting['allowed_role'] == 'scout_leader') {
                                                    echo 'Leaders Only';
                                                } elseif ($meeting['allowed_role'] == 'specific') {
                                                    echo 'Specific Scouts';
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($meeting['leader_name']); ?></td>
                                <td>
                                    <?= date("F j, Y, g:i A", strtotime($meeting['scheduled_at'])); ?>
                                    <div class="countdown-timer text-info fw-bold small mt-1"></div>
                                </td>
                                <td><?= $meeting['duration_display']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php 
                                            $is_creator = ($user_id == $meeting['created_by']);
                                            $allowed_role = $meeting['allowed_role'] ?? 'all';
                                            $can_join = false;

                                            // Check if user can join
                                            if ($user_role === 'admin' || $is_creator) {
                                                // Admins and creators can always join
                                                $can_join = true;
                                            } elseif ($allowed_role === 'all') {
                                                // Everyone can join
                                                $can_join = true;
                                            } elseif ($allowed_role === 'scout' && $user_role === 'scout') {
                                                // Scouts can join scout-only meetings
                                                $can_join = true;
                                            } elseif ($allowed_role === 'scout_leader' && $user_role === 'scout_leader') {
                                                // Leaders can join leader-only meetings
                                                $can_join = true;
                                            } elseif ($allowed_role === 'specific') {
                                                // Check if user is in the allowed list
                                                $check_allowed = mysqli_query($conn, "SELECT 1 FROM meeting_allowed_users WHERE meeting_id = {$meeting['id']} AND user_id = $user_id");
                                                if ($check_allowed && mysqli_num_rows($check_allowed) > 0) {
                                                    $can_join = true;
                                                }
                                            }

                                            $btn_text = $is_creator ? "Start" : "Join";
                                            $btn_class = $is_creator ? "btn-primary" : "btn-success";
                                            $disabled_attr = $is_creator ? "" : "disabled";
                                        ?>
                                        <?php if ($can_join): ?>
                                        <a href="video_meeting.php?id=<?= $meeting['id']; ?>&topic=<?= urlencode($meeting['title']); ?>" class="btn <?= $btn_class ?> btn-sm join-btn <?= $disabled_attr ?>"><?= $btn_text ?></a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled title="Restricted Meeting">Restricted</button>
                                        <?php endif; ?>
                                        <?php if (!$is_creator): ?>
                                            <span class="countdown small text-muted"></span>
                                        <?php endif; ?>
                                        <?php if ($is_creator): ?>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editMeetingModal<?= $meeting['id']; ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMeetingModal<?= $meeting['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No upcoming meetings scheduled.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
</div>

    <?php endif; ?>
    </div> <!-- End listView -->

    <!-- Calendar View -->
    <?php if (!$view_archive): ?>
    <div id="calendarView" style="display:none;" class="glass">
        <div id="calendar" class="bg-white text-dark p-3 rounded"></div>
    </div>
    <?php endif; ?>

<!-- Toast Notification for Meeting Start -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="meetingStartToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="toast-header bg-success text-white">
            <i class="bi bi-camera-video-fill me-2"></i>
            <strong class="me-auto">Meeting Started!</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body bg-dark text-white">
            <p class="mb-2"><strong id="toastMeetingTitle"></strong> has started!</p>
            <a id="toastJoinBtn" href="#" class="btn btn-success btn-sm w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Join Meeting Now
            </a>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
</div>

<!-- Delete Meeting Modals -->
<?php 
$all_meetings_for_delete = array_merge($ongoing_meetings, $upcoming_meetings, $past_meetings);
foreach ($all_meetings_for_delete as $meeting):
    if ($user_id == $meeting['created_by'] || $user_role === 'admin'): ?>
    <div class="modal fade" id="deleteMeetingModal<?= $meeting['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 16px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #c0392b, #e74c3c); border: none; padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Meeting
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="mb-1" style="font-size: 1rem; color: rgba(255,255,255,0.7);">You are about to delete</p>
                    <p class="fw-bold" style="font-size: 1.1rem; color: #e74c3c;"><?= htmlspecialchars($meeting['title'] ?? 'this meeting') ?></p>
                    <p class="mb-0" style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center" style="border-top: 1px solid rgba(255,255,255,0.1); gap: 1rem;">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <a href="view_meetings.php?delete_meeting_id=<?= $meeting['id'] ?>" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i> Yes, Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Edit Meeting Modals -->
<?php 
$all_meetings = array_merge($ongoing_meetings, $upcoming_meetings);
foreach ($all_meetings as $meeting): 
    if ($user_id == $meeting['created_by']):
        // Fetch currently allowed users for this meeting
        $current_allowed = [];
        if ($meeting['allowed_role'] === 'specific') {
            $ca_q = mysqli_query($conn, "SELECT user_id FROM meeting_allowed_users WHERE meeting_id = " . $meeting['id']);
            while($ca_row = mysqli_fetch_assoc($ca_q)) { $current_allowed[] = $ca_row['user_id']; }
        }
?>
<div class="modal fade" id="editMeetingModal<?= $meeting['id']; ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content text-dark">
      <div class="modal-header">
        <h5 class="modal-title">Edit Meeting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="view_meetings.php">
            <input type="hidden" name="update_meeting_id" value="<?= $meeting['id']; ?>">
            <div class="mb-3">
                <label class="form-label">Meeting Topic</label>
                <input type="text" name="meeting_topic" class="form-control" value="<?= htmlspecialchars($meeting['title']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Meeting Date & Time</label>
                <input type="datetime-local" name="meeting_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($meeting['scheduled_at'])); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">End Date & Time (Optional)</label>
                <input type="datetime-local" name="meeting_end_date" class="form-control" value="<?= !empty($meeting['end_at']) ? date('Y-m-d\TH:i', strtotime($meeting['end_at'])) : ''; ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Who can join?</label>
                <select name="allowed_role" class="form-select" onchange="toggleScoutList(this, 'scoutListEdit<?= $meeting['id']; ?>')">
                    <option value="all" <?= ($meeting['allowed_role'] ?? 'all') == 'all' ? 'selected' : '' ?>>Everyone</option>
                    <option value="scout" <?= ($meeting['allowed_role'] ?? '') == 'scout' ? 'selected' : '' ?>>Scouts Only</option>
                    <option value="scout_leader" <?= ($meeting['allowed_role'] ?? '') == 'scout_leader' ? 'selected' : '' ?>>Scout Leaders Only</option>
                    <option value="specific" <?= ($meeting['allowed_role'] ?? '') == 'specific' ? 'selected' : '' ?>>Specific Scouts (My Troop)</option>
                </select>
            </div>
            <div id="scoutListEdit<?= $meeting['id']; ?>" class="mb-3 border p-2 rounded" style="display: <?= ($meeting['allowed_role'] ?? '') == 'specific' ? 'block' : 'none' ?>; max-height: 150px; overflow-y: auto;">
                <label class="form-label small text-muted">Select Scouts:</label>
                <?php foreach ($my_scouts as $scout): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="specific_scouts[]" value="<?= $scout['id'] ?>" id="edit_scout_<?= $meeting['id'] ?>_<?= $scout['id'] ?>" <?= in_array($scout['id'], $current_allowed) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="edit_scout_<?= $meeting['id'] ?>_<?= $scout['id'] ?>"><?= htmlspecialchars($scout['name']) ?></label>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($my_scouts)) echo "<small class='text-danger'>No scouts found in your troop.</small>"; ?>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_private" value="1" id="is_private_edit<?= $meeting['id']; ?>" <?= !empty($meeting['is_private']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_private_edit<?= $meeting['id']; ?>">
                    Enable Lobby (Moderator must approve joins)
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Meeting</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; endforeach; ?>

<!-- Schedule Meeting Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content text-dark">
      <div class="modal-header">
        <h5 class="modal-title">Schedule a New Meeting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="view_meetings.php">
            <div class="mb-3">
                <label class="form-label">Meeting Topic</label>
                <input type="text" name="meeting_topic" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Meeting Date & Time</label>
                <input type="datetime-local" name="meeting_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">End Date & Time (Optional)</label>
                <input type="datetime-local" name="meeting_end_date" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Who can join?</label>
                <select name="allowed_role" class="form-select" onchange="toggleScoutList(this, 'scoutListCreate')">
                    <option value="all">Everyone</option>
                    <option value="scout">Scouts Only</option>
                    <option value="scout_leader">Scout Leaders Only</option>
                    <option value="specific">Specific Scouts (My Troop)</option>
                </select>
            </div>
            <div id="scoutListCreate" class="mb-3 border p-2 rounded" style="display: none; max-height: 150px; overflow-y: auto;">
                <label class="form-label small text-muted">Select Scouts:</label>
                <?php foreach ($my_scouts as $scout): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="specific_scouts[]" value="<?= $scout['id'] ?>" id="create_scout_<?= $scout['id'] ?>">
                        <label class="form-check-label" for="create_scout_<?= $scout['id'] ?>"><?= htmlspecialchars($scout['name']) ?></label>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($my_scouts)) echo "<small class='text-danger'>No scouts found in your troop.</small>"; ?>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_private" value="1" id="is_private_create">
                <label class="form-check-label" for="is_private_create">
                    Enable Lobby (Moderator must approve joins)
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Schedule Meeting</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleScoutList(select, targetId) {
    const target = document.getElementById(targetId);
    target.style.display = (select.value === 'specific') ? 'block' : 'none';
}

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

document.addEventListener('DOMContentLoaded', function() {
    // Prevent multiple reloads
    window.isReloading = false;
    
    <?php if (!$view_archive): ?>
    // Calendar Initialization
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?= $calendar_events_json ?>,
        height: 650
    });

    // Toggle View Logic
    document.getElementById('btnListView').addEventListener('click', function() {
        document.getElementById('listView').style.display = 'block';
        document.getElementById('calendarView').style.display = 'none';
        this.classList.add('active');
        document.getElementById('btnCalendarView').classList.remove('active');
    });

    document.getElementById('btnCalendarView').addEventListener('click', function() {
        document.getElementById('listView').style.display = 'none';
        document.getElementById('calendarView').style.display = 'block';
        this.classList.add('active');
        document.getElementById('btnListView').classList.remove('active');
        calendar.render();
    });
    <?php endif; ?>

    const upcomingMeetings = document.querySelectorAll('.upcoming-meeting-row');

    // Sync with server time to ensure accuracy
    const serverTime = <?= time() * 1000 ?>;
    const localTime = new Date().getTime();
    const timeOffset = serverTime - localTime;

    let countdownInterval = null;
    let activeCountdowns = new Set();

    if (upcomingMeetings.length > 0) {
        // Initialize all meetings as active countdowns
        upcomingMeetings.forEach(row => {
            activeCountdowns.add(row);
        });

        countdownInterval = setInterval(function() {
            let hasActiveCountdowns = false;

            upcomingMeetings.forEach(row => {
                // Skip if already started
                if (row.classList.contains('started')) {
                    activeCountdowns.delete(row);
                    return;
                }

                const meetingTime = parseInt(row.dataset.time, 10);
                const now = new Date().getTime() + timeOffset;
                const distance = meetingTime - now;

                const countdownTimerEl = row.querySelector('.countdown-timer');
                const joinBtn = row.querySelector('.join-btn');
                const titleCell = row.querySelector('.meeting-title-cell');

                // Run countdown logic for all users (show in date column)
                if (countdownTimerEl && distance > 0) {
                    hasActiveCountdowns = true;
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    let countdownText = '';
                    if (days > 0) countdownText += `${days}d `;
                    countdownText += `${hours}h ${minutes}m ${seconds}s`;
                    
                    countdownTimerEl.innerHTML = `⏱️ Starts in ${countdownText}`;
                } else if (distance <= 0) {
                    // Meeting Started - mark as started and remove from active countdowns
                    row.classList.add('started');
                    activeCountdowns.delete(row);
                    
                    if (joinBtn) {
                        joinBtn.classList.remove('disabled');
                    }
                    
                    if (titleCell && !titleCell.querySelector('.bi-record-circle-fill')) {
                        const icon = document.createElement('i');
                        icon.className = 'bi bi-record-circle-fill text-danger me-2';
                        titleCell.prepend(icon);
                    }
                    if (countdownTimerEl) {
                        countdownTimerEl.innerHTML = `<span class="text-success fw-bold">🟢 Meeting Started!</span>`;
                    }

                    // Show Toast Notification (only once)
                    const toastEl = document.getElementById('meetingStartToast');
                    if (toastEl) {
                        const meetingTitle = titleCell ? titleCell.innerText.trim() : "A meeting";
                        document.getElementById('toastMeetingTitle').innerText = meetingTitle;
                        const toastJoinBtn = document.getElementById('toastJoinBtn');
                        if (toastJoinBtn && joinBtn) {
                            toastJoinBtn.href = joinBtn.href;
                        }
                        const toast = new bootstrap.Toast(toastEl);
                        toast.show();
                    }

                    // Play Sound Notification
                    const audio = new Audio('sounds/notification.mp3');
                    audio.play().catch(error => console.log('Audio playback failed:', error));
                }
            });

            // Clear interval if no active countdowns remain
            if (!hasActiveCountdowns && activeCountdowns.size === 0) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }, 1000);
    }
});
</script>
</body>
</html>
