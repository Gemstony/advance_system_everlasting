<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('includes/functions.php');
require_once('includes/calendar.php');

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

// Handle delete directly from dashboard
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM tbl_events WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: academic/event-dashboard.php?msg=deleted");
    exit;
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');
$events = getEventsByMonth($year, $month, $pdo);
$eventsByDay = [];
foreach ($events as $ev) $eventsByDay[$ev['event_date']][] = $ev;

$msg = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':   $msg = '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i> Event added successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; break;
        case 'updated': $msg = '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i> Event updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; break;
        case 'deleted': $msg = '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i> Event deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'; break;
    }
}

// Helper to render calendar (same as original)
function renderCalendar($year, $month, $eventsByDay) {
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDayOfMonth);
    $startDayOfWeek = date('N', $firstDayOfMonth);
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $html = '<div class="calendar-weekdays">';
    foreach ($weekdays as $wd) $html .= '<div class="calendar-weekday">' . $wd . '</div>';
    $html .= '</div><div class="calendar-days">';
    $dayCounter = 1;
    for ($i = 1; $i < $startDayOfWeek; $i++) $html .= '<div class="calendar-day empty"></div>';
    while ($dayCounter <= $daysInMonth) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $dayCounter);
        $hasEvents = isset($eventsByDay[$dateStr]);
        $class = 'calendar-day' . ($hasEvents ? ' has-event' : '');
        $onclick = $hasEvents ? "window.location.href='academic/event-list.php?date=$dateStr'" : "window.location.href='academic/event-add.php?date=$dateStr'";
        $html .= '<div class="' . $class . '" onclick="' . $onclick . '">';
        $html .= '<div class="day-number">' . $dayCounter . '</div>';
        if ($hasEvents) {
            $html .= '<div class="event-markers">';
            foreach ($eventsByDay[$dateStr] as $ev) {
                $html .= '<span class="event-dot" style="background:' . htmlspecialchars($ev['color']) . ';" title="' . htmlspecialchars($ev['title']) . '"></span>';
            }
            $html .= '<span class="event-count">' . count($eventsByDay[$dateStr]) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $dayCounter++;
        if (($startDayOfWeek + $dayCounter - 2) % 7 == 0 && $dayCounter <= $daysInMonth) $html .= '</div><div class="calendar-days">';
    }
    $remaining = (7 - (($startDayOfWeek + $daysInMonth - 1) % 7)) % 7;
    for ($i = 0; $i < $remaining; $i++) $html .= '<div class="calendar-day empty"></div>';
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Dashboard - SRMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Additional styles – supplements the unified header theme */
        .app-content {
            background: transparent !important;
        }
        /* Calendar styles (glass‑morphic) */
        .calendar-grid {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 2rem;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.7);
        }
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .calendar-weekday {
            font-weight: 700;
            padding: 14px 8px;
            background: #ffffff;
            border-radius: 32px;
            text-align: center;
            font-size: 0.85rem;
            letter-spacing: 0.3px;
            color: #1e293b;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02), inset 0 1px 0 rgba(255,255,255,0.8);
            border: 1px solid rgba(203,213,225,0.4);
        }
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 14px;
        }
        .calendar-day {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 28px;
            padding: 14px 8px;
            min-height: 120px;
            transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 0 8px 16px -6px rgba(0,0,0,0.08), 0 0 0 1px rgba(255,255,255,0.9), inset 0 1px 0 rgba(255,255,255,0.6);
            cursor: pointer;
            border: 1px solid rgba(203,213,225,0.3);
            animation: slideUp 0.4s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes slideUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .calendar-day:nth-child(n) { animation-delay: calc(0.01s * var(--i, 1)); }
        .calendar-day:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 20px 30px -12px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,1);
            background: white;
        }
        .calendar-day.has-event {
            background: linear-gradient(145deg, #ecfdf5, #d1fae5);
            border-left: 6px solid #10b981;
            box-shadow: 0 8px 18px -8px rgba(16,185,129,0.2), inset 0 1px 0 rgba(255,255,255,0.8);
        }
        .calendar-day.empty {
            background: #f1f5f9;
            box-shadow: none;
            border: 1px solid #e2e8f0;
            cursor: default;
            opacity: 0.6;
        }
        .day-number {
            font-weight: 800;
            font-size: 1.3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .event-markers {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .event-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 1px white;
        }
        .event-count {
            background: #ef4444;
            border-radius: 30px;
            padding: 0 6px;
            font-size: 0.65rem;
            font-weight: bold;
            color: white;
            margin-left: 4px;
        }
        /* Upcoming events */
        .upcoming-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 1.8rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        .upcoming-header h4 {
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #2563eb);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .view-all-link {
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .view-all-link:hover {
            text-decoration: underline;
            transform: translateX(3px);
        }
        .upcoming-card {
            background: white;
            border-radius: 28px;
            border: none;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            position: relative;
            opacity: 0;
            animation: scaleIn 0.5s ease forwards;
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .upcoming-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--event-color, #10b981);
            transition: width 0.2s;
        }
        .upcoming-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 30px -12px rgba(0,0,0,0.15);
        }
        .upcoming-card:hover::before {
            width: 10px;
        }
        .upcoming-card .card-body {
            padding: 1.25rem;
        }
        .upcoming-card .card-title {
            font-weight: 800;
            font-size: 1.05rem;
            margin-bottom: 0.5rem;
        }
        .upcoming-meta {
            font-size: 0.8rem;
            color: #475569;
            margin-bottom: 0.75rem;
        }
        .event-badge {
            background: #f1f5f9;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            padding: 0.3rem 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-ghost:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        /* Navigation tile (glass) */
        .nav-tile {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(12px);
            border-radius: 60px;
            padding: 0.8rem 1.8rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05), inset 0 1px 0 rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.9);
        }
        .month-selector-card {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 0.6rem 1.2rem;
            box-shadow: 0 6px 14px rgba(0,0,0,0.05);
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.8);
        }
        .month-selector-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -10px rgba(0,0,0,0.1);
        }
        .form-select-sm {
            border-radius: 40px;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }
        .btn-3d {
            border-radius: 40px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .btn-3d:active {
            transform: translateY(1px);
        }
        .btn-outline-primary {
            background: white;
            border: 1px solid #cbd5e1;
            color: #1e293b;
        }
        .btn-outline-primary:hover {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59,130,246,0.2);
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: white !important;
        }
        .text-gradient {
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        h1, h3 { font-weight: 800; }
        /* Title override to keep white for header compatibility */
        .app-title h1 {
            color: white;
            text-shadow: 0 1px 2px black;
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
            <div><h1><i class="bi bi-calendar3 me-2"></i> Event Dashboard</h1></div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><a href="academic">Dashboard</a></li>
                <li class="breadcrumb-item active">Events</li>
            </ul>
        </div>

        <?= $msg ?>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="nav-tile d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h3 class="mb-0"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h3>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="month-selector-card d-flex gap-2">
                            <select id="monthSelect" class="form-select form-select-sm" style="width: auto;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto;">
                                <?php for ($y = $year - 5; $y <= $year + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <button id="goToMonthBtn" class="btn btn-primary btn-sm btn-3d">Go</button>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="academic/event-dashboard.php?month=<?= $month-1 < 1 ? 12 : $month-1 ?>&year=<?= $month == 1 ? $year-1 : $year ?>" class="btn btn-outline-primary btn-3d"><i class="bi bi-chevron-left"></i> Prev</a>
                            <a href="academic/event-dashboard.php?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-outline-secondary btn-3d">Today</a>
                            <a href="academic/event-dashboard.php?month=<?= $month+1 > 12 ? 1 : $month+1 ?>&year=<?= $month == 12 ? $year+1 : $year ?>" class="btn btn-outline-primary btn-3d">Next <i class="bi bi-chevron-right"></i></a>
                            <a href="academic/event-add.php" class="btn btn-success btn-3d ms-2"><i class="bi bi-plus-circle"></i> New Event</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="calendar-grid">
                    <?php echo renderCalendar($year, $month, $eventsByDay); ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="mt-5">
            <div class="upcoming-header">
                <h4><i class="bi bi-calendar-event-fill me-2"></i> Upcoming Events</h4>
                <a href="academic/event-list.php" class="view-all-link">View all <i class="bi bi-arrow-right-short"></i></a>
            </div>
            <div class="row">
                <?php 
                $stmt = $pdo->prepare("SELECT * FROM tbl_events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 6");
                $stmt->execute();
                $upcoming = $stmt->fetchAll();
                foreach ($upcoming as $ev): ?>
                    <div class="col-md-6 col-lg-4 mb-4 upcoming-card">
                        <div class="card upcoming-card h-100" style="--event-color: <?= htmlspecialchars($ev['color']) ?>;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($ev['title']) ?></h5>
                                    <span class="event-badge"><?= htmlspecialchars($ev['category']) ?></span>
                                </div>
                                <div class="upcoming-meta">
                                    <i class="bi bi-calendar-day me-1"></i> <?= date('l, M d, Y', strtotime($ev['event_date'])) ?><br>
                                    <?php if ($ev['start_time']): ?>
                                        <i class="bi bi-clock me-1"></i> <?= date('h:i A', strtotime($ev['start_time'])) ?>
                                        <?php if ($ev['end_time']): ?> – <?= date('h:i A', strtotime($ev['end_time'])) ?><?php endif; ?>
                                    <?php else: ?>
                                        <i class="bi bi-clock me-1"></i> All day
                                    <?php endif; ?>
                                    <?php if ($ev['venue']): ?>
                                        <br><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($ev['venue']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ev['description'])): ?>
                                    <p class="small text-muted mt-2 mb-3">
                                        <?= nl2br(htmlspecialchars(substr($ev['description'], 0, 80))) ?>
                                        <?= strlen($ev['description']) > 80 ? '…' : '' ?>
                                    </p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between gap-2 mt-3">
                                    <a href="academic/event-edit.php?id=<?= $ev['id'] ?>" class="btn btn-sm btn-ghost flex-fill"><i class="bi bi-pencil"></i> Edit</a>
                                    <a href="academic/event-dashboard.php?delete=<?= $ev['id'] ?>" class="btn btn-sm btn-ghost flex-fill text-danger" onclick="return confirm('Delete this event permanently?')"><i class="bi bi-trash"></i> Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($upcoming)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-4" style="border-radius: 40px; background: rgba(255,255,255,0.9); border: 1px solid #e2e8f0;">
                            <i class="bi bi-calendar-x fs-3"></i>
                            <p class="mt-2 mb-0">No upcoming events scheduled. <a href="academic/event-add.php">Create your first event →</a></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        document.getElementById('goToMonthBtn').addEventListener('click', function() {
            var month = document.getElementById('monthSelect').value;
            var year = document.getElementById('yearSelect').value;
            window.location.href = 'academic/event-dashboard.php?month=' + month + '&year=' + year;
        });
        // Stagger animation delays for calendar days
        document.querySelectorAll('.calendar-day').forEach((el, idx) => {
            el.style.setProperty('--i', idx + 1);
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>