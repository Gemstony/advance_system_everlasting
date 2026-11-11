<?php
chdir(__DIR__);
session_start();
require_once('db/config.php');
require_once('const/school.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Maintenance Mode</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="./">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --warning: #ffb703;
            --dark: #1e293b;
            --border-radius: 24px;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: 1px solid rgba(255, 255, 255, 0.3);
            --card-shadow: 0 20px 35px -8px rgba(0,0,0,0.1), 0 10px 15px -6px rgba(0,0,0,0.05);
        }
        body {
            background: linear-gradient(145deg, #f0f4fa 0%, #e6ecf4 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .maintenance-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            padding: 3rem;
            max-width: 500px;
            text-align: center;
        }
        .maintenance-card i {
            font-size: 5rem;
            color: var(--warning);
            margin-bottom: 1rem;
        }
        .maintenance-card h1 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        .maintenance-card p {
            color: #64748b;
            margin-bottom: 2rem;
        }
        .btn-logout {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 40px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <i class="bi bi-tools"></i>
        <h1>Maintenance Mode</h1>
        <p>The system is currently undergoing scheduled maintenance. Please check back later.</p>
        <?php if (isset($_SESSION['staff']) || isset($_SESSION['student'])): ?>
            <a href="logout" class="btn-logout">Logout</a>
        <?php else: ?>
            <a href="index" class="btn-logout">Go to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>