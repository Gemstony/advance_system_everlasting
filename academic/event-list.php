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

// Prepare staff array for the unified header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM tbl_events WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: academic/event-list.php?msg=deleted" . ($dateFilter ? "&date=$dateFilter" : ""));
    exit;
}

$sql = "SELECT e.*, CONCAT(s.fname, ' ', s.lname) as posted_by_name 
        FROM tbl_events e 
        LEFT JOIN tbl_staff s ON e.posted_by = s.id
        WHERE 1=1";
$params = [];

if ($dateFilter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $sql .= " AND e.event_date = ?";
    $params[] = $dateFilter;
}
if ($search) {
    $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterCategory && $filterCategory != 'all') {
    $sql .= " AND e.category = ?";
    $params[] = $filterCategory;
}
$sql .= " ORDER BY e.event_date DESC, e.start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$categories = getEventCategories($pdo);

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $msg = '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i> Event deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event List - SRMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        /* Additional page styles – supplements the unified header theme */
        .app-content {
            background: transparent !important;
        }

        /* Glass filter bar – subtle, light */
        .filter-bar {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(12px);
            border-radius: 32px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 15px 35px -12px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.8);
            transition: all 0.3s;
        }

        .filter-bar .form-control, .filter-bar .form-select {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            padding: 0.6rem 1.2rem;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .filter-bar .form-control:focus, .filter-bar .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .filter-bar label {
            color: #1e293b;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 0.5rem;
        }

        /* Buttons with glossy 3D effect */
        .btn-3d {
            border-radius: 40px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05), inset 0 1px 0 rgba(255,255,255,0.6);
        }
        .btn-3d:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 8px 18px -8px rgba(37,99,235,0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: white;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Premium event cards – white with glossy edges */
        .card-event {
            background: white;
            border-radius: 32px;
            border: none;
            transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 0 12px 25px -12px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.8);
            overflow: hidden;
            position: relative;
        }
        .card-event::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, rgba(255,255,255,0) 30%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 70%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .card-event:hover::after {
            opacity: 1;
        }
        .card-event:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 40px -16px rgba(0,0,0,0.2), 0 0 0 1px rgba(59,130,246,0.2);
        }
        .card-event .card-body {
            padding: 1.5rem;
        }
        .card-event .card-title {
            font-weight: 800;
            font-size: 1.2rem;
            color: #0f172a;
            letter-spacing: -0.2px;
        }
        .card-event .card-text {
            color: #475569;
            font-size: 0.85rem;
        }
        .badge.bg-secondary {
            background: #f1f5f9 !important;
            color: #1e293b;
            font-weight: 600;
            border-radius: 30px;
            padding: 0.3rem 0.8rem;
            border: 1px solid #e2e8f0;
        }
        .btn-warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        .btn-warning:hover {
            background: #fde68a;
            border-color: #fcd34d;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .btn-danger:hover {
            background: #fecaca;
            border-color: #fca5a5;
            transform: translateY(-2px);
        }
        .btn-warning, .btn-danger {
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s;
        }

        /* Alert styling */
        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 28px;
            color: #065f46;
        }
        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 28px;
            color: #1e40af;
        }
        .alert-info a {
            color: #2563eb;
        }

        /* Title – keep white for header consistency */
        .app-title h1 {
            color: white;
            text-shadow: 0 1px 2px black;
        }
        .breadcrumb {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 0.5rem 1rem;
        }
        .breadcrumb-item a, .breadcrumb-item.active {
            color: white !important;
        }
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-list-ul me-2"></i> Event List</h1></div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><a href="academic">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="academic/event-dashboard.php">Calendar</a></li>
                <li class="breadcrumb-item active">List</li>
            </ul>
        </div>

        <?= $msg ?>

        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <?php if ($dateFilter): ?>
                    <input type="hidden" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                <?php endif; ?>
                <div class="col-md-4">
                    <label><i class="bi bi-search me-1"></i> Search</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Title, description, venue...">
                </div>
                <div class="col-md-3">
                    <label><i class="bi bi-tag me-1"></i> Category</label>
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['name'] ?>" <?= $filterCategory == $cat['name'] ? 'selected' : '' ?>><?= $cat['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 btn-3d"><i class="bi bi-funnel"></i> Filter</button>
                </div>
                <div class="col-md-3">
                    <a href="academic/event-add.php" class="btn btn-success w-100 btn-3d"><i class="bi bi-plus-circle"></i> New Event</a>
                </div>
            </form>
            <?php if ($dateFilter): ?>
                <div class="mt-3 d-flex align-items-center gap-3">
                    <a href="academic/event-list.php" class="btn btn-sm btn-secondary rounded-pill"><i class="bi bi-x-circle"></i> Clear Date Filter</a>
                    <span class="text-secondary">Showing events for: <strong><?= date('F d, Y', strtotime($dateFilter)) ?></strong></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($events)): ?>
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-calendar-x fs-1"></i>
                <p class="mt-2 mb-0">No events found. <a href="academic/event-add.php">Create an event →</a></p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($events as $ev): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-event h-100" style="border-left: 6px solid <?= htmlspecialchars($ev['color']) ?>;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($ev['title']) ?></h5>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($ev['category']) ?></span>
                                </div>
                                <p class="card-text text-muted small">
                                    <i class="bi bi-calendar-day"></i> <?= date('M d, Y', strtotime($ev['event_date'])) ?><br>
                                    <?php if ($ev['start_time']): ?>
                                        <i class="bi bi-clock"></i> <?= date('h:i A', strtotime($ev['start_time'])) ?>
                                        <?php if ($ev['end_time']): ?> - <?= date('h:i A', strtotime($ev['end_time'])) ?><?php endif; ?><br>
                                    <?php endif; ?>
                                    <?php if ($ev['venue']): ?>
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['venue']) ?><br>
                                    <?php endif; ?>
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($ev['posted_by_name'] ?? 'Academic Office') ?>
                                </p>
                                <?php if (!empty($ev['description'])): ?>
                                    <p class="small text-secondary"><?= nl2br(htmlspecialchars(substr($ev['description'], 0, 100))) ?><?= strlen($ev['description']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="mt-3 d-flex gap-2">
                                    <a href="academic/event-edit.php?id=<?= $ev['id'] ?>" class="btn btn-sm btn-warning rounded-pill flex-fill"><i class="bi bi-pencil"></i> Edit</a>
                                    <a href="academic/event-list.php?delete=<?= $ev['id'] ?><?= $dateFilter ? "&date=$dateFilter" : "" ?>" class="btn btn-sm btn-danger rounded-pill flex-fill" onclick="return confirm('Delete this event permanently?')"><i class="bi bi-trash"></i> Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>