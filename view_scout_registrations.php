<?php
session_start();
include('config.php');

// 1. Security: Ensure user is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

// 2. Fetch all scout registration data
$scouts = [];
$query = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.school,
        u.membership_card,
        sp.gender,
        sp.birthday,
        sp.age,
        sp.grade_level,
        sp.paid_status,
        u.created_at as registration_date,
        r.rank_name,
        t.troop_name
    FROM users u
    JOIN scout_profiles sp ON u.id = sp.user_id
    LEFT JOIN ranks r ON u.rank_id = r.id
    LEFT JOIN troops t ON u.troop_id = t.id
    WHERE u.role = 'scout'
    ORDER BY u.created_at DESC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $scouts[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Scout Registrations</title>
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
                <h1 class="fw-bold"><i class="fas fa-clipboard-list me-3"></i>Scout Registrations</h1>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Membership Card</th>
                            <th>Birthday</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Rank</th>
                            <th>Troop</th>
                            <th>School</th>
                            <th>Payment Status</th>
                            <th>Date Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scouts)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No scout registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($scouts as $scout): ?>
                                <tr class="scout-row">
                                    <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['email']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['membership_card'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($scout['birthday'])); ?></td>
                                    <td><?php echo htmlspecialchars($scout['age']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['grade_level']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['rank_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($scout['troop_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($scout['school']); ?></td>
                                    <td>
                                        <?php if ($scout['paid_status'] === 'Paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($scout['registration_date'])); ?></td>
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