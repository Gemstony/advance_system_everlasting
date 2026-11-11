<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('includes/functions.php');
require_once('includes/calendar.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

// ========== FETCH STUDENT DATA ==========
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentData) die("Student not found.");
    
    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== CALENDAR LOGIC ==========
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($month < 1) $month = 12;
if ($month > 12) $month = 1;

$events = getEventsByMonth($year, $month, $pdo);
$eventsByDay = [];
foreach ($events as $ev) {
    $eventsByDay[$ev['event_date']][] = $ev;
}

$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-month me-2"></i> Monthly Calendar</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student/year.php">Calendar</a></li>
            <li class="breadcrumb-item active"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></li>
        </ul>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="tile p-3 d-flex justify-content-between align-items-center flex-wrap" style="background: rgba(0,0,0,0.3) !important; backdrop-filter: blur(6px); border: none;">
                <h3 class="mb-2 mb-md-0" style="color: white; text-shadow: 1px 1px 2px black;"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h3>
                <div>
                    <a href="student/month.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-outline-light">◀ Prev</a>
                    <a href="student/month.php?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-outline-light">Today</a>
                    <a href="student/month.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-outline-light">Next ▶</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile p-3 calendar-card-wrapper">
                <?php echo renderCalendarGridWithWeather($year, $month, $eventsByDay, 'student/event.php'); ?>
            </div>
        </div>
    </div>
</main>

<?php
/**
 * Render calendar grid with animated sun/cloud inside each date cell
 */
function renderCalendarGridWithWeather($year, $month, $eventsByDay, $eventLink) {
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $firstDayOfMonth);
    $startWeekday = date('w', $firstDayOfMonth); // 0 = Sunday
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    $grid = [];
    $week = array_fill(0, 7, null);
    $pos = $startWeekday;
    $day = 1;
    while ($day <= $daysInMonth) {
        $week[$pos] = $day;
        $pos++;
        if ($pos == 7) {
            $grid[] = $week;
            $week = array_fill(0, 7, null);
            $pos = 0;
        }
        $day++;
    }
    if ($pos != 0) {
        $grid[] = $week;
    }
    
    $html = '<table class="calendar-grid">';
    $html .= '<thead><tr>';
    foreach ($weekdays as $wd) {
        $html .= '<th class="calendar-weekday">' . $wd . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($grid as $weekCells) {
        $html .= '<tr>';
        foreach ($weekCells as $cellDay) {
            if ($cellDay === null) {
                $html .= '<td class="calendar-day empty-day"></td>';
                continue;
            }
            $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $cellDay);
            $hasEvent = !empty($eventsByDay[$dateStr]);
            $eventCount = $hasEvent ? count($eventsByDay[$dateStr]) : 0;
            $cellClass = 'calendar-day' . ($hasEvent ? ' has-event' : '');
            
            $html .= '<td class="' . $cellClass . '">';
            $html .= '<div class="weather-icon">';
            $html .= '<div class="sun-animated"></div>';
            $html .= '<div class="cloud-animated"></div>';
            $html .= '</div>';
            $html .= '<div class="day-number">';
            $html .= '<a href="' . $eventLink . '?date=' . $dateStr . '">' . $cellDay . '</a>';
            if ($hasEvent) {
                $html .= '<span class="event-count">' . $eventCount . '</span>';
            }
            $html .= '</div>';
            if ($hasEvent) {
                $html .= '<div class="event-dots">';
                foreach ($eventsByDay[$dateStr] as $ev) {
                    $html .= '<span class="event-dot" style="background-color:' . htmlspecialchars($ev['color']) . ';"></span>';
                }
                $html .= '</div>';
                // optional: show first event title
                $firstEv = $eventsByDay[$dateStr][0];
                $html .= '<div class="event-title">' . htmlspecialchars(substr($firstEv['title'], 0, 20)) . '</div>';
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}
?>

<!-- Enhanced Calendar Card Styles with inner sun/cloud animations -->
<style>
    .calendar-card-wrapper {
        background: rgba(0,0,0,0.2) !important;
        backdrop-filter: blur(4px);
        border-radius: 32px;
        padding: 20px;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .calendar-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 12px;
        background: transparent;
    }
    
    .calendar-weekday {
        background: rgba(30, 30, 40, 0.7);
        backdrop-filter: blur(4px);
        font-weight: 600;
        padding: 12px;
        text-align: center;
        border-radius: 20px;
        color: white !important;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.9rem;
        border: none;
    }
    
    /* Date cell – card style */
    .calendar-day {
        background: linear-gradient(145deg, #ffffff, #f9fafc);
        border-radius: 28px;
        padding: 14px 10px;
        min-height: 130px;
        vertical-align: top;
        transition: all 0.25s ease;
        box-shadow: 0 8px 16px -8px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.7);
        position: relative;
        overflow: hidden;
        color: #000000;
    }
    .calendar-day * {
        color: #000000 !important;
    }
    
    .calendar-day:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 28px -12px rgba(0,0,0,0.3);
        background: linear-gradient(145deg, #ffffff, #fff5e6);
    }
    
    /* Event day – greenish */
    .calendar-day.has-event {
        background: linear-gradient(145deg, #e8f5e9, #d4edda);
        border-left: 6px solid #2ecc71;
    }
    .calendar-day.has-event:hover {
        background: linear-gradient(145deg, #f0faf0, #e0f0e0);
    }
    
    /* Empty day */
    .calendar-day.empty-day {
        opacity: 0.5;
        background: rgba(220,220,230,0.6);
        backdrop-filter: blur(2px);
    }
    
    /* Day number */
    .day-number a {
        display: inline-block;
        font-weight: 800;
        font-size: 1.4rem;
        color: #000000 !important;
        text-decoration: none;
        background: rgba(255,255,200,0.5);
        padding: 4px 12px;
        border-radius: 40px;
        transition: 0.2s;
        margin-bottom: 8px;
    }
    .day-number a:hover {
        background: #ffdd88;
        transform: scale(1.02);
    }
    
    .event-count {
        background: #ef476f;
        color: white !important;
        border-radius: 30px;
        padding: 2px 10px;
        font-size: 0.7rem;
        font-weight: bold;
        margin-left: 8px;
        display: inline-block;
    }
    
    .event-dots {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .event-dot {
        width: 12px;
        height: 12px;
        border-radius: 20px;
        box-shadow: 0 0 2px rgba(0,0,0,0.2);
    }
    .event-title {
        font-size: 0.7rem;
        font-weight: 500;
        margin-top: 8px;
        color: #000000 !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* ----- ANIMATED SUN & CLOUD INSIDE EACH CELL ----- */
    .weather-icon {
        position: relative;
        float: right;
        width: 40px;
        height: 40px;
        margin: -5px -5px 0 0;
        pointer-events: none;
    }
    .sun-animated {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 22px;
        height: 22px;
        background: radial-gradient(circle, #ffdd66, #ffaa33);
        border-radius: 50%;
        box-shadow: 0 0 8px gold;
        animation: spinSun 4s infinite linear;
        z-index: 2;
    }
    @keyframes spinSun {
        0% { transform: rotate(0deg) scale(1); opacity: 0.9; }
        50% { transform: rotate(180deg) scale(1.1); opacity: 1; }
        100% { transform: rotate(360deg) scale(1); opacity: 0.9; }
    }
    .cloud-animated {
        position: absolute;
        top: 12px;
        right: -5px;
        width: 28px;
        height: 18px;
        background: rgba(255,255,255,0.9);
        border-radius: 40% 60% 50% 50% / 60% 50% 50% 40%;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        animation: driftCloud 6s infinite alternate ease-in-out;
        z-index: 1;
    }
    .cloud-animated::before,
    .cloud-animated::after {
        content: '';
        position: absolute;
        background: rgba(255,255,255,0.9);
        border-radius: 50%;
    }
    .cloud-animated::before {
        width: 14px;
        height: 14px;
        top: -6px;
        left: 6px;
    }
    .cloud-animated::after {
        width: 18px;
        height: 18px;
        top: -10px;
        left: 12px;
    }
    @keyframes driftCloud {
        0% { transform: translateX(0px); opacity: 0.7; }
        100% { transform: translateX(6px); opacity: 1; }
    }
    
    /* For event days, give sun/cloud a slight color shift */
    .calendar-day.has-event .sun-animated {
        background: radial-gradient(circle, #ffe28a, #ffbb44);
        animation: spinSun 3s infinite alternate;
    }
    .calendar-day.has-event .cloud-animated {
        background: rgba(255,255,230,0.95);
    }
    
    /* Responsive: smaller icons on mobile */
    @media (max-width: 768px) {
        .calendar-grid {
            border-spacing: 6px;
        }
        .calendar-day {
            min-height: 90px;
            padding: 8px 4px;
        }
        .day-number a {
            font-size: 1rem;
            padding: 2px 8px;
        }
        .weather-icon {
            transform: scale(0.7);
            margin: -8px -8px 0 0;
        }
        .event-dot {
            width: 8px;
            height: 8px;
        }
        .calendar-weekday {
            font-size: 0.7rem;
            padding: 8px;
        }
    }
    
    /* General UI improvements */
    .app-content {
        background: transparent !important;
        position: relative;
        z-index: 1;
    }
    .tile {
        background: rgba(255, 255, 255, 0.15) !important;
        backdrop-filter: blur(8px);
        border-radius: 32px;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .breadcrumb {
        background: rgba(0,0,0,0.4) !important;
        backdrop-filter: blur(8px);
        border-radius: 40px;
    }
    .breadcrumb-item a, .breadcrumb-item.active {
        color: white !important;
        text-shadow: 0 1px 2px black;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #ffffff, #ffec99);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        text-shadow: none;
    }
    .btn-outline-light {
        border-color: rgba(255,255,255,0.6);
        color: white;
    }
    .btn-outline-light:hover {
        background: rgba(255,255,255,0.2);
        border-color: white;
    }
</style>

<!-- Scripts -->
<link rel="stylesheet" href="assets/css/calendar.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="assets/js/calendar.js"></script>

<script>
$(document).ready(function() {
    // Ensure event class for cells with events
    $('.calendar-day').each(function() {
        if ($(this).find('.event-dot').length > 0) {
            $(this).addClass('has-event');
        }
    });
});
</script>

</body>
</html>