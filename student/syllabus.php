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

// Fetch student data for modular header
$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION sql_mode = ''");

    // Student details for header (minimal)
    $stmt = $pdo->prepare("SELECT fname, lname, display_image, gender, email FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentData) die("Student not found.");

    $student = [
        'fname' => $studentData['fname'],
        'lname' => $studentData['lname'],
        'id' => $student_id,
        'display_image' => $studentData['display_image'] ?? null,
        'gender' => $studentData['gender'] ?? null,
        'email' => $studentData['email'] ?? null
    ];
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Original syllabus logic – fetch class name and subjects
try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ?");
    $stmt->execute([$student_id]);
    $classId = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$classId]);
    $className = $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT DISTINCT subj.name AS subject_name, CONCAT(st.fname, ' ', st.lname) AS teacher_name
        FROM tbl_subject_combinations sc
        JOIN tbl_subjects subj ON sc.subject = subj.id
        LEFT JOIN tbl_staff st ON sc.teacher = st.id
        WHERE FIND_IN_SET(?, sc.class) > 0
        ORDER BY subj.name
    ");
    $stmt->execute([$classId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include modular header and sidebar
require 'inc/header.php';
require 'inc/sidebar.php';
?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-journal-bookmark-fill me-2"></i> Syllabus & Learning Materials</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Syllabus</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="tile p-4">
                <div class="tile-body">
                    <h4 class="mb-3"><?php echo htmlspecialchars($className); ?></h4>
                    <?php if (empty($subjects)): ?>
                        <div class="alert alert-warning">No subjects found for your class.</div>
                    <?php else: ?>
                        <div class="accordion" id="syllabusAccordion">
                            <?php $i = 0; foreach ($subjects as $sub): $i++; ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $i; ?>">
                                        <?php echo htmlspecialchars($sub['subject_name']); ?>
                                        <span class="ms-3 small text-muted">– <?php echo htmlspecialchars($sub['teacher_name'] ?? 'Teacher'); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#syllabusAccordion">
                                    <div class="accordion-body">
                                        <p><strong>Course outline:</strong> Detailed syllabus for <?php echo htmlspecialchars($sub['subject_name']); ?> will be uploaded here. Please consult your teacher for the latest materials.</p>
                                        <a href="#" class="btn btn-sm btn-outline-primary disabled">Download Syllabus (coming soon)</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Page-specific styles – glass‑compatible, preserves original accordion look -->
<style>
    .app-content {
        background: transparent !important;
    }
    .tile {
        background: rgba(255, 255, 255, 0.92) !important;
        backdrop-filter: blur(8px);
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    .accordion-item {
        background: transparent;
        border-color: rgba(0,0,0,0.1);
    }
    .accordion-button {
        background: rgba(248,249,250,0.8);
        backdrop-filter: blur(4px);
        font-weight: 500;
    }
    .accordion-button:not(.collapsed) {
        background: rgba(67, 97, 238, 0.15);
        color: #1e293b;
    }
    .accordion-body {
        background: rgba(255,255,255,0.6);
        backdrop-filter: blur(4px);
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
    body.dark .tile {
        background: rgba(30, 41, 59, 0.92) !important;
        color: #e2e8f0;
    }
    body.dark .accordion-button {
        background: rgba(51, 65, 85, 0.8);
        color: #e2e8f0;
    }
    body.dark .accordion-button:not(.collapsed) {
        background: rgba(67, 97, 238, 0.3);
        color: #f1f5f9;
    }
    body.dark .accordion-body {
        background: rgba(30, 41, 59, 0.7);
        color: #cbd5e1;
    }
    body.dark .breadcrumb {
        background: rgba(30, 41, 59, 0.7);
    }
    body.dark .breadcrumb-item a, body.dark .breadcrumb-item.active {
        color: #cbd5e1 !important;
    }
    body.dark .app-title h1 {
        background: linear-gradient(135deg, #f1f5f9, #94a3b8);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
</style>

<!-- Required scripts (header loads jQuery, Bootstrap, main) – no extra scripts needed -->
</body>
</html>