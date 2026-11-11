<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// Staff data for header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

// Fetch classes and active terms for dropdowns
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $classes = [];
    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $terms = [];
    $stmt = $conn->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY name");
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $classes = [];
    $terms = [];
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
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .app-content { background: transparent !important; }
        body { font-family: 'Inter', sans-serif; background: #f4f6fa; }

        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;
            text-shadow: none !important;
        }

        .tile {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
            transition: box-shadow 0.3s ease, transform 0.2s ease;
        }
        .tile:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        }
        .tile-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .form-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .form-control, .select2-container--default .select2-selection--single {
            background: #ffffff;
            border: 1px solid #dce1e8;
            border-radius: 16px;
            padding: 0.6rem 1rem;
            height: auto;
            color: #1e293b;
            font-weight: 500;
        }
        .form-control:focus, .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.1);
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            color: #1e293b;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 10px;
        }

        .btn-primary {
            background: #4361ee;
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.8rem;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(67,97,238,0.25);
            width: 100%;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50px;
            background: rgba(255,255,255,0.15);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(67,97,238,0.35);
            background: #3a56d4;
        }
        .btn-primary:hover::after {
            opacity: 1;
        }
        .btn-primary:active {
            transform: translateY(1px);
        }

        .center_form {
            margin: 0 auto;
            float: none;
        }

        @media (max-width: 576px) {
            .tile { padding: 1.2rem; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-file-earmark-text me-2"></i> Report Tool</h1></div>
        </div>

        <div class="row">
            <div class="col-md-5 col-lg-4 center_form">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-printer me-2"></i> Generate Report</h3>
                        <form enctype="multipart/form-data" action="academic/core/start_report" class="app_frm" method="POST" autocomplete="OFF">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-building me-1"></i> Select Class</label>
                                <select class="form-control select2" name="student" required style="width: 100%;">
                                    <option value="" selected disabled>Select One</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label"><i class="bi bi-calendar-week me-1"></i> Select Term</label>
                                <select class="form-control select2" name="term" required style="width: 100%;">
                                    <option value="" selected disabled>Select One</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars($term['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted">Only active terms are shown.</div>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-file-pdf me-2"></i> Generate Report
                                </button>
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
            $('.select2').select2({
                placeholder: "Select an option",
                allowClear: false
            });
        });
    </script>
</body>
</html>