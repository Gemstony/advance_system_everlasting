<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('includes/functions.php');
require_once('includes/calendar.php');   // provides getEventsByDate()

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

// ========== FETCH STUDENT DATA (for modular header/sidebar) ==========
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
    
    // For sidebar compatibility
    $fname = $studentData['fname'];
    $lname = $studentData['lname'];
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// ========== EVENT LOGIC ==========
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$events = getEventsByDate($date, $pdo);   // defined in includes/calendar.php

// ========== INCLUDE MODULAR HEADER & SIDEBAR ==========
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<!-- Preloader (overlay that disappears after load) -->
<div id="preloader">
    <div class="loader-wrapper">
        <span class="loader-letter">L</span>
        <span class="loader-letter">o</span>
        <span class="loader-letter">a</span>
        <span class="loader-letter">d</span>
        <span class="loader-letter">i</span>
        <span class="loader-letter">n</span>
        <span class="loader-letter">g</span>
        <div class="loader-bg-1"></div>
        <div class="loader-bg-2"></div>
        <div class="loader"></div>
    </div>
</div>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-event me-2"></i> Event Details</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="student/year.php">Calendar</a></li>
            <li class="breadcrumb-item active"><?= date('F j, Y', strtotime($date)) ?></li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="tile p-4">
                <h2 class="mb-4"><i class="bi bi-calendar2-day me-2"></i> Events on <?= date('F j, Y', strtotime($date)) ?></h2>
                
                <?php if (empty($events)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No events scheduled for this day.
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="parent">
                            <div class="card">
                                <div class="glass"></div>
                                <div class="content">
                                    <span class="title"><?= htmlspecialchars($event['title']) ?></span>
                                    <?php if (!empty($event['description'])): ?>
                                        <span class="text"><?= nl2br(htmlspecialchars($event['description'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="bottom">
                                    <div class="event-meta">
                                        <span><i class="bi bi-clock"></i> 
                                            <?= $event['start_time'] ? date('h:i A', strtotime($event['start_time'])) : 'All day' ?>
                                            <?php if ($event['end_time']): ?> – <?= date('h:i A', strtotime($event['end_time'])) ?><?php endif; ?>
                                        </span>
                                        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['venue'] ?: 'Not specified') ?></span>
                                        <span><i class="bi bi-folder"></i> <?= htmlspecialchars($event['category']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="mt-4">
                    <?php
                        $targetMonth = date('n', strtotime($date));
                        $targetYear = date('Y', strtotime($date));
                    ?>
                    <a href="student/month.php?month=<?= $targetMonth ?>&year=<?= $targetYear ?>" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Back to Calendar
                    </a>
                    <a href="student/year.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-calendar2-week"></i> Year View
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Styles – glass card (no circles), preloader, consistent with month.php -->
<style>
    /* ---------- PRELOADER (blur glass background) ---------- */
    #preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(10, 10, 20, 0.6);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease;
    }
    .loader-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 180px;
        aspect-ratio: 1 / 1;
        font-family: "Jura", sans-serif;
        font-size: 1.6em;
        font-weight: 700;
        border-radius: 50%;
        background-color: #0005;
        user-select: none;
    }
    .loader {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: inherit;
        z-index: 1;
        background-color: #c4d0cc22;
        backdrop-filter: blur(40px);
        -webkit-backdrop-filter: blur(40px);
        box-shadow: 0 0 10px 0 #fffa inset, 0 0 12px 4px #238bbb73 inset, 8px 0 8px 0px #fff inset, -8px 0 16px 2px #000 inset, 0 0 20px 2px #0006, 0 0 24px 4px #000000d8, 0 12px 200px 0px #2effdc9d;
        animation: rotate-anim 4s linear infinite;
        -webkit-animation: rotate-anim 4s linear infinite;
    }
    @keyframes rotate-anim {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @-webkit-keyframes rotate-anim {
        0% { -webkit-transform: rotate(0deg); }
        100% { -webkit-transform: rotate(360deg); }
    }
    .loader-bg-1, .loader-bg-2 {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: inherit;
        opacity: 0.5;
        background-color: #482892a1;
        filter: blur(40px);
        -webkit-filter: blur(40px);
        z-index: 0;
        animation: bg-transform 4s linear infinite;
        -webkit-animation: bg-transform 4s linear infinite;
    }
    .loader-bg-2 {
        background-color: #0c8653;
        animation: bg-transform 6s linear infinite reverse;
        -webkit-animation: bg-transform 6s linear infinite reverse;
    }
    @keyframes bg-transform {
        0%, 100% { transform: translate(-80%, 0%); clip-path: circle(50% at 130% 50%); }
        25% { transform: translate(0%, -80%); clip-path: circle(50% at 50% 130%); }
        50% { transform: translate(80%, 0%); clip-path: circle(50% at -30% 50%); }
        75% { transform: translate(0%, 80%); clip-path: circle(50% at 50% -30%); }
    }
    .loader-letter {
        display: inline-block;
        opacity: 0.7;
        transform: translateY(0);
        animation: loader-letter-anim 1.8s infinite ease;
        z-index: 2;
        color: #0004;
        text-shadow: 0 -1px 0 #eeffdd;
        filter: drop-shadow(0 6px 3px #000);
    }
    .loader-letter:nth-child(1) { animation-delay: 0s; }
    .loader-letter:nth-child(2) { animation-delay: 0.1s; }
    .loader-letter:nth-child(3) { animation-delay: 0.2s; }
    .loader-letter:nth-child(4) { animation-delay: 0.3s; }
    .loader-letter:nth-child(5) { animation-delay: 0.4s; }
    .loader-letter:nth-child(6) { animation-delay: 0.5s; }
    .loader-letter:nth-child(7) { animation-delay: 0.6s; }
    @keyframes loader-letter-anim {
        0%, 100% { opacity: 0.5; transform: none; }
        30% { opacity: 0.5; transform: translateX(-3px) rotate(-1deg); }
        40% { opacity: 0.9; transform: translateX(2px) rotate(3deg) scale(1.06); }
    }

    /* ---------- GLASS EVENT CARD (consistent look, no circles) ---------- */
    .parent {
        width: 100%;
        max-width: 550px;
        margin: 0 auto 25px;
        position: relative;
    }
    .card {
        height: auto;
        border-radius: 50px;
        background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        transition: all 0.4s ease;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255,255,255,0.3);
        overflow: hidden;
    }
    .glass {
        position: absolute;
        inset: 0;
        border-radius: 50px;
        background: rgba(255,255,255,0.15);
        pointer-events: none;
    }
    .content {
        padding: 30px 30px 70px 30px;
        position: relative;
        z-index: 2;
    }
    .content .title {
        display: block;
        font-weight: 900;
        font-size: 1.6rem;
        color: #000000 !important;
        margin-bottom: 12px;
        letter-spacing: -0.5px;
        text-shadow: none;
    }
    .content .text {
        display: block;
        color: #000000 !important;
        font-size: 1rem;
        line-height: 1.5;
        font-weight: 600;
        margin-top: 10px;
    }
    .bottom {
        padding: 10px 20px 20px 20px;
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        border-top: 1px solid rgba(0,0,0,0.15);
        margin-top: 10px;
    }
    .bottom .event-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        font-size: 0.85rem;
        font-weight: 700;
        color: #000000 !important;
    }
    .bottom .event-meta i {
        margin-right: 6px;
        color: #1a1a2e;
    }
    .parent:hover .card {
        transform: translateY(-5px);
        box-shadow: 0 25px 40px rgba(0,0,0,0.3);
    }

    /* Override tile & breadcrumb to be consistent with month.php */
    .tile {
        background: rgba(255,255,255,0.96) !important;
        backdrop-filter: blur(2px);
        border-radius: 28px;
        border: 1px solid rgba(0,0,0,0.1);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }
    .app-content {
        background: transparent !important;
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
    .btn-primary {
        background-color: #4361ee;
        border-color: #4361ee;
        color: white !important;
    }
    .btn-outline-secondary {
        color: #6c757d !important;
        border-color: #6c757d;
    }
    .btn-outline-secondary:hover {
        background-color: #6c757d;
        color: white !important;
    }
    .alert-info {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        color: #0c5460 !important;
    }
    @media (max-width: 768px) {
        .parent { max-width: 100%; }
        .content { padding: 20px 20px 60px 20px; }
        .content .title { font-size: 1.3rem; }
        .content .text { font-size: 0.9rem; }
        .bottom .event-meta { font-size: 0.7rem; gap: 12px; }
    }
</style>

<!-- Scripts (same as month.php) -->
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
    // Fade out preloader after page load
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            setTimeout(function() {
                preloader.style.opacity = '0';
                setTimeout(function() {
                    preloader.style.display = 'none';
                }, 500);
            }, 800);
        }
    });
</script>

</body>
</html>