<?php
// Change to parent directory so includes work correctly
chdir(__DIR__ . '/../');

// Start session safely (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include core configuration and helpers
$required_files = [
    'db/config.php',
    'const/school.php',
    'const/check_session.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Critical file missing: $file. Please check your installation.");
    }
    require_once $file;
}

// Ensure user is logged in as admin (level 0)
if (!isset($res) || $res != "1" || !isset($level) || $level != "0") {
    header("Location: ../");
    exit;
}

// -------------------------------------------------------------------
// Gather all dashboard statistics
// -------------------------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset,
        DBUser,
        DBPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Ensure images table exists with file_path column
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_images` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_type` varchar(50) NOT NULL,
        `file_path` varchar(255) DEFAULT NULL,
        `file_name` varchar(255) DEFAULT NULL,
        `image_data` longblob,
        `mime_type` varchar(50),
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `image_type` (`image_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Fetch signature and seal information for preview
    $signatureData = null;
    $sealData = null;
    $signatureMime = '';
    $sealMime = '';
    $signaturePath = null;
    $sealPath = null;

    $stmt = $pdo->prepare("SELECT file_path, image_data, mime_type FROM tbl_images WHERE image_type = 'signature' LIMIT 1");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['file_path'])) {
            $signaturePath = $row['file_path'];
        } elseif (!empty($row['image_data'])) {
            $signatureData = base64_encode($row['image_data']);
            $signatureMime = $row['mime_type'];
        }
    }

    $stmt = $pdo->prepare("SELECT file_path, image_data, mime_type FROM tbl_images WHERE image_type = 'seal' LIMIT 1");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['file_path'])) {
            $sealPath = $row['file_path'];
        } elseif (!empty($row['image_data'])) {
            $sealData = base64_encode($row['image_data']);
            $sealMime = $row['mime_type'];
        }
    }

    // Basic counts
    $terms_count        = $pdo->query("SELECT COUNT(*) FROM tbl_terms")->fetchColumn();
    $teachers_count     = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
    $students_count     = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $active_students    = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE status = 1")->fetchColumn();
    $inactive_students  = $students_count - $active_students;
    $subjects_count     = $pdo->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();
    $classes_count      = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
    $combinations_count = $pdo->query("SELECT COUNT(*) FROM tbl_subject_combinations")->fetchColumn();

    $admin_count    = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 0")->fetchColumn();
    $academic_count = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1")->fetchColumn();

    $male_students   = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Male'")->fetchColumn();
    $female_students = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Female'")->fetchColumn();
    $other_gender    = $students_count - ($male_students + $female_students);

    $active_term = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $active_term_name = $active_term ? $active_term['name'] : 'None';
    $active_term_id   = $active_term ? $active_term['id'] : 0;

    if ($active_term_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE term = ?");
        $stmt->execute([$active_term_id]);
        $results_entered = $stmt->fetchColumn();
    } else {
        $results_entered = 0;
    }

    $class_enrollment = $pdo->query("
        SELECT c.name, COUNT(s.id) as count
        FROM tbl_classes c
        LEFT JOIN tbl_students s ON c.id = s.class
        GROUP BY c.id
        ORDER BY c.name
    ")->fetchAll();

    $db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
                             FROM information_schema.tables 
                             WHERE table_schema = DATABASE()")->fetchColumn() ?: 0;

    $php_version   = phpversion();
    $mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn();

    $smtp = $pdo->query("SELECT * FROM tbl_smtp LIMIT 1")->fetch();
    $smtp_configured = ($smtp && !empty($smtp['server']) && $smtp['server'] != 'smtp server here');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_staff WHERE id = 1 AND password = ?");
    $stmt->execute(['$2y$10$S/yKJwxGoP12NHzY9.KmSe3Yyz4fgrozEOg8I9GcXjoiGmoDJFAYS']);
    $default_admin_check = $stmt->fetchColumn();
    $using_default_password = ($default_admin_check > 0);

    $backup_file = __DIR__ . '/../backups/latest.sql';
    $last_backup = file_exists($backup_file) ? date('Y-m-d H:i', filemtime($backup_file)) : 'Never';

    $recent_staff = $pdo->query("
        SELECT id, fname, lname, level, email
        FROM tbl_staff
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll();

    $recent_students = $pdo->query("
        SELECT id, fname, mname, lname, class
        FROM tbl_students
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll();

    $recent_results = $pdo->query("
        SELECT r.student, r.score, r.term, t.name as term_name, s.fname, s.lname
        FROM tbl_exam_results r
        LEFT JOIN tbl_students s ON r.student = s.id
        LEFT JOIN tbl_terms t ON r.term = t.id
        ORDER BY r.id DESC
        LIMIT 5
    ")->fetchAll();

    // Fetch existing contact info for the modal
    $contact = ['location' => '', 'phone1' => '', 'phone2' => '', 'email' => ''];
    try {
        $stmt = $pdo->query("SELECT location, phone1, phone2, email FROM tbl_school_contacts LIMIT 1");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contact = array_merge($contact, $row);
        }
    } catch (PDOException $e) {
        // Table may not exist – ignore
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Determine greeting based on hour (for the main title)
$hour = date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good morning';
    $greeting_icon = 'bi-brightness-high-fill';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good afternoon';
    $greeting_icon = 'bi-sun-fill';
} else {
    $greeting = 'Good evening';
    $greeting_icon = 'bi-moon-stars-fill';
}

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';

// Prepare staff array for the unified admin header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Admin',
    'lname'         => $lname ?? '',
    'display_image' => $img ?? null,
    'email'         => $email ?? '',
    'gender'        => $gender ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ===== DASHBOARD STYLES – ONLY SUPPLEMENTS THE HEADER THEME ===== */
        /* No body background – let the header's 30 themes show through */
        .app-content {
            background: transparent !important;
        }

        /* Glass stat cards – same as academic dashboard */
        .stat-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.2rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .stat-icon {
            font-size: 2.2rem;
            color: #4361ee;
            background: rgba(67,97,238,0.1);
            width: 60px;
            height: 60px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            color: #1e293b;
        }
        .stat-label {
            color: #475569;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Tiles – glass */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .tile:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        /* Quick action buttons */
        .quick-action .btn {
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            margin: 0 4px 8px 0;
            border: none;
        }
        .quick-action .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        .btn-primary { background: #4361ee; color: white; }
        .btn-success { background: #06d6a0; color: white; }
        .btn-info { background: #4cc9f0; color: white; }
        .btn-warning { background: #ffb703; color: #212529; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-outline-primary {
            background: transparent;
            border: 2px solid #4361ee;
            color: #4361ee;
        }
        .btn-outline-primary:hover {
            background: #4361ee;
            color: white;
        }

        /* Alert boxes */
        .alert-box {
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(8px);
            border-left: 6px solid;
        }
        .alert-box.warning {
            background: rgba(255,183,3,0.15);
            border-left-color: #ffb703;
            color: #92400e;
        }

        /* Chart containers */
        .chart-container {
            height: 250px;
            width: 100%;
            margin: 1rem 0;
        }

        /* Activity items */
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .activity-item:last-child { border-bottom: none; }

        /* Badges */
        .badge-level-0 { background: #ef476f; color: white; }
        .badge-level-1 { background: #ffb703; color: #212529; }
        .badge-level-2 { background: #06d6a0; color: white; }

        /* Modals – glass */
        .modal-content {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 28px;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.08);
        }
        .form-control {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }

        /* Image preview */
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border: 3px dashed rgba(67,97,238,0.3);
            border-radius: 20px;
            padding: 12px;
            margin-top: 12px;
            background: rgba(255,255,255,0.5);
        }

        /* DASHBOARD FOOTER – White card, no blur, clear text */
        .dashboard-footer {
            margin-top: 4rem;
            padding: 2rem;
            text-align: center;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            color: #1e293b;
            font-size: 0.95rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .dashboard-footer p {
            margin: 0;
            color: #1e293b;
        }

        /* Title stays white for header contrast */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
        }
        .breadcrumb {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            border-radius: 40px;
        }
        .breadcrumb-item a, .breadcrumb-item.active {
            color: white !important;
        }
    </style>
</head>
<body>

    <!-- Unified Admin Header (includes 30 themes, sidebar toggle, notifications) -->
    <?php require 'inc/header_admin.php'; ?>
    
    <!-- Shared Sidebar (role‑aware: admin sees admin menu) -->
    <?php require 'inc/sidebar.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div>
                <h1>
                    <i class="bi <?php echo $greeting_icon; ?> me-2"></i>
                    <?php echo $greeting . ', ' . htmlspecialchars($fname . ' ' . $lname); ?>
                    <small class="text-muted d-block fs-6 mt-1"><?php echo htmlspecialchars($school_name); ?></small>
                </h1>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-action mb-4">
            <a href="admin/register_students" class="btn btn-primary"><i class="bi bi-person-plus"></i> Register Student</a>
            <a href="admin/teachers?action=add" class="btn btn-success"><i class="bi bi-person-badge"></i> Add Teacher</a>
            <a href="admin/academic?action=add" class="btn btn-info"><i class="bi bi-person-workspace"></i> Add Academic</a>
            <a href="admin/system" class="btn btn-warning"><i class="bi bi-gear"></i> System Settings</a>
            <a href="admin/backup" class="btn btn-secondary" onclick="return confirm('Start manual backup?');"><i class="bi bi-database"></i> Backup Now</a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                <i class="bi bi-telephone"></i> School Contact Info
            </button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#signatureSealModal">
                <i class="bi bi-pen"></i> Signature & Seal
            </button>
        </div>

        <!-- Alerts -->
        <div class="row">
            <div class="col-12">
                <?php if (!$smtp_configured): ?>
                    <div class="alert-box warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>SMTP not configured.</strong> Email notifications will not work. 
                        <a href="admin/smtp" class="alert-link">Configure now</a>.
                    </div>
                <?php endif; ?>
                <?php if ($using_default_password): ?>
                    <div class="alert-box warning">
                        <i class="bi bi-shield-lock-fill me-2"></i>
                        <strong>Default admin password still in use.</strong> Change it immediately for security.
                        <a href="admin/profile" class="alert-link">Change password</a>.
                    </div>
                <?php endif; ?>
                <?php if ($active_term_id == 0): ?>
                    <div class="alert-box">
                        <i class="bi bi-calendar-x me-2"></i>
                        <strong>No active term set.</strong> Teachers cannot enter results. 
                        <a href="academic/terms" class="alert-link">Activate a term</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KPI Cards - First Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($students_count); ?></div>
                        <div class="stat-label">Total Students</div>
                        <small class="text-muted"><?php echo $active_students; ?> active, <?php echo $inactive_students; ?> inactive</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($teachers_count); ?></div>
                        <div class="stat-label">Teachers</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($academic_count); ?></div>
                        <div class="stat-label">Academic Staff</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-shield-lock"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($admin_count); ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards - Second Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-book"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($subjects_count); ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($classes_count); ?></div>
                        <div class="stat-label">Classes</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($combinations_count); ?></div>
                        <div class="stat-label">Combinations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($terms_count); ?></div>
                        <div class="stat-label">Terms</div>
                        <small class="text-muted">Active: <?php echo htmlspecialchars($active_term_name); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-pie-chart-fill"></i> Student Gender Distribution</h4>
                    <div class="chart-container">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-bar-chart-fill"></i> Students per Class</h4>
                    <div class="chart-container">
                        <canvas id="classChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health & Activity -->
        <div class="row g-4">
            <div class="col-md-4">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-hdd-stack me-2"></i> System Health</h4>
                    <table class="table table-sm">
                        <tr><th>Database Size</th><td><?php echo $db_size; ?> MB</span></tr>
                        <tr><th>Last Backup</th><td><?php echo htmlspecialchars($last_backup); ?></span></tr>
                        <tr><th>PHP Version</th><td><?php echo htmlspecialchars($php_version); ?></span></tr>
                        <tr><th>MySQL Version</th><td><?php echo htmlspecialchars($mysql_version); ?></span></tr>
                        <tr><th>SMTP</th><td><?php echo $smtp_configured ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-danger">Not configured</span>'; ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-person-plus me-2"></i> Recent Staff</h4>
                    <div class="activity-feed">
                        <?php if (empty($recent_staff)): ?>
                            <p class="text-muted">No staff added recently.</p>
                        <?php else: ?>
                            <?php foreach ($recent_staff as $s): 
                                $level_class = 'badge-level-'.$s['level'];
                            ?>
                                <div class="activity-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-person-circle me-2 text-primary"></i>
                                        <strong><?php echo htmlspecialchars($s['fname'].' '.$s['lname']); ?></strong>
                                        <span class="badge <?php echo $level_class; ?> ms-2">
                                            <?php echo ['Admin','Academic','Teacher'][$s['level']]; ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['email']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-people me-2"></i> Recent Students</h4>
                    <div class="activity-feed">
                        <?php if (empty($recent_students)): ?>
                            <p class="text-muted">No students added recently.</p>
                        <?php else: ?>
                            <?php foreach ($recent_students as $s): ?>
                                <div class="activity-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-person-circle me-2 text-success"></i>
                                        <strong><?php echo htmlspecialchars($s['fname'].' '.$s['mname'].' '.$s['lname']); ?></strong>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['id']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Exam Results -->
        <div class="row g-4 mt-2">
            <div class="col-12">
                <div class="tile">
                    <h4 class="tile-title"><i class="bi bi-file-text me-2"></i> Latest Exam Results Entered</h4>
                    <div class="activity-feed">
                        <?php if (empty($recent_results)): ?>
                            <p class="text-muted">No results entered recently.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th>Student</th><th>Score</th><th>Term</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($recent_results as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['fname'].' '.$r['lname']); ?></td>
                                            <td><?php echo htmlspecialchars($r['score']); ?>%</span>
                                            <td><?php echo htmlspecialchars($r['term_name']); ?></span>
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

        <!-- Dashboard Footer – White card, no blur, clear text -->
        <div class="dashboard-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All rights reserved. | SRMS v2.0</p>
        </div>
    </main>

    <!-- Contact Information Modal -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-telephone me-2"></i>School Contact Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">This information will appear on student report cards.</p>
                    <form id="contactForm">
                        <div class="mb-3">
                            <label for="location" class="form-label">Location / Address</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($contact['location']); ?>" placeholder="e.g., P.O. Box 1234, Dar es Salaam">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone1" class="form-label">Phone Number 1</label>
                                <input type="text" class="form-control" id="phone1" name="phone1" value="<?php echo htmlspecialchars($contact['phone1']); ?>" placeholder="+255 XXX XXX XXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone2" class="form-label">Phone Number 2</label>
                                <input type="text" class="form-control" id="phone2" name="phone2" value="<?php echo htmlspecialchars($contact['phone2']); ?>" placeholder="(optional)">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($contact['email']); ?>" placeholder="info@school.edu">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveContactBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Signature & Seal Modal -->
    <div class="modal fade" id="signatureSealModal" tabindex="-1" aria-labelledby="signatureSealModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pen me-2"></i>Manage Signature & Seal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="imageTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="signature-tab" data-bs-toggle="tab" data-bs-target="#signature">Head of School Signature</button></li>
                        <li class="nav-item"><button class="nav-link" id="seal-tab" data-bs-toggle="tab" data-bs-target="#seal">School Seal</button></li>
                    </ul>
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="signature">
                            <div class="text-center">
                                <?php if ($signaturePath): ?>
                                    <img src="<?php echo $signaturePath; ?>" class="image-preview" alt="Current Signature">
                                    <p class="mt-2 text-muted">Current Signature</p>
                                    <button class="btn btn-danger delete-signature" data-type="signature"><i class="bi bi-trash me-2"></i>Delete Signature</button>
                                <?php elseif ($signatureData): ?>
                                    <img src="data:<?php echo $signatureMime; ?>;base64,<?php echo $signatureData; ?>" class="image-preview" alt="Current Signature">
                                    <p class="mt-2 text-muted">Current Signature (legacy)</p>
                                    <button class="btn btn-danger delete-signature" data-type="signature"><i class="bi bi-trash me-2"></i>Delete Signature</button>
                                <?php else: ?>
                                    <div class="image-preview d-flex align-items-center justify-content-center" style="height:150px;"><span class="text-muted">No signature uploaded</span></div>
                                <?php endif; ?>
                            </div>
                            <form id="signatureUploadForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="signatureFile" class="form-label">Upload New Signature (PNG, JPG, JPEG, GIF, WEBP)</label>
                                    <input class="form-control" type="file" id="signatureFile" name="image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" required>
                                </div>
                                <input type="hidden" name="type" value="signature">
                                <button type="submit" class="btn btn-primary w-100">Upload Signature</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="seal">
                            <div class="text-center">
                                <?php if ($sealPath): ?>
                                    <img src="<?php echo $sealPath; ?>" class="image-preview" alt="Current Seal">
                                    <p class="mt-2 text-muted">Current Seal</p>
                                    <button class="btn btn-danger delete-seal" data-type="seal"><i class="bi bi-trash me-2"></i>Delete Seal</button>
                                <?php elseif ($sealData): ?>
                                    <img src="data:<?php echo $sealMime; ?>;base64,<?php echo $sealData; ?>" class="image-preview" alt="Current Seal">
                                    <p class="mt-2 text-muted">Current Seal (legacy)</p>
                                    <button class="btn btn-danger delete-seal" data-type="seal"><i class="bi bi-trash me-2"></i>Delete Seal</button>
                                <?php else: ?>
                                    <div class="image-preview d-flex align-items-center justify-content-center" style="height:150px;"><span class="text-muted">No seal uploaded</span></div>
                                <?php endif; ?>
                            </div>
                            <form id="sealUploadForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="sealFile" class="form-label">Upload New Seal (PNG, JPG, JPEG, GIF, WEBP)</label>
                                    <input class="form-control" type="file" id="sealFile" name="image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" required>
                                </div>
                                <input type="hidden" name="type" value="seal">
                                <button type="submit" class="btn btn-primary w-100">Upload Seal</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="loader/waitMe.js"></script>
    <script src="js/forms.js"></script>
    <script src="js/sweetalert2@11.js"></script>

    <script>
    $(document).ready(function(){
        // Gender pie chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [<?php echo $male_students; ?>, <?php echo $female_students; ?>, <?php echo $other_gender; ?>],
                    backgroundColor: ['#3b7cff', '#ff6b9d', '#6c757d']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Class bar chart
        const classLabels = <?php echo json_encode(array_column($class_enrollment, 'name')); ?>;
        const classCounts = <?php echo json_encode(array_column($class_enrollment, 'count')); ?>;
        const classCtx = document.getElementById('classChart').getContext('2d');
        new Chart(classCtx, {
            type: 'bar',
            data: {
                labels: classLabels,
                datasets: [{
                    label: 'Students',
                    data: classCounts,
                    backgroundColor: 'rgba(59,124,255,0.6)',
                    borderColor: 'rgba(59,124,255,1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: { legend: { display: false } }
            }
        });

        // Save contact information
        $('#saveContactBtn').on('click', function() {
            const formData = {
                location: $('#location').val(),
                phone1: $('#phone1').val(),
                phone2: $('#phone2').val(),
                email: $('#email').val()
            };
            if (!formData.location && !formData.phone1 && !formData.phone2 && !formData.email) {
                Swal.fire('Info', 'At least one field should be filled.', 'info');
                return;
            }
            $.ajax({
                url: 'admin/save_contact.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function() {
                    $('#saveContactBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Saved!', text: response.message, timer: 2000, showConfirmButton: false });
                        $('#contactModal').modal('hide');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', 'Server error: ' + xhr.responseText, 'error');
                },
                complete: function() {
                    $('#saveContactBtn').prop('disabled', false).html('Save Changes');
                }
            });
        });

        // Signature upload
        $('#signatureUploadForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: 'admin/save_image.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#signatureUploadForm button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Uploading...');
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Upload failed', 'error');
                },
                complete: function() {
                    $('#signatureUploadForm button[type="submit"]').prop('disabled', false).html('Upload Signature');
                }
            });
        });

        // Seal upload
        $('#sealUploadForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url: 'admin/save_image.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#sealUploadForm button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Uploading...');
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Upload failed', 'error');
                },
                complete: function() {
                    $('#sealUploadForm button[type="submit"]').prop('disabled', false).html('Upload Seal');
                }
            });
        });

        // Delete handlers
        $('.delete-signature, .delete-seal').on('click', function() {
            var btn = $(this);
            var type = btn.data('type');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'admin/delete_image.php',
                        type: 'POST',
                        data: { type: type },
                        dataType: 'json',
                        beforeSend: function() {
                            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Deleting...');
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Delete failed', 'error');
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('<i class="bi bi-trash me-2"></i>Delete');
                        }
                    });
                }
            });
        });
    });
    </script>
</body>
</html>