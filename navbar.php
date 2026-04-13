<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('config.php');

// Fetch current user info
$nav_user_id = $_SESSION['user_id'] ?? 0;
$nav_profile_pic = 'images/default_profile.png'; // Default image
$nav_role = $_SESSION['role'] ?? '';
$nav_user_data = [];

if ($nav_user_id) {
    $nav_query = "SELECT u.name, u.email, u.role, u.profile_picture, u.school, r.rank_name 
                  FROM users u 
                  LEFT JOIN ranks r ON u.rank_id = r.id
                  WHERE u.id = ?";
    $nav_stmt = mysqli_prepare($conn, $nav_query);
    mysqli_stmt_bind_param($nav_stmt, "i", $nav_user_id);
    mysqli_stmt_execute($nav_stmt);
    $nav_res = mysqli_stmt_get_result($nav_stmt);
    if ($nav_row = mysqli_fetch_assoc($nav_res)) {
        $nav_user_data = $nav_row;
        if (!empty($nav_row['profile_picture']) && file_exists($nav_row['profile_picture'])) {
            $nav_profile_pic = $nav_row['profile_picture'];
        }
    }
}

// Determine profile link based on role
// This link will be used for the "Edit Full Profile" button inside the modal
$profile_link = '#';
if ($nav_role === 'scout') {
    $profile_link = 'profile.php';
} elseif ($nav_role === 'scout_leader') {
    $profile_link = 'scout_leader_profile.php';
}
?>

<style>
    .top-navbar {
        background: transparent;
        border-radius: 15px;
        padding: 10px 20px;
        margin-bottom: 20px;
        position: sticky !important;
        top: 20px;
        z-index: 1040 !important;
        transition: all 0.3s ease-in-out;
        pointer-events: auto; /* Navbar itself is clickable */
    }
    
    /* Make navbar children clickable */
    .top-navbar * {
        pointer-events: auto !important;
    }
    .top-navbar.landing-nav {
        border-radius: 0;
        margin-bottom: 0;
        padding: 15px 25px;
    }
    .top-navbar.landing-nav.scrolled {
  
    }
    .search-input {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        border-radius: 20px;
        padding-left: 35px;
        width: 100%;
    }
    .search-input::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }
    .search-input:focus {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        box-shadow: none;
    }
    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.7);
        z-index: 10;
    }
    .profile-img-nav {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #28a745;
    }

    /* Profile Dropdown Styles */
    .dropdown-profile-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #28a745;
        margin: 0 auto 10px;
    }

    .dropdown-menu.profile-dropdown {
        background: rgba(30, 30, 30, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 0;
        width: 280px;
        z-index: 1050 !important;
        position: absolute !important;
    }
    
    .dropdown-menu.show {
        z-index: 1050 !important;
    }
    
    .nav-item.dropdown {
        position: relative;
        z-index: 1049;
    }

    .dropdown-menu.profile-dropdown .dropdown-item {
        color: rgba(255,255,255,0.8);
        padding: 10px 20px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .dropdown-menu.profile-dropdown .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    .dropdown-profile-card {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Profile Modal Styles (used by Logout Modal) */
    .modal-content.profile-modal {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        color: white;
    }
    .modal-header.profile-modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }
    .modal-body.profile-modal-body {
        text-align: center;
    }
    #logoutModal {
        z-index: 100000 !important;
        position: fixed !important;
    }

    /* Mobile Responsive Styles for Logout Modal */
    @media (max-width: 768px) {
        /* Navbar mobile adjustments */
        .top-navbar {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            margin: 0;
            padding: 10px 15px;
            border-radius: 0;
            background: linear-gradient(90deg, #000000 0%, #006e21 100%) !important;
            backdrop-filter: blur(10px);
            z-index: 1040 !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        /* Ensure navbar background stays consistent */
        .top-navbar.landing-nav,
        .top-navbar.scrolled {
            background: linear-gradient(90deg, #000000 0%, #006e21 100%) !important;
        }
        
        .top-navbar .container-fluid {
            padding: 0;
        }
        
        /* Adjust navbar content to account for toggle button */
        .top-navbar .navbar-nav {
            margin-left: auto;
            padding-right: 0;
        }
        
        /* Profile image and name alignment */
        .top-navbar .nav-link {
            padding: 5px 0;
        }
        
        .profile-img-nav {
            width: 38px;
            height: 38px;
            border: 2px solid #28a745;
        }
        
        /* Hide search on mobile */
        .top-navbar .position-relative {
            display: none !important;
        }
        
        #logoutModal .modal-dialog {
            margin: 1rem;
            max-width: calc(100% - 2rem);
        }
        
        #logoutModal .modal-content.profile-modal {
            border-radius: 15px;
        }
        
        #logoutModal .modal-header.profile-modal-header {
            padding: 1rem;
        }
        
        #logoutModal .modal-body.profile-modal-body {
            padding: 1.5rem 1rem;
        }
        
        #logoutModal .modal-body.profile-modal-body p {
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        #logoutModal .modal-footer {
            padding: 1rem;
            gap: 0.5rem;
        }
        
        #logoutModal .modal-footer .btn {
            padding: 0.5rem 1.5rem;
            font-size: 0.95rem;
        }
    }

    @media (max-width: 576px) {
        .top-navbar {
            padding: 8px 12px;
        }
        
        .profile-img-nav {
            width: 36px;
            height: 36px;
        }
        
        .top-navbar .nav-link span {
            font-size: 0.9rem;
        }
        
        #logoutModal .modal-dialog {
            margin: 0.5rem;
            max-width: calc(100% - 1rem);
        }
        
        #logoutModal .modal-content.profile-modal {
            border-radius: 12px;
        }
        
        #logoutModal .modal-header.profile-modal-header {
            padding: 0.875rem;
        }
        
        #logoutModal .modal-header .modal-title {
            font-size: 1.1rem;
        }
        
        #logoutModal .modal-body.profile-modal-body {
            padding: 1.25rem 0.875rem;
        }
        
        #logoutModal .modal-body.profile-modal-body p {
            font-size: 0.95rem;
        }
        
        #logoutModal .modal-footer {
            padding: 0.875rem;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        #logoutModal .modal-footer .btn {
            width: 100%;
            padding: 0.625rem;
            font-size: 0.9rem;
        }
    }
</style>

<nav class="navbar navbar-expand navbar-dark top-navbar">
    <div class="container-fluid">

        <!-- Search Bar -->
        <div class="position-relative d-none d-md-block" style="width: 300px;">
            <i class="fas fa-search search-icon"></i>
            <input class="form-control search-input" type="search" placeholder="Search..." id="globalSearch">
        </div>

        <!-- Profile Dropdown -->
        <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" class="profile-img-nav">
                    <span class="ms-2 text-white fw-bold d-none d-sm-block"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow profile-dropdown" aria-labelledby="navbarDropdown">
                    <?php if ($nav_role !== 'admin'): ?>
                    <li>
                        <div class="px-4 py-3 text-center dropdown-profile-card">
                            <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" class="dropdown-profile-img">
                            <h5 class="fw-bold mt-2 mb-0 text-white"><?php echo htmlspecialchars($nav_user_data['name'] ?? 'User'); ?></h5>
                            <small class="text-white-50"><?php echo htmlspecialchars($nav_user_data['email'] ?? 'N/A'); ?></small>
                        </div>
                    </li>
                    <li>
                        <a href="<?php echo $profile_link; ?>" class="dropdown-item">
                            <i class="fas fa-user-edit me-2"></i> View Full Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider m-0" style="border-color: rgba(255,255,255,0.1);"></li>
                    <?php endif; ?>
                    <li>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Log Out</a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content profile-modal">
      <div class="modal-header profile-modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body profile-modal-body">
        <p>Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <a href="logout_process.php" class="btn btn-danger btn-sm">Log Out</a>
      </div>
    </div>
  </div>
</div>

<script>
    // Move logout modal to body to ensure it appears on top of everything
    document.addEventListener('DOMContentLoaded', function() {
        var logoutModal = document.getElementById('logoutModal');
        if (logoutModal) {
            document.body.appendChild(logoutModal);
        }
    });
</script>