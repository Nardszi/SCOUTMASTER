<?php
session_start();
include('config.php');

// 1. Security: Ensure user is a scout leader.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout_leader') {
    header('Location: dashboard.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM scout_new_register WHERE id = ? AND registered_by_leader_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $delete_id, $_SESSION['user_id']);
    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['user_id'], 'Delete Registration', "Deleted registration ID $delete_id");
        $_SESSION['success'] = "Registration deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting registration.";
    }
    header("Location: scout_leader_register.php");
    exit();
}

// Handle Edit
if (isset($_POST['update_scout'])) {
    $id = $_POST['scout_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $school = $_POST['school'];
    $gender = $_POST['gender'];
    $birthday = $_POST['birthday'];
    $age = $_POST['age'];
    $grade_level = $_POST['grade_level'];
    $membership_card = $_POST['membership_card'];

    $stmt = mysqli_prepare($conn, "UPDATE scout_new_register SET name=?, email=?, school=?, gender=?, birthday=?, age=?, grade_level=?, membership_card=? WHERE id=? AND registered_by_leader_id=?");
    mysqli_stmt_bind_param($stmt, "sssssissi", $name, $email, $school, $gender, $birthday, $age, $grade_level, $membership_card, $id, $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['user_id'], 'Update Registration', "Updated registration ID $id");
        $_SESSION['success'] = "Registration updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating registration.";
    }
    header("Location: scout_leader_register.php");
    exit();
}

// 2. Fetch scout registration data created by this leader
$scouts = [];
$leader_id = $_SESSION['user_id'];
$query = "
    SELECT id, name, email, school, gender, birthday, age, grade_level, paid_status, membership_card, created_at as registration_date, 'Pending' as status
    FROM scout_new_register
    WHERE registered_by_leader_id = ?
    
    UNION ALL
    
    SELECT u.id, u.name, u.email, u.school, sp.gender, sp.birthday, sp.age, sp.grade_level, sp.paid_status, u.membership_card, u.created_at as registration_date, 'Approved' as status
    FROM users u
    JOIN scout_profiles sp ON u.id = sp.user_id
    WHERE u.scout_leader_id = ? AND u.role = 'scout'
    
    ORDER BY registration_date DESC
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $leader_id, $leader_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
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
    <title>Scout Registrations</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; min-height: 100vh; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }
        .main { flex: 1; margin-left: 240px; padding: 30px; position: relative; display: flex; flex-direction: column; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-collapsed .main { margin-left: 80px; }
        .main > * { position: relative; z-index: 1; }

        .glass {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 28px 32px;
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            flex: 1; margin-bottom: 20px;
        }

        .page-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .page-title i { color: #ffc107; }

        .btn-group-actions .btn {
            border-radius: 10px;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }
        .btn-group-actions .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); border: none; color: #000; }
        .btn-group-actions .btn-warning:hover { background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%); color: #000; }
        .btn-group-actions .btn-success { background: linear-gradient(135deg, #28a745 0%, #218838 100%); border: none; }
        .btn-group-actions .btn-success:hover { background: linear-gradient(135deg, #218838 0%, #1e7e34 100%); }

        .table-responsive { border-radius: 12px; overflow: hidden; }
        .table {
            color: white !important;
            --bs-table-color: white;
            --bs-table-hover-color: white;
            vertical-align: middle;
            margin-bottom: 0;
        }
        .table thead th {
            background: rgba(0,0,0,0.35);
            color: rgba(255,255,255,0.95);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 12px;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,0.15);
        }
        .table tbody td {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 12px;
        }
        .table tbody tr:hover td { background: rgba(255,255,255,0.12); }
        .table tbody tr:last-child td { border-bottom: none; }

        .badge-paid { background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; color: #fff; font-weight: 500; }
        .badge-unpaid { background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%) !important; color: #fff; font-weight: 500; }
        .badge-pending { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important; color: #000; font-weight: 500; }
        .badge-approved { background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; color: #fff; font-weight: 500; }

        .modal-content {
            background: linear-gradient(180deg, rgba(30,30,35,0.98) 0%, rgba(20,20,25,0.98) 100%);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1rem 1.25rem;
        }
        .modal-title { font-weight: 600; font-size: 1.15rem; }
        .modal-body { padding: 1.25rem; }
        .modal-body .form-label { color: rgba(255,255,255,0.9); font-weight: 500; }
        .modal-body .form-control, .modal-body .form-select {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 8px;
        }
        .modal-body .form-control:focus, .modal-body .form-select:focus {
            background: rgba(255,255,255,0.12);
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.25);
            color: #fff;
        }
        .modal-body .form-control::placeholder { color: rgba(255,255,255,0.4); }
        .modal-body .form-select option { background-color: #1a1a1a; color: white; }
        .modal-footer { border-top: 1px solid rgba(255,255,255,0.1); padding: 1rem 1.25rem; }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="page-header-row">
                <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Scout Registrations</h1>
                <div class="btn-group-actions d-flex gap-2">
                    <a href="scout_leader_register_batch.php" class="btn btn-success"><i class="fas fa-users me-2"></i>Register Scout</a>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#registerScoutModal">
                        <i class="fas fa-user-plus me-2"></i> Register User
                    </button>
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
                            <th>Payment</th>
                            <th>Date Registered</th>
                            <th>Registration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scouts)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">No scout registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($scouts as $scout): ?>
                                <tr class="scout-row">
                                    <td><?php echo htmlspecialchars($scout['name']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['email']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($scout['birthday'])); ?></td>
                                    <td><?php echo htmlspecialchars($scout['age']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['grade_level']); ?></td>
                                    <td><?php echo htmlspecialchars($scout['school']); ?></td>
                                    <td><code class="text-info"><?php echo htmlspecialchars($scout['membership_card'] ?? '—'); ?></code></td>
                                    <td>
                                        <?php
                                        $paid = isset($scout['paid_status']) && strcasecmp($scout['paid_status'], 'Paid') === 0;
                                        ?>
                                        <span class="badge <?php echo $paid ? 'badge-paid' : 'badge-unpaid'; ?>"><?php echo $paid ? 'Paid' : 'Unpaid'; ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($scout['registration_date'])); ?></td>
                                    <td>
                                        <?php if ($scout['status'] === 'Pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($scout['status'] === 'Pending'): ?>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editScoutModal<?= $scout['id'] ?>"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_id=<?= $scout['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this registration?')"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
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

<!-- Edit Modals -->
<?php foreach ($scouts as $scout): ?>
<?php if ($scout['status'] === 'Pending'): ?>
<div class="modal fade" id="editScoutModal<?= $scout['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="scout_id" value="<?= $scout['id'] ?>">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($scout['name']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($scout['email']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Membership Card</label><input type="text" name="membership_card" class="form-control" value="<?= htmlspecialchars($scout['membership_card'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label">School</label><input type="text" name="school" class="form-control" value="<?= htmlspecialchars($scout['school']) ?>"></div>
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="paid_status" class="form-select">
                            <option value="Paid" <?= (isset($scout['paid_status']) && strcasecmp($scout['paid_status'], 'Paid') === 0) ? 'selected' : '' ?>>Paid</option>
                            <option value="Unpaid" <?= (isset($scout['paid_status']) && strcasecmp($scout['paid_status'], 'Unpaid') === 0) ? 'selected' : '' ?>>Unpaid</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birthday</label>
                            <input type="date" name="birthday" class="form-control birthday-input" data-target="age<?= $scout['id'] ?>" value="<?= $scout['birthday'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" id="age<?= $scout['id'] ?>" class="form-control" value="<?= $scout['age'] ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= $scout['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $scout['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <select name="grade_level" class="form-select" required>
                                <option value="Boy Scout" <?= $scout['grade_level'] == 'Boy Scout' ? 'selected' : '' ?>>Boy Scout</option>
                                <option value="Outfit Scout" <?= $scout['grade_level'] == 'Outfit Scout' ? 'selected' : '' ?>>Outfit Scout</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_scout" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- Register User Modal -->
<div class="modal fade" id="registerScoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register New Scout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="registerScoutForm" action="scout_leader_register_scout.php">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membership Card Number</label>
                        <input type="text" class="form-control" name="membership_card" placeholder="Enter approved membership card number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="paid_status" class="form-select">
                            <option value="Paid" selected>Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School</label>
                        <input type="text" class="form-control" name="school" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birthday</label>
                            <input type="date" class="form-control" name="birthday" id="birthday" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="age" id="age" required readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Scout Type</label>
                            <select name="scout_type" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                                <option value="" style="background-color: #1a1a1a; color: white;">Select Scout Type</option>
                                <option value="boy_scout" style="background-color: #1a1a1a; color: white;">Boy Scout</option>
                                <option value="outfit_scout" style="background-color: #1a1a1a; color: white;">Outfit Scout</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select" required style="background-color: rgba(255,255,255,0.1) !important; color: white !important;">
                            <option value="" style="background-color: #1a1a1a; color: white;">Select Position</option>
                            <option value="normal_scout" style="background-color: #1a1a1a; color: white;">Normal Scout</option>
                            <option value="platoon_leader" style="background-color: #1a1a1a; color: white;">Platoon Leader</option>
                            <option value="troop_leader" style="background-color: #1a1a1a; color: white;">Troop Leader</option>
                        </select>
                    </div>
                    <button type="submit" name="register_scout" class="btn btn-primary w-100">Register User</button>
                </form>
            </div>
        </div>
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

    // Auto-calculate age
    document.querySelectorAll('.birthday-input').forEach(input => {
        input.addEventListener('change', function() {
            const targetId = this.getAttribute('data-target');
            const birthday = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const m = today.getMonth() - birthday.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            document.getElementById(targetId).value = age > 0 ? age : 0;
        });
    });

    // Auto-calculate age from birthday for Register Modal
    const birthdayInput = document.getElementById('birthday');
    if (birthdayInput) {
        birthdayInput.addEventListener('change', function() {
            const birthday = new Date(this.value);
            if (!isNaN(birthday.getTime())) {
                const today = new Date();
                let age = today.getFullYear() - birthday.getFullYear();
                const m = today.getMonth() - birthday.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthday.getDate())) {
                    age--;
                }
                document.getElementById('age').value = age > 0 ? age : 0;
            }
        });
    }
</script>
</body>
</html>