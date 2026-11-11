<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('includes/functions.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

if (!isset($account_id) || !is_numeric($account_id)) {
    die("Error: Academic staff ID not found. Please log in again.");
}

$date = $_GET['date'] ?? date('Y-m-d');
$categories = getEventCategories($pdo);
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("INSERT INTO tbl_events 
        (title, description, event_date, start_time, end_time, venue, category, color, posted_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['event_date'],
        $_POST['start_time'] ?: null,
        $_POST['end_time'] ?: null,
        $_POST['venue'],
        $_POST['category'],
        $_POST['color'],
        $account_id
    ]);
    
    $success = true;
    // Reset date to the newly saved event's date for convenience (but we'll keep the form for another entry)
    $date = $_POST['event_date'];
    // You can optionally clear the form fields here – we'll keep them as is, but we'll add a reset link.
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Event</title>
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(145deg, #f0f4ff 0%, #e9eff8 100%); }
        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,0.6);
            transition: all 0.3s;
        }
        .form-control, .form-select {
            border-radius: 28px;
            padding: 0.75rem 1.2rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }
        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1e293b;
        }
        .btn-3d {
            border-radius: 40px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .btn-3d:active {
            transform: translateY(2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            color: white !important;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59,130,246,0.3);
        }
        .btn-secondary {
            background: rgba(100,116,139,0.1);
            border: 1px solid #cbd5e1;
            color: #1e293b !important;
        }
        .btn-secondary:hover {
            background: #64748b;
            color: white !important;
            border-color: #64748b;
        }
        .alert-glass {
            background: rgba(16,185,129,0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 60px;
            padding: 1rem 1.5rem;
            color: #065f46;
            font-weight: 500;
        }
        .app-title h1 {
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
    </style>
</head>
<body class="app sidebar-mini">
<header class="app-header">
    <a class="app-header__logo" href="#">SRMS</a>
    <a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a>
    <ul class="app-nav">
        <li class="dropdown">
            <a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a>
            <ul class="dropdown-menu settings-menu dropdown-menu-right">
                <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </li>
    </ul>
</header>
<?php require 'inc/sidebar_academic.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-plus-circle me-2"></i> Add Event</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="academic">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="academic/event-dashboard.php">Calendar</a></li>
            <li class="breadcrumb-item active">Add Event</li>
        </ul>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-glass alert-dismissible fade show mb-4 d-flex justify-content-between align-items-center">
            <div><i class="bi bi-check-circle-fill me-2 fs-5"></i> Event saved successfully!</div>
            <div>
                <a href="academic/event-dashboard.php" class="btn btn-sm btn-outline-primary rounded-pill me-2">Go to Dashboard</a>
                <a href="academic/event-add.php" class="btn btn-sm btn-outline-secondary rounded-pill">Add Another</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="tile">
                <form method="POST" id="eventForm">
                    <div class="mb-4">
                        <label><i class="bi bi-pencil-square me-1"></i> Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g., Staff Meeting, Final Exam">
                    </div>
                    <div class="mb-4">
                        <label><i class="bi bi-text-paragraph me-1"></i> Description</label>
                        <textarea name="description" rows="4" class="form-control" placeholder="Optional details..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label><i class="bi bi-calendar-date me-1"></i> Date</label>
                        <input type="date" name="event_date" value="<?= htmlspecialchars($date) ?>" class="form-control" required>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label><i class="bi bi-clock me-1"></i> Start Time</label>
                            <input type="time" name="start_time" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label><i class="bi bi-clock-history me-1"></i> End Time</label>
                            <input type="time" name="end_time" class="form-control">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label><i class="bi bi-geo-alt me-1"></i> Venue / Location</label>
                        <input type="text" name="venue" class="form-control" placeholder="e.g., Main Hall, Room A12">
                    </div>
                    <div class="mb-4">
                        <label><i class="bi bi-tag me-1"></i> Category</label>
                        <select name="category" class="form-select">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label><i class="bi bi-palette me-1"></i> Color</label>
                        <input type="color" name="color" class="form-control w-25" value="#4f46e5" style="height: 50px; border-radius: 30px;">
                    </div>
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary btn-3d"><i class="bi bi-save me-1"></i> Save Event</button>
                        <a href="academic/event-dashboard.php" class="btn btn-secondary btn-3d"><i class="bi bi-x-circle me-1"></i> Cancel</a>
                        <button type="reset" class="btn btn-outline-secondary btn-3d"><i class="bi bi-arrow-repeat me-1"></i> Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
    // Optional: Auto-dismiss success alert after 5 seconds
    setTimeout(function() {
        let alert = document.querySelector('.alert-glass');
        if (alert) {
            let bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
</script>
</body>
</html>