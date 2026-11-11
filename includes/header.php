<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Everlasting Academy - Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/calendar.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a, #1e293b, #312e81); font-family: 'Segoe UI', system-ui; color: #f1f5f9; margin:0; padding:0; }
        .glass-nav { background: rgba(15,23,42,0.6); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .glass-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); border-radius: 24px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .btn-glass { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; transition: all 0.3s ease; }
        .btn-glass:hover { background: rgba(67,97,238,0.7); transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
<nav class="navbar glass-nav"><div class="container-fluid"><a class="navbar-brand text-white fw-bold" href="../public/year.php"><i class="bi bi-calendar3"></i> School Calendar</a>
<div class="d-flex"><?php if(isset($_SESSION['academic_id'])): ?><span class="text-light me-3">Academic Staff</span><a href="../academic/logout.php" class="btn btn-sm btn-outline-light">Logout</a><?php else: ?><a href="../academic/login.php" class="btn btn-sm btn-outline-light">Staff Login</a><?php endif; ?></div></div></nav>
<div class="container py-4">