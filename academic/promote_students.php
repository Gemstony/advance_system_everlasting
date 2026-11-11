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

$authenticatedStaffId = (int)($_SESSION['staff_id'] ?? ($account_id ?? 0));

// Staff data for header
$staff = [
    'id'            => $authenticatedStaffId,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Helper: log promotion
function logPromotion($conn, $studentId, $fromClass, $toClass, $promotedBy, $academicYear = null) {
    if ($academicYear === null) {
        $academicYear = date('Y');
    }
    $stmt = $conn->prepare("INSERT INTO tbl_promotion_log (student_id, from_class, to_class, promoted_by, promotion_date, academic_year) VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$studentId, $fromClass, $toClass, $promotedBy, $academicYear]);
}

function classExists($conn, $classId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_classes WHERE id = ?");
    $stmt->execute([(int)$classId]);
    return (int)$stmt->fetchColumn() === 1;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId = $authenticatedStaffId;
    if ($staffId == 0) {
        $_SESSION['error'] = 'You must be logged in to perform this action.';
        header("Location: promote_students");
        exit;
    }

    // Promote entire class
    if (isset($_POST['promote_all'])) {
        $fromClass = (int)$_POST['class'];
        $toClass   = (int)$_POST['class2'];

        if ($fromClass == $toClass) {
            $_SESSION['error'] = 'Source and target classes cannot be the same.';
            header("Location: promote_students");
            exit;
        }

        try {
            $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();

            if (!classExists($conn, $fromClass) || !classExists($conn, $toClass)) {
                throw new RuntimeException('The selected source or target class does not exist.');
            }

            $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? AND status = 1");
            $stmt->execute([$fromClass]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($students)) {
                $_SESSION['error'] = 'No active students found in the source class.';
                $conn->rollBack();
                header("Location: promote_students");
                exit;
            }

            // Only the current enrollment changes. Existing exam results retain
            // their original class and remain available through the promotion log.
            $updateStmt = $conn->prepare("UPDATE tbl_students SET class = ? WHERE id = ? AND class = ?");
            $updated = 0;
            foreach ($students as $studentId) {
                $updateStmt->execute([$toClass, $studentId, $fromClass]);
                if ($updateStmt->rowCount() > 0) {
                    $updated++;
                    logPromotion($conn, $studentId, $fromClass, $toClass, $staffId);
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Successfully promoted $updated student(s). Previous results were preserved.";
        } catch (Throwable $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        header("Location: promote_students");
        exit;
    }

    // Transfer selected students
    if (isset($_POST['transfer_selected'])) {
        $sourceClass = (int)$_POST['source_class'];
        $targetClass = (int)$_POST['target_class'];
        $selectedStudents = $_POST['students'] ?? [];

        if ($sourceClass == $targetClass) {
            $_SESSION['error'] = 'Source and target classes cannot be the same.';
            header("Location: promote_students");
            exit;
        }
        if (empty($selectedStudents) || !is_array($selectedStudents)) {
            $_SESSION['error'] = 'Please select at least one student to transfer.';
            header("Location: promote_students");
            exit;
        }

        try {
            $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();

            if (!classExists($conn, $sourceClass) || !classExists($conn, $targetClass)) {
                throw new RuntimeException('The selected source or target class does not exist.');
            }

            $updateStmt = $conn->prepare("UPDATE tbl_students SET class = ? WHERE id = ? AND class = ? AND status = 1");
            $updated = 0;

            foreach ($selectedStudents as $studentId) {
                $studentId = preg_replace('/[^a-zA-Z0-9\-]/', '', $studentId);
                if (empty($studentId)) continue;

                $updateStmt->execute([$targetClass, $studentId, $sourceClass]);
                if ($updateStmt->rowCount() > 0) {
                    $updated++;
                    logPromotion($conn, $studentId, $sourceClass, $targetClass, $staffId);
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Successfully transferred $updated student(s). Previous results were preserved.";
        } catch (Throwable $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        header("Location: promote_students");
        exit;
    }
}

// Fetch promotion log
$logEntries = [];
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $conn->prepare("
        SELECT l.*, 
               s.fname AS student_fname, s.mname AS student_mname, s.lname AS student_lname,
               c1.name AS from_class_name, c2.name AS to_class_name,
               st.fname AS staff_fname, st.lname AS staff_lname
        FROM tbl_promotion_log l
        LEFT JOIN tbl_students s ON l.student_id = s.id
        LEFT JOIN tbl_classes c1 ON l.from_class = c1.id
        LEFT JOIN tbl_classes c2 ON l.to_class = c2.id
        LEFT JOIN tbl_staff st ON l.promoted_by = st.id
        ORDER BY l.promotion_date DESC
        LIMIT 100
    ");
    $stmt->execute();
    $logEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore
}

// For the selected students form
$selectedSourceClass = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$studentsInClass = [];
if ($selectedSourceClass > 0) {
    try {
        $stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? AND status = 1 ORDER BY lname, fname");
        $stmt->execute([$selectedSourceClass]);
        $studentsInClass = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Promote & Transfer Students</title>
    <meta charset="utf-8">
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
        /* ---------- GLOBAL – leave header theme untouched ---------- */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
        }

        /* ---------- Glass tile (no particles) ---------- */
        .tile {
            position: relative;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 1.8rem;
            overflow: hidden;
            height: 100%;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-body {
            position: relative;
            z-index: 2;
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
        }

        /* ---------- Title (now black) ---------- */
        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;   /* black text */
            text-shadow: none !important;
        }

        /* ---------- Form controls ---------- */
        .form-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        .form-select, .select2-container--default .select2-selection--multiple {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.6rem 1rem;
            background: white;
            height: auto;
            color: #1e293b;
        }
        .form-select:focus, .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            color: #1e293b;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 20px;
            padding: 0.2rem 0.8rem;
            font-size: 0.85rem;
        }

        /* ---------- Buttons ---------- */
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            border: none;
            border-radius: 50px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
            width: 100%;
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
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 50px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            box-shadow: 0 8px 16px -6px rgba(108,117,125,0.4);
            width: 100%;
            color: white;
        }
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(108,117,125,0.5);
            background: linear-gradient(135deg, #5a6268, #343a40);
        }

        /* ---------- Flash messages ---------- */
        .flash {
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(8px);
        }
        .flash-success {
            background: rgba(6,214,160,0.2);
            border: 1px solid rgba(6,214,160,0.3);
            color: #065f46;
        }
        .flash-error {
            background: rgba(239,71,111,0.2);
            border: 1px solid rgba(239,71,111,0.3);
            color: #991b1b;
        }

        /* ---------- Table styling ---------- */
        .table-container {
            margin-top: 2rem;
        }
        .table-container .tile {
            padding: 1.5rem;
        }
        .table th {
            font-weight: 700;
            color: #1e293b;
            border-bottom: 2px solid #dee2e6;
        }
        .table td {
            vertical-align: middle;
        }
        .badge {
            border-radius: 50px;
            padding: 0.4rem 0.9rem;
            font-weight: 500;
        }
        .badge-all {
            background: #e9ecef;
            color: #212529;
        }
        .badge-selected {
            background: #d1e7dd;
            color: #0f5132;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .col-md-6 {
                margin-bottom: 1.5rem;
            }
        }
        .student-count {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* ---------- Tech Loading Animation for Load Students button ---------- */
        .btn-loading {
            position: relative;
            color: transparent !important;
            pointer-events: none;
        }
        .btn-loading::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 1.2rem;
            height: 1.2rem;
            margin-left: -0.6rem;
            margin-top: -0.6rem;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite, pulse 1.2s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @keyframes pulse {
            0% { opacity: 0.6; transform: scale(0.95) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.1) rotate(180deg); }
            100% { opacity: 0.6; transform: scale(0.95) rotate(360deg); }
        }
        /* Additional glow effect for tech feel */
        .btn-loading {
            box-shadow: 0 0 15px rgba(67,97,238,0.6);
            transition: box-shadow 0.3s;
        }
        .btn-loading:hover {
            box-shadow: 0 0 25px rgba(67,97,238,0.8);
        }
    </style>
</head>
<body>

    <!-- Header & Sidebar -->
    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-arrow-left-right me-2"></i> Promote & Transfer Students</h1></div>
        </div>

        <?php if ($successMsg): ?>
            <div class="flash flash-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="flash flash-error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <!-- Two forms side by side -->
        <div class="row">
            <!-- Card 1: Promote Entire Class -->
            <div class="col-md-6">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-people-fill me-2"></i> Promote Entire Class</h3>
                        <form action="" method="POST" class="app_frm" autocomplete="OFF">
                            <input type="hidden" name="promote_all" value="1">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-building me-1"></i> From Class</label>
                                <select class="form-control select2" name="class" required style="width: 100%;">
                                    <option value="" selected disabled> Select Class with Students</option>
                                    <?php
                                    try {
                                        $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                        $stmt = $conn->prepare("SELECT * FROM tbl_classes");
                                        $stmt->execute();
                                        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($classes as $class) {
                                            $check = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ? AND status = 1");
                                            $check->execute([$class['id']]);
                                            $count = $check->fetchColumn();
                                            if ($count > 0) {
                                                echo '<option value="'.$class['id'].'">'.htmlspecialchars($class['name']).' ('.$count.' students)</option>';
                                            }
                                        }
                                    } catch(PDOException $e) {
                                        // ignore
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label"><i class="bi bi-arrow-right-circle me-1"></i> To Class</label>
                                <select class="form-control select2" name="class2" required style="width: 100%;">
                                    <option value="" selected disabled> Select Target Class</option>
                                    <?php
                                    try {
                                        $stmt = $conn->prepare("SELECT * FROM tbl_classes");
                                        $stmt->execute();
                                        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($classes as $class) {
                                            echo '<option value="'.$class['id'].'">'.htmlspecialchars($class['name']).'</option>';
                                        }
                                    } catch(PDOException $e) {
                                        // ignore
                                    }
                                    ?>
                                </select>
                                <div class="form-text text-muted">All active students from the source class will be moved.</div>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-primary" type="submit" name="promote_all">
                                    <i class="bi bi-arrow-up-circle me-2"></i> Promote All
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card 2: Transfer Selected Students -->
            <div class="col-md-6">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-person-check me-2"></i> Transfer Selected Students</h3>
                        <form action="" method="GET" id="classSelectForm" class="mb-3">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-building me-1"></i> Source Class</label>
                                <select class="form-control select2" name="class" id="sourceClass" style="width: 100%;" required>
                                    <option value="">-- Select a class with students --</option>
                                    <?php
                                    try {
                                        $stmt = $conn->prepare("SELECT * FROM tbl_classes");
                                        $stmt->execute();
                                        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($classes as $class) {
                                            $check = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ? AND status = 1");
                                            $check->execute([$class['id']]);
                                            $count = $check->fetchColumn();
                                            if ($count > 0) {
                                                $selected = ($selectedSourceClass == $class['id']) ? 'selected' : '';
                                                echo '<option value="'.$class['id'].'" '.$selected.'>'.htmlspecialchars($class['name']).' ('.$count.' students)</option>';
                                            }
                                        }
                                    } catch(PDOException $e) {
                                        // ignore
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-secondary" id="loadStudentsBtn">
                                    <i class="bi bi-search me-2"></i> Load Students
                                </button>
                            </div>
                        </form>

                        <?php if ($selectedSourceClass > 0): ?>
                            <form action="" method="POST" id="transferForm">
                                <input type="hidden" name="transfer_selected" value="1">
                                <input type="hidden" name="source_class" value="<?php echo $selectedSourceClass; ?>">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-person-check me-1"></i> Select Students to Transfer</label>
                                    <select class="form-control select2-multi" name="students[]" id="students" multiple="multiple" style="width: 100%;" required>
                                        <?php foreach ($studentsInClass as $student): ?>
                                            <option value="<?php echo htmlspecialchars($student['id']); ?>">
                                                <?php echo htmlspecialchars($student['lname'] . ' ' . $student['fname'] . ' ' . $student['mname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="student-count">
                                        <?php echo count($studentsInClass); ?> active student(s) in this class.
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label"><i class="bi bi-arrow-right-circle me-1"></i> Target Class</label>
                                    <select class="form-control select2" name="target_class" required style="width: 100%;">
                                        <option value="" selected disabled> Select Target Class</option>
                                        <?php
                                        try {
                                            $stmt = $conn->prepare("SELECT * FROM tbl_classes");
                                            $stmt->execute();
                                            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($classes as $class) {
                                                echo '<option value="'.$class['id'].'">'.htmlspecialchars($class['name']).'</option>';
                                            }
                                        } catch(PDOException $e) {
                                            // ignore
                                        }
                                        ?>
                                    </select>
                                    <div class="form-text text-muted">Selected students will be moved to this class.</div>
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-primary" type="submit" name="transfer_selected" id="transferBtn">
                                        <i class="bi bi-arrow-left-right me-2"></i> Transfer Selected
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotion Log -->
        <div class="table-container">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-clock-history me-2"></i> Promotion / Transfer Log</h3>
                    <?php if (empty($logEntries)): ?>
                        <p class="text-muted">No promotion or transfer records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>From Class</th>
                                        <th>To Class</th>
                                        <th>Promoted By</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logEntries as $index => $entry): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <?php 
                                                    echo htmlspecialchars(
                                                        ($entry['student_fname'] ?? '') . ' ' . 
                                                        ($entry['student_mname'] ?? '') . ' ' . 
                                                        ($entry['student_lname'] ?? '')
                                                    );
                                                    if (empty($entry['student_fname'])) {
                                                        echo '<span class="text-muted">(deleted student)</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($entry['from_class_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($entry['to_class_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars(($entry['staff_fname'] ?? '') . ' ' . ($entry['staff_lname'] ?? '')); ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($entry['promotion_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-all">Transfer</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
            // Initialize Select2 for single selects
            $('.select2').select2({
                placeholder: "Select an option",
                allowClear: false
            });

            // Initialize Select2 for multi-select
            $('.select2-multi').select2({
                placeholder: "Select students to transfer",
                allowClear: false,
                closeOnSelect: false
            });

            // Auto-submit when source class changes (for transfer form)
            $('#sourceClass').on('change', function() {
                if ($(this).val() !== '') {
                    $('#classSelectForm').submit();
                }
            });

            // ----- Tech loading animation for Load Students button -----
            $('#loadStudentsBtn').on('click', function(e) {
                // Only if a class is selected
                if ($('#sourceClass').val() !== '') {
                    $(this).addClass('btn-loading');
                    $(this).html('<i class="bi bi-search me-2"></i> Loading...');
                    // The form will submit, so the loading state will only show briefly.
                    // We keep the class so the spinner appears.
                }
            });

            // Confirmation for "Promote All"
            $('form:has(button[name="promote_all"])').on('submit', function(e) {
                e.preventDefault();
                var from = $(this).find('select[name="class"]').val();
                var to = $(this).find('select[name="class2"]').val();
                if (!from || !to) {
                    Swal.fire('Error', 'Please select both classes.', 'warning');
                    return;
                }
                if (from === to) {
                    Swal.fire('Error', 'Source and target classes cannot be the same.', 'warning');
                    return;
                }
                Swal.fire({
                    title: 'Confirm Promotion',
                    text: 'Are you sure you want to promote ALL students from the source class to the target class?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, promote all!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $(this).find('button[type="submit"]').html('<span class="spinner-border spinner-border-sm me-2"></span> Promoting...').prop('disabled', true);
                        this.submit();
                    }
                });
            });

            // Confirmation for "Transfer Selected"
            $('#transferForm').on('submit', function(e) {
                e.preventDefault();

                var selected = $('#students').val();
                if (!selected || selected.length === 0) {
                    Swal.fire('Error', 'Please select at least one student to transfer.', 'warning');
                    return;
                }

                var target = $('select[name="target_class"]').val();
                if (!target) {
                    Swal.fire('Error', 'Please select a target class.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Confirm Transfer',
                    text: 'Are you sure you want to transfer the selected students to the new class?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, transfer them!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#transferBtn').html('<span class="spinner-border spinner-border-sm me-2"></span> Transferring...').prop('disabled', true);
                        this.submit();
                    }
                });
            });
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>