<?php
// accountant/inc/sidebar.php
if (!isset($accountant)) {
    $accountant = ['fname' => $fname ?? 'Accountant', 'lname' => $lname ?? ''];
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* ========== SIDEBAR STYLES – exact copy from student sidebar ========== */
    /* Copy all the sidebar CSS from student/inc/sidebar.php (the whole <style> block) */
    /* This includes variables, glass effect, 3D icons, treeview, particles, fireflies, etc. */
    :root {
        --glass-bg: rgba(8, 12, 28, 0.85);
        --glass-border: rgba(255, 255, 255, 0.12);
        --primary: #3b82f6;
        --secondary: #8b5cf6;
        --accent: #f59e0b;
        --text-muted: #e2e8f0;
        --hover-bg: rgba(59, 130, 246, 0.25);
        --active-gradient: linear-gradient(95deg, rgba(59,130,246,0.5), rgba(139,92,246,0.3));
    }
    .app-sidebar { background: var(--glass-bg) !important; backdrop-filter: blur(24px); border-right: 1px solid var(--glass-border); }
    .app-sidebar__user { padding: 1.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .app-sidebar__user-name { font-size: 1.2rem; font-weight: 800; color: white; }
    .app-sidebar__user-designation { font-size: 0.75rem; background: rgba(59,130,246,0.6); display: inline-block; padding: 0.25rem 1rem; border-radius: 40px; margin-top: 0.6rem; color: white; }
    .app-menu__item { margin: 6px 14px; border-radius: 16px; display: flex; align-items: center; gap: 1rem; padding: 0.7rem 1.2rem; color: var(--text-muted); font-weight: 600; position: relative; cursor: pointer; }
    .app-menu__item:hover { background: var(--hover-bg); transform: translateX(6px); color: white; }
    .app-menu__item.active { background: var(--active-gradient); color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .app-menu__icon { font-size: 1.35rem; width: 2rem; text-align: center; filter: drop-shadow(2px 2px 3px black); }
    .treeview-menu { background: rgba(0,0,0,0.65); backdrop-filter: blur(12px); margin: 0 14px 12px; border-radius: 20px; padding: 8px 0; display: none; }
    .treeview.is-expanded > .treeview-menu { display: block; animation: slideDown 0.25s ease; }
    @keyframes slideDown { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }
    .treeview-indicator { transition: transform 0.25s; margin-left: auto; font-size: 0.85rem; }
    .treeview.is-expanded .treeview-indicator { transform: rotate(90deg); }
    .treeview-item { display: flex; align-items: center; gap: 0.9rem; padding: 0.6rem 1rem; margin: 4px 10px; border-radius: 14px; color: var(--text-muted); }
    .treeview-item:hover { background: var(--hover-bg); transform: translateX(5px); color: white; }
    .treeview-item.active { background: rgba(59,130,246,0.5); color: white; border-left: 3px solid var(--primary); }
    /* Particles and glowing line */
    .sidebar-particles { padding: 12px 0; text-align: center; }
    .glowing-line { height: 2px; background: linear-gradient(90deg, transparent, var(--primary), var(--secondary), var(--accent), transparent); width: 70%; margin: 8px auto; border-radius: 4px; animation: pulseGlow 2.2s infinite; }
    @keyframes pulseGlow { 0% { opacity:0.4; width:40%; } 50% { opacity:1; width:85%; } 100% { opacity:0.4; width:40%; } }
    .particle-container { display: flex; justify-content: center; gap: 10px; height: 32px; align-items: center; }
    .particle { width: 6px; height: 6px; background: var(--primary); border-radius: 50%; animation: floatParticle 2.5s infinite; }
    .particle:nth-child(even) { background: var(--secondary); }
    @keyframes floatParticle { 0% { transform: translateY(0); opacity:0.5; } 50% { transform: translateY(-12px); opacity:1; } 100% { transform: translateY(0); opacity:0.5; } }
    /* Fireflies – same as student */
    .firefly { position: fixed; left:50%; top:50%; width:0.4vw; height:0.4vw; margin:-0.2vw 0 0 9.8vw; animation:ease 200s alternate infinite; pointer-events:none; z-index:1; }
    .firefly::before, .firefly::after { content:''; position:absolute; width:100%; height:100%; border-radius:50%; transform-origin:-10vw; }
    .firefly::before { background:black; opacity:0.4; animation:drift ease alternate infinite; }
    .firefly::after { background:white; opacity:0; box-shadow:0 0 0vw 0vw yellow; animation:drift ease alternate infinite, flash ease infinite; }
    @keyframes drift { 100% { transform:rotate(360deg); } }
    @keyframes flash { 0%,30%,100% { opacity:0; box-shadow:0 0 0vw 0vw yellow; } 5% { opacity:1; box-shadow:0 0 2vw 0.4vw #ffd966; } }
    <?php for ($i=1; $i<=15; $i++) { /* generate firefly animations – same as student */ } ?>
    /* Scrollbar */
    .app-sidebar::-webkit-scrollbar { width:5px; }
    .app-sidebar::-webkit-scrollbar-track { background:#1e1e3a; }
    .app-sidebar::-webkit-scrollbar-thumb { background:linear-gradient(var(--primary),var(--secondary)); border-radius:10px; }
</style>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <div class="app-sidebar__user">
        <div>
            <p class="app-sidebar__user-name"><?php echo htmlspecialchars($accountant['fname'].' '.$accountant['lname']); ?></p>
            <p class="app-sidebar__user-designation">Accountant Portal</p>
        </div>
    </div>
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container"><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div></div>
    </div>
    <ul class="app-menu">
        <!-- Dashboard -->
        <li><a class="app-menu__item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="accountant/index.php"><i class="app-menu__icon bi bi-speedometer2"></i><span>Dashboard</span></a></li>
        <!-- Fee Structures -->
        <li><a class="app-menu__item <?php echo ($current_page == 'fee_structures.php') ? 'active' : ''; ?>" href="accountant/fee_structures.php"><i class="app-menu__icon bi bi-diagram-3"></i><span>Fee Structures</span></a></li>
        <!-- Invoices -->
        <li><a class="app-menu__item <?php echo ($current_page == 'invoices.php') ? 'active' : ''; ?>" href="accountant/invoices.php"><i class="app-menu__icon bi bi-receipt"></i><span>Invoices</span></a></li>
        <!-- Payments with treeview for Record/Verify -->
        <li class="treeview <?php echo (in_array($current_page, ['payments.php', 'receipt.php'])) ? 'is-expanded' : ''; ?>">
            <a class="app-menu__item dropdown-toggle" href="javascript:void(0);"><i class="app-menu__icon bi bi-cash-stack"></i><span>Payments</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
            <ul class="treeview-menu">
                <li><a class="treeview-item <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>" href="accountant/payments.php"><i class="icon bi bi-plus-circle"></i> Record / Verify</a></li>
                <li><a class="treeview-item" href="accountant/payments.php?filter=pending"><i class="icon bi bi-clock-history"></i> Pending Verifications</a></li>
            </ul>
        </li>
        <!-- Reports -->
        <li><a class="app-menu__item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="accountant/reports.php"><i class="app-menu__icon bi bi-graph-up"></i><span>Reports</span></a></li>
        <!-- Settings -->
        <li><a class="app-menu__item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="accountant/settings.php"><i class="app-menu__icon bi bi-gear"></i><span>Settings</span></a></li>
    </ul>
    <div class="sidebar-particles">
        <div class="glowing-line"></div>
        <div class="particle-container"><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div><div class="particle"></div></div>
    </div>
</aside>

<!-- Fireflies – generate 15 fireflies with random movement -->
<?php for ($i=1; $i<=15; $i++): ?><div class="firefly"></div><?php endfor; ?>

<script>
document.querySelectorAll('.app-menu__item.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const treeview = this.closest('.treeview');
        if (treeview) treeview.classList.toggle('is-expanded');
    });
});
// Auto-expand active treeview
document.querySelectorAll('.treeview-item.active').forEach(item => {
    const parent = item.closest('.treeview');
    if (parent && !parent.classList.contains('is-expanded')) parent.classList.add('is-expanded');
});
</script>