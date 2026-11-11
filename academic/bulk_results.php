<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Access control: only academic staff
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}
if (!isset($_SESSION['bulk_result'])) {
    header("location:./");
    exit;
}

// Prepare staff array for header
$staff = [
    'id'            => $_SESSION['staff_id'] ?? 0,
    'fname'         => $fname ?? 'Academic',
    'lname'         => $lname ?? 'Staff',
    'display_image' => $img ?? null
];

$classId = $_SESSION['bulk_result']['student'];
$termId  = $_SESSION['bulk_result']['term'];

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $gradeSystem = $conn->query("SELECT * FROM tbl_grade_system")->fetchAll(PDO::FETCH_ASSOC);
    $divisionSystem = $conn->query("SELECT * FROM tbl_division_system")->fetchAll(PDO::FETCH_ASSOC);
    $GLOBALS['gradeSystem'] = $gradeSystem;
    $GLOBALS['divisionSystem'] = $divisionSystem;
    // For backward compatibility (if any other code still uses numeric $grading)
    $grading = $gradeSystem;

    $term = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
    $term->execute([$termId]);
    $term = $term->fetch(PDO::FETCH_ASSOC);
    if (!$term) throw new Exception("Term not found");

    $class = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
    $class->execute([$classId]);
    $class = $class->fetch(PDO::FETCH_ASSOC);
    if (!$class) throw new Exception("Class not found");

    $className = $class['name'];
    $pageTitle = $class['name'] . ' (' . $term['name'] . ' Results)';

    // Subject combos for this class
    $allCombos = $conn->query("
        SELECT sc.id, sc.class, s.id AS subject_id, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        WHERE s.status = 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    $validCombos = [];
    foreach ($allCombos as $combo) {
        $classList = unserialize($combo['class']);
        if (is_array($classList) && in_array($classId, $classList)) {
            $validCombos[] = $combo;
        }
    }
    $comboIds = array_column($validCombos, 'id');

    $students = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
    $students->execute([$classId]);
    $students = $students->fetchAll(PDO::FETCH_ASSOC);
    if (empty($students)) {
        echo '<div class="alert alert-warning">No students found in this class.</div>';
        exit;
    }

    $studentIds = array_column($students, 'id');
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $sql = "SELECT student, subject_combination, score
            FROM tbl_exam_results
            WHERE student IN ($studentPlaceholders) AND term = ?";
    $params = array_merge($studentIds, [$termId]);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultComboIds = array_unique(array_column($results, 'subject_combination'));
    foreach ($allCombos as $combo) {
        if (in_array($combo['id'], $resultComboIds) && !in_array($combo['id'], $comboIds)) {
            $validCombos[] = $combo;
        }
    }

    $resultsIndex = [];
    foreach ($results as $res) {
        $resultsIndex[$res['student']][$res['subject_combination']] = $res['score'];
    }

    $divisionOrder = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, '0' => 5];
    usort($students, function ($a, $b) use ($validCombos, $resultsIndex, $class, $className, $gradeSystem, $divisionSystem, $divisionOrder) {
        $getPerformance = function ($student) use ($validCombos, $resultsIndex, $class, $className, $gradeSystem, $divisionSystem) {
            $subjects = [];
            foreach ($validCombos as $combo) {
                if (array_key_exists($combo['id'], $resultsIndex[$student['id']] ?? [])) {
                    $subjects[] = [
                        'name' => $combo['subject_name'],
                        'score' => $resultsIndex[$student['id']][$combo['id']]
                    ];
                }
            }
            $result = calculate_acsee_result(
                $subjects,
                detect_combination_from_class_name($className) ?: '',
                $gradeSystem,
                $divisionSystem
            );
            return [
                'division' => $result['division'] ?? 'INC',
                'points' => $result['points'],
                'average' => count($subjects) ? array_sum(array_column($subjects, 'score')) / count($subjects) : -1
            ];
        };

        $aPerformance = $getPerformance($a);
        $bPerformance = $getPerformance($b);
        $aOrder = $divisionOrder[$aPerformance['division']] ?? 6;
        $bOrder = $divisionOrder[$bPerformance['division']] ?? 6;
        if ($aOrder !== $bOrder) return $aOrder <=> $bOrder;

        $aPoints = is_numeric($aPerformance['points']) ? (int)$aPerformance['points'] : PHP_INT_MAX;
        $bPoints = is_numeric($bPerformance['points']) ? (int)$bPerformance['points'] : PHP_INT_MAX;
        if ($aPoints !== $bPoints) return $aPoints <=> $bPoints;
        return $bPerformance['average'] <=> $aPerformance['average'];
    });

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <title>SRMS - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }
        
        /* DataTable styling – compact, dark text, glass feel */
        #srmsTable {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        #srmsTable thead th {
            background: rgba(241,245,249,0.7);
            backdrop-filter: blur(4px);
            font-weight: 700;
            font-size: 0.75rem;
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            padding: 0.8rem 0.6rem;
            text-transform: uppercase;
        }
        #srmsTable tbody tr {
            background: rgba(255,255,255,0.5);
            border-radius: 20px;
            transition: all 0.2s;
        }
        #srmsTable tbody tr:hover {
            background: rgba(255,255,255,0.8);
            transform: scale(1.01);
        }
        #srmsTable tbody td {
            padding: 0.7rem 0.6rem;
            color: #1e293b;
            border: none;
            vertical-align: middle;
        }
        #srmsTable tbody tr td:first-child {
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
        }
        #srmsTable tbody tr td:last-child {
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
        }
        
        /* Buttons */
        .btn-back {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 0.45rem 1.3rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: #4361ee;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .btn-back:hover {
            background: white;
            transform: translateY(-2px);
            color: #1e40af;
        }
        
        .btn-sm {
            border-radius: 40px;
            padding: 0.35rem 1rem;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.2s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #1e40af);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #1e3a8a);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -6px rgba(59,130,246,0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef476f, #dc2626);
            color: white;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            transform: translateY(-2px);
        }
        
        /* Avatar */
        .avatar_img_sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        /* Grade badge */
        .grade-badge {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            padding: 0.2rem 0.7rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.7rem;
            color: white;
            display: inline-block;
        }
        
        /* DataTable controls */
        .dataTables_filter input {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 40px;
            padding: 0.3rem 1rem 0.3rem 2rem;
            font-size: 0.8rem;
        }
        .dataTables_length select {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 30px;
            padding: 0.2rem 1.2rem 0.2rem 0.6rem;
            font-size: 0.8rem;
        }
        .dataTables_paginate .paginate_button {
            background: rgba(255,255,255,0.8);
            border-radius: 30px;
            padding: 0.25rem 0.8rem;
            margin: 0 2px;
            color: #1e293b !important;
            font-size: 0.75rem;
            border: none;
        }
        .dataTables_paginate .paginate_button.current {
            background: #4361ee;
            color: white !important;
        }
        
        /* Title */
        .app-title h1 {
            font-weight: 800;
            color: white;
            text-shadow: 0 1px 2px black;
            font-size: 1.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .tile { padding: 1rem; }
            #srmsTable tbody td { padding: 0.5rem 0.4rem; }
            .btn-sm { padding: 0.25rem 0.7rem; font-size: 0.65rem; }
        }
    </style>
</head>
<body>

    <!-- Include unified academic header -->
    <?php require 'inc/header_academic.php'; ?>
    
    <!-- Include academic sidebar -->
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h1><?php echo htmlspecialchars($pageTitle); ?></h1></div>
            <div><a href="academic/manage_results" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a></div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="tile">
                    <div class="tile-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="srmsTable">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>REG NO</th>
                                        <th>STUDENT NAME</th>
                                        <th>TOTAL</th>
                                        <th>AVG</th>
                                        <th>GRADE</th>
                                        <th>REMARKS</th>
                                        <th>DIV</th>
                                        <th>PTS</th>
                                        <th>ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // ----- EXACT LOOP FROM THE CLASSIC VERSION (for division & points) -----
                                foreach ($students as $student) {
                                    $studentId = $student['id'];
                                    $fullName = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);

                                    // Build subjects and scores in the same order as $validCombos.
                                    $scores = [];
                                    $subjectObjects = [];
                                    $totalScore = 0;
                                    $numSubjects = 0;

                                    foreach ($validCombos as $combo) {
                                        $comboId = $combo['id'];
                                        $score = array_key_exists($comboId, $resultsIndex[$studentId] ?? [])
                                            ? $resultsIndex[$studentId][$comboId]
                                            : null;
                                        $scores[] = $score;
                                        if ($score !== null && $score !== '') {
                                            $totalScore += $score;
                                            $numSubjects++;
                                            $subjectObjects[] = [
                                                'name' => $combo['subject_name'],
                                                'score' => $score
                                            ];
                                        }
                                    }

                                    $average = $numSubjects > 0 ? round($totalScore / $numSubjects, 1) : 'N/A';
                                    $grade = $numSubjects > 0 ? get_grade($average, $gradeSystem) : 'N/A';
                                    $remark = $numSubjects > 0 ? get_grade_remark($average, $gradeSystem) : 'N/A';
                                    $acsee = calculate_acsee_result(
                                        $subjectObjects,
                                        detect_combination_from_class_name($class['name']) ?: '',
                                        $gradeSystem,
                                        $divisionSystem
                                    );
                                    $points = $acsee['points'];
                                    $division = $acsee['division'] ?? 'INC';
                                ?>
                                    <tr>
                                        <td width="10">
                                            <?php
                                            // Modern avatar handling (improved, but does not affect division/points)
                                            $img = 'images/students/default.png';
                                            if (!empty($student['display_image']) && $student['display_image'] !== 'DEFAULT') {
                                                $custom = 'images/students/' . $student['display_image'];
                                                if (file_exists(__DIR__ . '/../' . $custom)) $img = $custom;
                                            } elseif (file_exists(__DIR__ . '/../images/students/' . strtolower($student['gender']) . '.png')) {
                                                $img = 'images/students/' . strtolower($student['gender']) . '.png';
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" class="avatar_img_sm" alt="Photo">
                                        </td>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($fullName); ?></td>
                                        <td><?php echo $totalScore; ?></td>
                                        <td><?php echo $average; ?></td>
                                        <td><span class="grade-badge"><?php echo htmlspecialchars($grade); ?></span></td>
                                        <td><?php echo htmlspecialchars($remark); ?></td>
                                        <td><?php echo htmlspecialchars($division); ?></td>
                                        <td><?php echo $points; ?></td>
                                        <td>
                                            <a href="academic/core/edit_result?std=<?php echo urlencode($studentId); ?>&term=<?php echo $termId; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                                            <a onclick="del('academic/core/drop_results?src=bulk_results&std=<?php echo urlencode($studentId); ?>&class=<?php echo $classId; ?>&term=<?php echo $termId; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php } ?>
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
                "sort": false,
                "pageLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "language": {
                    "search": "Filter:",
                    "paginate": {
                        "previous": "<i class='bi bi-chevron-left'></i>",
                        "next": "<i class='bi bi-chevron-right'></i>"
                    }
                }
            });
        });
        
        function del(url, msg) {
            if (confirm(msg)) window.location.href = url;
        }
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>