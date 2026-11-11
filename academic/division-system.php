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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Division System</title>
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

        /* --- Primary Glow Button (Add Division) --- */
        .btn-primary-glow {
            background: linear-gradient(145deg, #4f6ef7, #3b52d4);
            border: none;
            border-radius: 60px;
            padding: 0.75rem 2rem;
            font-weight: 700;
            font-size: 0.95rem;
            color: #fff;
            box-shadow: 0 8px 20px -6px rgba(67,97,238,0.5), inset 0 1px 1px rgba(255,255,255,0.3);
            transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }

        .btn-primary-glow:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 14px 28px -8px rgba(67,97,238,0.6), inset 0 1px 1px rgba(255,255,255,0.4);
            background: linear-gradient(145deg, #5a78ff, #3f56d8);
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

        /* --- DataTable styling with visible row separation and glass header --- */
        #srmsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px; /* increased gap between rows for visible separation */
        }

        /* --- Glass morphism contrast for the header row --- */
        #srmsTable thead th {
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0a1a3a;
            padding: 1.2rem 0.75rem;
            border-bottom: 3px solid rgba(67, 97, 238, 0.5);
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-shadow: 0 1px 2px rgba(255,255,255,0.3);
        }

        /* Each header cell with a subtle glass border */
        #srmsTable thead th:not(:last-child) {
            border-right: 1px solid rgba(255,255,255,0.3);
        }

        /* --- Table rows with visible separators --- */
        #srmsTable tbody tr {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border-radius: 16px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            margin-bottom: 6px;
            border-bottom: 2px solid rgba(0,0,0,0.06); /* visible line between rows */
        }

        #srmsTable tbody tr:hover {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 8px 20px -8px rgba(0,0,0,0.12);
            transform: scale(1.002);
            border-bottom-color: rgba(67,97,238,0.2);
        }

        #srmsTable td {
            vertical-align: middle;
            padding: 0.9rem 0.75rem;
            border-bottom: none;
            font-size: 0.92rem;
            background: transparent;
        }

        /* Rounded corners for first and last cell in each row */
        #srmsTable tbody tr td:first-child {
            border-top-left-radius: 14px;
            border-bottom-left-radius: 14px;
        }

        #srmsTable tbody tr td:last-child {
            border-top-right-radius: 14px;
            border-bottom-right-radius: 14px;
        }

        /* --- Different colors for different sections (columns) --- */
        #srmsTable td:nth-child(1) { /* Division */
            color: #2563eb;
            font-weight: 700;
        }
        #srmsTable td:nth-child(2) { /* Min % */
            color: #16a34a;
            font-weight: 600;
        }
        #srmsTable td:nth-child(3) { /* Max % */
            color: #ea580c;
            font-weight: 600;
        }
        #srmsTable td:nth-child(4) { /* Min Point */
            color: #7c3aed;
            font-weight: 600;
        }
        #srmsTable td:nth-child(5) { /* Max Point */
            color: #dc2626;
            font-weight: 600;
        }
        #srmsTable td:nth-child(6) { /* Points */
            color: #0891b2;
            font-weight: 600;
        }
        #srmsTable td:nth-child(7) { /* Actions */
            color: #475569;
        }

        /* Ensure the links inside Actions column keep their button colors */
        #srmsTable td:nth-child(7) .btn {
            color: #fff !important;
        }

        /* --- Action Buttons (Edit / Delete) --- */
        .btn-sm {
            border-radius: 40px;
            padding: 0.45rem 1.1rem;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.2px;
        }

        .btn-sm:active {
            transform: scale(0.94);
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

        .btn-danger {
            background: linear-gradient(145deg, #ef5b7a, #d93c5c);
            color: white;
            box-shadow: 0 4px 12px -4px rgba(239,71,111,0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(145deg, #f26885, #dc3f60);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -6px rgba(239,71,111,0.4);
        }

        /* --- Modals – premium glass --- */
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 36px;
            color: #1e293b;
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.3), 0 8px 24px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding: 1.4rem 2rem;
            background: rgba(247, 249, 252, 0.5);
        }

        .modal-title {
            font-weight: 800;
            color: #1e293b;
            font-size: 1.4rem;
            letter-spacing: -0.3px;
        }

        .modal-title i {
            color: #4361ee;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.06);
            padding: 1.2rem 2rem;
            background: rgba(247, 249, 252, 0.3);
        }

        .form-label {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            letter-spacing: 0.2px;
        }

        .form-control {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 20px;
            padding: 0.75rem 1.2rem;
            color: #1e293b;
            font-size: 0.95rem;
            transition: border 0.2s, box-shadow 0.2s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        }

        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15), inset 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
        }

        .btn-close {
            filter: none;
            opacity: 0.7;
            transition: opacity 0.2s, transform 0.2s;
        }

        .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        .modal .btn-primary {
            border-radius: 40px;
            padding: 0.7rem 1.5rem;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 6px 16px -4px rgba(67,97,238,0.3);
        }

        .modal .btn-secondary {
            background: #f1f3f6;
            color: #1e293b;
            border: none;
            border-radius: 40px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            transition: background 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }

        .modal .btn-secondary:hover {
            background: #e2e6ed;
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
            background: linear-gradient(145deg, #ef5b7a, #d93c5c) !important;
            border-radius: 40px !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px -4px rgba(239,71,111,0.4) !important;
            border: none !important;
            transition: all 0.2s ease !important;
        }
        .swal2-popup-custom .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px -6px rgba(239,71,111,0.5) !important;
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

        /* --- DataTable wrapper adjustments --- */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 50px;
            padding: 0.6rem 1.2rem 0.6rem 2.6rem;
            background: #ffffff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 1rem center;
            background-size: 18px;
            border: 1px solid rgba(0,0,0,0.08);
            color: #1e293b;
            font-size: 0.9rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: border 0.2s, box-shadow 0.2s;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.12);
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 30px;
            padding: 0.3rem 1.8rem 0.3rem 0.8rem;
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.08);
            color: #1e293b;
            font-size: 0.9rem;
            appearance: auto;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .dataTables_wrapper .dataTables_info {
            color: #475569;
            font-size: 0.9rem;
            padding-top: 1rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: rgba(0,0,0,0.03);
            border-radius: 30px;
            color: #1e293b !important;
            border: none !important;
            padding: 0.3rem 1rem;
            margin: 0 2px;
            font-weight: 600;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(145deg, #4361ee, #2a46c0);
            color: white !important;
            box-shadow: 0 4px 12px -4px rgba(67,97,238,0.3);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: rgba(0,0,0,0.06);
            box-shadow: 0 4px 8px rgba(0,0,0,0.06);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(145deg, #4f6ef7, #2a46c0);
            box-shadow: 0 6px 16px -4px rgba(67,97,238,0.4);
        }

        /* --- Small responsive tweaks --- */
        @media (max-width: 768px) {
            .app-title h1 {
                font-size: 1.6rem;
                padding: 0.2rem 1.2rem;
            }
            .tile {
                padding: 1.2rem;
            }
            .btn-primary-glow {
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div><h1><i class="bi bi-grid-3x3-gap-fill me-2"></i> Division System</h1></div>
            <div>
                <button class="btn btn-primary-glow btn-shine" type="button" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-circle me-2"></i> Add Division
                </button>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="addModal" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i> Add Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="academic/core/new_div">
                            <div class="mb-3">
                                <label class="form-label">Division</label>
                                <input required type="text" name="div" class="form-control" placeholder="e.g., I, II, III">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Minimum %</label>
                                <input required type="number" name="min" class="form-control" placeholder="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Maximum %</label>
                                <input required type="number" name="max" class="form-control" placeholder="100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Min Point</label>
                                <input required type="number" name="min2" class="form-control" placeholder="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Max Point</label>
                                <input required type="number" name="max2" class="form-control" placeholder="35">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Points</label>
                                <input required type="number" name="points" class="form-control" placeholder="e.g., 5">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="submit" value="1" class="btn btn-primary w-100 btn-shine">Add Division</button>
                                <button type="button" class="btn btn-secondary w-100 btn-shine" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="academic/core/update_div">
                            <div class="mb-3">
                                <label class="form-label">Division</label>
                                <input id="division" required type="text" name="div" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Minimum %</label>
                                <input id="min" required type="number" name="min" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Maximum %</label>
                                <input id="max" required type="number" name="max" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Min Point</label>
                                <input id="min2" required type="number" name="min2" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Max Point</label>
                                <input id="max2" required type="number" name="max2" class="form-control">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Points</label>
                                <input id="points" required type="number" name="points" class="form-control">
                            </div>
                            <input type="hidden" name="id" id="id">
                            <div class="d-flex gap-2">
                                <button type="submit" name="submit" value="1" class="btn btn-primary w-100 btn-shine">Save Changes</button>
                                <button type="button" class="btn btn-secondary w-100 btn-shine" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-body">
                        <div class="table-responsive">
                            <h3 class="tile-title"><i class="bi bi-grid-1x2-fill me-2"></i> Division Records</h3>
                            <table class="table table-hover align-middle" id="srmsTable">
                                <thead>
                                    <tr>
                                        <th>Division</th>
                                        <th>Min %</th>
                                        <th>Max %</th>
                                        <th>Min Point</th>
                                        <th>Max Point</th>
                                        <th>Points</th>
                                        <th width="140">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->prepare("SELECT * FROM tbl_division_system");
                                    $stmt->execute();
                                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($result as $row) {
                                        ?>
                                        <textarea style="display:none;" id="division_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['division']); ?></textarea>
                                        <textarea style="display:none;" id="min_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['min']); ?></textarea>
                                        <textarea style="display:none;" id="max_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['max']); ?></textarea>
                                        <textarea style="display:none;" id="min2_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['min_point']); ?></textarea>
                                        <textarea style="display:none;" id="max2_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['max_point']); ?></textarea>
                                        <textarea style="display:none;" id="points_<?php echo $row['division']; ?>"><?php echo htmlspecialchars($row['points']); ?></textarea>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['division']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['min']); ?>%</td>
                                            <td><?php echo htmlspecialchars($row['max']); ?>%</td>
                                            <td><?php echo htmlspecialchars($row['min_point']); ?></td>
                                            <td><?php echo htmlspecialchars($row['max_point']); ?></td>
                                            <td><?php echo htmlspecialchars($row['points']); ?></td>
                                            <td class="text-nowrap">
                                                <a onclick="set_division('<?php echo htmlspecialchars($row['division']); ?>');" class="btn btn-primary btn-sm btn-shine" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil"></i> Edit</a>
                                                <a onclick="del('academic/core/drop_division?id=<?php echo urlencode($row['division']); ?>', 'Delete this division?');" class="btn btn-danger btn-sm btn-shine" href="javascript:void(0);"><i class="bi bi-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="7" class="text-center text-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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
    <script>
    $(document).ready(function() {
        $('#srmsTable').DataTable({
            responsive: true,
            pageLength: 10,
            ordering: false,
            language: {
                search: "Search Divisions:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ divisions",
                paginate: {
                    previous: "<i class='bi bi-chevron-left'></i>",
                    next: "<i class='bi bi-chevron-right'></i>"
                }
            }
        });
    });

    function set_division(division) {
        $('#id').val(division);
        $('#division').val($('#division_' + division).val());
        $('#min').val($('#min_' + division).val());
        $('#max').val($('#max_' + division).val());
        $('#min2').val($('#min2_' + division).val());
        $('#max2').val($('#max2_' + division).val());
        $('#points').val($('#points_' + division).val());
    }

    // Enhanced delete with SweetAlert2 pop-up
    function del(url, msg) {
        Swal.fire({
            title: 'Are you sure?',
            text: msg || "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            background: 'rgba(255,255,255,0.95)',
            backdrop: 'rgba(0,0,0,0.4)',
            customClass: {
                popup: 'swal2-popup-custom'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>