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

// -------------------------------------------------------------------
// AJAX HANDLERS (unchanged logic)
// -------------------------------------------------------------------
if (isset($_GET['ajax'])) {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch combination for edit modal
    if ($_GET['ajax'] === 'get_combination') {
        header('Content-Type: application/json');
        try {
            $combId = (int)($_GET['id'] ?? 0);
            if (!$combId) throw new Exception('Invalid combination ID');

            $stmt = $pdo->prepare("SELECT sc.*, s.name AS subject_name, st.fname, st.lname
                                    FROM tbl_subject_combinations sc
                                    LEFT JOIN tbl_subjects s ON sc.subject = s.id
                                    LEFT JOIN tbl_staff st ON sc.teacher = st.id
                                    WHERE sc.id = ?");
            $stmt->execute([$combId]);
            $comb = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$comb) throw new Exception('Combination not found');

            $classList = @unserialize($comb['class']);
            if ($classList === false) $classList = [];

            $stmt = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name");
            $allClasses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmt = $pdo->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 ORDER BY fname");
            $stmt->execute();
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("SELECT id, name FROM tbl_subjects ORDER BY name");
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'comb' => $comb,
                'classList' => $classList,
                'allClasses' => $allClasses,
                'teachers' => $teachers,
                'subjects' => $subjects
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Toggle status (active/inactive)
    if ($_GET['ajax'] === 'toggle_status') {
        header('Content-Type: application/json');
        try {
            $id = (int)($_POST['id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0);
            if (!$id) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("UPDATE tbl_subject_combinations SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Copy combinations from one class to another
    if ($_GET['ajax'] === 'copy_combinations') {
        header('Content-Type: application/json');
        try {
            $sourceClass = (int)($_POST['source_class'] ?? 0);
            $targetClasses = isset($_POST['target_classes']) ? (array)$_POST['target_classes'] : [];
            if (!$sourceClass || empty($targetClasses)) throw new Exception('Invalid source or target classes');

            $stmt = $pdo->prepare("SELECT * FROM tbl_subject_combinations");
            $stmt->execute();
            $allCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $copied = 0;
            foreach ($allCombos as $combo) {
                $classList = @unserialize($combo['class']);
                if ($classList === false || !in_array($sourceClass, $classList)) continue;

                foreach ($targetClasses as $target) {
                    if ($target == $sourceClass) continue;
                    if (in_array($target, $classList)) continue;

                    $newClassList = $classList;
                    $newClassList[] = $target;
                    $serialized = serialize($newClassList);

                    // Check if combination already exists for target class
                    $exists = false;
                    foreach ($allCombos as $c) {
                        $cClasses = @unserialize($c['class']);
                        if ($c['subject'] == $combo['subject'] && is_array($cClasses) && in_array($target, $cClasses)) {
                            $exists = true;
                            break;
                        }
                    }
                    if ($exists) continue;

                    $insert = $pdo->prepare("INSERT INTO tbl_subject_combinations (class, subject, teacher, reg_date, status) VALUES (?, ?, ?, ?, ?)");
                    $insert->execute([$serialized, $combo['subject'], $combo['teacher'], $combo['reg_date'], $combo['status']]);
                    $copied++;
                }
            }
            echo json_encode(['success' => true, 'copied' => $copied]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Get per-class grouped subjects (for class view modal) – FIXED with better error handling
    if ($_GET['ajax'] === 'class_view') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->query("SELECT sc.*, s.name AS subject_name, st.fname, st.lname
                                 FROM tbl_subject_combinations sc
                                 LEFT JOIN tbl_subjects s ON sc.subject = s.id
                                 LEFT JOIN tbl_staff st ON sc.teacher = st.id
                                 ORDER BY sc.id DESC");
            $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $classMap = [];
            $classStmt = $pdo->query("SELECT id, name FROM tbl_classes");
            while ($row = $classStmt->fetch(PDO::FETCH_NUM)) {
                $classMap[$row[0]] = $row[1];
            }

            $grouped = [];
            foreach ($combos as $combo) {
                $classList = @unserialize($combo['class']);
                if (is_array($classList)) {
                    foreach ($classList as $cid) {
                        // Skip if class ID not found in map (deleted class)
                        if (!isset($classMap[$cid])) continue;
                        $className = $classMap[$cid];
                        // Build teacher name
                        $teacherName = trim(($combo['fname'] ?? '') . ' ' . ($combo['lname'] ?? ''));
                        if (empty($teacherName)) $teacherName = 'Unassigned';
                        $grouped[$className][] = [
                            'subject' => $combo['subject_name'] ?? 'N/A',
                            'teacher' => $teacherName
                        ];
                    }
                }
            }
            echo json_encode(['success' => true, 'data' => $grouped]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Flash messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Subject Combinations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== GLOBAL – LEAVE HEADER THEME UNTOUCHED ========== */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
        }
        
        /* ========== GLASS STYLES (light glass, dark text inside) ========== */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        .tile-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        /* Filter card */
        .filter-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.25s ease;
        }
        .filter-card:hover {
            background: rgba(255,255,255,0.85);
        }
        .filter-card label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        
        /* DataTable styling */
        #srmsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        #srmsTable thead th {
            background: rgba(241,245,249,0.7);
            backdrop-filter: blur(4px);
            font-weight: 700;
            font-size: 0.85rem;
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            padding: 1rem 0.75rem;
        }
        #srmsTable tbody tr {
            transition: background 0.15s;
        }
        #srmsTable tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }
        #srmsTable td {
            vertical-align: middle;
            padding: 0.9rem 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            color: #1e293b;
        }
        
        /* Buttons */
        .btn-sm {
            border-radius: 40px;
            padding: 0.4rem 1.2rem;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            margin: 0 2px;
            border: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -6px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            color: white;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef476f, #dc2626);
            color: white;
        }
        .btn-outline-primary {
            background: white;
            border: 1px solid #4361ee;
            color: #4361ee;
        }
        .btn-outline-primary:hover {
            background: #4361ee;
            color: white;
        }
        .btn-outline-danger {
            background: white;
            border: 1px solid #ef476f;
            color: #ef476f;
        }
        .btn-outline-danger:hover {
            background: #ef476f;
            color: white;
        }
        .btn-outline-secondary {
            background: white;
            border: 1px solid #64748b;
            color: #64748b;
        }
        .btn-outline-secondary:hover {
            background: #64748b;
            color: white;
        }
        .btn-outline-info {
            background: white;
            border: 1px solid #4cc9f0;
            color: #4cc9f0;
        }
        .btn-outline-info:hover {
            background: #4cc9f0;
            color: white;
        }
        .btn-outline-success {
            background: white;
            border: 1px solid #06d6a0;
            color: #06d6a0;
        }
        .btn-outline-success:hover {
            background: #06d6a0;
            color: white;
        }
        .btn-add {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 8px 16px -6px rgba(67,97,238,0.4);
            transition: all 0.2s ease;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -8px rgba(67,97,238,0.5);
        }
        
        /* Badges */
        .badge-status {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .badge-status.active { background: linear-gradient(135deg, #06d6a0, #059669); color: white; box-shadow: 0 2px 8px rgba(6,214,160,0.3); }
        .badge-status.inactive { background: #6c757d; color: white; }
        .badge-status:hover { transform: scale(1.05); }
        
        /* Modals – glass */
        .modal-content {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 28px;
            color: #1e293b;
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 1.2rem 1.5rem;
        }
        .modal-title {
            font-weight: 800;
            color: #1e293b;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.08);
        }
        .form-control, .form-select {
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 16px;
            padding: 0.6rem 1rem;
            color: #1e293b;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        .btn-close {
            filter: none;
        }
        
        /* Select2 overrides */
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            min-height: 42px;
        }
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }
        
        /* Flash messages */
        .flash {
            padding: 1rem 1.5rem;
            border-radius: 60px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(8px);
        }
        .flash-success { background: rgba(6,214,160,0.2); border: 1px solid rgba(6,214,160,0.3); color: #065f46; }
        .flash-error { background: rgba(239,71,111,0.2); border: 1px solid rgba(239,71,111,0.3); color: #991b1b; }
        .db-warning {
            background: rgba(255,183,3,0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,183,3,0.3);
            border-radius: 60px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #92400e;
        }
        
        /* DataTable export buttons & search */
        .dt-buttons .btn {
            border-radius: 40px;
            padding: 0.3rem 1rem;
            margin-right: 0.3rem;
            background: white;
            border: 1px solid #dee2e6;
            color: #495057;
            transition: all 0.2s ease;
        }
        .dt-buttons .btn:hover {
            background: #e0e7ff;
            transform: translateY(-2px);
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 50px;
            padding: 0.5rem 1rem 0.5rem 2.2rem;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 0.8rem center;
            background-size: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            color: #1e293b;
        }
        
        /* Teacher tooltip */
        .teacher-tooltip {
            border-bottom: 1px dashed #64748b;
            cursor: help;
        }
        
        /* Keep header/breadcrumb colors as in unified header */
        .breadcrumb {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 0.5rem 1rem;
        }
        .breadcrumb-item a, .breadcrumb-item.active {
            color: white !important;
        }
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
        }
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-diagram-3 me-2"></i> Subject Combinations</h1></div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><button class="btn-add btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-1"></i>Add</button></li>
                <li class="breadcrumb-item"><button class="btn btn-outline-secondary btn-sm" type="button" id="copyCombBtn" data-bs-toggle="modal" data-bs-target="#copyModal"><i class="bi bi-files me-1"></i>Copy</button></li>
                <li class="breadcrumb-item"><button class="btn btn-outline-info btn-sm" type="button" id="classViewBtn" data-bs-toggle="modal" data-bs-target="#classViewModal"><i class="bi bi-table me-1"></i>Class View</button></li>
                <li class="breadcrumb-item"><button class="btn btn-outline-success btn-sm" id="exportBtn"><i class="bi bi-download me-1"></i>Export CSV</button></li>
            </ul>
        </div>

        <?php if ($successMsg): ?>
            <div class="flash flash-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="flash flash-error"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <!-- Database warning (if column is still VARCHAR) -->
        <?php
        try {
            $pdoCheck = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
            $stmt = $pdoCheck->query("SHOW COLUMNS FROM tbl_subject_combinations LIKE 'class'");
            $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($colInfo && stripos($colInfo['Type'], 'varchar') !== false) {
                echo '<div class="db-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>';
                echo 'The "class" column is still VARCHAR. Run: <code>ALTER TABLE tbl_subject_combinations MODIFY class TEXT;</code>';
                echo '</div>';
            }
            $stmt = $pdoCheck->query("SHOW COLUMNS FROM tbl_subject_combinations LIKE 'status'");
            if (!$stmt->fetch()) {
                echo '<div class="db-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>';
                echo 'Missing status column. Run: <code>ALTER TABLE tbl_subject_combinations ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1;</code>';
                echo '</div>';
            }
        } catch (Exception $e) {}
        ?>

        <!-- Filter Card (glass) -->
        <div class="filter-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label><i class="bi bi-building"></i> Class</label>
                    <select id="filterClass" class="form-select">
                        <option value="">All Classes</option>
                        <?php
                        $stmt = $pdoCheck->query("SELECT id, name FROM tbl_classes ORDER BY name");
                        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                            echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label><i class="bi bi-book"></i> Subject</label>
                    <select id="filterSubject" class="form-select">
                        <option value="">All Subjects</option>
                        <?php
                        $stmt = $pdoCheck->query("SELECT id, name FROM tbl_subjects ORDER BY name");
                        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                            echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label><i class="bi bi-person"></i> Teacher</label>
                    <select id="filterTeacher" class="form-select">
                        <option value="">All Teachers</option>
                        <?php
                        $stmt = $pdoCheck->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 ORDER BY fname");
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                            echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1].' '.$row[2]).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label><i class="bi bi-toggle-on"></i> Status</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-12 mt-2">
                    <button class="btn btn-secondary btn-sm" id="clearFilters"><i class="bi bi-eraser me-1"></i> Clear Filters</button>
                </div>
            </div>
        </div>

        <!-- Add Modal (glass) -->
        <div class="modal fade" id="addModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Subject Combination</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" action="academic/core/new_comb" id="addForm">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Subject</label>
                                <select class="form-control select2" name="subject" id="addSubject" required style="width: 100%;">
                                    <option selected disabled value="">Select one</option>
                                    <?php
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $stmt = $conn->query("SELECT id, name FROM tbl_subjects ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Class(es)</label>
                                <select multiple="true" class="form-control select2" name="class[]" id="addClasses" required style="width: 100%;">
                                    <?php
                                    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Teacher</label>
                                <select class="form-control select2" name="teacher" id="addTeacher" required style="width: 100%;">
                                    <option selected disabled value="">Select one</option>
                                    <?php
                                    $stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 ORDER BY fname");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1].' '.$row[2]).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Add Combination</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal (glass) – content loaded via AJAX -->
        <div class="modal fade" id="editModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Subject Combination</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="comb_feedback">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copy Modal (glass) -->
        <div class="modal fade" id="copyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-files me-2"></i>Copy Combinations</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="copyForm">
                            <div class="mb-3">
                                <label class="fw-semibold">Source Class</label>
                                <select id="sourceClass" class="form-select" required>
                                    <option value="">Select...</option>
                                    <?php
                                    $stmt = $pdoCheck->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold">Target Class(es)</label>
                                <select multiple id="targetClasses" class="form-control select2" style="width:100%">
                                    <?php
                                    $stmt = $pdoCheck->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i> Copies all combinations from source class to target class(es). Duplicate subject+class combos are skipped.
                            </div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Copy</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class View Modal (glass) – FIXED with better content handling -->
        <div class="modal fade" id="classViewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-table me-2"></i>Subjects by Class</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="classViewContent">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div> Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table (glass tile) -->
        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-body">
                        <h3 class="tile-title"><i class="bi bi-table me-2"></i>Subject Combinations</h3>
                        <div class="table-responsive">
                            <table class="table table-hover" id="srmsTable">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Classes</th>
                                        <th># Classes</th>
                                        <th>Added On</th>
                                        <th>Status</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                    $classMap = [];
                                    $stmt = $conn->query("SELECT id, name FROM tbl_classes");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) $classMap[$row[0]] = $row[1];

                                    $teacherCounts = [];
                                    $countStmt = $conn->query("SELECT teacher, COUNT(*) as cnt FROM tbl_subject_combinations GROUP BY teacher");
                                    while ($row = $countStmt->fetch(PDO::FETCH_NUM)) $teacherCounts[$row[0]] = $row[1];

                                    $stmt = $conn->prepare("SELECT sc.*, sub.name AS subject_name, st.fname, st.lname
                                                             FROM tbl_subject_combinations sc
                                                             LEFT JOIN tbl_subjects sub ON sc.subject = sub.id
                                                             LEFT JOIN tbl_staff st ON sc.teacher = st.id
                                                             ORDER BY sc.id DESC");
                                    $stmt->execute();
                                    $result = $stmt->fetchAll();

                                    foreach ($result as $row) {
                                        $classList = @unserialize($row['class']);
                                        if ($classList === false) {
                                            $classDisplay = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Corrupted</span>';
                                            $classCount = 0;
                                        } else {
                                            $classNames = [];
                                            foreach ($classList as $cid) {
                                                $classNames[] = $classMap[$cid] ?? '<span class="text-danger">(unknown)</span>';
                                            }
                                            $classDisplay = implode(', ', $classNames);
                                            $classCount = count($classList);
                                        }
                                        $teacherName = ($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '');
                                        $teacherTooltip = $teacherName ? ($teacherCounts[$row['teacher']] ?? 0) . ' combo(s)' : '';
                                        $status = $row['status'] ?? 1;
                                        $statusBadge = $status == 1 
                                            ? '<span class="badge-status active status-badge" data-id="'.$row['id'].'" data-status="1"><i class="bi bi-check-circle-fill"></i> Active</span>'
                                            : '<span class="badge-status inactive status-badge" data-id="'.$row['id'].'" data-status="0"><i class="bi bi-x-circle-fill"></i> Inactive</span>';
                                        ?>
                                        <tr data-comb-id="<?php echo $row['id']; ?>">
                                            <td><?php echo htmlspecialchars($row['subject_name'] ?? 'N/A'); ?></td>
                                            <td class="teacher-tooltip" title="<?php echo htmlspecialchars($teacherTooltip); ?>"><?php echo htmlspecialchars($teacherName); ?></td>
                                            <td><?php echo $classDisplay; ?></td>
                                            <td><?php echo $classCount; ?></td>
                                            <td><?php echo htmlspecialchars($row['reg_date']); ?></td>
                                            <td><?php echo $statusBadge; ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm edit-btn" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Edit</button>
                                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $row['id']; ?>"><i class="bi bi-trash"></i> Delete</button>
                                            </span>
                                        </tr>
                                        <?php
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="7" class="text-danger">Database error: '.htmlspecialchars($e->getMessage()).'</span></span><tr>';
                                }
                                ?>
                                </tbody>
                            </table>
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
    <script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="select2/dist/js/select2.full.min.js"></script>
    <?php require_once('const/check-reply.php'); ?>
    <script>
    $(document).ready(function(){
        // Initialize DataTable with export
        var table = $('#srmsTable').DataTable({
            "sort": false,
            "dom": 'Bfrtip',
            "buttons": [
                { extend: 'csvHtml5', text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV', title: 'subject_combinations', exportOptions: { columns: [0,1,2,4] } }
            ]
        });
        $('#exportBtn').on('click', function(){ table.button('.buttons-csv').trigger(); });

        // Select2 initialization for all modals
        $('#addModal .select2').select2({ dropdownParent: $('#addModal') });
        $('#copyModal .select2').select2({ dropdownParent: $('#copyModal') });

        // Edit button: load data via AJAX
        $('.edit-btn').on('click', function(){
            var combId = $(this).data('id');
            $('#comb_feedback').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div> Loading...</div>');
            $.ajax({
                url: 'academic/combinations.php',
                method: 'GET',
                data: { ajax: 'get_combination', id: combId },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        var comb = res.comb, classList = res.classList, allClasses = res.allClasses, teachers = res.teachers, subjects = res.subjects;
                        var html = '<form class="app_frm" method="POST" action="academic/core/update_comb" id="editForm">';
                        html += '<input type="hidden" name="id" value="' + comb.id + '">';
                        html += '<div class="mb-3"><label class="fw-semibold">Subject</label><select class="form-control select2" name="subject" required style="width:100%;">';
                        $.each(subjects, function(i, subj){
                            var selected = (subj.id == comb.subject) ? ' selected' : '';
                            html += '<option value="' + subj.id + '"' + selected + '>' + subj.name + '</option>';
                        });
                        html += '</select></div>';
                        html += '<div class="mb-3"><label class="fw-semibold">Class(es)</label><select multiple class="form-control select2" name="class[]" required style="width:100%;">';
                        $.each(allClasses, function(id, name){
                            var selected = ($.inArray(id.toString(), classList) !== -1) ? ' selected' : '';
                            html += '<option value="' + id + '"' + selected + '>' + name + '</option>';
                        });
                        html += '</select></div>';
                        html += '<div class="mb-3"><label class="fw-semibold">Teacher</label><select class="form-control select2" name="teacher" required style="width:100%;">';
                        $.each(teachers, function(i, t){
                            var selected = (t.id == comb.teacher) ? ' selected' : '';
                            html += '<option value="' + t.id + '"' + selected + '>' + t.fname + ' ' + t.lname + '</option>';
                        });
                        html += '</select></div>';
                        html += '<div class="d-flex gap-2 justify-content-end"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="submit" value="1" class="btn btn-primary">Update</button></div></form>';
                        $('#comb_feedback').html(html);
                        $('#editModal .select2').select2({ dropdownParent: $('#editModal') });
                    } else {
                        $('#comb_feedback').html('<div class="alert alert-danger">Error: ' + res.error + '</div>');
                    }
                },
                error: function() { $('#comb_feedback').html('<div class="alert alert-danger">AJAX error loading data.</div>'); }
            });
        });

        // Delete button with SweetAlert
        $('.delete-btn').on('click', function(){
            var combId = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This will delete the subject combination permanently.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'academic/core/drop_comb?id=' + combId;
            });
        });

        // Toggle status (Active/Inactive)
        $(document).on('click', '.status-badge', function(){
            var $badge = $(this);
            var id = $badge.data('id');
            var currentStatus = $badge.data('status');
            var newStatus = currentStatus == 1 ? 0 : 1;
            $.ajax({
                url: 'academic/combinations.php',
                method: 'POST',
                data: { ajax: 'toggle_status', id: id, status: newStatus },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $badge.data('status', newStatus);
                        if (newStatus == 1) {
                            $badge.removeClass('inactive').addClass('active').html('<i class="bi bi-check-circle-fill"></i> Active');
                        } else {
                            $badge.removeClass('active').addClass('inactive').html('<i class="bi bi-x-circle-fill"></i> Inactive');
                        }
                        Swal.fire('Updated', 'Status changed', 'success');
                    } else { Swal.fire('Error', res.error, 'error'); }
                },
                error: function() { Swal.fire('Error', 'Could not update status', 'error'); }
            });
        });

        // Filters
        function applyFilters() {
            var subjectName = $('#filterSubject option:selected').text();
            var teacherName = $('#filterTeacher option:selected').text();
            var statusVal = $('#filterStatus').val();
            var statusText = statusVal === '1' ? 'Active' : (statusVal === '0' ? 'Inactive' : '');
            var className = $('#filterClass option:selected').text();
            table.columns(0).search(subjectName === 'All Subjects' ? '' : subjectName);
            table.columns(1).search(teacherName === 'All Teachers' ? '' : teacherName);
            table.columns(5).search(statusText);
            table.columns(2).search(className === 'All Classes' ? '' : className);
            table.draw();
        }
        $('#filterClass, #filterSubject, #filterTeacher, #filterStatus').on('change', applyFilters);
        $('#clearFilters').on('click', function(){
            $('#filterClass, #filterSubject, #filterTeacher, #filterStatus').val('');
            applyFilters();
        });

        // Copy combinations
        $('#copyForm').on('submit', function(e){
            e.preventDefault();
            var source = $('#sourceClass').val();
            var targets = $('#targetClasses').val();
            if (!source || !targets || targets.length === 0) {
                Swal.fire('Error', 'Select source and at least one target class.', 'warning');
                return;
            }
            Swal.fire({
                title: 'Copy Combinations',
                text: 'Copy all combinations from source to target class(es)?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, copy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'academic/combinations.php',
                        method: 'POST',
                        data: { ajax: 'copy_combinations', source_class: source, target_classes: targets },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire('Success', res.copied + ' combination(s) copied.', 'success').then(() => location.reload());
                            } else { Swal.fire('Error', res.error, 'error'); }
                        },
                        error: function() { Swal.fire('Error', 'Could not copy combinations', 'error'); }
                    });
                }
            });
        });

        // Class View Modal – FIXED: loads data when button clicked and modal shown
        $('#classViewBtn').on('click', function(){
            // Show loading indicator inside modal
            $('#classViewContent').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div> Loading...</div>');
            // Make AJAX call to fetch data
            $.ajax({
                url: 'academic/combinations.php',
                method: 'GET',
                data: { ajax: 'class_view' },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.data) {
                        var html = '<div class="row">';
                        // Check if data is empty
                        var classNames = Object.keys(res.data);
                        if (classNames.length === 0) {
                            html += '<div class="col-12"><div class="alert alert-info text-center">No subject combinations found.</div></div>';
                        } else {
                            $.each(res.data, function(className, subjects) {
                                html += '<div class="col-md-6 col-lg-4 mb-3"><div class="card h-100 shadow-sm"><div class="card-header bg-primary text-white fw-bold">' + className + '</div><ul class="list-group list-group-flush">';
                                if (subjects.length === 0) {
                                    html += '<li class="list-group-item text-muted">No subjects assigned</li>';
                                } else {
                                    $.each(subjects, function(i, subj) {
                                        html += '<li class="list-group-item d-flex justify-content-between align-items-center">' + subj.subject + '<span class="badge bg-secondary">' + subj.teacher + '</span></li>';
                                    });
                                }
                                html += '</ul></div></div>';
                            });
                        }
                        html += '</div>';
                        $('#classViewContent').html(html);
                    } else {
                        $('#classViewContent').html('<div class="alert alert-danger">' + (res.error || 'Failed to load class view.') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#classViewContent').html('<div class="alert alert-danger">Failed to load class view: ' + error + '</div>');
                }
            });
        });
    });
    </script>
</body>
</html>