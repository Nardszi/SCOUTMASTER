<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scout_leader') {
    header("Location: dashboard.php");
    exit();
}

$scout_leader_id = $_SESSION['user_id'];
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

/* ===================== ACTION HANDLERS ===================== */

// Create Troop
if (isset($_POST['create_troop'])) {
    $troop_name = trim($_POST['troop_name']);
    if (!empty($troop_name)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO troops (troop_name, scout_leader_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "si", $troop_name, $scout_leader_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Troop created successfully.";
        } else {
            $_SESSION['error'] = "Error creating troop.";
        }
    }
    header("Location: manage_scoutsTL.php");
    exit();
}

// Update Troop
if (isset($_POST['update_troop'])) {
    $troop_id = $_POST['troop_id'];
    $troop_name = trim($_POST['troop_name']);
    if (!empty($troop_name)) {
        $stmt = mysqli_prepare($conn, "UPDATE troops SET troop_name = ? WHERE id = ? AND scout_leader_id = ?");
        mysqli_stmt_bind_param($stmt, "sii", $troop_name, $troop_id, $scout_leader_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Troop updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating troop.";
        }
    }
    header("Location: manage_scoutsTL.php");
    exit();
}

// Delete Troop
if (isset($_POST['delete_troop'])) {
    $troop_id = $_POST['troop_id'];
    
    // Unassign users from this troop first
    $stmt = mysqli_prepare($conn, "UPDATE users SET troop_id = NULL WHERE troop_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $troop_id);
    mysqli_stmt_execute($stmt);

    // Delete troop
    $stmt = mysqli_prepare($conn, "DELETE FROM troops WHERE id = ? AND scout_leader_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $troop_id, $scout_leader_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Troop deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting troop.";
    }
    header("Location: manage_scoutsTL.php");
    exit();
}

// Update Scout (Assign Troop/Rank + Edit Info)
if (isset($_POST['update_scout'])) {
    $scout_id = $_POST['scout_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $school = $_POST['school'];
    $membership_card = $_POST['membership_card'];
    $troop_id = !empty($_POST['troop_id']) ? $_POST['troop_id'] : NULL;
    $rank_id = !empty($_POST['rank_id']) ? $_POST['rank_id'] : NULL;
    $scout_type = !empty($_POST['scout_type']) ? $_POST['scout_type'] : NULL;
    $position = !empty($_POST['position']) ? $_POST['position'] : NULL;

    $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, school = ?, troop_id = ?, rank_id = ?, membership_card = ?, scout_type = ?, status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sssissssi", $name, $email, $school, $troop_id, $rank_id, $membership_card, $scout_type, $position, $scout_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Scout updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating scout.";
    }
    header("Location: manage_scoutsTL.php");
    exit();
}

// Delete Scout
if (isset($_GET['delete_scout'])) {
    $scout_id = $_GET['delete_scout'];
    
    // Delete attendance
    $stmt = mysqli_prepare($conn, "DELETE FROM event_attendance WHERE scout_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $scout_id);
    mysqli_stmt_execute($stmt);

    // Delete user
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $scout_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Scout deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting scout.";
    }
    header("Location: manage_scoutsTL.php");
    exit();
}

/* ===================== FETCH DATA ===================== */

// Get Scout Leader's School for filtering
$leader_school = '';
$leader_query = mysqli_prepare($conn, "SELECT school FROM users WHERE id = ?");
mysqli_stmt_bind_param($leader_query, "i", $scout_leader_id);
mysqli_stmt_execute($leader_query);
$leader_result = mysqli_stmt_get_result($leader_query);
if ($leader_row = mysqli_fetch_assoc($leader_result)) {
    $leader_school = $leader_row['school'];
}

// Fetch Troops
$stmt = mysqli_prepare($conn, "SELECT id, troop_name FROM troops WHERE scout_leader_id = ?");
mysqli_stmt_bind_param($stmt, "i", $scout_leader_id);
mysqli_stmt_execute($stmt);
$troops_result = mysqli_stmt_get_result($stmt);
$troops = [];
while ($row = mysqli_fetch_assoc($troops_result)) {
    $troops[] = $row;
}

// Fetch Ranks (with scout_type)
$ranks_result = mysqli_query($conn, "SELECT id, rank_name, scout_type FROM ranks ORDER BY scout_type, rank_name");
$ranks = [];
while ($row = mysqli_fetch_assoc($ranks_result)) {
    $ranks[] = $row;
}

// Fetch All Troops for filter if view=all
$all_troops = $troops; // Default to my troops
if ($view_all) {
    $all_troops = [];
    $at_res = mysqli_query($conn, "SELECT id, troop_name FROM troops ORDER BY troop_name");
    while($row = mysqli_fetch_assoc($at_res)) {
        $all_troops[] = $row;
    }
}

// Filter Inputs
$filter_rank = $_GET['filter_rank'] ?? '';
$filter_troop = $_GET['filter_troop'] ?? '';
$filter_school = $_GET['filter_school'] ?? '';

// Fetch Scouts
// Check for is_archived column to filter out archived scouts
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'is_archived'");
$has_archive = mysqli_num_rows($check_col) > 0;
$archive_condition = $has_archive ? " AND (users.is_archived = 0 OR users.is_archived IS NULL)" : "";

$where_clauses = ["users.role = 'scout'"];
$params = [];
$types = "";

// Filter by school - TL can only see scouts from their school
if (!empty($leader_school)) {
    $where_clauses[] = "users.school = ?";
    $params[] = $leader_school;
    $types .= "s";
}

if (!$view_all) {
    $where_clauses[] = "troops.scout_leader_id = ?";
    $params[] = $scout_leader_id;
    $types .= "i";
}

if ($has_archive) {
    $where_clauses[] = "(users.is_archived = 0 OR users.is_archived IS NULL)";
}

if (!empty($filter_rank)) {
    $where_clauses[] = "users.rank_id = ?";
    $params[] = $filter_rank;
    $types .= "i";
}

if (!empty($filter_troop)) {
    $where_clauses[] = "users.troop_id = ?";
    $params[] = $filter_troop;
    $types .= "i";
}

if (!empty($filter_school)) {
    $where_clauses[] = "users.school LIKE ?";
    $params[] = "%" . $filter_school . "%";
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count Total Records
$count_query = "
    SELECT COUNT(*) as total
    FROM users
    LEFT JOIN troops ON users.troop_id = troops.id
    LEFT JOIN ranks ON users.rank_id = ranks.id
    WHERE $where_sql
";
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_rows / $limit);

$query = "
    SELECT users.id, users.name, users.email, users.school, users.membership_card, users.troop_id, users.rank_id, users.scout_type, users.status,
           users.profile_picture, troops.troop_name, ranks.rank_name,
           sp.birthday, sp.age, sp.gender, sp.paid_status
    FROM users
    LEFT JOIN troops ON users.troop_id = troops.id
    LEFT JOIN ranks ON users.rank_id = ranks.id
    LEFT JOIN scout_profiles sp ON users.id = sp.user_id
    WHERE $where_sql
    LIMIT ? OFFSET ?
";

// Add limit/offset to params for main query
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$scouts_result = mysqli_stmt_get_result($stmt);
$scouts = [];
while ($row = mysqli_fetch_assoc($scouts_result)) {
    $scouts[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Scouts (TL)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include('favicon_header.php'); ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* BASE */
body {
    margin:0;
    font-family: 'Inter', sans-serif;
    min-height:100vh;
    background:#0f172a;
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
    background:rgba(0,0,0,0.65);
    z-index:0;
}

.main > *{
    position:relative;
    z-index:1;
}

/* GLASS */
.glass{
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
    backdrop-filter:blur(20px);
    border-radius:24px;
    padding:28px;
    margin-bottom:24px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

/* HEADER */
.page-title{
    font-size: 2.5rem;
    font-weight:900;
    margin-bottom:24px;
    background: linear-gradient(135deg, #fff, #10b981, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* TABLE */
.table{
    color:white;
    border-radius: 12px;
    overflow: hidden;
}

.table thead{
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.3), rgba(5, 150, 105, 0.2));
    border-bottom: 2px solid rgba(16, 185, 129, 0.5);
}

.table thead th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 1px;
    padding: 16px 12px;
    border: none;
}

.table-striped tbody tr:nth-of-type(odd){
    background-color:rgba(255,255,255,0.03);
}

.table-striped tbody tr:hover {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
    transform: scale(1.01);
    transition: all 0.3s;
}

.table td, .table th{
    vertical-align:middle;
    border-color: rgba(255, 255, 255, 0.08);
    padding: 14px 12px;
}

/* BUTTONS */
.btn{
    border-radius:12px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.05);
}

.btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Bootstrap modal fix */
.modal { z-index: 1050 !important; }
.modal-backdrop { z-index: 1040 !important; }

/* Modal specific styling for visibility */
.modal { z-index: 99999 !important; }
.modal-backdrop { z-index: 99998 !important; }
.btn-close { filter: brightness(0) invert(1); opacity: 1; cursor: pointer; pointer-events: auto !important; }
.modal-content {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(6, 78, 59, 0.95));
    color: white;
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 16px;
    backdrop-filter: blur(20px);
}

.modal-header {
    border-bottom: 1px solid rgba(16, 185, 129, 0.3);
    padding: 20px 24px;
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-body {
    padding: 24px;
}

.section-header {
    border-bottom: 2px solid rgba(16, 185, 129, 0.3);
    padding-bottom: 12px;
    margin-bottom: 24px;
    font-weight: 700;
    font-size: 1.2rem;
    color: #10b981;
}

.troop-card {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 32px;
    border: 1px solid rgba(16, 185, 129, 0.2);
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.1);
}

.form-control, .form-select {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white !important;
    border-radius: 10px;
    padding: 10px 14px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

option {
    background-color: #0f172a;
    color: white;
}

.scout-row {
    cursor: pointer;
    transition: all 0.3s;
}

.scout-row .badge {
    transition: all 0.2s;
}

.scout-row .badge:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.filter-container {
    background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    padding: 20px;
}

@media print {
    .sidebar, .navbar, .btn, .troop-card, .mb-4, .page-title, .alert, .modal, .btn-group {
        display: none !important;
    }
    .main {
        margin-left: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .glass {
        background: white !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .table {
        color: black !important;
    }
    .table th, .table td {
        border: 1px solid #000 !important;
        color: black !important;
    }
    .table th:last-child, .table td:last-child {
        display: none !important;
    }
    body {
        background: white !important;
        color: black !important;
    }
    .text-success {
        color: black !important;
    }
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
            <div class="page-title"><i class="fas fa-user-shield me-2"></i>Manage Scouts (TL)</div>

            <?php if (isset($_SESSION['success'])) { ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php } ?>
            <?php if (isset($_SESSION['error'])) { ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php } ?>

            <!-- VIEW SWITCH -->
            <div class="mb-4">
                <div class="btn-group">
                    <a href="?view=my" class="btn btn-<?= !$view_all ? 'primary' : 'outline-light' ?>">
                        <i class="fas fa-users"></i> My Scouts
                    </a>
                    <a href="?view=all" class="btn btn-<?= $view_all ? 'primary' : 'outline-light' ?>">
                        <i class="fas fa-globe"></i> All Scouts
                    </a>
                </div>
            </div>

            <!-- TROOP MANAGEMENT -->
            <div class="troop-card">
                <h4 class="section-header"><i class="fas fa-campground me-2"></i> Manage Troops</h4>
                <form method="post" class="row g-2 mb-3 align-items-center">
                    <div class="col-auto">
                        <input type="text" name="troop_name" class="form-control " placeholder="New Troop Name" required>
                    </div>
                    <div class="col-auto">
                        <button name="create_troop" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Create
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Troop Name</th>
                                <th width="20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($troops as $t) { ?>
                            <tr>
                                <td>
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="troop_id" value="<?= $t['id'] ?>">
                                        <input type="text" name="troop_name" value="<?= htmlspecialchars($t['troop_name']) ?>" class="form-control form-control-sm" style="color: black !important; background-color: white !important;" required>
                                        <button name="update_troop" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this troop? The scout will be unassigned from this troop.');">
                                        <input type="hidden" name="troop_id" value="<?= $t['id'] ?>">
                                        <button name="delete_troop" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FILTERS -->
            <form method="GET" class="filter-container row g-3 mb-4 align-items-center">
                <?php if($view_all): ?><input type="hidden" name="view" value="all"><?php endif; ?>
                <div class="col-md-4">
                    <select name="filter_rank" class="form-select form-select-sm">
                        <option value="">Filter by Rank</option>
                        <?php foreach ($ranks as $r) { ?>
                            <option value="<?= $r['id'] ?>" <?= $filter_rank == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['rank_name']) ?>
                                <?php if (!empty($r['scout_type'])): ?>
                                    (<?= $r['scout_type'] === 'boy_scout' ? 'Boy Scout' : 'Outfit Scout' ?>)
                                <?php endif; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="filter_troop" class="form-select form-select-sm">
                        <option value="">Filter by Troop</option>
                        <?php foreach ($all_troops as $t) { ?>
                            <option value="<?= $t['id'] ?>" <?= $filter_troop == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['troop_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i> Apply Filters</button>
                </div>
            </form>

            <!-- SCOUT LIST -->
            <div class="d-flex justify-content-between align-items-center mt-4 border-bottom pb-2 mb-3" style="border-color: rgba(255,255,255,0.1) !important;">
                <h4 class="fw-bold text-success mb-0"><i class="fas fa-users me-2"></i> Scout List</h4>
                <button onclick="window.print()" class="btn btn-outline-light btn-sm"><i class="fas fa-print me-2"></i> Print List</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>School</th>
                            <th>Card #</th>
                            <th>Scout Type</th>
                            <th>Position</th>
                            <th>Troop</th>
                            <th>Rank</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scouts as $s) { ?>
                        <tr class="scout-row" style="cursor: pointer;" onclick="if(!event.target.closest('.btn')) { bootstrap.Modal.getOrCreateInstance(document.getElementById('editScoutModal<?= $s['id'] ?>')).show(); }">
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td style="font-size: 0.85rem;"><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= htmlspecialchars($s['school'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($s['membership_card'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($s['scout_type'])): ?>
                                    <span class="badge" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.25), rgba(255, 152, 0, 0.15)); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107; font-size: 0.7rem;">
                                        <?php echo $s['scout_type'] === 'boy_scout' ? 'Boy Scout' : 'Outfit Scout'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['status'])): ?>
                                    <span class="badge" style="background: linear-gradient(135deg, rgba(0, 188, 212, 0.25), rgba(0, 150, 136, 0.15)); border: 1px solid rgba(0, 188, 212, 0.4); color: #00bcd4; font-size: 0.7rem;">
                                        <?php echo $s['status'] === 'platoon_leader' ? 'Platoon Ldr' : 'Troop Ldr'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($s['troop_name'])): ?>
                                    <span class="badge bg-warning text-dark" style="cursor: pointer;" title="Click to assign troop">
                                        <i class="fas fa-plus-circle me-1"></i>Unassigned
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success" style="cursor: pointer;" title="Click to change troop">
                                        <?= htmlspecialchars($s['troop_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($s['rank_name'])): ?>
                                    <span class="badge bg-secondary" style="cursor: pointer;" title="Click to assign rank">
                                        <i class="fas fa-plus-circle me-1"></i>Unranked
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark" style="cursor: pointer;" title="Click to change rank">
                                        <?= htmlspecialchars($s['rank_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewScoutModal<?= $s['id'] ?>" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editScoutModal<?= $s['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteScoutModal<?= $s['id'] ?>" title="Delete Scout">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php 
                        function get_pagination_link($p) {
                            $params = $_GET;
                            $params['page'] = $p;
                            return '?' . http_build_query($params);
                        }
                    ?>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="<?= get_pagination_link($page - 1) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link <?= ($page == $i) ? 'bg-success border-success' : 'bg-dark text-white border-secondary' ?>" href="<?= get_pagination_link($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="<?= get_pagination_link($page + 1) ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>

        <?php include('footer.php'); ?>

    </div>

    <!-- EDIT MODALS (Outside Table) -->
    <?php foreach ($scouts as $s) { ?>

    <!-- VIEW PROFILE MODAL -->
    <div class="modal fade" id="viewScoutModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="background: rgba(20,20,20,0.97); border: 1px solid rgba(255,255,255,0.15); color: white; border-radius: 16px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #1a6b3c, #28a745); border: none; padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title" style="color:white;"><i class="fas fa-user-circle me-2"></i> Scout Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" style="background: rgba(20,20,20,0.97);">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-3 text-center">
                            <img src="<?= htmlspecialchars(!empty($s['profile_picture']) ? $s['profile_picture'] : 'uploads/default-avatar.png') ?>"
                                 alt="Profile"
                                 style="width:110px; height:110px; object-fit:cover; border-radius:50%; border: 3px solid rgba(40,167,69,0.6);">
                        </div>
                        <div class="col-md-9">
                            <h4 class="mb-1" style="color:white;"><?= htmlspecialchars($s['name']) ?></h4>
                            <p class="mb-1" style="color:rgba(255,255,255,0.6);"><?= htmlspecialchars($s['email']) ?></p>
                            <p class="mb-0" style="color:rgba(255,255,255,0.6);"><?= htmlspecialchars($s['school'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.15);">
                    <div class="row g-3">
                        <!-- Scout Info -->
                        <div class="col-md-6">
                            <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px; border: 1px solid rgba(255,255,255,0.1);">
                                <p style="color:rgba(255,255,255,0.5); font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Scout Info</p>
                                <?php
                                $scout_rows = [
                                    'Card #'     => htmlspecialchars($s['membership_card'] ?? 'N/A'),
                                    'Scout Type' => !empty($s['scout_type']) ? '<span class="badge" style="background:rgba(255,193,7,0.2);border:1px solid rgba(255,193,7,0.4);color:#ffc107;">'.($s['scout_type']==='boy_scout'?'Boy Scout':'Outfit Scout').'</span>' : 'N/A',
                                    'Position'   => !empty($s['status']) ? '<span class="badge" style="background:rgba(0,188,212,0.2);border:1px solid rgba(0,188,212,0.4);color:#00bcd4;">'.ucwords(str_replace('_',' ',$s['status'])).'</span>' : 'N/A',
                                    'Troop'      => !empty($s['troop_name']) ? '<span class="badge bg-success">'.htmlspecialchars($s['troop_name']).'</span>' : 'Unassigned',
                                    'Rank'       => !empty($s['rank_name']) ? '<span class="badge bg-info text-dark">'.htmlspecialchars($s['rank_name']).'</span>' : 'Unranked',
                                ];
                                foreach ($scout_rows as $lbl => $val): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.07);">
                                    <span style="color:rgba(255,255,255,0.5); font-size:0.85rem;"><?= $lbl ?></span>
                                    <span style="color:white; font-size:0.9rem;"><?= $val ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Personal Info -->
                        <div class="col-md-6">
                            <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px; border: 1px solid rgba(255,255,255,0.1);">
                                <p style="color:rgba(255,255,255,0.5); font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Personal Info</p>
                                <?php
                                $paid = $s['paid_status'] ?? 'Unpaid';
                                $personal_rows = [
                                    'Birthday' => !empty($s['birthday']) ? date('F j, Y', strtotime($s['birthday'])) : 'N/A',
                                    'Age'      => !empty($s['age']) ? htmlspecialchars($s['age']) : 'N/A',
                                    'Gender'   => !empty($s['gender']) ? htmlspecialchars(ucfirst($s['gender'])) : 'N/A',
                                    'Payment'  => '<span class="badge '.($paid==='Paid'?'bg-success':'bg-danger').'">'.$paid.'</span>',
                                ];
                                foreach ($personal_rows as $lbl => $val): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.07);">
                                    <span style="color:rgba(255,255,255,0.5); font-size:0.85rem;"><?= $lbl ?></span>
                                    <span style="color:white; font-size:0.9rem;"><?= $val ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1); background: rgba(20,20,20,0.97);">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE SCOUT MODAL -->
    <div class="modal fade" id="deleteScoutModal<?= $s['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(20,20,20,0.97); border: 1px solid rgba(255,255,255,0.15); color: white; border-radius: 16px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #c0392b, #e74c3c); border: none; padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title" style="color:white;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Scout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4" style="background: rgba(20,20,20,0.97);">
                    <p class="mb-1" style="color:rgba(255,255,255,0.7);">You are about to permanently delete</p>
                    <p class="fw-bold" style="font-size:1.1rem; color:#e74c3c;"><?= htmlspecialchars($s['name']) ?></p>
                    <p class="mb-0" style="font-size:0.85rem; color:rgba(255,255,255,0.5);">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center" style="border-top: 1px solid rgba(255,255,255,0.1); background: rgba(20,20,20,0.97); gap: 1rem;">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <a href="?delete_scout=<?= $s['id'] ?>" class="btn btn-danger px-4"><i class="fas fa-trash me-1"></i> Yes, Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal fade" id="editScoutModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Scout: <?= htmlspecialchars($s['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="scout_id" value="<?= $s['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($s['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">School</label>
                            <input type="text" name="school" class="form-control" value="<?= htmlspecialchars($s['school'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Membership Card</label>
                            <input type="text" name="membership_card" class="form-control" value="<?= htmlspecialchars($s['membership_card'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Scout Type</label>
                            <select name="scout_type" class="form-select">
                                <option value="">-- Select Scout Type --</option>
                                <option value="boy_scout" <?= ($s['scout_type'] ?? '') == 'boy_scout' ? 'selected' : '' ?>>Boy Scout</option>
                                <option value="outfit_scout" <?= ($s['scout_type'] ?? '') == 'outfit_scout' ? 'selected' : '' ?>>Outfit Scout</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select name="position" class="form-select">
                                <option value="">-- Select Position --</option>
                                <option value="platoon_leader" <?= ($s['status'] ?? '') == 'platoon_leader' ? 'selected' : '' ?>>Platoon Leader</option>
                                <option value="troop_leader" <?= ($s['status'] ?? '') == 'troop_leader' ? 'selected' : '' ?>>Troop Leader</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Troop</label>
                            <select name="troop_id" class="form-select">
                                <option value="">-- Select Troop --</option>
                                <?php foreach ($troops as $t) { ?>
                                    <option value="<?= $t['id'] ?>" <?= ($s['troop_id'] == $t['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['troop_name']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Rank</label>
                            <select name="rank_id" class="form-select">
                                <option value="">-- Select Rank --</option>
                                <?php 
                                $scout_type = $s['scout_type'] ?? '';
                                foreach ($ranks as $r) { 
                                    // Only show ranks that match the scout's type
                                    if (!empty($scout_type) && isset($r['scout_type']) && $r['scout_type'] !== $scout_type) {
                                        continue;
                                    }
                                    
                                    $type_label = '';
                                    if (isset($r['scout_type'])) {
                                        $type_label = $r['scout_type'] === 'boy_scout' ? ' (Boy Scout)' : ' (Outfit Scout)';
                                    }
                                ?>
                                    <option value="<?= $r['id'] ?>" <?= ($s['rank_id'] == $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['rank_name']) ?><?= $type_label ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <?php if (empty($scout_type)): ?>
                                <small class="text-warning d-block mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Please select a Scout Type first to see relevant ranks
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_scout" class="btn btn-primary">Update Scout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

</div>

<script>
// Search functionality for the navbar
document.getElementById("globalSearch").addEventListener("keyup", function() {
    let input = this.value.toLowerCase();
    let rows = document.querySelectorAll(".scout-row");
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
});

// Dynamic rank filtering based on scout type
document.addEventListener('DOMContentLoaded', function() {
    // Get all scout type selects in modals
    const scoutTypeSelects = document.querySelectorAll('select[name="scout_type"]');
    
    scoutTypeSelects.forEach(function(scoutTypeSelect) {
        scoutTypeSelect.addEventListener('change', function() {
            const modal = this.closest('.modal');
            const rankSelect = modal.querySelector('select[name="rank_id"]');
            const selectedScoutType = this.value;
            const warningMsg = modal.querySelector('.text-warning');
            
            // Show/hide warning message
            if (warningMsg) {
                warningMsg.style.display = selectedScoutType ? 'none' : 'block';
            }
            
            // Filter rank options
            const rankOptions = rankSelect.querySelectorAll('option');
            rankOptions.forEach(function(option) {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const optionText = option.textContent;
                if (selectedScoutType === 'boy_scout') {
                    option.style.display = optionText.includes('(Boy Scout)') ? 'block' : 'none';
                } else if (selectedScoutType === 'outfit_scout') {
                    option.style.display = optionText.includes('(Outfit Scout)') ? 'block' : 'none';
                } else {
                    option.style.display = 'block';
                }
            });
            
            // Reset rank selection if current selection is not visible
            const currentOption = rankSelect.options[rankSelect.selectedIndex];
            if (currentOption && currentOption.style.display === 'none') {
                rankSelect.value = '';
            }
        });
    });
});
</script>
