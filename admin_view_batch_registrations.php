<?php
session_start();
include('config.php');

// Security: Ensure user is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
$grouped_scouts = [];
$archive_scouts = [];
$archive_scouts_flat = [];
$approved_scouts_list = [];
$approved_scouts_grouped = [];

if ($current_tab == 'pending') {
    // Fetch batch registrations (entries where email is NULL or empty)
    $query = "
        SELECT
            snr.id,
            snr.name,
            snr.registration_status,
            snr.age,
            snr.sex,
            snr.membership_card,
            snr.highest_rank,
            snr.years_in_scouting,
            snr.school,
            snr.paid_status,
            snr.created_at as registration_date,
            u.name as leader_name
        FROM scout_new_register snr
        LEFT JOIN users u ON snr.registered_by_leader_id = u.id
        WHERE snr.email IS NULL OR snr.email = ''
        ORDER BY u.name ASC, snr.created_at DESC
    ";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $leader = $row['leader_name'] ?: 'Unknown Leader';
            $grouped_scouts[$leader][] = $row;
        }
    }
} elseif ($current_tab == 'approved') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    $where_clauses = ["sra.archive_status = 'Approved'"];
    if ($start_date) {
        $where_clauses[] = "DATE(sra.archived_at) >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $where_clauses[] = "DATE(sra.archived_at) <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }
    if ($search) {
        $s = mysqli_real_escape_string($conn, $search);
        $where_clauses[] = "(sra.name LIKE '%$s%' OR sra.school LIKE '%$s%' OR u.name LIKE '%$s%')";
    }
    if ($status_filter) {
        $sf = mysqli_real_escape_string($conn, $status_filter);
        $where_clauses[] = "sra.registration_status = '$sf'";
    }

    $where_sql = "WHERE " . implode(" AND ", $where_clauses);

    $query = "
        SELECT sra.id, sra.name, sra.registration_status, sra.age, sra.sex, sra.membership_card, sra.highest_rank, sra.years_in_scouting, sra.school, sra.paid_status, sra.archived_at, sra.archive_status, sra.registered_by_leader_id, sra.archived_by, u.name as leader_name, admin.name as admin_name
        FROM admin_scout_archive sra
        LEFT JOIN users u ON sra.registered_by_leader_id = u.id 
        LEFT JOIN users admin ON sra.archived_by = admin.id
        $where_sql
        ORDER BY sra.archived_at DESC
    ";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $approved_scouts_list[] = $row;
            $leader = $row['leader_name'] ?: 'Unknown Leader';
            // Group by leader, then by approval date
            $approval_date = date('Y-m-d', strtotime($row['archived_at']));
            $approved_scouts_grouped[$leader][$approval_date][] = $row;
        }
    }
} else {
    // Fetch archived registrations
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    $where_clauses = [];
    if ($start_date) {
        $where_clauses[] = "DATE(sra.archived_at) >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
    }
    if ($end_date) {
        $where_clauses[] = "DATE(sra.archived_at) <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
    }
    if ($status_filter) {
        $where_clauses[] = "sra.archive_status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
    }

    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $query = "
        SELECT
            sra.id, sra.name, sra.registration_status, sra.age, sra.sex, sra.membership_card, sra.highest_rank, sra.years_in_scouting, sra.school, sra.paid_status, sra.archived_at, sra.archive_status, sra.registered_by_leader_id, sra.archived_by,
            u.name as leader_name,
            admin.name as admin_name
        FROM admin_scout_archive sra
        LEFT JOIN users u ON sra.registered_by_leader_id = u.id
        LEFT JOIN users admin ON sra.archived_by = admin.id
        $where_sql
        ORDER BY u.name ASC, sra.archived_at DESC
    ";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $leader = $row['leader_name'] ?: 'Unknown Leader';
            $archive_scouts[$leader][] = $row;
            $archive_scouts_flat[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Registrations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }
        .main { flex: 1; margin-left: 240px; padding: 30px; position: relative; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-collapsed .main { margin-left: 80px; }
        .main > * { position: relative; z-index: 1; }
        .glass {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.15);
            flex: 1; margin-bottom: 20px;
        }
        .table { color: white !important; --bs-table-color: white; --bs-table-hover-color: white; vertical-align: middle; }
        .table thead th { background-color: rgba(0,0,0,0.3); border-bottom: 2px solid rgba(255,255,255,0.1); }
        .table tbody td { background-color: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
        .table-hover tbody tr:hover td { background-color: rgba(255,255,255,0.15); }
        .modal-content {
            background: rgba(20, 20, 20, 0.95);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.1); }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="fw-bold"><i class="fas fa-users-cog me-3"></i>Scout Registration</h1>
                <a href="view_new_registrations.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to User Registration</a>
            </div>

            <ul class="nav nav-pills mb-4">
                <li class="nav-item"><a class="nav-link <?= $current_tab == 'pending' ? 'active bg-success' : 'text-white' ?>" href="?tab=pending">Pending Approval</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_tab == 'approved' ? 'active bg-success' : 'text-white' ?>" href="?tab=approved">Approved Scouts</a></li>
                <li class="nav-item"><a class="nav-link <?= $current_tab == 'archive' ? 'active bg-success' : 'text-white' ?>" href="?tab=archive">Archive History</a></li>
            </ul>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if ($current_tab == 'pending'): ?>
            <form action="process_batch_registration.php" method="POST" id="batchForm">
                <div class="mb-3 sticky-top p-3 rounded" style="background: rgba(0,0,0,0.8); top: 80px; z-index: 100; border: 1px solid rgba(255,255,255,0.1);">
                    <div class="d-flex gap-2 align-items-center">
                        <span class="me-2 fw-bold">Bulk Actions:</span>
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" onclick="return confirm('Approve selected scouts? Accounts will be created.')">
                            <i class="fas fa-check me-1"></i> Approve Selected
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Reject and delete selected registrations?')">
                            <i class="fas fa-times me-1"></i> Reject Selected
                        </button>
                    </div>
                </div>

                <?php if (empty($grouped_scouts)): ?>
                    <div class="alert alert-info text-center">No pending Scout registrations found.</div>
                <?php else: ?>
                    <?php foreach ($grouped_scouts as $leader => $scouts): ?>
                        <div class="card bg-transparent border-secondary mb-4">
                            <div class="card-header bg-secondary text-white fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-tie me-2"></i> Submitted by: <?= htmlspecialchars($leader) ?></span>
                                <span class="badge bg-light text-dark"><?= count($scouts) ?> Scouts</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;">
                                                    <input type="checkbox" class="form-check-input select-all-group" data-group="<?= md5($leader) ?>" title="Select all for this leader">
                                                </th>
                                                <th style="width: 180px;">Name</th>
                                                <th style="width: 80px;">Status</th>
                                                <th style="width: 60px;">Age</th>
                                                <th style="width: 60px;">Sex</th>
                                                <th style="width: 220px;">School</th>
                                                <th style="width: 140px;">Membership Card</th>
                                                <th style="width: 100px;">Rank</th>
                                                <th style="width: 80px;">Years</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scouts as $scout): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="ids[]" value="<?= $scout['id'] ?>" class="form-check-input group-item-<?= md5($leader) ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                                    <td>
                                                        <?php if($scout['registration_status'] == 'N'): ?>
                                                            <span class="badge bg-info">New</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Re-Reg</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($scout['age']); ?></td>
                                                    <td><?php echo htmlspecialchars($scout['sex']); ?></td>
                                                    <td><?php echo htmlspecialchars($scout['school'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($scout['membership_card']); ?></td>
                                                    <td><?php echo htmlspecialchars($scout['highest_rank']); ?></td>
                                                    <td><?php echo htmlspecialchars($scout['years_in_scouting']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </form>
            <?php elseif ($current_tab == 'approved'): ?>
                <!-- Approved Scouts Table -->
                <div class="card bg-transparent border-secondary mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="approved">
                            <div class="col-md-2">
                                <label class="form-label text-white-50">Start Date</label>
                                <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($start_date) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-white-50">End Date</label>
                                <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($end_date) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-white-50">Status</label>
                                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                                    <option value="">All</option>
                                    <option value="N" <?= $status_filter == 'N' ? 'selected' : '' ?>>New</option>
                                    <option value="RR" <?= $status_filter == 'RR' ? 'selected' : '' ?>>Re-Reg</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-white-50">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Name, School, Leader..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-search me-1"></i> Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($approved_scouts_grouped)): ?>
                    <div class="alert alert-info text-center">No approved scouts found matching your criteria.</div>
                <?php else: ?>
                    <?php foreach ($approved_scouts_grouped as $leader => $dates): ?>
                        <div class="card bg-transparent border-secondary mb-4">
                            <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-tie me-2"></i> Leader: <?= htmlspecialchars($leader) ?></span>
                            </div>
                            <div class="card-body">
                                <?php foreach ($dates as $date => $scouts): ?>
                                <div class="card bg-dark border-secondary mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-calendar-check me-2"></i> Approved on: <?= date('F j, Y', strtotime($date)) ?></span>
                                        <div>
                                            <form action="print_batch_form.php" method="POST" target="_blank" class="d-inline me-2">
                                                <?php foreach($scouts as $s): ?>
                                                    <input type="hidden" name="ids[]" value="<?= $s['id'] ?>">
                                                <?php endforeach; ?>
                                                <button type="submit" class="btn btn-light btn-sm"><i class="fas fa-print me-1"></i> Print Form for this Batch</button>
                                            </form>
                                            <span class="badge bg-light text-dark"><?= count($scouts) ?> Scouts</span>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 200px;">Name</th>
                                                        <th style="width: 80px;">Status</th>
                                                        <th style="width: 150px;">Membership Card</th>
                                                        <th style="width: 250px;">School</th>
                                                        <th style="width: 100px;">Rank</th>
                                                        <th style="width: 150px;">Action Taken</th>
                                                        <th style="width: 120px;">Approved By</th>
                                                        <th style="width: 130px;">Date Approved</th>
                                                        <th style="width: 120px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($scouts as $scout): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($scout['name']) ?></td>
                                                            <td>
                                                                <?php if($scout['registration_status'] == 'N'): ?>
                                                                    <span class="badge bg-info">New</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning text-dark">Re-Reg</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($scout['membership_card']) ?></td>
                                                            <td><?= htmlspecialchars($scout['school'] ?? 'N/A') ?></td>
                                                            <td><?= htmlspecialchars($scout['highest_rank']) ?></td>
                                                            <td><span class="badge bg-success">Ready for Account</span></td>
                                                            <td><?= htmlspecialchars($scout['admin_name'] ?? 'System') ?></td>
                                                            <td><?= date('M j, Y', strtotime($scout['archived_at'])) ?></td>
                                                            <td>
                                                                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewApprovedModal<?= $scout['id'] ?>"><i class="fas fa-eye"></i></button>
                                                                <form action="process_batch_registration.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this approved record?');">
                                                                    <input type="hidden" name="id" value="<?= $scout['id'] ?>">
                                                                    <input type="hidden" name="action" value="delete_archive">
                                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <!-- Archive Table -->
                <div class="card bg-transparent border-secondary mb-4">
                    <div class="card-body p-3 d-flex justify-content-between align-items-end">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="archive">
                            <div class="col-md-3">
                                <label class="form-label text-white-50">Start Date</label>
                                <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($start_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-white-50">End Date</label>
                                <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($end_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-white-50">Status</label>
                                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                                    <option value="">All Statuses</option>
                                    <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter Archive</button>
                            </div>
                        </form>
                        
                        <?php if (!empty($archive_scouts)): ?>
                        <form action="process_batch_registration.php" method="POST" onsubmit="return confirm('Are you sure you want to clear ALL archive history? This cannot be undone.');">
                            <input type="hidden" name="action" value="clear_archive">
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt me-1"></i> Clear All History</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($archive_scouts)): ?>
                    <div class="alert alert-info text-center">No archived records found.</div>
                <?php else: ?>
                    <?php foreach ($archive_scouts as $leader => $scouts): ?>
                        <div class="card bg-transparent border-secondary mb-4">
                            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-tie me-2"></i> Leader: <?= htmlspecialchars($leader) ?></span>
                                <div>
                                    <span class="badge bg-secondary"><?= count($scouts) ?> Records</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 200px;">Name</th>
                                                <th style="width: 80px;">Status</th>
                                                <th style="width: 150px;">Membership Card</th>
                                                <th style="width: 250px;">School</th>
                                                <th style="width: 150px;">Action Taken</th>
                                                <th style="width: 120px;">Action By</th>
                                                <th style="width: 150px;">Date Archived</th>
                                                <th style="width: 120px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scouts as $scout): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                                    <td>
                                                        <?php if($scout['registration_status'] == 'N'): ?>
                                                            <span class="badge bg-info">New</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Re-Reg</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($scout['membership_card']) ?></td>
                                                    <td><?php echo htmlspecialchars($scout['school'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if($scout['archive_status'] == 'Approved'): ?>
                                                            <span class="badge bg-info text-dark">Ready for Account</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($scout['admin_name'] ?? 'System'); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($scout['archived_at'])); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewArchiveModal<?= $scout['id'] ?>"><i class="fas fa-eye"></i></button>
                                                        <form action="process_batch_registration.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this history record?');">
                                                            <input type="hidden" name="id" value="<?= $scout['id'] ?>">
                                                            <input type="hidden" name="action" value="delete_archive">
                                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>
        <?php include('footer.php'); ?>
    </div>
</div>

<!-- Archive View Modals -->
<?php if ($current_tab == 'archive' && !empty($archive_scouts_flat)): ?>
    <?php foreach($archive_scouts_flat as $s): ?>
    <div class="modal fade" id="viewArchiveModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archived Registration Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($s['name']) ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($s['archive_status']) ?></p>
                    <p><strong>Registration Status:</strong> <?= ($s['registration_status'] == 'N') ? 'New' : 'Re-registering' ?></p>
                    <p><strong>Age:</strong> <?= htmlspecialchars($s['age']) ?></p>
                    <p><strong>Sex:</strong> <?= htmlspecialchars($s['sex']) ?></p>
                    <p><strong>School:</strong> <?= htmlspecialchars($s['school']) ?></p>
                    <p><strong>Membership Card:</strong> <?= htmlspecialchars($s['membership_card']) ?></p>
                    <p><strong>Highest Rank:</strong> <?= htmlspecialchars($s['highest_rank']) ?></p>
                    <p><strong>Years in Scouting:</strong> <?= htmlspecialchars($s['years_in_scouting']) ?></p>
                    <p><strong>Leader:</strong> <?= htmlspecialchars($s['leader_name']) ?></p>
                    <p><strong>Action By:</strong> <?= htmlspecialchars($s['admin_name'] ?? 'System') ?></p>
                    <p><strong>Date Processed:</strong> <?= date('M j, Y g:i A', strtotime($s['archived_at'])) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Approved View Modals -->
<?php if ($current_tab == 'approved' && !empty($approved_scouts_list)): ?>
    <?php foreach($approved_scouts_list as $s): ?>
    <div class="modal fade" id="viewApprovedModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approved Scout Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($s['name']) ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-success">Approved</span></p>
                    <p><strong>Registration Status:</strong> <?= ($s['registration_status'] == 'N') ? 'New' : 'Re-registering' ?></p>
                    <p><strong>Age:</strong> <?= htmlspecialchars($s['age']) ?></p>
                    <p><strong>Sex:</strong> <?= htmlspecialchars($s['sex']) ?></p>
                    <p><strong>School:</strong> <?= htmlspecialchars($s['school']) ?></p>
                    <p><strong>Membership Card:</strong> <?= htmlspecialchars($s['membership_card']) ?></p>
                    <p><strong>Highest Rank:</strong> <?= htmlspecialchars($s['highest_rank']) ?></p>
                    <p><strong>Years in Scouting:</strong> <?= htmlspecialchars($s['years_in_scouting']) ?></p>
                    <p><strong>Leader:</strong> <?= htmlspecialchars($s['leader_name']) ?></p>
                    <p><strong>Approved By:</strong> <?= htmlspecialchars($s['admin_name'] ?? 'System') ?></p>
                    <p><strong>Date Approved:</strong> <?= date('M j, Y g:i A', strtotime($s['archived_at'])) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle "Select All" for each leader group
    document.querySelectorAll('.select-all-group').forEach(headerCheckbox => {
        headerCheckbox.addEventListener('change', function() {
            const groupClass = 'group-item-' + this.dataset.group;
            document.querySelectorAll('.' + groupClass).forEach(itemCheckbox => {
                itemCheckbox.checked = this.checked;
            });
        });
    });
</script>
</body>
</html>
