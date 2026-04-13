<?php
session_start();
include('config.php');

// Only Admin can view logs
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Set MySQL session timezone to Philippine Standard Time
mysqli_query($conn, "SET time_zone = '+08:00'");

// Handle Clear Logs
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_logs'])) {
    $truncate = "TRUNCATE TABLE activity_logs";
    if (mysqli_query($conn, $truncate)) {
        $_SESSION['success'] = "Activity logs cleared successfully.";
    } else {
        $_SESSION['error'] = "Error clearing logs: " . mysqli_error($conn);
    }
    header("Location: activity_log.php");
    exit();
}

// Pagination Configuration
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Search Logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';

$where_clauses = [];
$search_condition = "";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(u.name LIKE '%$search_escaped%' OR l.action LIKE '%$search_escaped%' OR l.details LIKE '%$search_escaped%')";
}
if (!empty($start_date)) {
    $start_date_escaped = mysqli_real_escape_string($conn, $start_date);
    $where_clauses[] = "DATE(l.created_at) >= '$start_date_escaped'";
}
if (!empty($end_date)) {
    $end_date_escaped = mysqli_real_escape_string($conn, $end_date);
    $where_clauses[] = "DATE(l.created_at) <= '$end_date_escaped'";
}
if (!empty($role_filter)) {
    $role_escaped = mysqli_real_escape_string($conn, $role_filter);
    $where_clauses[] = "u.role = '$role_escaped'";
}
if (!empty($action_filter)) {
    $action_escaped = mysqli_real_escape_string($conn, $action_filter);
    $where_clauses[] = "l.action = '$action_escaped'";
}

if (count($where_clauses) > 0) {
    $search_condition = " WHERE " . implode(' AND ', $where_clauses);
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id" . $search_condition;
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Fetch distinct actions for filter dropdown
$actions_query = "SELECT DISTINCT action FROM activity_logs ORDER BY action ASC";
$actions_result = mysqli_query($conn, $actions_query);
$actions_list = [];
while ($act_row = mysqli_fetch_assoc($actions_result)) {
    $actions_list[] = $act_row['action'];
}

// Fetch Logs with Limit
$query = "
    SELECT l.*, u.name as user_name, u.role as user_role, u.profile_picture,
           DATE_FORMAT(CONVERT_TZ(l.created_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') as created_at_manila
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    $search_condition
    ORDER BY l.created_at DESC 
    LIMIT $offset, $limit
";
$result = mysqli_query($conn, $query);

// Fetch data for the chart (respecting filters)
$chart_query = "
    SELECT DATE_FORMAT(l.created_at, '%Y-%m-%d') as activity_date, COUNT(l.id) as activity_count
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    $search_condition
    GROUP BY activity_date
    ORDER BY activity_date ASC
";
$chart_result = mysqli_query($conn, $chart_query);
$chart_labels = [];
$chart_data = [];
if ($chart_result) {
    while ($row = mysqli_fetch_assoc($chart_result)) {
        $chart_labels[] = date('M j', strtotime($row['activity_date']));
        $chart_data[] = $row['activity_count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activity Log</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include('favicon_header.php'); ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
    body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; font-family:'Segoe UI',sans-serif; }
    body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
    .wrapper { display: flex; min-height: 100vh; }
    .main { flex: 1; margin-left: 240px; padding: 30px; transition: margin-left 0.3s; }
    body.sidebar-collapsed .main { margin-left: 80px; }
    .glass {
        background: rgba(139, 139, 139, 0.5);
        backdrop-filter: blur(15px);
        border-radius: 20px;
        padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        color: white;
    }
    .table { color: white !important; vertical-align: middle; }
    .table thead th { background-color: rgba(0, 0, 0, 0.4); color: #fff !important; border-bottom: 2px solid rgba(255, 255, 255, 0.1); font-weight: 600; }
    .table tbody td { background-color: rgba(255, 255, 255, 0.02); border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #fff !important; }
    .table-hover tbody tr:hover td { background-color: rgba(255, 255, 255, 0.1); color: white !important; }
    .table tbody td * { color: inherit !important; }
    .table a { color: #fff !important; text-decoration: underline; }
    .table a:hover { color: #ddd !important; }
    .user-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
    .badge-role { font-size: 0.7em; opacity: 0.8; }

    /* Print Styles */
    @media print {
        .sidebar, .navbar, .btn, form, .pagination { display: none !important; }
        .main { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
        .wrapper { display: block !important; }
        body { background: white !important; color: black !important; }
        .glass { background: none !important; border: none !important; box-shadow: none !important; color: black !important; padding: 0 !important; }
        .table { color: black !important; }
        .table th { background-color: #f0f0f0 !important; color: black !important; border: 1px solid #000 !important; }
        .table td { color: black !important; border: 1px solid #000 !important; }
        .badge { border: 1px solid #000; color: black !important; background: none !important; }
    }
</style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <?php include('navbar.php'); ?>
        
        <div class="glass mb-4">
            <h4 class="mb-3"><i class="bi bi-bar-chart-line-fill me-2"></i>Activity Overview</h4>
            <div style="max-width: 800px; margin: 0 auto;">
                <canvas id="activityChart" style="height: 150px;"></canvas>
            </div>
        </div>

        <div class="glass">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-clock-history me-2"></i>Activity Log</h2>
                <div>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to clear all activity logs? This action cannot be undone.');" class="d-inline">
                        <button type="submit" name="clear_logs" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i> Clear Logs</button>
                    </form>
                </div>
            </div>
            
            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($start_date) ?>" title="Start Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($end_date) ?>" title="End Date">
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-select bg-dark text-white border-secondary">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="scout_leader" <?= $role_filter == 'scout_leader' ? 'selected' : '' ?>>Scout Leader</option>
                            <option value="scout" <?= $role_filter == 'scout' ? 'selected' : '' ?>>Scout</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="action_filter" class="form-select bg-dark text-white border-secondary">
                            <option value="">All Actions</option>
                            <?php foreach ($actions_list as $act): ?>
                                <option value="<?= htmlspecialchars($act) ?>" <?= $action_filter == $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2 d-flex">
                        <button class="btn btn-primary me-1" type="submit"><i class="bi bi-search"></i></button>
                        <select name="limit" class="form-select bg-dark text-white border-secondary me-1" style="width: auto;" onchange="this.form.submit()">
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <?php if(!empty($search) || !empty($start_date) || !empty($end_date) || !empty($role_filter) || !empty($action_filter)): ?>
                            <a href="activity_log.php" class="btn btn-secondary"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if (isset($_SESSION['success'])) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= !empty($row['profile_picture']) ? htmlspecialchars($row['profile_picture']) : 'images/default_profile.png' ?>" class="user-avatar">
                                            <div>
                                                <?php
                                                    $userName = 'System';
                                                    $userLink = false;

                                                    if (!empty($row['user_id'])) {
                                                        if (!empty($row['user_name'])) {
                                                            $userName = htmlspecialchars($row['user_name']);
                                                            $userLink = 'edit_scout.php?id=' . $row['user_id'];
                                                        } else {
                                                            $userName = 'Deleted User (ID: ' . $row['user_id'] . ')';
                                                        }
                                                    }
                                                ?>
                                                <div>
                                                    <?php if ($userLink): ?>
                                                        <a href="<?= $userLink ?>" class="text-white" style="text-decoration: none;"><?= $userName ?></a>
                                                    <?php else: ?>
                                                        <?= $userName ?>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-secondary badge-role"><?= ucfirst($row['user_role'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-info';
                                            if(stripos($row['action'], 'delete') !== false || stripos($row['action'], 'reject') !== false || stripos($row['action'], 'remove') !== false) $badgeClass = 'bg-danger';
                                            elseif(stripos($row['action'], 'create') !== false || stripos($row['action'], 'approve') !== false || stripos($row['action'], 'add') !== false || $row['action'] === 'Login') $badgeClass = 'bg-success';
                                            elseif(stripos($row['action'], 'update') !== false || stripos($row['action'], 'edit') !== false) $badgeClass = 'bg-warning text-dark';
                                            elseif($row['action'] === 'Logout') $badgeClass = 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['action']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['details']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($row['created_at_manila'] ?? $row['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">No activity recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&role=<?= urlencode($role_filter) ?>&action_filter=<?= urlencode($action_filter) ?>">Previous</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link bg-dark text-white border-secondary">Page <?= $page ?> of <?= $total_pages ?></span></li>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&role=<?= urlencode($role_filter) ?>&action_filter=<?= urlencode($action_filter) ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        
        <?php include('footer.php'); ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('activityChart').getContext('2d');
    if (ctx) {
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Activities per Day',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        display: false,
                        beginAtZero: true
                    },
                    x: {
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
