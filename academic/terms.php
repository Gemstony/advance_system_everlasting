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

// Fetch all data
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all terms
    $stmt = $conn->query("SELECT * FROM tbl_terms ORDER BY id DESC");
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Advanced statistics ---
    // 1. Distinct students per term
    $studentCounts = [];
    $stmt = $conn->query("SELECT term, COUNT(DISTINCT student) as student_count FROM tbl_exam_results GROUP BY term");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $studentCounts[$row['term']] = $row['student_count'];
    }

    // 2. Total subject entries per term
    $subjectCounts = [];
    $stmt = $conn->query("SELECT term, COUNT(*) as cnt FROM tbl_exam_results GROUP BY term");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subjectCounts[$row['term']] = $row['cnt'];
    }

    // 3. Average score, pass/fail counts per term
    $termPerformance = [];
    $stmt = $conn->query("
        SELECT term, 
               AVG(score) as avg_score, 
               SUM(CASE WHEN score >= 45 THEN 1 ELSE 0 END) as pass_count,
               SUM(CASE WHEN score < 45 THEN 1 ELSE 0 END) as fail_count,
               COUNT(*) as total_entries
        FROM tbl_exam_results 
        GROUP BY term
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $termPerformance[$row['term']] = [
            'avg' => round($row['avg_score'], 1),
            'pass' => $row['pass_count'],
            'fail' => $row['fail_count'],
            'total' => $row['total_entries']
        ];
    }

    // 4. Total distinct students across all terms
    $stmt = $conn->query("SELECT COUNT(DISTINCT student) as total_students FROM tbl_exam_results");
    $totalStudentsWithResults = $stmt->fetchColumn();

    // 5. Total subject entries across all terms
    $stmt = $conn->query("SELECT COUNT(*) as total_entries FROM tbl_exam_results");
    $totalSubjectEntries = $stmt->fetchColumn();

    // 6. Average subject entries per student
    $avg_per_student = ($totalStudentsWithResults > 0) ? round($totalSubjectEntries / $totalStudentsWithResults, 1) : 0;

    // 7. Most active term (with most students)
    $mostActiveTermId = null;
    $mostActiveStudentCount = 0;
    foreach ($studentCounts as $termId => $count) {
        if ($count > $mostActiveStudentCount) {
            $mostActiveStudentCount = $count;
            $mostActiveTermId = $termId;
        }
    }
    $mostActiveTermName = '';
    foreach ($terms as $t) {
        if ($t['id'] == $mostActiveTermId) {
            $mostActiveTermName = $t['name'];
            break;
        }
    }

    // 8. Terms with results
    $terms_with_results = count($studentCounts);

    // Calculate active/inactive
    $active_terms = 0;
    $inactive_terms = 0;
    foreach ($terms as $t) {
        if ($t['status'] == 1) $active_terms++; else $inactive_terms++;
    }
    $total_terms = count($terms);

} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Academic Terms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== GLOBAL ===== */
        .app-content {
            background: transparent !important;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fa;
        }

        /* ===== PAGE TITLE (black) ===== */
        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;
            text-shadow: none !important;
        }

        /* ===== STATS CARDS – compact, professional ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1rem 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
            transition: transform 0.2s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            background: #f0f4ff;
            color: #4361ee;
        }
        .stat-card .stat-content {
            flex: 1;
            min-width: 0; /* for truncation */
        }
        .stat-card .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .stat-card .stat-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.3px;
            margin-top: 1px;
        }
        .stat-card .stat-sub {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Variants */
        .stat-card.green .stat-icon { background: #e6f7ed; color: #0b8a5e; }
        .stat-card.red .stat-icon { background: #fce8ec; color: #c92a4a; }
        .stat-card.gold .stat-icon { background: #fef3d7; color: #b78103; }
        .stat-card.blue .stat-icon { background: #e9edfd; color: #3651d4; }
        .stat-card.teal .stat-icon { background: #e0f4f0; color: #0d9488; }
        .stat-card.pink .stat-icon { background: #fce4ec; color: #c2185b; }

        /* ===== CARDS (tiles) – compact ===== */
        .tile-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
            margin-bottom: 1.5rem;
            transition: box-shadow 0.3s ease;
        }
        .tile-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }
        .tile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        .tile-header h3 {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tile-header .tile-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        .tile-header .tile-actions .manage-text {
            color: #1e293b !important;
            font-weight: 500;
            font-size: 0.8rem;
        }

        /* ===== DATA TABLE ===== */
        #srmsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: #1e293b;
            font-size: 0.85rem;
        }
        #srmsTable thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 2px solid #e9edf2;
            color: #1e293b;
            padding: 0.7rem 0.5rem;
        }
        #srmsTable tbody tr {
            transition: background 0.15s;
        }
        #srmsTable tbody tr:hover {
            background: #fafbfc;
        }
        #srmsTable td {
            vertical-align: middle;
            padding: 0.7rem 0.5rem;
            border-bottom: 1px solid #f1f3f5;
            color: #1e293b;
        }

        /* Badges */
        .badge-status {
            padding: 0.2rem 0.8rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.65rem;
            letter-spacing: 0.2px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .badge-status.active {
            background: #e6f7ed;
            color: #0b7a4a;
        }
        .badge-status.inactive {
            background: #fce8ec;
            color: #b91c2e;
        }
        .result-badge {
            background: #f1f5f9;
            border-radius: 50px;
            padding: 0.2rem 0.8rem;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }
        .result-badge .entry-count {
            color: #0d9488;
            font-weight: 700;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #0b8a5e;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* ===== BUTTONS – professional with glass glow hover ===== */
        .btn {
            border-radius: 40px;
            padding: 0.3rem 1.2rem;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.25s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 40px;
            background: rgba(255,255,255,0.15);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .btn:hover::after {
            opacity: 1;
        }
        .btn-primary {
            background: #4361ee;
            color: white;
            box-shadow: 0 2px 8px rgba(67,97,238,0.2);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67,97,238,0.3);
            background: #3a56d4;
        }
        .btn-danger {
            background: #ef476f;
            color: white;
            box-shadow: 0 2px 8px rgba(239,71,111,0.2);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239,71,111,0.3);
            background: #dc3545;
        }
        .btn-success {
            background: #0b8a5e;
            color: white;
            box-shadow: 0 2px 8px rgba(11,138,94,0.2);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11,138,94,0.3);
            background: #0a7a52;
        }
        .btn-secondary {
            background: #e9edf2;
            color: #1e293b;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
            background: #dce1e8;
        }
        .btn-primary-glow {
            background: #4361ee;
            color: white;
            border-radius: 50px;
            padding: 0.5rem 1.6rem;
            font-weight: 600;
            border: none;
            box-shadow: 0 4px 12px rgba(67,97,238,0.25);
            transition: all 0.25s ease;
            font-size: 0.85rem;
        }
        .btn-primary-glow:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(67,97,238,0.35);
            background: #3a56d4;
            color: white;
        }

        /* ===== MODALS ===== */
        .modal-content {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 24px;
            color: #1e293b;
        }
        .modal-header {
            border-bottom: 1px solid #f1f3f5;
            padding: 1.2rem 1.5rem 0.5rem;
        }
        .modal-title {
            font-weight: 800;
            color: #1e293b;
            font-size: 1.1rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid #f1f3f5;
            padding: 0.8rem 1.5rem 1.5rem;
        }
        .form-label {
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            background: #ffffff;
            border: 1px solid #dce1e8;
            border-radius: 16px;
            padding: 0.6rem 1rem;
            color: #1e293b;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.1);
        }

        /* ===== DATA TABLE WRAPPER ===== */
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            color: #1e293b;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .dataTables_wrapper .dataTables_length select {
            background: white;
            border: 1px solid #dce1e8;
            border-radius: 30px;
            padding: 0.2rem 1.6rem 0.2rem 0.8rem;
            font-weight: 500;
            color: #1e293b;
            font-size: 0.85rem;
        }
        .dataTables_wrapper .dataTables_filter input {
            background: white;
            border: 1px solid #dce1e8;
            border-radius: 50px;
            padding: 0.4rem 1rem 0.4rem 2.2rem;
            color: #1e293b;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2343656e' class='bi bi-search' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 0.8rem center;
            background-size: 1rem;
            width: 200px;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.08);
            outline: none;
            width: 240px;
        }
        .dataTables_wrapper .dataTables_info {
            color: #475569;
            font-weight: 500;
            font-size: 0.85rem;
            padding-top: 0.8rem;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 0.8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #1e293b !important;
            background: #f1f5f9;
            border-radius: 30px;
            border: none;
            margin: 0 2px;
            padding: 0.2rem 0.8rem;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee;
            color: white !important;
            border-radius: 30px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e2e8f0;
            color: #1e293b !important;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.8rem;
            }
            .stat-card {
                padding: 0.8rem;
            }
            .stat-icon {
                width: 38px;
                height: 38px;
                font-size: 1.2rem;
            }
            .stat-card .stat-value {
                font-size: 1.3rem;
            }
            .tile-card {
                padding: 0.8rem;
            }
            .tile-header {
                flex-direction: column;
                align-items: stretch;
            }
            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
            }
            .dataTables_wrapper .dataTables_filter input:focus {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <!-- Page Title (black) -->
        <div class="app-title">
            <div><h1><i class="bi bi-calendar-week me-2"></i> Academic Terms</h1></div>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><button class="btn btn-primary-glow" type="button" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg me-2"></i>Add New Term</button></li>
            </ul>
        </div>

        <!-- Stats Grid (compact) -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $total_terms; ?></div>
                    <div class="stat-label">Total Terms</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $active_terms; ?></div>
                    <div class="stat-label">Active Terms</div>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $inactive_terms; ?></div>
                    <div class="stat-label">Inactive Terms</div>
                </div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStudentsWithResults); ?></div>
                    <div class="stat-label">Students with Results</div>
                    <div class="stat-sub">Across all terms</div>
                </div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon"><i class="bi bi-file-text"></i></div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalSubjectEntries); ?></div>
                    <div class="stat-label">Total Subject Entries</div>
                    <div class="stat-sub">Avg <?php echo $avg_per_student; ?> per student</div>
                </div>
            </div>
            <div class="stat-card pink">
                <div class="stat-icon"><i class="bi bi-trophy"></i></div>
                <div class="stat-content">
                    <div class="stat-value" style="font-size:1.3rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($mostActiveTermName); ?>">
                        <?php echo htmlspecialchars($mostActiveTermName ?: 'N/A'); ?>
                    </div>
                    <div class="stat-label">Most Active Term</div>
                    <div class="stat-sub"><?php echo $mostActiveStudentCount; ?> students</div>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="addModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Academic Term</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_term">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Term Name</label>
                                <input required name="name" class="form-control" type="text" placeholder="e.g., Midterm 2026">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select" name="status" required>
                                    <option selected disabled value="">Select status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Add Term</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Academic Term</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/update_term">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Term Name</label>
                                <input id="term" required name="name" class="form-control" type="text">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select id="status" class="form-select" name="status" required>
                                    <option selected disabled value="">Select status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <input type="hidden" name="id" id="id">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Tile (no Export CSV) -->
        <div class="tile-card">
            <div class="tile-header">
                <h3><i class="bi bi-table"></i> All Terms</h3>
                <div class="tile-actions">
                    <span class="manage-text" style="color:#000 !important; font-weight:500;">Manage your academic periods</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="srmsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Students with Results</th>
                            <th>Performance (Avg / Pass)</th>
                            <th>Actions</th>
                            <th>Toggle Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sn = 1;
                        foreach ($terms as $row): 
                            $studentCnt = isset($studentCounts[$row['id']]) ? $studentCounts[$row['id']] : 0;
                            $subjectCnt = isset($subjectCounts[$row['id']]) ? $subjectCounts[$row['id']] : 0;
                            $perf = isset($termPerformance[$row['id']]) ? $termPerformance[$row['id']] : ['avg'=>0, 'pass'=>0, 'fail'=>0, 'total'=>0];
                            $avg = $perf['avg'];
                            $pass = $perf['pass'];
                            $total = $perf['total'];
                            $passRate = ($total > 0) ? round(($pass / $total) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>
                                <?php if ($row['status'] == 1): ?>
                                    <span class="badge-status active"><i class="bi bi-check-circle"></i> ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge-status inactive"><i class="bi bi-x-circle"></i> INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="result-badge" title="<?php echo $subjectCnt; ?> subject entries">
                                    <i class="bi bi-person me-1"></i> <?php echo $studentCnt; ?>
                                </span>
                                <?php if ($studentCnt > 0): ?>
                                    <span class="small text-muted ms-1">(<span class="entry-count" style="color:#0d9488;"><?php echo $subjectCnt; ?></span> entries)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($total > 0): ?>
                                    <span class="badge bg-<?php echo ($avg >= 75) ? 'success' : (($avg >= 45) ? 'warning' : 'danger'); ?>">
                                        <?php echo $avg; ?>%
                                    </span>
                                    <span class="badge bg-info text-dark">Pass: <?php echo $passRate; ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted">No data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a onclick="set_term('<?php echo $row['id']; ?>', '<?php echo $row['status']; ?>');" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Edit</a>
                                <a onclick="del('academic/core/drop_term?id=<?php echo $row['id']; ?>', 'Delete this term?')" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Delete</a>
                            </td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" class="term-toggle" data-term-id="<?php echo $row['id']; ?>" <?php echo ($row['status'] == 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
    <script>
    $(document).ready(function(){
        // DataTable (no export button)
        var table = $('#srmsTable').DataTable({
            "sort": false,
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "language": {
                "search": "Search terms:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ terms",
                "infoEmpty": "No terms available",
                "infoFiltered": "(filtered from _MAX_ total terms)",
                "paginate": {
                    "previous": "<i class='bi bi-chevron-left'></i>",
                    "next": "<i class='bi bi-chevron-right'></i>"
                }
            }
        });

        // Toggle active status via AJAX
        $('.term-toggle').on('change', function() {
            var toggle = $(this);
            var termId = toggle.data('term-id');
            var newStatus = toggle.is(':checked') ? 1 : 0;
            $.ajax({
                url: 'academic/toggle_term_status.php',
                method: 'POST',
                data: { term_id: termId, status: newStatus },
                dataType: 'json',
                beforeSend: function() { toggle.prop('disabled', true); },
                success: function(response) {
                    if (response.success) {
                        var row = toggle.closest('tr');
                        var statusCell = row.find('td:eq(2)');
                        if (newStatus === 1) {
                            statusCell.html('<span class="badge-status active"><i class="bi bi-check-circle"></i> ACTIVE</span>');
                        } else {
                            statusCell.html('<span class="badge-status inactive"><i class="bi bi-x-circle"></i> INACTIVE</span>');
                        }
                        Swal.fire({ icon: 'success', title: 'Status Updated', text: response.message, timer: 2000, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                        toggle.prop('checked', !newStatus);
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error', 'error');
                    toggle.prop('checked', !newStatus);
                },
                complete: function() { toggle.prop('disabled', false); }
            });
        });
    });

    // Helper: store term names in hidden textareas for edit modal
    <?php foreach ($terms as $row): ?>
    $('body').append('<textarea style="display:none;" id="term_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></textarea>');
    <?php endforeach; ?>

    function set_term(id, status) {
        $('#id').val(id);
        $('#term').val($('#term_' + id).val());
        $('#status').val(status);
    }

    function del(url, msg) {
        if (confirm(msg)) window.location.href = url;
    }
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>