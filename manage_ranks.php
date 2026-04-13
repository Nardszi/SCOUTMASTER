<?php
session_start();
include('config.php');

// 1. Security & Setup
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// 2. Handle POST Actions
$redirect_url = 'manage_ranks.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rank Actions
    if (isset($_POST['add_rank'])) {
        $rank_name = mysqli_real_escape_string($conn, $_POST['rank_name']);
        $scout_type = mysqli_real_escape_string($conn, $_POST['scout_type']);
        
        $query = "INSERT INTO ranks (rank_name, scout_type) VALUES ('$rank_name', '$scout_type')";
        @mysqli_query($conn, $query);
        $new_rank_id = mysqli_insert_id($conn);
        @logActivity($conn, $_SESSION['user_id'], 'Add Rank', "Added rank '$rank_name' (ID: $new_rank_id, Type: $scout_type)");
        $_SESSION['success'] = "Rank added successfully.";
    } elseif (isset($_POST['update_rank'])) {
        $rank_id = (int)$_POST['rank_id'];
        $rank_name = mysqli_real_escape_string($conn, $_POST['rank_name']);
        $scout_type = mysqli_real_escape_string($conn, $_POST['scout_type']);
        
        $query = "UPDATE ranks SET rank_name = '$rank_name', scout_type = '$scout_type' WHERE id = $rank_id";
        @mysqli_query($conn, $query);
        @logActivity($conn, $_SESSION['user_id'], 'Update Rank', "Updated rank '$rank_name' (ID: $rank_id, Type: $scout_type)");
        $_SESSION['success'] = "Rank updated successfully.";
    } elseif (isset($_POST['delete_rank'])) {
        $rank_id = (int)$_POST['rank_id'];

        // Check if rank is assigned to users
        $check = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE rank_id = $rank_id");
        $row = mysqli_fetch_assoc($check);

        if ($row && $row['total'] > 0) {
            $_SESSION['error'] = "This rank is assigned to users. Remove or update users first.";
        } else {
            // Fetch name for logging before deleting
            $r_result = @mysqli_query($conn, "SELECT rank_name FROM ranks WHERE id = $rank_id");
            $rank_name = "ID: $rank_id";
            if ($r_result && $r_row = mysqli_fetch_assoc($r_result)) {
                $rank_name = $r_row['rank_name'];
            }

            @mysqli_query($conn, "DELETE FROM ranks WHERE id = $rank_id");
            @logActivity($conn, $_SESSION['user_id'], 'Delete Rank', "Deleted rank '$rank_name'");
            $_SESSION['success'] = "Rank deleted successfully.";
        }
    }

    header("Location: " . $redirect_url);
    exit();
}

// 3. Fetch Data for Display
$ranks_data = [];

// Filter Logic
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

$query = "SELECT * FROM ranks WHERE 1=1";
if ($filter_type !== 'all') {
    $query .= " AND scout_type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
}
$query .= " ORDER BY scout_type ASC, rank_name ASC";
$result = @mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) { 
        $ranks_data[] = $row; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scout Ranks</title>
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
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="fw-bold"><i class="fas fa-trophy me-3"></i>Manage Scout Ranks</h1>
                <div class="d-flex gap-3">
                    <form method="GET" id="filterForm" class="d-flex gap-2">
                        <select name="type" class="form-select bg-transparent text-white" style="border-color: rgba(255,255,255,0.3); width: 180px;" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" <?php if($filter_type == 'all') echo 'selected'; ?> class="text-white bg-dark">All Types</option>
                            <option value="boy_scout" <?php if($filter_type == 'boy_scout') echo 'selected'; ?> class="text-white bg-dark">Boy Scout</option>
                            <option value="outfit_scout" <?php if($filter_type == 'outfit_scout') echo 'selected'; ?> class="text-white bg-dark">Outfit Scout</option>
                        </select>
                    </form>
                    <div class="position-relative" style="width: 250px;">
                        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50"></i>
                        <input type="text" id="rankSearch" class="form-control bg-transparent text-white ps-5" placeholder="Search ranks..." style="border-color: rgba(255,255,255,0.3);">
                    </div>
                    <a href="rank_advancement.php" class="btn btn-info"><i class="fas fa-award me-2"></i>Rank Advancement</a>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRankModal"><i class="fas fa-plus me-2"></i>Add New Rank</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Rank Name</th><th>Scout Type</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($ranks_data as $rank): ?>
                        <tr class="rank-row">
                            <td class="rank-name"><?php echo htmlspecialchars($rank['rank_name']); ?></td>
                            <td>
                                <?php 
                                $type = isset($rank['scout_type']) ? $rank['scout_type'] : 'boy_scout';
                                if ($type == 'boy_scout') {
                                    echo '<span class="badge bg-primary">Boy Scout</span>';
                                } else {
                                    echo '<span class="badge bg-info">Outfit Scout</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editRankModal<?php echo $rank['id']; ?>" title="Edit Rank"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteRankModal<?php echo $rank['id']; ?>" title="Delete Rank"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Rank Modal -->
<div class="modal fade" id="addRankModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Rank</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rank Name</label>
                        <input type="text" name="rank_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scout Type</label>
                        <select name="scout_type" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                            <option value="" style="background-color: #1a1a1a; color: white;">-- Select Scout Type --</option>
                            <option value="boy_scout" style="background-color: #1a1a1a; color: white;">Boy Scout</option>
                            <option value="outfit_scout" style="background-color: #1a1a1a; color: white;">Outfit Scout</option>
                        </select>
                        <small class="text-white-50 d-block mt-1">Choose whether this rank is for Boy Scouts or Outfit Scouts</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                    <button type="submit" name="add_rank" class="btn btn-success"><i class="fas fa-plus me-2"></i>Add Rank</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit & Delete Modals -->
<?php foreach ($ranks_data as $rank): ?>
<div class="modal fade" id="editRankModal<?php echo $rank['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="rank_id" value="<?php echo $rank['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Rank</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rank Name</label>
                        <input type="text" name="rank_name" class="form-control" value="<?php echo htmlspecialchars($rank['rank_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scout Type</label>
                        <select name="scout_type" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                            <option value="boy_scout" <?php if(isset($rank['scout_type']) && $rank['scout_type'] == 'boy_scout') echo 'selected'; ?> style="background-color: #1a1a1a; color: white;">Boy Scout</option>
                            <option value="outfit_scout" <?php if(isset($rank['scout_type']) && $rank['scout_type'] == 'outfit_scout') echo 'selected'; ?> style="background-color: #1a1a1a; color: white;">Outfit Scout</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                    <button type="submit" name="update_rank" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteRankModal<?php echo $rank['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="rank_id" value="<?php echo $rank['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the rank "<?php echo htmlspecialchars($rank['rank_name']); ?>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                    <button type="submit" name="delete_rank" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Rank</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const searchInput = document.getElementById('rankSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.rank-row');

            rows.forEach(row => {
                const name = row.querySelector('.rank-name').textContent.toLowerCase();
                row.style.display = name.includes(searchValue) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>
