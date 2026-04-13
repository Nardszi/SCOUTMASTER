<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Boy Scout Admin'; ?></title>
    <?php include('favicon_header.php'); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: url("images/wall3.jpg") no-repeat center center/cover fixed; color: white; }
        body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: -1; }
        .wrapper { display: flex; min-height: 100vh; position: relative; z-index: 1; }
        .main { flex: 1; margin-left: 240px; padding: 30px; position: relative; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-collapsed .main {
            margin-left: 0;
        }
        .main > * { position: relative; z-index: 1; }
        .glass { background: rgba(255,255,255,0.1); backdrop-filter: blur(12px); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.15); }
        .table { color: white; vertical-align: middle; }
        .table thead th { background-color: rgba(0,0,0,0.3); border-bottom: 2px solid rgba(255,255,255,0.1); }
        .table tbody td { background-color: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
        .table-hover tbody tr:hover td { background-color: rgba(255,255,255,0.15); }
        .modal-content { background: rgba(20, 20, 20, 0.95); color: white; border: 1px solid rgba(255, 255, 255, 0.1); }
        .modal-header, .modal-footer { border-color: rgba(255, 255, 255, 0.1); }
        .form-control, .form-select { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,0.2); color: white; border-color: #28a745; box-shadow: none; }
        .form-control::placeholder { color: rgba(255,255,255,0.7); }
        .badge-icon-sm { width: 40px; height: 40px; object-fit: contain; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include('sidebar.php'); ?>
    <div class="main">
        <?php include('navbar.php'); ?>