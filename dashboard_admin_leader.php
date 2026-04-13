<?php
session_start();
if (!isset($_SESSION['user_id']) || 
   ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'scout_leader')) {
    header("Location: login.php");
    exit();
}

include('config.php');

$role = $_SESSION['role'];
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

// Initialize all variables with default values
$user_count = 0;
$total_users = 0;
$pending_requests = 0;
$total_activities = 0;
$pending_users = 0;
$meetings_count = 0;
$events_count = 0;
$ranks_count = 0;
$badges_count = 0;
$new_registrations_count = 0;
$attendees_count = 0;
$pending_badge_approvals = 0;

// Simple, safe queries
$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM users");
if ($result) $user_count = mysqli_fetch_assoc($result)['total'];
$total_users = $user_count;

// Count scouts under this leader (for scout_leader role)
$troop_members_count = 0;
if ($role == 'scout_leader') {
    $leader_id = $_SESSION['user_id'];
    $result = @mysqli_query($conn, "SELECT COUNT(*) as total FROM users u JOIN troops t ON u.troop_id = t.id WHERE t.scout_leader_id = $leader_id AND u.role = 'scout' AND u.is_archived = 0");
    if ($result) $troop_members_count = mysqli_fetch_assoc($result)['total'];
}

$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM users WHERE approved=0");
if ($result) $pending_users = mysqli_fetch_assoc($result)['total'];

$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM scout_new_register");
if ($result) $new_registrations_count = mysqli_fetch_assoc($result)['total'];

$pending_requests = $pending_users + $new_registrations_count;

// Only count upcoming meetings (scheduled_at is in the future)
$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM meetings WHERE scheduled_at > NOW()");
if ($result) $meetings_count = mysqli_fetch_assoc($result)['total'];

// Only count upcoming/ongoing approved events (today or future)
$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM events WHERE status = 'approved' AND event_date >= CURDATE()");
if ($result) $events_count = mysqli_fetch_assoc($result)['total'];

$total_activities = $events_count + $meetings_count;

$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM ranks");
if ($result) $ranks_count = mysqli_fetch_assoc($result)['total'];

$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM merit_badges");
if ($result) $badges_count = mysqli_fetch_assoc($result)['total'];

$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM event_attendance");
if ($result) $attendees_count = mysqli_fetch_assoc($result)['total'];

// Pending event approvals
$pending_events_count = 0;
$result = @mysqli_query($conn,"SELECT COUNT(*) as total FROM events WHERE status = 'pending'");
if ($result) $pending_events_count = mysqli_fetch_assoc($result)['total'];

// Fetch pending events list
$pending_events = @mysqli_query($conn,"SELECT * FROM events WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
if (!$pending_events) $pending_events = mysqli_query($conn, "SELECT * FROM events WHERE 1=0");

// Get upcoming meetings only (future meetings, or today's meetings that haven't ended yet)
$current_datetime = date('Y-m-d H:i:s');
$meetings = @mysqli_query($conn,"SELECT * FROM meetings WHERE (
    scheduled_at > '$current_datetime' OR 
    (DATE(scheduled_at) = CURDATE() AND (
        (end_at IS NOT NULL AND end_at > '$current_datetime') OR 
        (end_at IS NULL AND DATE_ADD(scheduled_at, INTERVAL 2 HOUR) > '$current_datetime')
    ))
) ORDER BY scheduled_at ASC LIMIT 5");
if (!$meetings) $meetings = mysqli_query($conn, "SELECT * FROM meetings WHERE 1=0");

// Get upcoming events only (future events, starting from tomorrow)
$events = @mysqli_query($conn,"SELECT * FROM events WHERE DATE(event_date) > CURDATE() AND status = 'approved' ORDER BY event_date ASC LIMIT 5");
if (!$events) $events = mysqli_query($conn, "SELECT * FROM events WHERE 1=0");

$pending = @mysqli_query($conn,"SELECT * FROM users WHERE approved=0 LIMIT 5");
if (!$pending) $pending = mysqli_query($conn, "SELECT * FROM users WHERE 1=0");

// Fetch ongoing activities (today's events and meetings that haven't ended)
$ongoing_activities = [];
$today = date('Y-m-d');
$now = new DateTime();
$current_time = date('Y-m-d H:i:s');

// Get today's events
$ongoing_events_result = @mysqli_query($conn, "SELECT id, event_title as title, event_date as scheduled_at FROM events WHERE status = 'approved' AND event_date = '$today' ORDER BY event_date ASC");
if ($ongoing_events_result) {
    while($row = mysqli_fetch_assoc($ongoing_events_result)) {
        $row['type'] = 'event';
        $row['status'] = 'Ongoing';
        $row['time'] = 'All Day';
        $row['sort_time'] = '00:00:00';
        $ongoing_activities[] = $row;
    }
}

// Get today's meetings (only ongoing or upcoming, not past meetings)
// A meeting is considered ongoing/upcoming if:
// 1. It has an end_at time and that time hasn't passed yet, OR
// 2. It has no end_at time but was scheduled less than 2 hours ago
$ongoing_meetings_result = @mysqli_query($conn, "SELECT id, title, scheduled_at, end_at FROM meetings WHERE DATE(scheduled_at) = '$today' ORDER BY scheduled_at ASC");
if ($ongoing_meetings_result) {
    while($row = mysqli_fetch_assoc($ongoing_meetings_result)) {
        $meeting_time = new DateTime($row['scheduled_at']);
        $is_ongoing_or_upcoming = false;
        
        // Check if meeting hasn't ended yet
        if (!empty($row['end_at'])) {
            // Has explicit end time - check if it hasn't passed
            $end_time = new DateTime($row['end_at']);
            if ($end_time >= $now) {
                $is_ongoing_or_upcoming = true;
            }
        } else {
            // No end time - assume 2 hour duration
            $assumed_end = clone $meeting_time;
            $assumed_end->modify('+2 hours');
            if ($assumed_end >= $now) {
                $is_ongoing_or_upcoming = true;
            }
        }
        
        // Only add if meeting is still ongoing or upcoming
        if ($is_ongoing_or_upcoming) {
            $row['type'] = 'meeting';
            $row['status'] = ($now >= $meeting_time) ? 'Ongoing' : 'Upcoming';
            $row['time'] = $meeting_time->format('g:i A');
            $row['sort_time'] = $meeting_time->format('H:i:s');
            $ongoing_activities[] = $row;
        }
    }
}

// Sort activities by time
usort($ongoing_activities, function($a, $b) {
    return strcmp($a['sort_time'], $b['sort_time']);
});

$ongoing_meetings = [];
$todays_activity_details = $ongoing_activities;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Scout Master Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<?php include('favicon_header.php'); ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* BASE */
body{
    background: #0f172a;
    font-family: 'Inter', sans-serif;
    margin:0;
    padding:0;
    color: #fff;
}

/* MAIN CONTENT LAYOUT */
.main-content {
    margin-left: 240px;
    padding: 30px;
    min-height: 100vh;
    background: url('images/wall3.jpg') no-repeat center center;
    background-attachment: fixed;
    background-size: cover;
    position: relative;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease-in-out;
}

body.sidebar-collapsed .main-content {
    margin-left: 80px;
}

/* DARK OVERLAY */
.main-content::before{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.65);
    z-index:0;
}

.content-container { 
    position:relative; 
    z-index:1; 
}

/* HEADER */
.welcome-header {
    padding: 3rem 0;
    text-align: center;
    position: relative;
}
.welcome-header h1 {
    font-weight: 900;
    font-size: clamp(2.5rem, 5vw, 4rem);
    background: linear-gradient(135deg, #fff, #10b981, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: none;
    letter-spacing: -1px;
    margin-bottom: 1rem;
}
.welcome-header p {
    font-size: clamp(1rem, 2vw, 1.3rem);
    background: linear-gradient(135deg, #10b981, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    max-width: 600px;
    margin: 0 auto;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
}

/* STAT CARDS */
.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
    opacity: 0;
    transition: opacity 0.4s;
}
.stat-card:hover::before {
    opacity: 1;
}
.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
}
.stat-card-link {
    text-decoration: none;
    color: inherit;
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
    transition: all 0.3s;
    position: relative;
}
.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 12px 24px rgba(0,0,0,0.4);
}
.stat-icon.users { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.stat-icon.meetings { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.events { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.stat-icon.ranks { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-icon.pending { background: linear-gradient(135deg, #ef4444, #dc2626); }
.stat-icon.badges { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-icon.attendees { background: linear-gradient(135deg, #10b981, #059669); }
.stat-icon.new-regs { background: linear-gradient(135deg, #f97316, #ea580c); }

.stat-info .stat-number {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, #fff, #e0e7ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.stat-info .stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* GLASS CONTAINER */
.glass {
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    transition: all 0.3s;
}
.glass:hover {
    border-color: rgba(255, 255, 255, 0.25);
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
}
.glass-header {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 2px solid rgba(255,255,255,0.15);
    padding-bottom: 12px;
    color: #fff;
}
.glass-header:hover {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}
.glass-header i {
    color: #3b82f6;
    font-size: 1.5rem;
}

/* LIST ITEMS */
.item {
    padding: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    transition: all 0.3s;
    border-radius: 12px;
    margin-bottom: 8px;
}
.item:last-child { border-bottom: none; margin-bottom: 0; }
.item:hover { 
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.1));
    transform: translateX(8px);
    border-color: rgba(59, 130, 246, 0.3);
}
.item strong {
    font-size: 1.05rem;
    font-weight: 600;
    color: #fff;
}
.item small { 
    color: rgba(255,255,255,0.7);
    font-size: 0.85rem;
}

/* ANIMATIONS */
.animated-section {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s ease-out forwards;
}
@keyframes fadeInUp {
    to { opacity: 1; transform: translateY(0); }
}

/* Stat Cards Grid Layout */
.stat-cards-row {
    display: flex;
    flex-wrap: nowrap;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 16px;
    scrollbar-width: thin;
    scrollbar-color: rgba(59, 130, 246, 0.5) rgba(255, 255, 255, 0.1);
}
.stat-cards-row::-webkit-scrollbar {
    height: 8px;
}
.stat-cards-row::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}
.stat-cards-row::-webkit-scrollbar-thumb {
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 10px;
}
.stat-cards-row > div {
    flex: 1 1 auto;
    min-width: 160px;
}


.footer-wrapper{
    margin-top:auto;
    position: relative;
    z-index: 1;
}

/* Timeline Widget */
.timeline-widget {
    position: relative;
    padding-left: 32px;
    border-left: 3px solid rgba(59, 130, 246, 0.3);
    margin-left: 12px;
}
.timeline-entry { position: relative; margin-bottom: 24px; }
.timeline-entry:last-child { margin-bottom: 0; }
.timeline-dot {
    position: absolute; left: -34px; top: 8px;
    width: 16px; height: 16px; border-radius: 50%;
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    border: 3px solid #0f172a;
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.3);
    transition: all 0.3s;
}
.timeline-dot.ongoing { 
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.4), 0 0 20px rgba(245, 158, 11, 0.6);
    animation: pulse-dot 2s infinite;
}
.timeline-entry:hover .timeline-dot {
    transform: scale(1.3);
}
.timeline-time { 
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
    font-weight: 700;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.timeline-card {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.05));
    border-radius: 12px;
    padding: 16px;
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.3s;
}
.timeline-card:hover {
    transform: translateX(8px);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.1));
    border-color: rgba(59, 130, 246, 0.4);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
}
.timeline-card h6 {
    font-weight: 600;
    font-size: 1.05rem;
}
@keyframes pulse-dot {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7), 0 0 20px rgba(245, 158, 11, 0.6); }
    70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0), 0 0 20px rgba(245, 158, 11, 0.6); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0), 0 0 20px rgba(245, 158, 11, 0.6); }
}
</style>
</head>

<body>
<!-- Sidebar -->
<?php include('sidebar.php'); ?>



<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <?php include('navbar.php'); ?>
    <div class="content-container container-fluid">
        <div class="welcome-header animated-section">
            <h1>SCOUT MASTER DASHBOARD</h1>
            <p>WELCOME <?php echo strtoupper($role); ?>!</p>
        </div>
        
        <?php if (!empty($todays_activity_details)): ?>
        <div class="glass animated-section" style="animation-delay: 0.1s;">
            <h4 class="glass-header"><i class="fas fa-history"></i> Today's Schedule</h4>
            <div class="timeline-widget">
                <?php foreach ($todays_activity_details as $activity): ?>
                    <?php $dot_class = ($activity['status'] == 'Ongoing') ? 'ongoing' : ''; ?>
                    <div class="timeline-entry">
                        <div class="timeline-dot <?= $dot_class ?>"></div>
                        <div class="timeline-time"><?= $activity['time'] ?></div>
                        <div class="timeline-card">
                            <h6 class="mb-1 text-white"><?= htmlspecialchars($activity['title']) ?></h6>
                            <span class="badge bg-<?= ($activity['type'] == 'event') ? 'info text-dark' : 'success' ?>"><?= ucfirst($activity['type']) ?></span>
                            <small class="text-white-50 ms-2"><?= $activity['status'] ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SIMPLIFIED STATS FOR ADMIN - 4 Main Cards -->
        <?php if ($role == 'admin'): ?>
        <div class="row mb-4 animated-section" style="animation-delay: 0.2s;">
            <div class="col-md-3">
                <a href="manage_scouts.php" class="stat-card-link">
                    <div class="stat-card" style="min-height: 180px;">
                        <div class="stat-icon users" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $total_users; ?>" style="font-size: 3rem;">0</div>
                            <div class="stat-label" style="font-size: 0.9rem;">Total Users</div>
                            <small class="text-white-50 d-block mt-2" style="font-size: 0.75rem;">All registered scouts and leaders</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="view_new_registrations.php" class="stat-card-link">
                    <div class="stat-card" style="min-height: 180px;">
                        <div class="stat-icon pending" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $pending_requests; ?>" style="font-size: 3rem;">0</div>
                            <div class="stat-label" style="font-size: 0.9rem;">Pending Requests</div>
                            <small class="text-white-50 d-block mt-2" style="font-size: 0.75rem;">Items waiting for approval</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="events.php" class="stat-card-link">
                    <div class="stat-card" style="min-height: 180px;">
                        <div class="stat-icon events" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $total_activities; ?>" style="font-size: 3rem;">0</div>
                            <div class="stat-label" style="font-size: 0.9rem;">Activities</div>
                            <small class="text-white-50 d-block mt-2" style="font-size: 0.75rem;">Events and meetings</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="events.php" class="stat-card-link">
                    <div class="stat-card" style="min-height: 180px; position: relative;">
                        <div class="stat-icon" style="width: 70px; height: 70px; font-size: 2rem; background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $pending_events_count; ?>" style="font-size: 3rem;">0</div>
                            <div class="stat-label" style="font-size: 0.9rem;">Pending Events</div>
                            <small class="text-white-50 d-block mt-2" style="font-size: 0.75rem;">Events awaiting approval</small>
                        </div>

                    </div>
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Scout Leader Stats -->
        <div class="stat-cards-row mb-4 animated-section" style="animation-delay: 0.2s;">
            <div>
                <a href="manage_scoutsTL.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon users"><i class="fas fa-users"></i></div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $troop_members_count; ?>">0</div>
                            <div class="stat-label">Troop Members</div>
                        </div>
                    </div>
                </a>
            </div>
            <div>
                <a href="badge_approvals.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon badges"><i class="fas fa-user-check"></i></div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $pending_badge_approvals; ?>">0</div>
                            <div class="stat-label">Badge Approvals</div>
                        </div>
                    </div>
                </a>
            </div>
            <div>
                <a href="view_meetings.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon meetings"><i class="fas fa-video"></i></div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $meetings_count; ?>">0</div>
                            <div class="stat-label">Meetings</div>
                        </div>
                    </div>
                </a>
            </div>
            <div>
                <a href="events.php" class="stat-card-link">
                    <div class="stat-card">
                        <div class="stat-icon events"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-info">
                            <div class="stat-number" data-target="<?php echo $events_count; ?>">0</div>
                            <div class="stat-label">Events</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="row animated-section" style="animation-delay: 0.4s;">
            <!-- EVENTS -->
            <div class="col-md-6">
                <div class="glass h-100">
                    <a href="events.php" style="text-decoration: none; color: inherit;">
                        <div class="glass-header" style="cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-calendar-check"></i> Upcoming Events
                            <i class="fas fa-arrow-right ms-2" style="font-size: 0.9rem; opacity: 0.7;"></i>
                        </div>
                    </a>
                    <?php if(mysqli_num_rows($events)>0){
                        while($e=mysqli_fetch_assoc($events)){ ?>
                            <a href="events.php?id=<?php echo $e['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="item">
                                    <strong><?php echo htmlspecialchars($e['event_title']); ?></strong><br>
                                    <small><i class="fas fa-clock me-1"></i><?php echo $e['event_date']; ?></small>
                                </div>
                            </a>
                    <?php }}else{ echo "<p class='text-white'>No upcoming events.</p>"; } ?>
                </div>
            </div>

            <!-- MEETINGS -->
            <div class="col-md-6">
                <div class="glass h-100">
                    <a href="view_meetings.php" style="text-decoration: none; color: inherit;">
                        <div class="glass-header" style="cursor: pointer; transition: all 0.3s;">
                            <i class="fas fa-video"></i> Upcoming Meetings
                            <i class="fas fa-arrow-right ms-2" style="font-size: 0.9rem; opacity: 0.7;"></i>
                        </div>
                    </a>
                    
                    <?php if(mysqli_num_rows($meetings)>0){
                        while($m=mysqli_fetch_assoc($meetings)){ ?>
                            <a href="view_meetings.php?id=<?php echo $m['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="item">
                                    <strong><?php echo htmlspecialchars($m['title']); ?></strong><br>
                                    <small><i class="fas fa-clock me-1"></i><?php echo $m['scheduled_at']; ?></small>
                                </div>
                            </a>
                    <?php }}else{ echo "<p class='text-white'>No upcoming meetings.</p>"; } ?>
                </div>
            </div>
        </div>

        <?php if($role=='admin'){ ?>
        <div class="glass mt-4 animated-section" style="animation-delay: 0.6s;">
            <div class="glass-header"><i class="fas fa-user-plus"></i> Pending Users</div>
            <?php if(mysqli_num_rows($pending)>0){
                while($p=mysqli_fetch_assoc($pending)){ ?>
                    <div class="item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($p['email']); ?></small>
                        </div>
                        <a href="manage_scouts.php" class="btn btn-sm btn-warning">Review</a>
                    </div>
            <?php }}else{ echo "<p class='text-white'>No pending users.</p>"; } ?>
        </div>

        <div class="glass mt-4 animated-section" style="animation-delay: 0.7s;">
            <div class="glass-header d-flex align-items-center">
                <i class="fas fa-calendar-times" style="color:#f59e0b;"></i>
                <span class="ms-2">Pending Event Approvals</span>
                <?php if ($pending_events_count > 0): ?>
                    <span class="badge bg-danger ms-2"><?= $pending_events_count ?></span>
                <?php endif; ?>
                <a href="events.php" class="btn btn-warning btn-sm ms-auto">
                    <i class="fas fa-eye me-1"></i> View Pending
                    <?php if ($pending_events_count > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $pending_events_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php if ($pending_events_count === 0): ?>
                <p class="text-white-50 mb-0">No pending event approvals.</p>
            <?php else: ?>
                <p class="text-white-50 mb-0"><?= $pending_events_count ?> event(s) waiting for your approval.</p>
            <?php endif; ?>
        </div>

        <?php } ?>

    </div>

    <!-- FOOTER -->
    <div class="footer-wrapper">
        <?php include('footer.php'); ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality for dashboard items (if search element exists)
const globalSearch = document.getElementById("globalSearch");
if (globalSearch) {
    globalSearch.addEventListener("keyup", function() {
        let input = this.value.toLowerCase();
        // Search in items and stat cards
        let items = document.querySelectorAll(".item, .stat-card");
        items.forEach(item => {
            let text = item.innerText.toLowerCase();
            // For stat cards, we might want to hide the parent col if not matching, but simple display toggle works for items
            if(item.classList.contains('item')) {
                item.style.display = text.includes(input) ? "" : "none";
            }
        });
    });
}

// Navbar scroll effect
document.addEventListener('DOMContentLoaded', function() {
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
</script>
</body>
</html>
