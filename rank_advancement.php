<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: login.php');
    exit();
}

$message = '';

// Handle Promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_scout'])) {
    $scout_id = intval($_POST['scout_id']);
    $new_rank_id = intval($_POST['new_rank_id']);
    
    // Fetch scout details for logging
    $stmt = mysqli_prepare($conn, "SELECT name, rank_id FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $scout_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $scout = mysqli_fetch_assoc($res);
    
    if ($scout) {
        // Update rank
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET rank_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "ii", $new_rank_id, $scout_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $message = "<div class='alert alert-success'>Successfully promoted " . htmlspecialchars($scout['name']) . "!</div>";
            
            // Log activity
            $action = "Promoted scout " . $scout['name'] . " to rank ID " . $new_rank_id;
            $log_query = "INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())";
            $log_stmt = mysqli_prepare($conn, $log_query);
            if ($log_stmt) {
                $uid = $_SESSION['user_id'];
                mysqli_stmt_bind_param($log_stmt, "is", $uid, $action);
                mysqli_stmt_execute($log_stmt);
            }
        } else {
            $message = "<div class='alert alert-danger'>Error updating rank.</div>";
        }
    }
}

// Fetch Ranks (with scout_type)
$ranks = [];
$r_query = mysqli_query($conn, "SELECT id, rank_name, scout_type FROM ranks ORDER BY scout_type ASC, id ASC");
if ($r_query) {
    while ($row = mysqli_fetch_assoc($r_query)) {
        $ranks[] = $row;
    }
}

// Fetch Scouts
$where_clause = "WHERE role = 'scout'";
if ($_SESSION['role'] == 'scout_leader') {
    // Filter for leader's troops
    $where_clause .= " AND troop_id IN (SELECT id FROM troops WHERE scout_leader_id = " . intval($_SESSION['user_id']) . ")";
}

$scouts_query = "
    SELECT u.id, u.name, u.school, u.rank_id, u.scout_type, r.rank_name 
    FROM users u 
    LEFT JOIN ranks r ON u.rank_id = r.id 
    $where_clause 
    ORDER BY u.name ASC
";
$scouts_result = mysqli_query($conn, $scouts_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rank Advancement</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
        .wrapper { display: flex; min-height: 100vh; }
        .main { flex: 1; margin-left: 240px; padding: 30px; transition: all 0.3s; }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(12px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.15); }
        .table { color: white !important; --bs-table-color: white; --bs-table-hover-color: white; }
        .table tbody td { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); vertical-align: middle; color: white; }
        .table thead th { background: rgba(0,0,0,0.3); border-bottom: 2px solid rgba(255,255,255,0.1); color: white; }
        .table-hover tbody tr:hover td { color: white; background-color: rgba(255,255,255,0.15); }
        .form-select { background: rgba(0,0,0,0.5); color: white; border: 1px solid rgba(255,255,255,0.2); }
        .form-select:focus { background: rgba(0,0,0,0.7); color: white; }
        option { background-color: #333; color: white; }
        @media (max-width: 768px) { .main { margin-left: 0; } }
        .wrapper.toggled .main { margin-left: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'navbar.php'; ?>
        <div class="glass">
            <h2 class="mb-4"><i class="fas fa-medal text-warning me-2"></i>Rank Advancement</h2>
            <?php echo $message; ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Scout Name</th>
                            <th>School</th>
                            <th>Scout Type</th>
                            <th>Current Rank</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($scouts_result && mysqli_num_rows($scouts_result) > 0): ?>
                            <?php while ($scout = mysqli_fetch_assoc($scouts_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                <td><?php echo htmlspecialchars($scout['school']); ?></td>
                                <td>
                                    <?php if (!empty($scout['scout_type'])): ?>
                                        <?php if ($scout['scout_type'] === 'boy_scout'): ?>
                                            <span class="badge" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.25), rgba(255, 152, 0, 0.15)); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107;">Boy Scout</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.25), rgba(255, 152, 0, 0.15)); border: 1px solid rgba(255, 193, 7, 0.4); color: #ffc107;">Outfit Scout</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($scout['rank_name'] ?? 'Unranked'); ?></span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="scout_id" value="<?php echo $scout['id']; ?>">
                                        <select name="new_rank_id" class="form-select form-select-sm" required style="width: 200px;">
                                            <option value="">Select Rank...</option>
                                            <?php 
                                            $scout_type = $scout['scout_type'] ?? '';
                                            foreach ($ranks as $rank): 
                                                // Only show ranks that match the scout's type
                                                if (!empty($scout_type) && isset($rank['scout_type']) && $rank['scout_type'] !== $scout_type) {
                                                    continue;
                                                }
                                                
                                                $type_label = '';
                                                if (isset($rank['scout_type'])) {
                                                    $type_label = $rank['scout_type'] === 'boy_scout' ? ' (Boy Scout)' : ' (Outfit Scout)';
                                                }
                                            ?>
                                                <option value="<?php echo $rank['id']; ?>" <?php echo ($scout['rank_id'] == $rank['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rank['rank_name']) . $type_label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($scout_type)): ?>
                                            <button type="button" class="btn btn-warning btn-sm" disabled title="Scout type not set">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="promote_scout" class="btn btn-success btn-sm">
                                                <i class="fas fa-arrow-up"></i> Update
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center">No scouts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php include 'footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>