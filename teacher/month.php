<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Teacher only (level 2)
if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *, DATE(event_date) as event_date
        FROM tbl_events
        WHERE audience IN ('teachers', 'both')
          AND YEAR(event_date) = ?
          AND MONTH(event_date) = ?
        ORDER BY event_date ASC, start_time ASC
    ");
    $stmt->execute([$year, $month]);
    $events = $stmt->fetchAll();

    $eventsByDay = [];
    foreach ($events as $ev) {
        $eventsByDay[$ev['event_date']][] = $ev;
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Helper to render cards – event days get a blue gradient, non‑event days keep the warm gradient
function renderCalendarGrid($year, $month, $eventsByDay, $eventLinkBase) {
    $firstDayOfMonth = mktime(0,0,0,$month,1,$year);
    $daysInMonth = date('t', $firstDayOfMonth);
    $startDayOfWeek = date('N', $firstDayOfMonth);
    $calendarDays = [];

    for ($i = 1; $i < $startDayOfWeek; $i++) {
        $calendarDays[] = ['day' => null];
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $d);
        $calendarDays[] = [
            'day' => $d,
            'date' => $date,
            'events' => $eventsByDay[$date] ?? []
        ];
    }

    $html = '<div class="calendar-modern">';
    $html .= '<div class="calendar-weekdays">';
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($weekdays as $wd) {
        $html .= '<div class="weekday">' . $wd . '</div>';
    }
    $html .= '</div><div class="calendar-days">';

    foreach ($calendarDays as $cell) {
        if ($cell['day'] === null) {
            $html .= '<div class="calendar-day empty"></div>';
        } else {
            $eventCount = count($cell['events']);
            $hasEvents = $eventCount > 0;
            $tooltip = '';
            if ($hasEvents) {
                $titles = array_map(fn($ev) => htmlspecialchars($ev['title']), $cell['events']);
                $tooltip = 'data-bs-toggle="tooltip" data-bs-html="true" title="<strong>' . implode('<br>', $titles) . '</strong>"';
            }

            // For event days: blue background + glow; for non‑event days: warm gradient
            $cardClasses = 'card weather-card';
            if ($hasEvents) {
                $cardClasses .= ' event-active event-glow';
            } else {
                $cardClasses .= ' no-event';
            }

            $html .= '<a href="' . $eventLinkBase . '?date=' . $cell['date'] . '" class="calendar-card-link" ' . $tooltip . '>';
            $html .= '<div class="parent">';
            $html .= '<div class="' . $cardClasses . '">';
            // Cloud and sun decorations (always present, gives a light atmospheric effect)
            $html .= '<div class="container">';
            $html .= '<div class="cloud front"><div class="left-front"></div><div class="right-front"></div></div>';
            $html .= '<div class="cloud back"><div class="left-back"></div><div class="right-back"></div></div>';
            $html .= '<div class="sun"><div class="sunshine"></div></div>';
            $html .= '</div>';
            // Card content
            $html .= '<div class="card-header">';
            $html .= '<span class="day-number-big">' . $cell['day'] . '</span>';
            $html .= '<span class="event-count-summary">' . ($hasEvents ? $eventCount . ' event' . ($eventCount > 1 ? 's' : '') : 'No events') . '</span>';
            $html .= '</div>';
            $html .= '<div class="temp-scale">';
            $html .= '<span>' . ($hasEvents ? '<i class="bi bi-calendar-event"></i>' : '<i class="bi bi-calendar-day"></i>') . '</span>';
            $html .= '</div>';
            $html .= '</div></div></a>';
        }
    }
    $html .= '</div></div>';
    return $html;
}

$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-month me-2"></i> Monthly Calendar</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="teacher/events.php">All Events</a></li>
            <li class="breadcrumb-item active"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></li>
        </ul>
    </div>
    <div class="tile-nav">
        <div class="current-month"><i class="bi bi-calendar2-week me-2"></i> <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></div>
        <div class="d-flex gap-2 flex-wrap">
            <button id="themeToggleBtn" class="button">
                <div class="outline"></div>
                <div class="state"><i id="themeIcon" class="bi bi-moon-stars"></i> <span id="themeText">Dark</span></div>
            </button>
            <a href="teacher/month.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="button">
                <div class="outline"></div>
                <div class="state"><i class="bi bi-chevron-left"></i> Prev</div>
            </a>
            <a href="teacher/month.php?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="button">
                <div class="outline"></div>
                <div class="state"><i class="bi bi-calendar3"></i> Today</div>
            </a>
            <a href="teacher/month.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="button">
                <div class="outline"></div>
                <div class="state">Next <i class="bi bi-chevron-right"></i></div>
            </a>
        </div>
    </div>
    <div class="row"><div class="col-12"><div class="tile p-3">
        <?php echo renderCalendarGrid($year, $month, $eventsByDay, 'teacher/eventdetails.php'); ?>
        <div class="mt-3 text-muted small text-center"><i class="bi bi-info-circle"></i> Blue cards = days with events. Click any card to see details.</div>
    </div></div></div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip({ trigger: 'hover', placement: 'top' });

    // Light/Dark theme toggle (calendar‑specific, independent from global theme)
    const THEME_KEY = 'teacher_calendar_theme';
    let currentTheme = localStorage.getItem(THEME_KEY) || 'light';
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark');
            $('#themeIcon').removeClass('bi-moon-stars').addClass('bi-sun');
            $('#themeText').text('Light');
        } else {
            document.body.classList.remove('dark');
            $('#themeIcon').removeClass('bi-sun').addClass('bi-moon-stars');
            $('#themeText').text('Dark');
        }
        localStorage.setItem(THEME_KEY, theme);
        currentTheme = theme;
    }
    applyTheme(currentTheme);
    $('#themeToggleBtn').on('click', function() {
        applyTheme(currentTheme === 'light' ? 'dark' : 'light');
    });
});
</script>

<style>
/* ---------- LIGHT MODE (default) ---------- */
:root {
    --bg-glass: rgba(255, 255, 255, 0.95);
    --bg-glass-secondary: rgba(255, 255, 255, 0.85);
    --bg-weekday: linear-gradient(145deg, #f1f5f9, #e6edf4);
    --text-primary: #1e293b;
    --text-secondary: #334155;
    --border-dash: #cbd5e1;
    --tooltip-bg: #1e293b;
    --tooltip-text: #f1f5f9;
    /* Warm gradient for days without events */
    --card-bg-warm: radial-gradient(178.94% 106.41% at 26.42% 106.41%, #FFF7B1 0%, rgba(255, 255, 255, 0) 71.88%), #FFFFFF;
    /* Blue gradient for days with events */
    --card-bg-blue: radial-gradient(178.94% 106.41% at 26.42% 106.41%, #60a5fa 0%, rgba(59,130,246,0) 71.88%), #1e3a8a;
    --event-glow: 0 0 15px rgba(59,130,246,0.6), 0 0 8px rgba(59,130,246,0.8);
    --event-text-light: #ffffff;
    --event-text-dark: #e0f2fe;
}
body.dark {
    --bg-glass: rgba(30, 41, 59, 0.95);
    --bg-glass-secondary: rgba(30, 41, 59, 0.85);
    --bg-weekday: linear-gradient(145deg, #334155, #1e293b);
    --text-primary: #f1f5f9;
    --text-secondary: #cbd5e1;
    --border-dash: #475569;
    --tooltip-bg: #0f172a;
    --tooltip-text: #e2e8f0;
    --card-bg-warm: radial-gradient(178.94% 106.41% at 26.42% 106.41%, #4a3e1f 0%, rgba(30,41,59,0) 71.88%), #1e293b;
    --card-bg-blue: radial-gradient(178.94% 106.41% at 26.42% 106.41%, #3b82f6 0%, rgba(30,64,175,0) 71.88%), #0f172a;
    --event-glow: 0 0 15px rgba(96,165,250,0.6), 0 0 8px rgba(96,165,250,0.9);
    --event-text-light: #f8fafc;
    --event-text-dark: #bfdbfe;
}
.app-content {
    background: transparent !important;
    position: relative;
    z-index: 1;
}
/* Button styles (fancy) */
.button {
    --primary: #ff5569;
    --neutral-1: #f7f8f7;
    --neutral-2: #e7e7e7;
    --radius: 14px;
    cursor: pointer;
    border-radius: var(--radius);
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.3);
    border: none;
    box-shadow: 0 0.5px 0.5px 1px rgba(255, 255, 255, 0.2), 0 10px 20px rgba(0, 0, 0, 0.2), 0 4px 5px 0px rgba(0, 0, 0, 0.05);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s ease;
    min-width: 100px;
    padding: 10px 20px;
    height: 48px;
    font-family: "Galano Grotesque", Poppins, Montserrat, sans-serif;
    font-style: normal;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    color: #1e293b;
    background: var(--neutral-1);
}
body.dark .button {
    color: #f1f5f9;
    --neutral-1: #334155;
    --neutral-2: #1e293b;
}
.button:hover {
    transform: scale(1.02);
    box-shadow: 0 0 1px 2px rgba(255, 255, 255, 0.3), 0 15px 30px rgba(0, 0, 0, 0.3), 0 10px 3px -3px rgba(0, 0, 0, 0.04);
}
.button:active {
    transform: scale(1);
    box-shadow: 0 0 1px 2px rgba(255, 255, 255, 0.3), 0 10px 3px -3px rgba(0, 0, 0, 0.2);
}
.button:after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: var(--radius);
    border: 2.5px solid transparent;
    background: linear-gradient(var(--neutral-1), var(--neutral-2)) padding-box, linear-gradient(to bottom, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.45)) border-box;
    z-index: 0;
    transition: all 0.4s ease;
}
.button:hover::after {
    transform: scale(1.05, 1.1);
    box-shadow: inset 0 -1px 3px 0 rgba(255, 255, 255, 1);
}
.button::before {
    content: "";
    inset: 7px 6px 6px 6px;
    position: absolute;
    background: linear-gradient(to top, var(--neutral-1), var(--neutral-2));
    border-radius: 30px;
    filter: blur(0.5px);
    z-index: 2;
}
.state {
    padding-left: 10px;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 6px;
}
.outline {
    position: absolute;
    border-radius: inherit;
    overflow: hidden;
    z-index: 1;
    opacity: 0;
    transition: opacity 0.4s ease;
    inset: -2px -3.5px;
}
.outline::before {
    content: "";
    position: absolute;
    inset: -100%;
    background: conic-gradient(from 180deg, transparent 60%, white 80%, transparent 100%);
    animation: spin 2s linear infinite;
    animation-play-state: paused;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.button:hover .outline { opacity: 1; }
.button:hover .outline::before { animation-play-state: running; }
/* Animated card (weather style) */
.parent {
    width: 100%;
    height: 100%;
    min-height: 150px;
    position: relative;
}
.card.weather-card {
    width: 100%;
    height: 100%;
    position: relative;
    padding: 20px 15px;
    border-radius: 23px;
    transition: all 0.3s cubic-bezier(0.15, 0.83, 0.66, 1);
    cursor: pointer;
    overflow: hidden;
    box-shadow: 0px 155px 62px rgba(0, 0, 0, 0.01), 0px 87px 52px rgba(0, 0, 0, 0.05), 0px 39px 39px rgba(0, 0, 0, 0.09), 0px 10px 21px rgba(0, 0, 0, 0.1);
}
.card.weather-card.no-event {
    background: var(--card-bg-warm);
}
.card.weather-card.event-active {
    background: var(--card-bg-blue);
}
.card.weather-card.event-active .day-number-big,
.card.weather-card.event-active .event-count-summary,
.card.weather-card.event-active .temp-scale span {
    color: var(--event-text-light);
}
.card.weather-card.event-active .temp-scale {
    background: rgba(0,0,0,0.2);
}
.card.weather-card.event-glow {
    box-shadow: 0px 155px 62px rgba(0, 0, 0, 0.01), 0px 87px 52px rgba(0, 0, 0, 0.05), 0px 39px 39px rgba(0, 0, 0, 0.09), 0px 10px 21px rgba(0, 0, 0, 0.1), var(--event-glow);
    border: 1px solid rgba(59,130,246,0.4);
}
.card.weather-card:hover {
    transform: scale(1.02);
}
.container {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
    transform: scale(0.7);
    opacity: 0.5;
}
.cloud { position: absolute; }
.front {
    top: 10px;
    left: -20px;
    animation: clouds 8s infinite ease-in-out;
}
.back {
    top: 40px;
    left: 50px;
    animation: clouds 12s infinite ease-in-out;
}
.left-front, .left-back, .right-front, .right-back {
    background-color: #4c9beb;
    display: inline-block;
}
.left-front {
    width: 45px;
    height: 45px;
    border-radius: 50% 50% 0% 50%;
}
.right-front {
    width: 55px;
    height: 55px;
    border-radius: 50% 50% 50% 0%;
    margin-left: -25px;
}
.left-back {
    width: 30px;
    height: 30px;
    border-radius: 50% 50% 0% 50%;
}
.right-back {
    width: 40px;
    height: 40px;
    border-radius: 50% 50% 50% 0%;
    margin-left: -20px;
}
.sun {
    width: 80px;
    height: 80px;
    background: linear-gradient(to right, #fcbb04, #fffc00);
    border-radius: 50%;
    position: absolute;
    bottom: -30px;
    right: -30px;
}
.sunshine {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    animation: sunshines 2s infinite;
}
@keyframes sunshines {
    0% { transform: scale(1); opacity: 0.6; }
    100% { transform: scale(1.4); opacity: 0; }
}
@keyframes clouds {
    0% { transform: translateX(5px); }
    50% { transform: translateX(-5px); }
    100% { transform: translateX(5px); }
}
.card-header {
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    z-index: 2;
}
.day-number-big {
    font-weight: 800;
    font-size: 28px;
    line-height: 1;
    color: var(--text-primary);
}
.event-count-summary {
    font-weight: 600;
    font-size: 12px;
    color: rgba(87, 77, 51, 0.8);
}
body.dark .event-count-summary {
    color: rgba(255,255,255,0.7);
}
.temp-scale {
    position: absolute;
    right: 15px;
    bottom: 15px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 9px;
    padding: 4px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}
.calendar-modern {
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(4px);
    border-radius: 24px;
    padding: 1rem;
}
body.dark .calendar-modern {
    background: rgba(30,41,59,0.6);
}
.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}
.weekday {
    text-align: center;
    font-weight: 600;
    padding: 10px 6px;
    background: var(--bg-weekday);
    border-radius: 40px;
    font-size: 0.85rem;
    text-transform: uppercase;
    color: var(--text-primary);
}
.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}
.calendar-card-link {
    text-decoration: none;
    display: block;
    height: 100%;
}
.calendar-day.empty {
    background: rgba(241,245,249,0.5);
    border: 1px dashed var(--border-dash);
    border-radius: 20px;
    min-height: 150px;
}
body.dark .calendar-day.empty {
    background: rgba(30,41,59,0.3);
}
.tooltip-inner {
    background-color: var(--tooltip-bg);
    color: var(--tooltip-text);
    font-size: 0.8rem;
    border-radius: 16px;
    padding: 8px 12px;
}
.tooltip-arrow::before { border-top-color: var(--tooltip-bg) !important; }
.tile {
    background: var(--bg-glass) !important;
    backdrop-filter: blur(12px);
    border-radius: 28px;
    box-shadow: 0 20px 35px -12px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.4);
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(8px);
    border-radius: 40px;
    padding: 0.5rem 1.2rem;
}
body.dark .breadcrumb {
    background: rgba(30,41,59,0.7);
}
.breadcrumb-item a { color: #4361ee; text-decoration: none; font-weight: 500; }
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
body.dark .app-title h1 {
    background: linear-gradient(135deg, #f1f5f9, #cbd5e1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.tile-nav {
    background: var(--bg-glass-secondary);
    backdrop-filter: blur(8px);
    border-radius: 60px;
    padding: 0.8rem 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.5);
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
}
.current-month {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
body.dark .current-month {
    background: linear-gradient(135deg, #f1f5f9, #94a3b8);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
@media (max-width: 768px) {
    .calendar-days { gap: 4px; }
    .parent { min-height: 110px; }
    .day-number-big { font-size: 20px; }
    .event-count-summary { font-size: 9px; }
    .temp-scale { font-size: 10px; padding: 2px 6px; }
    .weekday { font-size: 0.7rem; padding: 6px 2px; }
    .current-month { font-size: 1.2rem; }
    .button { min-width: 70px; padding: 6px 12px; height: 38px; font-size: 12px; }
}
</style>
</body>
</html>