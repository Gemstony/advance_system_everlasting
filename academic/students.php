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

// Get class ID from URL (safe)
$class_id = isset($_GET['class']) ? (int)$_GET['class'] : 0;

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all classes for the filter dropdown
    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
    $all_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no class selected, default to first class
    if ($class_id == 0 && !empty($all_classes)) {
        $class_id = $all_classes[0]['id'];
    }
    
    // Get class name
    $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        die("Class not found.");
    }
    $class_name = $class['name'];
    
    // Fetch students for this class
    $stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ? ORDER BY fname, lname");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_POST['ajax_action'];
        
        // Get student details
        if ($action === 'get_student') {
            $id = $_POST['student_id'];
            $stmt = $conn->prepare("SELECT * FROM tbl_students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student) {
                echo json_encode(['success' => true, 'data' => $student]);
            } else {
                throw new Exception('Student not found.');
            }
            exit;
        }
        
        // Update student
        if ($action === 'update_student') {
            $id = $_POST['student_id'];
            $fname = $_POST['fname'];
            $mname = $_POST['mname'];
            $lname = $_POST['lname'];
            $gender = $_POST['gender'];
            $email = $_POST['email'];
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
            
            $stmt = $conn->prepare("UPDATE tbl_students SET fname=?, mname=?, lname=?, gender=?, email=?, status=? WHERE id=?");
            $stmt->execute([$fname, $mname, $lname, $gender, $email, $status, $id]);
            if ($stmt->rowCount() > 0 || true) { // allow update even if no changes
                echo json_encode(['success' => true, 'message' => 'Student updated successfully.']);
            } else {
                echo json_encode(['success' => true, 'message' => 'No changes made.']);
            }
            exit;
        }
        
        // Toggle status
        if ($action === 'toggle_status') {
            $id = $_POST['student_id'];
            $stmt = $conn->prepare("SELECT status FROM tbl_students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch();
            if (!$student) {
                throw new Exception('Student not found.');
            }
            $newStatus = $student['status'] == 1 ? 0 : 1;
            $stmt = $conn->prepare("UPDATE tbl_students SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            echo json_encode(['success' => true, 'new_status' => $newStatus]);
            exit;
        }
        
        // Delete student
        if ($action === 'delete_student') {
            $id = $_POST['student_id'];
            $stmt = $conn->prepare("DELETE FROM tbl_students WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully.']);
            } else {
                throw new Exception('Student not found.');
            }
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>SRMS - Students</title>
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

        .class-filter {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .class-filter label {
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }
        .class-filter select {
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            padding: 0.4rem 1.2rem;
            background: white;
            font-weight: 500;
            color: #0f172a;
            transition: all 0.2s;
        }
        .class-filter select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.1);
            outline: none;
        }

        #studentsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            color: #1e293b;
            font-size: 0.92rem;
        }
        #studentsTable thead th {
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
        #studentsTable tbody tr {
            transition: background 0.15s;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        #studentsTable tbody tr:hover {
            background: rgba(67,97,238,0.04);
        }
        #studentsTable td {
            vertical-align: middle;
            padding: 0.8rem 0.6rem;
            color: #1e293b;
        }

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
        .action-btn.delete { background: linear-gradient(135deg, #ef476f, #dc2626); }

        /* Status toggle */
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

        /* Modals - all text black */
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
            color: #0f172a;
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
        .form-control, .form-select {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 0.75rem 1.2rem;
            color: #1e293b;
            font-weight: 500;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67,97,238,0.15);
            outline: none;
        }
        .btn-close {
            filter: none;
        }
        .text-muted {
            color: #475569 !important;
        }

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

        .status-badge {
            padding: 0.2rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        @media (max-width: 768px) {
            .app-content { padding: 1rem; }
            .tile-header { flex-direction: column; align-items: stretch; }
            .tile-actions { justify-content: flex-start; }
            .action-group { justify-content: flex-start; }
            .action-btn { font-size: 0.65rem; padding: 0.2rem 0.5rem; }
            .action-btn i { font-size: 0.75rem; }
            .class-filter { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <h1><i class="bi bi-people"></i> Students</h1>
            <ul class="app-breadcrumb breadcrumb">
                <li class="breadcrumb-item"><a href="academic/dashboard">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="academic/classes">Classes</a></li>
                <li class="breadcrumb-item active">Students</li>
            </ul>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-header">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h3 class="tile-title"><i class="bi bi-table"></i> Student List</h3>
                            <div class="class-filter">
                                <label for="classFilter"><i class="bi bi-building"></i> Class:</label>
                                <select id="classFilter" onchange="window.location.href='academic/students?class='+this.value">
                                    <?php foreach ($all_classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $class_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:1rem; padding:0.3rem 1rem;">
                                <?php echo count($students); ?> students
                            </span>
                        </div>
                        <div class="tile-actions">
                            <button class="btn btn-outline-glow" type="button" id="exportCsvBtn">
                                <i class="bi bi-file-earmark-excel"></i> Export CSV
                            </button>
                            <button class="btn btn-primary-glow" type="button" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="bi bi-plus-lg"></i> Add Student
                            </button>
                        </div>
                    </div>

                    <div class="tile-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th class="text-center" style="min-width: 240px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr data-id="<?php echo $student['id']; ?>">
                                            <td><?php echo htmlspecialchars($student['id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']); ?></td>
                                            <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <button class="toggle-status <?php echo $student['status'] == 1 ? 'active' : ''; ?>" 
                                                        data-id="<?php echo $student['id']; ?>" 
                                                        title="Toggle status"></button>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-group">
                                                    <button onclick="viewStudent('<?php echo $student['id']; ?>')" class="action-btn view" data-bs-toggle="modal" data-bs-target="#viewModal" title="View Student">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    <button onclick="editStudent('<?php echo $student['id']; ?>')" class="action-btn edit" data-bs-toggle="modal" data-bs-target="#editModal" title="Edit Student">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <button onclick="deleteStudent('<?php echo $student['id']; ?>')" class="action-btn delete" title="Delete Student">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
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

    <!-- ===== ADD MODAL ===== -->
    <div class="modal fade" id="addStudentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_student">
                        <input type="hidden" name="class" value="<?php echo $class_id; ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input required name="fname" class="form-control" type="text">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input name="mname" class="form-control" type="text">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name</label>
                                <input required name="lname" class="form-control" type="text">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select required name="gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input required name="email" class="form-control" type="email" placeholder="student@email.com">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Password</label>
                                <input required name="password" class="form-control" type="password" placeholder="Min 8 characters">
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="submit" value="1" class="btn btn-primary">Add Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== VIEW MODAL ===== -->
    <div class="modal fade" id="viewModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person me-2"></i>Student Details</h5>
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

    <!-- ===== EDIT MODAL ===== -->
    <div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="loader/waitMe.js"></script>
    <script src="js/sweetalert2@11.js"></script>
    <script src="js/forms.js"></script>
    <script src="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#studentsTable').DataTable({
                "order": [[1, "asc"]],
                "pageLength": 10,
                "language": { "search": "Filter students:" },
                "columnDefs": [
                    { "orderable": false, "targets": [5] }
                ]
            });

            // Export CSV
            $('#exportCsvBtn').on('click', function() {
                var csv = [];
                var headers = ['ID', 'Full Name', 'Gender', 'Email', 'Status'];
                csv.push(headers.join(','));

                var rows = table.rows({ search: 'applied' }).nodes();
                $(rows).each(function() {
                    var cells = $(this).find('td');
                    var rowData = [];
                    rowData.push('"' + cells.eq(0).text().trim() + '"');
                    rowData.push('"' + cells.eq(1).text().trim() + '"');
                    rowData.push('"' + cells.eq(2).text().trim() + '"');
                    rowData.push('"' + cells.eq(3).text().trim() + '"');
                    var statusText = cells.eq(4).find('.toggle-status').hasClass('active') ? 'Active' : 'Inactive';
                    rowData.push('"' + statusText + '"');
                    csv.push(rowData.join(','));
                });

                if (csv.length === 1) {
                    Swal.fire('No data', 'No students to export.', 'info');
                    return;
                }

                var blob = new Blob([csv.join('\n')], { type: 'text/csv' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'students_export.csv';
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
                    data: { ajax_action: 'toggle_status', student_id: id },
                    dataType: 'json',
                    beforeSend: function() {
                        btn.css('opacity', '0.6');
                    },
                    success: function(response) {
                        btn.css('opacity', '1');
                        if (response.success) {
                            btn.toggleClass('active', response.new_status == 1);
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

        // ===== VIEW STUDENT =====
        function viewStudent(id) {
            var modalBody = $('#viewModalBody');
            modalBody.html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { ajax_action: 'get_student', student_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var s = response.data;
                        var html = `
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="class-detail-row"><strong>Student ID</strong> <span>${s.id}</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="class-detail-row"><strong>Status</strong> <span>${s.status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</span></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="class-detail-row"><strong>First Name</strong> <span>${s.fname}</span></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="class-detail-row"><strong>Middle Name</strong> <span>${s.mname}</span></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="class-detail-row"><strong>Last Name</strong> <span>${s.lname}</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="class-detail-row"><strong>Gender</strong> <span>${s.gender}</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="class-detail-row"><strong>Email</strong> <span>${s.email}</span></div>
                                </div>
                            </div>
                        `;
                        modalBody.html(html);
                    } else {
                        modalBody.html(`<div class="alert alert-danger">${response.message}</div>`);
                    }
                },
                error: function() {
                    modalBody.html(`<div class="alert alert-danger">Failed to load student details.</div>`);
                }
            });
        }

        // ===== EDIT STUDENT =====
        function editStudent(id) {
            var modalBody = $('#editModalBody');
            modalBody.html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { ajax_action: 'get_student', student_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var s = response.data;
                        var html = `
                            <input type="hidden" id="edit_student_id" value="${s.id}">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">First Name</label>
                                    <input id="edit_fname" class="form-control" type="text" value="${s.fname}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input id="edit_mname" class="form-control" type="text" value="${s.mname}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Last Name</label>
                                    <input id="edit_lname" class="form-control" type="text" value="${s.lname}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select id="edit_gender" class="form-select">
                                        <option value="Male" ${s.gender == 'Male' ? 'selected' : ''}>Male</option>
                                        <option value="Female" ${s.gender == 'Female' ? 'selected' : ''}>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input id="edit_email" class="form-control" type="email" value="${s.email}">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Status</label>
                                    <select id="edit_status" class="form-select">
                                        <option value="1" ${s.status == 1 ? 'selected' : ''}>Active</option>
                                        <option value="0" ${s.status == 0 ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        modalBody.html(html);
                        // Store the student id for save
                        modalBody.data('student_id', id);
                    } else {
                        modalBody.html(`<div class="alert alert-danger">${response.message}</div>`);
                    }
                },
                error: function() {
                    modalBody.html(`<div class="alert alert-danger">Failed to load student data for editing.</div>`);
                }
            });
        }

        // Save edit
        $(document).on('click', '#saveEditBtn', function() {
            var modalBody = $('#editModalBody');
            var id = modalBody.data('student_id');
            if (!id) {
                Swal.fire('Error', 'No student loaded for editing.', 'error');
                return;
            }

            var fname = $('#edit_fname').val().trim();
            var mname = $('#edit_mname').val().trim();
            var lname = $('#edit_lname').val().trim();
            var gender = $('#edit_gender').val();
            var email = $('#edit_email').val().trim();
            var status = $('#edit_status').val();

            if (!fname || !lname || !email) {
                Swal.fire('Validation', 'Please fill in all required fields.', 'warning');
                return;
            }

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    ajax_action: 'update_student',
                    student_id: id,
                    fname: fname,
                    mname: mname,
                    lname: lname,
                    gender: gender,
                    email: email,
                    status: status
                },
                dataType: 'json',
                beforeSend: function() {
                    Swal.fire({ title: 'Updating...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Updated!', text: response.message, timer: 1500, showConfirmButton: false }).then(() => {
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
        });

        // ===== DELETE STUDENT =====
        function deleteStudent(id) {
            Swal.fire({
                title: 'Delete Student?',
                text: 'This action is permanent and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: { ajax_action: 'delete_student', student_id: id },
                        dataType: 'json',
                        beforeSend: function() {
                            Swal.fire({ title: 'Deleting...', text: 'Please wait', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, timer: 1500, showConfirmButton: false }).then(() => location.reload());
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
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>