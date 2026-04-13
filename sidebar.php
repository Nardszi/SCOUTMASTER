<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Online Users Logic
$online_count = 1;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $uid = $_SESSION['user_id'];
    // Update last activity for current user
    @mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = $uid");
    // Count users active in last 1 minute
    $online_res = @mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE last_activity > (NOW() - INTERVAL 1 MINUTE)");
    if ($online_res) $online_count = mysqli_fetch_assoc($online_res)['total'];
    // $online_res = @mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE last_activity > (NOW() - INTERVAL 5 MINUTE)");
    // if ($online_res) $online_count = mysqli_fetch_assoc($online_res)['total'];
}

// Check for activities today
if (!isset($todays_activity_details)) {
    $is_activity_today = false;
    $todays_activity_details = []; // New array for detailed info
    $today = date('Y-m-d');
    $now = new DateTime();

    if (isset($conn)) {
    // Fetch approved events for today
    $stmt_event = mysqli_prepare($conn, "SELECT id, event_title as title, 'event' as type FROM events WHERE event_date = ? AND status = 'approved'");
    if ($stmt_event) {
        mysqli_stmt_bind_param($stmt_event, "s", $today);
        mysqli_stmt_execute($stmt_event);
        $res_event = mysqli_stmt_get_result($stmt_event);
        while ($row = mysqli_fetch_assoc($res_event)) {
            $row['status'] = 'Ongoing'; // An event on a given day is considered ongoing for that day.
            $row['time'] = 'All Day';
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
            $todays_activity_details[] = $row;
        }
        mysqli_stmt_close($stmt_meeting);
    }
    
    if (!empty($todays_activity_details)) {
        $is_activity_today = true;
    }
    }
}

// Check for ongoing meetings that the current user can join
$ongoing_meetings_count = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_role = $_SESSION['role'] ?? '';
    
    // Get ongoing meetings (started and not ended yet)
    $ongoing_query = "SELECT m.id, m.title, m.scheduled_at, m.end_at, m.allowed_role, m.meeting_link, m.created_by
                      FROM meetings m 
                      WHERE m.scheduled_at <= NOW() 
                      AND (
                          (m.end_at IS NOT NULL AND m.end_at >= NOW()) 
                          OR (m.end_at IS NULL AND DATE_ADD(m.scheduled_at, INTERVAL 2 HOUR) >= NOW())
                      )
                      ORDER BY m.scheduled_at DESC";
    
    $ongoing_result = mysqli_query($conn, $ongoing_query);
    
    if ($ongoing_result) {
        while ($meeting = mysqli_fetch_assoc($ongoing_result)) {
            $can_join = false;
            
            // Check if user can join based on role
            if ($current_role === 'admin' || $current_role === 'scout_leader') {
                // Admins and leaders can join all meetings
                $can_join = true;
            } elseif ($current_role === 'scout') {
                // Check meeting permissions for scouts
                $allowed_role = $meeting['allowed_role'] ?? 'all';
                
                if ($allowed_role === 'all') {
                    $can_join = true;
                } elseif ($allowed_role === 'scout') {
                    $can_join = true;
                } elseif ($allowed_role === 'specific') {
                    // Check if scout is in allowed list
                    $check_allowed = mysqli_query($conn, "SELECT 1 FROM meeting_allowed_users WHERE meeting_id = {$meeting['id']} AND user_id = $current_user_id");
                    if ($check_allowed && mysqli_num_rows($check_allowed) > 0) {
                        $can_join = true;
                    }
                }
            }
            
            if ($can_join) {
                $ongoing_meetings_count++;
            }
        }
    }
}
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<style>
    .sidebar {
        width: 240px;
        background: linear-gradient(180deg, #000000 0%, #006e21 100%);
        padding: 20px;
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: fixed;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        top: 0;
        left: 0;
        overflow-y: auto;
        box-shadow: 8px 0 32px rgba(0,0,0,0.6);
        font-family: 'Inter', sans-serif;
        z-index: 1000;
        border-right: 1px solid rgba(40, 167, 69, 0.2);
        pointer-events: auto; /* Sidebar is clickable */
    }

    /* Desktop Toggle Button */
    .sidebar-toggle-btn {
        display: none;
    }
        top: 20px;
        left: 225px;
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, #28a745, #1e7e34);
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        font-size: 14px;
        transition: all 0.3s;
        z-index: 1050;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    .sidebar-toggle-btn:hover {
        background: linear-gradient(135deg, #34ce57, #28a745);
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.6);
    }

    body.sidebar-collapsed .sidebar-toggle-btn {
        left: 65px;
    }

    .logo-container {
        transition: transform 0.3s ease;
        margin-bottom: 0.5rem;
    }
    
    .logo-container:hover {
        transform: scale(1.05);
    }

    .sidebar-logo {
        width: 100%;
        max-width: 200px;
        border-radius: 0;
        height: auto;
        object-fit: contain;
        display: block;
        margin: 0 auto;
        filter: drop-shadow(0 4px 12px rgba(40, 167, 69, 0.3));
    }

    .sidebar a {
        display: flex;
        align-items: center;
        padding: 14px 16px;
        margin: 6px 0;
        overflow: hidden;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.03);
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-left: 3px solid transparent;
        position: relative;
        backdrop-filter: blur(10px);
    }

    .sidebar a::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
        opacity: 0;
        transition: opacity 0.3s;
        border-radius: 12px;
    }

    .sidebar a:hover::before {
        opacity: 1;
    }

    .sidebar a span {
        white-space: nowrap;
        transition: opacity 0.3s ease-in-out;
    }

    .sidebar a i {
        width: 28px;
        font-size: 17px;
        margin-right: 12px;
        text-align: center;
        transition: all 0.3s;
    }

    .sidebar a:hover {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.2), rgba(40, 167, 69, 0.15));
        color: #28a745;
        border-left: 3px solid #28a745;
        transform: translateX(6px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .sidebar a:hover i {
        transform: scale(1.15);
        color: #28a745;
    }
    
    .sidebar-heading {
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-top: 24px;
        margin-bottom: 12px;
        padding-left: 8px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar-heading i {
        color: #28a745;
        font-size: 13px;
    }

    .sidebar a.active {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.25), rgba(40, 167, 69, 0.2));
        color: #28a745;
        border-left: 3px solid #28a745;
        box-shadow: 0 4px 16px rgba(40, 167, 69, 0.4);
        font-weight: 600;
    }

    .sidebar a.active i {
        color: #28a745;
    }

    /* Custom Scrollbar for Sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.03);
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #28a745, #1e7e34);
        border-radius: 10px;
    }
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #34ce57, #28a745);
    }

    /* Collapsed State */
    body.sidebar-collapsed .sidebar {
        width: 80px;
    }

    body.sidebar-collapsed .sidebar .sidebar-heading,
    body.sidebar-collapsed .sidebar a span,
    body.sidebar-collapsed .sidebar .mt-auto .d-flex span,
    body.sidebar-collapsed .sidebar #liveDate,
    body.sidebar-collapsed .sidebar #liveClock,
    body.sidebar-collapsed .sidebar .mt-auto .text-center {
        display: none;
        opacity: 0;
    }

    body.sidebar-collapsed .sidebar a {
        justify-content: center;
        padding: 14px 10px;
    }

    body.sidebar-collapsed .sidebar a i {
        margin-right: 0;
        font-size: 22px;
    }

    body.sidebar-collapsed .sidebar .sidebar-heading i {
        display: block;
        text-align: center;
        width: 100%;
        margin: 24px 0 12px;
        font-size: 1.3rem;
        color: #28a745;
    }

    body.sidebar-collapsed .sidebar img {
        width: 50px;
    }

    body.sidebar-collapsed .sidebar .user-info {
        display: none;
        opacity: 0;
    }
    
    body.sidebar-collapsed .sidebar .mt-auto .d-flex {
        justify-content: center;
    }
    body.sidebar-collapsed .sidebar .mt-auto .fa-circle {
        margin-right: 0 !important;
    }
    
    /* Custom Tooltip Styling */
    .sidebar-tooltip .tooltip-inner {
        background: linear-gradient(135deg, #000000, #006e21);
        color: #fff;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #28a745;
        box-shadow: 0 4px 16px rgba(40, 167, 69, 0.4);
    }
    .sidebar-tooltip.bs-tooltip-end .tooltip-arrow::before {
        border-right-color: #28a745;
    }

    /* Sink effect for main content */
    .main, .main-content {
        transform-origin: center;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    body.sidebar-collapsed .main,
    body.sidebar-collapsed .main-content {
        transform: scale(0.98);
        border-radius: 20px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }

    .live-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border-radius: 50%;
        margin-left: auto;
        animation: blink-animation 1.5s infinite;
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.8);
    }
    @keyframes blink-animation {
        0% { opacity: 1; box-shadow: 0 0 12px rgba(239, 68, 68, 0.8); }
        50% { opacity: 0.4; box-shadow: 0 0 6px rgba(239, 68, 68, 0.4); }
        100% { opacity: 1; box-shadow: 0 0 12px rgba(239, 68, 68, 0.8); }
    }

    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.1); }
        100% { opacity: 1; transform: scale(1); }
    }

    /* Notification Badge */
    .notification-badge {
        display: inline-block;
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: auto;
        min-width: 20px;
        text-align: center;
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.6);
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {
        0%, 100% { 
            transform: scale(1);
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.6);
        }
        50% { 
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.9);
        }
    }

    body.sidebar-collapsed .notification-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 0.65rem;
        padding: 2px 5px;
        min-width: 18px;
    }

    .user-info {
        background: rgba(40, 167, 69, 0.1);
        border-radius: 8px;
        padding: 8px;
        margin-top: 8px;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    /* Mobile Menu Toggle Button */
    .mobile-menu-toggle {
        display: none !important; /* Hidden by default on desktop */
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1100;
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
        border: none;
        border-radius: 10px;
        width: 44px;
        height: 44px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        transition: all 0.3s;
        pointer-events: auto;
        align-items: center;
        justify-content: center;
    }

    .mobile-menu-toggle:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.6);
    }

    .mobile-menu-toggle i {
        font-size: 20px;
    }

    /* Mobile Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .sidebar-overlay.active {
        opacity: 1;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        /* Hide desktop toggle button on mobile */
        .sidebar-toggle-btn {
            display: none;
        }

        .mobile-menu-toggle {
            display: flex !important;
            z-index: 1150 !important;
            pointer-events: auto !important;
            position: fixed;
            top: 15px;
            left: 15px;
            width: 44px;
            height: 44px;
        }

        .sidebar {
            transform: translateX(-100%);
            width: 75vw; /* 75% of viewport width for better proportions */
            max-width: 320px; /* Maximum width cap */
            z-index: 1050;
            pointer-events: auto;
            padding: 15px;
        }

        .sidebar.mobile-open {
            transform: translateX(0);
            pointer-events: auto;
        }
        
        /* Optimize logo size */
        .sidebar-logo {
            max-width: 160px;
        }
        
        /* Optimize link spacing */
        .sidebar a {
            padding: 12px 14px;
            margin: 4px 0;
            font-size: 14px;
        }
        
        .sidebar a i {
            font-size: 16px;
            margin-right: 10px;
        }
        
        /* Optimize heading */
        .sidebar-heading {
            font-size: 11px;
            padding: 8px 14px;
            margin-top: 12px;
        }
        
        /* Optimize user info */
        .user-info {
            margin-top: -15px;
            padding: 3px;
        }
        
        .user-info small {
            font-size: 0.7rem;
        }

        .sidebar-overlay {
            display: block;
            pointer-events: none;
        }

        .sidebar-overlay.active {
            pointer-events: auto;
        }

        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
            width: 75vw;
            max-width: 320px;
        }

        body.sidebar-collapsed .sidebar.mobile-open {
            transform: translateX(0);
        }

        /* Reset collapsed styles on mobile */
        body.sidebar-collapsed .sidebar .sidebar-heading,
        body.sidebar-collapsed .sidebar a span,
        body.sidebar-collapsed .sidebar .mt-auto .d-flex span,
        body.sidebar-collapsed .sidebar #liveDate,
        body.sidebar-collapsed .sidebar #liveClock,
        body.sidebar-collapsed .sidebar .mt-auto .text-center {
            display: block;
            opacity: 1;
        }

        body.sidebar-collapsed .sidebar a {
            justify-content: flex-start;
            padding: 12px 14px;
        }

        body.sidebar-collapsed .sidebar a i {
            margin-right: 10px;
            font-size: 16px;
        }

        body.sidebar-collapsed .sidebar img {
            width: 100%;
            max-width: 160px;
        }

        body.sidebar-collapsed .sidebar .user-info {
            display: block;
            opacity: 1;
        }

        /* Main content adjustments */
        .main, .main-content {
            margin-left: 0 !important;
        }

        body.sidebar-collapsed .main,
        body.sidebar-collapsed .main-content {
            margin-left: 0 !important;
            transform: none;
            border-radius: 0;
            box-shadow: none;
        }
    }

    @media (max-width: 576px) {
        .sidebar {
            width: 80vw; /* 80% of viewport on small phones */
            max-width: 280px;
            padding: 12px;
        }
        
        .sidebar-logo {
            max-width: 140px;
        }
        
        .sidebar a {
            padding: 10px 12px;
            font-size: 13px;
        }
        
        .sidebar a i {
            font-size: 15px;
            margin-right: 8px;
        }
        
        .sidebar-heading {
            font-size: 10px;
            padding: 6px 12px;
        }

        .mobile-menu-toggle {
            top: 12px;
            left: 12px;
            width: 40px;
            height: 40px;
        }

        .mobile-menu-toggle i {
            font-size: 18px;
        }
    }

    /* Protection against Tailwind CSS and other frameworks affecting sidebar date/time */
    .sidebar #liveDate,
    .sidebar div#liveDate {
        font-size: 0.85rem !important;
        line-height: normal !important;
    }
    
    .sidebar #liveClock,
    .sidebar div#liveClock {
        font-size: 0.9rem !important;
        line-height: normal !important;
    }
    
    .sidebar .mt-auto span {
        font-size: 0.75rem !important;
    }
</style>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Toggle Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Desktop Toggle Button -->
<button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Toggle Sidebar">
    <i class="fas fa-chevron-left"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="logo-container">
        <img src="images/homelogo.png" alt="Boy Scout Logo" class="sidebar-logo">
        <div class="text-center user-info" style="margin-top: -20px; background: transparent; border: none; padding: 4px;">
            <small class="text-white-50 text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;"><?php echo htmlspecialchars(str_replace('_', ' ', $role)); ?></small>
        </div>
    </div>

    <div class="sidebar-heading"><i class="fas fa-compass me-2"></i> Main</div>
    <a href="dashboard.php" class="<?= ($current_page == 'dashboard.php' || $current_page == 'dashboard_scout.php' || $current_page == 'dashboard_admin_leader.php') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>

    <?php if ($is_activity_today): ?>
        <a href="<?= ($role == 'scout') ? 'view_events.php' : 'events.php' ?>" class="text-warning" data-bs-toggle="tooltip" data-bs-placement="right" title="Activity Today">
            <i class="fas fa-bullhorn"></i> <span>Activity Today</span>
            <span class="live-indicator"></span>
        </a>
    <?php endif; ?>

    <?php if ($role === 'admin') { ?>
        <div class="sidebar-heading"><i class="fas fa-user-shield me-2"></i> Administration</div>
        <a href="manage_scouts.php" class="<?= $current_page == 'manage_scouts.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Users"><i class="fas fa-users-cog"></i> <span>Schools</span></a>
        <a href="manage_merit_badges.php" class="<?= $current_page == 'manage_merit_badges.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Merit Badges"><i class="fas fa-medal"></i> <span>Merit Badges</span></a>
        <a href="manage_ranks.php" class="<?= $current_page == 'manage_ranks.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Ranks"><i class="fas fa-trophy"></i> <span>Ranks</span></a>
        <a href="events.php" class="<?= $current_page == 'events.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Events"><i class="fas fa-calendar-check"></i> <span>Events</span></a>
        <a href="view_new_registrations.php" class="<?= $current_page == 'view_new_registrations.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="New Registrations"><i class="fas fa-inbox"></i> <span>Registrations</span></a>
        <a href="view_meetings.php" class="<?= $current_page == 'view_meetings.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Meetings">
            <i class="fas fa-video"></i> <span>Meetings</span>
            <?php if ($ongoing_meetings_count > 0): ?>
                <span class="notification-badge"><?= $ongoing_meetings_count ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_reports.php" class="<?= $current_page == 'admin_reports.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Admin Reports"><i class="fas fa-file-alt"></i> <span>Admin Reports</span></a>
        <a href="activity_log.php" class="<?= $current_page == 'activity_log.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Activity Log"><i class="fas fa-history"></i> <span>Activity Log</span></a>
    <?php } ?>

    <?php if ($role === 'scout_leader') { ?>
        <div class="sidebar-heading"><i class="fas fa-flag me-2"></i> Troop Management</div>
        <a href="manage_scoutsTL.php" class="<?= $current_page == 'manage_scoutsTL.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Scouts"><i class="fas fa-campground"></i> <span>Scouts</span></a>
        <a href="badge_approvals.php" class="<?= $current_page == 'badge_approvals.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Badge Approvals"><i class="fas fa-user-check"></i> <span>Badge Approvals</span></a>
        <a href="rank_advancement.php" class="<?= $current_page == 'rank_advancement.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Rank Advancement"><i class="fas fa-award"></i> <span>Rank Advancement</span></a>
        <a href="view_event_attendees.php" class="<?= $current_page == 'view_event_attendees.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Event Attendees"><i class="fas fa-clipboard-list"></i> <span>Event Attendees</span></a>
        <a href="scout_leader_register.php" class="<?= $current_page == 'scout_leader_register.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Scout Registrations"><i class="fas fa-user-plus"></i> <span>Registrations</span></a>
        <a href="events.php" class="<?= $current_page == 'events.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Events"><i class="fas fa-calendar-plus"></i> <span>Events</span></a>
        <a href="view_meetings.php" class="<?= $current_page == 'view_meetings.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Manage Meetings">
            <i class="fas fa-video"></i> <span>Meetings</span>
            <?php if ($ongoing_meetings_count > 0): ?>
                <span class="notification-badge"><?= $ongoing_meetings_count ?></span>
            <?php endif; ?>
        </a>
    <?php } ?>
    
    <?php if ($role === 'scout') { ?>
        <div class="sidebar-heading"><i class="fas fa-hiking me-2"></i> Activities</div>
        <a href="view_meetings.php" class="<?= $current_page == 'view_meetings.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Meetings">
            <i class="fas fa-video"></i> <span>Meetings</span>
            <?php if ($ongoing_meetings_count > 0): ?>
                <span class="notification-badge"><?= $ongoing_meetings_count ?></span>
            <?php endif; ?>
        </a>
        <a href="merit_badges.php" class="<?= ($current_page == 'merit_badges.php' || $current_page == 'badge_progress.php') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Merit Badges"><i class="fas fa-medal"></i> <span>Merit Badges</span></a>
        <a href="view_events.php" class="<?= $current_page == 'view_events.php' ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Events"><i class="fas fa-calendar-day"></i> <span>Events</span></a>
 
    <?php } ?>

    <!-- Online & Time -->
    <div class="mt-auto px-3 mb-2 text-white-50" style="font-size: 0.8rem;">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-circle text-success me-2" style="font-size: 8px; animation: pulse 2s infinite;"></i>
            <span style="font-size: 0.75rem;"><?= $online_count ?> Online</span>
        </div>
        <div id="liveDate" class="fw-bold text-white mb-1" style="font-size: 0.85rem;"></div>
        <div id="liveClock" class="text-success" style="font-size: 0.9rem; font-weight: 600;"></div>
        <div class="mt-4 text-center" style="font-family: 'Orbitron', sans-serif; color: #00ffcc; letter-spacing: 2px; font-size: 0.8rem; font-weight: 700; text-shadow: 0 0 8px rgba(0, 255, 204, 0.5); border-top: 2px solid rgba(40, 167, 69, 0.3); padding-top: 12px;">
            DAWN GIREN NARD
        </div>
    </div>

    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const dateOptions = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', dateOptions);
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', timeOptions);
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const body = document.body;
            const toggleButton = document.getElementById('sidebar-toggle');
            const sidebarLinks = document.querySelectorAll('.sidebar a[data-bs-toggle="tooltip"]');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            // Initialize tooltips
            const tooltipInstances = [...sidebarLinks].map(tooltipTriggerEl => {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover',
                    customClass: 'sidebar-tooltip' // Custom class for styling
                });
            });

            // Function to enable/disable tooltips based on sidebar state
            const updateTooltips = () => {
                if (body.classList.contains('sidebar-collapsed') && window.innerWidth > 768) {
                    tooltipInstances.forEach(tooltip => tooltip.enable());
                } else {
                    tooltipInstances.forEach(tooltip => tooltip.disable());
                }
            };

            // Function to toggle sidebar
            const toggleSidebar = () => {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
                updateTooltips(); // Update tooltips after toggling
                
                // Change toggle button icon
                const toggleIcon = toggleButton.querySelector('i');
                if (body.classList.contains('sidebar-collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            };

            // Mobile menu toggle
            const toggleMobileMenu = () => {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
                
                // Change icon
                const icon = mobileMenuToggle.querySelector('i');
                if (sidebar.classList.contains('mobile-open')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            };

            // Close mobile menu
            const closeMobileMenu = () => {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                const icon = mobileMenuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            };

            // Check for saved state in localStorage on page load
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
                // Set initial icon state
                const toggleIcon = toggleButton.querySelector('i');
                if (toggleIcon) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                }
            }

            // Set initial tooltip state
            updateTooltips();

            // Event listener for the toggle button (desktop)
            if (toggleButton) {
                toggleButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
            }

            // Event listener for mobile menu toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }

            // Event listener for overlay click
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileMenu);
            }

            // Close mobile menu when clicking a link
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        closeMobileMenu();
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeMobileMenu();
                }
                updateTooltips();
            });
        });
    </script>
</div>
