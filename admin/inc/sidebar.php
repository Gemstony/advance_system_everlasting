<?php
// Get current script name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Active states for each main dropdown
$is_core_active = ($current_page == 'index.php');

// Results dropdown children
$is_academic_active = ($current_page == 'academic.php');
$is_teachers_active = ($current_page == 'teachers.php');
$is_report_active = ($current_page == 'report.php');
$is_results_active = ($is_academic_active || $is_teachers_active || $is_report_active);

// Students dropdown children
$is_students_active = in_array($current_page, ['register_students.php', 'import_students.php', 'manage_students.php']);

// Settings dropdown children
$is_smtp_active = ($current_page == 'smtp.php');
$is_system_active = ($current_page == 'system.php');
$is_reset_active = ($current_page == 'reset_password.php');
$is_settings_active = ($is_smtp_active || $is_system_active || $is_reset_active);
?>
<style>
    /* ========== SIDEBAR ENHANCEMENTS ========== */
    .app-sidebar {
        background: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%) !important;
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255,255,255,0.08);
    }
    .app-sidebar__user {
        padding: 1.5rem 1rem 0.8rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .app-sidebar__user-name {
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .app-sidebar__user-designation {
        font-size: 0.7rem;
        background: rgba(67,97,238,0.2);
        display: inline-block;
        padding: 0.2rem 0.8rem;
        border-radius: 30px;
        margin-top: 0.3rem;
    }
    .app-menu__item {
        margin: 4px 8px;
        border-radius: 12px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .app-menu__item:hover {
        background: rgba(67,97,238,0.15);
        padding-left: 20px;
    }
    .app-menu__item.active {
        background: linear-gradient(90deg, rgba(67,97,238,0.25), rgba(139,92,246,0.1));
        border-left: 3px solid #4361ee;
        border-radius: 12px 0 0 12px;
    }
    .treeview-menu {
        background: rgba(15,23,42,0.5);
        margin-left: 12px;
        border-radius: 12px;
        padding: 6px 0;
    }
    .treeview-item {
        border-radius: 10px;
        margin: 2px 6px;
        padding: 6px 12px;
        transition: all 0.2s;
    }
    .treeview-item:hover {
        background: rgba(67,97,238,0.2);
        transform: translateX(4px);
    }
    .treeview-item.active {
        background: rgba(67,97,238,0.3);
        color: white;
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

    /* ========== ICON COLORS ========== */
    .app-menu__icon.bi-speedometer2 { color: #4361ee; }
    .treeview > a .app-menu__icon.bi-bar-chart-steps { color: #3b82f6; }
    .treeview > a .app-menu__icon.bi-people { color: #ef476f; }
    .treeview > a .app-menu__icon.bi-gear { color: #8b5cf6; }

    .treeview-menu .bi-person-badge { color: #06d6a0; }
    .treeview-menu .bi-chalkboard { color: #ffb703; }
    .treeview-menu .bi-file-earmark-spreadsheet { color: #8b5cf6; }
    .treeview-menu .bi-envelope-paper { color: #f97316; }
    .treeview-menu .bi-gear-wide-connected { color: #a855f7; }
    .treeview-menu .bi-key { color: #94a3b8; }
    .treeview-menu .bi-person-plus-fill { color: #06d6a0; }
    .treeview-menu .bi-upload { color: #f97316; }
    .treeview-menu .bi-sliders2 { color: #8b5cf6; }

    /* Divider */
    .menu-divider-light {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        margin: 12px 16px;
    }

    /* ========== ANIMATED PARTICLES ========== */
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
</style>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <!-- User Profile with Top Particles -->
    <div class="app-sidebar__user">
        <div>
            <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname.' '.$lname); ?></p>
            <p class="app-sidebar__user-designation">Administrator</p>
        </div>
    </div>
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container">
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div>
        </div>
    </div>

    <!-- Main Menu -->
    <ul class="app-menu">
        <!-- ========== DASHBOARD ========== -->
        <li>
            <a class="app-menu__item <?php echo $is_core_active ? 'active' : ''; ?>" href="admin">
                <i class="app-menu__icon bi bi-speedometer2"></i>
                <span class="app-menu__label">Dashboard</span>
            </a>
        </li>

        <!-- ========== RESULTS DROPDOWN ========== -->
        <li class="treeview <?php echo $is_results_active ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-bar-chart-steps"></i>
                <span class="app-menu__label">Results</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo $is_academic_active ? 'active' : ''; ?>" href="admin/academic"><i class="icon bi bi-person-badge"></i> Academic Account</a></li>
                <li><a class="treeview-item <?php echo $is_teachers_active ? 'active' : ''; ?>" href="admin/teachers"><i class="icon bi bi-chalkboard"></i> Teachers</a></li>
                <li class="menu-divider-light"></li>
                <li><a class="treeview-item <?php echo $is_report_active ? 'active' : ''; ?>" href="admin/report"><i class="icon bi bi-file-earmark-spreadsheet"></i> Exam Report Tool</a></li>
            </ul>
        </li>

        <!-- ========== STUDENTS DROPDOWN ========== -->
        <li class="treeview <?php echo $is_students_active ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-people"></i>
                <span class="app-menu__label">Students</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'register_students.php') ? 'active' : ''; ?>" href="admin/register_students"><i class="icon bi bi-person-plus-fill"></i> Register Students</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'import_students.php') ? 'active' : ''; ?>" href="admin/import_students"><i class="icon bi bi-upload"></i> Import Students</a></li>
                <li><a class="treeview-item <?php echo ($current_page == 'manage_students.php') ? 'active' : ''; ?>" href="admin/manage_students"><i class="icon bi bi-sliders2"></i> Manage Students</a></li>
            </ul>
        </li>

        <!-- ========== SETTINGS DROPDOWN ========== -->
        <li class="treeview <?php echo $is_settings_active ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);">
                <i class="app-menu__icon bi bi-gear"></i>
                <span class="app-menu__label">Settings</span>
                <i class="treeview-indicator bi bi-chevron-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo $is_smtp_active ? 'active' : ''; ?>" href="admin/smtp"><i class="icon bi bi-envelope-paper"></i> SMTP Settings</a></li>
                <li><a class="treeview-item <?php echo $is_system_active ? 'active' : ''; ?>" href="admin/system"><i class="icon bi bi-gear-wide-connected"></i> System Settings</a></li>
                <li><a class="treeview-item <?php echo $is_reset_active ? 'active' : ''; ?>" href="admin/reset_password.php"><i class="icon bi bi-key"></i> Reset Password</a></li>
            </ul>
        </li>
    </ul>

    <!-- Bottom Particles (already present) -->
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container">
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div><div class="particle"></div>
            <div class="particle"></div><div class="particle"></div>
        </div>
    </div>
</aside>

<script>
// Pure JavaScript for dropdown toggles (unchanged)
document.addEventListener('DOMContentLoaded', function() {
    var toggles = document.querySelectorAll('.app-menu__item.dropdown-toggle, .treeview-item.dropdown-toggle');
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
    var expandedMenus = document.querySelectorAll('.treeview.is-expanded > .treeview-menu');
    expandedMenus.forEach(function(menu) {
        menu.style.display = 'block';
    });
});
</script>