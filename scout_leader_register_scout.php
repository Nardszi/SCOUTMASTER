<?php
session_start();
include('config.php');

// Allow only Scout Leader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'scout_leader') {
    header("Location: dashboard.php");
    exit();
}

// Handle Scout Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_scout'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    // Generate a secure, random temporary password as it's not provided in the form.
    $temporary_password = bin2hex(random_bytes(8)); // e.g., a 16-character random string
    $password = password_hash($temporary_password, PASSWORD_DEFAULT);
    $school = $_POST['school'];
    $gender = $_POST['gender'];
    $birthday = $_POST['birthday'];
    $age = $_POST['age'];
    $grade_level = $_POST['grade_level'];
    $paid_status = 'Paid';
    $membership_card = $_POST['membership_card'] ?? '';
    $registered_by_leader_id = $_SESSION['user_id'];

    // Validate Membership Card
    if (empty($membership_card)) {
        $_SESSION['error'] = "Membership Card Number is required.";
        header("Location: scout_leader_register.php");
        exit();
    }

    // Check if card exists in admin_scout_archive (Approved)
    $checkCard = mysqli_prepare($conn, "SELECT id FROM admin_scout_archive WHERE membership_card = ? AND archive_status = 'Approved'");
    mysqli_stmt_bind_param($checkCard, "s", $membership_card);
    mysqli_stmt_execute($checkCard);
    $cardResult = mysqli_stmt_get_result($checkCard);
    
    if (mysqli_num_rows($cardResult) === 0) {
        $_SESSION['error'] = "Invalid Membership Card Number. Please ensure the card is registered and approved in the system.";
        header("Location: scout_leader_register.php");
        exit();
    }
    mysqli_stmt_close($checkCard);

    // Check if card is already used in scout_profiles (Active Users)
    $checkUsed = mysqli_prepare($conn, "SELECT id FROM scout_profiles WHERE membership_card = ?");
    mysqli_stmt_bind_param($checkUsed, "s", $membership_card);
    mysqli_stmt_execute($checkUsed);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkUsed)) > 0) {
        $_SESSION['error'] = "This Membership Card has already been used by an active scout.";
        header("Location: scout_leader_register.php");
        exit();
    }
    mysqli_stmt_close($checkUsed);

    // Check if card is already used in scout_new_register (Pending Registrations)
    $checkPending = mysqli_prepare($conn, "SELECT id FROM scout_new_register WHERE membership_card = ?");
    mysqli_stmt_bind_param($checkPending, "s", $membership_card);
    mysqli_stmt_execute($checkPending);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkPending)) > 0) {
        $_SESSION['error'] = "This Membership Card is already attached to a pending registration.";
        header("Location: scout_leader_register.php");
        exit();
    }
    mysqli_stmt_close($checkPending);

    // Check if email exists in either users or the new registration table
    $check_email_query = "SELECT 1 FROM users WHERE email = ? UNION ALL SELECT 1 FROM scout_new_register WHERE email = ?";
    $stmt_check = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($stmt_check, "ss", $email, $email);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $_SESSION['error'] = "A user or registration with this email already exists.";
    } else {
        // Insert into the new scout_new_register table
        $insert_query = "INSERT INTO scout_new_register (name, email, password, school, gender, birthday, age, grade_level, paid_status, registered_by_leader_id, membership_card) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssssssissis", $name, $email, $password, $school, $gender, $birthday, $age, $grade_level, $paid_status, $registered_by_leader_id, $membership_card);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Scout registered successfully into the new system.";
            logActivity($conn, $registered_by_leader_id, 'New Scout Registration', "Leader registered new scout '$name' (pending admin approval).");
        } else {
            $_SESSION['error'] = "Error registering scout: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_stmt_close($stmt_check);

    header("Location: scout_leader_register.php");
    exit();
}

$page_title = 'Register Scout';
include('header.php');
?>
<style>
    .register-card {
        background: rgba(255,255,255,0.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 20px;
        padding: 2rem 2.5rem;
        max-width: 720px;
        margin: 0 auto;
        box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    }
    .register-card h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .register-card h2 i { color: #ffc107; }
    .register-card .subtitle { color: rgba(255,255,255,0.7); font-size: 0.95rem; margin-bottom: 1.75rem; }
    .register-card .form-label { color: rgba(255,255,255,0.95); font-weight: 500; }
    .register-card .form-control, .register-card .form-select {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.2);
        color: #fff;
        border-radius: 10px;
    }
    .register-card .form-control:focus, .register-card .form-select:focus {
        background: transparent;
        border-color: #28a745;
        box-shadow: none;
        color: #fff;
    }
    .register-card .form-control::placeholder { color: rgba(255,255,255,0.45); }
    .payment-status-box {
        background: rgba(0,0,0,0.2);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .payment-status-box .label { color: rgba(255,255,255,0.8); font-weight: 500; }
    #paymentStatusBadge { font-size: 1rem; padding: 0.4rem 0.9rem; font-weight: 600; }
    #paymentStatusBadge.paid { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #fff; border: none; }
    #paymentStatusBadge.unpaid { background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%); color: #fff; border: none; }
    .btn-submit {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        border: none;
        border-radius: 10px;
        padding: 0.65rem 1.5rem;
        font-weight: 600;
    }
    .btn-submit:hover { background: linear-gradient(135deg, #218838 0%, #1e7e34 100%); }
    .btn-back {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.25);
        color: #fff;
        border-radius: 10px;
        padding: 0.65rem 1.25rem;
    }
    .btn-back:hover { background: rgba(255,255,255,0.2); color: #fff; }
</style>

<div class="glass" style="max-width: 800px; margin: 0 auto;">
    <div class="register-card">
        <h2><i class="fas fa-user-plus"></i> Register New Scout</h2>
        <p class="subtitle">Fill in the scout details below. Membership card must be approved in the system.</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="post" action="scout_leader_register_scout.php" id="registerForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" required placeholder="Scout's full name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required placeholder="email@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Membership Card Number</label>
                <input type="text" class="form-control" name="membership_card" required placeholder="Enter approved membership card number">
            </div>

            <div class="mb-3">
                <label class="form-label">School</label>
                <input type="text" class="form-control" name="school" required placeholder="School name">
            </div>

            <div class="payment-status-box">
                <span class="label"><i class="fas fa-wallet me-2"></i>Payment status for this scout</span>
                <span id="paymentStatusBadge" class="badge paid">Paid</span>
            </div>

            <div class="mb-3">
                <label class="form-label">Is the scout paid or not?</label>
                <select name="paid_status" class="form-select" id="paidStatusSelect">
                    <option value="Paid" selected>Paid</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Birthday</label>
                    <input type="date" class="form-control" name="birthday" id="birthday" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Age</label>
                    <input type="number" class="form-control" name="age" id="age" required min="1" max="99" placeholder="Auto from birthday">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
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

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
                <a href="scout_leader_register.php" class="btn btn-back"><i class="fas fa-arrow-left me-2"></i>Back to list</a>
                <button type="submit" name="register_scout" class="btn btn-success btn-submit"><i class="fas fa-check me-2"></i>Register Scout</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var select = document.getElementById('paidStatusSelect');
    var badge = document.getElementById('paymentStatusBadge');
    if (select && badge) {
        function updateStatus() {
            var isPaid = select.value === 'Paid';
            badge.textContent = isPaid ? 'Paid' : 'Unpaid';
            badge.className = 'badge ' + (isPaid ? 'paid' : 'unpaid');
        }
        select.addEventListener('change', updateStatus);
        updateStatus();
    }
    var birthday = document.getElementById('birthday');
    var ageInput = document.getElementById('age');
    if (birthday && ageInput) {
        birthday.addEventListener('change', function() {
            var d = new Date(this.value);
            if (isNaN(d.getTime())) return;
            var today = new Date();
            var a = today.getFullYear() - d.getFullYear();
            var m = today.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < d.getDate())) a--;
            ageInput.value = a > 0 ? a : '';
        });
    }
})();
</script>
<?php include('footer.php'); ?>
</div></div></body></html>