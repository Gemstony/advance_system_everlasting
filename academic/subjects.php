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

// Fetch subjects and usage counts
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT * FROM tbl_subjects ORDER BY name");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usage = [];
    $stmt = $pdo->query("SELECT subject, COUNT(*) as count FROM tbl_subject_combinations GROUP BY subject");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $usage[$row['subject']] = $row['count'];
    }

    $total_subjects = count($subjects);
    $used_subjects = count($usage);
    $unused_subjects = $total_subjects - $used_subjects;
    $usage_rate = $total_subjects > 0 ? round(($used_subjects / $total_subjects) * 100, 1) : 0;

    $most_used_subject = null;
    $max_usage = 0;
    foreach ($usage as $subj_id => $cnt) {
        if ($cnt > $max_usage) {
            $max_usage = $cnt;
            foreach ($subjects as $s) {
                if ($s['id'] == $subj_id) {
                    $most_used_subject = $s['name'];
                    break;
                }
            }
        }
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Subjects</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== BASE ===== */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
            padding: 1.5rem 2rem;
        }

        /* ===== MODERN STAT CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.2rem 1.2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.06);
            border-color: #4361ee;
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: #f0f4ff;
            color: #4361ee;
        }
        .stat-card:nth-child(2) .stat-icon { background: #e6f7ed; color: #06d6a0; }
        .stat-card:nth-child(3) .stat-icon { background: #fce8ec; color: #ef476f; }
        .stat-card:nth-child(4) .stat-icon { background: #fff3e0; color: #f59e0b; }

        .stat-content {
            flex: 1;
        }
        .stat-content .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
            color: #475569;
            margin: 0;
        }
        .stat-content .value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .stat-content .sub {
            font-size: 0.7rem;
            color: #475569;
            font-weight: 500;
        }

        /* ===== MODERN FILTER CARDS ===== */
        .filter-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        .filter-card {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #f1f5f9;
            color: #1e293b;
            border: 2px solid transparent;
        }
        .filter-card i {
            font-size: 1.2rem;
        }
        .filter-card .filter-count {
            background: rgba(0,0,0,0.08);
            border-radius: 30px;
            padding: 0.1rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .filter-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        .filter-card.used-card {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .filter-card.used-card .filter-count {
            background: #28a745;
            color: white;
        }
        .filter-card.unused-card {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .filter-card.unused-card .filter-count {
            background: #dc3545;
            color: white;
        }
        .filter-card.clear-card {
            background: #dbeafe;
            color: #1e40af;
            border-color: #4361ee;
        }
        .filter-card.clear-card .filter-count {
            background: #4361ee;
            color: white;
        }
        .filter-card.active-filter {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.3), 0 6px 16px rgba(67,97,238,0.15);
        }
        .filter-card.used-card.active-filter {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.3), 0 6px 16px rgba(40,167,69,0.15);
        }
        .filter-card.unused-card.active-filter {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220,53,69,0.3), 0 6px 16px rgba(220,53,69,0.15);
        }

        /* ===== TILE ===== */
        .tile {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.8rem 1.8rem 1.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        .tile:hover {
            box-shadow: 0 8px 28px rgba(0,0,0,0.06);
            border-color: rgba(67,97,238,0.2);
        }
        .tile-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: #0f172a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== TABLE ===== */
        #subjectsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: #1e293b;
        }
        #subjectsTable thead th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #4361ee;
            color: #0f172a;
            padding: 0.9rem 0.75rem;
        }
        #subjectsTable tbody tr {
            transition: background 0.15s;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        #subjectsTable tbody tr:hover {
            background: rgba(67,97,238,0.04);
        }
        #subjectsTable td {
            vertical-align: middle;
            padding: 0.8rem 0.75rem;
            color: #1e293b;
        }

        /* ===== BADGE USAGE (inside table) ===== */
        .badge-usage {
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .badge-usage.used {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .badge-usage.unused {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .badge-usage:hover {
            transform: scale(1.04);
        }

        /* ===== BUTTONS WITH GLASS GLOW EFFECT ===== */
        .btn-sm {
            border-radius: 30px;
            padding: 0.3rem 1rem;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            position: relative;
            overflow: hidden;
        }
        .btn-sm::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.15) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }
        .btn-sm:hover::before {
            opacity: 1;
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .btn-primary {
            background: #4361ee;
            border-color: #4361ee;
            color: white !important;
        }
        .btn-primary:hover {
            background: #3651d4;
            box-shadow: 0 8px 24px rgba(67,97,238,0.3);
        }

        .btn-outline-primary {
            background: transparent;
            border-color: #4361ee;
            color: #4361ee !important;
        }
        .btn-outline-primary:hover {
            background: #4361ee;
            color: white !important;
            box-shadow: 0 8px 24px rgba(67,97,238,0.25);
        }

        .btn-outline-danger {
            background: transparent;
            border-color: #ef476f;
            color: #ef476f !important;
        }
        .btn-outline-danger:hover {
            background: #ef476f;
            color: white !important;
            box-shadow: 0 8px 24px rgba(239,71,111,0.25);
        }

        .btn-outline-info {
            background: transparent;
            border-color: #06d6a0;
            color: #06d6a0 !important;
        }
        .btn-outline-info:hover {
            background: #06d6a0;
            color: white !important;
            box-shadow: 0 8px 24px rgba(6,214,160,0.25);
        }

        .btn-add {
            background: #4361ee;
            border: 2px solid #4361ee;
            border-radius: 40px;
            padding: 0.6rem 2rem;
            font-weight: 700;
            color: white !important;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(67,97,238,0.2);
            position: relative;
            overflow: hidden;
        }
        .btn-add::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }
        .btn-add:hover::before {
            opacity: 1;
        }
        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(67,97,238,0.3);
            background: #3651d4;
        }

        .btn-secondary {
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            color: #1e293b !important;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* ===== MODALS ===== */
        .modal-content {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.04);
            border-radius: 20px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.06);
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.04);
            padding: 1.5rem 1.5rem 0.5rem;
        }
        .modal-title {
            font-weight: 700;
            color: #0f172a;
        }
        .modal-body {
            padding: 1.5rem;
            color: #1e293b;
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.04);
            padding: 1rem 1.5rem 1.5rem;
        }
        .form-control {
            background: #ffffff;
            border: 1px solid #dce1e8;
            border-radius: 16px;
            padding: 0.75rem 1.2rem;
            font-weight: 500;
            color: #1e293b;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.1);
            outline: none;
        }
        .btn-close {
            filter: none;
        }

        /* ===== SUMMARY CARDS (inside details modal) ===== */
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem;
            text-align: center;
            transition: all 0.2s;
        }
        .summary-card:hover {
            background: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
        }
        .summary-card i {
            font-size: 2rem;
            color: #4361ee;
        }
        .summary-card .count {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .summary-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: #475569;
        }
        .combination-list .list-group-item {
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 0.3rem;
            color: #1e293b;
        }

        /* ===== DATA TABLES ===== */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 30px;
            padding: 0.5rem 1rem 0.5rem 2.2rem;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 0.8rem center;
            background-size: 16px;
            border: 1px solid #dce1e8;
            color: #1e293b;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.08);
            outline: none;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #dce1e8;
            border-radius: 30px;
            padding: 0.3rem 1.8rem 0.3rem 0.8rem;
            font-weight: 500;
            color: #1e293b;
            background: white;
        }
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: #4361ee;
            outline: none;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #1e293b !important;
            background: #f1f5f9;
            border-radius: 30px;
            border: 1px solid #e2e8f0 !important;
            margin: 0 3px;
            padding: 0.3rem 0.8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e2e8f0 !important;
        }
        .dt-buttons .btn {
            border-radius: 30px;
            padding: 0.3rem 1rem;
            margin-right: 0.3rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            color: #1e293b !important;
            font-weight: 500;
        }
        .dt-buttons .btn:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .app-content { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-cards { gap: 0.5rem; }
            .filter-card { padding: 0.4rem 0.8rem; font-size: 0.75rem; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <!-- Title & Add Button -->
        <div class="app-title">
            <div><h1><i class="bi bi-book me-2"></i> Subjects</h1></div>
            <div>
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Subject
                </button>
            </div>
        </div>

        <!-- Stats Grid (modern flat cards) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-book"></i></div>
                <div class="stat-content">
                    <div class="label">Total Subjects</div>
                    <div class="value"><?php echo $total_subjects; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-content">
                    <div class="label">In Use</div>
                    <div class="value"><?php echo $used_subjects; ?></div>
                    <div class="sub"><?php echo $usage_rate; ?>% of total</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                <div class="stat-content">
                    <div class="label">Unused</div>
                    <div class="value"><?php echo $unused_subjects; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-trophy"></i></div>
                <div class="stat-content">
                    <div class="label">Most Used</div>
                    <div class="value"><?php echo $most_used_subject ?? '—'; ?></div>
                    <div class="sub"><?php echo $max_usage > 0 ? $max_usage . ' combination(s)' : ''; ?></div>
                </div>
            </div>
        </div>

        <!-- Modern Filter Cards -->
        <div class="filter-cards">
            <div class="filter-card used-card" data-filter="used">
                <i class="bi bi-check-circle-fill"></i>
                <span class="filter-label">Used</span>
                <span class="filter-count"><?php echo $used_subjects; ?></span>
            </div>
            <div class="filter-card unused-card" data-filter="unused">
                <i class="bi bi-x-circle-fill"></i>
                <span class="filter-label">Unused</span>
                <span class="filter-count"><?php echo $unused_subjects; ?></span>
            </div>
            <div class="filter-card clear-card" id="clearFilterBtn">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span class="filter-label">Clear</span>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="addModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Subject</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" action="academic/core/new_subject" id="addForm">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Subject Name</label>
                                <input required name="name" class="form-control" type="text" placeholder="e.g., Mathematics" autofocus>
                            </div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Add Subject</button>
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
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Subject</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" action="academic/core/update_subject" id="editForm">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Subject Name</label>
                                <input id="edit_name" required name="name" class="form-control" type="text">
                            </div>
                            <input type="hidden" name="id" id="edit_id">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Subject Usage Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="detailsModalBody">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="tile">
            <div class="tile-body">
                <h3 class="tile-title"><i class="bi bi-table me-2"></i>Subject List</h3>
                <div class="table-responsive">
                    <table class="table table-hover" id="subjectsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject Name</th>
                                <th>Usage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $index => $row): 
                                $id = $row['id'];
                                $name = htmlspecialchars($row['name']);
                                $usage_count = $usage[$id] ?? 0;
                                $sn = $index + 1;
                            ?>
                            <tr data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>">
                                <td><?php echo $sn; ?></td>
                                <td><?php echo $name; ?></td>
                                <td>
                                    <?php if ($usage_count > 0): ?>
                                        <span class="badge-usage used view-details" data-id="<?php echo $id; ?>" style="cursor: pointer;">
                                            <i class="bi bi-diagram-3"></i> <?php echo $usage_count; ?> combination<?php echo $usage_count != 1 ? 's' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-usage unused">Not used</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <textarea style="display:none;" id="subject_<?php echo $id; ?>"><?php echo $name; ?></textarea>
                                    <button onclick="set_subject('<?php echo $id; ?>')" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <a onclick="del('academic/core/drop_subject?id=<?php echo $id; ?>', 'Delete Subject?');" class="btn btn-sm btn-outline-danger" href="javascript:void(0);">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                    <?php if ($usage_count > 0): ?>
                                        <button class="btn btn-sm btn-outline-info view-details-btn" data-id="<?php echo $id; ?>">
                                            <i class="bi bi-info-circle"></i> Details
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    <script src="cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        function set_subject(id) {
            $('#edit_id').val(id);
            $('#edit_name').val($('#subject_'+id).val());
        }

        function del(url, msg) {
            if (confirm(msg)) window.location.href = url;
        }

        $(document).ready(function() {
            // DataTable with export buttons and page length options
            var table = $('#subjectsTable').DataTable({
                "order": [[1, "asc"]],
                "pageLength": 10,
                "lengthMenu": [[5, 10, 20, 50, 100, -1], [5, 10, 20, 50, 100, "All"]],
                "language": { 
                    "search": "Filter:",
                    "lengthMenu": "Show _MENU_ entries",
                    "paginate": {
                        "previous": "<i class='bi bi-chevron-left'></i>",
                        "next": "<i class='bi bi-chevron-right'></i>"
                    }
                },
                "dom": 'Bfrtip',
                "buttons": [
                    { extend: 'copy', text: '<i class="bi bi-files"></i> Copy' },
                    { extend: 'csv', text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV' },
                    { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel' },
                    { extend: 'pdf', text: '<i class="bi bi-file-earmark-pdf"></i> PDF' },
                    { extend: 'print', text: '<i class="bi bi-printer"></i> Print' }
                ]
            });

            // Filter cards functionality
            $('.filter-card').on('click', function() {
                var filter = $(this).data('filter');
                if (filter === 'used' || filter === 'unused') {
                    $('.filter-card').removeClass('active-filter');
                    $(this).addClass('active-filter');
                    if (filter === 'used') {
                        table.column(2).search('combination', true, false).draw();
                    } else if (filter === 'unused') {
                        table.column(2).search('Not used', true, false).draw();
                    }
                }
            });

            // Clear filter
            $('#clearFilterBtn').on('click', function() {
                $('.filter-card').removeClass('active-filter');
                table.column(2).search('').draw();
            });

            // Load subject details via AJAX
            function loadSubjectDetails(id) {
                $('#detailsModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');
                $('#detailsModal').modal('show');
                $.ajax({
                    url: 'academic/core/get_subject_combinations',
                    method: 'GET',
                    data: { subject_id: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            var html = '<div class="row mb-4 g-3">';
                            html += '<div class="col-md-4"><div class="summary-card"><i class="bi bi-person-badge"></i><div class="count">' + res.teacher_count + '</div><div class="label">Teachers</div></div></div>';
                            html += '<div class="col-md-4"><div class="summary-card"><i class="bi bi-people"></i><div class="count">' + res.student_count + '</div><div class="label">Students</div></div></div>';
                            html += '<div class="col-md-4"><div class="summary-card"><i class="bi bi-diagram-3"></i><div class="count">' + res.combination_count + '</div><div class="label">Combinations</div></div></div>';
                            html += '</div>';
                            html += '<h6 class="mb-3 fw-bold"><i class="bi bi-list-ul me-2"></i>Combinations using this subject:</h6>';
                            if (res.data.length === 0) {
                                html += '<p class="mb-0">No combinations found.</p>';
                            } else {
                                html += '<div class="combination-list">';
                                html += '<div class="list-group">';
                                $.each(res.data, function(i, item) {
                                    html += '<div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">' +
                                            '<span><i class="bi bi-book me-2 text-primary"></i><strong>' + item.subject_name + '</strong></span>' +
                                            '<span><i class="bi bi-person me-2 text-success"></i>' + item.teacher + '</span>' +
                                            '<span class="badge bg-primary">' + item.classes + '</span>' +
                                            '</div>';
                                });
                                html += '</div>';
                                html += '</div>';
                            }
                            $('#detailsModalBody').html(html);
                        } else {
                            $('#detailsModalBody').html('<div class="alert alert-danger">' + res.error + '</div>');
                        }
                    },
                    error: function() {
                        $('#detailsModalBody').html('<div class="alert alert-danger">Failed to load details.</div>');
                    }
                });
            }

            // Click on usage badge (inside table)
            $('#subjectsTable tbody').on('click', '.view-details', function() {
                var id = $(this).data('id');
                loadSubjectDetails(id);
            });

            // Click on details button
            $('#subjectsTable tbody').on('click', '.view-details-btn', function() {
                var id = $(this).data('id');
                loadSubjectDetails(id);
            });

            // Form validation
            $('#addForm').on('submit', function(e) {
                if ($(this).find('input[name="name"]').val().trim() === '') {
                    e.preventDefault();
                    Swal.fire('Error', 'Subject name cannot be empty.', 'error');
                }
            });
            $('#editForm').on('submit', function(e) {
                if ($(this).find('input[name="name"]').val().trim() === '') {
                    e.preventDefault();
                    Swal.fire('Error', 'Subject name cannot be empty.', 'error');
                }
            });
        });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>