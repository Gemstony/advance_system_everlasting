<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

// Fetch student data for header (needed by inc/header.php)
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
$school_logo = defined('WBLogo') ? WBLogo : 'logo.png';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Fetch student details (minimal for header)
    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch();
    if (!$studentData) die("Student not found.");

    // Build $student array for modular header
    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];

    // Also fetch school contacts if needed (optional)
    $stmt2 = $pdo->prepare("SELECT name, logo FROM tbl_school LIMIT 1");
    $stmt2->execute();
    $school = $stmt2->fetch();
    if ($school) {
        $school_name = $school['name'];
        $school_logo = $school['logo'];
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== GRADING SYSTEM QUERY ==========
$gradingData = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_grade_system ORDER BY id");
    $stmt->execute();
    $gradingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gradingData = [];
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-star-fill me-2"></i> Grading System</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Grading System</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="tile-title m-0">Grade Classification</h4>
                    <span class="badge bg-secondary fs-6 px-3 py-2">Performance Levels</span>
                </div>

                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-3 me-3" style="color:#3498db;"></i>
                        <div>
                            <h6 class="fw-bold mb-1">How Grades Are Determined</h6>
                            <p class="mb-0 small">Each subject score is converted into a letter grade based on the ranges shown below. The remark column provides a qualitative description of the performance level.</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle" id="srmsTable">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Minimum Score</th>
                                <th>Maximum Score</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gradingData as $row): ?>
                                <?php
                                    $grade = $row['name'];
                                    $badgeClass = 'grade-';
                                    if ($grade == 'A') $badgeClass .= 'A';
                                    elseif ($grade == 'B') $badgeClass .= 'B';
                                    elseif ($grade == 'C') $badgeClass .= 'C';
                                    elseif ($grade == 'D') $badgeClass .= 'D';
                                    elseif ($grade == 'F') $badgeClass .= 'F';
                                    else $badgeClass .= 'other';
                                ?>
                                <tr>
                                    <td><span class="grade-badge <?= $badgeClass; ?>"><?= htmlspecialchars($grade); ?></span></td>
                                    <td><?= (int)$row['min']; ?></td>
                                    <td><?= (int)$row['max']; ?></td>
                                    <td><span class="remark-tag"><?= htmlspecialchars($row['remark']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 bg-light rounded">
                    <p class="mb-0 small"><i class="bi bi-star-fill text-warning me-2"></i><strong>Note:</strong> Grades are assigned based on the total score achieved in each subject. The grading scale may vary by subject or examination type.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Page‑specific styles (glass‑morphic, theme‑compatible) -->
<style>
    .app-content {
        background: transparent !important;
    }
    .tile {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(4px);
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }
    .table th {
        background-color: #2c3e50;
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #1a252f;
    }
    .table td {
        vertical-align: middle;
        padding: 0.75rem;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(52, 152, 219, 0.1);
        transition: background-color 0.2s ease;
    }
    .grade-badge {
        display: inline-block;
        padding: 0.35rem 0.85rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 2rem;
        min-width: 3rem;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .grade-A { background-color: #28a745; color: white; }
    .grade-B { background-color: #17a2b8; color: white; }
    .grade-C { background-color: #ffc107; color: #212529; }
    .grade-D { background-color: #fd7e14; color: white; }
    .grade-F { background-color: #dc3545; color: white; }
    .grade-other { background-color: #6c757d; color: white; }
    .info-card {
        background: rgba(248,249,250,0.9);
        border-left: 4px solid #3498db;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .tile-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2c3e50;
    }
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .remark-tag {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background-color: #e9ecef;
        border-radius: 1rem;
        font-size: 0.9rem;
        color: #495057;
    }
    .breadcrumb {
        background: rgba(255,255,255,0.7);
        backdrop-filter: blur(4px);
        border-radius: 40px;
        padding: 0.5rem 1rem;
    }
    .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
    .app-title h1 {
        font-weight: 800;
        background: linear-gradient(135deg, #1e293b, #2d3a5e);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
</style>

<!-- Required scripts (DataTables, jQuery, Bootstrap, etc.) -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
    $(document).ready(function() {
        $('#srmsTable').DataTable({
            "sort": false,
            "pageLength": 10,
            "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
            "language": {
                "search": "Filter records:",
                "paginate": {
                    "previous": "<",
                    "next": ">"
                }
            }
        });
    });
</script>

</body>
</html>