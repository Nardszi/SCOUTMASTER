    <?php
    session_start();
    include('config.php');

    // 1. Security: Ensure user is an admin.
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        header('Location: dashboard.php');
        exit();
    }

    // Count pending batch registrations
    $pending_batch_count = 0;
    $pending_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM scout_new_register WHERE email IS NULL OR email = ''");
    if ($pending_res) {
        $pending_batch_count = (int) mysqli_fetch_assoc($pending_res)['cnt'];
    }

    // 2. Fetch all new scout registration data
    $new_scouts = [];
    $query = "
        SELECT
            snr.id,
            snr.name,
            snr.email,
            snr.school,
            snr.gender,
            snr.birthday,
            snr.age,
            snr.grade_level,
            snr.paid_status,
            snr.membership_card,
            snr.created_at as registration_date,
            u.name as leader_name
        FROM scout_new_register snr
        JOIN users u ON snr.registered_by_leader_id = u.id
        WHERE snr.email IS NOT NULL AND snr.email != ''
        ORDER BY snr.created_at DESC
    ";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $new_scouts[] = $row;
        }
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View New Scout Registrations</title>
        <?php include('favicon_header.php'); ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
            body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
            .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }
            .main { flex: 1; margin-left: 240px; padding: 30px; position: relative; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
            body.sidebar-collapsed .main {
                margin-left: 80px;
            }
            .main > * { position: relative; z-index: 1; }
            .glass {
                background: rgba(255,255,255,0.1);
                backdrop-filter: blur(12px);
                border-radius: 20px;
                padding: 30px;
                border: 1px solid rgba(255,255,255,0.15);
                flex: 1; margin-bottom: 20px;
            }
            .table {
                color: white !important;
                --bs-table-color: white;
                --bs-table-hover-color: white;
                vertical-align: middle;
            }
            .table thead th { background-color: rgba(0,0,0,0.3); border-bottom: 2px solid rgba(255,255,255,0.1); }
            .table tbody td { background-color: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
            .table-hover tbody tr:hover td { background-color: rgba(255,255,255,0.15); }
        </style>
    </head>
    <body>

    <div class="wrapper">
        <?php include('sidebar.php'); ?>

        <div class="main">
            <?php include('navbar.php'); ?>

            <div class="glass">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="fw-bold"><i class="fas fa-inbox me-3"></i>New User Registrations</h1>
                    <div>
                    <a href="admin_view_batch_registrations.php" class="btn btn-primary position-relative">
                        <i class="fas fa-users-cog me-2"></i>Scout Registration
                        <?php if ($pending_batch_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7rem;">
                                <?= $pending_batch_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Birthday</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Grade Level</th>
                                <th>School</th>
                                <th>Membership Card</th>
                                <th>Registered By</th>
                                <th>Date Registered</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($new_scouts)): ?>
                                <tr>
                                    <td colspan="12" class="text-center">No new scout registrations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($new_scouts as $scout): ?>
                                    <tr class="scout-row">
                                        <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                        <td><?php echo htmlspecialchars($scout['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($scout['birthday'])); ?></td>
                                        <td><?php echo htmlspecialchars($scout['age']); ?></td>
                                        <td><?php echo htmlspecialchars($scout['gender']); ?></td>
                                        <td><?php echo htmlspecialchars($scout['grade_level']); ?></td>
                                        <td><?php echo htmlspecialchars($scout['school']); ?></td>
                                        <td><?php echo htmlspecialchars($scout['membership_card'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($scout['leader_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($scout['registration_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form action="approve_registrations.php" method="POST" onsubmit="return confirm('Are you sure you want to approve this scout? This will create their account and email them a password.');">
                                                    <input type="hidden" name="approve_id" value="<?= $scout['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                                                </form>
                                                <form action="reject_registration.php" method="POST" onsubmit="return confirm('Are you sure you want to reject this scout? This will delete the registration and send a rejection email.');">
                                                    <input type="hidden" name="reject_id" value="<?= $scout['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php include('footer.php'); ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // This search is tied to the navbar search, which has id="globalSearch"
        document.getElementById('globalSearch').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.scout-row');

            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
    </body>
    </html>