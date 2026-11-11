<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Teacher only (level 2)
if ($res != "1" || $level != "2") {
    header("location:../");
    exit;
}

// Check if student list exists in session (set by teacher/core/list_students)
if (!isset($_SESSION['student_list'])) {
    header("location:./list_students");
    exit;
}

$students = $_SESSION['student_list'];

// Prepare placeholder string for SQL IN clause
$matches = implode(',', array_fill(0, count($students), '?'));
$class_names = [];

try {
    // Fetch class names for mapping
    $stmt = $conn->prepare("SELECT id, name FROM tbl_classes");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $class_names = $classes;

    // Fetch students based on session list
    $stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class IN ($matches)");
    $stmt->execute($students);
    $result = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['reply'] = [['danger', "Database error: " . $e->getMessage()]];
    header("location:./list_students");
    exit;
}

// Prepare data for header (required by inc/header.php)
$staff = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null
];
$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
?>
<?php include 'inc/header.php'; ?>
<?php include 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div>
            <h1>
                <i class="bi bi-people me-2"></i>
                Students List
                <small class="text-muted d-block fs-6 mt-1">View and manage students from selected classes</small>
            </h1>
        </div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="teacher/list_students">List Students</a></li>
            <li class="breadcrumb-item active">Results</li>
        </ul>
    </div>

    <!-- Reply / Alert Messages -->
    <?php if (isset($_SESSION['reply'])): ?>
        <?php foreach ($_SESSION['reply'] as $msg): ?>
            <div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show"><?= $msg[1] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endforeach; ?>
        <?php unset($_SESSION['reply']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <div class="table-responsive">
                        <h3 class="tile-title">Student List</h3>
                        <table class="table table-hover table-bordered" id="srmsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Registration Number</th>
                                    <th>Firstname</th>
                                    <th>Middlename</th>
                                    <th>Lastname</th>
                                    <th>Gender</th>
                                    <th>Class</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $row): ?>
                                    <tr>
                                        <td width="10">
                                            <?php if ($row['display_image'] == "DEFAULT"): ?>
                                                <img src="images/students/<?= $row['gender']; ?>.png" class="avatar_img_sm">
                                            <?php else: ?>
                                                <img src="images/students/<?= $row['display_image']; ?>" class="avatar_img_sm">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($row['id']); ?></td>
                                        <td><?= htmlspecialchars($row['fname']); ?></td>
                                        <td><?= htmlspecialchars($row['mname']); ?></td>
                                        <td><?= htmlspecialchars($row['lname']); ?></td>
                                        <td><?= htmlspecialchars($row['gender']); ?></td>
                                        <td><?= htmlspecialchars($class_names[$row['class']] ?? 'Unknown'); ?></td>
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
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<?php require_once('const/check-reply.php'); ?>

<script>
$(document).ready(function() {
    $('#srmsTable').DataTable({
        "order": [],
        "pageLength": 25,
        "responsive": true,
        "language": {
            "search": "Search students:",
            "lengthMenu": "Show _MENU_ students per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ students",
            "emptyTable": "No students found"
        }
    });
});
</script>

<style>
/* Synchronized transparent glass style with the teacher dashboard */
.app-content {
    background: transparent !important;
}
.tile {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.6);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.5rem 1rem;
}
.avatar_img_sm {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}
.dataTables_wrapper .dt-buttons {
    margin-bottom: 1rem;
}
/* Ensure table rows are readable on glass background */
.table {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 16px;
    overflow: hidden;
}
.table thead th {
    background: rgba(0, 0, 0, 0.03);
}
</style>
</body>
</html>