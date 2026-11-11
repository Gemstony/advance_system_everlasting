<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

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

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM tbl_events WHERE audience IN ('teachers','both') AND DATE(event_date) = ? ORDER BY start_time ASC");
    $stmt->execute([$date]);
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-event me-2"></i> Event Details</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="teacher/events.php">All Events</a></li>
            <li class="breadcrumb-item active"><?= date('F j, Y', strtotime($date)) ?></li>
        </ul>
    </div>
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="tile p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="detail-icon"><i class="bi bi-calendar2-day fs-3"></i></div>
                    <div><h2 class="mb-0">Events on <?= date('F j, Y', strtotime($date)) ?></h2><p class="text-muted mb-0"><?= date('l', strtotime($date)) ?></p></div>
                </div>

                <?php if (empty($events)): ?>
                    <div class="no-events">
                        <i class="bi bi-inbox-fill fs-1 d-block mb-3"></i>
                        <h4>No events scheduled</h4>
                        <p>There are no events for teachers on this day.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="parent">
                            <!-- Card with dynamic left border color -->
                            <div class="card" style="border-left: 8px solid <?= htmlspecialchars($event['color']) ?>;">
                                <!-- Animated weather decorations (clouds + sun) -->
                                <div class="container">
                                    <div class="cloud front">
                                        <div class="left-front"></div>
                                        <div class="right-front"></div>
                                    </div>
                                    <div class="cloud back">
                                        <div class="left-back"></div>
                                        <div class="right-back"></div>
                                    </div>
                                    <div class="sun">
                                        <div class="sunshine"></div>
                                    </div>
                                </div>
                                <!-- Event content (white card, black text) -->
                                <div class="event-content">
                                    <div class="event-title">
                                        <span><i class="bi bi-tag-fill me-2" style="color: <?= htmlspecialchars($event['color']) ?>;"></i> <?= htmlspecialchars($event['title']) ?></span>
                                        <span class="event-category-badge"><i class="bi bi-folder-fill me-1"></i> <?= htmlspecialchars($event['category']) ?></span>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="event-description"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
                                    <?php endif; ?>
                                    <div class="event-meta">
                                        <div class="meta-item"><i class="bi bi-clock"></i> <?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : 'All day' ?><?php if (!empty($event['end_time'])) echo ' – '.date('h:i A', strtotime($event['end_time'])); ?></div>
                                        <div class="meta-item"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['venue'] ?: 'Not specified') ?></div>
                                    </div>
                                    <?php if ($event['audience'] === 'both'): ?>
                                        <div class="audience-badge"><i class="bi bi-people-fill me-1"></i> For staff & students</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="mt-4 d-flex gap-2">
                    <a href="teacher/month.php?month=<?= date('n', strtotime($date)) ?>&year=<?= date('Y', strtotime($date)) ?>" class="btn-custom"><i class="bi bi-arrow-left"></i> Back to Calendar</a>
                    <a href="teacher/events.php" class="btn-custom"><i class="bi bi-list-ul"></i> All Events</a>
                </div>
            </div>
        </div>
    </div>
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
    // No sidebar toggle script needed – handled globally by inc/header.php
});
</script>

<style>
/* ---------- GLOBAL STYLES ---------- */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', sans-serif;
    background-attachment: fixed;
}
.app-content { background: transparent !important; position: relative; z-index: 1; }

/* ---------- BUTTON STYLES (fancy, same as month view) ---------- */
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
    background: var(--neutral-1);
    color: #1e293b;
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

/* ---------- ANIMATED CARD (weather style) – white background, black text ---------- */
.parent {
    width: 100%;
    margin-bottom: 1.5rem;
    perspective: 1000px;
}
.card {
    width: 100%;
    position: relative;
    padding: 25px;
    background: #ffffff;  /* solid white background */
    box-shadow: 0px 155px 62px rgba(0, 0, 0, 0.01), 0px 87px 52px rgba(0, 0, 0, 0.05), 0px 39px 39px rgba(0, 0, 0, 0.09), 0px 10px 21px rgba(0, 0, 0, 0.1);
    border-radius: 23px;
    transition: all 0.8s cubic-bezier(0.15, 0.83, 0.66, 1);
    cursor: pointer;
    overflow: visible;
    border-left: 8px solid #4c9beb; /* fallback, will be overridden inline */
}
.card:hover {
    transform: scale(1.02);
    box-shadow: 0 20px 30px -10px rgba(0,0,0,0.2);
}

/* Container for animated clouds and sun (same as before) */
.container {
    width: 250px;
    height: 250px;
    position: absolute;
    right: -35px;
    top: -50px;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: scale(0.7);
    pointer-events: none;
    z-index: 0;
}
.cloud { position: absolute; }
.front {
    padding-top: 45px;
    margin-left: 25px;
    display: inline;
    position: absolute;
    z-index: 11;
    animation: clouds 8s infinite;
    animation-timing-function: ease-in-out;
}
.back {
    margin-top: -30px;
    margin-left: 150px;
    z-index: 12;
    animation: clouds 12s infinite;
    animation-timing-function: ease-in-out;
}
.right-front {
    width: 45px;
    height: 45px;
    border-radius: 50% 50% 50% 0%;
    background-color: #4c9beb;
    display: inline-block;
    margin-left: -25px;
    z-index: 5;
}
.left-front {
    width: 65px;
    height: 65px;
    border-radius: 50% 50% 0% 50%;
    background-color: #4c9beb;
    display: inline-block;
    z-index: 5;
}
.right-back {
    width: 50px;
    height: 50px;
    border-radius: 50% 50% 50% 0%;
    background-color: #4c9beb;
    display: inline-block;
    margin-left: -20px;
    z-index: 5;
}
.left-back {
    width: 30px;
    height: 30px;
    border-radius: 50% 50% 0% 50%;
    background-color: #4c9beb;
    display: inline-block;
    z-index: 5;
}
.sun {
    width: 120px;
    height: 120px;
    background: linear-gradient(to right, #fcbb04, #fffc00);
    border-radius: 60px;
    position: absolute;
    bottom: -50px;
    right: -50px;
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
    0% { transform: translateX(15px); }
    50% { transform: translateX(0px); }
    100% { transform: translateX(15px); }
}

/* Event content – clearly visible with black text */
.event-content {
    position: relative;
    z-index: 10;
}
.event-title {
    font-weight: 800;
    font-size: 1.4rem;
    margin-bottom: 0.5rem;
    color: #1e293b; /* dark text */
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.event-category-badge {
    background: rgba(0,0,0,0.08);
    border-radius: 40px;
    padding: 0.2rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}
.event-description {
    color: #334155;
    margin: 1rem 0;
    line-height: 1.5;
    font-size: 0.9rem;
}
.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1rem;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #1e293b;
    font-size: 0.9rem;
}
.meta-item i {
    font-size: 1.1rem;
    color: #4361ee;
}
.audience-badge {
    display: inline-block;
    background: rgba(0,0,0,0.08);
    border-radius: 30px;
    padding: 0.2rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 1rem;
}

/* Tile and navigation (consistent with month view) */
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
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
.breadcrumb-item a { color: #4361ee; text-decoration: none; font-weight: 500; }
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.tile-nav {
    background: rgba(255,255,255,0.85);
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
.btn-custom {
    border-radius: 40px;
    padding: 0.5rem 1.2rem;
    font-weight: 500;
    background: rgba(255,255,255,0.9);
    border: 1px solid #e2e8f0;
    color: #1e293b;
    transition: 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn-custom:hover { background: #4361ee; border-color: #4361ee; color: white; transform: translateY(-2px); }

.no-events {
    background: rgba(255,255,255,0.95);
    border-radius: 28px;
    padding: 2rem;
    text-align: center;
    color: #64748b;
}

@media (max-width: 768px) {
    .event-title { font-size: 1.1rem; }
    .event-meta { gap: 1rem; }
    .meta-item { font-size: 0.8rem; }
    .btn-custom { padding: 0.3rem 1rem; font-size: 0.8rem; }
}
</style>
</body>
</html>