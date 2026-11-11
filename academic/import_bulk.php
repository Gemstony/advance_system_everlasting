<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Academic only (level 1)
if (!($res == "1" && $level == "1")) {
    header("location:../");
    exit;
}

// Helper to parse class list (may still be needed for subject loading, but we'll use AJAX)
function parse_class_list($raw) {
    if (is_array($raw)) return $raw;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) return array_map('intval', $un);
    $json = @json_decode($raw, true);
    if (is_array($json)) return array_map('intval', $json);
    $raw_trim = trim((string)$raw);
    if ($raw_trim === '') return [];
    if (ctype_digit($raw_trim)) return [(int)$raw_trim];
    $parts = preg_split('/\s*,\s*/', $raw_trim);
    $ints = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (ctype_digit($p)) $ints[] = (int)$p;
    }
    return $ints;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}

$classes = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name ASC")->fetchAll();
$terms   = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id ASC")->fetchAll();
$grading = $pdo->query("SELECT id, name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();

$title = 'Bulk Import Results';

// Prepare staff array for header (consistent with grading system)
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - <?php echo htmlspecialchars($title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== ENHANCED REALISTIC STYLING – KEEP FUNCTIONALITY INTACT ===== */
        * {
            box-sizing: border-box;
        }

        body {
            background: #f4f6fb;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .app-content {
            background: transparent !important;
            padding: 1.5rem 2rem 3rem;
            position: relative;
        }

        /* --- Subtle background texture --- */
        .app-content::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 40%, rgba(67,97,238,0.04) 0%, transparent 70%),
                        radial-gradient(ellipse at 70% 60%, rgba(239,71,111,0.03) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .app-title {
            position: relative;
            z-index: 1;
            margin-bottom: 2.5rem;
        }

        /* --- Title now black, clean, with subtle background --- */
        .app-title h1 {
            font-weight: 800;
            font-size: 2.2rem;
            color: #1e293b;
            letter-spacing: -0.5px;
            display: inline-block;
            padding: 0.3rem 2rem 0.3rem 1.5rem;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
            border-radius: 60px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }

        .app-title h1 i {
            color: #4361ee;
            filter: drop-shadow(0 2px 4px rgba(67,97,238,0.2));
        }

        /* --- Glass shine effect for all buttons --- */
        .btn-shine {
            position: relative;
            overflow: hidden;
        }

        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.25) 50%, rgba(255,255,255,0) 100%);
            transform: translateX(-100%) rotate(25deg);
            transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            pointer-events: none;
        }

        .btn-shine:hover::after {
            transform: translateX(100%) rotate(25deg);
        }

        /* --- Glass Tile (Main Card) --- */
        .tile {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 32px;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15), 0 8px 24px -6px rgba(0,0,0,0.05), inset 0 1px 2px rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.5);
            padding: 2rem 2.2rem;
            position: relative;
            z-index: 1;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            margin-bottom: 2rem;
        }

        .tile:hover {
            box-shadow: 0 28px 56px -16px rgba(0,0,0,0.2), 0 12px 32px -8px rgba(0,0,0,0.08), inset 0 1px 2px rgba(255,255,255,0.9);
            transform: translateY(-4px);
        }

        .tile::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 32px;
            background: linear-gradient(145deg, rgba(255,255,255,0.6) 0%, rgba(255,255,255,0) 80%);
            pointer-events: none;
        }

        .tile-body {
            position: relative;
            z-index: 2;
        }

        /* --- Step indicator --- */
        .step-indicator {
            display: flex;
            margin-bottom: 2.5rem;
            gap: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .step {
            flex: 1;
            text-align: center;
            padding: 0.9rem;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(4px);
            border-radius: 50px;
            font-weight: 600;
            color: #495057;
            border: 1px solid rgba(255,255,255,0.4);
            transition: all 0.2s ease;
        }

        .step.active {
            background: linear-gradient(145deg, #4f6ef7, #3b52d4);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 10px 20px -8px rgba(67,97,238,0.4);
        }

        .step.completed {
            background: linear-gradient(145deg, #06d6a0, #04b386);
            color: white;
            border-color: #06d6a0;
        }

        /* --- Modal Cards (glassy) --- */
        .modal-card {
            border: none;
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(255,255,255,0.6);
            transition: all 0.2s ease;
        }

        .modal-card:hover {
            background: rgba(255,255,255,0.8);
            box-shadow: 0 12px 28px -8px rgba(0,0,0,0.08);
        }

        .modal-card .card-header {
            background: rgba(248, 249, 250, 0.4);
            backdrop-filter: blur(4px);
            font-weight: 700;
            color: #1e293b;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            border-radius: 20px 20px 0 0;
            padding: 1rem 1.5rem;
        }

        .modal-card .card-body {
            padding: 1.5rem;
        }

        /* --- Checkboxes (class & subject) --- */
        .class-checkbox, .subject-checkbox {
            margin-bottom: 0.75rem;
        }

        .form-check-input {
            border-radius: 6px;
            border: 2px solid rgba(0,0,0,0.15);
            transition: all 0.15s;
        }

        .form-check-input:checked {
            background-color: #4361ee;
            border-color: #4361ee;
        }

        .form-check-label {
            font-weight: 500;
            color: #1e293b;
        }

        /* --- Buttons --- */
        .btn {
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            padding: 0.6rem 1.8rem;
            position: relative;
            overflow: hidden;
        }

        .btn-lg {
            padding: 0.75rem 2.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(145deg, #4f6ef7, #3b52d4);
            color: white;
            box-shadow: 0 4px 12px -4px rgba(67,97,238,0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(145deg, #5a78ff, #3f56d8);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(67,97,238,0.4);
        }

        .btn-success {
            background: linear-gradient(145deg, #06d6a0, #04b386);
            color: white;
            box-shadow: 0 4px 12px -4px rgba(6,214,160,0.3);
        }

        .btn-success:hover {
            background: linear-gradient(145deg, #0ae0a8, #04b386);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(6,214,160,0.4);
        }

        .btn-info {
            background: linear-gradient(145deg, #4cc9f0, #36a8d0);
            color: white;
            box-shadow: 0 4px 12px -4px rgba(76,201,240,0.3);
        }

        .btn-info:hover {
            background: linear-gradient(145deg, #5dd6f5, #3aafd8);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(76,201,240,0.4);
        }

        .btn-secondary {
            background: rgba(0,0,0,0.06);
            color: #1e293b;
            border: 1px solid rgba(0,0,0,0.08);
        }

        .btn-secondary:hover {
            background: rgba(0,0,0,0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px -4px rgba(0,0,0,0.08);
        }

        .btn-outline-secondary {
            background: transparent;
            color: #1e293b;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .btn-outline-secondary:hover {
            background: rgba(0,0,0,0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px -4px rgba(0,0,0,0.06);
        }

        /* --- Form controls --- */
        .form-select, .form-control {
            background: white;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 0.7rem 1.2rem;
            color: #1e293b;
            transition: border 0.2s, box-shadow 0.2s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-select:focus, .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.12);
            outline: none;
        }

        /* --- Info badge --- */
        .info-badge {
            background: rgba(67, 97, 238, 0.1);
            border: 1px solid rgba(67, 97, 238, 0.2);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 0.5rem 1.2rem;
            color: #4361ee;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* --- Help card --- */
        .help-card {
            background: rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            border: 1px solid rgba(255,255,255,0.6);
            margin-top: 1rem;
        }

        /* --- Preview table --- */
        #previewTable {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            font-size: 0.85rem;
        }

        #previewTable th {
            background: #eef2ff;
            color: #1e293b;
            font-weight: 700;
            padding: 0.75rem 0.5rem;
            border-bottom: 2px solid #4361ee;
        }

        #previewTable td {
            padding: 0.6rem 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }

        #previewTable tbody tr:hover {
            background: rgba(67,97,238,0.03);
        }

        /* --- SweetAlert2 custom glass styling --- */
        .swal2-popup-custom {
            backdrop-filter: blur(16px) !important;
            background: rgba(255,255,255,0.92) !important;
            border-radius: 32px !important;
            border: 1px solid rgba(255,255,255,0.6) !important;
            box-shadow: 0 30px 60px -16px rgba(0,0,0,0.25) !important;
            font-family: 'Inter', system-ui, sans-serif !important;
        }
        .swal2-popup-custom .swal2-title {
            color: #1e293b !important;
            font-weight: 700 !important;
        }
        .swal2-popup-custom .swal2-html-container {
            color: #475569 !important;
        }
        .swal2-popup-custom .swal2-confirm {
            background: linear-gradient(145deg, #4f6ef7, #3b52d4) !important;
            border-radius: 40px !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px -4px rgba(67,97,238,0.4) !important;
            border: none !important;
            transition: all 0.2s ease !important;
        }
        .swal2-popup-custom .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px -6px rgba(67,97,238,0.5) !important;
        }
        .swal2-popup-custom .swal2-cancel {
            background: #f1f3f6 !important;
            color: #1e293b !important;
            border-radius: 40px !important;
            font-weight: 600 !important;
            transition: background 0.2s !important;
            border: none !important;
        }
        .swal2-popup-custom .swal2-cancel:hover {
            background: #e2e6ed !important;
        }

        /* --- Responsive --- */
        @media (max-width: 768px) {
            .app-title h1 {
                font-size: 1.6rem;
                padding: 0.2rem 1.2rem;
            }
            .tile {
                padding: 1.2rem;
            }
            .step-indicator {
                flex-direction: column;
                gap: 0.25rem;
            }
            .step {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

    <!-- ===== EXTERNAL HEADER (same as grading system) ===== -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- ===== EXTERNAL SIDEBAR ===== -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><?php echo htmlspecialchars($title); ?></h1></div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="tile">
                    <div class="step-indicator">
                        <div class="step active" id="step1">1. Select Classes &amp; Term</div>
                        <div class="step" id="step2">2. Choose Subjects</div>
                        <div class="step" id="step3">3. Download &amp; Upload</div>
                    </div>

                    <!-- Step 1: Classes and Term -->
                    <div id="step1-content">
                        <div class="card modal-card">
                            <div class="card-header">
                                <i class="bi bi-calendar3 me-2"></i> Term
                            </div>
                            <div class="card-body">
                                <select id="modalTermSelect" class="form-select">
                                    <option value="">-- Select Term --</option>
                                    <?php foreach ($terms as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="card modal-card">
                            <div class="card-header">
                                <i class="bi bi-building me-2"></i> Classes (select one or more)
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($classes as $c): ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="form-check class-checkbox">
                                                <input class="form-check-input class-check" type="checkbox" value="<?= $c['id'] ?>" id="class<?= $c['id'] ?>">
                                                <label class="form-check-label" for="class<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="button" id="selectAllClasses" class="btn btn-sm btn-outline-secondary btn-shine">Select All</button>
                                    <button type="button" id="deselectAllClasses" class="btn btn-sm btn-outline-secondary btn-shine">Deselect All</button>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" id="step1Next" class="btn btn-primary btn-lg btn-shine">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Subjects -->
                    <div id="step2-content" style="display: none;">
                        <div class="card modal-card">
                            <div class="card-header">
                                <i class="bi bi-book me-2"></i> Subjects (union across selected classes)
                            </div>
                            <div class="card-body">
                                <div id="subjectLoading" class="text-center py-4" style="display: none;">
                                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                                    <p class="mt-3">Loading subjects...</p>
                                </div>
                                <div id="subjectCheckboxContainer" class="row">
                                    <!-- populated by JS -->
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="button" id="selectAllSubjects" class="btn btn-sm btn-outline-secondary btn-shine">Select All</button>
                                    <button type="button" id="deselectAllSubjects" class="btn btn-sm btn-outline-secondary btn-shine">Deselect All</button>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" id="step2Prev" class="btn btn-secondary btn-lg btn-shine"><i class="bi bi-arrow-left"></i> Back</button>
                            <button type="button" id="step2Next" class="btn btn-primary btn-lg btn-shine">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Download & Upload -->
                    <div id="step3-content" style="display: none;">
                        <div class="card modal-card">
                            <div class="card-header">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Download Template
                            </div>
                            <div class="card-body">
                                <button type="button" id="downloadTemplateBtn" class="btn btn-success btn-lg btn-shine" disabled>
                                    <i class="bi bi-file-earmark-spreadsheet"></i> Download CSV Template
                                </button>
                                <small class="text-muted d-block mt-2">The template contains students from selected classes and empty score columns for chosen subjects.</small>
                            </div>
                        </div>

                        <div class="card modal-card">
                            <div class="card-header">
                                <i class="bi bi-cloud-upload me-2"></i> Upload Completed CSV
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-3 align-items-start flex-wrap">
                                    <input type="file" id="importFile" accept=".csv, .txt" class="form-control" style="max-width: 400px;">
                                    <button type="button" id="previewBtn" class="btn btn-info btn-lg btn-shine" disabled><i class="bi bi-eye"></i> Preview</button>
                                    <button type="button" id="clearFileBtn" class="btn btn-outline-secondary btn-lg btn-shine"><i class="bi bi-x"></i> Clear</button>
                                </div>

                                <!-- File info and preview -->
                                <div id="previewContainer" style="display: none; margin-top: 2rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="fw-semibold mb-0">Preview (first 10 rows)</h5>
                                        <span class="info-badge" id="fileStats"></span>
                                    </div>
                                    <div class="table-responsive" style="max-height: 350px; border-radius: 16px;">
                                        <table class="table table-sm table-bordered" id="previewTable">
                                            <thead></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <div class="mt-4 d-flex justify-content-end">
                                        <button type="button" id="confirmImportBtn" class="btn btn-primary btn-lg px-5 btn-shine">
                                            <i class="bi bi-check-circle"></i> Confirm Import
                                        </button>
                                    </div>
                                </div>

                                <!-- Help section -->
                                <div class="help-card mt-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-question-circle-fill fs-3 text-primary"></i>
                                        <div>
                                            <strong>How to prepare your CSV file</strong>
                                            <p class="mb-0 text-muted small">Save your Excel file as <kbd>CSV (Comma delimited)</kbd> (*.csv). Ensure the first row contains the exact headers as in the downloaded template. Each row must have the same number of columns.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="button" id="step3Prev" class="btn btn-secondary btn-lg btn-shine"><i class="bi bi-arrow-left"></i> Back</button>
                        </div>
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
    <script src="select2/dist/js/select2.full.min.js"></script>
    <script>
    $(document).ready(function(){
        // ========== Import Wizard ==========
        // Step navigation
        $('#step1Next').on('click', function() {
            var term = $('#modalTermSelect').val();
            var classesSelected = $('.class-check:checked').length > 0;
            if (!term) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select a term.',
                    customClass: { popup: 'swal2-popup-custom' }
                });
                return;
            }
            if (!classesSelected) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select at least one class.',
                    customClass: { popup: 'swal2-popup-custom' }
                });
                return;
            }
            var classIds = [];
            $('.class-check:checked').each(function() { classIds.push($(this).val()); });
            loadSubjects(classIds);
            $('#step1').removeClass('active').addClass('completed');
            $('#step2').addClass('active');
            $('#step1-content').hide();
            $('#step2-content').show();
        });

        $('#step2Prev').on('click', function() {
            $('#step2').removeClass('active');
            $('#step1').addClass('active').removeClass('completed');
            $('#step2-content').hide();
            $('#step1-content').show();
        });

        $('#step2Next').on('click', function() {
            var subjectsSelected = $('.subject-check:checked').length > 0;
            if (!subjectsSelected) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select at least one subject.',
                    customClass: { popup: 'swal2-popup-custom' }
                });
                return;
            }
            $('#step2').removeClass('active').addClass('completed');
            $('#step3').addClass('active');
            $('#step2-content').hide();
            $('#step3-content').show();
            $('#downloadTemplateBtn').prop('disabled', false);
        });

        $('#step3Prev').on('click', function() {
            $('#step3').removeClass('active');
            $('#step2').addClass('active').removeClass('completed');
            $('#step3-content').hide();
            $('#step2-content').show();
        });

        // Class select/deselect all
        $('#selectAllClasses').on('click', function() {
            $('.class-check').prop('checked', true);
        });
        $('#deselectAllClasses').on('click', function() {
            $('.class-check').prop('checked', false);
        });

        // Load subjects via AJAX
        function loadSubjects(classIds) {
            $('#subjectLoading').show();
            $('#subjectCheckboxContainer').empty();
            $.ajax({
                url: 'academic/core/get_subjects_for_classes.php',
                data: { classes: classIds.join(',') },
                dataType: 'json',
                success: function(res) {
                    $('#subjectLoading').hide();
                    if (res.success && res.subjects.length > 0) {
                        var html = '';
                        res.subjects.forEach(function(subj) {
                            html += '<div class="col-md-4 col-lg-3">';
                            html += '<div class="form-check subject-checkbox">';
                            html += '<input class="form-check-input subject-check" type="checkbox" value="' + subj.id + '" id="subj' + subj.id + '">';
                            html += '<label class="form-check-label" for="subj' + subj.id + '">' + subj.name + '</label>';
                            html += '</div></div>';
                        });
                        $('#subjectCheckboxContainer').html(html);
                    } else {
                        $('#subjectCheckboxContainer').html('<p class="text-muted">No subjects found for the selected classes.</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#subjectLoading').hide();
                    console.error('AJAX error:', status, error);
                    $('#subjectCheckboxContainer').html('<p class="text-danger">Error loading subjects. Check console.</p>');
                }
            });
        }

        // Subject select/deselect all
        $('#selectAllSubjects').on('click', function() {
            $('.subject-check').prop('checked', true);
        });
        $('#deselectAllSubjects').on('click', function() {
            $('.subject-check').prop('checked', false);
        });

        // Download template
        $('#downloadTemplateBtn').on('click', function() {
            var classIds = [];
            $('.class-check:checked').each(function() { classIds.push($(this).val()); });
            var subjectIds = [];
            $('.subject-check:checked').each(function() { subjectIds.push($(this).val()); });
            var termId = $('#modalTermSelect').val();
            var url = 'academic/core/download_template.php?term=' + termId + '&classes=' + classIds.join(',') + '&subjects=' + subjectIds.join(',');
            window.location.href = url;
        });

        // File input
        $('#importFile').on('change', function() {
            $('#previewBtn').prop('disabled', !this.files.length);
            // Reset preview when new file selected
            $('#previewContainer').hide();
        });

        $('#clearFileBtn').on('click', function() {
            $('#importFile').val('');
            $('#previewBtn').prop('disabled', true);
            $('#previewContainer').hide();
        });

        // Robust CSV parser (handles quotes and commas inside fields)
        function parseCSVLine(line) {
            const result = [];
            let start = 0;
            let inQuotes = false;
            for (let i = 0; i < line.length; i++) {
                if (line[i] === '"') {
                    inQuotes = !inQuotes;
                } else if (line[i] === ',' && !inQuotes) {
                    result.push(line.substring(start, i).replace(/^"|"$/g, '').replace(/""/g, '"'));
                    start = i + 1;
                }
            }
            result.push(line.substring(start).replace(/^"|"$/g, '').replace(/""/g, '"'));
            return result;
        }

        // Preview CSV
        $('#previewBtn').on('click', function() {
            var file = $('#importFile')[0].files[0];
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function(e) {
                var content = e.target.result;
                var lines = content.split(/\r\n|\n/).filter(line => line.trim() !== '');
                if (lines.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'File is empty.',
                        customClass: { popup: 'swal2-popup-custom' }
                    });
                    return;
                }

                // Parse headers and data using robust parser
                var headers = parseCSVLine(lines[0]);
                var data = [];
                for (var i = 1; i < Math.min(lines.length, 11); i++) {
                    data.push(parseCSVLine(lines[i]));
                }

                // Update file stats
                $('#fileStats').text(lines.length + ' rows, ' + headers.length + ' columns');

                // Build preview table
                var thead = $('#previewTable thead');
                var tbody = $('#previewTable tbody');
                thead.empty();
                tbody.empty();

                var headerRow = '<tr>';
                headers.forEach(h => { headerRow += '<th>' + h + '</th>'; });
                headerRow += '</tr>';
                thead.append(headerRow);

                data.forEach(row => {
                    var rowHtml = '<tr>';
                    for (var j = 0; j < headers.length; j++) {
                        var cell = (j < row.length) ? row[j] : '';
                        rowHtml += '<td>' + cell + '</td>';
                    }
                    rowHtml += '</tr>';
                    tbody.append(rowHtml);
                });

                $('#previewContainer').show();
            };
            reader.readAsText(file);
        });

        // Confirm import
        $('#confirmImportBtn').on('click', function() {
            var file = $('#importFile')[0].files[0];
            if (!file) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please select a file.',
                    customClass: { popup: 'swal2-popup-custom' }
                });
                return;
            }
            var termId = $('#modalTermSelect').val();
            if (!termId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Term is required.',
                    customClass: { popup: 'swal2-popup-custom' }
                });
                return;
            }

            var formData = new FormData();
            formData.append('term', termId);
            formData.append('file', file);

            $.ajax({
                url: 'academic/core/import_results.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#confirmImportBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Importing...');
                },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 3000,
                            showConfirmButton: false,
                            customClass: { popup: 'swal2-popup-custom' }
                        }).then(() => {
                            window.location.href = 'academic/report';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Import Failed',
                            text: res.message,
                            customClass: { popup: 'swal2-popup-custom' }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Import AJAX error:', status, error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Server error. Check logs.',
                        customClass: { popup: 'swal2-popup-custom' }
                    });
                },
                complete: function() {
                    $('#confirmImportBtn').prop('disabled', false).html('<i class="bi bi-check-circle"></i> Confirm Import');
                }
            });
        });
    });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>