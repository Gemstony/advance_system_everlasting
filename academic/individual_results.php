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

// Prepare staff array for header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // All classes for dropdowns (both cards)
    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Card 1: Students (all active) – exactly as original
    $stmt = $conn->query("SELECT id, fname, mname, lname FROM tbl_students WHERE status = 1 ORDER BY fname");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Card 1: Active terms
    $stmt = $conn->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY name");
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Individual Results</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <!-- Removed waitMe.css to avoid any preloader -->
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== GLOBAL ===== */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
        }
        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;
            text-shadow: none !important;
        }

        /* ===== GLASS TILES ===== */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(67,97,238,0.3);
        }

        /* ===== FORM CONTROLS ===== */
        .form-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-label i {
            color: #4361ee;
        }
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.25s ease;
            min-height: 48px;
        }
        .select2-container--default .select2-selection--single:hover,
        .select2-container--default .select2-selection--multiple:hover {
            border-color: #4361ee;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            color: #1e293b;
            padding-left: 1rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 11px;
            right: 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding: 6px 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 30px;
            padding: 0.2rem 0.8rem;
            font-size: 0.85rem;
            color: #1e293b;
        }
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        .form-control {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.8rem 1rem;
            background: white;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.25s ease;
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
            outline: none;
        }

        /* ===== GLOSSY BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.8rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.25s ease;
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
            width: 100%;
            margin-top: 0.5rem;
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(67,97,238,0.5);
            background: linear-gradient(135deg, #3a56d4, #1e40af);
        }
        .btn-primary:active {
            transform: translateY(1px);
        }
        .btn-outline-primary {
            background: transparent;
            border: 2px solid #4361ee;
            border-radius: 50px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            font-size: 1rem;
            color: #4361ee;
            transition: all 0.25s ease;
            width: 100%;
            margin-top: 0.5rem;
        }
        .btn-outline-primary:hover {
            background: #4361ee;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.3);
        }
        .btn-outline-secondary {
            background: transparent;
            border: 2px solid #6c757d;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #6c757d;
            transition: all 0.25s ease;
        }
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .tile { padding: 1.5rem; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-person-badge me-2"></i> Individual Results</h1></div>
        </div>

        <!-- Two Cards Row -->
        <div class="row g-4">
            <!-- Card 1: View Results (original logic – unchanged) -->
            <div class="col-md-6">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-eye me-2"></i> View Results</h3>
                        <form action="academic/core/start_edit" class="app_frm" method="POST" autocomplete="OFF">
                            <!-- Student dropdown (all active students) -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-people"></i> Student</label>
                                <select class="form-control select2" name="student" required style="width: 100%;">
                                    <option value="" selected disabled>— Select student —</option>
                                    <?php foreach ($students as $row): 
                                        $fullName = trim($row['fname'] . ' ' . $row['mname'] . ' ' . $row['lname']);
                                    ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo htmlspecialchars($fullName) . ' (' . $row['id'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Term dropdown -->
                            <div class="mb-4">
                                <label class="form-label"><i class="bi bi-calendar-week"></i> Term</label>
                                <select class="form-control select2" name="term" required style="width: 100%;">
                                    <option value="" selected disabled>— Select term —</option>
                                    <?php foreach ($terms as $row): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-eye me-2"></i> View Results</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card 2: Find Repeat Students – submits to repeat_std.php -->
            <div class="col-md-6">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-people-fill me-2"></i> Find Repeat Names</h3>
                        <form action="academic/repeat_std" method="GET" autocomplete="off" id="searchForm">
                            <!-- Multi‑select classes -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-building"></i> Select Classes</label>
                                <select class="form-control select2-multi" name="search_classes[]" multiple="multiple" style="width: 100%;" id="classMultiSelect" required>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select one or more classes to check for duplicate names.</small>
                            </div>

                            <!-- Name filter (optional) -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-filter"></i> Name Filter (optional)</label>
                                <input type="text" class="form-control" name="search_name" id="searchName"
                                       placeholder="Type partial name to filter..." 
                                       value="">
                                <small class="text-muted">Leave blank to see all students in selected classes.</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" type="submit" id="searchBtn"><i class="bi bi-search me-2"></i> Find Duplicates</button>
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
    <!-- Removed waitMe.js to avoid any preloader -->
    <script src="js/sweetalert2@11.js"></script>
    <script src="js/forms.js"></script>
    <script src="select2/dist/js/select2.full.min.js"></script>
    <script>
        $(document).ready(function(){
            // Initialize Select2 for single selects (Card 1)
            $('.select2').select2({
                placeholder: "Select an option",
                allowClear: false,
                width: '100%'
            });

            // Initialize Select2 for multi-select (Card 2)
            $('.select2-multi').select2({
                placeholder: "Select one or more classes",
                allowClear: false,
                width: '100%'
            });
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>