<?php
// inc/sidebar.php - Student Sidebar with enhanced clarity and 3D icons
// Finance & Fees section included - all original links preserved
// ADDED: "Learn" navigation item after School Calendar

if (!isset($fname) || !isset($lname)) {
    if (isset($_SESSION['fname']) && isset($_SESSION['lname'])) {
        $fname = $_SESSION['fname'];
        $lname = $_SESSION['lname'];
    } else {
        $fname = 'Student';
        $lname = '';
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* ========== ROOT VARIABLES – Higher contrast for clarity ========== */
    :root {
        --glass-bg: rgba(8, 12, 28, 0.85);
        --glass-border: rgba(255, 255, 255, 0.12);
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --secondary: #8b5cf6;
        --accent: #f59e0b;
        --text-light: #ffffff;
        --text-muted: #e2e8f0;
        --hover-bg: rgba(59, 130, 246, 0.25);
        --active-gradient: linear-gradient(95deg, rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.3));
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.2);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
    }

    /* Sidebar – deeper glass for better text contrast */
    .app-sidebar {
        background: var(--glass-bg) !important;
        backdrop-filter: blur(24px) saturate(200%);
        border-right: 1px solid var(--glass-border);
        box-shadow: 8px 0 40px rgba(0, 0, 0, 0.3);
    }

    /* User profile – clear separation */
    .app-sidebar__user {
        padding: 1.8rem 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
        background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(139,92,246,0.05));
    }
    .app-sidebar__user-name {
        font-size: 1.2rem;
        font-weight: 800;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        letter-spacing: -0.2px;
    }
    .app-sidebar__user-designation {
        font-size: 0.75rem;
        background: rgba(59,130,246,0.6);
        backdrop-filter: blur(4px);
        display: inline-block;
        padding: 0.25rem 1rem;
        border-radius: 40px;
        margin-top: 0.6rem;
        color: white;
        font-weight: 600;
        border: 0.5px solid rgba(255,255,255,0.3);
    }

    /* Menu items – better spacing and font weight */
    .app-menu__item {
        margin: 6px 14px;
        border-radius: 16px;
        transition: all 0.25s ease;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.7rem 1.2rem;
        background: transparent;
        font-weight: 600;
        font-size: 0.95rem;
        position: relative;
        cursor: pointer;
    }
    /* Active/hover indicator – more visible */
    .app-menu__item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        height: 60%;
        width: 4px;
        background: linear-gradient(180deg, var(--primary), var(--secondary));
        border-radius: 0 6px 6px 0;
        transform: scaleY(0);
        transition: transform 0.2s ease;
    }
    .app-menu__item:hover::before,
    .app-menu__item.active::before {
        transform: scaleY(1);
    }
    .app-menu__item:hover {
        background: var(--hover-bg);
        transform: translateX(6px);
        color: white;
        box-shadow: var(--shadow-sm);
    }
    .app-menu__item.active {
        background: var(--active-gradient);
        color: white;
        box-shadow: var(--shadow-md);
        border-left: none;
    }

    /* ========== 3D ICONS ========== */
    .app-menu__icon {
        font-size: 1.35rem;
        width: 2rem;
        text-align: center;
        filter: drop-shadow(2px 2px 3px rgba(0,0,0,0.4));
        transition: all 0.2s ease;
        display: inline-block;
    }
    /* 3D depth on hover */
    .app-menu__item:hover .app-menu__icon {
        transform: translateY(-2px) scale(1.1);
        filter: drop-shadow(3px 4px 6px rgba(0,0,0,0.5));
    }
    /* Individual icon base colors – vibrant and distinct */
    .app-menu__icon.bi-speedometer2 { color: #3b82f6; text-shadow: 0 1px 2px #1e3a8a; }
    .app-menu__icon.bi-person { color: #10b981; text-shadow: 0 1px 2px #065f46; }
    .app-menu__icon.bi-book-open { color: #f59e0b; text-shadow: 0 1px 2px #b45309; }
    .app-menu__icon.bi-file-text { color: #a855f7; text-shadow: 0 1px 2px #581c87; }
    .app-menu__icon.bi-wallet2 { color: #ec489a; text-shadow: 0 1px 2px #9d174d; }
    .app-menu__icon.bi-megaphone { color: #ef4444; text-shadow: 0 1px 2px #991b1b; }
    .app-menu__icon.bi-building { color: #14b8a6; text-shadow: 0 1px 2px #115e59; }
    .app-menu__icon.bi-calendar3 { color: #06b6d4; text-shadow: 0 1px 2px #155e75; }
    /* Added icon for Learn */
    .app-menu__icon.bi-mortarboard { color: #f97316; text-shadow: 0 1px 2px #9a3412; }

    /* Treeview submenu – improved clarity */
    .treeview-menu {
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(12px);
        margin: 0 14px 12px 14px;
        border-radius: 20px;
        padding: 8px 0;
        display: none;
        border: 0.5px solid rgba(255,255,255,0.1);
        box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    .treeview.is-expanded > .treeview-menu {
        display: block;
        animation: slideDown 0.25s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .treeview-indicator {
        transition: transform 0.25s ease;
        margin-left: auto;
        font-size: 0.85rem;
        filter: drop-shadow(0 1px 1px black);
    }
    .treeview.is-expanded .treeview-indicator {
        transform: rotate(90deg);
    }
    .treeview-item {
        border-radius: 14px;
        margin: 4px 10px;
        padding: 0.6rem 1rem;
        transition: all 0.2s;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.9rem;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .treeview-item:hover {
        background: var(--hover-bg);
        transform: translateX(5px);
        color: white;
    }
    .treeview-item.active {
        background: rgba(59,130,246,0.5);
        color: white;
        border-left: 3px solid var(--primary);
    }
    .treeview-item .icon {
        font-size: 1rem;
        width: 1.6rem;
        filter: drop-shadow(1px 1px 1px black);
        transition: transform 0.2s;
    }
    .treeview-item:hover .icon {
        transform: scale(1.1);
    }

    /* Particles & glowing line – keep but refined */
    .sidebar-particles {
        padding: 12px 0;
        text-align: center;
    }
    .glowing-line {
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--primary), var(--secondary), var(--accent), transparent);
        width: 70%;
        margin: 8px auto;
        border-radius: 4px;
        animation: pulseGlow 2.2s infinite;
    }
    @keyframes pulseGlow {
        0% { opacity: 0.4; width: 40%; }
        50% { opacity: 1; width: 85%; box-shadow: 0 0 10px var(--primary); }
        100% { opacity: 0.4; width: 40%; }
    }
    .particle-container {
        display: flex;
        justify-content: center;
        gap: 10px;
        height: 32px;
        align-items: center;
    }
    .particle {
        width: 6px;
        height: 6px;
        background: var(--primary);
        border-radius: 50%;
        animation: floatParticle 2.5s infinite ease-in-out;
        box-shadow: 0 0 4px var(--primary);
    }
    .particle:nth-child(even) { background: var(--secondary); }
    .particle:nth-child(3n) { background: var(--accent); width: 7px; height: 7px; }
    @keyframes floatParticle {
        0% { transform: translateY(0) scale(1); opacity: 0.5; }
        50% { transform: translateY(-12px) scale(1.2); opacity: 1; }
        100% { transform: translateY(0) scale(1); opacity: 0.5; }
    }

    /* Firefly background – preserved for ambiance */
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
        5% { opacity: 1; box-shadow: 0 0 2vw 0.4vw #ffd966; }
    }
    <?php
    // 15 fireflies with random movement (exact same generation as original)
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

    /* Custom scrollbar – more visible */
    .app-sidebar::-webkit-scrollbar { width: 5px; }
    .app-sidebar::-webkit-scrollbar-track { background: #1e1e3a; border-radius: 10px; }
    .app-sidebar::-webkit-scrollbar-thumb { background: linear-gradient(var(--primary), var(--secondary)); border-radius: 10px; }
</style>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <!-- User Profile (unchanged) -->
    <div class="app-sidebar__user">
        <div>
            <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname.' '.$lname); ?></p>
            <p class="app-sidebar__user-designation">Student Portal</p>
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

    <!-- Navigation Menu – EXACT same links and structure, only visual enhancements -->
    <ul class="app-menu">
        <!-- Dashboard -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="student">
                <i class="app-menu__icon bi bi-speedometer2"></i>
                <span class="app-menu__label">Dashboard</span>
            </a>
        </li>

        <!-- Student Info -->
        <li class="treeview <?php echo (in_array($current_page, ['view.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-person"></i>
                <span class="app-menu__label">Student Info</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'view.php') ? 'active' : ''; ?>" href="student/view"><i class="icon bi bi-person-badge"></i> My Profile</a></li>
            </ul>
        </li>

        <!-- Courses -->
        <li class="treeview <?php echo (in_array($current_page, ['subjects.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-book-open"></i>
                <span class="app-menu__label">Courses</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'subjects.php') ? 'active' : ''; ?>" href="student/subjects"><i class="icon bi bi-book"></i> My Subjects</a></li>
                <li><a class="treeview-item" href="student/timetable"><i class="icon bi bi-calendar-week"></i> Timetable</a></li>
                <li><a class="treeview-item" href="student/syllabus"><i class="icon bi bi-file-earmark-text"></i> Syllabus / Materials</a></li>
            </ul>
        </li>

        <!-- Assessment -->
        <li class="treeview <?php echo (in_array($current_page, ['results.php', 'grading-system.php', 'division-system.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-file-text"></i>
                <span class="app-menu__label">Assessment</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'results.php') ? 'active' : ''; ?>" href="student/results"><i class="icon bi bi-file-text"></i> Examination Results</a></li>
                <li><a class="treeview-item" href="student/assignments"><i class="icon bi bi-pencil-square"></i> Assignments & Quizzes</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'grading-system.php') ? 'active' : ''; ?>" href="student/grading-system"><i class="icon bi bi-award"></i> Grading System</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'division-system.php') ? 'active' : ''; ?>" href="student/division-system"><i class="icon bi bi-layers"></i> Division System</a></li>
            </ul>
        </li>

        <!-- Finance & Fees (School Fees) -->
        <li class="treeview <?php echo (in_array($current_page, ['fees.php', 'invoices.php', 'payment-history.php', 'fee-structure.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-wallet2"></i>
                <span class="app-menu__label">Finance & Fees</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'fees.php') ? 'active' : ''; ?>" href="student/fees"><i class="icon bi bi-credit-card"></i> Fee Dashboard</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'invoices.php') ? 'active' : ''; ?>" href="student/invoices"><i class="icon bi bi-receipt"></i> My Invoices</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'payment-history.php') ? 'active' : ''; ?>" href="student/payment-history"><i class="icon bi bi-clock-history"></i> Payment History</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'fee-structure.php') ? 'active' : ''; ?>" href="student/fee-structure"><i class="icon bi bi-file-text"></i> Fee Structure</a></li>
                <li><a class="treeview-item" href="student/pay-fees"><i class="icon bi bi-cash-stack"></i> Make Payment</a></li>
            </ul>
        </li>

        <!-- Communication -->
        <li class="treeview">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-megaphone"></i>
                <span class="app-menu__label">Communication</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item" href="student/announcements"><i class="icon bi bi-megaphone"></i> Announcements</a></li>
            </ul>
        </li>

        <!-- Support & Extras -->
        <li class="treeview">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-building"></i>
                <span class="app-menu__label">Support & Extras</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item" href="student/helpdesk"><i class="icon bi bi-headset"></i> Helpdesk / Support</a></li>
            </ul>
        </li>

        <!-- School Calendar -->
        <li>
            <a class="app-menu__item <?php echo ($current_page == 'year.php') ? 'active' : ''; ?>" href="student/year.php">
                <i class="app-menu__icon bi bi-calendar3"></i>
                <span class="app-menu__label">School Calendar</span>
            </a>
        </li>

        <!-- Learn / Learning Programs (NEW) -->
        <li>
    <a class="app-menu__item <?php echo ($current_page == 'learning_program.php') ? 'active' : ''; ?>" href="student/learning_program.php">
        <i class="app-menu__icon bi bi-mortarboard"></i>
        <span class="app-menu__label">Learn</span>
    </a>
</li>

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

<!-- Firefly Background (exact same count) -->
<?php for ($i = 1; $i <= 15; $i++): ?>
    <div class="firefly"></div>
<?php endfor; ?>

<script>
// Treeview toggle – IDENTICAL to original, no changes
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
    // Auto-expand active treeview
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