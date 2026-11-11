<?php
// inc/sidebar_academic.php - Academic Staff Sidebar (Enhanced Animations)
// Must be included after session start and after $fname, $lname, $pending_requests are defined
if (!isset($fname) || !isset($lname)) {
    if (isset($_SESSION['fname']) && isset($_SESSION['lname'])) {
        $fname = $_SESSION['fname'];
        $lname = $_SESSION['lname'];
    } else {
        $fname = 'Academic';
        $lname = 'Staff';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);

$setup_pages = ['terms.php', 'classes.php', 'subjects.php', 'combinations.php'];
$exam_pages  = ['full results.php', 'manage_results.php', 'individual_results.php', 'change_requests.php'];
$event_pages = ['event-dashboard.php', 'event-add.php', 'event-list.php', 'event-categories.php'];
// Removed $report_pages – no dropdown needed

$is_setup_expanded = in_array($current_page, $setup_pages);
$is_exam_expanded  = in_array($current_page, $exam_pages);
$is_event_expanded = in_array($current_page, $event_pages);
?>
<style>
    /* ===== SIDEBAR – Glass Background with Breathing Glow ===== */
    .app-sidebar {
        background: rgba(10, 10, 20, 0.55) !important;
        backdrop-filter: blur(18px) saturate(180%);
        border-right: 1px solid rgba(255,255,255,0.08);
        transition: box-shadow 0.5s ease;
        box-shadow: 0 0 30px rgba(67,97,238,0.1);
        animation: sidebarGlow 4s infinite alternate;
    }
    @keyframes sidebarGlow {
        0% { box-shadow: 0 0 20px rgba(67,97,238,0.05), inset 0 0 20px rgba(67,97,238,0.02); }
        100% { box-shadow: 0 0 40px rgba(67,97,238,0.2), inset 0 0 30px rgba(67,97,238,0.05); }
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

    /* ===== MENU ITEMS ===== */
    .app-menu__item {
        margin: 4px 12px;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.7rem 1rem;
        background: transparent;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }
    /* Shimmer effect on hover */
    .app-menu__item::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
        transition: left 0.6s;
    }
    .app-menu__item:hover::before {
        left: 100%;
    }
    .app-menu__item:hover {
        background: rgba(67,97,238,0.25);
        transform: translateX(6px) scale(1.02);
        color: white;
        box-shadow: 0 4px 15px rgba(67,97,238,0.15);
    }
    .app-menu__item.active {
        background: linear-gradient(90deg, rgba(67,97,238,0.35), rgba(139,92,246,0.15));
        border-left: 3px solid #4361ee;
        color: white;
        box-shadow: 0 4px 15px rgba(67,97,238,0.1);
    }
    .app-menu__icon {
        font-size: 1.2rem;
        width: 1.5rem;
        text-align: center;
        flex-shrink: 0;
        transition: transform 0.3s ease;
    }
    .app-menu__item:hover .app-menu__icon {
        transform: scale(1.15) rotate(-3deg);
    }
    /* Icon colors */
    .app-menu__icon.bi-speedometer2 { color: #4361ee; }
    .app-menu__icon.bi-gear { color: #8b5cf6; }
    .app-menu__icon.bi-building { color: #06d6a0; }
    .app-menu__icon.bi-people { color: #06d6a0; }
    .app-menu__icon.bi-book { color: #ffb703; }
    .app-menu__icon.bi-diagram-3 { color: #ffb703; }
    .app-menu__icon.bi-arrow-up-circle { color: #10b981; }
    .app-menu__icon.bi-file-earmark-text { color: #f97316; }
    .app-menu__icon.bi-upload { color: #f97316; }
    .app-menu__icon.bi-pencil-square { color: #8b5cf6; }
    .app-menu__icon.bi-person-badge { color: #0ea5e9; }
    .app-menu__icon.bi-arrow-repeat { color: #ef476f; }
    .app-menu__icon.bi-trophy { color: #f59e0b; }
    .app-menu__icon.bi-layers { color: #10b981; }
    .app-menu__icon.bi-calendar3 { color: #f59e0b; }
    .app-menu__icon.bi-calendar-week { color: #f59e0b; }
    .app-menu__icon.bi-plus-circle { color: #22c55e; }
    .app-menu__icon.bi-list-ul { color: #8b5cf6; }
    .app-menu__icon.bi-tags { color: #06b6d4; }
    .app-menu__icon.bi-megaphone { color: #e11d48; }

    /* ===== MKEKA REPORT – RED ICON + RAINBOW BORDER (Always Visible) ===== */
    .app-menu__item.mkeka-item {
        position: relative;
        border: 2px solid transparent;
        border-radius: 16px;
        background: rgba(255,255,255,0.03);
        transition: all 0.3s ease;
        margin: 4px 12px;
    }
    .app-menu__item.mkeka-item::before {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 16px;
        padding: 2px;
        background: conic-gradient(from var(--angle, 0deg), #ff6b6b, #ffd93d, #6bcb77, #4d96ff, #9b59b6, #ff6b6b);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        opacity: 1; /* Always visible */
        animation: rotateBorder 3s linear infinite;
        pointer-events: none;
    }
    @keyframes rotateBorder {
        0% { --angle: 0deg; }
        100% { --angle: 360deg; }
    }
    @property --angle {
        syntax: '<angle>';
        initial-value: 0deg;
        inherits: false;
    }
    .app-menu__item.mkeka-item .app-menu__icon.bi-file-earmark-text {
        color: #dc3545 !important; /* solid red */
        font-size: 1.3rem;
        filter: drop-shadow(0 0 4px rgba(220, 53, 69, 0.3));
    }
    .app-menu__item.mkeka-item:hover .app-menu__icon.bi-file-earmark-text {
        filter: drop-shadow(0 0 8px rgba(220, 53, 69, 0.6));
    }
    .app-menu__item.mkeka-item.active {
        background: linear-gradient(90deg, rgba(220, 53, 69, 0.15), rgba(139,92,246,0.05));
        color: white;
    }
    .app-menu__item.mkeka-item.active .app-menu__icon.bi-file-earmark-text {
        color: #dc3545 !important;
    }

    /* ===== TREEVIEW ===== */
    .treeview-menu {
        background: rgba(15,23,42,0.6);
        backdrop-filter: blur(8px);
        margin-left: 12px;
        border-radius: 12px;
        padding: 6px 0;
        display: none;
        list-style: none;
    }
    .treeview.is-expanded > .treeview-menu {
        display: block;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        0% { opacity: 0; transform: translateY(-8px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .treeview-indicator {
        transition: transform 0.3s ease;
        margin-left: auto;
        font-size: 0.8rem;
    }
    .treeview.is-expanded .treeview-indicator {
        transform: rotate(90deg);
    }
    .treeview-item {
        border-radius: 10px;
        margin: 2px 6px;
        padding: 6px 12px;
        transition: all 0.3s ease;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .treeview-item:hover {
        background: rgba(67,97,238,0.25);
        transform: translateX(6px);
        color: white;
    }
    .treeview-item.active {
        background: rgba(67,97,238,0.4);
        color: white;
    }
    .treeview-item .icon {
        font-size: 1rem;
        width: 1.2rem;
        text-align: center;
        color: #94a3b8;
        transition: transform 0.3s;
    }
    .treeview-item:hover .icon {
        transform: scale(1.1);
        color: #a5b4fc;
    }

    .menu-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        margin: 10px 16px;
    }

    /* ===== BADGE ===== */
    .badge-pending {
        background: #ffb703;
        color: #212529;
        margin-left: 5px;
        font-size: 0.7rem;
        border-radius: 30px;
        padding: 0.2rem 0.5rem;
        font-weight: 700;
        animation: pulseBadge 1.5s infinite;
    }
    @keyframes pulseBadge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    /* ===== CUSTOM SCROLLBAR ===== */
    .app-sidebar::-webkit-scrollbar { width: 4px; }
    .app-sidebar::-webkit-scrollbar-track { background: #1e293b; }
    .app-sidebar::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }

    /* ===== ENHANCED ANIMATIONS: Glowing Line with Moving Gradient ===== */
    .sidebar-particles {
        padding: 12px 0;
        position: relative;
        overflow: hidden;
        text-align: center;
    }
    .glowing-line {
        height: 2px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899, #3b82f6);
        background-size: 300% 100%;
        width: 70%;
        margin: 8px auto;
        border-radius: 4px;
        animation: gradientMove 3s linear infinite, pulseGlow 2s ease-in-out infinite;
    }
    @keyframes gradientMove {
        0% { background-position: 0% 50%; }
        100% { background-position: 300% 50%; }
    }
    @keyframes pulseGlow {
        0%, 100% { opacity: 0.4; box-shadow: 0 0 6px #3b82f6; }
        50% { opacity: 1; box-shadow: 0 0 15px #8b5cf6, 0 0 30px rgba(139,92,246,0.3); }
    }

    /* ===== ORBITING PARTICLES ===== */
    .particle-container {
        position: relative;
        height: 40px;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0;
    }
    .particle {
        position: absolute;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #3b82f6;
        box-shadow: 0 0 8px #60a5fa;
        animation: orbitParticle 4s infinite linear;
    }
    .particle:nth-child(1) { animation-duration: 4s; animation-delay: 0s; background: #60a5fa; width: 7px; height: 7px; }
    .particle:nth-child(2) { animation-duration: 5s; animation-delay: 0.3s; background: #8b5cf6; width: 5px; height: 5px; }
    .particle:nth-child(3) { animation-duration: 3.5s; animation-delay: 0.7s; background: #a855f7; width: 8px; height: 8px; }
    .particle:nth-child(4) { animation-duration: 4.5s; animation-delay: 1.2s; background: #3b82f6; width: 4px; height: 4px; }
    .particle:nth-child(5) { animation-duration: 5.5s; animation-delay: 0.5s; background: #60a5fa; width: 6px; height: 6px; }
    .particle:nth-child(6) { animation-duration: 3.8s; animation-delay: 1.5s; background: #818cf8; width: 7px; height: 7px; }
    .particle:nth-child(7) { animation-duration: 4.2s; animation-delay: 0.9s; background: #a855f7; width: 5px; height: 5px; }
    .particle:nth-child(8) { animation-duration: 4.8s; animation-delay: 1.8s; background: #3b82f6; width: 6px; height: 6px; }

    @keyframes orbitParticle {
        0% { transform: translateX(-20px) scale(0.5); opacity: 0.3; }
        25% { transform: translateX(0px) scale(1.2); opacity: 1; }
        50% { transform: translateX(20px) scale(0.8); opacity: 0.6; }
        75% { transform: translateX(0px) scale(1.1); opacity: 0.8; }
        100% { transform: translateX(-20px) scale(0.5); opacity: 0.3; }
    }

    /* ===== FIREFLY BACKGROUND – Enhanced ===== */
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
        background: rgba(0,0,0,0.3);
        opacity: 0.6;
        animation: drift ease alternate infinite;
    }
    .firefly::after {
        background: radial-gradient(circle, #fcd34d, #f59e0b);
        opacity: 0;
        box-shadow: 0 0 0vw 0vw #fbbf24;
        animation: drift ease alternate infinite, flashFirefly ease infinite;
    }
    @keyframes drift {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes flashFirefly {
        0%, 30%, 100% { opacity: 0; box-shadow: 0 0 0vw 0vw #fbbf24; }
        5% { opacity: 0.9; box-shadow: 0 0 2vw 0.5vw #fbbf24; }
    }
    <?php
    // Generate unique keyframes for 20 fireflies with more random paths
    $quantity = 20;
    for ($i = 1; $i <= $quantity; $i++) {
        $steps = rand(20, 35);
        $rotationSpeed = rand(10, 22) . 's';
        echo "
        .firefly:nth-child({$i}) {
            animation-name: move{$i};
        }
        .firefly:nth-child({$i})::before {
            animation-duration: {$rotationSpeed};
        }
        .firefly:nth-child({$i})::after {
            animation-duration: {$rotationSpeed}, " . rand(3000, 9000) . "ms;
            animation-delay: 0ms, " . rand(300, 8000) . "ms;
        }
        @keyframes move{$i} {";
        for ($step = 0; $step <= $steps; $step++) {
            $percent = $step * (100 / $steps);
            $translateX = rand(-60, 60) . 'vw';
            $translateY = rand(-60, 60) . 'vh';
            $scale = (rand(30, 80) / 100) + 0.2;
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
            <p class="app-sidebar__user-designation">Academic Staff</p>
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
            <a class="app-menu__item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="academic">
                <i class="app-menu__icon bi bi-speedometer2"></i>
                <span class="app-menu__label">Dashboard</span>
            </a>
        </li>

        <!-- Academic Setup -->
        <li class="treeview <?php echo $is_setup_expanded ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-gear"></i>
                <span class="app-menu__label">Academic Setup</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'terms.php') ? 'active' : ''; ?>" href="academic/terms"><i class="icon bi bi-calendar-week"></i> Academic Terms</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>" href="academic/classes"><i class="icon bi bi-building"></i> Classes</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'subjects.php') ? 'active' : ''; ?>" href="academic/subjects"><i class="icon bi bi-book"></i> Subjects</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'combinations.php') ? 'active' : ''; ?>" href="academic/combinations"><i class="icon bi bi-diagram-3"></i> Subject Combinations</a></li>
            </ul>
        </li>

        <!-- Promote Students -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'promote_students.php') ? 'active' : ''; ?>" href="academic/promote_students">
                <i class="app-menu__icon bi bi-arrow-up-circle"></i>
                <span class="app-menu__label">Promote Students</span>
            </a>
        </li>

        <!-- Examination Results -->
        <li class="treeview <?php echo $is_exam_expanded ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-file-earmark-text"></i>
                <span class="app-menu__label">Examination Results</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'full results.php') ? 'active' : ''; ?>" href="academic/full%20results"><i class="icon bi bi-upload"></i> Bulk Results</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'manage_results.php') ? 'active' : ''; ?>" href="academic/manage_results"><i class="icon bi bi-pencil-square"></i> Manage Results</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'individual_results.php') ? 'active' : ''; ?>" href="academic/individual_results"><i class="icon bi bi-person-badge"></i> Individual Results</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'change_requests.php') ? 'active' : ''; ?>" href="academic/change_requests">
                    <i class="icon bi bi-arrow-repeat"></i> Change Requests 
                    <?php if (isset($pending_requests) && $pending_requests > 0): ?>
                        <span class="badge-pending"><?php echo $pending_requests; ?></span>
                    <?php endif; ?>
                </a></li>
            </ul>
        </li>

        <!-- Grading & Division -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'grading-system.php') ? 'active' : ''; ?>" href="academic/grading-system">
                <i class="app-menu__icon bi bi-trophy"></i>
                <span class="app-menu__label">Grading System</span>
            </a>
        </li>
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'division-system.php') ? 'active' : ''; ?>" href="academic/division-system">
                <i class="app-menu__icon bi bi-layers"></i>
                <span class="app-menu__label">Division System</span>
            </a>
        </li>

        <!-- ===== MKEKA REPORT – Standalone with Red Icon & Always‑Visible Rainbow Border ===== -->
        <li>
            <a class="app-menu__item mkeka-item <?php echo ($current_page == 'mkeka_report.php') ? 'active' : ''; ?>" href="academic/mkeka_report">
                <i class="app-menu__icon bi bi-file-earmark-text"></i>
                <span class="app-menu__label">MKEKA Report</span>
            </a>
        </li>

        <div class="menu-divider"></div>

        <!-- Events -->
        <li class="treeview <?php echo $is_event_expanded ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-calendar3"></i>
                <span class="app-menu__label">Events Management</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'event-dashboard.php') ? 'active' : ''; ?>" href="academic/event-dashboard.php"><i class="icon bi bi-calendar-week"></i> Calendar Dashboard</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'event-add.php') ? 'active' : ''; ?>" href="academic/event-add.php"><i class="icon bi bi-plus-circle"></i> Add New Event</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'event-list.php') ? 'active' : ''; ?>" href="academic/event-list.php"><i class="icon bi bi-list-ul"></i> View All Events</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'event-categories.php') ? 'active' : ''; ?>" href="academic/event-categories.php"><i class="icon bi bi-tags"></i> Manage Categories</a></li>
            </ul>
        </li>

        <!-- Announcements -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'announcement.php') ? 'active' : ''; ?>" href="academic/announcement">
                <i class="app-menu__icon bi bi-megaphone"></i>
                <span class="app-menu__label">Post Announcement</span>
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

<!-- Fireflies (20 animated) -->
<?php for ($i = 1; $i <= 20; $i++): ?>
    <div class="firefly"></div>
<?php endfor; ?>

<script>
// Treeview toggle functionality
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
    // Auto-expand active treeviews
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