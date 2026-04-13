<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scout_leader') {
    header('Location: login.php');
    exit();
}

$leader_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle CSV import
$csv_import_msg = '';
$csv_import_rows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $tmpFile = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($tmpFile, 'r');
    if ($handle) {
        $header = fgetcsv($handle); // skip header row
        $csvErrors = [];
        $csvCount = 0;
        $insert_csv = "INSERT INTO scout_new_register (name, registration_status, age, sex, membership_card, highest_rank, years_in_scouting, school, paid_status, registered_by_leader_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $csv_stmt = mysqli_prepare($conn, $insert_csv);
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) < 8) { $csvErrors[] = "Row $rowNum: Not enough columns."; continue; }
            $name = trim($row[0]);
            $reg_status = trim($row[1]);
            $age = intval($row[2]);
            $sex = trim($row[3]);
            $membership_card = trim($row[4]);
            $highest_rank = trim($row[5]);
            $years_in_scouting = intval($row[6]);
            $school = trim($row[7]);
            $paid_status = (isset($row[8]) && strtolower(trim($row[8])) === 'paid') ? 'Paid' : 'Unpaid';

            if (empty($name)) continue;
            if (empty($reg_status) || $age < 1 || empty($sex)) { $csvErrors[] = "Row $rowNum ($name): Missing required fields."; continue; }
            if (empty($membership_card)) { $csvErrors[] = "Row $rowNum ($name): Membership card required."; continue; }

            mysqli_stmt_bind_param($csv_stmt, "ssisssissi", $name, $reg_status, $age, $sex, $membership_card, $highest_rank, $years_in_scouting, $school, $paid_status, $leader_id);
            if (mysqli_stmt_execute($csv_stmt)) { $csvCount++; } else { $csvErrors[] = "Row $rowNum: DB error."; }
        }
        fclose($handle);
        mysqli_stmt_close($csv_stmt);
        if ($csvCount > 0) {
            $success_msg = "CSV Import: Successfully registered $csvCount scout(s). Pending admin approval.";
            if (function_exists('logActivity')) logActivity($conn, $leader_id, 'CSV Batch Import', "Imported $csvCount scouts via CSV.");
        }
        if (!empty($csvErrors)) $error_msg = implode("<br>", $csvErrors);
    } else {
        $error_msg = "Could not read the uploaded CSV file.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_history_id'])) {
        $del_id = intval($_POST['delete_history_id']);
        $del_stmt = mysqli_prepare($conn, "DELETE FROM scout_registration_archive WHERE id = ? AND registered_by_leader_id = ?");
        mysqli_stmt_bind_param($del_stmt, "ii", $del_id, $leader_id);
        if (mysqli_stmt_execute($del_stmt)) {
            if (function_exists('logActivity')) {
                logActivity($conn, $leader_id, 'Delete Registration History', "Deleted history ID $del_id");
            }
            $success_msg = "History record deleted successfully.";
        } else {
            $error_msg = "Error deleting record.";
        }
    } elseif (isset($_POST['clear_all_history'])) {
        $clear_stmt = mysqli_prepare($conn, "DELETE FROM scout_registration_archive WHERE registered_by_leader_id = ?");
        mysqli_stmt_bind_param($clear_stmt, "i", $leader_id);
        if (mysqli_stmt_execute($clear_stmt)) {
            if (function_exists('logActivity')) {
                logActivity($conn, $leader_id, 'Clear Registration History', "Cleared all registration history");
            }
            $success_msg = "All history records cleared successfully.";
        } else {
            $error_msg = "Error clearing history.";
        }
    } elseif (isset($_POST['name'])) {
        $names = $_POST['name'] ?? [];
        $registration_statuses = $_POST['registration_status'] ?? [];
        $ages = $_POST['age'] ?? [];
        $sexes = $_POST['sex'] ?? [];
        $membership_cards = $_POST['membership_card'] ?? [];
        $highest_ranks = $_POST['highest_rank'] ?? [];
        $years_in_scoutings = $_POST['years_in_scouting'] ?? [];
        $schools = $_POST['school'] ?? [];
        $paid_statuses = $_POST['paid_status'] ?? [];

        $count = 0;
        $errors = [];

        $insert_query = "INSERT INTO scout_new_register (name, registration_status, age, sex, membership_card, highest_rank, years_in_scouting, school, paid_status, registered_by_leader_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        if (!$insert_stmt) {
            $error_msg = "Database error preparing statement.";
        } else {
            for ($i = 0; $i < count($names); $i++) {
                $name = trim($names[$i] ?? '');
                $registration_status = $registration_statuses[$i] ?? '';
                $age = intval($ages[$i] ?? 0);
                $sex = $sexes[$i] ?? '';
                $membership_card = trim($membership_cards[$i] ?? '');
                $highest_rank = trim($highest_ranks[$i] ?? '');
                $years_in_scouting = intval($years_in_scoutings[$i] ?? 0);
                $school = trim($schools[$i] ?? '');
                $paid_status = (isset($paid_statuses[$i]) && $paid_statuses[$i] === 'Paid') ? 'Paid' : 'Unpaid';

                if (empty($name)) continue;

                if (empty($registration_status) || $age < 1 || empty($sex)) {
                    $errors[] = "Row " . ($i + 1) . ": Missing required fields (name, registration status, age, sex).";
                    continue;
                }

                if (empty($membership_card)) {
                    $errors[] = "Row " . ($i + 1) . ": Membership card number is required.";
                    continue;
                }

                mysqli_stmt_bind_param($insert_stmt, "ssisssissi", $name, $registration_status, $age, $sex, $membership_card, $highest_rank, $years_in_scouting, $school, $paid_status, $leader_id);

                if (mysqli_stmt_execute($insert_stmt)) {
                    $count++;
                } else {
                    $errors[] = "Row " . ($i + 1) . ": " . mysqli_error($conn);
                }
            }
            mysqli_stmt_close($insert_stmt);
        }

        if ($count > 0) {
            $success_msg = "Successfully registered $count scout(s). They are now pending admin approval.";
            if (function_exists('logActivity')) {
                logActivity($conn, $leader_id, 'Batch Scout Registration', "Registered $count new scouts.");
            }
        }
        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }
}

$pending_scouts = [];
$p_query = "SELECT * FROM scout_new_register WHERE registered_by_leader_id = ? AND (email IS NULL OR email = '') ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $p_query);
mysqli_stmt_bind_param($stmt, "i", $leader_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $pending_scouts[] = $row;
    }
}

$history_scouts = [];
$h_query = "SELECT * FROM scout_registration_archive WHERE registered_by_leader_id = ? ORDER BY archived_at DESC LIMIT 50";
$stmt = mysqli_prepare($conn, $h_query);
mysqli_stmt_bind_param($stmt, "i", $leader_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $history_scouts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Scout Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0a0a0a; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; }
        .wrapper { display: flex; min-height: 100vh; }
        .main { flex: 1; margin-left: 240px; padding: 28px; background: url("images/wall3.jpg") no-repeat center center/cover; background-attachment: fixed; position: relative; transition: margin-left 0.3s; }
        .main::before { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,0.68); z-index: 0; pointer-events: none; }
        .content-container {
            position: relative; z-index: 2;
            background: transparent;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            padding: 28px 32px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .page-title { font-size: 1.5rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
        .page-title i { color: #ffc107; }
        .form-control, .form-select {
            background: transparent !important;
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff !important;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            background: transparent !important;
            border-color: #28a745;
            box-shadow: none;
            color: #fff !important;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.5); }
        option { background: #1a1a1a; color: #fff; }
        .table { color: #fff !important; margin-bottom: 0; }
        .table thead th {
            background: rgba(0,0,0,0.4);
            color: rgba(255,255,255,0.95);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,0.15);
        }
        .table td { color: #fff !important; border-color: rgba(255,255,255,0.08); vertical-align: middle; padding: 10px; background: rgba(255,255,255,0.02); }
        .table tbody tr:hover td { background: transparent; }
        .table td .btn { position: relative; z-index: 3; pointer-events: auto !important; }
        .btn-remove { color: #ff6b6b; cursor: pointer; font-size: 1rem; }
        .btn-remove:hover { color: #ff4c4c; }
        .badge-paid { background: #28a745 !important; color: #fff !important; font-weight: 600; }
        .badge-unpaid { background: #dc3545 !important; color: #fff !important; font-weight: 600; }
        .modal-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.1); }
        .payment-cell { min-width: 115px; }
        .payment-cell .row-badge { font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px; }
        .memb-cell { color: rgba(255,255,255,0.5); font-size: 0.9rem; }@media (max-width: 768px) { .main { margin-left: 0; } }
        .nav-tabs .nav-link { color: rgba(255,255,255,0.7); border: none; border-bottom: 2px solid transparent; font-weight: 500; }
        .nav-tabs .nav-link.active { color: #28a745; background: transparent; border-bottom: 2px solid #28a745; }
        .nav-tabs .nav-link:hover { color: #fff; }
        .btn-submit { background: linear-gradient(135deg, #28a745 0%, #218838 100%); border: none; border-radius: 8px; font-weight: 600; }
        .btn-submit:hover { background: linear-gradient(135deg, #218838 0%, #1e7e34 100%); }
        .btn-back { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: #fff; border-radius: 8px; }
        .btn-back:hover { background: rgba(255,255,255,0.2); color: #fff; }
        .section-divider { border-color: rgba(255,255,255,0.1); margin: 2rem 0; }
        .section-title { font-size: 1.15rem; font-weight: 600; margin-bottom: 1rem; }
        .section-title i { color: #17a2b8; margin-right: 0.5rem; }
        .modal { z-index: 99999 !important; }
        .modal-backdrop { z-index: 99998 !important; }
        .btn-close { filter: brightness(0) invert(1); opacity: 1; cursor: pointer; pointer-events: auto !important; }
        .csv-import-box {
            background: rgba(40,167,69,0.08);
            border: 1px solid rgba(40,167,69,0.3);
            border-radius: 10px;
            padding: 14px 18px;
        }
        .fw-600 { font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <div class="content-container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="page-title"><i class="fas fa-users"></i> Batch Scout Registration</h2>
                <a href="scout_leader_register.php" class="btn btn-back"><i class="fas fa-arrow-left me-2"></i>Back to Registrations</a>
            </div>
            <p class="text-white-50 mb-3">Register up to 12 scouts at once. Set <strong>Payment</strong> (Paid/Unpaid) per row. Enter membership card number manually for each scout.</p>

            <!-- CSV Import Section -->
            <div class="csv-import-box mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-file-csv" style="color:#28a745;font-size:1.2rem;"></i>
                    <span class="fw-600" style="font-size:0.97rem;">Import via CSV</span>
                    <a href="generate_sample_csv.php" class="btn btn-sm btn-outline-success ms-auto" download>
                        <i class="fas fa-download me-1"></i>Download Sample CSV
                    </a>
                </div>
                <form method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="file" name="csv_file" accept=".csv" class="form-control form-control-sm" style="max-width:320px;" required>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-upload me-1"></i>Import CSV</button>
                </form>
                <small class="text-white-50 d-block mt-1">Columns: Surname Given Name M.I, Registration Status (N / RR), Age, Sex (Male/Female), Membership Card No., Highest Rank, Yrs in Scouting, School, Payment (Paid/Unpaid)</small>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?= $error_msg ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="table-responsive">
                    <table class="table" id="scoutTable">
                        <thead>
                            <tr>
                                <th style="width: 36px;">#</th>
                                <th>Surname, Given Name, M.I</th>
                                <th>Registration Status</th>
                                <th style="width: 80px;">Age</th>
                                <th style="width: 90px;">Sex</th>
                                <th class="payment-cell">Payment</th>
                                <th>Membership Card No.</th>
                                <th>Highest Rank Earned</th>
                                <th>Yrs in Scouting</th>
                                <th>School</th>
                                <th style="width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                    <button type="button" class="btn btn-outline-light" onclick="addRow()"><i class="fas fa-plus me-2"></i>Add Row</button>
                    <button type="submit" class="btn btn-success btn-submit"><i class="fas fa-save me-2"></i>Submit Registrations</button>
                </div>
            </form>

            <hr class="section-divider">
            <h3 class="section-title"><i class="fas fa-history"></i>Registration Status</h3>

            <ul class="nav nav-tabs mb-3" id="statusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending Approval</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">History (Approved/Rejected)</button>
                </li>
            </ul>

            <div class="tab-content" id="statusTabsContent">
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_scouts)): ?>
                                    <tr><td colspan="4" class="text-center text-white-50 py-4">No pending registrations.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_scouts as $s):
                                        $isPaid = isset($s['paid_status']) && strcasecmp($s['paid_status'], 'Paid') === 0;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['name']) ?></td>
                                            <td><span class="badge <?= $isPaid ? 'badge-paid' : 'badge-unpaid' ?>"><?= $isPaid ? 'Paid' : 'Unpaid' ?></span></td>
                                            <td><span class="badge bg-warning text-dark">Pending</span></td>
                                            <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <?php if (!empty($history_scouts)): ?>
                        <div class="text-end mb-3">
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearAllHistoryModal">
                                <i class="fas fa-trash-alt me-1"></i>Clear All History
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date Processed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history_scouts)): ?>
                                    <tr><td colspan="5" class="text-center text-white py-4">No history found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($history_scouts as $s):
                                        $histPaid = isset($s['paid_status']) && strcasecmp($s['paid_status'], 'Paid') === 0;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['name']) ?></td>
                                            <td><span class="badge <?= $histPaid ? 'badge-paid' : 'badge-unpaid' ?>"><?= $histPaid ? 'Paid' : 'Unpaid' ?></span></td>
                                            <td>
                                                <?php if (isset($s['archive_status']) && $s['archive_status'] === 'Approved'): ?>
                                                    <span class="badge bg-info text-dark">Ready for Account</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($s['archived_at'])) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm me-1" data-bs-toggle="modal" data-bs-target="#viewHistoryModal<?= $s['id'] ?>"><i class="fas fa-eye"></i></button>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteHistoryModal<?= $s['id'] ?>"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($history_scouts as $s): ?>
<div class="modal fade" id="viewHistoryModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registration Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Name:</strong> <?= htmlspecialchars($s['name']) ?></p>
                <p><strong>Payment:</strong> <span class="badge <?= (isset($s['paid_status']) && strcasecmp($s['paid_status'], 'Paid') === 0) ? 'badge-paid' : 'badge-unpaid' ?>"><?= (isset($s['paid_status']) && strcasecmp($s['paid_status'], 'Paid') === 0) ? 'Paid' : 'Unpaid' ?></span></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($s['archive_status'] ?? '—') ?></p>
                <p><strong>Registration Status:</strong> <?= (isset($s['registration_status']) && $s['registration_status'] === 'N') ? 'New' : 'Re-registering' ?></p>
                <p><strong>Age:</strong> <?= htmlspecialchars($s['age'] ?? '—') ?></p>
                <p><strong>Sex:</strong> <?= htmlspecialchars($s['sex'] ?? '—') ?></p>
                <p><strong>School:</strong> <?= htmlspecialchars($s['school'] ?? '—') ?></p>
                <p><strong>Membership Card:</strong> <?= htmlspecialchars($s['membership_card'] ?? '—') ?></p>
                <p><strong>Highest Rank:</strong> <?= htmlspecialchars($s['highest_rank'] ?? '—') ?></p>
                <p><strong>Years in Scouting:</strong> <?= htmlspecialchars($s['years_in_scouting'] ?? '—') ?></p>
                <p><strong>Date Processed:</strong> <?= date('M j, Y g:i A', strtotime($s['archived_at'])) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var maxRows = 12;
    var rowCount = 0;

    function updatePaymentBadge(selectEl) {
        var cell = selectEl && selectEl.closest ? selectEl.closest('td') : null;
        if (!cell) return;
        var badge = cell.querySelector('.row-badge');
        if (!badge) return;
        var isPaid = selectEl.value === 'Paid';
        badge.textContent = isPaid ? 'Paid' : 'Unpaid';
        badge.className = 'badge row-badge ' + (isPaid ? 'badge-paid' : 'badge-unpaid');
    }

    window.addRow = function() {
        if (rowCount >= maxRows) {
            alert("Maximum of 12 scouts per batch.");
            return;
        }
        rowCount++;
        var tbody = document.querySelector('#scoutTable tbody');
        if (!tbody) return;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + rowCount + '</td>' +
            '<td><input type="text" name="name[]" class="form-control form-control-sm" placeholder="Surname, Given Name, M.I" required></td>' +
            '<td><select name="registration_status[]" class="form-select form-select-sm" required><option value="N">New</option><option value="RR">Re-registering</option></select></td>' +
            '<td><input type="number" name="age[]" class="form-control form-control-sm" min="1" max="99" placeholder="Age" required></td>' +
            '<td><select name="sex[]" class="form-select form-select-sm" required><option value="Male">Male</option><option value="Female">Female</option></select></td>' +
            '<td class="payment-cell"><select name="paid_status[]" class="form-select form-select-sm payment-select"><option value="Paid" selected>Paid</option><option value="Unpaid">Unpaid</option></select><span class="badge row-badge badge-paid mt-1 d-inline-block">Paid</span></td>' +
            '<td><input type="text" name="membership_card[]" class="form-control form-control-sm" placeholder="Enter card number" required></td>' +
            '<td><input type="text" name="highest_rank[]" class="form-control form-control-sm" placeholder="Optional"></td>' +
            '<td><input type="number" name="years_in_scouting[]" class="form-control form-control-sm" min="0" placeholder="0"></td>' +
            '<td><input type="text" name="school[]" class="form-control form-control-sm" placeholder="School"></td>' +
            '<td class="text-center">' + (rowCount > 1 ? '<i class="fas fa-trash btn-remove" onclick="removeRow(this)" title="Remove row"></i>' : '') + '</td>';
        tbody.appendChild(tr);
        var paySelect = tr.querySelector('.payment-select');
        if (paySelect) {
            paySelect.addEventListener('change', function() { updatePaymentBadge(this); });
        }
        if (rowCount === 2) {
            var firstRow = tbody.querySelector('tr');
            var lastCell = firstRow && firstRow.cells && firstRow.cells[firstRow.cells.length - 1];
            if (lastCell && !lastCell.querySelector('.btn-remove')) {
                var icon = document.createElement('i');
                icon.className = 'fas fa-trash btn-remove';
                icon.title = 'Remove row';
                icon.onclick = function() { removeRow(this); };
                lastCell.appendChild(icon);
            }
        }
    };

    window.removeRow = function(btn) {
        var row = btn && btn.closest ? btn.closest('tr') : null;
        if (!row) return;
        row.remove();
        rowCount--;
        var rows = document.querySelectorAll('#scoutTable tbody tr');
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].cells[0]) rows[i].cells[0].textContent = i + 1;
        }
        if (rows.length === 1) {
            var lastCell = rows[0].cells[rows[0].cells.length - 1];
            var icon = lastCell && lastCell.querySelector('.btn-remove');
            if (icon) icon.remove();
        }
    };

    document.getElementById('scoutTable').addEventListener('change', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('payment-select')) {
            updatePaymentBadge(e.target);
        }
    });

    if (document.querySelector('#scoutTable tbody')) {
        addRow();
    }
})();
</script>
<!-- Per-record Delete History Modals -->
<?php foreach ($history_scouts as $s): ?>
<div class="modal fade" id="deleteHistoryModal<?= $s['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(20,20,20,0.97);border:1px solid rgba(255,255,255,0.15);color:white;border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;padding:1.25rem 1.5rem;">
                <h5 class="modal-title" style="color:white;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body text-center py-4" style="background:rgba(20,20,20,0.97);">
                    <p class="mb-1" style="color:rgba(255,255,255,0.7);">You are about to delete the record of</p>
                    <p class="fw-bold" style="font-size:1.1rem;color:#e74c3c;"><?= htmlspecialchars($s['name']) ?></p>
                    <p class="mb-0" style="font-size:0.85rem;color:rgba(255,255,255,0.5);">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center" style="border-top:1px solid rgba(255,255,255,0.1);background:rgba(20,20,20,0.97);gap:1rem;">
                    <input type="hidden" name="delete_history_id" value="<?= $s['id'] ?>">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fas fa-trash me-1"></i> Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Clear All History Modal -->
<div class="modal fade" id="clearAllHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(20,20,20,0.97);border:1px solid rgba(255,255,255,0.15);color:white;border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;padding:1.25rem 1.5rem;">
                <h5 class="modal-title" style="color:white;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Clear All History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body text-center py-4" style="background:rgba(20,20,20,0.97);">
                    <p class="mb-1" style="color:rgba(255,255,255,0.7);">You are about to clear</p>
                    <p class="fw-bold" style="font-size:1.1rem;color:#e74c3c;">All Registration History</p>
                    <p class="mb-0" style="font-size:0.85rem;color:rgba(255,255,255,0.5);">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center" style="border-top:1px solid rgba(255,255,255,0.1);background:rgba(20,20,20,0.97);gap:1rem;">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="clear_all_history" class="btn btn-danger px-4"><i class="fas fa-trash-alt me-1"></i> Yes, Clear All</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
