<?php
session_start();
include('config.php');

// 1. Security & Setup: Ensure user is a scout.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'scout') {
    header('Location: login.php');
    exit();
}
$scout_id = $_SESSION['user_id'];

// Get scout's scout_type
$scout_type = '';
$scout_query = mysqli_prepare($conn, "SELECT scout_type FROM users WHERE id = ?");
mysqli_stmt_bind_param($scout_query, "i", $scout_id);
mysqli_stmt_execute($scout_query);
$scout_result = mysqli_stmt_get_result($scout_query);
if ($scout_row = mysqli_fetch_assoc($scout_result)) {
    $scout_type = $scout_row['scout_type'] ?? '';
}

// 2. Fetch all merit badges with the scout's progress for each.
// Filter by scout_type if available
$badges = [];
$query = "
    SELECT
        mb.id,
        mb.name,
        mb.icon_path,
        mb.is_eagle_required,
        mb.scout_type,
        sbp.status,
        (SELECT COUNT(*) FROM badge_requirements WHERE merit_badge_id = mb.id) as total_reqs,
        (SELECT COUNT(srp.id)
            FROM scout_requirement_progress srp
            WHERE srp.scout_badge_progress_id = sbp.id AND srp.date_approved IS NOT NULL
        ) as approved_reqs
    FROM
        merit_badges mb
    LEFT JOIN
        scout_badge_progress sbp ON mb.id = sbp.merit_badge_id AND sbp.scout_id = ?
";

// Filter by scout type if set
if (!empty($scout_type)) {
    $query .= " WHERE mb.scout_type = ?";
}

$query .= " ORDER BY mb.is_eagle_required DESC, mb.name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($scout_type)) {
    mysqli_stmt_bind_param($stmt, "is", $scout_id, $scout_type);
} else {
    mysqli_stmt_bind_param($stmt, "i", $scout_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $badges[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merit Badges</title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; pointer-events: none; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 0; }
        .main { 
            flex: 1; 
            margin-left: 240px; 
            padding: 30px; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            transition: margin-left 0.3s ease-in-out; 
            z-index: 0; 
        }
        body.sidebar-collapsed .main {
            margin-left: 80px;
        }
        .main > * { position: relative; z-index: 1; pointer-events: auto; }
        .glass {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.15);
            flex: 1; margin-bottom: 20px;
            position: relative;
            z-index: 1;
            pointer-events: auto;
        }

        /* Filter Buttons */
        .filter-btn {
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
        }
        /* Badge Card Styles */
        .badge-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
            position: relative;
            z-index: 2;
            pointer-events: auto;
            touch-action: manipulation;
        }
        .badge-card {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            z-index: 2;
            pointer-events: auto;
        }

        /* All interactive elements */
        button, a, input, select, .filter-btn {
            position: relative;
            z-index: 2;
            pointer-events: auto;
            touch-action: manipulation;
            cursor: pointer;
        }
        .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            background: rgba(0, 0, 0, 0.4);
            border-color: rgba(40, 167, 69, 0.5);
        }
        .badge-card-img-container {
            height: 150px;
            position: relative;
            background-color: rgba(0,0,0,0.2);
            padding: 10px;
        }
        .badge-card-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .eagle-required-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            backdrop-filter: blur(5px);
            background-color: rgba(0,0,0,0.5);
        }
        .badge-card .card-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
        }
        .badge-card .card-title {
            font-weight: 600;
            color: white;
            font-size: 1.1rem;
        }
        .badge-card .progress-container {
            margin-top: auto; /* Pushes this block to the bottom */
        }
        .progress {
            height: 8px;
            background-color: rgba(0,0,0,0.4);
            border-radius: 5px;
            margin-top: 10px;
        }
        .progress-bar {
            background-color: #28a745;
            transition: width 1s ease-in-out;
        }
        .status-badge {
            font-size: 0.8rem;
        }

        /* Ensure mobile menu button is always on top and clickable */
        .mobile-menu-toggle {
            z-index: 99999 !important;
            position: fixed !important;
            pointer-events: auto !important;
            display: none;
            top: 15px !important;
            left: 15px !important;
            width: 44px !important;
            height: 44px !important;
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
            border: none !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4) !important;
            transition: all 0.3s !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .mobile-menu-toggle i {
            font-size: 18px !important;
        }

        /* Ensure nothing blocks the button */
        body, .wrapper, .main, .main::before, .main > * {
            pointer-events: auto;
        }

        .main::before {
            pointer-events: none !important;
        }

        /* Desktop - Ensure sidebar collapse works */
        @media (min-width: 769px) {
            .main {
                margin-left: 240px;
            }
            
            body.sidebar-collapsed .main {
                margin-left: 80px;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block !important;
            }

            /* Fix z-index for mobile menu button */
            .wrapper {
                z-index: 0 !important;
            }

            .main {
                margin-left: 0 !important;
                padding: 80px 15px 15px !important; /* Add top padding for mobile menu button */
                z-index: 0 !important;
            }

            .main::before {
                z-index: -1 !important;
                pointer-events: none !important;
            }

            .main > * {
                z-index: 1 !important;
            }

            body.sidebar-collapsed .main {
                margin-left: 0 !important;
            }

            .glass {
                padding: 15px;
                margin-bottom: 15px;
            }

            h2, .page-title {
                font-size: 24px;
                margin-bottom: 15px;
            }

            /* Filter buttons - touch friendly */
            .filter-btn {
                font-size: 14px;
                padding: 10px 15px;
                margin-bottom: 5px;
                min-height: 44px;
                touch-action: manipulation;
            }

            .btn-group {
                flex-wrap: wrap;
                gap: 5px;
                width: 100%;
            }

            /* Search input */
            .form-control {
                font-size: 16px;
                padding: 12px 15px;
                min-height: 44px;
            }

            /* Badge cards */
            .badge-card {
                margin-bottom: 15px;
            }

            .badge-card-img-container {
                height: 120px;
            }

            .badge-card-body {
                padding: 12px;
            }

            .badge-card .card-title {
                font-size: 1rem;
            }

            .progress {
                height: 6px;
            }

            .status-badge {
                font-size: 0.75rem;
            }

            /* All buttons - touch friendly */
            .btn, button, a {
                min-height: 44px;
                touch-action: manipulation;
                padding: 10px 15px;
                font-size: 14px;
            }

            /* Ensure all interactive elements are touchable */
            .badge-card-link,
            .badge-card,
            button,
            a,
            input,
            select {
                pointer-events: auto !important;
                touch-action: manipulation !important;
            }
        }

        @media (max-width: 576px) {
            .main {
                padding: 70px 10px 10px !important;
            }

            h2, .page-title {
                font-size: 20px;
            }

            .glass {
                padding: 12px 10px;
            }

            .filter-btn {
                font-size: 13px;
                padding: 8px 12px;
            }

            .badge-card-img-container {
                height: 100px;
            }

            .badge-card-body {
                padding: 10px;
            }

            .badge-card .card-title {
                font-size: 0.95rem;
            }

            .btn {
                font-size: 13px;
                padding: 8px 12px;
            }
            
            .mobile-menu-toggle {
                width: 40px !important;
                height: 40px !important;
                top: 12px !important;
                left: 12px !important;
            }
            
            .mobile-menu-toggle i {
                font-size: 16px !important;
            }
        }
    </style>

    <!-- CRITICAL FIX: Ensure mobile button is always clickable -->
    <style>
        @media (max-width: 768px) {
            /* ULTRA FIX: Remove all blocking elements */
            .wrapper,
            .main,
            .glass,
            .main > *,
            .card,
            .badge-card,
            h1, h2, h3, h4, h5, h6,
            div, section {
                pointer-events: none !important;
            }

            /* Only allow pointer events on interactive elements */
            button,
            a,
            input,
            select,
            textarea,
            .btn,
            .filter-btn,
            .badge-card-link,
            .form-control {
                pointer-events: auto !important;
            }

            /* Create safe zone for button - push content down */
            .main {
                padding-top: 90px !important;
            }

            .glass {
                margin-top: 0 !important;
                pointer-events: auto !important;
            }

            /* Force mobile button above everything */
            .mobile-menu-toggle,
            #mobile-menu-toggle,
            button.mobile-menu-toggle {
                z-index: 2147483647 !important;
                position: fixed !important;
                top: 15px !important;
                left: 15px !important;
                display: flex !important;
                pointer-events: auto !important;
                touch-action: manipulation !important;
                -webkit-tap-highlight-color: rgba(40, 167, 69, 0.5) !important;
                background: linear-gradient(135deg, #28a745, #1e7e34) !important;
                color: white !important;
                border: none !important;
                border-radius: 10px !important;
                cursor: pointer !important;
                width: 44px !important;
                height: 44px !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4) !important;
            }
            
            .mobile-menu-toggle i,
            #mobile-menu-toggle i,
            button.mobile-menu-toggle i {
                font-size: 18px !important;
            }

            /* Remove pointer-events from overlays */
            .main::before,
            body::before {
                pointer-events: none !important;
            }

            /* Ensure the button area is completely clear */
            .main > *:first-child {
                margin-top: 20px !important;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include('sidebar.php'); ?>

    <div class="main">
        <?php include('navbar.php'); ?>

        <div class="glass">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="fw-bold"><i class="fas fa-medal me-3"></i>Merit Badges</h1>
            </div>
            
            <?php if (empty($scout_type)): ?>
                <div class="alert alert-warning bg-transparent text-white border-warning mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Scout Type Not Set:</strong> Please contact your scout leader to set your scout type (Boy Scout or Outfit Scout) to see relevant merit badges.
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="row mb-4 g-3 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2" id="badgeFilters">
                        <button class="btn btn-outline-light filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-outline-primary filter-btn" data-filter="in_progress">In Progress</button>
                        <button class="btn btn-outline-success filter-btn" data-filter="completed">Completed</button>
                        <button class="btn btn-outline-secondary filter-btn" data-filter="not_started">Not Started</button>
                        <button class="btn btn-outline-warning filter-btn" data-filter="eagle_required"><i class="fas fa-star me-1"></i>Eagle Required</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="position-relative">
                        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50"></i>
                        <input type="text" id="badgeSearch" class="form-control bg-transparent text-white ps-5" placeholder="Search badges..." style="border-color: rgba(255,255,255,0.3);">
                    </div>
                </div>
            </div>

            <div class="row g-4">
                
                <?php if (empty($badges)): ?>
                    <div class="col-12">
                        <div class="alert alert-info bg-transparent text-white border-info">No merit badges have been added to the system yet.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($badges as $badge): ?>
                        <?php
                            $total_reqs = (int)($badge['total_reqs'] ?? 0);
                            $approved_reqs = (int)($badge['approved_reqs'] ?? 0);
                            $percentage = ($total_reqs > 0) ? round(($approved_reqs / $total_reqs) * 100) : 0;
                            $status = $badge['status'] ?? 'not_started';

                            $status_slug = 'not_started';
                            $status_text = 'Not Started';
                            $status_class = 'bg-secondary';
                            if ($status === 'in_progress') {
                                $status_slug = 'in_progress';
                                $status_text = 'In Progress';
                                $status_class = 'bg-primary';
                            } elseif ($status === 'completed' || ($percentage === 100 && $total_reqs > 0)) {
                                $status_slug = 'completed';
                                $status_text = 'Completed';
                                $status_class = 'bg-success';
                            }
                        ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex badge-item" data-status="<?php echo $status_slug; ?>" data-eagle="<?php echo $badge['is_eagle_required']; ?>">
                            <a href="badge_progress.php?id=<?php echo $badge['id']; ?>" class="badge-card-link w-100">
                                <div class="card badge-card">
                                    <div class="badge-card-img-container">
                                        <img src="<?php echo htmlspecialchars($badge['icon_path'] ?? 'images/default_badge.png'); ?>" class="badge-card-img" alt="<?php echo htmlspecialchars($badge['name']); ?>">
                                        <?php if ($badge['is_eagle_required']): ?>
                                            <span class="badge eagle-required-badge"><i class="fas fa-star me-1 text-warning"></i> Eagle Required</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($badge['name']); ?></h5>
                                        <div class="progress-container">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                <small class="text-white-50"><?php echo $approved_reqs . ' / ' . $total_reqs; ?> Approved</small>
                                            </div>
                                            <div class="progress mt-2">
                                                <div class="progress-bar" role="progressbar" style="width: 0%;" data-width="<?php echo $percentage; ?>" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Animate progress bars when they scroll into view
        const progressBars = document.querySelectorAll('.badge-card .progress-bar');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bar = entry.target;
                    const targetWidth = bar.getAttribute('data-width');
                    bar.style.width = targetWidth + '%';
                    observer.unobserve(bar); // Stop observing once animated
                }
            });
        }, { threshold: 0.1 }); // Trigger when 10% of the element is visible

        progressBars.forEach(bar => {
            observer.observe(bar);
        });
    });
    const searchInput = document.getElementById('badgeSearch');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const badgeItems = document.querySelectorAll('.badge-item');

    function filterBadges() {
        const searchValue = searchInput.value.toLowerCase();
        const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;

        badgeItems.forEach(item => {
            const title = item.querySelector('.card-title').textContent.toLowerCase();
            const status = item.dataset.status;
            const isEagle = item.dataset.eagle === '1';
            
            let matchesSearch = title.includes(searchValue);
            let matchesFilter = (activeFilter === 'all') || 
                                (activeFilter === 'eagle_required' && isEagle) || 
                                (activeFilter === status);

            if (matchesSearch && matchesFilter) {
                item.classList.remove('d-none');
            } else {
                item.classList.add('d-none');
            }
        });
    }

    searchInput.addEventListener('keyup', filterBadges);

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterBadges();
        });
    });
</script>
</body>
</html>