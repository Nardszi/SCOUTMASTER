<?php
session_start();
include('config.php');

// 1. Security & Setup
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];
if (!in_array($role, ['scout', 'scout_leader', 'admin'])) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['badge_id']) || !is_numeric($_GET['badge_id'])) {
    header('Location: dashboard.php');
    exit();
}

$badge_id = (int)$_GET['badge_id'];

// Determine Scout ID
if ($role === 'scout') {
    $scout_id = $_SESSION['user_id'];
} else {
    // Leaders/Admins must provide scout_id
    if (isset($_GET['scout_id']) && is_numeric($_GET['scout_id'])) {
        $scout_id = (int)$_GET['scout_id'];
    } else {
        die("Error: Scout ID is required to view the certificate.");
    }
}

// 2. Fetch all necessary data for the certificate
$query = "
    SELECT
        scout.name AS scout_name,
        mb.name AS badge_name,
        sbp.date_completed,
        leader.name AS leader_name
    FROM scout_badge_progress sbp
    JOIN users scout ON sbp.scout_id = scout.id
    JOIN merit_badges mb ON sbp.merit_badge_id = mb.id
    LEFT JOIN troops t ON scout.troop_id = t.id
    LEFT JOIN users leader ON t.scout_leader_id = leader.id
    WHERE sbp.scout_id = ? AND sbp.merit_badge_id = ? AND sbp.status = 'completed'
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $scout_id, $badge_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cert_data = mysqli_fetch_assoc($result);

if (!$cert_data || !$cert_data['date_completed']) {
    die("Certificate not available. The badge may not be completed or does not exist.");
}

$scout_name = htmlspecialchars($cert_data['scout_name']);
$badge_name = htmlspecialchars($cert_data['badge_name']);
$completion_date = date("F j, Y", strtotime($cert_data['date_completed']));
$leader_name = htmlspecialchars($cert_data['leader_name'] ?? 'Scout Master');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Achievement - <?php echo $badge_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
            -webkit-print-color-adjust: exact;
        }
        .certificate-container {
            width: 800px;
            height: 565px;
            background-color: #fff;
            border: 10px solid #006e21;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            position: relative;
            text-align: center;
            color: #333;
            background-image: url('images/bsp-logo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 200px;
            background-color: rgba(255, 255, 255, 0.85);
            background-blend-mode: lighten;
        }
        .header {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            color: #004d17;
            margin-bottom: 20px;
        }
        .subtitle { font-size: 24px; margin-bottom: 30px; }
        .recipient {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            color: #28a745;
            border-bottom: 2px solid #333;
            display: inline-block;
            padding-bottom: 5px;
            margin-bottom: 30px;
        }
        .body-text { font-size: 18px; margin-bottom: 40px; }
        .badge-name { font-weight: 700; font-size: 22px; }
        .footer {
            display: flex;
            justify-content: space-around;
            position: absolute;
            bottom: 60px;
            left: 40px;
            right: 40px;
        }
        .signature-line { border-top: 1px solid #333; width: 200px; margin: 0 auto; padding-top: 5px; font-size: 14px; }
        .print-button { margin-top: 20px; padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        @media print {
            body { background-color: #fff; padding: 0; }
            .print-button { display: none; }
            .certificate-container { box-shadow: none; margin: 0; border: 10px solid #006e21; }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="header">Certificate of Achievement</div>
        <div class="subtitle">This certificate is proudly presented to</div>
        <div class="recipient"><?php echo $scout_name; ?></div>
        <div class="body-text">
            For successfully completing all requirements for the<br>
            <span class="badge-name"><?php echo $badge_name; ?></span> Merit Badge.
        </div>
        <div class="footer">
            <div class="signature"><div class="signature-line"><?php echo $completion_date; ?></div><div>Date Completed</div></div>
            <div class="signature"><div class="signature-line"><?php echo $leader_name; ?></div><div>Scout Leader</div></div>
        </div>
    </div>
    <button class="print-button" onclick="window.print()">Print Certificate</button>
</body>
</html>