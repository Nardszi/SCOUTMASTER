<?php
session_start();
include('config.php');

// Allow only Scout Leader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scout_leader') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Scout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: url("images/wall.png") no-repeat center center/cover fixed; color: white; }
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
            max-width: 800px;
            margin: 0 auto;
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.2);
            color: white;
            border-color: #28a745;
            box-shadow: none;
        }
        .form-label { font-weight: 500; }
        option { background-color: #333; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><i class="fas fa-user-plus me-2"></i>Register New Scout</h2>
                <a href="scout_leader_register.php" class="btn btn-secondary">Back to List</a>
            </div>

            <form method="POST" action="scout_leader_register_scout.php">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">School</label>
                    <input type="text" class="form-control" name="school" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Membership Card Number</label>
                    <input type="text" class="form-control" name="membership_card" placeholder="Enter Membership Card No." required>
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
                            <option value="" disabled selected>Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status / Grade Level</label>
                        <select name="grade_level" class="form-select" required>
                            <option value="NEW SCOUT" selected>NEW SCOUT</option>
                            <option value="ELEMENTARY SCOUT">ELEMENTARY SCOUT</option>
                            <option value="HIGHSCHOOL SCOUT">HIGHSCHOOL SCOUT</option>
                            <option value="PLATOON LEADER">PLATOON LEADER</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Payment Status</label>
                    <select name="paid_status" class="form-select" required>
                        <option value="Unpaid" selected>Unpaid</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" name="register_scout" class="btn btn-success btn-lg">Register Scout</button>
                </div>
            </form>
        </div>
        
        <?php include('footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-calculate age from birthday
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