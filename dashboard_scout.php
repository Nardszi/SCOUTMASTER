<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: login.php');
    exit();
}
include('config.php');

// Fetch total counts - Simple queries for InfinityFree
$total_meetings = 0;
$total_events = 0;

$result = @mysqli_query($conn, "SELECT COUNT(*) as total FROM meetings");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_meetings = $row ? $row['total'] : 0;
}

$result = @mysqli_query($conn, "SELECT COUNT(*) as total FROM events WHERE status = 'approved'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_events = $row ? $row['total'] : 0;
}

// Fetch upcoming meetings and events - Only future dates (exclude today)
$current_datetime = date('Y-m-d H:i:s');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$meetings_result = @mysqli_query($conn, "SELECT id, title, scheduled_at FROM meetings WHERE DATE(scheduled_at) >= '$tomorrow' ORDER BY scheduled_at ASC LIMIT 5");
if (!$meetings_result) {
    $meetings_result = mysqli_query($conn, "SELECT id, title, scheduled_at FROM meetings WHERE 1=0");
}

$events_result = @mysqli_query($conn, "SELECT id, event_title as title, event_date as scheduled_at FROM events WHERE status = 'approved' AND DATE(event_date) >= '$tomorrow' ORDER BY event_date ASC LIMIT 5");
if (!$events_result) {
    $events_result = mysqli_query($conn, "SELECT id, event_title as title, event_date as scheduled_at FROM events WHERE 1=0");
}

// No ongoing meetings check - keep it simple for InfinityFree
$ongoing_meetings = [];
$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

// Fetch ongoing activities (today's events and meetings)
$ongoing_activities = [];
$today = date('Y-m-d');

// Get today's events
$ongoing_events_result = @mysqli_query($conn, "SELECT id, event_title as title, event_date as scheduled_at FROM events WHERE status = 'approved' AND event_date = '$today' ORDER BY event_date ASC");
if ($ongoing_events_result) {
    while($row = mysqli_fetch_assoc($ongoing_events_result)) {
        $row['type'] = 'event';
        $ongoing_activities[] = $row;
    }
}

// Get today's meetings (only ongoing or upcoming, not past meetings)
$current_time = date('Y-m-d H:i:s');
$ongoing_meetings_result = @mysqli_query($conn, "SELECT id, title, scheduled_at, end_at FROM meetings WHERE DATE(scheduled_at) = '$today' AND (
    (end_at IS NOT NULL AND end_at >= '$current_time') OR 
    (end_at IS NULL AND DATE_ADD(scheduled_at, INTERVAL 2 HOUR) >= '$current_time')
) ORDER BY scheduled_at ASC");
if ($ongoing_meetings_result) {
    while($row = mysqli_fetch_assoc($ongoing_meetings_result)) {
        $row['type'] = 'meeting';
        $ongoing_activities[] = $row;
    }
}

$activities = [];
if ($meetings_result) {
    while($row = mysqli_fetch_assoc($meetings_result)) {
        $row['type'] = 'meeting';
        $activities[] = $row;
    }
}
if ($events_result) {
    while($row = mysqli_fetch_assoc($events_result)) {
        $row['type'] = 'event';
        $activities[] = $row;
    }
}

// Limit to 5 total activities (no sorting to avoid issues)
$activities = array_slice($activities, 0, 5);

// Fetch troop and leader info
$user_id = $_SESSION['user_id'];
$troop_info_query = @mysqli_query($conn, "
    SELECT t.troop_name, l.name AS troop_leader_name, u.scout_type
    FROM users u
    LEFT JOIN troops t ON u.troop_id = t.id
    LEFT JOIN users l ON t.scout_leader_id = l.id
    WHERE u.id = $user_id
");
$troop_info = mysqli_fetch_assoc($troop_info_query);
$troop_name = $troop_info['troop_name'] ?? 'Not Assigned';
$troop_leader_name = $troop_info['troop_leader_name'] ?? 'N/A';
$scout_type = $troop_info['scout_type'] ?? '';

// Fetch in-progress badges (filtered by scout_type)
$in_progress_badges_query = "
    SELECT
        mb.id,
        mb.name,
        mb.icon_path,
        sbp.status,
        (SELECT COUNT(*) FROM badge_requirements WHERE merit_badge_id = mb.id) as total_reqs,
        (SELECT COUNT(*)
            FROM scout_requirement_progress srp
            WHERE srp.scout_badge_progress_id = sbp.id AND srp.date_approved IS NOT NULL
        ) as approved_reqs
    FROM
        merit_badges mb
    JOIN
        scout_badge_progress sbp ON mb.id = sbp.merit_badge_id
    WHERE
        sbp.scout_id = ? AND sbp.status = 'in_progress'
";

// Filter by scout_type if set
if (!empty($scout_type)) {
    $in_progress_badges_query .= " AND mb.scout_type = ?";
}

$in_progress_badges_query .= "
    ORDER BY
        sbp.date_started DESC
    LIMIT 4
";

$badges_stmt = mysqli_prepare($conn, $in_progress_badges_query);
if (!empty($scout_type)) {
    mysqli_stmt_bind_param($badges_stmt, "is", $user_id, $scout_type);
} else {
    mysqli_stmt_bind_param($badges_stmt, "i", $user_id);
}
mysqli_stmt_execute($badges_stmt);
$in_progress_badges_result = mysqli_stmt_get_result($badges_stmt);
$in_progress_badges = [];
while ($row = mysqli_fetch_assoc($in_progress_badges_result)) {
    $in_progress_badges[] = $row;
}

// Fetch completed badges (filtered by scout_type)
$completed_badges_query = "
    SELECT
        mb.id,
        mb.name,
        mb.icon_path,
        sbp.date_completed
    FROM
        merit_badges mb
    JOIN
        scout_badge_progress sbp ON mb.id = sbp.merit_badge_id
    WHERE
        sbp.scout_id = ? AND sbp.status = 'completed'
";

// Filter by scout_type if set
if (!empty($scout_type)) {
    $completed_badges_query .= " AND mb.scout_type = ?";
}

$completed_badges_query .= "
    ORDER BY
        sbp.date_completed DESC
    LIMIT 12
";

$completed_badges_stmt = mysqli_prepare($conn, $completed_badges_query);
if (!empty($scout_type)) {
    mysqli_stmt_bind_param($completed_badges_stmt, "is", $user_id, $scout_type);
} else {
    mysqli_stmt_bind_param($completed_badges_stmt, "i", $user_id);
}
mysqli_stmt_execute($completed_badges_stmt);
$completed_badges_result = mysqli_stmt_get_result($completed_badges_stmt);
$completed_badges = [];
while ($row = mysqli_fetch_assoc($completed_badges_result)) {
    $completed_badges[] = $row;
}

// Check for activities today
$todays_activity_details = [];
$today = date('Y-m-d');
$now = new DateTime();

// Fetch approved events for today
$stmt_event = mysqli_prepare($conn, "SELECT id, event_title as title, 'event' as type FROM events WHERE event_date = ? AND status = 'approved'");
if ($stmt_event) {
    mysqli_stmt_bind_param($stmt_event, "s", $today);
    mysqli_stmt_execute($stmt_event);
    $res_event = mysqli_stmt_get_result($stmt_event);
    while ($row = mysqli_fetch_assoc($res_event)) {
        $row['status'] = 'Ongoing';
        $row['time'] = 'All Day';
        $row['sort_time'] = '00:00:00';
        $todays_activity_details[] = $row;
    }
    mysqli_stmt_close($stmt_event);
}

// Fetch approved meetings for today
$stmt_meeting = mysqli_prepare($conn, "SELECT id, title, scheduled_at, 'meeting' as type FROM meetings WHERE DATE(scheduled_at) = ? AND (status = 'approved' OR is_approved = 1)");
if ($stmt_meeting) {
    mysqli_stmt_bind_param($stmt_meeting, "s", $today);
    mysqli_stmt_execute($stmt_meeting);
    $res_meeting = mysqli_stmt_get_result($stmt_meeting);
    while ($row = mysqli_fetch_assoc($res_meeting)) {
        $meeting_time = new DateTime($row['scheduled_at']);
        $row['status'] = ($now >= $meeting_time) ? 'Ongoing' : 'Upcoming';
        $row['time'] = $meeting_time->format('g:i A');
        $row['sort_time'] = $meeting_time->format('H:i:s');
        $todays_activity_details[] = $row;
    }
    mysqli_stmt_close($stmt_meeting);
}

// Sort activities by time
usort($todays_activity_details, function($a, $b) {
    return strcmp($a['sort_time'], $b['sort_time']);
});
$is_activity_today = !empty($todays_activity_details);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Dashboard</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body {
    background: #000;
    font-family: 'Poppins', sans-serif;
    margin:0; padding:0;
    color: #fff;
}

.main-content {
    margin-left: 240px;
    padding: 30px;
    min-height: 100vh; 
    background: url('images/picscout1.png') no-repeat center center;
    background-attachment: fixed;
    background-size: cover;
    position: relative;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease-in-out;
    z-index: 1;
    isolation: isolate; /* Create new stacking context */
}

body.sidebar-collapsed .main-content {
    margin-left: 0;
}

.main-content::before{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.65);
    z-index:-1; /* Behind everything in main-content */
    pointer-events: none;
}

.content-container { 
    position:relative; 
    z-index: 1; /* Above the ::before overlay */
    flex: 1;
    display: flex;
    flex-direction: column;
    pointer-events: auto; /* Ensure it's clickable */
}

/* Fix: Ensure all content is touchable/clickable */
.content-container * {
    pointer-events: auto;
}

/* Reset z-index for all content elements */
.glass, .animated-section, .alert, .stat-card, .activity-item, 
.badge-card, .timeline-widget, .welcome-header {
    position: relative;
    z-index: auto;
}

/* All interactive elements - ensure they're clickable/touchable */
button, a, input, select, textarea, .btn, .form-control, .form-select, 
.stat-card, .activity-item, .badge-card, .glass, .list-group-item,
.form-check-input, .form-check-label, .badge-progress-card-link,
.completed-badge-item, .view-detail-btn, .btn-view, .timeline-card {
    position: relative;
    pointer-events: auto !important;
    touch-action: manipulation;
    cursor: pointer;
    z-index: auto;
}

/* Ensure form elements are interactive */
input[type="checkbox"], input[type="radio"], input[type="file"] {
    pointer-events: auto !important;
    touch-action: manipulation;
}

/* Header */
.welcome-header {
    padding: 4rem 0;
    text-align: center;
}
.welcome-header h1 {
    font-weight: 700;
    font-size: clamp(2.5rem, 5vw, 4rem);
    color: #fff;
    text-shadow: 0 4px 10px rgba(0,0,0,0.4);
}
.welcome-header p {
    font-size: clamp(1.1rem, 2vw, 1.25rem);
    color: rgba(255,255,255,0.8);
    max-width: 600px;
    margin: 1rem auto 0;
}

/* Stat Cards */
.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #fff;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
}
.stat-icon.meetings {
    background-color: #198754;
}
.stat-icon.events {
    background-color: #0dcaf0;
    color: #000;
}
.stat-info .stat-number {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1.1;
}
.stat-info .stat-label {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 500;
}

/* Glass container for lists */
.glass {
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(10px);
    border-radius:20px;
    padding:25px;
    margin-bottom:20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    pointer-events: auto;
}

/* Ensure all glass children are clickable */
.glass * {
    pointer-events: auto;
}

.glass-header {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Activity List Item */
.activity-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 12px;
    background: rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}
.activity-item:hover {
    background: rgba(0,0,0,0.4);
    transform: translateX(5px);
}
.activity-info strong {
    display: block;
    font-weight: 500;
    font-size: 1.05rem;
}
.activity-info small {
    color: rgba(255,255,255,0.6);
    font-size: 0.85rem;
}
.btn-view {
    background: #28a745;
    color: white;
    border: none;
    border-radius: 20px;
    padding: 6px 15px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background 0.3s;
}
.btn-view:hover {
    background: #218838;
}

/* Modal Styles */
.modal-content { 
    border-radius:15px; 
    background: #2c3e50;
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    position: relative;
    z-index: 1060;
}

.modal { 
    z-index: 10000 !important;
    position: fixed !important;
}

.modal-backdrop {
    z-index: 9999 !important;
}

.modal-header { 
    background-color: rgba(0,0,0,0.2); 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.modal-body p {
    margin-bottom: 0.5rem;
}

.modal-body strong {
    color: #95a5a6;
}

.btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Ensure all modal interactive elements are clickable */
.modal *,
.modal button,
.modal .btn,
.modal .btn-close {
    pointer-events: auto !important;
    touch-action: manipulation;
}

/* Move modal to body to escape stacking context */
#detailModal {
    z-index: 10000 !important;
    position: fixed !important;
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
    }

    .modal-body p {
        font-size: 14px;
        margin-bottom: 0.75rem;
    }

    .modal-body img {
        max-width: 100%;
        height: auto;
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
    input[type="time"] {
        font-size: 16px !important; /* Prevent zoom on iOS */
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

    .modal-body p {
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

    /* Date inputs on small mobile */
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"] {
        font-size: 16px !important;
        padding: 10px 12px;
    }
}

/* Animations */
.animated-section {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s ease-out forwards;
    pointer-events: auto;
}

.animated-section * {
    pointer-events: auto;
}

@keyframes fadeInUp {
    to { opacity: 1; transform: translateY(0); }
}

/* Badge Progress Cards */
.badge-progress-card-link {
    text-decoration: none;
    color: inherit;
}
.badge-progress-card {
    background: rgba(0,0,0,0.25);
    padding: 15px;
    border-radius: 12px;
    transition: all 0.3s ease;
    height: 100%;
    border: 1px solid transparent;
}
.badge-progress-card:hover {
    transform: translateY(-5px);
    background: rgba(0,0,0,0.4);
    border-color: #28a745;
}
.badge-progress-icon {
    width: 50px;
    height: 50px;
    object-fit: contain;
    margin-right: 15px;
}
.badge-progress-info {
    width: calc(100% - 65px);
}
.badge-progress-info h6 {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.badge-progress-card .progress-bar {
    background-color: #28a745;
    transition: width 1s ease-in-out;
}

/* Completed Badges */
.completed-badge-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    text-decoration: none;
    color: white;
    transition: transform 0.2s ease-in-out;
    height: 100%;
    padding: 15px 10px;
    background: rgba(0,0,0,0.2);
    border-radius: 12px;
}
.completed-badge-item:hover {
    transform: scale(1.05);
    background: rgba(0,0,0,0.3);
}
.completed-badge-icon {
    width: 60px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 8px;
    filter: drop-shadow(0 0 5px rgba(40, 167, 69, 0.5));
}
.completed-badge-item .name {
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.2;
    margin-top: auto;
}
.completed-badge-item .date {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
}

/* Responsive */
@media(max-width:768px){
    .main-content{ margin-left:0; padding:15px; }
}

.footer-wrapper{
    margin-top:auto;
    position: relative;
    z-index: 1;
}

/* Timeline Widget */
.timeline-widget {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid rgba(255,255,255,0.1);
    margin-left: 10px;
}
.timeline-entry { position: relative; margin-bottom: 20px; }
.timeline-entry:last-child { margin-bottom: 0; }
.timeline-dot {
    position: absolute; left: -26px; top: 5px;
    width: 12px; height: 12px; border-radius: 50%;
    background: #0dcaf0; border: 2px solid #000;
}
.timeline-dot.ongoing { background: #ffc107; box-shadow: 0 0 8px #ffc107; animation: pulse-dot 2s infinite; }
.timeline-time { font-size: 0.8rem; color: rgba(255,255,255,0.5); font-weight: 600; margin-bottom: 4px; }
.timeline-card {
    background: rgba(255,255,255,0.05); border-radius: 8px; padding: 12px;
    border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s;
}
.timeline-card:hover { transform: translateX(5px); background: rgba(255,255,255,0.1); }
@keyframes pulse-dot { 0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } }

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
        padding: 15px;
        padding-top: 70px; /* Add space for fixed navbar */
        z-index: 1 !important;
        pointer-events: auto !important;
    }
    
    body.sidebar-collapsed .main-content {
        margin-left: 0 !important;
    }

    /* Ensure all content is touchable on mobile */
    .content-container {
        pointer-events: auto !important;
        touch-action: auto !important;
    }

    .content-container * {
        pointer-events: auto !important;
    }

    /* Make sure all interactive elements work on mobile */
    button, a, input, select, textarea, .btn, .form-control, .form-select,
    .stat-card, .activity-item, .badge-card, .glass, .list-group-item,
    .view-detail-btn, .badge-progress-card-link, .completed-badge-item,
    .alert, .progress, .timeline-card {
        pointer-events: auto !important;
        touch-action: manipulation !important;
    }

    /* Ensure cards are touchable */
    .stat-card, .activity-item, .badge-progress-card, .completed-badge-item {
        pointer-events: auto !important;
        touch-action: manipulation !important;
        cursor: pointer;
    }
    
    .welcome-header {
        padding: 1.5rem 0;
        margin-top: 0;
    }
    
    .welcome-header h1 {
        font-size: 2rem;
    }
    
    .welcome-header p {
        font-size: 1rem;
    }
    
    /* Stat cards stack on mobile */
    .stat-card {
        padding: 15px;
        gap: 15px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-info .stat-number {
        font-size: 1.8rem;
    }
    
    .stat-info .stat-label {
        font-size: 0.85rem;
    }
    
    /* Glass containers */
    .glass {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .glass-header {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }
    
    /* Activity items */
    .activity-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 12px;
    }
    
    .activity-info {
        width: 100%;
    }
    
    .btn-view {
        width: 100%;
        text-align: center;
    }
    
    /* Badge cards */
    .badge-card {
        margin-bottom: 15px;
    }
    
    .badge-card-body {
        padding: 12px;
    }
    
    /* Progress bars */
    .progress {
        height: 8px;
    }
    
    /* Timeline adjustments */
    .timeline-card {
        padding: 12px;
        margin-bottom: 10px;
    }
    
    /* Modal adjustments */
    .modal-dialog {
        margin: 1rem;
        max-width: calc(100% - 2rem);
        z-index: 1060;
    }
    
    .modal-dialog-centered {
        min-height: calc(100% - 2rem);
    }
    
    .modal-content {
        border-radius: 12px;
        background-color: #2c3e50;
        color: white;
        position: relative;
        z-index: 1060;
    }
    
    .modal-header {
        padding: 1rem;
        background-color: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .modal-title {
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .modal-body {
        padding: 1rem;
        font-size: 14px;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .modal-body p,
    .modal-body strong {
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.6;
        margin-bottom: 0.75rem;
    }
    
    .modal-body strong {
        color: #95a5a6;
        display: inline-block;
        min-width: 100px;
    }
    
    .modal-footer {
        padding: 0.875rem 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background-color: rgba(0, 0, 0, 0.1);
    }
    
    .modal-footer .btn {
        padding: 0.5rem 1.5rem;
        font-size: 0.95rem;
        min-height: 44px;
        border-radius: 8px;
        pointer-events: auto !important;
        touch-action: manipulation;
    }
    
    .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        pointer-events: auto !important;
        touch-action: manipulation;
    }
    
    /* Ensure modal and backdrop have correct z-index */
    #detailModal {
        z-index: 10000 !important;
        position: fixed !important;
    }
    
    #detailModal .modal-backdrop {
        z-index: 9999 !important;
    }
    
    /* Make all modal elements clickable */
    .modal-header *,
    .modal-body *,
    .modal-footer * {
        pointer-events: auto !important;
    }
    
    /* Ensure modal dialog is above everything */
    .modal-dialog {
        z-index: 10001 !important;
        position: relative;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 12px;
        padding-top: 65px; /* Adjust for smaller navbar on phones */
    }
    
    .welcome-header {
        padding: 1rem 0;
    }
    
    .welcome-header h1 {
        font-size: 1.75rem;
    }
    
    .welcome-header p {
        font-size: 0.9rem;
    }
    
    /* Modal adjustments for small phones */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-content {
        border-radius: 10px;
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
        max-height: 55vh;
    }
    
    .modal-body p {
        font-size: 13px;
        margin-bottom: 0.5rem;
    }
    
    .modal-body strong {
        font-size: 13px;
        min-width: 80px;
    }
    
    .modal-footer {
        padding: 0.75rem 0.875rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        padding: 0.625rem;
        font-size: 0.9rem;
    }
}
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
    
    .stat-info .stat-number {
        font-size: 1.5rem;
    }
    
    .glass {
        padding: 12px;
    }
    
    .glass-header {
        font-size: 1.1rem;
    }
    
    .activity-item {
        padding: 10px;
    }
    
    .activity-info strong {
        font-size: 0.95rem;
    }
    
    .activity-info small {
        font-size: 0.8rem;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<?php include('sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-container container-fluid">
        <!-- Top Navbar -->
        <?php include('navbar.php'); ?>

        <div class="welcome-header animated-section">
            <h1>Embark on Your Next Adventure</h1>
            <p class="lead text-white-50">"Laging Handa" - Always Ready. Explore upcoming activities and stay connected with your troop.</p>
        </div>

        <!-- Ongoing Activities Section -->
        <?php if (!empty($ongoing_activities)): ?>
        <div class="glass animated-section" style="animation-delay: 0.15s;">
            <h4 class="glass-header"><i class="bi bi-broadcast"></i> Ongoing Activities (Today)</h4>
            <?php foreach ($ongoing_activities as $activity): ?>
                <div class="activity-item" style="background: rgba(220, 53, 69, 0.2); border-left: 4px solid #dc3545;">
                    <div class="activity-info d-flex align-items-center">
                        <?php if ($activity['type'] === 'meeting'): ?>
                            <i class="bi bi-camera-video-fill fs-4 me-3 text-danger"></i>
                        <?php else: ?>
                            <i class="bi bi-flag-fill fs-4 me-3 text-danger"></i>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($activity['title']); ?></strong>
                            <small><i class="bi bi-clock-fill me-1 text-warning"></i><?= date("F j, Y, g:i a", strtotime($activity['scheduled_at'])); ?></small>
                            <span class="badge bg-danger ms-2">ONGOING</span>
                        </div>
                    </div>
                    <?php if ($activity['type'] === 'meeting'): ?>
                        <a href="video_meeting.php?id=<?= $activity['id'] ?>" class="btn btn-danger btn-sm fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Join Now
                        </a>
                    <?php else: ?>
                        <button class="btn-view view-detail-btn" data-id="<?= $activity['id'];?>" data-type="<?= $activity['type']; ?>" style="background: #dc3545;">View</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="glass animated-section" style="animation-delay: 0.2s;">
            <h4 class="glass-header"><i class="bi bi-calendar-week"></i> Upcoming Activities</h4>
            
            <?php if (!empty($ongoing_meetings)): ?>
                <div class="mb-3" style="background: #dc3545; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-record-circle-fill text-white me-2" style="font-size: 0.8rem;"></i>
                        <h6 class="mb-0 text-white fw-bold">MEETING IN PROGRESS</h6>
                    </div>
                    <p class="mb-3 text-white" style="font-size: 1.1rem; font-weight: 500;">
                        <?= htmlspecialchars($ongoing_meetings[0]['title']) ?>
                    </p>
                    <a href="video_meeting.php?id=<?= $ongoing_meetings[0]['id'] ?>" class="btn btn-light btn-sm fw-bold">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Join Now
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item searchable-item">
                        <div class="activity-info d-flex align-items-center">
                            <?php if ($activity['type'] === 'meeting'): ?>
                                <i class="bi bi-camera-video-fill fs-4 me-3 text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-flag-fill fs-4 me-3 text-primary"></i>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($activity['title']); ?></strong>
                                <small><i class="bi bi-clock me-1"></i><?= date("F j, Y, g:i a", strtotime($activity['scheduled_at'])); ?></small>
                            </div>
                        </div>
                        <button class="btn-view view-detail-btn" data-id="<?= $activity['id'];?>" data-type="<?= $activity['type']; ?>">View</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-white text-center">No upcoming activities right now. Stay tuned!</p>
            <?php endif; ?>
        </div>

        <!-- In-Progress Badges -->
        <div class="glass animated-section" style="animation-delay: 0.4s;">
            <h4 class="glass-header"><i class="bi bi-person-workspace"></i> Badges in Progress</h4>
            <div class="row g-3">
                <?php if (!empty($in_progress_badges)): ?>
                    <?php foreach ($in_progress_badges as $badge): ?>
                        <?php
                            $total_reqs = (int)($badge['total_reqs'] ?? 0);
                            $approved_reqs = (int)($badge['approved_reqs'] ?? 0);
                            $percentage = ($total_reqs > 0) ? round(($approved_reqs / $total_reqs) * 100) : 0;
                        ?>
                        <div class="col-lg-3 col-md-6">
                            <a href="badge_progress.php?id=<?= $badge['id'] ?>" class="badge-progress-card-link">
                                <div class="badge-progress-card">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png') ?>" alt="<?= htmlspecialchars($badge['name']) ?>" class="badge-progress-icon">
                                        <div class="badge-progress-info">
                                            <h6 class="mb-0"><?= htmlspecialchars($badge['name']) ?></h6>
                                            <small class="text-white-50"><?= $approved_reqs ?> of <?= $total_reqs ?> approved</small>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar" role="progressbar" style="width: 0%;" data-width="<?= $percentage ?>" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-white text-center">You have no badges in progress. <a href="merit_badges.php" class="text-success">Start a new one!</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Badges -->
        <div class="glass animated-section" style="animation-delay: 0.6s;">
            <h4 class="glass-header"><i class="bi bi-patch-check-fill"></i> Completed Badges</h4>
            <div class="row g-3">
                <?php if (mysqli_num_rows($completed_badges_result) > 0): ?>
                    <?php foreach ($completed_badges as $badge): ?>
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2 d-flex">
                            <a href="badge_progress.php?id=<?= $badge['id'] ?>" class="completed-badge-item w-100" title="Completed on <?= date('M j, Y', strtotime($badge['date_completed'])) ?>">
                                <img src="<?= htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png') ?>" alt="<?= htmlspecialchars($badge['name']) ?>" class="completed-badge-icon">
                                <span class="name"><?= htmlspecialchars($badge['name']) ?></span>
                                <span class="date"><?= date('M j, Y', strtotime($badge['date_completed'])) ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-white text-center">You haven't completed any badges yet. Keep up the great work!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badges/Stats -->
        <div class="row animated-section" style="animation-delay: 0.8s;">
            <div class="col-lg-6 mb-4">
                <div class="stat-card h-100">
                    <div class="stat-icon meetings">
                        <i class="bi bi-camera-video-fill"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-target="<?php echo $total_meetings; ?>">0</div>
                        <div class="stat-label">Total Meetings</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="stat-card h-100">
                    <div class="stat-icon events">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-target="<?php echo $total_events; ?>">0</div>
                        <div class="stat-label">Total Events</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Sections -->
        <div class="row">
            <div class="col-lg-4 animated-section" style="animation-delay: 1.0s;">
                <div class="glass h-100">
                    <h4 class="glass-header"><i class="bi bi-person-badge-fill"></i> My Troop</h4>
                    <p class="mb-1"><strong>Troop:</strong> <?= htmlspecialchars($troop_name) ?></p>
                    <p><strong>Scout Leader:</strong> <?= htmlspecialchars($troop_leader_name) ?></p>
                </div>
            </div>
            <div class="col-lg-8 animated-section" style="animation-delay: 1.2s;">
                <div class="glass h-100">
                    <h4 class="glass-header"><i class="bi bi-person-badge-fill"></i> Scout Information</h4>
                    <p>Stay active in your scouting journey! Check your merit badges, upcoming events, and meetings regularly to make the most of your experience.</p>
                </div>
            </div>
        </div>
    </div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modal-body-content">...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Move modal to body to escape stacking context issues
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    
    const navbar = document.querySelector('.top-navbar');
    if (navbar) {
        navbar.classList.add('landing-nav');

        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
});

document.querySelectorAll('.view-detail-btn').forEach(button => {
    button.addEventListener('click', function(){
        const id = this.dataset.id;
        const type = this.dataset.type;
        const modalBody = document.getElementById('modal-body-content');
        const modalTitle = document.getElementById('detailModalLabel');

        fetch(`${type}_detail_modal.php?id=${id}`)
            .then(res => res.text())
            .then(data => {
                // The detail modal PHP might return an error message, style it
                if (data.toLowerCase().includes('error') || data.toLowerCase().includes('not found')) {
                     modalBody.innerHTML = `<div class="alert alert-danger">${data}</div>`;
                } else {
                     modalBody.innerHTML = data;
                }
                modalTitle.textContent = type === 'meeting' ? 'Meeting Details' : 'Event Details';
                const myModal = new bootstrap.Modal(document.getElementById('detailModal'));
                myModal.show();
            });
    });
});

// Counter Animation for Stats
const counters = document.querySelectorAll('.stat-number[data-target]');
const speed = 200; // The lower the slower

counters.forEach(counter => {
    const updateCount = () => {
        const target = +counter.getAttribute('data-target');
        const count = +counter.innerText;
        
        // Calculate increment step
        const inc = Math.max(1, target / speed);

        if (count < target) {
            counter.innerText = Math.ceil(count + inc);
            setTimeout(updateCount, 15);
        } else {
            counter.innerText = target;
        }
    };
    updateCount();
});

// Animate progress bars when they scroll into view
const progressBars = document.querySelectorAll('.badge-progress-card .progress-bar');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const bar = entry.target;
            const targetWidth = bar.getAttribute('data-width');
            bar.style.width = targetWidth + '%';
            observer.unobserve(bar); // Stop observing once animated
        }
    });
}, { threshold: 0.1 });

progressBars.forEach(bar => {
    observer.observe(bar);
});
</script>
    <div class="footer-wrapper">
        <?php include('footer.php'); ?>
    </div>
