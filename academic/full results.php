<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Academic only (level 1)
if (!($res == "1" && $level == "1")) {
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

// Helper to parse class list
function parse_class_list($raw) {
    if (is_array($raw)) return $raw;
    $un = @unserialize($raw);
    if ($un !== false && is_array($un)) return array_map('intval', $un);
    $json = @json_decode($raw, true);
    if (is_array($json)) return array_map('intval', $json);
    $raw_trim = trim((string)$raw);
    if ($raw_trim === '') return [];
    if (ctype_digit($raw_trim)) return [(int)$raw_trim];
    $parts = preg_split('/\s*,\s*/', $raw_trim);
    $ints = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (ctype_digit($p)) $ints[] = (int)$p;
    }
    return $ints;
}

$selected_class = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$selected_term  = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$export = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : '';

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}

$classes = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name ASC")->fetchAll();
$terms   = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id ASC")->fetchAll();
$gradeSystem = $pdo->query("SELECT * FROM tbl_grade_system ORDER BY min DESC")->fetchAll();
$divisionSystem = $pdo->query("SELECT * FROM tbl_division_system ORDER BY points ASC")->fetchAll();
$selectedClassName = '';
foreach ($classes as $class) {
    if ((int)$class['id'] === $selected_class) {
        $selectedClassName = $class['name'];
        break;
    }
}

// ------------------------------------------------------------
// Grade mapping for subject grades (MKEKA style)
// ------------------------------------------------------------
function get_tz_grade_info($score) {
    $score = floor($score);
    $scale = [
        ['min' => 80, 'max' => 100, 'name' => 'A', 'points' => 1],
        ['min' => 70, 'max' => 79, 'name' => 'B', 'points' => 2],
        ['min' => 60, 'max' => 69, 'name' => 'C', 'points' => 3],
        ['min' => 50, 'max' => 59, 'name' => 'D', 'points' => 4],
        ['min' => 40, 'max' => 49, 'name' => 'E', 'points' => 5],
        ['min' => 35, 'max' => 39, 'name' => 'S', 'points' => 6],
        ['min' => 0,  'max' => 34, 'name' => 'F', 'points' => 7]
    ];
    foreach ($scale as $g) {
        if ($score >= $g['min'] && $score <= $g['max']) {
            return $g;
        }
    }
    return ['name' => 'N/A', 'points' => 0];
}

// ------------------------------------------------------------
// Helper: get grade from average using DB grading
// ------------------------------------------------------------
function get_grade_from_avg($avg, $grading) {
    $avg = floor((float)$avg);
    foreach ($grading as $g) {
        if ($avg >= $g['min'] && $avg <= $g['max']) {
            return ['name' => $g['name'], 'remark' => $g['remark']];
        }
    }
    return ['name' => '', 'remark' => ''];
}

// ------------------------------------------------------------
// Ranking with tie handling
// ------------------------------------------------------------
function compute_ranks($students, $subjects, $exam_index) {
    $totals = [];
    foreach ($students as $st) {
        $sid = $st['id'];
        $total = 0;
        foreach ($subjects as $scid => $meta) {
            $total += $exam_index[$sid][$scid] ?? 0;
        }
        $totals[$sid] = $total;
    }
    arsort($totals);
    $ranks = [];
    $prev_total = null;
    $prev_rank = 0;
    $i = 0;
    foreach ($totals as $sid => $total) {
        $i++;
        if ($prev_total === null) {
            $rank = 1;
        } else {
            $rank = ($total === $prev_total) ? $prev_rank : $i;
        }
        $ranks[$sid] = $rank;
        $prev_total = $total;
        $prev_rank = $rank;
    }
    return $ranks;
}

// ------------------------------------------------------------
// Main data fetching
// ------------------------------------------------------------
$subjects = [];
$students = [];
$exam_index = [];
$subject_names = [];

if ($selected_class && $selected_term) {
    // Get subject combinations for this class
    $stmt = $pdo->prepare("
        SELECT sc.id AS sc_id, sc.class AS sc_class, s.id AS subject_id, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $all_sc = $stmt->fetchAll();
    foreach ($all_sc as $r) {
        $class_list = parse_class_list($r['sc_class']);
        if (in_array($selected_class, $class_list, true)) {
            $subjects[(int)$r['sc_id']] = [
                'sc_id' => (int)$r['sc_id'],
                'subject_id' => (int)$r['subject_id'],
                'subject_name' => $r['subject_name'] ?? 'Unknown'
            ];
            $subject_names[] = $r['subject_name'] ?? 'Unknown';
        }
    }

    // Students in class
    $stmt = $pdo->prepare("SELECT id, fname, mname, lname, display_image, gender FROM tbl_students WHERE class = ? AND status = 1 ORDER BY id ASC");
    $stmt->execute([$selected_class]);
    $students = $stmt->fetchAll();

    // Exam results
    $student_ids = array_column($students, 'id');
    $student_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $stmt = $pdo->prepare("SELECT student, subject_combination, score FROM tbl_exam_results WHERE student IN ($student_placeholders) AND term = ?");
    $stmt->execute(array_merge($student_ids, [$selected_term]));
    $all_results = $stmt->fetchAll();
    $result_subject_ids = [];
    foreach ($all_results as $rr) {
        $sid = $rr['student'];
        $scid = (int)$rr['subject_combination'];
        $result_subject_ids[$scid] = true;
        $score = is_null($rr['score']) ? 0 : (float)$rr['score'];
        if (!isset($exam_index[$sid])) $exam_index[$sid] = [];
        $exam_index[$sid][$scid] = $score;
    }
    // A promoted student may have a result recorded with a combination
    // belonging to the former class. Keep that subject visible as well.
    foreach ($all_sc as $r) {
        $scid = (int)$r['sc_id'];
        if (isset($result_subject_ids[$scid])) {
            $subjects[$scid] = [
                'sc_id' => $scid,
                'subject_id' => (int)$r['subject_id'],
                'subject_name' => $r['subject_name'] ?? 'Unknown'
            ];
        }
    }
}

// ------------------------------------------------------------
// Compute student data
// ------------------------------------------------------------
$student_data = [];
$total_students = count($students);
$students_without_results = 0;

if (!empty($subjects) && !empty($students)) {
    foreach ($students as $st) {
        $sid = $st['id'];
        $gender = $st['gender'];
        $fullname = trim($st['fname'] . ' ' . $st['mname'] . ' ' . $st['lname']);

        $score_list = [];
        $subject_grade_pairs = [];
        $total_score = 0;
        $has_result = false;

        foreach ($subjects as $scid => $smeta) {
            $score = array_key_exists($scid, $exam_index[$sid] ?? [])
                ? $exam_index[$sid][$scid]
                : null;
            $score_list[] = $score;
            if ($score !== null && $score !== '') $total_score += $score;
            if ($score !== null && $score !== '') $has_result = true;

            // Subject grade
            if ($score !== null && $score !== '') {
                $grade_info = get_tz_grade_info($score);
                $subject_grade_pairs[] = $smeta['subject_name'] . "-'" . $grade_info['name'] . "'";
            }
        }

        if (!$has_result) $students_without_results++;

        $subj_count = count(array_filter($score_list, function ($score) {
            return $score !== null && $score !== '';
        }));
        $average = ($subj_count > 0) ? round($total_score / $subj_count, 2) : 0;

        // Overall grade from DB grading
        $ginfo = get_grade_from_avg($average, $gradeSystem);
        $grade = $ginfo['name'];
        $remark = $ginfo['remark'];

        $subject_objects = [];
        foreach ($subjects as $scid => $smeta) {
            $score = array_key_exists($scid, $exam_index[$sid] ?? [])
                ? $exam_index[$sid][$scid]
                : null;
            if ($score !== null && $score !== '') {
                $subject_objects[] = [
                    'name' => $smeta['subject_name'],
                    'score' => $score
                ];
            }
        }
        $acsee = calculate_acsee_result(
            $subject_objects,
            detect_combination_from_class_name($selectedClassName) ?: '',
            $gradeSystem,
            $divisionSystem
        );
        $division = $acsee['division'];
        $points = $acsee['points'];

        // Subject string
        $subject_grade_string = implode(' ', $subject_grade_pairs);

        $student_data[] = [
            'id'          => $sid,
            'name'        => $fullname,
            'gender'      => $gender,
            'total'       => $total_score,
            'average'     => $average,
            'grade'       => $grade,
            'remark'      => $remark,
            'division'    => $division,
            'points'      => $points,
            'subject_grades' => $subject_grade_string,
            'display_image' => $st['display_image'],
        ];
    }
}

// Rank and display students by NECTA division, then AGGT points, then average.
$division_order = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, '0' => 5, null => 6];
usort($student_data, function ($a, $b) use ($division_order) {
    $a_order = $division_order[$a['division']] ?? 99;
    $b_order = $division_order[$b['division']] ?? 99;
    if ($a_order !== $b_order) return $a_order <=> $b_order;

    $a_points = is_numeric($a['points']) ? (int)$a['points'] : PHP_INT_MAX;
    $b_points = is_numeric($b['points']) ? (int)$b['points'] : PHP_INT_MAX;
    if ($a_points !== $b_points) return $a_points <=> $b_points;
    if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];

    return strcasecmp($a['name'], $b['name']);
});

$ranks = [];
$previous_rank_key = null;
$position = 0;
$rank = 1;
foreach ($student_data as $data) {
    $position++;
    $rank_key = implode('|', [
        $data['division'] ?? 'N/A',
        is_numeric($data['points']) ? (int)$data['points'] : '-',
        (float)$data['average']
    ]);
    if ($rank_key !== $previous_rank_key) $rank = $position;
    $ranks[$data['id']] = $rank;
    $previous_rank_key = $rank_key;
}

// Title
$title = 'Bulk Results';
if ($selected_class && $selected_term) {
    $stmt = $pdo->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$selected_class]);
    $cname = $stmt->fetchColumn() ?: '';
    $stmt = $pdo->prepare("SELECT name FROM tbl_terms WHERE id = ?");
    $stmt->execute([$selected_term]);
    $tname = $stmt->fetchColumn() ?: '';
    if ($cname && $tname) $title = $cname . " (" . $tname . " Results)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - <?php echo htmlspecialchars($title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <link rel="stylesheet" href="cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="select2/dist/css/select2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== GLOBAL – black title ===== */
        body { background: transparent; font-family: 'Inter', sans-serif; }
        .app-content { background: transparent !important; }
        .app-title h1 {
            font-weight: 800;
            color: #000000 !important;
            text-shadow: none !important;
        }

        /* ===== GLASS FILTER CARD ===== */
        .filter-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            margin-bottom: 1.8rem;
        }
        .filter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }

        /* ===== GLASS TILE ===== */
        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 1.5rem;
        }
        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
            background: rgba(255,255,255,0.95);
        }

        /* ===== DATA TABLE ===== */
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
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border-bottom: 2px solid #4361ee;
            color: #1e293b;
            padding: 0.8rem 0.5rem;
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
            padding: 0.7rem 0.5rem;
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

        /* ===== BUTTONS ===== */
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
        .btn-outline-primary {
            background: white;
            border: 1px solid #4361ee;
            color: #4361ee;
        }
        .btn-outline-primary:hover {
            background: #4361ee;
            color: white;
        }
        .btn-success {
            background: linear-gradient(135deg, #06d6a0, #059669);
            color: white;
        }
        .btn-info {
            background: linear-gradient(135deg, #4cc9f0, #3b82f6);
            color: white;
        }

        .export-btns .btn { margin-right: 0.5rem; margin-bottom: 0.5rem; }

        /* ===== AVATAR ===== */
        .avatar_img_sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        /* ===== GRADE BADGE ===== */
        .grade-badge {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            padding: 0.2rem 0.7rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.7rem;
            color: white;
            display: inline-block;
        }

        /* ===== DIVISION BADGE ===== */
        .div-badge {
            display: inline-block;
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .div-I { background: #06d6a0; color: white; }
        .div-II { background: #4cc9f0; color: white; }
        .div-III { background: #ffb703; color: #1e293b; }
        .div-IV { background: #ef476f; color: white; }
        .div-0 { background: #6c757d; color: white; }

        /* ===== SUBJECT GRADES CELL ===== */
        .subject-grade-cell {
            font-size: 0.75rem;
            max-width: 320px;
            word-break: break-word;
        }

        /* ===== GENDER FILTER ===== */
        .gender-filter .btn {
            margin-right: 0.5rem;
            background: white;
            border: 1px solid #dee2e6;
        }
        .gender-filter .btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }

        /* ===== SELECT2 ===== */
        .select2-container--default .select2-selection--single {
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.1);
            height: 42px;
            background: white;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            color: #1e293b;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow { top: 8px; }

        /* ===== DATA TABLE CONTROLS ===== */
        .dataTables_filter input {
            border-radius: 40px;
            padding: 0.3rem 1rem 0.3rem 2rem;
            border: 1px solid rgba(0,0,0,0.1);
            background: white;
            color: #1e293b;
            font-size: 0.85rem;
        }
        .dataTables_length select {
            border-radius: 30px;
            padding: 0.2rem 1.2rem 0.2rem 0.6rem;
            border: 1px solid rgba(0,0,0,0.1);
            background: white;
            color: #1e293b;
            font-size: 0.85rem;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .filter-card, .tile { padding: 1rem; }
            #srmsTable tbody td { padding: 0.5rem 0.3rem; }
            .btn-sm { padding: 0.25rem 0.7rem; font-size: 0.65rem; }
            .subject-grade-cell { max-width: 150px; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h1><?php echo htmlspecialchars($title); ?></h1></div>
            <div>
                <a href="academic/manage_results" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <form id="filterForm" method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="bi bi-building"></i> Select Class</label>
                    <select name="class" id="classSelect" class="form-control select2" required>
                        <option value="">-- Select class --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ($selected_class == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><i class="bi bi-calendar-week"></i> Select Term</label>
                    <select name="term" id="termSelect" class="form-control select2" required>
                        <option value="">-- Select term --</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>" <?php echo ($selected_term == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex flex-wrap align-items-center export-btns">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel"></i> Show Results</button>
                        <?php if ($selected_class && $selected_term): ?>
                            <a class="btn btn-success me-2" target="_blank"
                               href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?export=excel&class=<?php echo urlencode($selected_class); ?>&term=<?php echo urlencode($selected_term); ?>">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                            <a href="academic/import_bulk.php" class="btn btn-info">
                                <i class="bi bi-upload"></i> Import Results
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Gender Filter (only if students exist) -->
        <?php if ($selected_class && $selected_term && !empty($students)): ?>
        <div class="gender-filter mb-3 d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold me-2"><i class="bi bi-gender-ambiguous"></i> Filter by Gender:</span>
            <button class="btn btn-outline-primary btn-sm active" data-gender="all">All</button>
            <button class="btn btn-outline-primary btn-sm" data-gender="Male">Male</button>
            <button class="btn btn-outline-primary btn-sm" data-gender="Female">Female</button>
        </div>
        <?php endif; ?>

        <!-- Main Tile -->
        <div class="tile">
            <div class="tile-body">
                <?php if (!$selected_class || !$selected_term): ?>
                    <div class="alert alert-info">Select a class and academic term then click <strong>Show Results</strong>.</div>
                <?php elseif (empty($subjects)): ?>
                    <div class="alert alert-warning">No subject combinations found for this class.</div>
                <?php elseif (empty($students)): ?>
                    <div class="alert alert-warning">No students in this class.</div>
                <?php else: ?>

                    <?php if ($students_without_results > 0): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <span><strong><?php echo $students_without_results; ?> student(s)</strong> have no results entered for this term. Scores shown as 0.</span>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover" id="srmsTable">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>REG NO</th>
                                    <th>STUDENT NAME</th>
                                    <th>GENDER</th>
                                    <th>SUBJECT GRADES</th>
                                    <th>AVG</th>
                                    <th>GRADE</th>
                                    <th>REMARKS</th>
                                    <th>DIV</th>
                                    <th>PTS</th>
                                    <th>POS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            // Sort students by rank
                            usort($student_data, function($a, $b) use ($ranks) {
                                $rank_a = $ranks[$a['id']] ?? 999;
                                $rank_b = $ranks[$b['id']] ?? 999;
                                return $rank_a <=> $rank_b;
                            });

                            foreach ($student_data as $data):
                                $sid = $data['id'];
                                $gender = $data['gender'];
                                $rank = $ranks[$sid] ?? '';
                            ?>
                                <tr data-gender="<?= $gender ?>">
                                    <td width="10">
                                    <?php
                                    $imagePath = 'images/students/default.png';
                                    // find student from $students array
                                    $st = current(array_filter($students, fn($s) => $s['id'] == $sid));
                                    if ($st) {
                                        if (!empty($st['display_image']) && $st['display_image'] !== "DEFAULT") {
                                            $custom = 'images/students/' . $st['display_image'];
                                            if (file_exists(__DIR__ . '/../' . $custom)) $imagePath = $custom;
                                        } else {
                                            $genderImg = 'images/students/' . $gender . '.png';
                                            if (file_exists(__DIR__ . '/../' . $genderImg)) $imagePath = $genderImg;
                                        }
                                    }
                                    echo '<img src="' . htmlspecialchars($imagePath) . '" class="avatar_img_sm" alt="Photo">';
                                    ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($sid); ?></td>
                                    <td><?php echo htmlspecialchars($data['name']); ?></td>
                                    <td><?= $gender ?></td>
                                    <td class="subject-grade-cell"><?php echo htmlspecialchars($data['subject_grades']); ?></td>
                                    <td><?php echo $data['average']; ?></td>
                                    <td><span class="grade-badge"><?php echo htmlspecialchars($data['grade']); ?></span></td>
                                    <td><?php echo htmlspecialchars($data['remark']); ?></td>
                                    <td>
                                        <?php
                                        $div = $data['division'];
                                        $div_class = 'div-' . $div;
                                        if ($div == 'I') $div_class = 'div-I';
                                        elseif ($div == 'II') $div_class = 'div-II';
                                        elseif ($div == 'III') $div_class = 'div-III';
                                        elseif ($div == 'IV') $div_class = 'div-IV';
                                        else $div_class = 'div-0';
                                        ?>
                                        <span class="div-badge <?php echo $div_class; ?>"><?php echo $div; ?></span>
                                    </td>
                                    <td><?php echo $data['points']; ?></td>
                                    <td><?php echo $rank; ?></td>
                                    <td>
                                        <a href="academic/core/edit_result?std=<?php echo urlencode($sid); ?>&term=<?php echo $selected_term; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                                        <a onclick="del('academic/core/drop_results?src=bulk_results&std=<?php echo urlencode($sid); ?>&class=<?php echo $selected_class; ?>&term=<?php echo $selected_term; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
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
    <script src="cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="select2/dist/js/select2.full.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#srmsTable').DataTable({
                "sort": false,
                "pageLength": 25,
                "lengthChange": true,
                "dom": 'Bfrtip',
                "buttons": [
                    { extend: 'copy', text: '<i class="bi bi-files"></i> Copy' },
                    { extend: 'csv', text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV' },
                    { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel' },
                    { extend: 'print', text: '<i class="bi bi-printer"></i> Print' }
                ],
                "columnDefs": [
                    { "orderable": false, "targets": "_all" }
                ]
            });

            $('.select2').select2();

            $('#filterForm').on('submit', function(e) {
                var c = $('#classSelect').val();
                var t = $('#termSelect').val();
                if (!c || !t) {
                    e.preventDefault();
                    alert('Please select both Class and Term.');
                    return false;
                }
            });

            // Gender filter – column index 3 (GENDER)
            $('.gender-filter .btn').on('click', function() {
                $('.gender-filter .btn').removeClass('active');
                $(this).addClass('active');
                var gender = $(this).data('gender');
                if (gender === 'all') {
                    table.column(3).search('').draw();
                } else {
                    table.column(3).search('^' + gender + '$', true, false).draw();
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