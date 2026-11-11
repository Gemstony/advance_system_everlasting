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

// Handle AJAX for approve/deny
if (isset($_POST['action']) && in_array($_POST['action'], ['approve', 'deny'])) {
    header('Content-Type: application/json');
    try {
        $request_id = (int)$_POST['request_id'];
        if (!$request_id) throw new Exception('Invalid request ID');

        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM tbl_result_change_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new Exception('Request not found or already processed');

        if ($_POST['action'] === 'approve') {
            $stmt = $pdo->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND term = ? AND subject_combination = ?");
            $stmt->execute([
                $request['requested_score'],
                $request['student_id'],
                $request['class_id'],
                $request['term_id'],
                $request['subject_combination_id']
            ]);
            $status = 'approved';
        } else {
            $status = 'denied';
        }

        $stmt = $pdo->prepare("UPDATE tbl_result_change_requests SET status = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->execute([$status, $account_id, $request_id]);

        echo json_encode(['success' => true, 'message' => 'Request ' . $status]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Normal page display – fetch all pending requests
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT r.id, r.requested_score, r.current_score, r.reason, r.requested_at,
               s.fname, s.mname, s.lname, s.id AS student_id,
               subj.name AS subject_name,
               t.name AS term_name,
               c.name AS class_name,
               CONCAT(tch.fname, ' ', tch.lname) AS teacher_name
        FROM tbl_result_change_requests r
        LEFT JOIN tbl_students s ON r.student_id = s.id
        LEFT JOIN tbl_subject_combinations sc ON r.subject_combination_id = sc.id
        LEFT JOIN tbl_subjects subj ON sc.subject = subj.id
        LEFT JOIN tbl_terms t ON r.term_id = t.id
        LEFT JOIN tbl_classes c ON r.class_id = c.id
        LEFT JOIN tbl_staff tch ON r.teacher_id = tch.id
        WHERE r.status = 'pending'
        ORDER BY r.requested_at DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Change Requests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ========== GLOBAL – LEAVE HEADER THEME UNTOUCHED ========== */
        body {
            background: transparent;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .app-content {
            background: transparent !important;
        }
        
        /* ========== GLASS TILE ========== */
        .tile {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 1.8rem;
            position: relative;
            overflow: hidden;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        
        /* Table styling – dark text on light glass */
        .table {
            color: #1e293b;
        }
        .table thead th {
            background: rgba(241,245,249,0.7);
            backdrop-filter: blur(4px);
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 0.85rem;
        }
        .table tbody tr {
            transition: background 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(0,0,0,0.02);
        }
        .table td {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
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
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -6px rgba(0,0,0,0.15);
        }
        .btn-approve {
            background: linear-gradient(135deg, #06d6a0, #059669);
            color: white;
        }
        .btn-deny {
            background: linear-gradient(135deg, #ef476f, #dc2626);
            color: white;
        }
        
        /* Alert styling */
        .alert-info {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 60px;
            color: #1e293b;
        }
        
        /* DataTable wrapper */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 50px;
            padding: 0.5rem 1rem 0.5rem 2.2rem;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 0.8rem center;
            background-size: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            color: #1e293b;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 30px;
            padding: 0.3rem 1.5rem 0.3rem 0.8rem;
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            color: #1e293b;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: #1e293b;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: rgba(0,0,0,0.05);
            border-radius: 30px;
            color: #1e293b !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee;
            color: white !important;
        }
        
        /* Title – keep white for header consistency */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
        }
        
        /* Breadcrumb */
        .breadcrumb-item a, .breadcrumb-item.active {
            color: white !important;
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
            <div><h1><i class="bi bi-arrow-repeat me-2"></i> Change Requests</h1></div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="alert alert-info text-center py-4">No pending change requests.</div>
        <?php else: ?>
            <div class="tile">
                <div class="tile-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="requestsTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Term</th>
                                    <th>Current Score</th>
                                    <th>Requested Score</th>
                                    <th>Reason</th>
                                    <th>Teacher</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req):
                                    $fullname = htmlspecialchars($req['fname'] . ' ' . $req['mname'] . ' ' . $req['lname']);
                                    $subject = htmlspecialchars($req['subject_name']);
                                    $class = htmlspecialchars($req['class_name']);
                                    $term = htmlspecialchars($req['term_name']);
                                    $current = $req['current_score'];
                                    $requested = $req['requested_score'];
                                    $reason = nl2br(htmlspecialchars($req['reason']));
                                    $teacher = htmlspecialchars($req['teacher_name']);
                                    $date = date('d/m/Y H:i', strtotime($req['requested_at']));
                                ?>
                                <tr data-id="<?php echo $req['id']; ?>">
                                    <td><?php echo $fullname; ?></td>
                                    <td><?php echo $subject; ?></span>
                                    <td><?php echo $class; ?></span>
                                    <td><?php echo $term; ?></span>
                                    <td><?php echo $current; ?>%</span>
                                    <td><?php echo $requested; ?>%</span>
                                    <td class="text-wrap" style="max-width: 200px;"><?php echo $reason; ?></span>
                                    <td><?php echo $teacher; ?></span>
                                    <td><?php echo $date; ?></span>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-approve approve-btn" data-id="<?php echo $req['id']; ?>"><i class="bi bi-check-lg"></i> Approve</button>
                                        <button class="btn btn-sm btn-deny deny-btn" data-id="<?php echo $req['id']; ?>"><i class="bi bi-x-lg"></i> Deny</button>
                                    </span>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="loader/waitMe.js"></script>
    <script src="js/sweetalert2@11.js"></script>
    <script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>

    <script>
    $(document).ready(function(){
        var table = $('#requestsTable').DataTable({
            pageLength: 25,
            lengthMenu: [10,25,50,100],
            order: [[8, 'desc']],
            columnDefs: [{ orderable: false, targets: [6,9] }],
            language: {
                search: "Filter:",
                paginate: {
                    previous: "<i class='bi bi-chevron-left'></i>",
                    next: "<i class='bi bi-chevron-right'></i>"
                }
            }
        });

        // Approve request
        $('.approve-btn').on('click', function(){
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            Swal.fire({
                title: 'Approve Request?',
                text: 'This will permanently change the student\'s score.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#06d6a0',
                cancelButtonColor: '#ef476f',
                confirmButtonText: 'Yes, approve'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '',
                        method: 'POST',
                        data: { action: 'approve', request_id: id },
                        dataType: 'json',
                        beforeSend: function() {
                            $row.find('.approve-btn, .deny-btn').prop('disabled', true);
                        },
                        success: function(res) {
                            if (res.success) {
                                table.row($row).remove().draw();
                                Swal.fire('Approved!', 'Score has been updated.', 'success');
                            } else {
                                Swal.fire('Error', res.error, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire('AJAX Error', error, 'error');
                        },
                        complete: function() {
                            $row.find('.approve-btn, .deny-btn').prop('disabled', false);
                        }
                    });
                }
            });
        });

        // Deny request
        $('.deny-btn').on('click', function(){
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            Swal.fire({
                title: 'Deny Request?',
                text: 'This will reject the request. No changes will be made.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef476f',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, deny'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '',
                        method: 'POST',
                        data: { action: 'deny', request_id: id },
                        dataType: 'json',
                        beforeSend: function() {
                            $row.find('.approve-btn, .deny-btn').prop('disabled', true);
                        },
                        success: function(res) {
                            if (res.success) {
                                table.row($row).remove().draw();
                                Swal.fire('Denied', 'Request has been denied.', 'success');
                            } else {
                                Swal.fire('Error', res.error, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire('AJAX Error', error, 'error');
                        },
                        complete: function() {
                            $row.find('.approve-btn, .deny-btn').prop('disabled', false);
                        }
                    });
                }
            });
        });
    });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>