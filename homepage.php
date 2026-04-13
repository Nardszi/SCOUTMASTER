<?php
// Include database connection
include('config.php');

// Fetch events data to be used later in the body
$events = [];
$current_date = date('Y-m-d');
if ($conn) {
    $query = "SELECT events.*, users.name AS organizer
              FROM events
              LEFT JOIN users ON events.scout_leader_id = users.id
              WHERE events.status='approved' AND events.event_date > '$current_date'
              ORDER BY event_date ASC
              LIMIT 9"; // Show upcoming approved events

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $events[] = $row;
        }
    }

    // Fetch Statistics
    $total_scouts = 0;
    $total_events = 0;
    $total_badges = 0;

    $scout_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'scout'");
    if ($scout_res) $total_scouts = mysqli_fetch_assoc($scout_res)['total'];

    $event_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM events WHERE status = 'approved'");
    if ($event_res) $total_events = mysqli_fetch_assoc($event_res)['total'];

    $badge_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM merit_badges");
    if ($badge_res) $total_badges = mysqli_fetch_assoc($badge_res)['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Master</title>
    <?php include('favicon_header.php'); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: #000;
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        /* --- NAVBAR --- */
        .navbar {
            transition: background-color 0.4s ease;
            padding: 1rem 1.5rem;
        }
        .navbar.scrolled {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .nav-link {
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-link:hover {
            color: #28a745 !important;
        }

        /* --- HERO SECTION --- */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/wall3.jpg') no-repeat center center/cover;
            position: relative;
            background-attachment: fixed;
            padding: 100px 0;
        }
        .hero-content {
            position: relative;
            z-index: 1;
        }
        .hero-content h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 800;
            text-shadow: 0 4px 15px rgba(0,0,0,0.5);
            animation: fadeInDown 1s ease-out;
            margin-top: -4rem;
            color: rgb(255, 255, 255);

         
        }
        .hero-content p {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            max-width: 650px;
            margin: 0.5rem auto 0;
            color: rgba(255,255,255,0.85);
            animation: fadeInUp 1s ease-out 0.5s;
            animation-fill-mode: backwards;
        }
        .hero-logo {
            width: 500px;
            height: auto;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 0 20px rgba(25, 151, 36, 0.58));
            animation: float 6s ease-in-out infinite;
        }

        /* --- GENERAL SECTION STYLING --- */
        .section {
            padding: 80px 0;
        }
        #events {
            background: linear-gradient(to bottom, #0a1a0f, #000000);
        }
        section[id], #about, #contact {
            scroll-margin-top: 80px;
        }
        .section-title {
            text-align: center;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 50px;
            position: relative;
        }
        .section-title::after {
            content: '';
            width: 80px;
            height: 4px;
            background: #28a745;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        /* --- EVENTS SECTION --- */
        .event-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }
        .event-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .event-card-body {
            padding: 20px;
        }
        .event-card-body h5 {
            font-weight: 600;
        }
        .event-card-body small {
            color: rgba(255,255,255,0.7);
        }

        /* --- BADGES/GALLERY SECTION --- */
        .scout-images {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }
        .scout-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        .scout-img:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        /* --- ABOUT/CONTACT SECTION --- */
        .info-section {
            background: linear-gradient(to bottom, #0a1a0f, #000000);
        }
        #gallery {
            background: linear-gradient(to bottom, #000000, #0a1a0f);
        }
        .glass-info {
            background: rgba(255,255,255,0.05);
            padding: 30px;
            border-radius: 15px;
            height: 100%;
        }
        .glass-info h3 {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 15px;
        }

        /* --- FOOTER --- */
        footer {
            background-color: #050505;
            padding: 20px 0;
            text-align: center;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.5);
        }
        
        /* --- MODAL --- */
        .modal-content {
            background: #1c1c1c;
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .modal-img {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }

        /* --- ANIMATIONS --- */
        .animated-section {
            opacity: 0;
            transition: opacity 1s, transform 1s;
            transform: translateY(50px);
        }
        .animated-section.visible {
            opacity: 1;
            transform: translateY(0);
        }
         a:hover {
            color: #218838;
            text-decoration: underline;
        }
        .nav-link:hover, .nav-link.active {
            color: #28a745 !important;
            text-shadow: 0 0 10px rgba(40, 167, 69, 0.8);
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .scroll-down {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 2rem;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateX(-50%); }
            40% { transform: translateY(-10px) translateX(-50%); }
            60% { transform: translateY(-5px) translateX(-50%); }
        }

        
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: #28a745; }
        }

        /* --- CARD ANIMATION --- */
        .animate-card {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .animate-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* --- STATS SECTION --- */
        .stats-section {
            background: transparent;
            width: 100%;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            padding: 0;
            transition: max-height 0.7s ease-in-out, opacity 0.5s ease-in-out, padding 0.7s ease-in-out;
        }
        .stats-section.show {
            max-height: 500px; /* Adjust if content is taller */
            opacity: 1;
            padding: 40px 0 0;
        }
        .stats-toggle-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px auto 0;
            backdrop-filter: blur(5px);
        }
        .stats-toggle-btn:hover {
            background: rgba(40, 167, 69, 0.8);
            transform: scale(1.1);
        }
        .stat-item {
            padding: 10px ;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            transition: transform 0.3s ease;
        }
        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #28a745;
            margin-bottom: 10px;
            display: inline-block;
        }
        .counter {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0;
            color: white;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .navbar-brand {
                font-size: 1.2rem;
            }

            .nav-link {
                font-size: 14px;
                padding: 0.5rem 1rem;
            }

            .navbar-collapse {
                background: rgba(0, 0, 0, 0.95);
                padding: 1rem;
                border-radius: 10px;
                margin-top: 1rem;
            }

            .hero {
                height: auto;
                min-height: 100vh;
                padding: 100px 20px 60px;
                background-attachment: scroll;
            }

            .hero-logo {
                width: 280px;
                margin-bottom: 1rem;
            }

            .hero-content h1 {
                font-size: 2.2rem;
                margin-top: -2rem;
            }

            .hero-content p {
                font-size: 1rem;
                padding: 0 10px;
            }

            .stats-toggle-btn {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
                margin-top: 15px;
            }

            .stats-section.show {
                max-height: 800px;
                padding: 30px 0 0;
            }

            .scroll-down {
                bottom: 20px;
                font-size: 1.5rem;
            }

            .section {
                padding: 50px 15px;
            }

            .section-title {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .section-title::after {
                width: 60px;
                height: 3px;
            }

            .stat-item {
                margin-bottom: 15px;
                padding: 20px 15px;
            }

            .counter {
                font-size: 2.5rem;
            }

            .stat-icon {
                font-size: 2rem;
                margin-bottom: 8px;
            }

            .stat-item p {
                font-size: 0.9rem;
            }

            .event-card {
                margin-bottom: 20px;
            }

            .event-card img {
                height: 180px;
            }

            .event-card-body {
                padding: 15px;
            }

            .event-card-body h5 {
                font-size: 1.1rem;
            }

            .event-card-body small {
                font-size: 0.85rem;
            }

            .scout-images {
                gap: 15px;
            }

            .scout-img {
                width: 100px;
                height: 100px;
            }

            .glass-info {
                padding: 20px;
                margin-bottom: 20px;
            }

            .glass-info h3 {
                font-size: 1.3rem;
                margin-bottom: 12px;
            }

            .glass-info p {
                font-size: 0.95rem;
            }

            .glass-info ul li {
                font-size: 0.9rem;
            }

            footer {
                padding: 20px 15px;
            }

            footer p {
                font-size: 14px;
            }

            .modal-dialog {
                margin: 1rem;
            }

            .modal-body {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                padding: 0.5rem 0.75rem;
            }

            .navbar-brand {
                font-size: 1rem;
            }

            .nav-link {
                font-size: 13px;
                padding: 0.4rem 0.75rem;
            }

            .navbar-collapse {
                padding: 0.75rem;
                margin-top: 0.75rem;
            }

            .hero {
                padding: 80px 15px 50px;
                min-height: 100vh;
            }

            .hero-logo {
                width: 220px;
                margin-bottom: 0.75rem;
            }

            .hero-content h1 {
                font-size: 1.75rem;
                margin-top: -1.5rem;
            }

            .hero-content p {
                font-size: 0.9rem;
                padding: 0 5px;
                max-width: 100%;
            }

            .stats-toggle-btn {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                margin-top: 12px;
            }

            .stats-section.show {
                max-height: 900px;
                padding: 25px 0 0;
            }

            .scroll-down {
                bottom: 15px;
                font-size: 1.3rem;
            }

            .section {
                padding: 40px 10px;
            }

            .section-title {
                font-size: 1.6rem;
                margin-bottom: 25px;
            }

            .section-title::after {
                width: 50px;
                height: 3px;
            }

            .stat-item {
                margin-bottom: 12px;
                padding: 18px 12px;
            }

            .counter {
                font-size: 2rem;
            }

            .stat-icon {
                font-size: 1.75rem;
                margin-bottom: 6px;
            }

            .stat-item p {
                font-size: 0.85rem;
                margin-bottom: 0;
            }

            .event-card img {
                height: 160px;
            }

            .event-card-body {
                padding: 12px;
            }

            .event-card-body h5 {
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .event-card-body small {
                font-size: 0.8rem;
                line-height: 1.4;
            }

            .scout-images {
                gap: 12px;
            }

            .scout-img {
                width: 80px;
                height: 80px;
            }

            .glass-info {
                padding: 18px;
                margin-bottom: 15px;
            }

            .glass-info h3 {
                font-size: 1.2rem;
                margin-bottom: 10px;
            }

            .glass-info p {
                font-size: 0.9rem;
                line-height: 1.5;
            }

            .glass-info ul li {
                font-size: 0.85rem;
                margin-bottom: 8px;
            }

            footer {
                padding: 18px 12px;
            }

            footer p {
                font-size: 13px;
                margin-bottom: 0;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-header {
                padding: 0.875rem;
            }

            .modal-title {
                font-size: 1.1rem;
            }

            .modal-body {
                padding: 0.875rem;
            }

            .modal-img {
                border-radius: 8px;
            }
        }

        /* Extra small devices */
        @media (max-width: 375px) {
            .hero-logo {
                width: 180px;
            }

            .hero-content h1 {
                font-size: 1.5rem;
            }

            .hero-content p {
                font-size: 0.85rem;
            }

            .section-title {
                font-size: 1.4rem;
            }

            .counter {
                font-size: 1.75rem;
            }

            .stat-icon {
                font-size: 1.5rem;
            }

            .scout-img {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                SCOUT MASTER
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php#events">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="homepage.php#about">About</a></li>
                <li class="nav-item"><a class="nav-link " href="login.php">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <img src="images/homelogo.png" alt="Scout Logo" class="hero-logo" >
            <h1>Be Prepared. For Life.</h1>
            <p>Join a community dedicated to building character, leadership, and adventure.</p>
            <div class="stats-toggle-btn" onclick="toggleStats()" title="View Statistics">
                <i class="bi bi-bar-chart-fill"></i>
            </div>
        </div>

        <!-- STATISTICS SECTION -->
        <div class="stats-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-4 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-item">
                            <i class="bi bi-people-fill stat-icon"></i>
                            <h2 class="counter" data-target="<?= $total_scouts ?>">0</h2>
                            <p class="text-white-50">Registered Scouts</p>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-item">
                            <i class="bi bi-calendar-check-fill stat-icon"></i>
                            <h2 class="counter" data-target="<?= $total_events ?>">0</h2>
                            <p class="text-white-50">Events Held</p>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="stat-item">
                            <i class="bi bi-award-fill stat-icon"></i>
                            <h2 class="counter" data-target="<?= $total_badges ?>">0</h2>
                            <p class="text-white-50">Merit Badges</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="#events" class="scroll-down"><i class="bi bi-chevron-down"></i></a>
    </div>

    <!-- UPCOMING EVENTS SECTION -->
    <section id="events" class="section">
        <div class="container">
            <h2 class="section-title">Upcoming Events</h2>
            <div class="row g-4 justify-content-center">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="col-lg-4 col-md-6 animate-card">
                            <div class="event-card">
                                <?php
                                $image_path = $event['event_image'];
                                if (empty($image_path) || !file_exists($image_path)) {
                                    $image_path = 'images/default_event.png';
                                }
                                ?>
                                <img src="<?= htmlspecialchars($image_path); ?>" alt="Event Image">
                                <div class="event-card-body">
                                    <h5><?= htmlspecialchars($event['event_title']); ?></h5>
                                    <small>
                                        <i class="bi bi-calendar-event"></i> <?= date("M d, Y", strtotime($event['event_date'])); ?><br>
                                        <i class="bi bi-person-fill"></i> Organizer: <?= htmlspecialchars($event['organizer'] ?? 'N/A'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>No upcoming events found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- BADGES/GALLERY SECTION -->
    <section id="gallery" class="section animated-section">
        <div class="container">
            <h2 class="section-title">Gallery & Badges</h2>
            <div class="scout-images mt-4">
                <img src="images/im1.png" alt="Scout Image 1" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/2.png', 'Membership Badge')">
                <img src="images/im2.png" alt="Scout Image 2" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/3.png', 'Tenderfoot Scout')">
                <img src="images/im3.png" alt="Scout Image 3" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/4.png', 'Second Class Scout')">
                <img src="images/im4.png" alt="Scout Image 4" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/5.png', 'First Class Scout')">
                <img src="images/im5.png" alt="Scout Image 5" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/6.png', 'Scout Service')">
                <img src="images/im6.png" alt="Scout Image 6" class="scout-img" data-bs-toggle="modal" data-bs-target="#imageModal" onclick="showImage('images/7.png', 'Scout Citizen')">
            </div>
        </div>
    </section>

    <!-- ABOUT & CONTACT SECTION -->
    <section class="section info-section">
        <div class="container">
            <div class="row g-4">
                <div id="about" class="col-lg-6 animated-section">
                    <div class="glass-info h-100">
                        <h3><i class="bi bi-info-circle-fill me-2"></i> About Us</h3>
                        <p>The Boy Scouts of the Philippines is one of the oldest and largest scouting organizations in the world, dedicated to developing young people into responsible citizens and leaders. Our mission is to instill in young people the values of the Scout Oath and Law through a unique program of learning by doing.</p>
                    </div>
                </div>
                <div id="contact" class="col-lg-6 animated-section">
                    <div class="glass-info h-100">
                        <h3><i class="bi bi-telephone-fill me-2"></i> Contact Us</h3>
                        <p>For any inquiries or support, please reach out to your local council office. We are here to help you on your scouting journey.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-envelope me-2"></i> <strong>Email:</strong> scoutmaster@.org.ph</li>
                            <li class="mb-2"><i class="bi bi-phone me-2"></i> <strong>Phone:</strong> (09)062585212</li>
                            <li><i class="bi bi-geo-alt me-2"></i> <strong>Address:</strong> Victorias City,Negros Occidental,Philippines</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; <?= date("Y"); ?> Boy Scout Management System. All Rights Reserved.</p>
            <p>Crafted with <i class="bi bi-heart-fill text-danger"></i> by Giren Nard Dawn</p>
        </div>
    </footer>

    <!-- MODAL FOR IMAGES -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageTitle">Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" class="modal-img" src="" alt="Enlarged Image">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Modal image handler
        function showImage(imageSrc, title) {
            document.getElementById("modalImage").src = imageSrc;
            document.getElementById("imageTitle").textContent = title;
        }

        // Section animation on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.15
        });

        document.querySelectorAll('.animated-section').forEach(section => {
            observer.observe(section);
        });

        document.querySelectorAll('.animate-card').forEach((card, index) => {
            // Stagger the animation for each card
            card.style.transitionDelay = `${index * 100}ms`;
            observer.observe(card);
        });

        // Counter Animation
        const counters = document.querySelectorAll('.counter');
        const speed = 200; 

        const animateCounters = () => {
            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-target');
                    const count = +counter.innerText;
                    const inc = target / speed;
                    if (count < target) {
                        counter.innerText = Math.ceil(count + inc);
                        setTimeout(updateCount, 20);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
            });
        }

        function toggleStats() {
            const statsSection = document.querySelector('.stats-section');
            const toggleButton = document.querySelector('.stats-toggle-btn');
            const toggleIcon = toggleButton.querySelector('i');
            const isShowing = statsSection.classList.toggle('show');

            if (isShowing) {
                toggleIcon.classList.replace('bi-bar-chart-fill', 'bi-x-lg');
                toggleButton.setAttribute('title', 'Close');
                // Reset counters to 0 before animating
                document.querySelectorAll('.counter').forEach(counter => {
                    counter.innerText = '0';
                });
                // Animate counters
                animateCounters();
            } else {
                toggleIcon.classList.replace('bi-x-lg', 'bi-bar-chart-fill');
                toggleButton.setAttribute('title', 'View Statistics');
            }
        }

        // Auto-hide statistics when scrolling away
        const statsContainer = document.querySelector('.stats-section');
        if (statsContainer) {
            const toggleButton = document.querySelector('.stats-toggle-btn');
            const autoHideObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting && statsContainer.classList.contains('show')) {
                        statsContainer.classList.remove('show');
                        const toggleIcon = toggleButton.querySelector('i');
                        if (toggleIcon) toggleIcon.classList.replace('bi-x-lg', 'bi-bar-chart-fill');
                        if (toggleButton) toggleButton.setAttribute('title', 'View Statistics');
                    }
                });
            }, { threshold: 0 });
            autoHideObserver.observe(statsContainer);
        }
    </script>
</body>
</html>
