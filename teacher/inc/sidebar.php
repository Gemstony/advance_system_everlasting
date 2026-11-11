<?php
// inc/sidebar_teacher.php - Teacher Sidebar with glass background, animations & Events menu
if (!isset($fname) || !isset($lname)) {
    // Fallback: try to get from session if not set
    if (isset($_SESSION['fname']) && isset($_SESSION['lname'])) {
        $fname = $_SESSION['fname'];
        $lname = $_SESSION['lname'];
    } else {
        $fname = 'Teacher';
        $lname = '';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Teacher sidebar with glass background – theme background shows through */
    .app-sidebar {
        background: rgba(10, 10, 20, 0.55) !important;
        backdrop-filter: blur(18px) saturate(180%);
        border-right: 1px solid rgba(255,255,255,0.08);
        transition: background 0.3s ease;
    }
    .app-sidebar__user {
        padding: 1.5rem 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        text-align: center;
    }
    .app-sidebar__user-name {
        font-size: 1rem;
        font-weight: 600;
        color: #f1f5f9;
    }
    .app-sidebar__user-designation {
        font-size: 0.7rem;
        background: rgba(67,97,238,0.3);
        display: inline-block;
        padding: 0.2rem 0.8rem;
        border-radius: 30px;
        margin-top: 0.3rem;
        color: #a5b4fc;
    }
    .app-menu__item {
        margin: 4px 12px;
        border-radius: 12px;
        transition: all 0.2s ease;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.7rem 1rem;
        background: transparent;
    }
    .app-menu__item:hover {
        background: rgba(67,97,238,0.25);
        transform: translateX(4px);
        color: white;
    }
    .app-menu__item.active {
        background: linear-gradient(90deg, rgba(67,97,238,0.35), rgba(139,92,246,0.15));
        border-left: 3px solid #4361ee;
        color: white;
    }
    .app-menu__icon {
        font-size: 1.2rem;
        width: 1.5rem;
        text-align: center;
    }
    /* Custom scrollbar */
    .app-sidebar::-webkit-scrollbar {
        width: 4px;
    }
    .app-sidebar::-webkit-scrollbar-track {
        background: #1e293b;
    }
    .app-sidebar::-webkit-scrollbar-thumb {
        background: #475569;
        border-radius: 10px;
    }
    /* Treeview styles */
    .treeview-menu {
        background: rgba(15,23,42,0.6);
        backdrop-filter: blur(8px);
        margin-left: 12px;
        border-radius: 12px;
        padding: 6px 0;
        display: none;
    }
    .treeview.is-expanded > .treeview-menu {
        display: block;
    }
    .treeview-indicator {
        transition: transform 0.2s;
        margin-left: auto;
    }
    .treeview.is-expanded .treeview-indicator {
        transform: rotate(90deg);
    }
    .treeview-item {
        border-radius: 10px;
        margin: 2px 6px;
        padding: 6px 12px;
        transition: all 0.2s;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .treeview-item:hover {
        background: rgba(67,97,238,0.25);
        transform: translateX(4px);
        color: white;
    }
    .treeview-item.active {
        background: rgba(67,97,238,0.4);
        color: white;
    }
    .menu-divider-light {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        margin: 10px 16px;
    }
    /* Icon colors (unchanged) */
    .bi-speedometer2 { color: #4361ee; }
    .bi-person { color: #06d6a0; }
    .bi-book-open { color: #ffb703; }
    .bi-file-text { color: #8b5cf6; }
    .bi-award { color: #f97316; }
    .bi-layers { color: #10b981; }
    .bi-clock-history { color: #f97316; }
    .bi-calendar-check { color: #06d6a0; }
    .bi-megaphone { color: #e11d48; }
    .bi-chat-dots { color: #3b82f6; }
    .bi-cash-stack { color: #10b981; }
    .bi-building { color: #a855f7; }
    .bi-trophy { color: #ffb703; }
    .bi-headset { color: #8b5cf6; }
    .bi-calendar-event { color: #f59e0b; } /* new for Events */

    /* ========== ANIMATED PARTICLES & GLOWING LINE ========== */
    .sidebar-particles {
        padding: 12px 0;
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    .glowing-line {
        height: 1px;
        background: linear-gradient(90deg, transparent, #3b82f6, #8b5cf6, #3b82f6, transparent);
        width: 70%;
        margin: 8px auto;
        border-radius: 2px;
        animation: pulseGlow 2.5s infinite;
    }
    @keyframes pulseGlow {
        0% { opacity: 0.3; width: 50%; }
        50% { opacity: 1; width: 80%; box-shadow: 0 0 6px #3b82f6; }
        100% { opacity: 0.3; width: 50%; }
    }
    .particle-container {
        display: flex;
        justify-content: center;
        gap: 10px;
        height: 30px;
        align-items: center;
    }
    .particle {
        width: 5px;
        height: 5px;
        background: #3b82f6;
        border-radius: 50%;
        animation: floatParticle 2s infinite ease-in-out;
        box-shadow: 0 0 3px #60a5fa;
    }
    .particle:nth-child(1) { animation-delay: 0s; background: #60a5fa; }
    .particle:nth-child(2) { animation-delay: 0.25s; background: #3b82f6; width: 6px; height: 6px; }
    .particle:nth-child(3) { animation-delay: 0.5s; background: #818cf8; }
    .particle:nth-child(4) { animation-delay: 0.75s; background: #a855f7; width: 7px; height: 7px; }
    .particle:nth-child(5) { animation-delay: 1s; background: #60a5fa; }
    .particle:nth-child(6) { animation-delay: 1.25s; background: #3b82f6; }
    .particle:nth-child(7) { animation-delay: 1.5s; background: #8b5cf6; width: 6px; height: 6px; }
    .particle:nth-child(8) { animation-delay: 1.75s; background: #60a5fa; }
    @keyframes floatParticle {
        0% { transform: translateY(0) scale(1); opacity: 0.4; }
        50% { transform: translateY(-8px) scale(1.2); opacity: 1; }
        100% { transform: translateY(0) scale(1); opacity: 0.4; }
    }

    /* ========== FIREFLY BACKGROUND ANIMATION ========== */
    /* Fireflies remain independent – they float over the theme background */
    .firefly {
        position: fixed;
        left: 50%;
        top: 50%;
        width: 0.4vw;
        height: 0.4vw;
        margin: -0.2vw 0 0 9.8vw;
        animation: ease 200s alternate infinite;
        pointer-events: none;
        z-index: 1;
    }
    .firefly::before,
    .firefly::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        transform-origin: -10vw;
    }
    .firefly::before {
        background: black;
        opacity: 0.4;
        animation: drift ease alternate infinite;
    }
    .firefly::after {
        background: white;
        opacity: 0;
        box-shadow: 0 0 0vw 0vw yellow;
        animation: drift ease alternate infinite, flash ease infinite;
    }
    @keyframes drift {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes flash {
        0%, 30%, 100% { opacity: 0; box-shadow: 0 0 0vw 0vw yellow; }
        5% { opacity: 1; box-shadow: 0 0 2vw 0.4vw yellow; }
    }
    <?php
    // Generate unique keyframes for 15 fireflies with random movements
    $quantity = 15;
    for ($i = 1; $i <= $quantity; $i++) {
        $steps = rand(16, 28);
        $rotationSpeed = rand(8, 18) . 's';
        echo "
        .firefly:nth-child({$i}) {
            animation-name: move{$i};
        }
        .firefly:nth-child({$i})::before {
            animation-duration: {$rotationSpeed};
        }
        .firefly:nth-child({$i})::after {
            animation-duration: {$rotationSpeed}, " . rand(5000, 11000) . "ms;
            animation-delay: 0ms, " . rand(500, 8500) . "ms;
        }
        @keyframes move{$i} {";
        for ($step = 0; $step <= $steps; $step++) {
            $percent = $step * (100 / $steps);
            $translateX = rand(-50, 50) . 'vw';
            $translateY = rand(-50, 50) . 'vh';
            $scale = (rand(25, 75) / 100) + 0.25;
            echo "
            {$percent}% {
                transform: translateX({$translateX}) translateY({$translateY}) scale({$scale});
            }";
        }
        echo "
        }
        ";
    }
    ?>
</style>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <!-- User Profile -->
    <div class="app-sidebar__user">
        <div>
            <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname.' '.$lname); ?></p>
            <p class="app-sidebar__user-designation">Teacher</p>
        </div>
    </div>

    <!-- Top Particles -->
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container">
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="app-menu">
        <!-- Dashboard -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'index.php' || $current_page == '') ? 'active' : ''; ?>" href="teacher">
                <i class="app-menu__icon bi bi-speedometer2"></i>
                <span class="app-menu__label">Dashboard</span>
            </a>
        </li>

        <!-- Academic Terms -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'terms.php') ? 'active' : ''; ?>" href="teacher/terms">
                <i class="app-menu__icon bi bi-folder"></i>
                <span class="app-menu__label">Academic Terms</span>
            </a>
        </li>

        <!-- Subject Combinations -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'combinations.php') ? 'active' : ''; ?>" href="teacher/combinations">
                <i class="app-menu__icon bi bi-book-open"></i>
                <span class="app-menu__label">Subject Combinations</span>
            </a>
        </li>

        <!-- Students Management -->
        <li class="treeview <?php echo (in_array($current_page, ['list_students.php', 'export_students.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-people"></i>
                <span class="app-menu__label">Students</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'list_students.php') ? 'active' : ''; ?>" href="teacher/list_students"><i class="icon bi bi-person-badge"></i> List Students</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'export_students.php') ? 'active' : ''; ?>" href="teacher/export_students"><i class="icon bi bi-download"></i> Export Students</a></li>
            </ul>
        </li>

        <!-- Examination Results -->
        <li class="treeview <?php echo (in_array($current_page, ['import_results.php', 'manage_results.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-file-text"></i>
                <span class="app-menu__label">Examination Results</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'import_results.php') ? 'active' : ''; ?>" href="teacher/import_results"><i class="icon bi bi-upload"></i> Import Results</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'manage_results.php') ? 'active' : ''; ?>" href="teacher/manage_results"><i class="icon bi bi-pencil-square"></i> View / Edit Results</a></li>
            </ul>
        </li>

        <!-- Grading System -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'grading-system.php') ? 'active' : ''; ?>" href="teacher/grading-system">
                <i class="app-menu__icon bi bi-award"></i>
                <span class="app-menu__label">Grading System</span>
            </a>
        </li>
        <!-- Teaching Materials (Assessments & Quizzes) -->
        <li class="treeview ...">
            ...
        </li>

        <!-- Quizzes (New interactive assessments) -->
        <li class="treeview <?php echo (in_array($current_page, ['quizzes.php', 'create_quiz.php', 'quiz_results.php', 'edit_quiz.php', 'view_submission.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-pencil-square"></i>
                <span class="app-menu__label">Quizzes</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="app-menu__item" href="teacher/create_material"><i class="app-menu__icon bi bi-cloud-upload"></i><span class="app-menu__label">Create Material</span></a></li>
<li><a class="app-menu__item" href="teacher/quizzes"><i class="app-menu__icon bi bi-pencil-square"></i><span class="app-menu__label">My Quizzes</span></a></li>
<li><a class="app-menu__item" href="teacher/list_materials"><i class="app-menu__icon bi bi-files"></i><span class="app-menu__label">My Uploads</span></a></li>
            </ul>
        </li>

        
        <!-- Division System -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'division-system.php') ? 'active' : ''; ?>" href="teacher/division-system">
                <i class="app-menu__icon bi bi-layers"></i>
                <span class="app-menu__label">Division System</span>
            </a>
        </li>

        <!-- EVENTS (New) -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'events.php' || $current_page == 'year.php' || $current_page == 'month.php' || $current_page == 'eventdetails.php') ? 'active' : ''; ?>" href="teacher/events.php">
                <i class="app-menu__icon bi bi-calendar-event"></i>
                <span class="app-menu__label">Events</span>
            </a>
        </li>

        <!-- Result Change Requests -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'result_requests.php') ? 'active' : ''; ?>" href="teacher/result_requests">
                <i class="app-menu__icon bi bi-chat-dots"></i>
                <span class="app-menu__label">Result Change Requests</span>
            </a>
        </li>
    </ul>
    

    <!-- Bottom Particles -->
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container">
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div>
        </div>
    </div>
</aside>

<!-- Firefly Background Elements (15 animated fireflies) -->
<?php for ($i = 1; $i <= 15; $i++): ?>
    <div class="firefly"></div>
<?php endfor; ?>

<script>
// Treeview toggle functionality (pure JavaScript) – unchanged
document.addEventListener('DOMContentLoaded', function() {
    var toggles = document.querySelectorAll('.app-menu__item.dropdown-toggle');
    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var treeview = this.closest('.treeview');
            if (!treeview) return;
            treeview.classList.toggle('is-expanded');
            var menu = treeview.querySelector(':scope > .treeview-menu');
            if (menu) {
                menu.style.display = treeview.classList.contains('is-expanded') ? 'block' : 'none';
            }
        });
    });
    // Initially expand any treeview that contains an active page
    var activeItems = document.querySelectorAll('.treeview-item.active');
    activeItems.forEach(function(item) {
        var parentTreeview = item.closest('.treeview');
        if (parentTreeview && !parentTreeview.classList.contains('is-expanded')) {
            parentTreeview.classList.add('is-expanded');
            var menu = parentTreeview.querySelector(':scope > .treeview-menu');
            if (menu) menu.style.display = 'block';
        }
    });
});
</script>