<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Only academic staff allowed
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

// Check if student result session is set
if (!isset($_SESSION['student_result'])) {
    header("location:./");
    exit;
}

$studentId = $_SESSION['student_result']['student'];
$termId    = $_SESSION['student_result']['term'];

try {
    $conn = new PDO(
        'mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset . ';collation=' . DBCollation,
        DBUser,
        DBPass
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch student data
    $stmt = $conn->prepare("SELECT id, fname, mname, lname, class FROM tbl_students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        die("Student not found.");
    }

    // Fetch term data
    $stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ?");
    $stmt->execute([$termId]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term) {
        die("Term not found.");
    }

    // Fetch class data (optional, used for display)
    $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$student['class']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    $pageTitle = $student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname'] . ' (' . $term['name'] . ' Results)';
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <title>SRMS - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
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
        
        /* ========== GLASS TILE (consistent with other pages) ========== */
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
        
        /* Student info chip */
        .student-info {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 0.8rem 1.2rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .student-info i {
            color: #4361ee;
        }
        
        /* Subject grid – simple layout */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        /* Form controls – dark text */
        .form-label {
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-label i {
            color: #4361ee;
        }
        .form-control {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.7rem 1rem;
            background: white;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67,97,238,0.2);
            outline: none;
        }
        
        /* Buttons */
        .btn {
            border-radius: 40px;
            padding: 0.65rem 1.8rem;
            font-weight: 700;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            color: white;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef476f, #dc2626);
            color: white;
        }
        .btn-back {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.6);
            color: #1e293b;
        }
        .btn-back:hover {
            background: white;
        }
        
        /* Title – keep white for header consistency */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .tile {
                padding: 1.2rem;
            }
            .subjects-grid {
                grid-template-columns: 1fr;
            }
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
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
            <div><h1><?php echo htmlspecialchars($pageTitle); ?></h1></div>
            <div><a href="academic/manage_results" class="btn btn-back"><i class="bi bi-arrow-left me-2"></i> Back to Selection</a></div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-body">
                        <!-- Student info summary -->
                        <div class="student-info">
                            <i class="bi bi-person-badge"></i>
                            <?php echo htmlspecialchars($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']); ?>
                            | <i class="bi bi-building"></i> <?php echo htmlspecialchars($class['name'] ?? 'N/A'); ?>
                            | <i class="bi bi-calendar-week"></i> <?php echo htmlspecialchars($term['name']); ?>
                        </div>

                        <form action="academic/core/update_results" class="app_frm" method="POST" autocomplete="OFF">
                            <?php
                            $totalScore = 0;

                            // Get all subject combinations that apply to this student's class
                            $stmt = $conn->prepare("SELECT sc.id, sc.class, s.id AS subject_id, s.name AS subject_name
                                                    FROM tbl_subject_combinations sc
                                                    LEFT JOIN tbl_subjects s ON sc.subject = s.id
                                                    WHERE s.status = 1");
                            $stmt->execute();
                            $allCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            // Filter combinations that belong to this student's class
                            $studentClassId = $student['class'];
                            $validCombos = array_filter($allCombos, function($combo) use ($studentClassId) {
                                $classList = unserialize($combo['class']);
                                return is_array($classList) && in_array($studentClassId, $classList);
                            });

                            // Fetch existing results
                            $stmt = $conn->prepare("SELECT subject_combination, score FROM tbl_exam_results
                                                    WHERE student = ? AND term = ?");
                            $stmt->execute([$studentId, $termId]);
                            $existingResults = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $existingResults[$row['subject_combination']] = $row['score'];
                            }
                            foreach ($allCombos as $combo) {
                                if (isset($existingResults[$combo['id']])) {
                                    $validCombos[$combo['id']] = $combo;
                                }
                            }

                            if (empty($validCombos)) {
                                echo '<div class="alert alert-warning">No subject combinations found for this class.</div>';
                            } else {
                                echo '<div class="subjects-grid">';
                                foreach ($validCombos as $combo) {
                                    $score = isset($existingResults[$combo['id']]) ? $existingResults[$combo['id']] : 0;
                                    $totalScore += $score;
                                    ?>
                                    <div class="subject-field">
                                        <label class="form-label"><i class="bi bi-book"></i> <?php echo htmlspecialchars($combo['subject_name']); ?></label>
                                        <input value="<?php echo htmlspecialchars($score); ?>"
                                               name="<?php echo $combo['id']; ?>"
                                               class="form-control"
                                               required
                                               type="number"
                                               step="0.01"
                                               min="0"
                                               max="100"
                                               placeholder="Score 0–100">
                                    </div>
                                    <?php
                                }
                                echo '</div>';
                            }
                            ?>
                            <input type="hidden" name="student" value="<?php echo htmlspecialchars($studentId); ?>">
                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($termId); ?>">
                            <input type="hidden" name="class" value="<?php echo htmlspecialchars($studentClassId); ?>">

                            <div class="mt-4 d-flex flex-wrap gap-3">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save me-2"></i> Save Results</button>
                                <?php if ($totalScore > 0): ?>
                                    <a onclick="del('academic/core/drop_results?src=single_results&std=<?php echo urlencode($studentId); ?>&class=<?php echo $studentClassId; ?>&term=<?php echo $termId; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger"><i class="bi bi-trash me-2"></i> Delete All</a>
                                <?php endif; ?>
                            </div>
                        </form>
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
    <script src="select2/dist/js/select2.full.min.js"></script>
    <?php require_once('const/check-reply.php'); ?>
    <script>
        function del(url, msg) {
            if (confirm(msg)) window.location.href = url;
        }
    </script>
</body>
</html>