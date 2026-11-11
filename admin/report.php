<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    header("location:../");
    exit;
}

// Fetch statistics for cards
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $totalClasses = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
    $activeTerms = $pdo->query("SELECT COUNT(*) FROM tbl_terms WHERE status = 1")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $totalSubjects = $pdo->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Report Tool</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --light-bg: #f8fafc;
            --border-radius: 16px;
            --card-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.02);
            --transition: all 0.2s ease;
        }
        body { background: #f5f7fc; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .app-content { background: transparent; }
        .tile { border-radius: var(--border-radius); box-shadow: var(--card-shadow); border: none; background: white; margin-bottom: 2rem; transition: var(--transition); }
        .tile:hover { box-shadow: 0 15px 30px -10px rgba(0,0,0,0.15); transform: translateY(-2px); }
        .stats-row { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card {
            flex: 1 1 200px;
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border: 1px solid #eef2f6;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border-color: var(--primary-light);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.8rem;
        }
        .stat-content h4 {
            margin: 0 0 0.2rem 0;
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .stat-content .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        .form-label { font-weight: 600; color: #495057; }
        .form-control, .select2-container--default .select2-selection--single {
            border-radius: 12px;
            border: 1.5px solid #e9ecef;
            padding: 0.6rem 1rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 30px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(67,97,238,0.3);
        }
        .btn-primary:hover {
            background: #3651d4;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(67,97,238,0.4);
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1>Report Tool</h1></div>
    </div>

    <!-- Statistics cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-content">
                <h4>Total Classes</h4>
                <div class="value"><?php echo number_format($totalClasses); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-content">
                <h4>Active Terms</h4>
                <div class="value"><?php echo number_format($activeTerms); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <h4>Students</h4>
                <div class="value"><?php echo number_format($totalStudents); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-book"></i></div>
            <div class="stat-content">
                <h4>Subjects</h4>
                <div class="value"><?php echo number_format($totalSubjects); ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title">Generate Report</h3>
                    <form enctype="multipart/form-data" action="admin/core/start_report" class="app_frm" method="POST" autocomplete="OFF">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select class="form-control select2" name="student" required style="width: 100%;">
                                <option value="" selected disabled>-- Select a class --</option>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo "Connection failed: " . $e->getMessage();
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Term</label>
                            <select class="form-control select2" name="term" required style="width: 100%;">
                                <option value="" selected disabled>-- Select a term --</option>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY name");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo "Connection failed: " . $e->getMessage();
                                }
                                ?>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-primary" type="submit">Generate Report</button>
                        </div>
                    </form>
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
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
    $(document).ready(function() {
        $('.select2').select2();
    });
</script>
</body>
</html>