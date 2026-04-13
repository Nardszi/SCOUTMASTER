<?php
session_start();
include('config.php');

// Ensure only Admin and Scout Leader can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'scout_leader'])) {
    header('Location: login.php');
    exit();
}

// Check if user ID is passed
if (!isset($_GET['id'])) {
    header('Location: manage_scouts.php');
    exit();
}

$user_id = $_GET['id'];

// Fetch user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo "User not found.";
    exit();
}

$user = mysqli_fetch_assoc($result);

// Fetch available ranks
$ranks_query = "SELECT * FROM ranks";
$ranks_result = mysqli_query($conn, $ranks_query);

// Update user information
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $rank_id = $_POST['rank_id']; // New rank assignment
    $approved = isset($_POST['approved']) ? 1 : 0;
    $membership_card = $_POST['membership_card'];

    // Check if rank_id exists in the ranks table
    if ($rank_id) {
        $rank_check_query = "SELECT id FROM ranks WHERE id = ?";
        $rank_check_stmt = mysqli_prepare($conn, $rank_check_query);
        mysqli_stmt_bind_param($rank_check_stmt, "i", $rank_id);
        mysqli_stmt_execute($rank_check_stmt);
        $rank_check_result = mysqli_stmt_get_result($rank_check_stmt);
        
        // If rank_id doesn't exist, set rank_id to NULL and show an error message
        if (mysqli_num_rows($rank_check_result) == 0) {
            echo "Error: The selected rank does not exist. Rank will be set to NULL.";
            $rank_id = NULL; // Set rank_id to NULL if the rank does not exist
        }
    } else {
        // If no rank is selected, set rank_id to NULL
        $rank_id = NULL;
    }

    // Update user information
    $update_query = "UPDATE users SET name = ?, email = ?, role = ?, rank_id = ?, approved = ?, membership_card = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sssiisi", $name, $email, $role, $rank_id, $approved, $membership_card, $user_id);

    if (mysqli_stmt_execute($update_stmt)) {
        header('Location: manage_scouts.php');
        exit();
    } else {
        echo "Error updating user information.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        /* BASE */
        body{
            margin:0;
            font-family:'Segoe UI',sans-serif;
            min-height:100vh;
            background:#000;
            color:white;
        }

        /* LAYOUT */
        .wrapper{
            display:flex;
            min-height:100vh;
        }

        /* MAIN BACKGROUND */
        .main{
            flex:1;
            margin-left: 240px;
            padding:30px; 
            background:url("images/wall3.jpg") no-repeat center center/cover;
            position:relative;
            display:flex;
            flex-direction:column;
            transition: margin-left 0.3s ease-in-out;
        }

        body.sidebar-collapsed .main {
            margin-left: 0;
        }

        /* OVERLAY */
        .main::before{
            content:"";
            position:absolute;
            inset:0;
            background:rgba(0,0,0,0.55);
            z-index:0;
        }

        .main > *{
            position:relative;
            z-index:1;
        }

        /* GLASS */
        .glass{
            background:rgba(255,255,255,0.15);
            backdrop-filter:blur(10px);
            border-radius:20px;
            padding:20px;
            margin-bottom:20px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2);
            color: #333;
            max-width: 600px;
            margin: 0 auto;
        }

        .sidebar-active {
            background: rgba(40, 167, 69, 0.2) !important;
            border-left: 4px solid #28a745 !important;
            color: #28a745 !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="form-container">
                <h2 class="mb-4 text-center">Edit User Information</h2>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Membership Card</label>
                        <input type="text" class="form-control" name="membership_card" value="<?php echo htmlspecialchars($user['membership_card'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role">
                            <option value="admin" <?php if ($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="scout_leader" <?php if ($user['role'] == 'scout_leader') echo 'selected'; ?>>Scout Leader</option>
                            <option value="scout" <?php if ($user['role'] == 'scout') echo 'selected'; ?>>Scout</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Rank</label>
                        <select class="form-control" name="rank_id">
                            <option value="">-- Select Rank --</option>
                            <?php while ($rank = mysqli_fetch_assoc($ranks_result)) { ?>
                                <option value="<?php echo $rank['id']; ?>" <?php if ($user['rank_id'] == $rank['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($rank['rank_name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="approved" <?php if ($user['approved']) echo 'checked'; ?>>
                        <label class="form-check-label">Approved</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                    <a href="manage_scouts.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </form>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</div>

<script>
// This script is for highlighting the active sidebar link.
document.addEventListener("DOMContentLoaded", function() {
    const currentPath = window.location.pathname.split("/").pop();
    const sidebar = document.querySelector('.wrapper').firstElementChild;
    
    if (sidebar) {
        const links = sidebar.getElementsByTagName('a');
        for (let link of links) {
            // Highlight if exact match OR if editing a scout (highlight Manage Scouts)
            if (link.getAttribute('href') === currentPath || (currentPath === 'edit_scout.php' && link.getAttribute('href') === 'manage_scouts.php')) {
                link.classList.add('sidebar-active');
            }
        }
    }
});

</script>
</body>
</html>
