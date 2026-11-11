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

$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$year = $currentYear;

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Count events per month for teachers (audience teachers or both)
    $stmt = $pdo->prepare("
        SELECT MONTH(event_date) as month, COUNT(*) as cnt
        FROM tbl_events
        WHERE audience IN ('teachers', 'both')
          AND YEAR(event_date) = ?
        GROUP BY MONTH(event_date)
    ");
    $stmt->execute([$year]);
    $monthCounts = $stmt->fetchAll();

    $eventsByMonth = [];
    foreach ($monthCounts as $row) {
        $eventsByMonth[(int)$row['month']] = (int)$row['cnt'];
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = [
        'name' => date('F', mktime(0,0,0,$m,1)),
        'eventCount' => $eventsByMonth[$m] ?? 0,
        'hasEvents' => ($eventsByMonth[$m] ?? 0) > 0
    ];
}
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="tile-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h3>Academic Year <?= $year ?></h3>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select id="yearSelect" class="year-selector">
                <?php for ($y = date('Y')-5; $y <= date('Y')+5; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <a href="teacher/year.php?year=<?= $year-1 ?>" class="nav-btn"><i class="bi bi-chevron-left"></i> Previous</a>
            <a href="teacher/year.php?year=<?= date('Y') ?>" class="nav-btn">Current Year</a>
            <a href="teacher/year.php?year=<?= $year+1 ?>" class="nav-btn">Next <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($months as $monthNum => $month): ?>
            <div class="col-md-3 col-sm-6">
                <div class="month-card <?= $month['hasEvents'] ? 'has-events' : 'no-events' ?>" data-url="teacher/month.php?month=<?= $monthNum ?>&year=<?= $year ?>">
                    <div class="logo">
                        <span class="circle circle1"></span>
                        <span class="circle circle2"></span>
                        <span class="circle circle3"></span>
                        <span class="circle circle4"></span>
                        <span class="circle circle5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29.667 31.69" class="svg">
                                <path d="M12.827,1.628A1.561,1.561,0,0,1,14.31,0h2.964a1.561,1.561,0,0,1,1.483,1.628v11.9a9.252,9.252,0,0,1-2.432,6.852q-2.432,2.409-6.963,2.409T2.4,20.452Q0,18.094,0,13.669V1.628A1.561,1.561,0,0,1,1.483,0h2.98A1.561,1.561,0,0,1,5.947,1.628V13.191a5.635,5.635,0,0,0,.85,3.451,3.153,3.153,0,0,0,2.632,1.094,3.032,3.032,0,0,0,2.582-1.076,5.836,5.836,0,0,0,.816-3.486Z"/>
                                <path d="M75.207,20.857a1.561,1.561,0,0,1-1.483,1.628h-2.98a1.561,1.561,0,0,1-1.483-1.628V1.628A1.561,1.561,0,0,1,70.743,0h2.98a1.561,1.561,0,0,1,1.483,1.628Z" transform="translate(-45.91 0)"/>
                                <path d="M0,80.018A1.561,1.561,0,0,1,1.483,78.39h26.7a1.561,1.561,0,0,1,1.483,1.628v2.006a1.561,1.561,0,0,1-1.483,1.628H1.483A1.561,1.561,0,0,1,0,82.025Z" transform="translate(0 -51.963)"/>
                            </svg>
                        </span>
                    </div>
                    <div class="glass"></div>
                    <div class="content">
                        <span class="title"><?= $month['name'] ?></span>
                        <?php if ($month['hasEvents']): ?>
                            <div class="event-badge"><i class="bi bi-calendar-event"></i> <?= $month['eventCount'] ?> Event(s)</div>
                            <div class="text-hint">✨ Click to view</div>
                        <?php else: ?>
                            <div class="event-badge no-event-badge"><i class="bi bi-calendar-x"></i> No events</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
$(document).ready(function() {
    // Year dropdown redirect
    $('#yearSelect').on('change', function() {
        window.location.href = 'teacher/year.php?year=' + $(this).val();
    });

    // Month card click with burst effect + redirect
    $('.month-card').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        if (!url) return;

        const rect = this.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;

        // Burst spots
        for (let i = 0; i < 30; i++) {
            const spot = document.createElement('div');
            spot.classList.add('burst-spot');
            const size = Math.random() * 12 + 4;
            spot.style.width = size + 'px';
            spot.style.height = size + 'px';
            spot.style.left = (centerX + (Math.random() - 0.5) * 40) + 'px';
            spot.style.top = (centerY + (Math.random() - 0.5) * 40) + 'px';
            const angle = Math.random() * Math.PI * 2;
            const distance = 80 + Math.random() * 100;
            const tx = Math.cos(angle) * distance;
            const ty = Math.sin(angle) * distance;
            spot.style.setProperty('--tx', tx + 'px');
            spot.style.setProperty('--ty', ty + 'px');
            document.body.appendChild(spot);
            setTimeout(() => spot.remove(), 600);
        }

        // Tick overlay
        const tick = document.createElement('div');
        tick.classList.add('tick-overlay');
        tick.innerHTML = '✓';
        tick.style.left = (centerX - 30) + 'px';
        tick.style.top = (centerY - 30) + 'px';
        document.body.appendChild(tick);
        setTimeout(() => tick.remove(), 500);

        // Redirect after animation
        setTimeout(() => {
            window.location.href = url;
        }, 380);
    });
});
</script>

<style>
/* Page specific styles */
.app-content {
    background: transparent !important;
}
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    border: 1px solid rgba(255,255,255,0.6);
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.5rem 1rem;
}
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
/* Month card styles */
.month-card {
    width: 100%;
    height: auto;
    border-radius: 50px;
    transition: all 0.5s ease-in-out;
    transform-style: preserve-3d;
    box-shadow: rgba(5, 71, 17, 0) 40px 50px 25px -40px, rgba(5, 71, 17, 0.2) 0px 25px 25px -5px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    perspective: 1000px;
    margin-bottom: 1.5rem;
}
.month-card.has-events {
    background: linear-gradient(135deg, #2b6cb0 0%, #4299e1 100%);
}
.month-card.no-events {
    background: linear-gradient(135deg, #2f855a 0%, #48bb78 100%);
}
.glass {
    transform-style: preserve-3d;
    position: absolute;
    inset: 8px;
    border-radius: 55px;
    border-top-right-radius: 100%;
    background: linear-gradient(0deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.5) 100%);
    transform: translate3d(0px, 0px, 25px);
    border-left: 1px solid rgba(255,255,255,0.5);
    border-bottom: 1px solid rgba(255,255,255,0.5);
    transition: all 0.5s ease-in-out;
    pointer-events: none;
}
.content {
    padding: 80px 30px 30px 30px;
    transform: translate3d(0, 0, 26px);
    position: relative;
    z-index: 10;
    text-align: center;
}
.content .title {
    display: block;
    color: #ffffff;
    font-weight: 900;
    font-size: 1.8rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.event-badge {
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.3rem 1rem;
    display: inline-block;
    margin-top: 1rem;
    font-weight: 600;
    color: white;
}
.no-event-badge {
    background: rgba(0,0,0,0.4);
}
.text-hint {
    color: rgba(255,255,255,0.9);
    font-size: 0.8rem;
    margin-top: 0.5rem;
}
.logo {
    position: absolute;
    right: 0;
    top: 0;
    transform-style: preserve-3d;
    pointer-events: none;
    z-index: 5;
}
.logo .circle {
    display: block;
    position: absolute;
    aspect-ratio: 1;
    border-radius: 50%;
    top: 0;
    right: 0;
    box-shadow: rgba(100, 100, 111, 0.2) -10px 10px 20px 0px;
    backdrop-filter: blur(5px);
    background: rgba(255, 255, 255, 0.2);
    transition: all 0.5s ease-in-out;
    animation: floatCircle 6s infinite ease-in-out;
}
.logo .circle1 { width: 170px; transform: translate3d(0, 0, 20px); top: 8px; right: 8px; animation-duration: 5s; animation-delay: 0s; }
.logo .circle2 { width: 140px; transform: translate3d(0, 0, 40px); top: 10px; right: 10px; animation-duration: 7s; animation-delay: 0.5s; }
.logo .circle3 { width: 110px; transform: translate3d(0, 0, 60px); top: 17px; right: 17px; animation-duration: 6s; animation-delay: 1s; }
.logo .circle4 { width: 80px; transform: translate3d(0, 0, 80px); top: 23px; right: 23px; animation-duration: 8s; animation-delay: 1.5s; }
.logo .circle5 { width: 50px; transform: translate3d(0, 0, 100px); top: 30px; right: 30px; display: grid; place-content: center; background: rgba(255,255,255,0.3); animation-duration: 4s; animation-delay: 0.3s; }
.logo .circle5 .svg { width: 20px; fill: white; }
@keyframes floatCircle {
    0% { transform: translate3d(0, 0, var(--z, 20px)) scale(1); opacity: 0.7; }
    50% { transform: translate3d(0, -10px, var(--z, 20px)) scale(1.05); opacity: 1; }
    100% { transform: translate3d(0, 0, var(--z, 20px)) scale(1); opacity: 0.7; }
}
.logo .circle1 { --z: 20px; }
.logo .circle2 { --z: 40px; }
.logo .circle3 { --z: 60px; }
.logo .circle4 { --z: 80px; }
.logo .circle5 { --z: 100px; }
.month-card:hover {
    transform: none;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}
.month-card:hover .glass {
    transform: translate3d(0px, 0px, 28px);
}
.nav-btn {
    background: linear-gradient(135deg, rgba(122,105,249,0.8), rgba(242,99,120,0.8));
    border: none;
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
    backdrop-filter: blur(8px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    text-decoration: none;
    display: inline-block;
}
.nav-btn:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, #7A69F9, #F26378);
    color: white;
}
.year-selector {
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: 50px;
    padding: 0.6rem 1.2rem;
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.year-selector option {
    background: #1e293b;
    color: white;
}
.tile-header {
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(14px);
    border-radius: 60px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255,255,255,0.2);
}
.tile-header h3 {
    font-weight: 800;
    margin: 0;
    color: white;
    text-shadow: 0 1px 2px black;
}
.tick-overlay {
    position: fixed;
    z-index: 9999;
    font-size: 60px;
    color: #00ffaa;
    filter: drop-shadow(0 0 8px cyan);
    pointer-events: none;
    animation: tickFly 0.5s ease-out forwards;
}
@keyframes tickFly {
    0% { transform: scale(0) rotate(-90deg); opacity: 1; }
    100% { transform: scale(1.5) rotate(0deg); opacity: 0; top: 10% !important; left: 50% !important; }
}
.burst-spot {
    position: fixed;
    border-radius: 50%;
    background: radial-gradient(circle, #ffd966, #ff8c42);
    pointer-events: none;
    z-index: 9998;
    animation: burstOut 0.6s cubic-bezier(0.2, 0.9, 0.4, 1) forwards;
}
@keyframes burstOut {
    0% { transform: scale(0); opacity: 0.9; }
    100% { transform: translate(var(--tx, 0px), var(--ty, 0px)) scale(1.2); opacity: 0; }
}
@media (max-width: 768px) {
    .content { padding: 60px 20px 20px; }
    .content .title { font-size: 1.3rem; }
    .nav-btn { padding: 0.4rem 1rem; font-size: 0.85rem; }
    .tile-header { padding: 0.8rem 1rem; }
    .tile-header h3 { font-size: 1.2rem; }
}
</style>
</body>
</html>