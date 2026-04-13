<?php
session_start();
include 'config.php';

// Only Admin can view reports
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// --- Build filter conditions from GET (used for all report queries) ---
$filter_role = isset($_GET['user_role']) && $_GET['user_role'] !== '' ? mysqli_real_escape_string($conn, $_GET['user_role']) : null;
$filter_school = isset($_GET['school']) && trim($_GET['school']) !== '' ? mysqli_real_escape_string($conn, trim($_GET['school'])) : null;
$filter_rank = isset($_GET['rank']) && trim($_GET['rank']) !== '' ? mysqli_real_escape_string($conn, trim($_GET['rank'])) : null;
$filter_date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? mysqli_real_escape_string($conn, $_GET['date_from']) . ' 00:00:00' : null;
$filter_date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? mysqli_real_escape_string($conn, $_GET['date_to']) . ' 23:59:59' : null;
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? mysqli_real_escape_string($conn, $_GET['status']) : null;

$user_where = ["(u.is_archived = 0 OR u.is_archived IS NULL)"];
if ($filter_role) $user_where[] = "u.role = '$filter_role'";
if ($filter_school) $user_where[] = "u.school LIKE '%$filter_school%'";
if ($filter_date_from) $user_where[] = "u.created_at >= '$filter_date_from'";
if ($filter_date_to) $user_where[] = "u.created_at <= '$filter_date_to'";
if ($filter_status === 'approved') $user_where[] = "u.approved = 1";
if ($filter_status === 'pending') $user_where[] = "u.approved = 0";

$user_where_sql = implode(' AND ', $user_where);

// For rank filter we need JOIN with ranks
$rank_join = '';
if ($filter_rank) {
    $rank_join = " LEFT JOIN ranks r_f ON u.rank_id = r_f.id ";
    $user_where[] = "(r_f.rank_name LIKE '%$filter_rank%' OR (u.rank_id IS NULL AND '$filter_rank' = 'Unranked'))";
    $user_where_sql = implode(' AND ', $user_where);
}

// Get user and school counts (filtered)
$user_count = 0;
$user_result = mysqli_query($conn, "SELECT COUNT(*) total FROM users u $rank_join WHERE $user_where_sql");
if ($user_result) { $user_count = mysqli_fetch_assoc($user_result)['total'] ?? 0; }

$school_count = 0;
$school_result = mysqli_query($conn, "SELECT COUNT(DISTINCT u.school) AS total FROM users u $rank_join WHERE $user_where_sql AND u.school IS NOT NULL AND u.school != '' AND u.school != 'N/A'");
if ($school_result && ($row = mysqli_fetch_assoc($school_result))) { $school_count = $row['total'] ?? 0; }

// Scout Registrations summary (filtered)
$scout_reg_count = 0;
$scout_reg_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users u $rank_join WHERE $user_where_sql AND u.role = 'scout'");
if ($scout_reg_result) { $scout_reg_count = mysqli_fetch_assoc($scout_reg_result)['total'] ?? 0; }

$scout_list = [];
$recent_scouts = mysqli_query($conn, "SELECT u.name, u.school, u.created_at, u.approved FROM users u $rank_join WHERE $user_where_sql AND u.role = 'scout' ORDER BY u.created_at DESC LIMIT 50");
if ($recent_scouts) {
    while ($row = mysqli_fetch_assoc($recent_scouts)) { $scout_list[] = $row; }
}

// Additional metrics (filtered)
$active_scouts = 0;
$active_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users u $rank_join WHERE $user_where_sql AND u.role='scout' AND u.status='active'");
if ($active_query) { $active_scouts = mysqli_fetch_assoc($active_query)['total'] ?? 0; }

$rank_advancements = 0;
$month_start = date('Y-m-01 00:00:00');
$ra_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM activity_logs WHERE action LIKE 'Promoted scout%' AND created_at >= '$month_start'");
if ($ra_query) { $rank_advancements = mysqli_fetch_assoc($ra_query)['total'] ?? 0; }

// Badge Progress summary
$badge_completed = 0;
$badge_completion_query = mysqli_query($conn, "SELECT COUNT(DISTINCT scout_badge_progress_id) as completed FROM scout_requirement_progress WHERE date_approved IS NOT NULL");
if ($badge_completion_query) { $badge_completed = mysqli_fetch_assoc($badge_completion_query)['completed'] ?? 0; }
$badges_completed_total = $badge_completed;

// prepare monthly signups for past year (filtered)
$monthly_labels = [];
$monthly_data = [];
$month_query = mysqli_query($conn, "SELECT DATE_FORMAT(u.created_at, '%Y-%m') AS month, COUNT(*) AS cnt FROM users u $rank_join WHERE $user_where_sql GROUP BY month ORDER BY month DESC LIMIT 12");
if ($month_query) {
    $temp = [];
    while ($mrow = mysqli_fetch_assoc($month_query)) {
        $temp[] = $mrow;
    }
    $temp = array_reverse($temp);
    foreach ($temp as $m) {
        $monthly_labels[] = $m['month'];
        $monthly_data[] = $m['cnt'];
    }
}

// prepare role distribution (filtered)
$role_labels = [];
$role_data = [];
$role_query = mysqli_query($conn, "SELECT u.role, COUNT(*) AS cnt FROM users u $rank_join WHERE $user_where_sql GROUP BY u.role");
if ($role_query) {
    while ($r = mysqli_fetch_assoc($role_query)) {
        $role_labels[] = ucfirst($r['role']);
        $role_data[] = $r['cnt'];
    }
}

// prepare rank distribution (filtered)
$rank_labels = [];
$rank_data = [];
$rank_query = mysqli_query($conn, "SELECT COALESCE(r.rank_name, 'Unranked') as rname, COUNT(u.id) as cnt FROM users u LEFT JOIN ranks r ON u.rank_id = r.id $rank_join WHERE $user_where_sql AND u.role = 'scout' GROUP BY rname ORDER BY cnt DESC");
if ($rank_query) {
    while ($row = mysqli_fetch_assoc($rank_query)) {
        $rank_labels[] = $row['rname'];
        $rank_data[] = $row['cnt'];
    }
}

// Build subquery of filtered user IDs for badge/attendance/payment
$filtered_user_ids_sql = "SELECT u.id FROM users u $rank_join WHERE $user_where_sql";
$has_user_filter = $filter_role || $filter_school || $filter_rank || $filter_date_from || $filter_date_to || $filter_status;

// prepare badge completion stats (filter by scouts if filter active)
$badge_labels = [];
$badge_data = [];
$badge_where = "sbp.status = 'completed'";
if ($has_user_filter) $badge_where .= " AND sbp.scout_id IN ($filtered_user_ids_sql)";
$badge_stat_query = mysqli_query($conn, "SELECT mb.name, COUNT(sbp.id) as cnt FROM scout_badge_progress sbp JOIN merit_badges mb ON sbp.merit_badge_id = mb.id WHERE $badge_where GROUP BY mb.name ORDER BY cnt DESC LIMIT 10");
if ($badge_stat_query) {
    while ($row = mysqli_fetch_assoc($badge_stat_query)) {
        $badge_labels[] = $row['name'];
        $badge_data[] = $row['cnt'];
    }
}

// prepare attendance stats (filter by scouts if filter active)
$attendance_labels = [];
$attendance_data = [];
if ($has_user_filter) {
    $att_query2 = mysqli_query($conn, "SELECT e.id, e.event_title, e.event_date FROM events e WHERE e.status = 'approved' ORDER BY e.event_date DESC LIMIT 10");
    if ($att_query2) {
        while ($erow = mysqli_fetch_assoc($att_query2)) {
            $eid = (int)$erow['id'];
            $cnt_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM event_attendance ea WHERE ea.event_id = $eid AND ea.scout_id IN ($filtered_user_ids_sql)");
            $cnt = 0;
            if ($cnt_result && $crow = mysqli_fetch_assoc($cnt_result)) $cnt = (int)$crow['cnt'];
            $attendance_labels[] = $erow['event_title'];
            $attendance_data[] = $cnt;
        }
    }
} else {
    $att_query = mysqli_query($conn, "SELECT e.event_title, COUNT(ea.scout_id) as cnt FROM events e LEFT JOIN event_attendance ea ON e.id = ea.event_id WHERE e.status = 'approved' GROUP BY e.id ORDER BY e.event_date DESC LIMIT 10");
    if ($att_query) {
        while ($row = mysqli_fetch_assoc($att_query)) {
            $attendance_labels[] = $row['event_title'];
            $attendance_data[] = $row['cnt'];
        }
    }
}

// prepare payment stats (filter by user_id if filter active)
$payment_labels = ['Paid', 'Unpaid'];
$payment_data = [0, 0];
$pay_where = "1=1";
if ($has_user_filter) $pay_where = "sp.user_id IN ($filtered_user_ids_sql)";
$pay_query = mysqli_query($conn, "SELECT sp.paid_status, COUNT(*) as cnt FROM scout_profiles sp WHERE $pay_where GROUP BY sp.paid_status");
if ($pay_query) {
    while ($row = mysqli_fetch_assoc($pay_query)) {
        if (strcasecmp($row['paid_status'], 'Paid') === 0) {
            $payment_data[0] = (int)$row['cnt'];
        } else {
            $payment_data[1] += (int)$row['cnt'];
        }
    }
}

// Define chart data for summaryChart
$chart_labels = ['Total Users', 'Total Schools', 'Active Scouts', 'Badges Completed'];
$chart_data = [$user_count, $school_count, $active_scouts, $badge_completed];

// Export URL query string (pass current filters)
$export_query = http_build_query(array_filter([
    'user_role' => $filter_role ?? null,
    'school' => $filter_school ?? null,
    'rank' => $filter_rank ?? null,
    'date_from' => $filter_date_from ? substr($filter_date_from, 0, 10) : null,
    'date_to' => $filter_date_to ? substr($filter_date_to, 0, 10) : null,
    'status' => $filter_status ?? null,
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - Boy Scout System</title>
    <?php include('favicon_header.php'); ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background: url("images/wall3.jpg") no-repeat center center/cover fixed;
            color: white;
            font-family: 'Segoe UI', sans-serif;
        }
        
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: -1;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .main {
            flex: 1;
            margin-left: 240px;
            padding: 30px;
            transition: margin-left 0.3s;
        }
        
        body.sidebar-collapsed .main {
            margin-left: 80px;
        }
        
        .glass {
            background: rgba(139, 139, 139, 0.5);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            color: white;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .stat-card .icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 2rem;
        }
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-card p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .stat-card .trend {
            font-size: 0.85rem;
            margin-top: 10px;
        }
        
        .table {
            color: white !important;
            vertical-align: middle;
        }
        
        .table thead th {
            background-color: rgba(0, 0, 0, 0.4);
            color: #fff !important;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            font-weight: 600;
        }
        
        .table tbody td {
            background-color: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #fff !important;
        }
        
        .table-hover tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff !important;
        }
        
        .table tbody td * {
            color: inherit !important;
        }
        
        .text-muted {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        
        .nav-tabs {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border: none;
            padding: 12px 20px;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px 10px 0 0;
        }
        
        .nav-tabs .nav-link.active {
            color: #28a745;
            background: rgba(40, 167, 69, 0.2);
            border-bottom: 3px solid #28a745;
            border-radius: 10px 10px 0 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        @media print {
            .sidebar, .navbar, .btn, form.filter-form, .no-print {
                display: none !important;
            }
            .main {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .glass {
                background: white !important;
                color: black !important;
                box-shadow: none !important;
            }
            .table {
                color: black !important;
            }
        }
        
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 15px;
            }
            .glass {
                padding: 20px;
            }
            .stat-card {
                margin-bottom: 15px;
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
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                <div>
                    <h2 class="mb-2"><i class="fas fa-chart-line me-2"></i>Admin Reports</h2>
                    <p class="text-white-50 mb-0">View all reports and statistics for the Boy Scout system.</p>
                </div>
                <div class="no-print">
                    <a href="export_reports.php?format=pdf&<?php echo $export_query; ?>" class="btn btn-danger btn-sm me-2" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <form method="get" action="admin_reports.php" class="filter-form no-print">
                <div class="row g-2 mb-4">
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small">Role</label>
                        <select name="user_role" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">All Roles</option>
                            <option value="scout" <?php echo ($filter_role === 'scout') ? 'selected' : ''; ?>>Scout</option>
                            <option value="scout_leader" <?php echo ($filter_role === 'scout_leader') ? 'selected' : ''; ?>>Scout Leader</option>
                            <option value="admin" <?php echo ($filter_role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small">School</label>
                        <input type="text" name="school" placeholder="School name" value="<?php echo htmlspecialchars($filter_school ?? ''); ?>" class="form-control form-control-sm bg-dark text-white border-secondary">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small">Rank</label>
                        <input type="text" name="rank" placeholder="Rank name" value="<?php echo htmlspecialchars($filter_rank ?? ''); ?>" class="form-control form-control-sm bg-dark text-white border-secondary">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small">From</label>
                        <input type="date" name="date_from" value="<?php echo $filter_date_from ? substr($filter_date_from, 0, 10) : ''; ?>" class="form-control form-control-sm bg-dark text-white border-secondary">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small">To</label>
                        <input type="date" name="date_to" value="<?php echo $filter_date_to ? substr($filter_date_to, 0, 10) : ''; ?>" class="form-control form-control-sm bg-dark text-white border-secondary">
                    </div>
                    <div class="col-md-1 col-sm-6">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">All</option>
                            <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-1 col-sm-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-success btn-sm me-1 w-100">Apply</button>
                    </div>
                </div>
                <?php if($filter_role || $filter_school || $filter_rank || $filter_date_from || $filter_date_to || $filter_status): ?>
                <div class="mb-3">
                    <a href="admin_reports.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i> Reset Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
            
            <!-- Export Buttons -->

            
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                    <div class="stat-card">
                        <div class="icon" style="background: rgba(59, 130, 246, 0.35);">
                            <i class="fas fa-users" style="color: #60a5fa; font-size:2rem;"></i>
                        </div>
                        <p>Total Registered Scouts</p>
                        <h3><?php echo $scout_reg_count; ?></h3>
                        <div class="trend text-success">
                            <i class="fas fa-arrow-up"></i> +5%
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                    <div class="stat-card">
                        <div class="icon" style="background: rgba(34, 197, 94, 0.35);">
                            <i class="fas fa-user-check" style="color: #4ade80; font-size:2rem;"></i>
                        </div>
                        <p>Active Scouts</p>
                        <h3><?php echo $active_scouts; ?></h3>
                        <div class="trend text-success">
                            <i class="fas fa-arrow-up"></i> +3%
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                    <div class="stat-card">
                        <div class="icon" style="background: rgba(251, 191, 36, 0.35);">
                            <i class="fas fa-trophy" style="color: #fcd34d; font-size:2rem;"></i>
                        </div>
                        <p>Rank Advancement (This Month)</p>
                        <h3><?php echo $rank_advancements; ?></h3>
                        <div class="trend text-danger">
                            <i class="fas fa-arrow-down"></i> -1%
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                    <div class="stat-card">
                        <div class="icon" style="background: rgba(168, 85, 247, 0.35);">
                            <i class="fas fa-award" style="color: #c084fc; font-size:2rem;"></i>
                        </div>
                        <p>Badges Completed</p>
                        <h3><?php echo $badges_completed_total; ?></h3>
                        <div class="trend text-success">
                            <i class="fas fa-arrow-up"></i> +8%
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="reportTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="reg-tab" data-bs-toggle="tab" data-bs-target="#registration" type="button" role="tab">
                        Registration Report
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rank-tab" data-bs-toggle="tab" data-bs-target="#rank" type="button" role="tab">
                        Rank Advancement
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="badge-tab" data-bs-toggle="tab" data-bs-target="#badge" type="button" role="tab">
                        Badge Completion
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="att-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                        Attendance Report
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pay-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">
                        Payment Summary
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="reportTabContent">
                <!-- Registration Report Tab -->
                <div class="tab-pane fade show active" id="registration" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6 mb-3">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Monthly Signups (Last 12 Months)</h5>
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>User Role Distribution</h5>
                            <div class="chart-container">
                                <canvas id="roleChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-table me-2"></i>Recent Scout Registrations</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>School</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($scout_list) > 0): ?>
                                    <?php foreach ($scout_list as $scout): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                            <td><?php echo htmlspecialchars($scout['school'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($scout['created_at'])); ?></td>
                                            <td>
                                                <?php if ($scout['approved']): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted">No scout registrations found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Rank Advancement Tab -->
                <div class="tab-pane fade" id="rank" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-8 mb-3">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Rank Distribution</h5>
                            <div class="chart-container">
                                <canvas id="rankChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stat-card">
                                <div class="icon" style="background: rgba(251, 191, 36, 0.2);">
                                    <i class="fas fa-trophy" style="color: #fbbf24;"></i>
                                </div>
                                <p>Total Rank Advancements</p>
                                <h3><?php echo $rank_advancements; ?></h3>
                                <p class="text-white-50 small mb-0">This Month</p>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-table me-2"></i>Rank Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rank Name</th>
                                    <th>Number of Scouts</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_scouts_with_rank = array_sum($rank_data);
                                if (count($rank_labels) > 0): 
                                    foreach ($rank_labels as $idx => $rank_name): 
                                        $count = $rank_data[$idx];
                                        $percentage = $total_scouts_with_rank > 0 ? round(($count / $total_scouts_with_rank) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rank_name); ?></td>
                                        <td><?php echo $count; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach; 
                                else: 
                                ?>
                                    <tr><td colspan="3" class="text-center text-muted">No rank data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Badge Completion Tab -->
                <div class="tab-pane fade" id="badge" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-8 mb-3">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Top 10 Completed Badges</h5>
                            <div class="chart-container">
                                <canvas id="badgeChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stat-card">
                                <div class="icon" style="background: rgba(168, 85, 247, 0.2);">
                                    <i class="fas fa-award" style="color: #a855f7;"></i>
                                </div>
                                <p>Total Badges Completed</p>
                                <h3><?php echo $badges_completed_total; ?></h3>
                                <p class="text-white-50 small mb-0">All Time</p>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-table me-2"></i>Badge Completion Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Badge Name</th>
                                    <th>Completions</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($badge_labels) > 0): ?>
                                    <?php 
                                    $max_badge = max($badge_data);
                                    foreach ($badge_labels as $idx => $badge_name): 
                                        $count = $badge_data[$idx];
                                        $percentage = $max_badge > 0 ? round(($count / $max_badge) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($badge_name); ?></td>
                                            <td><?php echo $count; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-purple" style="width: <?php echo $percentage; ?>%; background-color: #a855f7;" role="progressbar" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No badge completion data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Attendance Report Tab -->
                <div class="tab-pane fade" id="attendance" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Event Attendance (Last 10 Events)</h5>
                            <div class="chart-container">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-table me-2"></i>Attendance Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Attendees</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($attendance_labels) > 0): ?>
                                    <?php foreach ($attendance_labels as $idx => $event_name): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event_name); ?></td>
                                            <td><?php echo $attendance_data[$idx]; ?></td>
                                            <td>
                                                <?php if ($attendance_data[$idx] > 20): ?>
                                                    <span class="badge bg-success">High Attendance</span>
                                                <?php elseif ($attendance_data[$idx] > 10): ?>
                                                    <span class="badge bg-info">Moderate</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Low</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">No attendance data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Payment Summary Tab -->
                <div class="tab-pane fade" id="payment" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6 mb-3">
                            <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Payment Status Distribution</h5>
                            <div class="chart-container">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="stat-card">
                                        <div class="icon" style="background: rgba(34, 197, 94, 0.2);">
                                            <i class="fas fa-check-circle" style="color: #22c55e;"></i>
                                        </div>
                                        <p>Paid Scouts</p>
                                        <h3><?php echo $payment_data[0]; ?></h3>
                                        <p class="text-white-50 small mb-0">Total paid registrations</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="stat-card">
                                        <div class="icon" style="background: rgba(239, 68, 68, 0.2);">
                                            <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                                        </div>
                                        <p>Unpaid Scouts</p>
                                        <h3><?php echo $payment_data[1]; ?></h3>
                                        <p class="text-white-50 small mb-0">Pending payments</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-table me-2"></i>Payment Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_payments = array_sum($payment_data);
                                foreach ($payment_labels as $idx => $status): 
                                    $count = $payment_data[$idx];
                                    $percentage = $total_payments > 0 ? round(($count / $total_payments) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($status === 'Paid'): ?>
                                                <span class="badge bg-success"><?php echo $status; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?php echo $status; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $count; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?php echo $status === 'Paid' ? 'bg-success' : 'bg-danger'; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $percentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include('footer.php'); ?>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js default configuration
    Chart.defaults.color = '#fff';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
    
    // Monthly Signups Chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Monthly Signups',
                    data: <?php echo json_encode($monthly_data); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
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
                        beginAtZero: true,
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    x: {
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: 'white' }
                    }
                }
            }
        });
    }
    
    // Role Distribution Chart
    const roleCtx = document.getElementById('roleChart');
    if (roleCtx) {
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($role_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($role_data); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: 'white', padding: 15 }
                    }
                }
            }
        });
    }
    
    // Rank Distribution Chart
    const rankCtx = document.getElementById('rankChart');
    if (rankCtx) {
        new Chart(rankCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($rank_labels); ?>,
                datasets: [{
                    label: 'Number of Scouts',
                    data: <?php echo json_encode($rank_data); ?>,
                    backgroundColor: 'rgba(251, 191, 36, 0.8)',
                    borderColor: 'rgba(251, 191, 36, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    x: {
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: 'white' }
                    }
                }
            }
        });
    }
    
    // Badge Completion Chart
    const badgeCtx = document.getElementById('badgeChart');
    if (badgeCtx) {
        new Chart(badgeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($badge_labels); ?>,
                datasets: [{
                    label: 'Completions',
                    data: <?php echo json_encode($badge_data); ?>,
                    backgroundColor: 'rgba(168, 85, 247, 0.8)',
                    borderColor: 'rgba(168, 85, 247, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: 'white' }
                    }
                }
            }
        });
    }
    
    // Attendance Chart
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx) {
        new Chart(attendanceCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($attendance_labels); ?>,
                datasets: [{
                    label: 'Attendees',
                    data: <?php echo json_encode($attendance_data); ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: 'white' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    x: {
                        ticks: { 
                            color: 'white',
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: 'white' }
                    }
                }
            }
        });
    }
    
    // Payment Status Chart
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        new Chart(paymentCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($payment_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($payment_data); ?>,
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: 'white', padding: 15 }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
