<?php
session_start();
include('config.php');

// 1. Security: Ensure user is a scout leader.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['scout_leader', 'admin'])) {
    header('Location: dashboard.php');
    exit();
}

$scout_leader_id = $_SESSION['user_id'];

// 2. Fetch events created by the Scout Leader
if ($_SESSION['role'] === 'admin') {
    $query_events = "SELECT id, event_title, event_date FROM events WHERE status = 'approved' ORDER BY event_date DESC";
    $stmt = mysqli_prepare($conn, $query_events);
} else {
    $query_events = "SELECT id, event_title, event_date FROM events WHERE scout_leader_id = ? AND status = 'approved' ORDER BY event_date DESC";
    $stmt = mysqli_prepare($conn, $query_events);
    mysqli_stmt_bind_param($stmt, "i", $scout_leader_id);
}
mysqli_stmt_execute($stmt);
$events_result = mysqli_stmt_get_result($stmt);

$events = [];
$event_ids = [];
while ($row = mysqli_fetch_assoc($events_result)) {
    $events[] = $row;
    $event_ids[] = $row['id'];
}

// 3. Fetch all attendees for these events in one go to prevent N+1 queries
$attendees_by_event = [];
if (!empty($event_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($event_ids), '?'));
    $types = str_repeat('i', count($event_ids));

    $query_attendees = "
        SELECT 
            ea.event_id,
            ea.scout_id, 
            ea.name AS attendee_name, 
            u.email,
            ea.address,
            ea.scout_rank,
            ea.emergency_contact,
            ea.reason,
            ea.requirements,
            ea.waiver_file
        FROM event_attendance ea
        JOIN users u ON ea.scout_id = u.id 
        WHERE ea.event_id IN ($ids_placeholder)
        ORDER BY ea.name ASC
    ";
    $stmt_attendees = mysqli_prepare($conn, $query_attendees);
    mysqli_stmt_bind_param($stmt_attendees, $types, ...$event_ids);
    mysqli_stmt_execute($stmt_attendees);
    $attendees_result = mysqli_stmt_get_result($stmt_attendees);

    // 4. Group attendees by event_id for easy lookup
    while ($attendee = mysqli_fetch_assoc($attendees_result)) {
        $attendees_by_event[$attendee['event_id']][] = $attendee;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Attendees</title>
<?php include('favicon_header.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<style>
/* BASE */
body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; font-family:'Segoe UI',sans-serif; }
body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: -1; }

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
    z-index: 1;
}
body.sidebar-collapsed .main {
    margin-left: 80px;
}

.main > *{
    position:relative;
    z-index:2;
}

/* GLASS */
.glass{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(12px);
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

/* Accordion styling */
.accordion-item {
    background-color: rgba(0,0,0,0.3) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    margin-bottom: 1rem;
    border-radius: 15px !important;
    overflow: visible !important;
    z-index: auto !important;
}
.accordion-header button {
    background-color: rgba(0,0,0,0.4) !important;
    color: white !important;
    font-weight: 600;
    box-shadow: none !important;
}
.accordion-header button:not(.collapsed) {
    background-color: rgba(40, 167, 69, 0.4) !important;
    color: #28a745 !important;
}
.accordion-header button:focus {
    box-shadow: none !important;
    border-color: rgba(40, 167, 69, 0.5) !important;
}
.accordion-header button::after {
    filter: invert(1) grayscale(100%) brightness(200%);
}
.accordion-body {
    background-color: rgba(0,0,0,0.3) !important;
    color: white !important;
    padding: 20px !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    overflow: visible !important;
    z-index: 1 !important;
}

.accordion-collapse.show {
    display: block !important;
    visibility: visible !important;
    overflow: visible !important;
}

.accordion-collapse.collapsing {
    display: block !important;
    overflow: visible !important;
}

/* Attendee list styling */
.attendee-list {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0;
    overflow: visible !important;
}
.attendee-item {
    background: rgba(255, 255, 255, 0.1) !important;
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: visible !important;
    position: relative;
    z-index: 1;
}
.attendee-main-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.btn-remove {
    font-size: 13px;
    padding: 4px 10px;
}

.attendee-details {
    margin-top: 15px;
    overflow: hidden !important;
    position: relative;
    z-index: 2;
    transition: all 0.35s ease;
}

.attendee-details.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.attendee-details.collapsing {
    overflow: hidden !important;
    transition: height 0.35s ease;
}

.attendee-details .bg-dark {
    background-color: rgba(0, 0, 0, 0.4) !important;
}

.attendee-details .row > div {
    margin-bottom: 10px;
    color: rgba(255, 255, 255, 0.95) !important;
}

.attendee-details strong {
    color: #28a745;
    margin-right: 8px;
}

/* Ensure buttons are visible and clickable */
.btn {
    position: relative;
    z-index: 10;
    white-space: nowrap;
    display: inline-block !important;
    visibility: visible !important;
}

.btn-success {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    color: white !important;
}

.btn-outline-light {
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: white !important;
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border-color: white !important;
    color: white !important;
}

h6 {
    display: block !important;
    visibility: visible !important;
}

/* Ensure collapse works properly */
.collapse:not(.show) {
    display: none !important;
}

.collapse.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.collapsing {
    position: relative;
    height: 0;
    overflow: hidden !important;
    transition: height 0.35s ease;
}

/* Print styles */
@media print {
    .sidebar, .navbar, .btn, .accordion-button::after {
        display: none !important;
    }
    .main {
        margin-left: 0 !important;
    }
    .accordion-collapse {
        display: block !important;
    }
    .attendee-details {
        display: block !important;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="page-title"><i class="bi bi-people-fill me-3"></i>Event Attendees</div>

            <?php if(empty($events)) { ?>
                <div class="alert alert-info bg-transparent text-white border-info">You have not created any approved events yet.</div>
            <?php } else { ?>
                <div class="accordion" id="eventsAccordion">
                    <?php foreach ($events as $index => $event) { ?>
                        <div class="accordion-item searchable-item">
                            <h2 class="accordion-header" id="heading<?= $event['id'] ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $event['id'] ?>" aria-expanded="false" aria-controls="collapse<?= $event['id'] ?>">
                                    <div class="d-flex w-100 align-items-center justify-content-between me-3">
                                        <span class="text-start me-2"><?= htmlspecialchars($event['event_title']) ?></span>
                                        <span class="badge bg-primary rounded-pill d-flex align-items-center flex-shrink-0"><i class="bi bi-calendar-event me-1"></i> <?= date("M j, Y", strtotime($event['event_date'])) ?></span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?= $event['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $event['id'] ?>" data-bs-parent="#eventsAccordion">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <a href="export_attendees.php?event_id=<?= $event['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
                                        </a>
                                    </div>

                                    <?php $current_attendees = $attendees_by_event[$event['id']] ?? []; ?>
                                    
                                    <h6 class="mb-3 text-white">Joined Scouts (<?= count($current_attendees) ?>)</h6>

                                    <?php if(!empty($current_attendees)) { ?>
                                        <ul class="attendee-list">
                                            <?php foreach ($current_attendees as $scout) { ?>
                                                <li class="attendee-item">
                                                    <div class="attendee-main-info">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($scout['attendee_name']); ?></strong> 
                                                            <small class="text-white-50">(<?php echo htmlspecialchars($scout['email']); ?>)</small>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-outline-light py-1 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?= $scout['scout_id'] ?>-<?= $event['id'] ?>" aria-expanded="false">
                                                                Details <i class="bi bi-chevron-down"></i>
                                                            </button>
                                                            <form method="post" action="remove_attendee.php" class="d-inline" onsubmit="return confirm('Remove this scout from the event?')">
                                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                                <input type="hidden" name="scout_id" value="<?php echo $scout['scout_id']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm btn-remove">
                                                                    <i class="bi bi-trash"></i> Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="collapse attendee-details mt-3" id="details-<?= $scout['scout_id'] ?>-<?= $event['id'] ?>">
                                                        <div class="p-3 bg-dark bg-opacity-25 rounded">
                                                            <div class="row g-2">
                                                                <div class="col-md-6"><strong>Rank:</strong> <?php echo htmlspecialchars($scout['scout_rank']); ?></div>
                                                                <div class="col-md-6"><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($scout['emergency_contact']); ?></div>
                                                                <div class="col-12"><strong>Address:</strong> <?php echo htmlspecialchars($scout['address']); ?></div>
                                                                <div class="col-12"><strong>Reason for Joining:</strong> <?php echo htmlspecialchars($scout['reason']); ?></div>
                                                                <?php if(!empty($scout['requirements'])): ?>
                                                                    <div class="col-12"><strong>Additional Requirements:</strong> <?php echo htmlspecialchars($scout['requirements']); ?></div>
                                                                <?php endif; ?>
                                                                <?php if(!empty($scout['waiver_file'])): ?>
                                                                    <div class="col-12">
                                                                        <strong>Parent Waiver:</strong> 
                                                                        <a href="<?php echo htmlspecialchars($scout['waiver_file']); ?>" target="_blank" class="text-success">
                                                                            <i class="bi bi-file-earmark-text"></i> View Attached File
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <p class="text-white-50">No scouts have joined this event yet.</p>
                                    <?php } ?>
                                </div>
                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
            <?php } ?>
        </div>
        <?php include('footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality for the navbar
document.getElementById("globalSearch").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let items = document.querySelectorAll(".searchable-item");
    items.forEach(item => {
        // For accordion, we check the button text and the body content
        let buttonText = item.querySelector('.accordion-button').innerText.toLowerCase();
        let bodyText = item.querySelector('.accordion-body').innerText.toLowerCase();
        
        if (buttonText.includes(input) || bodyText.includes(input)) {
            item.style.display = "";
        } else {
            item.style.display = "none";
        }
    });
});

// Auto-open accordion if highlight param is present
const urlParams = new URLSearchParams(window.location.search);
const highlightId = urlParams.get('highlight');
if (highlightId) {
    const collapseElement = document.getElementById('collapse' + highlightId);
    if (collapseElement) {
        new bootstrap.Collapse(collapseElement, { show: true });
        collapseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
</script>
</body>
</html>
