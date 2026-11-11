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

// Fetch announcements for teachers:
// - level 0 = Teachers only
// - level 2 = Both staff and students
// (level 1 = Students only is EXCLUDED)
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Count total announcements (teachers + both)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE level IN (0, 2)");
    $stmt->execute();
    $total_announcements = $stmt->fetchColumn();

    // Count recent (last 30 days)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_announcements WHERE level IN (0, 2) AND create_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recent_announcements = $stmt->fetchColumn();

    // Fetch all relevant announcements
    $stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level IN (0, 2) ORDER BY create_date DESC");
    $stmt->execute();
    $announcements = $stmt->fetchAll();

} catch(PDOException $e) {
    $announcements = [];
    $total_announcements = $recent_announcements = 0;
    $_SESSION['reply'] = [['danger', "Database error: " . $e->getMessage()]];
}

$reply = $_SESSION['reply'] ?? null;
unset($_SESSION['reply']);

// Prepare header data (compatible with inc/header.php)
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
$school_logo = defined('WBLogo') ? WBLogo : 'logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Announcements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        /* Ensure theme background shows through – do NOT override body background */
        .app-content {
            background: transparent !important;
        }
        /* Glass cards & tiles */
        .stat-card, .tile, .info-box, .announcement-card {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            padding: 1.2rem 1rem;
            text-align: center;
            transition: 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2rem;
            color: #3b7cff;
            margin-bottom: 0.3rem;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1e293b;
        }
        .stat-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .info-box {
            border-left: 4px solid #3b7cff;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .announcement-card {
            margin-bottom: 1.5rem;
            padding: 1.2rem;
            transition: 0.2s;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .announcement-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .announcement-date {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .announcement-content {
            color: #334155;
            line-height: 1.5;
        }
        .announcement-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 500;
            background: #eef2ff;
            color: #3b7cff;
            margin-left: 0.5rem;
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
        .dataTables_wrapper .dataTables_filter input {
            background: rgba(255,255,255,0.9);
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            padding: 0.3rem 1rem;
        }
        /* Ensure announcement content HTML renders nicely */
        .announcement-content p {
            margin-bottom: 0.5rem;
        }
        .announcement-content ul, .announcement-content ol {
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
        }
        .announcement-content b, .announcement-content strong {
            font-weight: 600;
        }
        /* Black text for the note */
        .note-text {
            color: #000000 !important;
        }
    </style>
</head>
<body class="app sidebar-mini">

<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; // Role-aware sidebar shows teacher menu based on $level ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-megaphone me-2"></i>
                Announcements
                <small class="text-muted d-block fs-6 mt-1">Official notices from administration</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item active">Announcements</li>
        </ul>
    </div>

    <!-- Reply / Alert Messages -->
    <?php if ($reply): foreach ($reply as $msg): ?>
        <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bi bi-megaphone-fill"></div>
            <div class="stat-value"><?= $total_announcements ?></div>
            <div class="stat-label">Total Announcements</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-calendar-week"></div>
            <div class="stat-value"><?= $recent_announcements ?></div>
            <div class="stat-label">Last 30 Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bi bi-envelope-paper"></div>
            <div class="stat-value"><?= date('F Y') ?></div>
            <div class="stat-label">Current Month</div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Stay Informed:</strong> All official announcements from the school administration are displayed here. 
        <strong class="text-primary">Only announcements intended for staff (or both staff & students) are shown.</strong> Student‑only notices are excluded.
    </div>

    <!-- Announcements List -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-newspaper me-2"></i>Latest Announcements</h3>
                    <?php if (empty($announcements)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                            No announcements available at the moment.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="announcementsTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Content</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['title']) ?></strong>
                                                <?php if ($row['level'] == 0): ?>
                                                    <span class="announcement-badge"><i class="bi bi-person-badge me-1"></i>Staff Only</span>
                                                <?php elseif ($row['level'] == 2): ?>
                                                    <span class="announcement-badge"><i class="bi bi-people me-1"></i>Staff & Students</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space: nowrap;"><?= date('d M Y, H:i', strtotime($row['create_date'])) ?></td>
                                            <td class="announcement-content"><?= $row['announcement'] ?> <!-- Render HTML directly --></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Note with black text -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <p class="mb-0 note-text">
                        <i class="bi bi-bell-fill me-1 text-warning"></i>
                        <strong>Note:</strong> Announcements marked "Staff Only" are visible exclusively to teachers and administrative staff. Those marked "Staff & Students" are visible to the whole school community. Student‑only announcements are not displayed here.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    // Initialize DataTable with custom options
    $('#announcementsTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 10,
        "language": {
            "emptyTable": "No announcements found",
            "search": "Filter:",
            "lengthMenu": "Show _MENU_ announcements per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ announcements"
        },
        "columnDefs": [
            { "orderable": false, "targets": 2 } // Disable sorting on content column
        ]
    });

    // ------------------------------------------------------------
    // Background Synchronization: Sidebar toggle state preservation
    // (identical to teacher dashboard implementation)
    // ------------------------------------------------------------
    let sidebar = $('.app-sidebar, .sidebar, #sidebar').first();
    if (sidebar.length === 0) {
        sidebar = $('aside, .sidebar-nav, .side-menu').first();
        if (sidebar.length) sidebar.addClass('app-sidebar');
    } else {
        sidebar.addClass('app-sidebar');
    }

    function syncSidebarDisplay() {
        if (document.body.classList.contains('sidebar-hidden')) {
            sidebar.css('display', 'none');
        } else {
            sidebar.css('display', '');
        }
    }

    function setSidebarState(collapsed) {
        if (collapsed) {
            document.body.classList.add('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            document.body.classList.remove('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'false');
        }
        syncSidebarDisplay();
    }

    const savedState = localStorage.getItem('sidebarCollapsed');
    const isMobile = window.innerWidth <= 768;

    if (savedState !== null) {
        setSidebarState(savedState === 'true');
    } else {
        setSidebarState(isMobile);
    }

    const sidebarToggle = document.getElementById('sidebarToggle') || document.querySelector('[data-toggle="sidebar"]');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            const currentlyHidden = document.body.classList.contains('sidebar-hidden');
            setSidebarState(!currentlyHidden);
        });
    }

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                syncSidebarDisplay();
            }
        });
    });
    observer.observe(document.body, { attributes: true });

    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (localStorage.getItem('sidebarCollapsed') === null) {
                const nowMobile = window.innerWidth <= 768;
                setSidebarState(nowMobile);
            } else {
                syncSidebarDisplay();
            }
        }, 150);
    });

    syncSidebarDisplay();
});
</script>
</body>
</html>