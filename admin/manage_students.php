<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    header("location:../");
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Statistics
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $activeStudents = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE status = 1")->fetchColumn();
    $inactiveStudents = $totalStudents - $activeStudents;
    $totalClasses = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
    $maleStudents = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Male'")->fetchColumn();
    $femaleStudents = $totalStudents - $maleStudents;
    $activePercent = $totalStudents > 0 ? round(($activeStudents / $totalStudents) * 100, 1) : 0;

    // Recent students (last 5)
    $recentStudents = $pdo->query("
        SELECT s.id, s.fname, s.mname, s.lname, s.gender, s.email, s.status, c.name AS class_name
        FROM tbl_students s
        LEFT JOIN tbl_classes c ON s.class = c.id
        ORDER BY s.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Search functionality
    $search_query = '';
    $search_results = [];
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $search_query = trim($_GET['search']);
        $stmt = $pdo->prepare("
            SELECT s.id, s.fname, s.mname, s.lname, s.gender, s.email, s.status, s.class, c.name AS class_name
            FROM tbl_students s
            LEFT JOIN tbl_classes c ON s.class = c.id
            WHERE s.id LIKE :search 
               OR s.fname LIKE :search 
               OR s.mname LIKE :search 
               OR s.lname LIKE :search
            ORDER BY s.fname
        ");
        $search_param = '%' . $search_query . '%';
        $stmt->execute([':search' => $search_param]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch all classes for the edit modal dropdown
    $classes = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - Manage Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <base href="../">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <style>
        /* Glassmorphism & animations */
        :root {
            --primary: #4361ee;
            --primary-dark: #3651c7;
            --primary-light: rgba(67, 97, 238, 0.12);
            --success: #06d6a0;
            --warning: #ffb703;
            --danger: #ef476f;
            --dark: #1e293b;
            --light-bg: #f1f5f9;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: 1px solid rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            --border-radius: 28px;
            --card-radius: 24px;
            --transition: all 0.2s ease;
        }
        body {
            background: linear-gradient(145deg, #f0f4fa 0%, #e6ecf4 100%);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }
        .app-content {
            animation: fadeInUp 0.5s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        /* Glassmorphic tiles */
        .tile {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: var(--card-radius);
            box-shadow: var(--glass-shadow);
            border: var(--glass-border);
            transition: var(--transition);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .tile:hover {
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 1.2rem 1rem;
            border: var(--glass-border);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: rgba(67, 97, 238, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.8rem;
        }
        .stat-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5b6e8c;
        }
        .stat-content .value {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1.2;
        }
        .stat-content .sub {
            font-size: 0.7rem;
            color: #7c8ba0;
        }
        /* Enhanced search bar (fixed overlay) */
        .search-bar-container {
            margin-bottom: 2rem;
            position: relative;
        }
        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: 60px;
            border: var(--glass-border);
            padding: 0.3rem 0.3rem 0.3rem 1.2rem;
            transition: 0.2s;
        }
        .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        .search-icon {
            color: #8ba0bc;
            font-size: 1.2rem;
        }
        .search-input {
            flex: 1;
            position: relative;
        }
        .search-input input {
            width: 100%;
            border: none;
            padding: 0.85rem 0.5rem;
            background: transparent;
            font-size: 1rem;
            outline: none;
            color: var(--dark);
        }
        .search-input input::placeholder {
            color: #9aa9bf;
        }
        .search-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: none;
        }
        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
        .search-wrapper button {
            background: var(--primary);
            border: none;
            border-radius: 60px;
            padding: 0.6rem 1.8rem;
            color: white;
            font-weight: 500;
            transition: 0.2s;
        }
        .search-wrapper button:hover {
            background: var(--primary-dark);
            transform: scale(0.98);
        }
        .clear-search {
            background: transparent;
            border: none;
            color: #8ba0bc;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 0.5rem;
            transition: 0.2s;
        }
        .clear-search:hover {
            color: var(--danger);
        }
        /* Autocomplete dropdown - overlays everything */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
            max-height: 320px;
            overflow-y: auto;
            z-index: 9999;
            margin-top: 8px;
        }
        .autocomplete-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #edf2f7;
            transition: 0.1s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item:hover, .autocomplete-item.active {
            background: var(--primary-light);
        }
        .autocomplete-item strong {
            color: var(--primary);
        }
        .autocomplete-item small {
            color: #7c8ba0;
            font-size: 0.75rem;
        }
        /* Tables, badges, etc. (unchanged) */
        .table-responsive {
            border-radius: 20px;
            overflow-x: auto;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: rgba(67, 97, 238, 0.08);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .table tbody tr {
            transition: 0.15s;
        }
        .table tbody tr:hover {
            background: rgba(67, 97, 238, 0.04);
        }
        .badge-status {
            padding: 0.35rem 0.8rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.7rem;
        }
        .badge-active {
            background: var(--success);
            color: white;
        }
        .badge-inactive {
            background: #94a3b8;
            color: white;
        }
        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.75rem;
            border-radius: 40px;
        }
        .select2-container--default .select2-selection--multiple {
            border-radius: 40px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.8);
        }
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: var(--glass-border);
            border-radius: 32px;
        }
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                gap: 1rem;
            }
            .stat-card {
                padding: 0.9rem;
            }
            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 1.4rem;
            }
            .stat-content .value {
                font-size: 1.4rem;
            }
            .search-wrapper {
                flex-wrap: wrap;
                padding: 0.5rem;
            }
            .search-input {
                min-width: 100%;
                margin-bottom: 0.5rem;
            }
            .search-wrapper button {
                width: 100%;
                margin-top: 0.3rem;
            }
            .tile-title {
                font-size: 1.1rem;
            }
            .table td, .table th {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">SRMS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-people me-2"></i> Manage Students</h1></div>
    </div>

    <!-- Dynamic Statistics Cards with count-up -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card" data-value="<?php echo $totalStudents; ?>">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-content">
                <h4>Total Students</h4>
                <div class="value">0</div>
            </div>
        </div>
        <div class="stat-card" data-value="<?php echo $activeStudents; ?>">
            <div class="stat-icon"><i class="bi bi-check-circle-fill" style="color: var(--success); background: rgba(6,214,160,0.12);"></i></div>
            <div class="stat-content">
                <h4>Active</h4>
                <div class="value">0</div>
                <div class="sub"><?php echo $activePercent; ?>% of total</div>
            </div>
        </div>
        <div class="stat-card" data-value="<?php echo $inactiveStudents; ?>">
            <div class="stat-icon"><i class="bi bi-x-circle-fill" style="color: var(--danger); background: rgba(239,71,111,0.12);"></i></div>
            <div class="stat-content">
                <h4>Inactive</h4>
                <div class="value">0</div>
            </div>
        </div>
        <div class="stat-card" data-value="<?php echo $maleStudents; ?>">
            <div class="stat-icon"><i class="bi bi-gender-male"></i></div>
            <div class="stat-content">
                <h4>Male</h4>
                <div class="value">0</div>
            </div>
        </div>
        <div class="stat-card" data-value="<?php echo $femaleStudents; ?>">
            <div class="stat-icon"><i class="bi bi-gender-female"></i></div>
            <div class="stat-content">
                <h4>Female</h4>
                <div class="value">0</div>
            </div>
        </div>
        <div class="stat-card" data-value="<?php echo $totalClasses; ?>">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div class="stat-content">
                <h4>Classes</h4>
                <div class="value">0</div>
            </div>
        </div>
    </div>

    <!-- Two-column layout (class selection + recent students) -->
    <div class="row g-4">
        <div class="col-md-5">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-bookmark-check me-2"></i> Select Classes to Manage</h3>
                    <form class="app_frm" method="POST" action="admin/core/list_students">
                        <div class="mb-3">
                            <label class="form-label">Class(es)</label>
                            <select multiple="true" class="form-control select2" name="class[]" required style="width: 100%;">
                                <?php
                                try {
                                    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
                                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    $stmt = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name");
                                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                                        echo '<option value="'.$row[0].'">'.htmlspecialchars($row[1]).'</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option disabled>Error loading classes</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="submit" value="1" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> Manage Students</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="tile">
                <div class="tile-body">
                    <h3 class="tile-title"><i class="bi bi-clock-history me-2"></i> Recently Added Students</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Reg No</th>
                                    <th>Name</th>
                                    <th><i class="bi bi-building"></i> Class</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudents as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['id']); ?></td>
                                    <td><i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($s['fname'] . ' ' . $s['mname'] . ' ' . $s['lname']); ?></td>
                                    <td><?php echo htmlspecialchars($s['class_name'] ?? '—'); ?></td>
                                    <td><span class="badge-status <?php echo $s['status'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $s['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== SEARCH BAR & RESULTS MOVED BELOW ALL CARDS ========== -->
    <!-- Enhanced Search Bar with fixed overlay -->
    <div class="search-bar-container">
        <div class="search-wrapper">
            <i class="bi bi-search search-icon"></i>
            <div class="search-input">
                <input type="text" id="globalSearchInput" placeholder="Search by name or registration number..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
                <div class="search-loading" id="searchLoading"></div>
                <div id="autocompleteDropdown" class="autocomplete-suggestions" style="display: none;"></div>
            </div>
            <button type="button" id="searchBtn" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
            <?php if (!empty($search_query)): ?>
                <a href="admin/manage_students" class="clear-search"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Results (if any) -->
    <?php if (isset($_GET['search']) && trim($_GET['search']) !== ''): ?>
        <div class="tile animate-fade">
            <div class="tile-body">
                <h3 class="tile-title">Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <table><th>Reg No</th><th>Full Name</th><th>Class</th><th>Gender</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($search_results)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No students found.<?php echo ' '; ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($search_results as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                    <td><span class="badge-status <?php echo $student['status'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $student['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-student" 
                                            data-id="<?php echo $student['id']; ?>"
                                            data-fname="<?php echo htmlspecialchars($student['fname']); ?>"
                                            data-mname="<?php echo htmlspecialchars($student['mname']); ?>"
                                            data-lname="<?php echo htmlspecialchars($student['lname']); ?>"
                                            data-email="<?php echo htmlspecialchars($student['email']); ?>"
                                            data-gender="<?php echo $student['gender']; ?>"
                                            data-class="<?php echo $student['class']; ?>"
                                            data-status="<?php echo $student['status']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <a href="javascript:void(0);" onclick="deleteStudent('<?php echo $student['id']; ?>')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</a>
                                        <a href="javascript:void(0);" onclick="resetStudentPassword('<?php echo $student['id']; ?>')" class="btn btn-sm btn-warning"><i class="bi bi-key"></i> Reset</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Edit Student Modal (unchanged functionality) -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="admin/core/update_student">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3"><label>First Name</label><input type="text" name="fname" id="edit_fname" class="form-control" required></div>
                    <div class="mb-3"><label>Middle Name</label><input type="text" name="mname" id="edit_mname" class="form-control"></div>
                    <div class="mb-3"><label>Last Name</label><input type="text" name="lname" id="edit_lname" class="form-control" required></div>
                    <div class="mb-3"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                    <div class="mb-3"><label>Gender</label><select name="gender" id="edit_gender" class="form-select"><option>Male</option><option>Female</option></select></div>
                    <div class="mb-3"><label>Class</label><select name="class" id="edit_class" class="form-select"><?php foreach ($classes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label>Status</label><select name="status" id="edit_status" class="form-select"><option value="1">Active</option><option value="0">Blocked</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit" value="1" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$(document).ready(function() {
    // Initialize select2
    $('.select2').select2();

    // ----- Count-up animation for statistics -----
    function animateNumber(element, finalValue) {
        let current = 0;
        const duration = 800;
        const step = Math.ceil(finalValue / (duration / 20));
        const interval = setInterval(function() {
            current += step;
            if (current >= finalValue) {
                clearInterval(interval);
                element.text(finalValue.toLocaleString());
            } else {
                element.text(current.toLocaleString());
            }
        }, 20);
    }
    $('.stat-card').each(function() {
        let final = parseInt($(this).data('value'));
        let valueSpan = $(this).find('.value');
        animateNumber(valueSpan, final);
    });

    // ----- Autocomplete functionality -----
    let typingTimer;
    const doneTypingInterval = 300;
    const $searchInput = $('#globalSearchInput');
    const $searchBtn = $('#searchBtn');
    const $autocomplete = $('#autocompleteDropdown');
    const $loading = $('#searchLoading');

    $searchInput.on('input', function() {
        clearTimeout(typingTimer);
        let query = $(this).val();
        if (query.length >= 2) {
            typingTimer = setTimeout(function() {
                $loading.show();
                $.ajax({
                    url: 'admin/core/search_students_ajax.php',
                    type: 'GET',
                    data: { search: query, limit: 8 },
                    dataType: 'json',
                    success: function(res) {
                        $loading.hide();
                        if (res.success && res.students.length > 0) {
                            let html = '';
                            res.students.forEach(s => {
                                let fullName = s.fname + (s.mname ? ' ' + s.mname : '') + ' ' + s.lname;
                                html += `<div class="autocomplete-item" data-id="${s.id}">
                                            <div><strong>${escapeHtml(s.id)}</strong> – ${escapeHtml(fullName)}</div>
                                            <small>${escapeHtml(s.class_name || 'No class')}</small>
                                        </div>`;
                            });
                            $autocomplete.html(html).show();
                        } else {
                            $autocomplete.html('<div class="autocomplete-item" style="cursor:default;">No matching students</div>').show();
                        }
                    },
                    error: function() {
                        $loading.hide();
                        $autocomplete.hide();
                    }
                });
            }, doneTypingInterval);
        } else {
            $autocomplete.hide();
        }
    });

    $(document).on('click', '.autocomplete-item', function() {
        let studentId = $(this).data('id');
        $searchInput.val(studentId);
        $autocomplete.hide();
        window.location.href = 'admin/manage_students?search=' + encodeURIComponent(studentId);
    });

    // Hide dropdown on outside click
    $(document).click(function(e) {
        if (!$(e.target).closest('.search-input').length) {
            $autocomplete.hide();
        }
    });

    // Search button
    $searchBtn.click(function() {
        let searchTerm = $searchInput.val().trim();
        if (searchTerm !== '') {
            window.location.href = 'admin/manage_students?search=' + encodeURIComponent(searchTerm);
        } else {
            window.location.href = 'admin/manage_students';
        }
    });
    $searchInput.keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $searchBtn.click();
        }
    });

    // Edit student button (works for any .edit-student)
    $('.edit-student').click(function() {
        $('#edit_id').val($(this).data('id'));
        $('#edit_fname').val($(this).data('fname'));
        $('#edit_mname').val($(this).data('mname'));
        $('#edit_lname').val($(this).data('lname'));
        $('#edit_email').val($(this).data('email'));
        $('#edit_gender').val($(this).data('gender'));
        $('#edit_class').val($(this).data('class'));
        $('#edit_status').val($(this).data('status'));
        $('#editStudentModal').modal('show');
    });
});

// Helper: escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Delete student with SweetAlert
function deleteStudent(studentId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef476f',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin/core/delete_student?id=' + studentId;
        }
    });
}

// Reset password
function resetStudentPassword(studentId) {
    Swal.fire({
        title: 'Reset Password?',
        text: "This will reset the student's password to a random string.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffb703',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, reset!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin/core/reset_student_password?id=' + studentId;
        }
    });
}
</script>
</body>
</html>