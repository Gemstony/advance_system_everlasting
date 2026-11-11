<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Admin only (level 0)
if ($res != "1" || $level != "0") {
    header("location:../");
    exit;
}

try {
    $pdo = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total_teachers = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
    $active_teachers = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2 AND status = 1")->fetchColumn();
    $inactive_teachers = $total_teachers - $active_teachers;

    // Subject assignments per teacher
    $assignments = $pdo->query("
        SELECT sc.teacher, s.name as subject_name
        FROM tbl_subject_combinations sc
        JOIN tbl_subjects s ON sc.subject = s.id
    ")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $total_teachers = $active_teachers = $inactive_teachers = 0;
    $assignments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Teacher Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e0e7ff;
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --dark: #1e293b;
            --light-bg: #f8fafc;
            --border-radius: 24px;
            --card-shadow: 0 20px 35px -8px rgba(0,0,0,0.1), 0 10px 15px -6px rgba(0,0,0,0.05);
            --transition: all 0.2s ease;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.4);
        }
        body {
            background: linear-gradient(145deg, #f0f4fa 0%, #e6ecf4 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        .app-content { background: transparent; }

        /* Glassmorphic stat cards */
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            box-shadow: var(--card-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 40px -10px rgba(0,0,0,0.15);
            background: rgba(255,255,255,0.95);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
            color: var(--dark);
        }
        .stat-label {
            color: #5b6e8c;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
        }

        /* Glass tile with animated gradient border */
        .tile {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .tile::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--border-radius);
            padding: 2px;
            background: linear-gradient(135deg, #4361ee, #06d6a0, #ef476f, #ffb703);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            animation: gradientShift 6s ease infinite;
            background-size: 200% 200%;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .tile:hover {
            box-shadow: 0 20px 35px -8px rgba(0,0,0,0.15);
        }
        .tile-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* Enhanced search bar */
        .table-search-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.5rem;
        }
        .table-search-wrapper .input-group {
            width: 300px;
            background: rgba(255,255,255,0.6);
            border-radius: 60px;
            overflow: hidden;
            backdrop-filter: blur(4px);
        }
        .table-search-wrapper .form-control {
            background: transparent;
            border: none;
            padding: 0.6rem 1.2rem;
        }
        .table-search-wrapper .input-group-text {
            background: transparent;
            border: none;
            color: var(--primary);
        }

        /* Smaller buttons */
        .btn-sm-custom {
            padding: 0.2rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 40px;
        }

        /* Toggle switch for status */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ef476f;
            transition: 0.3s;
            border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #06d6a0;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .status-label {
            font-size: 0.7rem;
            margin-left: 0.5rem;
            vertical-align: middle;
        }

        /* Badges for subjects */
        .badge-subject {
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.3rem 0.8rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.2rem;
            display: inline-block;
        }

        /* Table styling */
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(4px);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            border-bottom: 2px solid rgba(255,255,255,0.8);
        }
        .table tbody tr {
            transition: 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(255,255,255,0.6);
            transform: scale(1.01);
        }

        /* Modals */
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 32px;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.5);
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px;
            padding: 0.6rem 1.2rem;
        }
        .form-control:focus, .form-select:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
        }

        /* DataTable override */
        .dataTables_filter {
            display: none;
        }
        @media (max-width: 768px) {
            .table-search-wrapper .input-group {
                width: 100%;
            }
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1><i class="bi bi-person-badge me-2" style="color: var(--primary);"></i> Teacher Management</h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><button class="btn btn-glass btn-glass-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-circle me-1"></i> Add Teacher</button></li>
            <li class="breadcrumb-item"><button class="btn btn-glass btn-glass-success" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload me-1"></i> Import</button></li>
        </ul>
    </div>

    <!-- Stats Cards with colorful icons -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--primary); background: rgba(67,97,238,0.12);"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_teachers; ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success); background: rgba(6,214,160,0.12);"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-value"><?php echo $active_teachers; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--danger); background: rgba(239,71,111,0.12);"><i class="bi bi-x-circle-fill"></i></div>
                <div>
                    <div class="stat-value"><?php echo $inactive_teachers; ?></div>
                    <div class="stat-label">Inactive</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher List Tile with Gradient Border and Search -->
    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h3 class="tile-title mb-0"><i class="bi bi-table me-2"></i> Teacher List</h3>
                    <div class="table-search-wrapper">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="customSearchInput" class="form-control" placeholder="Search by name, email...">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="srmsTable">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Gender</th>
                                <th>Subjects</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        try {
                            $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level = 2 ORDER BY fname");
                            $stmt->execute();
                            $result = $stmt->fetchAll();

                            foreach($result as $row) {
                                $teacher_id = $row['id'];
                                $subject_list = isset($assignments[$teacher_id]) 
                                    ? implode(', ', array_map('htmlspecialchars', $assignments[$teacher_id]))
                                    : '—';
                                ?>
                                <tr>
                                    <td><i class="bi bi-person-circle me-1 text-primary"></i> <?php echo htmlspecialchars($row['fname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lname']); ?></td>
                                    <td><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><i class="bi bi-gender-<?php echo strtolower($row['gender']) == 'male' ? 'male' : 'female'; ?> me-1"></i> <?php echo htmlspecialchars($row['gender']); ?></td>
                                    <td>
                                        <?php 
                                        if ($subject_list !== '—') {
                                            $subjects_array = explode(', ', $subject_list);
                                            foreach ($subjects_array as $subj) {
                                                echo '<span class="badge-subject">' . $subj . '</span> ';
                                            }
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" class="status-toggle" data-id="<?php echo $row['id']; ?>" <?php echo $row['status'] == 1 ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <span class="status-label"><?php echo $row['status'] == 1 ? 'Active' : 'Blocked'; ?></span>
                                    </td>
                                    <td>
                                        <textarea style="display:none;" id="fname_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['fname']); ?></textarea>
                                        <textarea style="display:none;" id="lname_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['lname']); ?></textarea>
                                        <textarea style="display:none;" id="email_<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['email']); ?></textarea>
                                        <button onclick="set_user('<?php echo $row['id']; ?>', '<?php echo $row['gender']; ?>', '<?php echo $row['status']; ?>');" 
                                                data-bs-toggle="modal" data-bs-target="#editModal" 
                                                class="btn btn-sm-custom btn-glass btn-glass-primary me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <a onclick="del('admin/core/drop_user2?id=<?php echo $row['id']; ?>', 'Delete Teacher?');" 
                                           href="javascript:void(0);" 
                                           class="btn btn-sm-custom btn-glass" style="background: var(--danger); color: white;">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } catch(PDOException $e) {
                            echo '<tr><td colspan="7" class="text-danger">Error loading teachers: ' . htmlspecialchars($e->getMessage()) . '</td></td>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Add Modal -->
<div class="modal fade" id="addModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i> Add Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/new_user2">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input required name="email" class="form-control" type="email" placeholder="Enter email address">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="npass" name="password" placeholder="***************">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="cnpass" placeholder="***************">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender" required>
                            <option selected disabled value="">Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option selected disabled value="">Select status</option>
                            <option value="1">Active</option>
                            <option value="0">Blocked</option>
                        </select>
                    </div>
                    <button type="submit" name="submit" value="1" class="btn btn-glass btn-glass-primary w-100">Add Teacher</button>
                    <button type="button" class="btn btn-glass w-100 mt-2" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/update_user2">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input id="fname" required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input id="lname" required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input id="email" required name="email" class="form-control" type="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select id="gender" class="form-select" name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="status" class="form-select" name="status" required>
                            <option value="1">Active</option>
                            <option value="0">Blocked</option>
                        </select>
                    </div>
                    <input type="hidden" name="id" id="id">
                    <button type="submit" name="submit" value="1" class="btn btn-glass btn-glass-primary w-100">Save Changes</button>
                    <button type="button" class="btn btn-glass w-100 mt-2" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i> Import Teachers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form enctype="multipart/form-data" class="app_frm" method="POST" action="admin/core/import_users">
                    <div class="mb-3">
                        <label class="form-label">Excel File</label>
                        <input required accept=".xlsx" type="file" name="file" class="form-control">
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-download me-1"></i> Download excel template from <a download href="templates/import_teachers.xlsx" class="alert-link">here</a>
                    </div>
                    <button type="submit" name="submit" value="1" class="btn btn-glass btn-glass-primary w-100">Import</button>
                    <button type="button" class="btn btn-glass w-100 mt-2" data-bs-dismiss="modal">Close</button>
                </form>
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
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#srmsTable').DataTable({
            "order": [[0, 'asc']],
            "columnDefs": [
                { "orderable": false, "targets": [4,5,6] }
            ],
            "language": {
                "search": "",
                "searchPlaceholder": "Search...",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "No entries available",
                "zeroRecords": "No matching teachers found"
            },
            "dom": 'lrtip'
        });

        // Custom search input
        $('#customSearchInput').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Toggle status via AJAX
        $(document).on('change', '.status-toggle', function() {
            var $toggle = $(this);
            var teacherId = $toggle.data('id');
            var newStatus = $toggle.prop('checked') ? 1 : 0;
            var $statusLabel = $toggle.closest('td').find('.status-label');
            
            $.ajax({
                url: 'admin/core/toggle_teacher_status.php',
                type: 'POST',
                data: { id: teacherId, status: newStatus },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $statusLabel.text(newStatus === 1 ? 'Active' : 'Blocked');
                        // Update stats cards (optional, we'll reload later or just update values)
                        // For simplicity, reload the page to refresh stats
                        // But we can also update stats dynamically
                        location.reload(); // Reload to refresh stats, cards, etc.
                    } else {
                        // Revert toggle
                        $toggle.prop('checked', !$toggle.prop('checked'));
                        Swal.fire('Error', response.message || 'Failed to update status', 'error');
                    }
                },
                error: function() {
                    $toggle.prop('checked', !$toggle.prop('checked'));
                    Swal.fire('Error', 'Network error. Please try again.', 'error');
                }
            });
        });
    });

    function set_user(id, gender, status) {
        $('#id').val(id);
        $('#fname').val($('#fname_'+id).val());
        $('#lname').val($('#lname_'+id).val());
        $('#email').val($('#email_'+id).val());
        $('#gender option[value="'+gender+'"]').prop('selected', true);
        $('#status option[value="'+status+'"]').prop('selected', true);
    }

    function del(url, msg) {
        Swal.fire({
            title: msg,
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }

    function lettersOnly(e) {
        var charCode = e.which ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 65 || charCode > 90) && (charCode < 97 || charCode > 122)) 
            return false;
        return true;
    }

    // Simple password match validation for add modal
    document.querySelector('#addModal form')?.addEventListener('submit', function(e) {
        var pass = document.getElementById('npass');
        var cpass = document.getElementById('cnpass');
        if (pass.value !== cpass.value) {
            e.preventDefault();
            Swal.fire('Error', 'Passwords do not match', 'error');
        }
    });
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>