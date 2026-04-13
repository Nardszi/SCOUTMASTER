<?php
session_start();
include('config.php');

// 1. Security & Setup
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Helper function for handling file uploads
function handle_badge_icon_upload($file_input_name) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
        $target_dir = "uploads/badge_icons/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $safe_file_name = preg_replace("/[^a-zA-Z0-9-_\.]/", "", basename($_FILES[$file_input_name]['name']));
        $target_file = $target_dir . time() . "_" . $safe_file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif', 'svg']) && move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $target_file)) {
            return $target_file;
        }
    }
    return null;
}

// 2. Handle POST Actions
$redirect_url = 'manage_merit_badges.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Badge Actions
    if (isset($_POST['add_badge'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $scout_type = mysqli_real_escape_string($conn, $_POST['scout_type']);
        $is_eagle_required = isset($_POST['is_eagle_required']) ? 1 : 0;
        $icon_path = handle_badge_icon_upload('icon_path');
        $icon_path_escaped = $icon_path ? "'" . mysqli_real_escape_string($conn, $icon_path) . "'" : "NULL";
        
        $query = "INSERT INTO merit_badges (name, description, scout_type, is_eagle_required, icon_path) VALUES ('$name', '$description', '$scout_type', $is_eagle_required, $icon_path_escaped)";
        @mysqli_query($conn, $query);
        $new_badge_id = mysqli_insert_id($conn);
        @logActivity($conn, $_SESSION['user_id'], 'Add Badge', "Added merit badge '$name' (ID: $new_badge_id, Type: $scout_type)");
        $_SESSION['success'] = "Merit Badge added successfully.";
    } elseif (isset($_POST['update_badge'])) {
        $badge_id = (int)$_POST['badge_id'];
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $scout_type = mysqli_real_escape_string($conn, $_POST['scout_type']);
        $is_eagle_required = isset($_POST['is_eagle_required']) ? 1 : 0;
        $new_icon_path = handle_badge_icon_upload('icon_path');
        
        if ($new_icon_path) {
            $new_icon_path_escaped = mysqli_real_escape_string($conn, $new_icon_path);
            $query = "UPDATE merit_badges SET name = '$name', description = '$description', scout_type = '$scout_type', is_eagle_required = $is_eagle_required, icon_path = '$new_icon_path_escaped' WHERE id = $badge_id";
        } else {
            $query = "UPDATE merit_badges SET name = '$name', description = '$description', scout_type = '$scout_type', is_eagle_required = $is_eagle_required WHERE id = $badge_id";
        }
        @mysqli_query($conn, $query);
        @logActivity($conn, $_SESSION['user_id'], 'Update Badge', "Updated merit badge '$name' (ID: $badge_id, Type: $scout_type)");
        $_SESSION['success'] = "Merit Badge updated successfully.";
    } elseif (isset($_POST['delete_badge'])) {
        $badge_id = (int)$_POST['badge_id'];

        // Fetch name for logging before deleting
        $b_result = @mysqli_query($conn, "SELECT name FROM merit_badges WHERE id = $badge_id");
        $badge_name = "ID: $badge_id";
        if ($b_result && $b_row = mysqli_fetch_assoc($b_result)) {
            $badge_name = $b_row['name'];
        }

        @mysqli_query($conn, "DELETE FROM merit_badges WHERE id = $badge_id");
        @logActivity($conn, $_SESSION['user_id'], 'Delete Badge', "Deleted merit badge '$badge_name'");
        $_SESSION['success'] = "Merit Badge deleted successfully.";
    }

    // Requirement Actions
    $badge_id_for_reqs = isset($_POST['badge_id']) ? (int)$_POST['badge_id'] : null;
    if ($badge_id_for_reqs) {
        $redirect_url .= '?view_reqs_for=' . $badge_id_for_reqs;
    }

    if (isset($_POST['add_requirement'])) {
        $req_number = mysqli_real_escape_string($conn, $_POST['requirement_number']);
        $req_desc = mysqli_real_escape_string($conn, $_POST['description']);
        $query = "INSERT INTO badge_requirements (merit_badge_id, requirement_number, description) VALUES ($badge_id_for_reqs, '$req_number', '$req_desc')";
        @mysqli_query($conn, $query);
        @logActivity($conn, $_SESSION['user_id'], 'Add Requirement', "Added requirement '$req_number' to badge ID $badge_id_for_reqs");
        $_SESSION['success'] = "Requirement added.";
    } elseif (isset($_POST['update_requirement'])) {
        $req_id = (int)$_POST['requirement_id'];
        $req_number = mysqli_real_escape_string($conn, $_POST['requirement_number']);
        $req_desc = mysqli_real_escape_string($conn, $_POST['description']);
        $query = "UPDATE badge_requirements SET requirement_number = '$req_number', description = '$req_desc' WHERE id = $req_id AND merit_badge_id = $badge_id_for_reqs";
        @mysqli_query($conn, $query);
        @logActivity($conn, $_SESSION['user_id'], 'Update Requirement', "Updated requirement '$req_number' for badge ID $badge_id_for_reqs");
        $_SESSION['success'] = "Requirement updated.";
    } elseif (isset($_POST['delete_requirement'])) {
        $req_id = (int)$_POST['requirement_id'];
        @mysqli_query($conn, "DELETE FROM badge_requirements WHERE id = $req_id AND merit_badge_id = $badge_id_for_reqs");
        @logActivity($conn, $_SESSION['user_id'], 'Delete Requirement', "Deleted requirement ID $req_id from badge ID $badge_id_for_reqs");
        $_SESSION['success'] = "Requirement deleted.";
    }

    header("Location: " . $redirect_url);
    exit();
}

// 3. Fetch Data for Display
$view_mode = 'list_badges';
$badge_data = null;
$requirements_data = [];
$badges_data = [];

if (isset($_GET['view_reqs_for']) && is_numeric($_GET['view_reqs_for'])) {
    $view_mode = 'list_reqs';
    $badge_id = (int)$_GET['view_reqs_for'];
    
    $result = @mysqli_query($conn, "SELECT * FROM merit_badges WHERE id = $badge_id");
    if ($result) {
        $badge_data = mysqli_fetch_assoc($result);
    }
    if (!$badge_data) { header('Location: manage_merit_badges.php'); exit(); }

    $req_result = @mysqli_query($conn, "SELECT * FROM badge_requirements WHERE merit_badge_id = $badge_id ORDER BY id ASC");
    if ($req_result) {
        while ($row = mysqli_fetch_assoc($req_result)) { 
            $requirements_data[] = $row; 
        }
    }
} else {
    // Filter Logic
    $filter_eagle = isset($_GET['filter']) && $_GET['filter'] === 'eagle';
    $filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    $query = "SELECT * FROM merit_badges WHERE 1=1";
    if ($filter_eagle) {
        $query .= " AND is_eagle_required = 1";
    }
    if ($filter_type !== 'all') {
        $query .= " AND scout_type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
    }
    $query .= " ORDER BY scout_type ASC, name ASC";
    $result = @mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) { 
            $badges_data[] = $row; 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Merit Badges</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }
        .main { flex: 1; margin-left: 240px; padding: 30px; position: relative; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-collapsed .main {
            margin-left: 80px;
        }
        .main > * { position: relative; z-index: 1; }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(12px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.15); flex: 1; margin-bottom: 20px; }
        .table {
            color: white !important;
            --bs-table-color: white;
            --bs-table-hover-color: white;
            vertical-align: middle;
        }
        .table thead th { background-color: rgba(0,0,0,0.3); border-bottom: 2px solid rgba(255,255,255,0.1); }
        .table tbody td { background-color: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
        .table-hover tbody tr:hover td { background-color: rgba(255,255,255,0.15); }
        .modal-content { background: rgba(20, 20, 20, 0.95); color: white; border: 1px solid rgba(255, 255, 255, 0.1); }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.1); }
        .form-control, .form-select { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.2); color: white; border-color: #28a745; box-shadow: none; }
        .form-control::placeholder { color: rgba(255,255,255,0.7); }
        .form-select option { background-color: #1a1a1a; color: white; }
        .badge-icon-sm { width: 40px; height: 40px; object-fit: contain; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <?php include('navbar.php'); ?>
        <div class="glass">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($view_mode == 'list_reqs'): // REQUIREMENTS VIEW ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="fw-bold">Manage Requirements for "<?php echo htmlspecialchars($badge_data['name']); ?>"</h1>
                    <div>
                        <a href="manage_merit_badges.php" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i>Back to Badges</a>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRequirementModal"><i class="fas fa-plus me-2"></i>Add Requirement</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Description</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($requirements_data as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['requirement_number']); ?></td>
                                <td><?php echo htmlspecialchars($req['description']); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editRequirementModal<?php echo $req['id']; ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteRequirementModal<?php echo $req['id']; ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: // BADGES VIEW ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="fw-bold"><i class="fas fa-medal me-3"></i>Manage Merit Badges</h1>
                    <div class="d-flex gap-3">
                        <form method="GET" id="filterForm" class="d-flex gap-2">
                            <select name="type" class="form-select bg-transparent text-white" style="border-color: rgba(255,255,255,0.3); width: 180px;" onchange="document.getElementById('filterForm').submit()">
                                <option value="all" <?php if($filter_type == 'all') echo 'selected'; ?> class="text-white bg-dark">All Types</option>
                                <option value="boy_scout" <?php if($filter_type == 'boy_scout') echo 'selected'; ?> class="text-white bg-dark">Boy Scout</option>
                                <option value="outfit_scout" <?php if($filter_type == 'outfit_scout') echo 'selected'; ?> class="text-white bg-dark">Outfit Scout</option>
                            </select>
                            <select name="filter" class="form-select bg-transparent text-white" style="border-color: rgba(255,255,255,0.3); width: 180px;" onchange="document.getElementById('filterForm').submit()">
                                <option value="all" <?php if(!$filter_eagle) echo 'selected'; ?> class="text-white bg-dark">All Badges</option>
                                <option value="eagle" <?php if($filter_eagle) echo 'selected'; ?> class="text-white bg-dark">Eagle Required</option>
                            </select>
                        </form>
                        <div class="position-relative" style="width: 250px;">
                            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50"></i>
                            <input type="text" id="badgeSearch" class="form-control bg-transparent text-white ps-5" placeholder="Search badges..." style="border-color: rgba(255,255,255,0.3);">
                        </div>
                        <a href="badge_approvals.php" class="btn btn-warning"><i class="fas fa-user-check me-2"></i>Approvals</a>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBadgeModal"><i class="fas fa-plus me-2"></i>Add New Badge</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Icon</th><th>Name</th><th>Scout Type</th><th>Eagle Required</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($badges_data as $badge): ?>
                            <tr class="badge-row">
                                <td><img src="<?php echo htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png'); ?>" class="badge-icon-sm"></td>
                                <td class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></td>
                                <td>
                                    <?php 
                                    $type = isset($badge['scout_type']) ? $badge['scout_type'] : 'boy_scout';
                                    if ($type == 'boy_scout') {
                                        echo '<span class="badge bg-primary">Boy Scout</span>';
                                    } else {
                                        echo '<span class="badge bg-info">Outfit Scout</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $badge['is_eagle_required'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?></td>
                                <td>
                                    <a href="?view_reqs_for=<?php echo $badge['id']; ?>" class="btn btn-info btn-sm" title="Manage Requirements"><i class="fas fa-list-ol"></i></a>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editBadgeModal<?php echo $badge['id']; ?>" title="Edit Badge"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteBadgeModal<?php echo $badge['id']; ?>" title="Delete Badge"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Badge Modals -->
<div class="modal fade" id="addBadgeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Merit Badge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scout Type</label>
                        <select name="scout_type" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                            <option value="" style="background-color: #1a1a1a; color: white;">-- Select Scout Type --</option>
                            <option value="boy_scout" style="background-color: #1a1a1a; color: white;">Boy Scout</option>
                            <option value="outfit_scout" style="background-color: #1a1a1a; color: white;">Outfit Scout</option>
                        </select>
                        <small class="text-white-50 d-block mt-1">Choose whether this badge is for Boy Scouts or Outfit Scouts</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon</label>
                        <input type="file" name="icon_path" class="form-control" accept="image/*">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_eagle_required" value="1" id="add_eagle_req">
                        <label class="form-check-label" for="add_eagle_req">Eagle Required</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                    <button type="submit" name="add_badge" class="btn btn-success"><i class="fas fa-plus me-2"></i>Add Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($badges_data as $badge): ?>
<div class="modal fade" id="editBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Merit Badge</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($badge['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($badge['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scout Type</label>
                        <select name="scout_type" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                            <option value="boy_scout" <?php if(isset($badge['scout_type']) && $badge['scout_type'] == 'boy_scout') echo 'selected'; ?> style="background-color: #1a1a1a; color: white;">Boy Scout</option>
                            <option value="outfit_scout" <?php if(isset($badge['scout_type']) && $badge['scout_type'] == 'outfit_scout') echo 'selected'; ?> style="background-color: #1a1a1a; color: white;">Outfit Scout</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Icon (optional)</label>
                        <input type="file" name="icon_path" class="form-control" accept="image/*">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_eagle_required" value="1" id="edit_eagle_req<?php echo $badge['id']; ?>" <?php if($badge['is_eagle_required']) echo 'checked'; ?>>
                        <label class="form-check-label" for="edit_eagle_req<?php echo $badge['id']; ?>">Eagle Required</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                    <button type="submit" name="update_badge" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the "<?php echo htmlspecialchars($badge['name']); ?>" merit badge?</p>
                    <p class="text-danger">This will also delete all of its requirements and all scout progress associated with it. This action cannot be undone.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button><button type="submit" name="delete_badge" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Badge</button></div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Requirement Modals (only rendered in requirement view) -->
<?php if ($view_mode == 'list_reqs'): ?>
<div class="modal fade" id="addRequirementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="badge_id" value="<?php echo $badge_data['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Add New Requirement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Requirement Number (e.g., 1a, 2, 3c)</label><input type="text" name="requirement_number" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button><button type="submit" name="add_requirement" class="btn btn-success"><i class="fas fa-plus me-2"></i>Add Requirement</button></div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($requirements_data as $req): ?>
<div class="modal fade" id="editRequirementModal<?php echo $req['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="badge_id" value="<?php echo $badge_data['id']; ?>">
                <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Edit Requirement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Requirement Number</label><input type="text" name="requirement_number" class="form-control" value="<?php echo htmlspecialchars($req['requirement_number']); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($req['description']); ?></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button><button type="submit" name="update_requirement" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteRequirementModal<?php echo $req['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="badge_id" value="<?php echo $badge_data['id']; ?>">
                <input type="hidden" name="requirement_id" value="<?php echo $req['id']; ?>">
                <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Are you sure you want to delete requirement #<?php echo htmlspecialchars($req['requirement_number']); ?>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button><button type="submit" name="delete_requirement" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Requirement</button></div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const searchInput = document.getElementById('badgeSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.badge-row');

            rows.forEach(row => {
                const name = row.querySelector('.badge-name').textContent.toLowerCase();
                row.style.display = name.includes(searchValue) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>