<?php
session_start();
include('config.php');

// Allow only Admin and Troop Leader
if (!isset($_SESSION['user_id']) || 
   ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'troop_leader')) {
    header('Location: dashboard.php');
    exit();
}

$view = $_GET['view'] ?? 'active';

// Handle Archive/Restore/Permanent Delete actions
// Handle Bulk Actions by School
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_school']) && $_SESSION['role'] == 'admin') {
    $school = $_POST['bulk_school'];
    if ($school && $school !== 'all') {
        $school = mysqli_real_escape_string($conn, $school);
        $user_ids = [];
        $result = mysqli_query($conn, "SELECT id, role FROM users WHERE school = '$school'");
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['role'] !== 'admin') $user_ids[] = $row['id'];
        }
        if (isset($_POST['bulk_archive'])) {
            foreach ($user_ids as $uid) {
                mysqli_query($conn, "UPDATE users SET is_archived = 1, archived_at = NOW(), archived_by = '{$_SESSION['user_id']}' WHERE id = $uid");
            }
            $_SESSION['success'] = "All users in $school archived.";
            logActivity($conn, $_SESSION['user_id'], 'Bulk Archive', "Archived all users in school: $school");
        }
        if (isset($_POST['bulk_restore'])) {
            foreach ($user_ids as $uid) {
                mysqli_query($conn, "UPDATE users SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = $uid");
            }
            $_SESSION['success'] = "All users in $school restored.";
            logActivity($conn, $_SESSION['user_id'], 'Bulk Restore', "Restored all users in school: $school");
        }
        if (isset($_POST['bulk_delete'])) {
            mysqli_begin_transaction($conn);
            try {
                foreach ($user_ids as $uid) {
                    mysqli_query($conn, "DELETE FROM event_attendance WHERE scout_id = $uid");
                    mysqli_query($conn, "DELETE FROM scout_profiles WHERE user_id = $uid");
                    mysqli_query($conn, "DELETE FROM scout_badge_progress WHERE scout_id = $uid");
                    mysqli_query($conn, "DELETE FROM users WHERE id = $uid");
                }
                mysqli_commit($conn);
                $_SESSION['success'] = "All users in $school permanently deleted.";
                logActivity($conn, $_SESSION['user_id'], 'Bulk Permanent Delete', "Deleted all users in school: $school");
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "Failed to permanently delete all users in $school.";
            }
        }
        header("Location: manage_scouts.php");
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id_to_action'])) {
    $user_id = intval($_POST['user_id_to_action']);
    
    if (isset($_POST['restore_user'])) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "User restored successfully.";
            logActivity($conn, $_SESSION['user_id'], 'Restore User', "Restored user ID: $user_id");
        } else {
            $_SESSION['error'] = "Error restoring user.";
        }
        header("Location: manage_scouts.php?view=archive");
        exit();
    }

    if (isset($_POST['delete_permanently'])) {
        // Fetch user name for logging before delete
        $name_query = mysqli_prepare($conn, "SELECT name, role FROM users WHERE id = ?");
        mysqli_stmt_bind_param($name_query, "i", $user_id);
        mysqli_stmt_execute($name_query);
        $user_res = mysqli_fetch_assoc(mysqli_stmt_get_result($name_query));
        $user_name = $user_res['name'] ?? "ID: $user_id";

        if ($user_res && $user_res['role'] === 'admin') {
             $_SESSION['error'] = "Admins cannot be permanently deleted.";
             header("Location: manage_scouts.php?view=archive");
             exit();
        }

        // Begin transaction for safe deletion
        mysqli_begin_transaction($conn);
        try {
            // Delete from related tables
            mysqli_query($conn, "DELETE FROM event_attendance WHERE scout_id = $user_id");
            mysqli_query($conn, "DELETE FROM scout_profiles WHERE user_id = $user_id");
            mysqli_query($conn, "DELETE FROM scout_badge_progress WHERE scout_id = $user_id");

            // Finally, delete the user
            $delete_user = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($delete_user, "i", $user_id);
            mysqli_stmt_execute($delete_user);

            mysqli_commit($conn);
            $_SESSION['success'] = "User permanently deleted.";
            logActivity($conn, $_SESSION['user_id'], 'Permanent Delete User', "Permanently deleted user '$user_name'");
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Failed to permanently delete user and related data.";
        }
        header("Location: manage_scouts.php?view=archive");
        exit();
    }
}

// Handle User Update from Modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $edit_user_id = intval($_POST['edit_user_id']);
    $name = $_POST['name'];
    $email = $_POST['email'];
    $school = $_POST['school'];
    $rank_id = !empty($_POST['rank_id']) ? intval($_POST['rank_id']) : NULL;
    $membership_card = $_POST['membership_card'];
    
    // Get scout_type and position (for scouts only)
    $scout_type = !empty($_POST['scout_type']) ? $_POST['scout_type'] : NULL;
    $position = !empty($_POST['position']) ? $_POST['position'] : NULL;

    if ($_SESSION['role'] == 'admin') {
        $role = $_POST['role'];
        $approved = isset($_POST['approved']) ? 1 : 0;
        
        // If position is provided, use it as status; otherwise keep existing status
        $status = $position ? $position : NULL;
        
        $update_query = "UPDATE users SET name = ?, email = ?, school = ?, role = ?, rank_id = ?, approved = ?, membership_card = ?, scout_type = ?, status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ssssiisssi", $name, $email, $school, $role, $rank_id, $approved, $membership_card, $scout_type, $status, $edit_user_id);
    } else {
        // Scout leader cannot change role or approval status.
        $status = $position ? $position : NULL;
        
        $update_query = "UPDATE users SET name = ?, email = ?, school = ?, rank_id = ?, membership_card = ?, scout_type = ?, status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "sssissi", $name, $email, $school, $rank_id, $membership_card, $scout_type, $status, $edit_user_id);
    }

    if (mysqli_stmt_execute($update_stmt)) {
        $_SESSION['success'] = "User updated successfully.";
        logActivity($conn, $_SESSION['user_id'], 'Update User', "Updated user ID: $edit_user_id ($name)");
    } else {
        $_SESSION['error'] = "Error updating user: " . mysqli_error($conn);
    }
    header("Location: manage_scouts.php");
    exit();
}

// Handle User Approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_user_id'])) {
    $approve_id = intval($_POST['approve_user_id']);
    $query = "UPDATE users SET approved = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $approve_id);
    if(mysqli_stmt_execute($stmt)){
        // Update payment status to Paid
        $pay_stmt = mysqli_prepare($conn, "UPDATE scout_profiles SET paid_status = 'Paid' WHERE user_id = ?");
        mysqli_stmt_bind_param($pay_stmt, "i", $approve_id);
        mysqli_stmt_execute($pay_stmt);

        $_SESSION['success'] = "User approved successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Approve User', "Approved user ID: $approve_id");
    } else {
        $_SESSION['error'] = "Error approving user.";
    }
    header("Location: manage_scouts.php");
    exit();
}

// Handle User Rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_user_id'])) {
    $reject_id = intval($_POST['reject_user_id']);
    $query = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $reject_id);
    if(mysqli_stmt_execute($stmt)){
        $_SESSION['success'] = "User rejected successfully!";
        logActivity($conn, $_SESSION['user_id'], 'Reject User', "Rejected/Deleted user ID: $reject_id");
    } else {
        $_SESSION['error'] = "Error rejecting user.";
    }
    header("Location: manage_scouts.php");
    exit();
}

// Handle Create User (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $school = $_POST['school'];
    $membership_card = $_POST['membership_card'];
    $scout_type = !empty($_POST['scout_type']) ? $_POST['scout_type'] : NULL;
    $position = !empty($_POST['position']) ? $_POST['position'] : NULL;

    // Check if email exists
    $check_query = "SELECT id FROM users WHERE email = ?";
    $stmt_check = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt_check, "s", $email);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $_SESSION['error'] = "Email already exists.";
    } else {
        // If position is provided, use it as status; otherwise use 'active'
        $status = $position ? $position : 'active';
        
        $insert_query = "INSERT INTO users (name, email, password, role, school, membership_card, approved, status, scout_type) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssssssss", $name, $email, $password, $role, $school, $membership_card, $status, $scout_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            
            // Automatically mark as Paid for Scouts and Scout Leaders
            if ($role === 'scout' || $role === 'scout_leader') {
                $paid_status = 'Paid';
                // Insert into scout_profiles with Paid status
                // Note: Admin form doesn't provide age/gender/birthday, so we insert defaults/NULLs where allowed or rely on DB defaults
                $profile_query = "INSERT INTO scout_profiles (user_id, paid_status, membership_card) VALUES (?, ?, ?)";
                $stmt_prof = mysqli_prepare($conn, $profile_query);
                mysqli_stmt_bind_param($stmt_prof, "iss", $user_id, $paid_status, $membership_card);
                mysqli_stmt_execute($stmt_prof);
            }

            $_SESSION['success'] = "User created successfully and marked as Paid.";
            logActivity($conn, $_SESSION['user_id'], 'Create User', "Created user $name ($role) - Paid");
        } else {
            $_SESSION['error'] = "Error creating user: " . mysqli_error($conn);
        }
    }
    header("Location: manage_scouts.php");
    exit();
}

// Fetch users
$view = $_GET['view'] ?? 'active';

// Check if the is_archived column exists to prevent errors if DB is not updated
$check_column_query = "SHOW COLUMNS FROM `users` LIKE 'is_archived'";
$check_column_result = mysqli_query($conn, $check_column_query);
$column_exists = mysqli_num_rows($check_column_result) > 0;

if ($view === 'archive') {
    if ($column_exists) {
        $users_result = mysqli_query($conn, "SELECT u.id, u.name, u.email, u.role, COALESCE(asa.membership_card, u.membership_card) as membership_card, u.archived_at, a.name as admin_name FROM users u LEFT JOIN users a ON u.archived_by = a.id LEFT JOIN admin_scout_archive asa ON u.name = asa.name WHERE u.is_archived = 1 GROUP BY u.id ORDER BY u.archived_at DESC");
    } else {
        $users_result = false; // No archived users if column doesn't exist
    }
} else {
    $query = "SELECT u.id, u.name, u.email, u.role, u.school, u.rank_id, u.approved, u.scout_type, u.status, u.profile_picture, 
              COALESCE(asa.membership_card, u.membership_card) as membership_card,
              sp.birthday, sp.age, sp.gender, sp.paid_status, r.rank_name
              FROM users u 
              LEFT JOIN admin_scout_archive asa ON u.name = asa.name
              LEFT JOIN scout_profiles sp ON u.id = sp.user_id
              LEFT JOIN ranks r ON u.rank_id = r.id";
    if ($column_exists) {
        $query .= " WHERE u.is_archived = 0 OR u.is_archived IS NULL";
    }
    $query .= " GROUP BY u.id ORDER BY FIELD(u.role, 'admin', 'scout_leader', 'scout'), u.name ASC";
    $users_result = mysqli_query($conn, $query);
}
$users_data = [];
if($users_result) {
    while ($row = mysqli_fetch_assoc($users_result)) {
        $users_data[] = $row;
    }
}

// Fetch pending users
$pending_users = mysqli_query($conn, "SELECT * FROM users WHERE approved = 0");

// Fetch ranks for edit modal
$ranks_result = mysqli_query($conn, "SELECT id, rank_name FROM ranks");
$ranks = mysqli_fetch_all($ranks_result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage SCHOOLS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include('favicon_header.php'); ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

/* BASE */
body{
    margin:0;
    font-family: 'Poppins', sans-serif;
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
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/wall3.jpg') no-repeat center center/cover;
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
    background:rgba(0,0,0,0.2);
    z-index:0;
}

.main > *{
    position:relative;
    z-index:1;
}

/* GLASS */
.glass{
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius:20px;
    padding:30px;
    margin-bottom:30px;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
}

/* HEADER */
.page-title{
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* TABLE */
.table{
    color: white !important;
    --bs-table-color: white;
    --bs-table-hover-color: white;
    vertical-align: middle;
}

.table thead th {
    background-color: rgba(0, 0, 0, 0.3);
    color: #fff;
    border-bottom: 2px solid rgba(255,255,255,0.1);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 15px;
    white-space: nowrap;
}

.table tbody td {
    background-color: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 12px 15px;
    transition: background 0.3s;
    vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) td {
    background-color: rgba(255, 255, 255, 0.02);
}

.table tbody tr:hover td {
    background-color: rgba(255, 255, 255, 0.15);
}

/* Action buttons alignment */
.table tbody td .d-flex {
    flex-wrap: nowrap;
    align-items: center;
}

.table tbody td .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    white-space: nowrap;
    min-width: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.table thead th[style*="text-align: center"],
.table tbody td[style*="text-align: center"] {
    vertical-align: middle !important;
}

/* Badge alignment */
.table tbody td .badge {
    display: inline-block;
    white-space: nowrap;
}

/* MODAL & FORM STYLES */
.modal {
    z-index: 99999 !important;
}
.modal-backdrop {
    z-index: 99998 !important;
}
.modal-content {
    background: rgba(20, 20, 20, 0.95);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
}
.modal-header {
    border-color: rgba(255, 255, 255, 0.1);
    position: relative;
}
.modal-footer {
    border-color: rgba(255, 255, 255, 0.1);
}
.form-control, .form-select {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
}
.form-control:focus, .form-select:focus {
    background: rgba(255,255,255,0.2);
    color: white;
    border-color: #28a745;
    box-shadow: none;
}
.form-select option {
    background-color: #333;
    color: white;
}
.btn-close {
    filter: brightness(0) invert(1);
    opacity: 1;
    cursor: pointer;
    pointer-events: auto !important;
}
.btn-close:hover {
    opacity: 0.75;
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
            <div class="page-title">MANAGE SCHOOLS</div>

            <?php if (isset($_SESSION['success'])) { ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php } ?>
            <?php if (isset($_SESSION['error'])) { ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php } ?>

            <!-- TABS -->
            <ul class="nav nav-pills mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $view === 'active' ? 'active bg-success' : 'text-white' ?>" href="?view=active">Active Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $view === 'archive' ? 'active bg-success' : 'text-white' ?>" href="?view=archive">Archived Users</a>
                </li>
            </ul>

            <?php if ($view === 'active'): ?>
            <!-- ACTIVE USERS VIEW -->

            <!-- ACTION BUTTONS -->
            <div class="mb-3">
                <a href="download_users.php" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Download CSV
                </a>

                <?php if ($_SESSION['role'] == 'admin') { ?>
                    <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#registerUserModal">
                        <i class="fas fa-user-plus me-2"></i> Register User
                    </button>
                <?php } ?>

                <?php if ($_SESSION['role'] == 'admin') { ?>
                    <button type="button" class="btn btn-warning ms-2 text-dark" data-bs-toggle="modal" data-bs-target="#pendingUsersModal">
                        <i class="fas fa-user-check me-2"></i> Approve Users
                        <?php if(mysqli_num_rows($pending_users) > 0) { ?>
                            <span class="badge bg-danger"><?= mysqli_num_rows($pending_users) ?></span>
                        <?php } ?>
                  
                <?php } ?>
            </div>

            <!-- SEARCH BAR -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label text-white-50">Search Scouts</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-white border-secondary">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email, school, or membership card..." onkeyup="searchUsers()">
                    </div>
                </div>
            </div>

            <!-- FILTER -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label text-white-50">Filter by Role</label>
                    <select id="filterRole" class="form-select" onchange="filterUsers()">
                        <option value="all">All</option>
                        <option value="scout">Scout</option>
                        <option value="scout_leader">Scout Leader</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white-50">Filter by School</label>
                    <select id="filterSchool" class="form-select" onchange="filterUsers()">
                        <option value="all">All Schools</option>
                        <?php
                        $schools = array_unique(array_map(function($u){ return $u['school'] ?? ''; }, $users_data));
                        foreach ($schools as $school) {
                            if ($school && $school !== 'N/A') {
                                echo '<option value="'.htmlspecialchars($school).'">'.htmlspecialchars($school).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white-50">Filter by Scout Type</label>
                    <select id="filterScoutType" class="form-select" onchange="filterUsers()">
                        <option value="all">All Scout Types</option>
                        <option value="boy_scout">Boy Scout</option>
                        <option value="outfit_scout">Outfit Scout</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white-50">Filter by Position</label>
                    <select id="filterPosition" class="form-select" onchange="filterUsers()">
                        <option value="all">All Positions</option>
                        <option value="platoon_leader">Platoon Leader</option>
                        <option value="troop_leader">Troop Leader</option>
                         <option value="normal_scout">Normal Scout</option>
                    </select>
                </div>
            </div>

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Name</th>
                            <th style="width: 15%;">Email</th>
                            <th style="width: 15%;">School</th>
                            <th style="width: 12%;">Membership Card</th>
                            <th style="width: 10%;">Scout Type</th>
                            <th style="width: 10%;">Position</th>
                            <th style="width: 8%;">Role</th>
                            <th style="width: 18%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_data as $row) { ?>
                        <tr class="user-row" 
                            data-role="<?php echo $row['role']; ?>" 
                            data-school="<?php echo htmlspecialchars($row['school'] ?? ''); ?>"
                            data-scout-type="<?php echo htmlspecialchars($row['scout_type'] ?? ''); ?>"
                            data-position="<?php echo htmlspecialchars($row['status'] ?? ''); ?>">
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo ($row['role'] === 'admin') ? '' : htmlspecialchars($row['school'] ?? ''); ?></td>
                            <td><?php echo ($row['role'] === 'admin') ? '' : htmlspecialchars($row['membership_card'] ?? ''); ?></td>
                            <td>
                                <?php if ($row['role'] === 'scout' && !empty($row['scout_type'])): ?>
                                    <span class="badge" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.25), rgba(255, 152, 0, 0.15)); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107;">
                                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($row['scout_type']))); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['role'] === 'scout' && !empty($row['status'])): ?>
                                    <span class="badge" style="background: linear-gradient(135deg, rgba(0, 188, 212, 0.25), rgba(0, 150, 136, 0.15)); border: 1px solid rgba(0, 188, 212, 0.4); color: #00bcd4;">
                                        <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($row['status']))); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $row['role'] == 'admin' ? 'danger' : ($row['role'] == 'scout_leader' ? 'warning text-dark' : 'success'); ?>"><?php echo ucfirst(htmlspecialchars($row['role'])); ?></span></td>
                            <td style="text-align: center;">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if ($row['role'] == 'scout'): ?>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewDetailsModal<?php echo $row['id']; ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($row['role'] == 'scout_leader'): ?>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewLeaderModal<?php echo $row['id']; ?>" title="View Leader Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="delete_user.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-secondary btn-sm"
                                       onclick="return confirm('Are you sure you want to archive this user?');"
                                       title="Archive User">
                                        <i class="fas fa-archive"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php else: // ARCHIVE VIEW ?>
            <div class="table-responsive">
                <table class="table table-hover" id="archivedUsersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Membership Card</th>
                            <th>Role</th>
                            <th>Archived At</th>
                            <th>Archived By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users_data)): ?>
                            <tr><td colspan="7" class="text-center">No archived users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users_data as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo ($row['role'] === 'admin') ? '' : htmlspecialchars($row['membership_card'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($row['role'])); ?></span></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($row['archived_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['admin_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id_to_action" value="<?= $row['id'] ?>">
                                            <button type="submit" name="restore_user" class="btn btn-success btn-sm" title="Restore User"><i class="fas fa-undo"></i> Restore</button>
                                        </form>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?= $row['id'] ?>">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Delete User Modals -->
            <?php if (!empty($users_data)): foreach ($users_data as $row): ?>
            <!-- placeholder, modals moved outside glass div -->
            <?php endforeach; endif; ?>

            <?php endif; ?>

        </div>

        <!-- FOOTER -->
        <?php include('footer.php'); ?>

    </div>

    <!-- Delete User Modals (outside glass/main for correct z-index) -->
    <?php if (!empty($users_data)): foreach ($users_data as $row): ?>
    <div class="modal fade" id="deleteUserModal<?= $row['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(255,255,255,0.1); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 16px; overflow: hidden;">
                <div class="modal-header" style="background: linear-gradient(135deg, #c0392b, #e74c3c); border: none; padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Permanently Delete User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body text-center py-4">
                        <p class="mb-1" style="font-size: 1rem; color: rgba(255,255,255,0.7);">You are about to permanently delete</p>
                        <p class="fw-bold" style="font-size: 1.1rem; color: #e74c3c;"><?= htmlspecialchars($row['name']) ?></p>
                        <p class="mb-0" style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">This action cannot be undone and will remove all related data.</p>
                    </div>
                    <div class="modal-footer justify-content-center" style="border-top: 1px solid rgba(255,255,255,0.1); gap: 1rem;">
                        <input type="hidden" name="user_id_to_action" value="<?= $row['id'] ?>">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_permanently" class="btn btn-danger px-4"><i class="fas fa-trash-alt me-1"></i> Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- Edit Modals (Placed outside table) -->
    <?php foreach ($users_data as $row) { ?>
    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit User: <?php echo htmlspecialchars($row['name']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="edit_user_id" value="<?php echo $row['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">School</label>
                            <input type="text" class="form-control" name="school" value="<?php echo htmlspecialchars($row['school'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Membership Card</label>
                            <input type="text" class="form-control" name="membership_card" value="<?php echo htmlspecialchars($row['membership_card'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="roleSelect<?php echo $row['id']; ?>" <?php if ($_SESSION['role'] != 'admin') echo 'disabled'; ?>>
                                <option value="admin" <?php if ($row['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                                <option value="scout_leader" <?php if ($row['role'] == 'scout_leader') echo 'selected'; ?>>Scout Leader</option>
                                <option value="scout" <?php if ($row['role'] == 'scout') echo 'selected'; ?>>Scout</option>
                            </select>
                        </div>

                        <?php if ($row['role'] == 'scout'): ?>
                        <div class="mb-3 scout-only-field" id="scoutTypeField<?php echo $row['id']; ?>" style="display: block;">
                            <label class="form-label">Scout Type</label>
                            <select class="form-select" name="scout_type">
                                <option value="">-- Select Scout Type --</option>
                                <option value="boy_scout" <?php if ($row['scout_type'] == 'boy_scout') echo 'selected'; ?>>Boy Scout</option>
                                <option value="outfit_scout" <?php if ($row['scout_type'] == 'outfit_scout') echo 'selected'; ?>>Outfit Scout</option>
                            </select>
                        </div>

                        <div class="mb-3 scout-only-field" id="positionField<?php echo $row['id']; ?>" style="display: block;">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position">
                                <option value="">-- Select Position --</option>
                                <option value="normal_scout" <?php if ($row['status'] == 'normal_scout') echo 'selected'; ?>>Normal Scout</option>
                                <option value="platoon_leader" <?php if ($row['status'] == 'platoon_leader') echo 'selected'; ?>>Platoon Leader</option>
                                <option value="troop_leader" <?php if ($row['status'] == 'troop_leader') echo 'selected'; ?>>Troop Leader</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="mb-3 scout-only-field" id="scoutTypeField<?php echo $row['id']; ?>" style="display: none;">
                            <label class="form-label">Scout Type</label>
                            <select class="form-select" name="scout_type">
                                <option value="">-- Select Scout Type --</option>
                                <option value="boy_scout" <?php if ($row['scout_type'] == 'boy_scout') echo 'selected'; ?>>Boy Scout</option>
                                <option value="outfit_scout" <?php if ($row['scout_type'] == 'outfit_scout') echo 'selected'; ?>>Outfit Scout</option>
                            </select>
                        </div>

                        <div class="mb-3 scout-only-field" id="positionField<?php echo $row['id']; ?>" style="display: none;">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position">
                                <option value="">-- Select Position --</option>
                                <option value="normal_scout" <?php if ($row['status'] == 'normal_scout') echo 'selected'; ?>>Normal Scout</option>
                                <option value="platoon_leader" <?php if ($row['status'] == 'platoon_leader') echo 'selected'; ?>>Platoon Leader</option>
                                <option value="troop_leader" <?php if ($row['status'] == 'troop_leader') echo 'selected'; ?>>Troop Leader</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Assign Rank</label>
                            <select class="form-select" name="rank_id">
                                <option value="">-- Select Rank --</option>
                                <?php foreach ($ranks as $rank) { ?>
                                    <option value="<?php echo $rank['id']; ?>" <?php if ($row['rank_id'] == $rank['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($rank['rank_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <?php if ($_SESSION['role'] == 'admin') { ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="approved" id="approvedCheck<?php echo $row['id']; ?>" <?php if ($row['approved']) echo 'checked'; ?>>
                            <label class="form-check-label" for="approvedCheck<?php echo $row['id']; ?>">Approved</label>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<!-- View Details Modals -->
<?php foreach ($users_data as $row) { 
    if ($row['role'] == 'scout') { ?>
    <div class="modal fade" id="viewDetailsModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewDetailsModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDetailsModalLabel<?php echo $row['id']; ?>">Scout Registration Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-4 text-center mb-3">
                            <img src="<?php echo htmlspecialchars($row['profile_picture'] ?? 'uploads/default-avatar.png'); ?>" 
                                 alt="Profile Picture" 
                                 class="img-fluid rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.3);">
                        </div>
                        
                        <!-- Basic Information -->
                        <div class="col-md-8">
                            <h6 class="text-info mb-3"><i class="fas fa-user"></i> Basic Information</h6>
                            <table class="table table-sm table-borderless text-white">
                                <tr>
                                    <td style="width: 40%;"><strong>Full Name:</strong></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>School:</strong></td>
                                    <td><?php echo htmlspecialchars($row['school'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Membership Card:</strong></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['membership_card'] ?? 'N/A'); ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr style="border-color: rgba(255, 255, 255, 0.2);">
                    
                    <!-- Scout Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-warning mb-3"><i class="fas fa-id-badge"></i> Scout Information</h6>
                            <table class="table table-sm table-borderless text-white">
                                <tr>
                                    <td style="width: 50%;"><strong>Scout Type:</strong></td>
                                    <td>
                                        <?php if (!empty($row['scout_type'])): ?>
                                            <span class="badge" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.25), rgba(255, 152, 0, 0.15)); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107;">
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($row['scout_type']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Position:</strong></td>
                                    <td>
                                        <?php if (!empty($row['status'])): ?>
                                            <span class="badge" style="background: linear-gradient(135deg, rgba(0, 188, 212, 0.25), rgba(0, 150, 136, 0.15)); border: 1px solid rgba(0, 188, 212, 0.4); color: #00bcd4;">
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($row['status']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not Set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Rank:</strong></td>
                                    <td>
                                        <?php if (!empty($row['rank_name'])): ?>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($row['rank_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No Rank Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-success mb-3"><i class="fas fa-info-circle"></i> Personal Information</h6>
                            <table class="table table-sm table-borderless text-white">
                                <tr>
                                    <td style="width: 50%;"><strong>Birthday:</strong></td>
                                    <td><?php echo !empty($row['birthday']) ? date('F j, Y', strtotime($row['birthday'])) : '<span class="text-muted">N/A</span>'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Age:</strong></td>
                                    <td><?php echo htmlspecialchars($row['age'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Gender:</strong></td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['gender'] ?? 'N/A')); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr style="border-color: rgba(255, 255, 255, 0.2);">
                    
                    <!-- Account Status -->
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="text-danger mb-3"><i class="fas fa-check-circle"></i> Account Status</h6>
                            <table class="table table-sm table-borderless text-white">
                                <tr>
                                    <td style="width: 25%;"><strong>Role:</strong></td>
                                    <td><span class="badge bg-success"><?php echo ucfirst(htmlspecialchars($row['role'])); ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Approval Status:</strong></td>
                                    <td>
                                        <?php if ($row['approved']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Status:</strong></td>
                                    <td>
                                        <?php if (!empty($row['paid_status'])): ?>
                                            <span class="badge bg-<?php echo $row['paid_status'] == 'Paid' ? 'success' : 'warning text-dark'; ?>">
                                                <?php echo htmlspecialchars($row['paid_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                        <i class="fas fa-edit"></i> Edit User
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php } 
} ?>

<!-- View Scout Leader Details Modals -->
<?php foreach ($users_data as $row) { 
    if ($row['role'] == 'scout_leader') { 
        // Fetch troop information for this scout leader
        $leader_id = $row['id'];
        
        // Get troop name
        $troop_query = "SELECT troop_name, id FROM troops WHERE scout_leader_id = ? LIMIT 1";
        $stmt_troop = mysqli_prepare($conn, $troop_query);
        mysqli_stmt_bind_param($stmt_troop, "i", $leader_id);
        mysqli_stmt_execute($stmt_troop);
        $troop_result = mysqli_stmt_get_result($stmt_troop);
        $troop_info = mysqli_fetch_assoc($troop_result);
        $troop_name = $troop_info['troop_name'] ?? 'Not Assigned';
        $troop_id = $troop_info['id'] ?? null;
        
        // Fetch troop members (scouts assigned to this troop)
        $troop_members = [];
        if ($troop_id) {
            $members_query = "SELECT u.id, u.name, u.email, u.school, u.membership_card, u.scout_type, u.status, sp.paid_status, r.rank_name
                            FROM users u
                            LEFT JOIN scout_profiles sp ON u.id = sp.user_id
                            LEFT JOIN ranks r ON u.rank_id = r.id
                            WHERE u.troop_id = ? AND u.role = 'scout'
                            ORDER BY u.name ASC";
            $stmt_members = mysqli_prepare($conn, $members_query);
            mysqli_stmt_bind_param($stmt_members, "i", $troop_id);
            mysqli_stmt_execute($stmt_members);
            $members_result = mysqli_stmt_get_result($stmt_members);
            while ($member = mysqli_fetch_assoc($members_result)) {
                $troop_members[] = $member;
            }
        }
?>
    <div class="modal fade" id="viewLeaderModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="viewLeaderModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLeaderModalLabel<?php echo $row['id']; ?>">Scout Leader Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-md-3 text-center mb-3">
                            <img src="<?php echo htmlspecialchars($row['profile_picture'] ?? 'uploads/default-avatar.png'); ?>" 
                                 alt="Profile Picture" 
                                 class="img-fluid rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.3);">
                        </div>
                        
                        <!-- Leader Information -->
                        <div class="col-md-9">
                            <h6 class="text-warning mb-3"><i class="fas fa-user-tie"></i> Scout Leader Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless text-white">
                                        <tr>
                                            <td style="width: 40%;"><strong>Full Name:</strong></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>School:</strong></td>
                                            <td><?php echo htmlspecialchars($row['school'] ?? 'N/A'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless text-white">
                                        <tr>
                                            <td style="width: 40%;"><strong>Role:</strong></td>
                                            <td><span class="badge bg-warning text-dark">Scout Leader</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Troop Name:</strong></td>
                                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($troop_name); ?></span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Approval Status:</strong></td>
                                            <td>
                                                <?php if ($row['approved']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Troop Members:</strong></td>
                                            <td><span class="badge bg-info"><?php echo count($troop_members); ?> Scouts</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="border-color: rgba(255, 255, 255, 0.2);">
                    
                    <!-- Troop Members Section -->
                    <h6 class="text-success mb-3"><i class="fas fa-users"></i> Troop Members</h6>
                    <?php if (count($troop_members) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>School</th>
                                    <th>Membership Card</th>
                                    <th>Scout Type</th>
                                    <th>Position</th>
                                    <th>Rank</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($troop_members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['school'] ?? 'N/A'); ?></td>
                                    <td><code class="text-info"><?php echo htmlspecialchars($member['membership_card'] ?? 'N/A'); ?></code></td>
                                    <td>
                                        <?php if (!empty($member['scout_type'])): ?>
                                            <span class="badge" style="background: rgba(255, 193, 7, 0.2); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107;">
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($member['scout_type']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($member['status'])): ?>
                                            <span class="badge" style="background: rgba(0, 188, 212, 0.2); border: 1px solid rgba(0, 188, 212, 0.4); color: #00bcd4;">
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($member['status']))); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($member['rank_name'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($member['rank_name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unranked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($member['paid_status'])): ?>
                                            <span class="badge bg-<?php echo $member['paid_status'] == 'Paid' ? 'success' : 'warning text-dark'; ?>">
                                                <?php echo htmlspecialchars($member['paid_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This scout leader has no troop members assigned yet.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                        <i class="fas fa-edit"></i> Edit User
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php } 
} ?>

<!-- Register User Modal -->
<div class="modal fade" id="registerUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membership Card</label>
                        <input type="text" name="membership_card" id="registerMembershipCard" class="form-control" placeholder="Enter membership card number">
                        <div class="form-text text-info">Enter the membership card number manually.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" id="registerRoleSelect" required>
                            <option value="admin">Admin</option>
                            <option value="scout_leader">Scout Leader</option>
                            <option value="scout">Scout</option>
                        </select>
                    </div>
                    <div class="mb-3" id="registerScoutTypeField" style="display: none;">
                        <label class="form-label">Scout Type</label>
                        <select name="scout_type" class="form-select">
                            <option value="">-- Select Scout Type --</option>
                            <option value="boy_scout">Boy Scout</option>
                            <option value="outfit_scout">Outfit Scout</option>
                        </select>
                    </div>
                    <div class="mb-3" id="registerPositionField" style="display: none;">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select">
                            <option value="">-- Select Position --</option>
                            <option value="normal_scout">Normal Scout</option>
                            <option value="platoon_leader">Platoon Leader</option>
                            <option value="troop_leader">Troop Leader</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School</label>
                        <input type="text" name="school" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="create_user" class="btn btn-primary">Register User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pending Users Modal -->
<div class="modal fade" id="pendingUsersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pending User Approvals</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if(mysqli_num_rows($pending_users) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p_user = mysqli_fetch_assoc($pending_users)): ?>
                    <tr>
                        <td><?= htmlspecialchars($p_user['name']) ?></td>
                        <td><?= htmlspecialchars($p_user['email']) ?></td>
                        <td><?= ucfirst($p_user['role']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="approve_user_id" value="<?= $p_user['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to reject this user?');">
                                <input type="hidden" name="reject_user_id" value="<?= $p_user['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-center">No pending users found.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function searchUsers(){
    let view = '<?= $view ?>';
    if (view !== 'active') return; // Only search on active view

    let searchInput = document.getElementById("searchInput").value.toLowerCase();
    let table = document.getElementById("usersTable");
    let rows = table.getElementsByClassName("user-row");

    for(let i=0; i<rows.length; i++){
        let cells = rows[i].getElementsByTagName("td");
        let name = cells[0].textContent.toLowerCase();
        let email = cells[1].textContent.toLowerCase();
        let school = cells[2].textContent.toLowerCase();
        let membershipCard = cells[3].textContent.toLowerCase();
        
        let matchesSearch = (searchInput === "" || 
                            name.includes(searchInput) || 
                            email.includes(searchInput) || 
                            school.includes(searchInput) || 
                            membershipCard.includes(searchInput));
        
        // Apply both search and filters
        let filterRole = document.getElementById("filterRole").value.toLowerCase();
        let filterSchool = document.getElementById("filterSchool").value.toLowerCase();
        let filterScoutType = document.getElementById("filterScoutType").value.toLowerCase();
        let filterPosition = document.getElementById("filterPosition").value.toLowerCase();
        
        let role = rows[i].getAttribute("data-role").toLowerCase();
        let schoolAttr = rows[i].getAttribute("data-school").toLowerCase();
        let scoutType = rows[i].getAttribute("data-scout-type").toLowerCase();
        let position = rows[i].getAttribute("data-position").toLowerCase();
        
        let showRole = (filterRole === "all" || role === filterRole);
        let showSchool = (filterSchool === "all" || schoolAttr === filterSchool || schoolAttr.includes(filterSchool));
        let showScoutType = (filterScoutType === "all" || scoutType === filterScoutType);
        let showPosition = (filterPosition === "all" || position === filterPosition);
        
        rows[i].style.display = (matchesSearch && showRole && showSchool && showScoutType && showPosition) ? "" : "none";
    }
}

function filterUsers(){
    let view = '<?= $view ?>';
    if (view !== 'active') return; // Only filter on active view

    let filterRole = document.getElementById("filterRole").value.toLowerCase();
    let filterSchool = document.getElementById("filterSchool").value.toLowerCase();
    let filterScoutType = document.getElementById("filterScoutType").value.toLowerCase();
    let filterPosition = document.getElementById("filterPosition").value.toLowerCase();
    let searchInput = document.getElementById("searchInput").value.toLowerCase();
    let table = document.getElementById("usersTable");
    let rows = table.getElementsByClassName("user-row");

    for(let i=0; i<rows.length; i++){
        let role = rows[i].getAttribute("data-role").toLowerCase();
        let school = rows[i].getAttribute("data-school").toLowerCase();
        let scoutType = rows[i].getAttribute("data-scout-type").toLowerCase();
        let position = rows[i].getAttribute("data-position").toLowerCase();
        
        let showRole = (filterRole === "all" || role === filterRole);
        let showSchool = (filterSchool === "all" || school === filterSchool || school.includes(filterSchool));
        let showScoutType = (filterScoutType === "all" || scoutType === filterScoutType);
        let showPosition = (filterPosition === "all" || position === filterPosition);
        
        // Apply search filter as well
        let matchesSearch = true;
        if (searchInput !== "") {
            let cells = rows[i].getElementsByTagName("td");
            let name = cells[0].textContent.toLowerCase();
            let email = cells[1].textContent.toLowerCase();
            let schoolText = cells[2].textContent.toLowerCase();
            let membershipCard = cells[3].textContent.toLowerCase();
            
            matchesSearch = (name.includes(searchInput) || 
                           email.includes(searchInput) || 
                           schoolText.includes(searchInput) || 
                           membershipCard.includes(searchInput));
        }
        
        rows[i].style.display = (showRole && showSchool && showScoutType && showPosition && matchesSearch) ? "" : "none";
    }
}

// Handle role change in edit modals to show/hide scout-only fields
document.addEventListener('DOMContentLoaded', function() {
    // Get all role select elements in edit modals
    const roleSelects = document.querySelectorAll('[id^="roleSelect"]');
    
    roleSelects.forEach(function(roleSelect) {
        // Extract the user ID from the select element's ID
        const userId = roleSelect.id.replace('roleSelect', '');
        
        // Add change event listener
        roleSelect.addEventListener('change', function() {
            const scoutTypeField = document.getElementById('scoutTypeField' + userId);
            const positionField = document.getElementById('positionField' + userId);
            
            if (this.value === 'scout') {
                if (scoutTypeField) scoutTypeField.style.display = 'block';
