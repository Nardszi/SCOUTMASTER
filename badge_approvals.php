<?php
session_start();
include('config.php');

// 1. Security & Setup
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: dashboard.php');
    exit();
}
$leader_id = $_SESSION['user_id'];
$is_detail_view = isset($_GET['scout_id']) && isset($_GET['badge_id']);

// 2. Handle Form Submission (Approvals & Rejections)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sbp_id = (int)$_POST['sbp_id'];
    $scout_id = (int)$_POST['scout_id'];
    $badge_id = (int)$_POST['badge_id'];

    if (isset($_POST['approve_single'])) {
        $req_id = (int)$_POST['approve_single'];
        $stmt = mysqli_prepare($conn, "UPDATE scout_requirement_progress SET date_approved = NOW(), approved_by_id = ?, rejection_comment = NULL WHERE scout_badge_progress_id = ? AND requirement_id = ?");
        mysqli_stmt_bind_param($stmt, "iii", $leader_id, $sbp_id, $req_id);
        mysqli_stmt_execute($stmt);
        logActivity($conn, $leader_id, 'Approve Requirement', "Approved requirement $req_id for scout $scout_id");
    } elseif (isset($_POST['action']) && $_POST['action'] === 'bulk_approve' && isset($_POST['req_ids'])) {
        foreach ($_POST['req_ids'] as $req_id) {
            $req_id = (int)$req_id;
            $stmt = mysqli_prepare($conn, "UPDATE scout_requirement_progress SET date_approved = NOW(), approved_by_id = ?, rejection_comment = NULL WHERE scout_badge_progress_id = ? AND requirement_id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $leader_id, $sbp_id, $req_id);
            mysqli_stmt_execute($stmt);
        }
        logActivity($conn, $leader_id, 'Bulk Approve', "Approved multiple requirements for scout $scout_id");
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reject' && isset($_POST['rejection_comment'])) {
        $req_id = (int)$_POST['req_id'];
        $comment = $_POST['rejection_comment'];
        // Set is_completed to 0 so scout can resubmit, and clear approval date/user.
        $stmt = mysqli_prepare($conn, "UPDATE scout_requirement_progress SET is_completed = 0, date_approved = NULL, approved_by_id = NULL, rejection_comment = ? WHERE scout_badge_progress_id = ? AND requirement_id = ?");
        mysqli_stmt_bind_param($stmt, "sii", $comment, $sbp_id, $req_id);
        mysqli_stmt_execute($stmt);
        logActivity($conn, $leader_id, 'Reject Requirement', "Rejected requirement $req_id for scout $scout_id");
    }
    // Check if the whole badge is now complete
    $total_reqs_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM badge_requirements WHERE merit_badge_id = ?");
    mysqli_stmt_bind_param($total_reqs_stmt, "i", $badge_id);
    mysqli_stmt_execute($total_reqs_stmt);
    $total_reqs = mysqli_fetch_row(mysqli_stmt_get_result($total_reqs_stmt))[0];

    $approved_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM scout_requirement_progress WHERE scout_badge_progress_id = ? AND date_approved IS NOT NULL");
    mysqli_stmt_bind_param($approved_count_stmt, "i", $sbp_id);
    mysqli_stmt_execute($approved_count_stmt);
    $approved_count = mysqli_fetch_row(mysqli_stmt_get_result($approved_count_stmt))[0];

    if ($total_reqs > 0 && $total_reqs == $approved_count) {
        $complete_badge_stmt = mysqli_prepare($conn, "UPDATE scout_badge_progress SET status = 'completed', date_completed = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($complete_badge_stmt, "i", $sbp_id);
        mysqli_stmt_execute($complete_badge_stmt);
        logActivity($conn, $leader_id, 'Badge Completed', "Badge $badge_id completed for scout $scout_id");
    }

    header("Location: badge_approvals.php?scout_id={$scout_id}&badge_id={$badge_id}&update=1");
    exit();
}

// 3. Fetch Data for Display
$page_data = [];
if ($is_detail_view) {
    // Detail View Data
    $scout_id = (int)$_GET['scout_id'];
    $badge_id = (int)$_GET['badge_id'];

    $stmt = mysqli_prepare($conn, "
        SELECT u.name as scout_name, u.profile_picture, mb.name as badge_name, mb.icon_path, sbp.id as sbp_id
        FROM scout_badge_progress sbp
        JOIN users u ON sbp.scout_id = u.id
        JOIN merit_badges mb ON sbp.merit_badge_id = mb.id
        WHERE sbp.scout_id = ? AND sbp.merit_badge_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $scout_id, $badge_id);
    mysqli_stmt_execute($stmt);
    $page_data['details'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$page_data['details']) {
        header('Location: badge_approvals.php'); exit();
    }
    $sbp_id = $page_data['details']['sbp_id'];

    $requirements = []; $approved_count = 0;
    $req_stmt = mysqli_prepare($conn, "
        SELECT br.id, br.requirement_number, br.description, srp.is_completed, srp.proof_file, srp.date_approved, approver.name as approver_name, srp.rejection_comment
        FROM badge_requirements br
        LEFT JOIN scout_requirement_progress srp ON br.id = srp.requirement_id AND srp.scout_badge_progress_id = ?
        LEFT JOIN users approver ON srp.approved_by_id = approver.id
        WHERE br.merit_badge_id = ? ORDER BY br.id
    ");
    mysqli_stmt_bind_param($req_stmt, "ii", $sbp_id, $badge_id);
    mysqli_stmt_execute($req_stmt);
    $req_result = mysqli_stmt_get_result($req_stmt);
    while ($row = mysqli_fetch_assoc($req_result)) {
        $requirements[] = $row;
        if ($row['date_approved'] !== null) $approved_count++;
    }
    $page_data['requirements'] = $requirements;
    $total_reqs = count($requirements);
    $page_data['progress_percentage'] = $total_reqs > 0 ? round(($approved_count / $total_reqs) * 100) : 0;

} else {
    // List View Data
    $stmt = mysqli_prepare($conn, "
        SELECT u.id as scout_id, u.name as scout_name, u.profile_picture as scout_pic,
               mb.id as badge_id, mb.name as badge_name, mb.icon_path as badge_icon,
               COUNT(srp.id) as pending_count
        FROM scout_requirement_progress srp
        JOIN scout_badge_progress sbp ON srp.scout_badge_progress_id = sbp.id
        JOIN users u ON sbp.scout_id = u.id
        JOIN merit_badges mb ON sbp.merit_badge_id = mb.id
        WHERE srp.is_completed = 1 AND srp.date_approved IS NULL
        GROUP BY u.id, mb.id ORDER BY u.name ASC, mb.name ASC
    ");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $page_data['pending_approvals'] = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $page_data['pending_approvals'][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merit Badge Approvals</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #000; color: white; }
        .wrapper { display: flex; min-height: 100vh; }
        .main { flex: 1; margin-left: 240px; padding: 30px; background: url("images/wall3.jpg") no-repeat center center/cover; position: relative; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-collapsed .main {
            margin-left: 80px;
        }
        .main::before { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,0.65); z-index: 0; }
        .main > * { position: relative; z-index: 1; }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(12px); border-radius: 20px; padding: 30px; border: 1px solid rgba(75, 71, 71, 0.15); color: white; }
        .progress-bar, .form-check-input:checked { background-color: #28a745; border-color: #28a745; }
        .btn-submit { background: #28a745; color: white; border: none; border-radius: 20px; padding: 10px 25px; font-weight: 600; transition: background 0.3s; }
        .btn-submit:hover { background: #218838; }
        
        /* List View Styles */
        .approval-card-link { text-decoration: none; color: inherit; }
        .approval-card { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); border-radius: 15px; transition: all 0.3s ease; color: white !important; }
        .approval-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); border-color: #28a745; }
        .scout-pic { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #28a745; }
        .badge-icon { width: 50px; height: 50px; object-fit: contain; }

        /* Detail View Styles */
        .badge-header-icon { width: 80px; height: 80px; border-radius: 50%; border: 3px solid #28a745; object-fit: cover; }
        .requirement-list .list-group-item { background-color: rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.2); color: white; margin-bottom: 10px; border-radius: 10px; }
        .requirement-list .list-group-item.approved { background-color: rgba(40, 167, 69, 0.2); border-left: 5px solid #28a745; }
        .requirement-list .list-group-item.pending-approval { background-color: rgba(255, 193, 7, 0.15); border-left: 5px solid #ffc107; }
        .modal-content { background: rgba(20, 20, 20, 0.95); color: white; border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <?php include('navbar.php'); ?>
        <div class="glass">
            <?php if ($is_detail_view): // DETAIL VIEW ?>
                <a href="badge_approvals.php" class="btn btn-outline-light btn-sm mb-4"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
                <?php if(isset($_GET['update'])): ?>
                    <div class="alert alert-success">Requirement status updated successfully!</div>
                <?php endif; ?>
                
                <div class="d-flex align-items-center mb-4">
                    <img src="<?php echo htmlspecialchars($page_data['details']['icon_path'] ?? 'images/default_badge.png'); ?>" alt="Badge" class="badge-header-icon me-4">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="fw-bold mb-0"><?php echo htmlspecialchars($page_data['details']['badge_name']); ?></h1>
                                <h4 class="text-white-50">for <?php echo htmlspecialchars($page_data['details']['scout_name']); ?></h4>
                            </div>
                            <?php if ($page_data['progress_percentage'] == 100): ?>
                                <a href="generate_certificate.php?badge_id=<?php echo (int)$_GET['badge_id']; ?>&scout_id=<?php echo (int)$_GET['scout_id']; ?>" target="_blank" class="btn btn-success"><i class="fas fa-certificate me-2"></i>View Certificate</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="mb-2">Overall Progress (Approved)</h5>
                    <div class="progress" style="height: 25px;"><div class="progress-bar" style="width: <?php echo $page_data['progress_percentage']; ?>%;"><?php echo $page_data['progress_percentage']; ?>%</div></div>
                </div>

                <form method="POST" id="approvalForm">
                    <input type="hidden" name="sbp_id" value="<?php echo $page_data['details']['sbp_id']; ?>">
                    <input type="hidden" name="scout_id" value="<?php echo (int)$_GET['scout_id']; ?>">
                    <input type="hidden" name="badge_id" value="<?php echo (int)$_GET['badge_id']; ?>">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0">Approve Requirements</h3>
                        <?php 
                        $has_pending = false;
                        foreach ($page_data['requirements'] as $r) { if ($r['is_completed'] == 1 && $r['date_approved'] === null) { $has_pending = true; break; } }
                        if ($has_pending): ?>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="selectAll"><label class="form-check-label" for="selectAll">Select All</label></div>
                            <button type="submit" name="action" value="bulk_approve" class="btn btn-success btn-sm">Approve Selected</button>
                        </div>
                        <?php endif; ?>
                    </div>

                <ul class="list-group requirement-list">
                    <?php foreach ($page_data['requirements'] as $req):
                        $is_approved = $req['date_approved'] !== null;
                        $is_pending = $req['is_completed'] == 1 && !$is_approved;
                        $item_class = $is_approved ? 'approved' : ($is_pending ? 'pending-approval' : '');
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center <?php echo $item_class; ?>">
                        <div class="d-flex align-items-center">
                            <?php if ($is_pending): ?>
                                <div class="form-check me-3"><input class="form-check-input req-checkbox" type="checkbox" name="req_ids[]" value="<?php echo $req['id']; ?>"></div>
                            <?php endif; ?>
                            <strong class="me-2"><?php echo htmlspecialchars($req['requirement_number']); ?>:</strong> <?php echo htmlspecialchars($req['description']); ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($req['proof_file'])): ?>
                                <a href="<?php echo htmlspecialchars($req['proof_file']); ?>" target="_blank" class="btn btn-sm btn-info me-3"><i class="fas fa-image"></i> View Proof</a>
                            <?php endif; ?>

                            <?php if ($is_approved): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Approved</span>
                            <?php elseif ($is_pending): ?>
                                <button type="submit" name="approve_single" value="<?php echo $req['id']; ?>" class="btn btn-sm btn-success me-2">Approve</button>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-req-id="<?php echo $req['id']; ?>">Reject</button>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Started</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                </form>

            <?php else: // LIST VIEW ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="fw-bold"><i class="fas fa-user-check me-3"></i>Pending Approvals</h1>
                </div>

                <?php if (empty($page_data['pending_approvals'])): ?>
                    <div class="alert alert-success bg-transparent text-white border-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                        Great job! There are no pending requirements to approve.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($page_data['pending_approvals'] as $item): ?>
                            <a href="badge_approvals.php?scout_id=<?php echo $item['scout_id']; ?>&badge_id=<?php echo $item['badge_id']; ?>" class="list-group-item list-group-item-action approval-card mb-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['scout_pic'] ?? 'images/default_profile.png'); ?>" alt="Scout" class="scout-pic me-3">
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($item['scout_name']); ?></h5>
                                            <small class="text-white-50">has items for</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center text-end">
                                        <div>
                                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($item['badge_name']); ?></h5>
                                            <span class="badge bg-warning text-dark"><?php echo $item['pending_count']; ?> items pending</span>
                                        </div>
                                        <img src="<?php echo htmlspecialchars($item['badge_icon'] ?? 'images/default_badge.png'); ?>" alt="Badge" class="badge-icon ms-3">
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    <?php if (isset($page_data['progress_percentage']) && $page_data['progress_percentage'] == 100): ?>
    confetti({
        particleCount: 150,
        spread: 70,
        origin: { y: 0.6 }
    });
    <?php endif; ?>
</script>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Reject Requirement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Please provide feedback for the scout on why this requirement is being rejected.</p>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="sbp_id" value="<?php echo $page_data['details']['sbp_id'] ?? ''; ?>">
                    <input type="hidden" name="req_id" id="modal_req_id" value="">
                    <input type="hidden" name="scout_id" value="<?php echo (int)($_GET['scout_id'] ?? 0); ?>">
                    <input type="hidden" name="badge_id" value="<?php echo (int)($_GET['badge_id'] ?? 0); ?>">
                    <div class="mb-3">
                        <label for="rejection_comment" class="form-label">Rejection Comment</label>
                        <textarea class="form-control bg-dark text-white" id="rejection_comment" name="rejection_comment" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Submit Rejection</button></div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('rejectModal')?.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var reqId = button.getAttribute('data-req-id');
    document.getElementById('modal_req_id').value = reqId;
});

document.getElementById('selectAll')?.addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.req-checkbox');
    for (var checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});
</script>
</body>
</html>






























































































































































































































































































































































-