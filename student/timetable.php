<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

if (!($res == "1" && $level == "3")) {
    header("location:../");
    exit;
}

// Fetch student data for header (compatible with modular header)
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Fetch student details for header
    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch();
    if (!$studentData) die("Student not found.");
    
    // Build $student array expected by inc/header.php
    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== ORIGINAL TIMETABLE LOGIC (unchanged) ==========
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get student's class
    $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $classId = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$classId]);
    $className = $stmt->fetchColumn();

    // Get all subjects assigned to this class
    $stmt = $conn->prepare("
        SELECT DISTINCT subj.name AS subject_name, CONCAT(st.fname, ' ', st.lname) AS teacher
        FROM tbl_subject_combinations sc
        JOIN tbl_subjects subj ON sc.subject = subj.id
        LEFT JOIN tbl_staff st ON sc.teacher = st.id
        WHERE FIND_IN_SET(?, sc.class) > 0
        ORDER BY subj.name
    ");
    $stmt->execute([$classId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build a simple weekly timetable (hardcoded days/periods)
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $periods = ['08:00-09:30', '09:45-11:15', '11:30-13:00', '14:00-15:30', '15:45-17:15'];
    $timetable = [];
    foreach ($days as $d) {
        $timetable[$d] = [];
        foreach ($periods as $idx => $p) {
            $subjIdx = (array_search($d, $days) + $idx) % max(1, count($subjects));
            $timetable[$d][$p] = $subjects[$subjIdx] ?? null;
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include the modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-calendar-week me-2"></i> My Timetable</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Timetable</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile">
                <div class="tile-body">
                    <h4><?php echo htmlspecialchars($className); ?> – Weekly Schedule</h4>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-striped">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Day / Time</th>
                                    <?php foreach ($periods as $p) echo "<th>$p</th>"; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $day): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $day; ?></td>
                                    <?php foreach ($periods as $period): 
                                        $item = $timetable[$day][$period];
                                    ?>
                                        <td>
                                            <?php if ($item): ?>
                                                <strong><?php echo htmlspecialchars($item['subject_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($item['teacher'] ?? 'Staff'); ?></small>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> This is a sample timetable based on your class subjects. Please confirm with your teacher for actual room allocations.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Page-specific styles (preserve glass-morphic look, do not conflict with header) -->
<style>
    /* Make content area transparent so theme background shows */
    .app-content {
        background: transparent !important;
    }
    /* Glass‑style tile to be readable on any background */
    .tile {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(4px);
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    }
    .table {
        background: transparent;
    }
    .table thead th {
        background: rgba(0,0,0,0.05);
        color: #1e293b;
    }
    .table td, .table th {
        border-color: rgba(0,0,0,0.1);
    }
    .alert-info {
        background: rgba(13, 202, 240, 0.85);
        backdrop-filter: blur(4px);
        border: none;
        color: #0c5460;
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
    .breadcrumb-item a, .breadcrumb-item.active {
        color: #1e293b !important;
    }
</style>

<!-- Required scripts (same as original, but note header does not load jQuery/Bootstrap) -->
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>