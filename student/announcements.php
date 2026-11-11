<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if (!($res == "1" && $level == "3")) {
    header("location:../");
    exit;
}

// Fetch student data for header
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
$school_logo = defined('WBLogo') ? WBLogo : 'logo.png';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Fetch student details for header
    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentData) die("Student not found.");

    // Build $student array for modular header
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

// Fetch announcements (original logic)
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $conn->prepare("SELECT id, title, announcement, create_date FROM tbl_announcements WHERE level IN (1,2) ORDER BY create_date DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$total = count($announcements);

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="container-fluid">
        <!-- Enhanced Header with Stats -->
        <div class="glass-header d-flex flex-wrap justify-content-between align-items-center">
            <div class="header-title">
                <h1><i class="bi bi-megaphone-fill me-2" style="color:#4361ee;"></i> Announcements</h1>
                <p class="text-secondary mt-1 mb-0">Stay updated with school news and notices</p>
            </div>
            <div class="header-stats d-flex gap-2 mt-2 mt-md-0">
                <span class="badge-total"><i class="bi bi-envelope-paper me-1"></i> <?php echo $total; ?> total</span>
                <span class="badge-unread" id="unreadBadgeHeader" style="display: none;"><i class="bi bi-eye"></i> <span id="unreadCount">0</span> unread</span>
            </div>
        </div>

        <!-- Enhanced Filter & Actions Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <button class="filter-btn active" data-filter="all">📢 All</button>
                <button class="filter-btn" data-filter="week">📅 This Week</button>
                <button class="filter-btn" data-filter="month">📆 This Month</button>
                <button class="filter-btn" data-filter="year">🗓️ This Year</button>
            </div>
            <div class="filter-actions">
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search announcements...">
                </div>
                <select id="sortOrder" class="sort-select">
                    <option value="newest">🆕 Newest first</option>
                    <option value="oldest">🕰️ Oldest first</option>
                </select>
                <button id="markAllReadBtn" class="mark-read-btn"><i class="bi bi-envelope-check"></i> Mark all read</button>
            </div>
        </div>

        <!-- Announcements Grid / List Container -->
        <div id="announcementsContainer" class="announcements-grid"></div>
        
        <!-- Pagination -->
        <div id="paginationContainer" class="pagination-wrapper"></div>
    </div>
</main>

<!-- Enhanced Page Styles (Glassmorphism + Grid, without altering core logic) -->
<style>
    /* Reset / Base */
    .app-content {
        background: transparent !important;
        position: relative;
        z-index: 1;
        padding: 1rem;
    }
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Glass Header */
    .glass-header {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border-radius: 32px;
        padding: 1.2rem 1.5rem;
        margin-bottom: 1.8rem;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        transition: all 0.2s;
    }
    .glass-header h1 {
        font-size: 1.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #1e293b, #2d3a5e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin: 0;
    }
    .header-stats .badge-total, .badge-unread {
        background: linear-gradient(135deg, #4361ee, #8b5cf6);
        border-radius: 40px;
        padding: 0.3rem 1rem;
        color: white;
        font-weight: 600;
        font-size: 0.75rem;
        box-shadow: 0 2px 8px rgba(67,97,238,0.3);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .badge-unread {
        background: linear-gradient(135deg, #ef476f, #f78c6b);
    }

    /* Filter Bar */
    .filter-bar {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        border-radius: 60px;
        padding: 0.6rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border: 1px solid rgba(255,255,255,0.6);
    }
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }
    .filter-btn {
        background: transparent;
        border: none;
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
        color: #334155;
        cursor: pointer;
        font-family: inherit;
    }
    .filter-btn.active {
        background: #4361ee;
        color: white;
        box-shadow: 0 4px 12px rgba(67,97,238,0.3);
    }
    .filter-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem;
    }
    .search-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .search-icon {
        position: absolute;
        left: 14px;
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }
    .search-input, .sort-select, .mark-read-btn {
        border-radius: 40px;
        padding: 0.5rem 1rem 0.5rem 2.2rem;
        font-size: 0.85rem;
        border: 1px solid rgba(0,0,0,0.08);
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(4px);
        font-family: inherit;
    }
    .search-input:focus {
        border-color: #4361ee;
        outline: none;
        box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
    }
    .sort-select {
        padding: 0.5rem 1rem;
        cursor: pointer;
    }
    .mark-read-btn {
        background: rgba(241,245,249,0.9);
        color: #4361ee;
        font-weight: 500;
        cursor: pointer;
        padding: 0.5rem 1.2rem;
        transition: all 0.2s;
    }
    .mark-read-btn:hover {
        background: #e2e8f0;
        transform: translateY(-1px);
    }

    /* Announcements Grid - Responsive cards */
    .announcements-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-top: 0.5rem;
    }
    @media (min-width: 768px) {
        .announcements-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (min-width: 1200px) {
        .announcements-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* Enhanced Card Design */
    .announcement-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        border-radius: 28px;
        transition: all 0.3s cubic-bezier(0.2, 0, 0, 1);
        border: 1px solid rgba(255,255,255,0.7);
        overflow: hidden;
        position: relative;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        height: 100%;
        box-shadow: 0 8px 20px rgba(0,0,0,0.03);
    }
    .announcement-card.unread {
        border-left: 6px solid #ef476f;
        background: rgba(255, 245, 245, 0.96);
    }
    .announcement-card.read {
        border-left: 6px solid #10b981;
        opacity: 0.92;
    }
    .announcement-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 30px -12px rgba(0,0,0,0.2);
        background: rgba(255, 255, 255, 1);
    }
    .card-header {
        padding: 1.2rem 1.2rem 0.6rem 1.2rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.8rem;
        flex-wrap: wrap;
    }
    .title-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    .announcement-icon {
        width: 44px;
        height: 44px;
        background: rgba(67,97,238,0.1);
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #4361ee;
        flex-shrink: 0;
    }
    .announcement-card.unread .announcement-icon {
        background: rgba(239,71,111,0.15);
        color: #ef476f;
    }
    .announcement-card.read .announcement-icon {
        background: rgba(16,185,129,0.1);
        color: #10b981;
    }
    .announcement-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
        margin: 0;
    }
    .new-badge {
        background: #ef476f;
        border-radius: 30px;
        padding: 0.2rem 0.7rem;
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        letter-spacing: 0.5px;
    }
    .announcement-date {
        font-size: 0.7rem;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 5px;
        background: rgba(0,0,0,0.03);
        padding: 0.2rem 0.6rem;
        border-radius: 30px;
        white-space: nowrap;
    }
    .card-preview {
        padding: 0.2rem 1.2rem 0.8rem 1.2rem;
        color: #334155;
        line-height: 1.5;
        font-size: 0.85rem;
        flex: 1;
    }
    .expand-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.7rem;
        color: #4361ee;
        margin-top: 8px;
        font-weight: 500;
    }
    .card-full {
        padding: 0 1.2rem 1.2rem 1.2rem;
        display: none;
        border-top: 1px solid rgba(0,0,0,0.06);
        margin-top: 0.2rem;
        color: #1e293b;
        line-height: 1.6;
        font-size: 0.85rem;
        background: rgba(248,250,252,0.5);
        border-radius: 0 0 24px 24px;
    }
    .card-full.show {
        display: block;
        animation: fadeSlide 0.25s ease;
    }
    @keyframes fadeSlide {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .toggle-icon {
        transition: transform 0.2s;
        font-size: 0.8rem;
    }
    .announcement-card.expanded .toggle-icon {
        transform: rotate(180deg);
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 2.5rem;
        margin-bottom: 1rem;
    }
    .pagination {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(8px);
        padding: 0.5rem 0.8rem;
        border-radius: 60px;
    }
    .page-btn {
        background: rgba(255,255,255,0.9);
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 40px;
        padding: 0.35rem 0.9rem;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        color: #1e293b;
    }
    .page-btn.active {
        background: #4361ee;
        color: white;
        border-color: #4361ee;
        box-shadow: 0 2px 8px rgba(67,97,238,0.3);
    }
    .page-btn:hover:not(.active) {
        background: #e2e8f0;
        transform: translateY(-1px);
    }
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(8px);
        border-radius: 40px;
        color: #475569;
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.6;
        margin-bottom: 1rem;
        display: inline-block;
    }

    /* Dark mode compatibility */
    body.dark .glass-header,
    body.dark .filter-bar,
    body.dark .announcement-card,
    body.dark .pagination,
    body.dark .empty-state {
        background: rgba(30, 41, 59, 0.85);
        border-color: rgba(255,255,255,0.1);
    }
    body.dark .glass-header h1 {
        background: linear-gradient(135deg, #e2e8f0, #a0aec0);
        -webkit-background-clip: text;
        background-clip: text;
    }
    body.dark .announcement-title,
    body.dark .card-preview,
    body.dark .card-full {
        color: #e2e8f0;
    }
    body.dark .announcement-date {
        color: #94a3b8;
    }
    body.dark .filter-btn {
        color: #cbd5e1;
    }
    body.dark .filter-btn.active {
        background: #4361ee;
        color: white;
    }
    body.dark .search-input,
    body.dark .sort-select,
    body.dark .mark-read-btn {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }
    body.dark .announcement-card.unread {
        background: rgba(239,71,111,0.12);
    }
    body.dark .card-full {
        background: rgba(15,23,42,0.6);
    }

    @media (max-width: 767px) {
        .glass-header { padding: 1rem; }
        .card-header { padding: 1rem 1rem 0.2rem 1rem; }
        .announcement-title { font-size: 1rem; }
    }
</style>

<!-- Page-specific Scripts (enhanced JS but same core logic) -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
    const announcementsData = <?php echo json_encode($announcements); ?>;
    const STORAGE_KEY = 'readAnnouncements';
    let readIds = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    let currentFilter = 'all';
    let currentSearch = '';
    let currentSort = 'newest';
    let currentPage = 1;
    const itemsPerPage = 9; // Increased for grid layout

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function filterByDate(ann, filter) {
        const now = new Date();
        const annDate = new Date(ann.create_date);
        if (filter === 'all') return true;
        if (filter === 'week') {
            const weekAgo = new Date();
            weekAgo.setDate(now.getDate() - 7);
            return annDate >= weekAgo;
        }
        if (filter === 'month') {
            const monthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
            return annDate >= monthAgo;
        }
        if (filter === 'year') {
            const yearAgo = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
            return annDate >= yearAgo;
        }
        return true;
    }

    function filterBySearch(ann, term) {
        return term ? ann.title.toLowerCase().includes(term.toLowerCase()) : true;
    }

    function getFilteredAndSorted() {
        let filtered = announcementsData.filter(ann => filterByDate(ann, currentFilter) && filterBySearch(ann, currentSearch));
        filtered.sort((a,b) => {
            const dateA = new Date(a.create_date);
            const dateB = new Date(b.create_date);
            return currentSort === 'newest' ? dateB - dateA : dateA - dateB;
        });
        return filtered;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, m => (m === '&' ? '&amp;' : m === '<' ? '&lt;' : m === '>' ? '&gt;' : m));
    }

    function updateUnreadBadge() {
        let unreadCount = 0;
        document.querySelectorAll('.announcement-card').forEach(card => {
            const id = parseInt(card.dataset.id);
            if (!readIds.includes(id)) unreadCount++;
        });
        const unreadSpan = document.getElementById('unreadCount');
        const badgeDiv = document.getElementById('unreadBadgeHeader');
        if (unreadCount > 0) {
            if (unreadSpan) unreadSpan.innerText = unreadCount;
            if (badgeDiv) badgeDiv.style.display = 'inline-flex';
        } else {
            if (badgeDiv) badgeDiv.style.display = 'none';
        }
    }

    function markAsRead(id) {
        if (!readIds.includes(id)) {
            readIds.push(id);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(readIds));
            render();
        }
    }

    function markAllAsRead() {
        const allIds = announcementsData.map(a => a.id);
        allIds.forEach(id => {
            if (!readIds.includes(id)) readIds.push(id);
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(readIds));
        render();
    }

    function attachCardEvents() {
        document.querySelectorAll('.announcement-card').forEach(card => {
            const fullDiv = card.querySelector('.card-full');
            const toggleIcon = card.querySelector('.toggle-icon');
            // Remove any previous listener to avoid duplication, but we'll use fresh
            card.removeEventListener('click', card.clickHandler);
            const clickHandler = function(e) {
                fullDiv.classList.toggle('show');
                card.classList.toggle('expanded');
                if (fullDiv.classList.contains('show')) {
                    if (toggleIcon) toggleIcon.classList.replace('bi-chevron-down', 'bi-chevron-up');
                    const id = parseInt(card.dataset.id);
                    if (!readIds.includes(id)) markAsRead(id);
                } else {
                    if (toggleIcon) toggleIcon.classList.replace('bi-chevron-up', 'bi-chevron-down');
                }
            };
            card.clickHandler = clickHandler;
            card.addEventListener('click', clickHandler);
        });
    }

    function attachPaginationEvents() {
        document.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                currentPage = parseInt(this.dataset.page);
                render();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    function render() {
        const filtered = getFilteredAndSorted();
        const totalPages = Math.ceil(filtered.length / itemsPerPage);
        if (totalPages > 0 && currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const start = (currentPage - 1) * itemsPerPage;
        const pageItems = filtered.slice(start, start + itemsPerPage);

        const container = document.getElementById('announcementsContainer');
        if (!pageItems.length) {
            container.innerHTML = `<div class="empty-state"><i class="bi bi-inbox-fill"></i><p class="mt-2 mb-0">No announcements match your criteria.</p><small>Try adjusting filters or search.</small></div>`;
            document.querySelector('.pagination-wrapper').innerHTML = '';
            updateUnreadBadge();
            return;
        }

        let html = '';
        pageItems.forEach(ann => {
            const isRead = readIds.includes(ann.id);
            const plainText = ann.announcement.replace(/<[^>]*>/g, '');
            const preview = plainText.substring(0, 110) + (plainText.length > 110 ? '...' : '');
            const isNew = (new Date() - new Date(ann.create_date)) < 7 * 24 * 60 * 60 * 1000;
            const readClass = isRead ? 'read' : 'unread';
            html += `
                <div class="announcement-card ${readClass}" data-id="${ann.id}">
                    <div class="card-header">
                        <div class="title-section">
                            <div class="announcement-icon"><i class="bi bi-megaphone"></i></div>
                            <div>
                                <div class="announcement-title">${escapeHtml(ann.title)}</div>
                                ${isNew ? '<span class="new-badge mt-1 d-inline-block">NEW</span>' : ''}
                            </div>
                        </div>
                        <div class="announcement-date"><i class="bi bi-calendar3"></i> ${formatDate(ann.create_date)}</div>
                    </div>
                    <div class="card-preview">
                        ${escapeHtml(preview)}
                        <div class="expand-indicator">
                            <i class="bi bi-chevron-down toggle-icon"></i> Read more
                        </div>
                    </div>
                    <div class="card-full">${ann.announcement}</div>
                </div>
            `;
        });
        container.innerHTML = html;

        let paginationHtml = '';
        if (totalPages > 1) {
            paginationHtml = `<div class="pagination">`;
            for (let i = 1; i <= totalPages; i++) {
                paginationHtml += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            paginationHtml += `</div>`;
        }
        document.querySelector('.pagination-wrapper').innerHTML = paginationHtml;

        attachCardEvents();
        attachPaginationEvents();
        updateUnreadBadge();
    }

    function initUI() {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                currentPage = 1;
                render();
            });
        });
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.addEventListener('input', function() {
            currentSearch = this.value;
            currentPage = 1;
            render();
        });
        const sortSelect = document.getElementById('sortOrder');
        if (sortSelect) sortSelect.addEventListener('change', function() {
            currentSort = this.value;
            currentPage = 1;
            render();
        });
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) markAllBtn.addEventListener('click', markAllAsRead);
    }

    initUI();
    render();
</script>
</body>
</html>