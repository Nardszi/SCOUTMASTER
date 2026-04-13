<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* HIDE SIDEBAR FOR ADMIN */
$hideSidebar = ($role === 'admin');

/* CREATE MEETING */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['meeting_topic'], $_POST['meeting_date'])) {
    $meeting_topic = $_POST['meeting_topic'];
    $meeting_date  = date('Y-m-d H:i:s', strtotime($_POST['meeting_date']));
    $meeting_link = "https://meet.jit.si/" . uniqid("scoutmeeting_");

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO meetings (title, scheduled_at, created_by, meeting_link, meeting_topic)
         VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssiss", $meeting_topic, $meeting_date, $user_id, $meeting_link, $meeting_topic);

    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $user_id, 'Schedule Meeting', "Scheduled meeting: $meeting_topic");
        $_SESSION['success'] = "Meeting scheduled successfully!";
    } else {
        $_SESSION['error'] = "Error scheduling meeting!";
    }
    header("Location: schedule_meeting.php");
    exit();
}

/* DELETE MEETING */
if (isset($_GET['delete_meeting_id'])) {
    $delete_id = $_GET['delete_meeting_id'];

    $check = mysqli_prepare($conn, "SELECT id FROM meetings WHERE id = ? AND created_by = ?");
    mysqli_stmt_bind_param($check, "ii", $delete_id, $user_id);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($res) > 0) {
        $del = mysqli_prepare($conn, "DELETE FROM meetings WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $delete_id);
        mysqli_stmt_execute($del);
        logActivity($conn, $user_id, 'Delete Meeting', "Deleted meeting ID: $delete_id");
        $_SESSION['success'] = "Meeting deleted successfully.";
    } else {
        $_SESSION['error'] = "You are not authorized to delete this meeting.";
    }
    header("Location: schedule_meeting.php");
    exit();
}

/* FETCH MEETINGS */
$filter_my = isset($_GET['filter']) && $_GET['filter'] === 'my';
$query_sql = "
    SELECT meetings.*, users.name AS creator
    FROM meetings
    JOIN users ON meetings.created_by = users.id
";
if ($filter_my) {
    $query_sql .= " WHERE meetings.created_by = $user_id ";
}
$query_sql .= " ORDER BY scheduled_at DESC";
$meetings_query = mysqli_query($conn, $query_sql);

$meetings_data = [];
while ($row = mysqli_fetch_assoc($meetings_query)) {
    $meetings_data[] = $row;
}

// Prepare Calendar Events
$calendar_events = [];
foreach ($meetings_data as $m) {
    $calendar_events[] = [
        'title' => $m['title'],
        'start' => date('Y-m-d\TH:i:s', strtotime($m['scheduled_at'])),
        'color' => ($m['created_by'] == $user_id) ? '#0d6efd' : '#198754'
    ];
}
$calendar_events_json = json_encode($calendar_events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule Meeting</title>
<?php include('favicon_header.php'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<style>
/* BASE */
body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; font-family:'Segoe UI',sans-serif; }
body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }

/* LAYOUT */
.wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }

/* MAIN BACKGROUND */
.main{
    flex:1;
    margin-left: 240px;
    padding:30px;
    position:relative;
    display:flex;
    flex-direction:column;
    transition: margin-left 0.3s ease-in-out;
}
body.sidebar-collapsed .main {
    margin-left: 80px;
}

.main > *{
    position:relative;
    z-index:1;
}

/* GLASS */
.glass{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(10px);
    border-radius:20px;
    padding:20px;
    margin-bottom:20px;
}

/* Page title */
.page-title {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 20px;
}

/* Table */
.table{
    color:white;
    vertical-align: middle;
}

.table thead{
    background:rgba(0,0,0,0.3);
}

.table-striped tbody tr:nth-of-type(odd){
    background-color:rgba(255,255,255,0.05);
}

.table td, .table th{
    border-color: rgba(255,255,255,0.1);
}

/* Buttons */
.btn{
    border-radius:20px;
}

#calendar a {
    text-decoration: none;
    color: inherit;
}

/* Mobile Calendar Styles */
@media (max-width: 768px) {
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
    
    /* Button adjustments */
    .btn {
        padding: 10px 15px;
        font-size: 14px;
        min-height: 44px;
    }
    
    /* Table responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    table {
        font-size: 12px;
    }
    
    table th,
    table td {
        padding: 8px 5px;
        white-space: nowrap;
    }
    
    /* Calendar mobile view */
    #calendar {
        font-size: 12px;
        padding: 10px !important;
    }
    
    .fc {
        background: white;
        padding: 10px;
        border-radius: 12px;
    }
    
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

    .fc-event-time {
        display: none;
    }
    
    /* Modal adjustments */
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
    
    .modal-footer {
        padding: 0.875rem 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        flex: 1;
        min-width: 120px;
    }
    
    /* Form controls - better touch targets */
    .form-control,
    .form-select {
        font-size: 16px;
        padding: 12px 15px;
        min-height: 44px;
    }
    
    /* Date inputs on mobile */
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"] {
        font-size: 16px !important;
        padding: 12px 15px;
        min-height: 44px;
    }
}

@media (max-width: 576px) {
    .page-title {
        font-size: 20px;
    }
    
    .col-12 {
        padding-left: 8px;
        padding-right: 8px;
    }
    
    .btn {
        font-size: 13px;
        padding: 8px 12px;
    }
    
    #calendar {
        padding: 8px !important;
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

    /* Stack toolbar buttons vertically */
    .fc .fc-toolbar-chunk:first-child .fc-button-group {
        flex-direction: column;
        width: 100%;
    }

    .fc .fc-toolbar-chunk:first-child .fc-button {
        width: 100%;
        margin-bottom: 5px;
    }
    
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
}
</style>
</head>

<body>

<div class="wrapper">

    <!-- Sidebar (Scout Leader Only) -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main">

        <!-- Top Navbar -->
        <?php include('navbar.php'); ?>

        <div class="glass">
        <h2 class="page-title"><i class="fas fa-calendar-plus"></i> Schedule Meeting</h2>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])) { ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php } ?>
        <?php if (isset($_SESSION['error'])) { ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php } ?>

        <!-- Schedule Button -->
        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleMeetingModal">
                <i class="fas fa-calendar-plus"></i> Schedule Meeting
            </button>
        </div>

        <!-- Schedule Meeting Modal -->
        <div class="modal fade" id="scheduleMeetingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content text-dark">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create a New Meeting</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Meeting Topic</label>
                                <input type="text" name="meeting_topic" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meeting Date & Time</label>
                                <input type="datetime-local" name="meeting_date" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-calendar-plus"></i> Schedule Meeting
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & View Toggle -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group">
                <a href="schedule_meeting.php" class="btn btn-<?= !$filter_my ? 'primary' : 'outline-light' ?>">All Meetings</a>
                <a href="schedule_meeting.php?filter=my" class="btn btn-<?= $filter_my ? 'primary' : 'outline-light' ?>">My Meetings</a>
            </div>

            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-light active" id="btnListView"><i class="fas fa-list"></i> List</button>
                <button type="button" class="btn btn-outline-light" id="btnCalendarView"><i class="fas fa-calendar-alt"></i> Calendar</button>
            </div>
        </div>

        <!-- Meetings Table -->
        <div id="listView">
            <h3 class="mt-4"><i class="fas fa-list"></i> All Scheduled Meetings</h3>
            <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Date & Time</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($meetings_data as $m) { ?>
                    <tr class="searchable-row" data-time="<?= strtotime($m['scheduled_at']) * 1000 ?>">
                        <td><?= htmlspecialchars($m['title']) ?></td>
                        <td>
                            <?= date("F j, Y g:i A", strtotime($m['scheduled_at'])) ?>
                            <div class="countdown small fw-bold mt-1"></div>
                        </td>
                        <td><?= htmlspecialchars($m['creator']) ?></td>
                        <td>
                            <?php if ($m['created_by'] == $user_id) { ?>
                                <a href="edit_meeting.php?meeting_id=<?= $m['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="schedule_meeting.php?delete_meeting_id=<?= $m['id'] ?>" class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this meeting?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Calendar View -->
        <div id="calendarView" style="display:none;">
            <h3 class="mt-4"><i class="fas fa-calendar-alt"></i> Calendar View</h3>
            <div id="calendar" class="bg-white text-dark p-3 rounded"></div>
        </div>
        </div>

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

document.addEventListener('DOMContentLoaded', function() {
    // Calendar Initialization
    var calendarEl = document.getElementById('calendar');
    
    // Detect if mobile
    const isMobile = window.innerWidth <= 768;
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: isMobile ? 'listWeek' : 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: isMobile ? 'dayGridMonth,listWeek' : 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: <?= $calendar_events_json ?>,
        height: 'auto',
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

    const rows = document.querySelectorAll('.searchable-row');

    // Sync with server time to ensure accuracy
    const serverTime = <?= time() * 1000 ?>;
    const localTime = new Date().getTime();
    const timeOffset = serverTime - localTime;

    setInterval(function() {
        const now = new Date().getTime() + timeOffset;

        rows.forEach(row => {
            const meetingTime = parseInt(row.getAttribute('data-time'));
            if (!meetingTime) return;

            const distance = meetingTime - now;
            const countdownEl = row.querySelector('.countdown');

            if (countdownEl) {
                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    countdownEl.innerHTML = `<span class="text-primary">Starts in: ${days}d ${hours}h ${minutes}m ${seconds}s</span>`;
                } else if (distance > -7200000) { // Within 2 hours
                    countdownEl.innerHTML = '<span class="text-success">Ongoing</span>';
                } else {
                    countdownEl.innerHTML = '<span class="text-muted">Finished</span>';
                }
            }
        });
    }, 1000);
});
</script>
</body>
</html>
