<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure 'status' column exists in tbl_classes (1=active, 0=archived)
    $stmt = $conn->query("SHOW COLUMNS FROM tbl_classes LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE tbl_classes ADD status TINYINT(1) NOT NULL DEFAULT 1");
    }
    
    // Fetch all classes with student count and status
    $stmt = $conn->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM tbl_students WHERE class = c.id) as student_count
        FROM tbl_classes c
        ORDER BY c.registration_date DESC
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $totalClasses = count($classes);
    $totalStudents = 0;
    $maxStudents = 0;
    $maxClass = '';
    $activeCount = 0;
    foreach ($classes as $c) {
        $totalStudents += $c['student_count'];
        if ($c['student_count'] > $maxStudents) {
            $maxStudents = $c['student_count'];
            $maxClass = $c['name'];
        }
        if ($c['status'] == 1) $activeCount++;
    }
    $avgStudents = $totalClasses > 0 ? round($totalStudents / $totalClasses, 1) : 0;
    $inactiveCount = $totalClasses - $activeCount;
    
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}

// Handle AJAX clone request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clone_id']) && isset($_POST['action']) && $_POST['action'] === 'clone_class') {
    header('Content-Type: application/json');
    try {
        $cloneId = (int)$_POST['clone_id'];
        $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
        $stmt->execute([$cloneId]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) {
            throw new Exception('Class not found.');
        }
        $newName = $orig['name'] . ' (Copy)';
        $stmt = $conn->prepare("INSERT INTO tbl_classes (name, registration_date, status) VALUES (?, NOW(), 1)");
        $stmt->execute([$newName]);
        $newId = $conn->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Class cloned successfully.', 'new_id' => $newId]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle toggle status AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id']) && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['toggle_id'];
        $stmt = $conn->prepare("SELECT status FROM tbl_classes WHERE id = ?");
        $stmt->execute([$id]);
        $class = $stmt->fetch();
        if (!$class) {
            throw new Exception('Class not found.');
        }
        $newStatus = $class['status'] == 1 ? 0 : 1;
        $stmt = $conn->prepare("UPDATE tbl_classes SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        echo json_encode(['success' => true, 'new_status' => $newStatus]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle bulk delete (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['ids'])) {
    header('Content-Type: application/json');
    try {
        $ids = $_POST['ids'];
        if (empty($ids)) {
            throw new Exception('No classes selected.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM tbl_classes WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "Deleted $deleted class(es)."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Classes</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4fb;
        }
        .app-content {
            background: transparent !important;
            padding: 1.5rem 2rem;
        }
        .app-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .app-title h1 {
            font-weight: 800;
            color: #0f172a;
            font-size: 1.8rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .app-title h1 i {
            color: #4361ee;
        }
        .breadcrumb {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(6px);
            border-radius: 40px;
            padding: 0.4rem 0.8rem;
            margin: 0;
            border: 1px solid rgba(255,255,255,0.6);
        }
        .breadcrumb-item a,
        .breadcrumb-item.active {
            color: #0f172a !important;
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb-item a:hover {
            color: #4361ee !important;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.82);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 1rem 1rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            border: 1px solid rgba(255,255,255,0.7);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #4361ee, #1e40af); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #047857); }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.red { background: linear-gradient(135deg, #ef476f, #dc2626); }
        .stat-info .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .stat-info .label {
            font-size: 0.75rem;
            color: #475569;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        /* Main Card */
        .tile {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.8rem 1.8rem 1.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border: 1px solid rgba(255,255,255,0.6);
            transition: box-shadow 0.3s ease;
        }
        .tile:hover {
            box-shadow: 0 16px 36px rgba(0,0,0,0.08);
        }
        .tile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .tile-title {
            font-weight: 800;
            font-size: 1.4rem;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tile-title i {
            color: #4361ee;
        }
        .tile-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .btn-primary-glow {
            background: linear-gradient(135deg, #4361ee, #3a56d4);
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.8rem;
            font-weight: 600;
            box-shadow: 0 6px 14px -4px rgba(67,97,238,0.4);
            transition: all 0.2s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -6px rgba(67,97,238,0.5);
            color: white;
        }
        .btn-outline-glow {
            background: transparent;
            border: 2px solid #4361ee;
            border-radius: 50px;
            padding: 0.5rem 1.6rem;
            font-weight: 600;
            color: #4361ee;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-outline-glow:hover {
            background: #4361ee;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px rgba(67,97,238,0.3);
        }

        /* Table */
        #srmsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: #1e293b;
            font-size: 0.92rem;
        }
        #srmsTable thead th {
            background: rgba(241,245,249,0.7);
            backdrop-filter: blur(4px);
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            padding: 0.9rem 0.6rem;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        #srmsTable tbody tr {
            transition: background 0.15s;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        #srmsTable tbody tr:hover {
            background: rgba(67,97,238,0.04);
        }
        #srmsTable td {
            vertical-align: middle;
            padding: 0.8rem 0.6rem;
            color: #1e293b;
        }
        .badge-student {
            background: rgba(67,97,238,0.12);
            color: #4361ee;
            font-weight: 600;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.85rem;
        }
        .status-badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-archived { background: #f8d7da; color: #721c24; }

        /* Action Buttons - redesigned with labels */
        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            justify-content: center;
        }
        .action-btn {
            border-radius: 30px;
            border: none;
            padding: 0.3rem 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            color: white;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            white-space: nowrap;
        }
        .action-btn i {
            font-size: 0.85rem;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
            color: white;
        }
        .action-btn.view { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .action-btn.edit { background: linear-gradient(135deg, #4361ee, #1e40af); }
        .action-btn.report { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
        .action-btn.manage { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .action-btn.export { background: linear-gradient(135deg, #10b981, #047857); }
        .action-btn.clone { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .action-btn.delete { background: linear-gradient(135deg, #ef476f, #dc2626); }

        /* Toggle switch for status */
        .toggle-status {
            width: 40px;
            height: 22px;
            background: #ccc;
            border-radius: 40px;
            display: inline-block;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
            border: none;
            padding: 0;
        }
        .toggle-status::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle-status.active {
            background: #10b981;
        }
        .toggle-status.active::after {
            transform: translateX(18px);
        }

        /* Modals */
        .modal-content {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 28px;
            color: #1e293b;
            box-shadow: 0 24px 48px rgba(0,0,0,0.1);
        }
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding: 1.5rem 1.5rem 0.5rem;
        }
        .modal-title {
            font-weight: 800;
            color: #0f172a;
        }
        .modal-body {
            padding: 1.5rem;
            color: #0f172a; /* All text black */
        }
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.06);
            padding: 1rem 1.5rem 1.5rem;
        }
        .form-label {
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-control {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 0.75rem 1.2rem;
            color: #1e293b;
            font-weight: 500;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
            outline: none;
        }
        .btn-close {
            filter: none;
        }

        /* View Modal details - all text black */
        .class-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            color: #0f172a;
        }
        .class-detail-row strong {
            color: #0f172a;
            font-weight: 700;
        }
        .class-detail-row span {
            color: #0f172a;
            font-weight: 500;
        }
        .student-list-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            color: #0f172a;
        }
        .student-list-item .badge {
            background: rgba(0,0,0,0.06) !important;
            color: #0f172a !important;
            font-weight: 600;
        }
        .subject-tag {
            background: rgba(67,97,238,0.1);
            color: #0f172a;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            margin: 0.2rem 0.2rem;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .modal-body hr {
            border-color: rgba(0,0,0,0.08);
        }
        .modal-body h6 {
            color: #0f172a;
            font-weight: 700;
        }
        .text-muted {
            color: #475569 !important;
        }

        /* DataTable */
        .dataTables_wrapper .dataTables_filter input {
            background: white;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50px;
            padding: 0.5rem 1rem 0.5rem 2.2rem;
            color: #1e293b;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #1e293b !important;
            background: rgba(0,0,0,0.04);
            border-radius: 30px;
            border: none !important;
            margin: 0 3px;
            padding: 0.3rem 0.8rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee !important;
            color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(0,0,0,0.1) !important;
            color: #1e293b !important;
        }

        /* Bulk delete toolbar */
        .bulk-toolbar {
            display: none;
            background: #eef2ff;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }
        .bulk-toolbar.show {
            display: flex;
        }
        .bulk-toolbar .bulk-count {
            font-weight: 600;
            color: #1e293b;
        }

        @media (max-width: 768px) {
            .app-content { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .tile-header { flex-direction: column; align-items: stretch; }
            .tile-actions { justify-content: flex-start; }
            .action-group { justify-content: flex-start; }
            .action-btn { font-size: 0.65rem; padding: 0.2rem 0.5rem; }
            .action-btn i { font-size: 0.75rem; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .app-title { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <!-- Title & Breadcrumb -->
        <div class="app-title">
            <h1><i class="bi bi-building"></i> Classes</h1>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><a href="academic/dashboard">Dashboard</a></li>
                <li class="breadcrumb-item active">Classes</li>
            </ul>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-grid-3x3-gap-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $totalClasses; ?></div>
                    <div class="label">Total Classes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $totalStudents; ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="bi bi-bar-chart-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $avgStudents; ?></div>
                    <div class="label">Avg Students / Class</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-trophy-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $maxStudents; ?></div>
                    <div class="label">Largest Class</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $activeCount; ?></div>
                    <div class="label">Active</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-archive-fill"></i></div>
                <div class="stat-info">
                    <div class="number"><?php echo $inactiveCount; ?></div>
                    <div class="label">Archived</div>
                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Class</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_class">
                            <div class="mb-3">
                                <label class="form-label">Class Name</label>
                                <input required name="name" class="form-control" type="text" placeholder="e.g., Form One Science 2026">
                            </div>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit" value="1" class="btn btn-primary">Add Class</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Class</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/update_class">
                            <div class="mb-3">
                                <label class="form-label">Class Name</label>
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

        <!-- View Modal -->
        <div class="modal fade" id="viewModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Class Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="viewModalBody">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Delete Confirmation Modal -->
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Confirm Bulk Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to delete <strong id="bulkCountDisplay">0</strong> class(es). This action is permanent and cannot be undone.</p>
                        <p class="text-muted">Are you sure you want to proceed?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmBulkDelete">Delete Permanently</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-header">
                        <h3 class="tile-title"><i class="bi bi-table"></i> Class List</h3>
                        <div class="tile-actions">
                            <button class="btn btn-outline-glow" type="button" id="exportCsvBtn">
                                <i class="bi bi-file-earmark-excel"></i> Export CSV
                            </button>
                            <button class="btn btn-primary-glow" type="button" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="bi bi-plus-lg"></i> Add Class
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Action Toolbar -->
                    <div class="bulk-toolbar" id="bulkToolbar">
                        <span class="bulk-count" id="bulkSelectedCount">0 selected</span>
                        <button class="btn btn-danger btn-sm" id="bulkDeleteBtn"><i class="bi bi-trash"></i> Delete Selected</button>
                        <button class="btn btn-secondary btn-sm" id="bulkClearBtn"><i class="bi bi-x"></i> Clear</button>
                    </div>

                    <div class="tile-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="srmsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" title="Select all">
                                        </th>
                                        <th>Class Name</th>
                                        <th>Registration Date</th>
                                        <th class="text-center">Students</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center" style="min-width: 320px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr data-id="<?php echo $class['id']; ?>" data-status="<?php echo $class['status']; ?>">
                                            <td><input type="checkbox" class="row-checkbox" value="<?php echo $class['id']; ?>"></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($class['name']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($class['registration_date'])); ?></td>
                                            <td class="text-center">
                                                <span class="badge-student"><?php echo $class['student_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <button class="toggle-status <?php echo $class['status'] == 1 ? 'active' : ''; ?>" 
                                                        data-id="<?php echo $class['id']; ?>" 
                                                        title="Toggle active status"></button>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-group">
                                                    <!-- View -->
                                                    <a onclick="viewClass('<?php echo $class['id']; ?>')" class="action-btn view" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#viewModal" title="View Details">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <!-- Edit -->
                                                    <a onclick="setEdit('<?php echo $class['id']; ?>', '<?php echo htmlspecialchars($class['name'], ENT_QUOTES); ?>')" class="action-btn edit" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal" title="Edit Class">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <!-- Quick Report -->
                                                    <a href="academic/report?class=<?php echo $class['id']; ?>" class="action-btn report" title="Class Report">
                                                        <i class="bi bi-file-text"></i> Report
                                                    </a>
                                                    <!-- Manage Students - safe link -->
                                                    <a href="academic/students?class=<?php echo urlencode($class['id']); ?>" class="action-btn manage" title="Manage Students">
                                                        <i class="bi bi-people"></i> Students
                                                    </a>
                                                    <!-- Export Students -->
                                                    <a href="academic/export_students?class=<?php echo urlencode($class['id']); ?>" class="action-btn export" title="Export Students (CSV)">
                                                        <i class="bi bi-download"></i> Export
                                                    </a>
                                                    <!-- Clone -->
                                                    <a onclick="cloneClass('<?php echo $class['id']; ?>')" class="action-btn clone" href="javascript:void(0);" title="Clone Class">
                                                        <i class="bi bi-copy"></i> Clone
                                                    </a>
                                                    <!-- Delete -->
                                                    <a onclick="del('academic/core/drop_class?id=<?php echo $class['id']; ?>', 'Delete this class?')" class="action-btn delete" href="javascript:void(0);" title="Delete Class">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#srmsTable').DataTable({
                "order": [[2, "desc"]],
                "pageLength": 10,
                "language": { "search": "Filter classes:" },
                "columnDefs": [
                    { "orderable": false, "targets": [0, 4, 5] },
                    { "width": "40px", "targets": 0 },
                    { "width": "auto", "targets": [1,2] },
                    { "width": "80px", "targets": 3 },
                    { "width": "100px", "targets": 4 },
                    { "width": "320px", "targets": 5 }
                ]
            });

            // Handle select all checkbox
            $('#selectAll').on('change', function() {
                var checked = $(this).prop('checked');
                $('.row-checkbox').prop('checked', checked);
                updateBulkToolbar();
            });

            // Individual row checkbox change
            $('#srmsTable tbody').on('change', '.row-checkbox', function() {
                updateBulkToolbar();
                var allChecked = $('.row-checkbox:checked').length === $('.row-checkbox').length;
                $('#selectAll').prop('checked', allChecked);
            });

            // Update bulk toolbar visibility and count
            function updateBulkToolbar() {
                var checked = $('.row-checkbox:checked');
                var count = checked.length;
                if (count > 0) {
                    $('#bulkToolbar').addClass('show');
                    $('#bulkSelectedCount').text(count + ' selected');
                } else {
                    $('#bulkToolbar').removeClass('show');
                }
            }

            // Bulk delete button
            $('#bulkDeleteBtn').on('click', function() {
                var ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();
                if (ids.length === 0) return;
                $('#bulkCountDisplay').text(ids.length);
                $('#bulkDeleteModal').modal('show');
                $('#confirmBulkDelete').data('ids', ids);
            });

            // Confirm bulk delete
            $('#confirmBulkDelete').on('click', function() {
                var ids = $(this).data('ids');
                if (!ids || ids.length === 0) return;
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { bulk_delete: 1, ids: ids },
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.fire({ title: 'Deleting...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    },
                    success: function(response) {
                        $('#bulkDeleteModal').modal('hide');
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, timer: 2000, showConfirmButton: false }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        $('#bulkDeleteModal').modal('hide');
                        Swal.fire('Error', 'Server error', 'error');
                    }
                });
            });

            // Clear selections
            $('#bulkClearBtn').on('click', function() {
                $('.row-checkbox').prop('checked', false);
                $('#selectAll').prop('checked', false);
                updateBulkToolbar();
            });

            // Export CSV
            $('#exportCsvBtn').on('click', function() {
                var csv = [];
                var headers = ['Class Name', 'Registration Date', 'Students', 'Status'];
                csv.push(headers.join(','));

                var rows = table.rows({ search: 'applied' }).nodes();
                $(rows).each(function() {
                    var cells = $(this).find('td');
                    var rowData = [];
                    rowData.push('"' + cells.eq(1).text().trim() + '"');
                    rowData.push('"' + cells.eq(2).text().trim() + '"');
                    rowData.push('"' + cells.eq(3).text().trim() + '"');
                    var statusText = cells.eq(4).find('.toggle-status').hasClass('active') ? 'Active' : 'Archived';
                    rowData.push('"' + statusText + '"');
                    csv.push(rowData.join(','));
                });

                if (csv.length === 1) {
                    Swal.fire('No data', 'No classes to export.', 'info');
                    return;
                }

                var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'classes_export.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            });

            // Toggle status
            $(document).on('click', '.toggle-status', function() {
                var btn = $(this);
                var id = btn.data('id');
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: { action: 'toggle_status', toggle_id: id },
                    dataType: 'json',
                    beforeSend: function() {
                        btn.css('opacity', '0.6');
                    },
                    success: function(response) {
                        btn.css('opacity', '1');
                        if (response.success) {
                            btn.toggleClass('active', response.new_status == 1);
                            btn.closest('tr').data('status', response.new_status);
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        btn.css('opacity', '1');
                        Swal.fire('Error', 'Server error', 'error');
                    }
                });
            });
        });

        // Helper functions
        function setEdit(id, name) {
            $('#edit_id').val(id);
            $('#edit_name').val(name);
        }

        function del(url, msg) {
            if (confirm(msg)) window.location.href = url;
        }

        // Clone class
        function cloneClass(id) {
            Swal.fire({
                title: 'Clone Class?',
                text: 'This will create a new class with the same name (appended " (Copy)").',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, clone it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: { action: 'clone_class', clone_id: id },
                        dataType: 'json',
                        beforeSend: function() {
                            Swal.fire({ title: 'Cloning...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Cloned!', text: response.message, timer: 2000, showConfirmButton: false }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Server error', 'error');
                        }
                    });
                }
            });
        }

        // View Class Details (enhanced with performance summary)
        function viewClass(classId) {
            var modalBody = $('#viewModalBody');
            modalBody.html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);

            $.ajax({
                url: 'academic/core/get_class_details',
                type: 'POST',
                data: { id: classId },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        var html = `
                            <div class="mb-3">
                                <div class="class-detail-row">
                                    <strong>Class Name</strong>
                                    <span>${data.class_name}</span>
                                </div>
                                <div class="class-detail-row">
                                    <strong>Registration Date</strong>
                                    <span>${data.reg_date}</span>
                                </div>
                                <div class="class-detail-row">
                                    <strong>Total Students</strong>
                                    <span>${data.student_count}</span>
                                </div>
                                <div class="class-detail-row">
                                    <strong>Status</strong>
                                    <span>${data.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Archived</span>'}</span>
                                </div>
                            </div>
                            <hr>
                            <h6 class="fw-bold mb-2"><i class="bi bi-people"></i> Students</h6>
                            <div style="max-height:200px; overflow-y:auto;">
                                ${data.students.length > 0 ? data.students.map(s => `
                                    <div class="student-list-item">
                                        <i class="bi bi-person-circle text-secondary"></i>
                                        <span>${s.fname} ${s.mname} ${s.lname}</span>
                                        <span class="badge bg-light text-dark ms-2">${s.id}</span>
                                    </div>
                                `).join('') : '<p class="text-muted">No students enrolled.</p>'}
                            </div>
                            <hr>
                            <h6 class="fw-bold mb-2"><i class="bi bi-book"></i> Subjects Offered</h6>
                            <div>
                                ${data.subjects.length > 0 ? data.subjects.map(sub => `
                                    <span class="subject-tag">${sub}</span>
                                `).join('') : '<span class="text-muted">No subjects assigned.</span>'}
                            </div>
                            <hr>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="academic/students?class=${classId}" class="btn btn-primary btn-sm"><i class="bi bi-people"></i> Manage Students</a>
                                <a href="academic/report?class=${classId}" class="btn btn-info btn-sm"><i class="bi bi-file-text"></i> Class Report</a>
                                <a href="academic/class_dashboard?id=${classId}" class="btn btn-success btn-sm"><i class="bi bi-speedometer2"></i> Class Dashboard</a>
                            </div>
                        `;
                        modalBody.html(html);
                    } else {
                        modalBody.html(`<div class="alert alert-danger">${data.message}</div>`);
                    }
                },
                error: function(xhr, status, error) {
                    var errMsg = 'An error occurred while loading class details.';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.message) errMsg = resp.message;
                    } catch(e) {}
                    modalBody.html(`<div class="alert alert-danger">${errMsg}</div>`);
                    console.error('AJAX Error:', xhr.responseText);
                }
            });
        }
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>