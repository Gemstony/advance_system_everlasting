<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// ------------------------------------------------------------
// Fetch grade and division systems for global use
// ------------------------------------------------------------
try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $gradeSystem = $pdo->query("SELECT * FROM tbl_grade_system")->fetchAll();
    $divisionSystem = $pdo->query("SELECT * FROM tbl_division_system")->fetchAll();
    $GLOBALS['gradeSystem'] = $gradeSystem;
    $GLOBALS['divisionSystem'] = $divisionSystem;

    // Fetch terms and classes for dropdowns
    $all_terms = $pdo->query("SELECT id, name, status FROM tbl_terms ORDER BY id DESC")->fetchAll();
    $classes_list = $pdo->query("SELECT id, name FROM tbl_classes ORDER BY name")->fetchAll();
    $current_term = $pdo->query("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1")->fetch();
    $current_term_id = $current_term ? $current_term['id'] : 0;

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// ------------------------------------------------------------
// Helper functions
// ------------------------------------------------------------
function div_to_roman($div) {
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '0' => '0'];
    return isset($map[$div]) ? $map[$div] : $div;
}

function get_tz_grade_info($score) {
    global $gradeSystem;
    $name = get_grade($score, $gradeSystem);
    return [
        'name' => $name,
        'points' => get_division_point_from_grade($name)
    ];
}

// ------------------------------------------------------------
// Generate MKEKA report data
// ------------------------------------------------------------
function generate_mkeka_report($term_id, $mode, $class_ids, $pdo) {
    try {
        $class_names = [];
        if (!empty($class_ids)) {
            $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders)");
            $stmt->execute($class_ids);
            $class_name_rows = $stmt->fetchAll();
            $class_names = array_column($class_name_rows, 'name');
            $class_name_by_id = [];
            foreach ($class_name_rows as $class_name_row) {
                $class_name_by_id[(int)$class_name_row['id']] = $class_name_row['name'];
            }
        }

        // Get all subject combinations
        $stmt = $pdo->prepare("
            SELECT sc.id, sc.class, sc.subject, s.name as subject_name
            FROM tbl_subject_combinations sc
            JOIN tbl_subjects s ON sc.subject = s.id
            WHERE sc.status = 1
        ");
        $stmt->execute();
        $combinations = $stmt->fetchAll();

        $class_subjects = [];
        $all_subject_names = [];
        foreach ($combinations as $comb) {
            $class_id_list = unserialize($comb['class']);
            if (!is_array($class_id_list)) continue;
            foreach ($class_id_list as $cid) {
                if (in_array($cid, $class_ids)) {
                    $class_subjects[$cid][$comb['id']] = $comb['subject_name'];
                    $all_subject_names[$comb['id']] = $comb['subject_name'];
                }
            }
        }

        $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, fname, mname, lname, gender, class
            FROM tbl_students
            WHERE class IN ($placeholders) AND status = 1
            ORDER BY id
        ");
        $stmt->execute($class_ids);
        $students = $stmt->fetchAll();

        if (empty($students)) {
            throw new Exception('No students found in the selected classes.');
        }

        $studentIds = array_column($students, 'id');
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare("
            SELECT student, class, subject_combination, score
            FROM tbl_exam_results
            WHERE term = ? AND student IN ($studentPlaceholders)
        ");
        $params = array_merge([$term_id], $studentIds);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Build combo map for subject name by combination id and class
        $stmt = $pdo->query("
            SELECT sc.id, sc.class, sc.subject, s.name as subject_name
            FROM tbl_subject_combinations sc
            JOIN tbl_subjects s ON sc.subject = s.id
            WHERE sc.status = 1
        ");
        $all_combs = $stmt->fetchAll();
        $combo_map = [];
        foreach ($all_combs as $c) {
            $class_list = unserialize($c['class']);
            if (is_array($class_list)) {
                foreach ($class_list as $cid) {
                    $combo_map[$c['id']][$cid] = $c['subject_name'];
                }
            }
        }

        $student_results = [];
        $subject_scores = [];
        foreach ($results as $r) {
            $sid = $r['student'];
            $cid = $r['class'];
            $comb_id = $r['subject_combination'];
            $score = $r['score'];
            $subject_name = isset($combo_map[$comb_id][$cid]) ? $combo_map[$comb_id][$cid] : null;
            if ($subject_name) {
                $student_results[$sid][$cid][$subject_name] = $score;
                $subject_scores[$subject_name][] = $score;
            }
        }

        $division_summary = [];
        foreach (['F', 'M', 'T'] as $summary_gender) {
            $division_summary[$summary_gender] = [
                'I' => 0,
                'II' => 0,
                'III' => 0,
                'IV' => 0,
                '0' => 0,
                'INC' => 0,
                'total' => 0
            ];
        }

        $student_list = [];
        $all_student_averages = [];

        foreach ($students as $student) {
            $sid = $student['id'];
            $gender = $student['gender'];
            $class_id = $student['class'];
            $fullname = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);

            // Results retain the class in which they were recorded. A promoted
            // student can therefore have no rows under the current class even
            // though the historical results are still valid for this report.
            $subj_scores = [];
            if (isset($student_results[$sid])) {
                foreach ($student_results[$sid] as $historical_scores) {
                    foreach ($historical_scores as $subject_name => $score) {
                        $subj_scores[$subject_name] = $score;
                    }
                }
            }
            $scores_array = [];
            $details = [];

            $subjects = [];
            foreach ($subj_scores as $subj => $score) {
                if ($score !== null && $score !== '') {
                    $grade_info = get_tz_grade_info($score);
                    $details[] = $subj . "-'" . $grade_info['name'] . "'";
                    $scores_array[] = $score;
                    $subjects[] = ['name' => $subj, 'score' => $score];
                }
            }

            if (empty($scores_array)) {
                $student_list[] = [
                    'cno' => $sid,
                    'name' => $fullname,
                    'sex' => $gender,
                    'aggt' => 'ABS',
                    'div' => 'ABS',
                    'avg' => 'N/A',
                    'grade' => 'N/A',
                    'details' => 'ABS'
                ];
                continue;
            }

            $total_score = array_sum($scores_array);
            $num_subjects = count($scores_array);
            $average = round($total_score / $num_subjects, 1);

            $overall_grade_info = get_tz_grade_info($average);
            $grade = $overall_grade_info['name'];

            $combination = detect_combination_from_class_name($class_name_by_id[$class_id] ?? '');
            $acsee = calculate_acsee_result(
                $subjects,
                $combination,
                $GLOBALS['gradeSystem'],
                $GLOBALS['divisionSystem']
            );
            $points = $acsee['points'];
            $division = $acsee['division'] ?? 'INC';

            $student_list[] = [
                'cno' => $sid,
                'name' => $fullname,
                'sex' => $gender,
                'aggt' => $points === null ? '-' : $points,
                'div' => $division,
                'avg' => $average,
                'grade' => $grade,
                'details' => implode(' ', $details),
                'calculation_status' => $acsee['status']
            ];

            $all_student_averages[$sid] = [
                'name' => $fullname,
                'avg' => $average,
                'gender' => $gender,
                'cno' => $sid
            ];

            $gender_key = ($gender == 'Male') ? 'M' : 'F';
            if (isset($division_summary[$gender_key][$division])) {
                $division_summary[$gender_key][$division]++;
            }
            $division_summary[$gender_key]['total']++;
            if (isset($division_summary['T'][$division])) {
                $division_summary['T'][$division]++;
            }
            $division_summary['T']['total']++;
        }

        // Sort by NECTA division first. Lower AGGT points are better;
        // average is only used to break ties within the same division.
        $division_order = [
            'I' => 1,
            'II' => 2,
            'III' => 3,
            'IV' => 4,
            '0' => 5,
            'INC' => 6,
            'ABS' => 7
        ];
        $student_sorter = function ($a, $b) use ($division_order) {
            $a_division_order = $division_order[$a['div']] ?? 99;
            $b_division_order = $division_order[$b['div']] ?? 99;
            if ($a_division_order !== $b_division_order) {
                return $a_division_order <=> $b_division_order;
            }

            if ($a['div'] === 'ABS' && $b['div'] === 'ABS') {
                return strcasecmp($a['name'], $b['name']);
            }

            $a_points = is_numeric($a['aggt']) ? (int)$a['aggt'] : PHP_INT_MAX;
            $b_points = is_numeric($b['aggt']) ? (int)$b['aggt'] : PHP_INT_MAX;
            if ($a_points !== $b_points) {
                return $a_points <=> $b_points;
            }

            $a_average = is_numeric($a['avg']) ? (float)$a['avg'] : -1;
            $b_average = is_numeric($b['avg']) ? (float)$b['avg'] : -1;
            if ($a_average !== $b_average) {
                return $b_average <=> $a_average;
            }

            return strcasecmp($a['name'], $b['name']);
        };

        usort($student_list, $student_sorter);

        $rank = 1;
        $position = 0;
        $previous_rank_key = null;
        foreach ($student_list as &$s) {
            if ($s['aggt'] === 'ABS') {
                $s['rank'] = '-';
                continue;
            }

            $position++;
            $rank_key = implode('|', [
                $s['div'],
                is_numeric($s['aggt']) ? (int)$s['aggt'] : '-',
                is_numeric($s['avg']) ? (float)$s['avg'] : '-'
            ]);
            if ($previous_rank_key !== $rank_key) {
                $rank = $position;
            }
            $s['rank'] = $rank;
            $previous_rank_key = $rank_key;
        }
        unset($s);

        $subject_performance = [];
        foreach ($subject_scores as $subject => $scores) {
            if (empty($scores)) continue;
            $count = count($scores);
            $sum = array_sum($scores);
            $avg = round($sum / $count, 1);
            $min = min($scores);
            $max = max($scores);
            $points_sum = 0;
            foreach ($scores as $sc) {
                $info = get_tz_grade_info($sc);
                $points_sum += $info['points'];
            }
            $gpa = round($points_sum / $count, 2);
            $grades = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'E'=>0, 'S'=>0, 'F'=>0];
            foreach ($scores as $sc) {
                $info = get_tz_grade_info($sc);
                if (isset($grades[$info['name']])) {
                    $grades[$info['name']]++;
                }
            }
            $subject_performance[] = [
                'subject' => $subject,
                'count' => $count,
                'average' => $avg,
                'min' => $min,
                'max' => $max,
                'gpa' => $gpa,
                'grades' => $grades
            ];
        }
        usort($subject_performance, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        $sorted_by_avg = $all_student_averages;
        uasort($sorted_by_avg, function($a, $b) {
            return $b['avg'] <=> $a['avg'];
        });
        $top10 = array_slice($sorted_by_avg, 0, 10, true);
        $bottom10 = array_slice($sorted_by_avg, max(0, count($sorted_by_avg) - 10), 10, true);
        $bottom10 = array_reverse($bottom10, true);

        $all_avgs = array_column($student_list, 'avg');
        $all_avgs = array_filter($all_avgs, function($v) { return $v !== 'N/A' && is_numeric($v); });
        $overall_gpa = count($all_avgs) > 0 ? round(array_sum($all_avgs) / count($all_avgs), 2) : 0;

        $all_aggts = array_column($student_list, 'aggt');
        $all_aggts = array_filter($all_aggts, function($v) { return $v !== 'ABS' && is_numeric($v); });
        $overall_points_gpa = count($all_aggts) > 0 ? round(array_sum($all_aggts) / count($all_aggts), 2) : 0;

        $term_name = $pdo->query("SELECT name FROM tbl_terms WHERE id = $term_id")->fetchColumn();

        return [
            'division_summary' => $division_summary,
            'details' => $student_list,
            'subject_performance' => $subject_performance,
            'top10' => $top10,
            'bottom10' => $bottom10,
            'overall_gpa' => $overall_gpa,
            'overall_points_gpa' => $overall_points_gpa,
            'term_name' => $term_name,
            'class_names' => $class_names,
        ];

    } catch (Exception $e) {
        throw $e;
    }
}

// ------------------------------------------------------------
// Handle MKEKA REPORT POST request (AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mkeka_term']) && isset($_POST['mkeka_mode'])) {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == 1;
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $term_id = (int)$_POST['mkeka_term'];
        $mode = $_POST['mkeka_mode'];
        $class_ids = [];

        if ($mode === 'auto') {
            $pattern = trim($_POST['mkeka_pattern']);
            if (empty($pattern)) {
                throw new Exception('Please enter a class name pattern.');
            }
            $stmt = $pdo->prepare("SELECT id FROM tbl_classes WHERE name LIKE ?");
            $stmt->execute(['%' . $pattern . '%']);
            $class_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($class_ids)) {
                throw new Exception('No classes found matching the pattern: ' . htmlspecialchars($pattern));
            }
        } else {
            if (!isset($_POST['mkeka_classes']) || empty($_POST['mkeka_classes'])) {
                throw new Exception('Please select at least one class.');
            }
            $class_ids = $_POST['mkeka_classes'];
        }

        $report = generate_mkeka_report($term_id, $mode, $class_ids, $pdo);

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'report' => $report]);
            exit;
        } else {
            $mkeka_report = $report;
        }

    } catch (Exception $e) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } else {
            $mkeka_error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SRMS - MKEKA Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="../">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="icon" href="images/icon.ico">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link type="text/css" rel="stylesheet" href="loader/waitMe.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        /* ===== Mkeka specific styles ===== */
        .app-content { background: transparent !important; }
        .tile {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.6);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .tile:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.12); background: rgba(255,255,255,0.95); }
        .tile-title { font-weight: 700; color: #1e293b; margin-bottom: 1rem; font-size: 1.2rem; }

        .mkeka-card {
            border-left: 8px solid #ffb703;
            background: linear-gradient(145deg, #ffffff, #fefbf5);
            backdrop-filter: blur(12px);
            border-radius: 36px;
            padding: 2.2rem 2.8rem;
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .mkeka-card::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -30%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 183, 3, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .mkeka-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 50px -16px rgba(255, 183, 3, 0.25);
        }
        .mkeka-card .mkeka-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.7rem;
            font-weight: 800;
            color: #1e293b;
        }
        .mkeka-card .mkeka-title .badge {
            font-size: 0.7rem;
            padding: 0.35rem 1rem;
            background: linear-gradient(145deg, #ffb703, #f59e0b);
            color: #1e293b;
            border-radius: 40px;
            font-weight: 700;
        }
        .mkeka-card .mkeka-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-left: 3.8rem;
            margin-top: 0.1rem;
        }

        .mkeka-card .form-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .mkeka-card .form-select,
        .mkeka-card .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #ffffff;
        }
        .mkeka-card .form-select:focus,
        .mkeka-card .form-control:focus {
            border-color: #ffb703;
            box-shadow: 0 0 0 4px rgba(255, 183, 3, 0.12);
        }
        .mkeka-card .form-select[multiple] {
            min-height: 80px;
            padding: 0.4rem 0.6rem;
        }
        .mkeka-card .form-select[multiple] option {
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        .mkeka-card .form-select[multiple] option:hover,
        .mkeka-card .form-select[multiple] option:checked {
            background: #ffb703 linear-gradient(0deg, #ffb703 0%, #ffb703 100%);
            color: #1e293b;
        }
        .mkeka-card .form-check-label {
            font-weight: 500;
            color: #1e293b;
        }
        .mkeka-card .form-check-input:checked {
            background-color: #ffb703;
            border-color: #ffb703;
        }
        .mkeka-card .text-muted {
            color: #000 !important;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .mkeka-card .mkeka-actions .btn {
            border-radius: 60px;
            padding: 0.7rem 2rem;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.25s;
            border: none;
            letter-spacing: 0.3px;
        }
        .mkeka-card .mkeka-actions .btn-warning {
            background: linear-gradient(145deg, #ffb703, #f59e0b);
            color: #1e293b;
            box-shadow: 0 8px 20px rgba(255, 183, 3, 0.3);
        }
        .mkeka-card .mkeka-actions .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(255, 183, 3, 0.4);
        }
        .mkeka-card .mkeka-actions .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        .mkeka-card .mkeka-actions .btn-secondary:hover {
            background: #cbd5e1;
            transform: translateY(-3px);
        }

        /* Report styles */
        .mkeka-report .summary-table { font-size: 0.9rem; }
        .mkeka-report .summary-table td, .mkeka-report .summary-table th { padding: 0.3rem 0.5rem; }
        .mkeka-report .detail-table { font-size: 0.85rem; }
        .mkeka-report .detail-table td { padding: 0.2rem 0.3rem; vertical-align: middle; }
        .mkeka-report .detail-table .cno { font-weight: 600; }
        .mkeka-report .detail-table .subjects { word-break: break-word; max-width: 400px; }
        .mkeka-report .div-badge {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .div-I { background: #06d6a0; color: white; }
        .div-II { background: #4cc9f0; color: white; }
        .div-III { background: #ffb703; color: #1e293b; }
        .div-IV { background: #ef476f; color: white; }
        .div-0 { background: #6c757d; color: white; }
        .div-INC { background: #f4a261; color: #1e293b; }
        .div-ABS { background: #adb5bd; color: white; }

        .mkeka-actions .btn {
            border-radius: 60px;
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            transition: all 0.2s;
        }
        .mkeka-actions .btn:hover {
            transform: translateY(-2px);
        }

        #mkekaDetailTable_wrapper .dataTables_filter input {
            border-radius: 40px;
            padding: 0.3rem 1rem 0.3rem 2rem;
            border: 1px solid #e2e8f0;
        }
        #mkekaDetailTable_wrapper .dataTables_length select {
            border-radius: 30px;
            padding: 0.2rem 1.2rem 0.2rem 0.6rem;
            border: 1px solid #e2e8f0;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button {
            border-radius: 30px;
            padding: 0.25rem 0.8rem;
            margin: 0 2px;
            border: 1px solid #dee2e6;
            background: #ffffff;
            color: #1e293b !important;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button.current {
            background: #4361ee;
            color: white !important;
            border-color: #4361ee;
        }
        #mkekaDetailTable_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
        }

        #mkekaLoading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        #mkekaLoading .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .subject-perf-table { font-size: 0.85rem; }
        .subject-perf-table td, .subject-perf-table th { padding: 0.3rem 0.4rem; }
        .top-bottom-table { font-size: 0.85rem; }
        .top-bottom-table td, .top-bottom-table th { padding: 0.3rem 0.4rem; }

        @media print {
            .app-header, .app-sidebar, .mkeka-card form, .mkeka-actions .btn {
                display: none !important;
            }
            .app-content { padding: 0 !important; margin: 0 !important; }
            .mkeka-report { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .mkeka-report .detail-table { font-size: 0.7rem; }
            .mkeka-report .detail-table .subjects { max-width: 100%; }
            .mkeka-report .summary-table { font-size: 0.8rem; }
            body { background: white !important; }
        }
    </style>
</head>
<body>

    <?php require 'inc/header_academic.php'; ?>
    <?php require 'inc/sidebar_academic.php'; ?>

    <main class="app-content">
        <div class="app-title">
            <div><h1><i class="bi bi-file-spreadsheet me-2"></i> MKEKA Report</h1></div>
        </div>

        <!-- ================================================== -->
        <!-- MKEKA CARD                                         -->
        <!-- ================================================== -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="mkeka-card">
                    <div class="mkeka-title">
                        <i class="bi bi-file-spreadsheet"></i>
                        MKEKA – Result Compilation Report
                        <span class="badge">NECTA‑style</span>
                    </div>
                    <p class="mkeka-subtitle mb-4">
                        Generate a detailed report for selected classes and term. Choose <strong>auto‑selection</strong> by class name pattern or <strong>manually</strong> pick classes.
                    </p>

                    <form method="POST" action="" id="mkekaForm">
                        <input type="hidden" name="ajax" value="1">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-calendar-event me-2"></i> Term</label>
                                <select name="mkeka_term" class="form-select" required>
                                    <?php foreach ($all_terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>" <?php echo ($term['id'] == $current_term_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($term['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="bi bi-gear me-2"></i> Selection Mode</label>
                                <div class="d-flex gap-4 pt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mkeka_mode" id="mode_auto" value="auto" checked onchange="toggleMkekaMode()">
                                        <label class="form-check-label" for="mode_auto">Auto</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="mkeka_mode" id="mode_manual" value="manual" onchange="toggleMkekaMode()">
                                        <label class="form-check-label" for="mode_manual">Manual</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3" id="mkeka_auto_div">
                                <label class="form-label"><i class="bi bi-search me-2"></i> Class Pattern</label>
                                <input type="text" name="mkeka_pattern" class="form-control" placeholder="e.g. FORM FIVE" value="FORM FIVE">
                                <small style="color:#000; font-size:0.8rem; font-weight:500; margin-top:0.2rem; display:block;">All classes containing this text will be selected.</small>
                            </div>
                            <div class="col-md-3 d-none" id="mkeka_manual_div">
                                <label class="form-label"><i class="bi bi-list-check me-2"></i> Select Classes</label>
                                <select name="mkeka_classes[]" class="form-select" multiple style="min-height: 80px; border-color: #ffb703;">
                                    <?php foreach ($classes_list as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:#000; font-size:0.8rem; font-weight:500; margin-top:0.2rem; display:block;">Hold <kbd>Ctrl</kbd> to select multiple classes.</small>
                            </div>
                            <div class="col-md-12 d-flex gap-3 mkeka-actions mt-2">
                                <button type="submit" class="btn btn-warning" id="generateMkekaBtn"><i class="bi bi-file-earmark-arrow-down me-2"></i> Generate Report</button>
                                <button type="reset" class="btn btn-secondary" id="clearMkeka"><i class="bi bi-eraser me-2"></i> Clear Selections</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="mkekaLoading" class="row mb-4">
            <div class="col-md-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Generating report...</span>
                </div>
                <p class="mt-2">Generating MKEKA Report, please wait...</p>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- MKEKA REPORT OUTPUT – dynamic container            -->
        <!-- ================================================== -->
        <div id="mkekaReportContainer"></div>

    </main>

    <script src="js/jquery-3.7.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
    <script src="loader/waitMe.js"></script>
    <script src="js/forms.js"></script>
    <script src="js/sweetalert2@11.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.js"></script>

    <script>
    $(document).ready(function() {

        // ------------------------------------------------------------------
        // Toggle Mkeka mode
        // ------------------------------------------------------------------
        window.toggleMkekaMode = function() {
            var mode = $('input[name="mkeka_mode"]:checked').val();
            if (mode === 'auto') {
                $('#mkeka_auto_div').removeClass('d-none');
                $('#mkeka_manual_div').addClass('d-none');
            } else {
                $('#mkeka_auto_div').addClass('d-none');
                $('#mkeka_manual_div').removeClass('d-none');
            }
        };
        toggleMkekaMode();

        // Clear button: reset the form and clear report container
        $('#clearMkeka').on('click', function(e) {
            e.preventDefault();
            $('#mkekaForm')[0].reset();
            toggleMkekaMode();
            $('#mkekaReportContainer').empty().hide();
            $('#mkekaLoading').hide();
        });

        // ------------------------------------------------------------------
        // AJAX form submission for MKEKA report
        // ------------------------------------------------------------------
        $('#mkekaForm').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var formData = form.serialize();

            $('#mkekaLoading').show();
            $('#mkekaReportContainer').empty().hide();

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    $('#mkekaLoading').hide();
                    if (response.success) {
                        renderMkekaReport(response.report);
                    } else {
                        Swal.fire('Error', response.message || 'Failed to generate report.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $('#mkekaLoading').hide();
                    Swal.fire('Error', 'Server error: ' + error, 'error');
                }
            });
        });

        // ------------------------------------------------------------------
        // Render MKEKA report from JSON data - split into two cards
        // ------------------------------------------------------------------
        function renderMkekaReport(report) {
            var container = $('#mkekaReportContainer');
            container.empty();

            // ------------------- CARD 1: Summary, Subject Performance, Top/Bottom -------------------
            var html1 = '<div class="row mb-4" id="mkekaReportCard1">';
            html1 += '<div class="col-md-12">';
            html1 += '<div class="tile mkeka-report">';

            // Header
            html1 += '<div class="d-flex justify-content-between align-items-start flex-wrap">';
            html1 += '<h4 class="tile-title" style="margin-bottom:0.2rem;">';
            html1 += '<i class="bi bi-printer me-2"></i> MKEKA Report';
            html1 += '<span style="font-weight:700; color:#000; font-size:1.1rem; margin-left:0.5rem;">';
            html1 += escapeHtml(report.term_name);
            if (report.class_names && report.class_names.length > 0) {
                html1 += ' – ' + escapeHtml(report.class_names.join(', '));
            }
            html1 += '</span></h4>';
            html1 += '<div class="mkeka-actions d-flex gap-2">';
            html1 += '<button class="btn btn-sm btn-success" id="exportExcelBtn"><i class="bi bi-file-earmark-excel"></i> Excel</button>';
            html1 += '<button class="btn btn-sm btn-secondary" id="closeMkekaBtn"><i class="bi bi-x-circle"></i> Close</button>';
            html1 += '</div></div>';

            // Division Performance Summary
            html1 += '<h5 class="mt-3">Division Performance Summary</h5>';
            html1 += '<div class="table-responsive">';
            html1 += '<table class="table table-bordered summary-table" style="max-width:700px;">';
            html1 += '<thead><tr><th>Sex</th><th>I</th><th>II</th><th>III</th><th>IV</th><th>0</th><th>Incomplete</th><th>Total</th></tr></thead>';
            html1 += '<tbody>';
            var sex_labels = {'F':'Female','M':'Male','T':'Total'};
            $.each(sex_labels, function(key, label) {
                var row = report.division_summary[key];
                html1 += '<tr>';
                html1 += '<td><strong>' + label + '</strong></td>';
                html1 += '<td>' + (row['I'] || 0) + '</td>';
                html1 += '<td>' + (row['II'] || 0) + '</td>';
                html1 += '<td>' + (row['III'] || 0) + '</td>';
                html1 += '<td>' + (row['IV'] || 0) + '</td>';
                html1 += '<td>' + (row['0'] || 0) + '</td>';
                html1 += '<td>' + (row['INC'] || 0) + '</td>';
                html1 += '<td><strong>' + (row['total'] || 0) + '</strong></td>';
                html1 += '</tr>';
            });
            html1 += '</tbody></table></div>';

            // Subject Performance
            html1 += '<h5 class="mt-4">Subject Performance Summary</h5>';
            html1 += '<div class="table-responsive">';
            html1 += '<table class="table table-bordered subject-perf-table">';
            html1 += '<thead><tr><th>Subject</th><th>Students</th><th>Average</th><th>Highest</th><th>Lowest</th><th>GPA</th>';
            html1 += '<th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>S</th><th>F</th></tr></thead>';
            html1 += '<tbody>';
            if (report.subject_performance && report.subject_performance.length > 0) {
                $.each(report.subject_performance, function(idx, subj) {
                    html1 += '<tr>';
                    html1 += '<td><strong>' + escapeHtml(subj.subject) + '</strong></td>';
                    html1 += '<td>' + subj.count + '</td>';
                    html1 += '<td>' + subj.average + '</td>';
                    html1 += '<td>' + subj.max + '</td>';
                    html1 += '<td>' + subj.min + '</td>';
                    html1 += '<td>' + subj.gpa + '</td>';
                    var grades = subj.grades || {};
                    html1 += '<td>' + (grades['A'] || 0) + '</td>';
                    html1 += '<td>' + (grades['B'] || 0) + '</td>';
                    html1 += '<td>' + (grades['C'] || 0) + '</td>';
                    html1 += '<td>' + (grades['D'] || 0) + '</td>';
                    html1 += '<td>' + (grades['E'] || 0) + '</td>';
                    html1 += '<td>' + (grades['S'] || 0) + '</td>';
                    html1 += '<td>' + (grades['F'] || 0) + '</td>';
                    html1 += '</tr>';
                });
            } else {
                html1 += '<tr><td colspan="13" class="text-center">No subject data available.</td></tr>';
            }
            html1 += '</tbody></table></div>';

            html1 += '<div class="mt-2"><strong>Overall GPA (Average):</strong> ' + (report.overall_gpa || 'N/A') + ' &nbsp;|&nbsp; ';
            html1 += '<strong>Overall Points GPA (AGGT):</strong> ' + (report.overall_points_gpa || 'N/A') + '</div>';

            // Top 10 & Bottom 10
            html1 += '<h5 class="mt-4">Top 10 & Bottom 10 Students</h5>';
            html1 += '<div class="row">';
            html1 += '<div class="col-md-6">';
            html1 += '<h6 class="text-success">Top 10 Students</h6>';
            html1 += '<div class="table-responsive">';
            html1 += '<table class="table table-striped top-bottom-table">';
            html1 += '<thead><tr><th>Rank</th><th>CNO</th><th>Name</th><th>Avg</th></tr></thead>';
            html1 += '<tbody>';
            var topList = report.top10 || {};
            var rank = 1;
            $.each(topList, function(cno, student) {
                html1 += '<tr><td>' + rank + '</td><td>' + escapeHtml(cno) + '</td><td>' + escapeHtml(student.name) + '</td><td>' + student.avg + '</td></tr>';
                rank++;
            });
            if (rank === 1) html1 += '<tr><td colspan="4" class="text-center">No data</td></tr>';
            html1 += '</tbody></table></div></div>';

            html1 += '<div class="col-md-6">';
            html1 += '<h6 class="text-danger">Bottom 10 Students</h6>';
            html1 += '<div class="table-responsive">';
            html1 += '<table class="table table-striped top-bottom-table">';
            html1 += '<thead><tr><th>Rank</th><th>CNO</th><th>Name</th><th>Avg</th></tr></thead>';
            html1 += '<tbody>';
            var bottomList = report.bottom10 || {};
            var bottomRank = 1;
            $.each(bottomList, function(cno, student) {
                html1 += '<tr><td>' + bottomRank + '</td><td>' + escapeHtml(cno) + '</td><td>' + escapeHtml(student.name) + '</td><td>' + student.avg + '</td></tr>';
                bottomRank++;
            });
            if (bottomRank === 1) html1 += '<tr><td colspan="4" class="text-center">No data</td></tr>';
            html1 += '</tbody></table></div></div></div>';

            html1 += '</div></div></div>';

            // ------------------- CARD 2: Detailed Results (with S/N) -------------------
            var html2 = '<div class="row mb-4" id="mkekaReportCard2">';
            html2 += '<div class="col-md-12">';
            html2 += '<div class="tile mkeka-report">';

            html2 += '<h5 class="tile-title" style="margin-bottom:0.5rem;"><i class="bi bi-table me-2"></i> Detailed Results</h5>';
            html2 += '<div class="table-responsive">';
            html2 += '<table class="table table-striped table-hover detail-table" id="mkekaDetailTable">';
            html2 += '<thead><tr><th>S/N</th><th>CNO</th><th>Name</th><th>Sex</th><th>Rank</th><th>Avg</th><th>Grade</th><th>AGGT</th><th>DIV</th><th>Detailed Subjects</th></tr></thead>';
            html2 += '<tbody>';
            // Add serial number counter
            $.each(report.details, function(idx, student) {
                var sn = idx + 1;
                html2 += '<tr>';
                html2 += '<td>' + sn + '</td>';
                html2 += '<td class="cno">' + escapeHtml(student.cno) + '</td>';
                html2 += '<td>' + escapeHtml(student.name) + '</td>';
                html2 += '<td>' + (student.sex === 'Male' ? 'M' : 'F') + '</td>';
                html2 += '<td>' + (student.rank || '-') + '</td>';
                html2 += '<td>';
                if (student.avg && !isNaN(student.avg)) {
                    html2 += Number(student.avg).toFixed(1);
                } else {
                    html2 += 'N/A';
                }
                html2 += '</td>';
                html2 += '<td>';
                if (student.grade && student.grade !== 'N/A') {
                    var color = getGradeColor(student.grade);
                    html2 += '<span class="badge bg-' + color + '">' + student.grade + '</span>';
                } else {
                    html2 += 'N/A';
                }
                html2 += '</td>';
                html2 += '<td><strong>' + (student.aggt === 'ABS' ? 'ABS' : student.aggt) + '</strong></td>';
                html2 += '<td>';
                if (student.div === 'ABS') {
                    html2 += '<span class="div-badge div-ABS">ABS</span>';
                } else {
                    var roman = div_to_roman(student.div);
                    html2 += '<span class="div-badge div-' + roman + '">' + roman + '</span>';
                }
                html2 += '</td>';
                html2 += '<td class="subjects">' + escapeHtml(student.details) + '</td>';
                html2 += '</tr>';
            });
            html2 += '</tbody></table></div>';

            html2 += '</div></div></div>';

            // Append both cards to container
            container.append(html1);
            container.append(html2);
            container.slideDown(400);

            // Event handlers for buttons (only once)
            container.find('#exportExcelBtn').on('click', function() {
                exportMultiSheetExcel(report);
            });
            container.find('#closeMkekaBtn').on('click', function() {
                container.slideUp(300, function() {
                    container.empty();
                });
            });

            // Initialize DataTable on the detail table (only if it exists)
            try {
                if ($('#mkekaDetailTable').length) {
                    $('#mkekaDetailTable').DataTable({
                        "pageLength": 10,
                        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                        "order": [[4, 'asc']], // rank column is now at index 4 (0-based: S/N=0, CNO=1, Name=2, Sex=3, Rank=4)
                        "columnDefs": [
                            { "orderable": false, "targets": [0, 1, 2, 3, 5, 6, 7, 8, 9] },
                            { "width": "5%", "targets": [0] },
                            { "width": "8%", "targets": [1] },
                            { "width": "18%", "targets": [2] },
                            { "width": "5%", "targets": [3] },
                            { "width": "6%", "targets": [4] },
                            { "width": "8%", "targets": [5] },
                            { "width": "8%", "targets": [6] },
                            { "width": "8%", "targets": [7] },
                            { "width": "8%", "targets": [8] },
                            { "width": "auto", "targets": [9] }
                        ],
                        "language": {
                            "search": "Filter:",
                            "paginate": {
                                "previous": "<i class='bi bi-chevron-left'></i>",
                                "next": "<i class='bi bi-chevron-right'></i>"
                            }
                        }
                    });
                }
            } catch (e) {
                console.warn('DataTable could not be initialised:', e);
            }
        }

        function escapeHtml(unsafe) {
            if (unsafe === undefined || unsafe === null) return '';
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function getGradeColor(grade) {
            var map = {
                'A': 'success',
                'B': 'info',
                'C': 'warning',
                'D': 'secondary',
                'E': 'dark',
                'S': 'primary',
                'F': 'danger'
            };
            return map[grade] || 'secondary';
        }

        function div_to_roman(div) {
            var map = {'1':'I','2':'II','3':'III','4':'IV','0':'0'};
            return map[div] || div;
        }

        // ------------------------------------------------------------------
        // Multi-sheet Excel export (add S/N column)
        // ------------------------------------------------------------------
        function exportMultiSheetExcel(reportData) {
            if (!reportData) {
                Swal.fire('No data', 'Please generate the report first.', 'warning');
                return;
            }

            var wb = XLSX.utils.book_new();

            var rows1 = [];
            // Add S/N column header
            rows1.push(['S/N', 'CNO', 'Name', 'Sex', 'Rank', 'Avg', 'Grade', 'AGGT', 'DIV', 'Detailed Subjects']);
            $.each(reportData.details, function(idx, s) {
                var divDisplay = (s.div === 'ABS') ? 'ABS' : div_to_roman(s.div);
                rows1.push([
                    (idx + 1),
                    s.cno || '',
                    s.name || '',
                    (s.sex === 'Male' ? 'M' : 'F'),
                    s.rank || '-',
                    (s.avg && !isNaN(s.avg)) ? Number(s.avg).toFixed(1) : 'N/A',
                    s.grade || 'N/A',
                    s.aggt || 'ABS',
                    divDisplay,
                    s.details || ''
                ]);
            });
            var ws1 = XLSX.utils.aoa_to_sheet(rows1);
            XLSX.utils.book_append_sheet(wb, ws1, 'MKEKA Report');

            var rows2 = [];
            rows2.push(['Subject', 'Students', 'Average', 'Highest', 'Lowest', 'GPA', 'A', 'B', 'C', 'D', 'E', 'S', 'F']);
            if (reportData.subject_performance) {
                $.each(reportData.subject_performance, function(idx, subj) {
                    var grades = subj.grades || {};
                    rows2.push([
                        subj.subject || '',
                        subj.count || 0,
                        subj.average || 0,
                        subj.max || 0,
                        subj.min || 0,
                        subj.gpa || 0,
                        grades['A'] || 0,
                        grades['B'] || 0,
                        grades['C'] || 0,
                        grades['D'] || 0,
                        grades['E'] || 0,
                        grades['S'] || 0,
                        grades['F'] || 0
                    ]);
                });
            }
            var ws2 = XLSX.utils.aoa_to_sheet(rows2);
            XLSX.utils.book_append_sheet(wb, ws2, 'Subject Performance');

            var rows3 = [];
            rows3.push(['Category', 'Rank', 'CNO', 'Name', 'Average']);
            var rank = 1;
            var topList = reportData.top10 || {};
            $.each(topList, function(cno, student) {
                rows3.push(['Top 10', rank, cno, student.name || '', student.avg || '']);
                rank++;
            });
            var bottomRank = 1;
            var bottomList = reportData.bottom10 || {};
            $.each(bottomList, function(cno, student) {
                rows3.push(['Bottom 10', bottomRank, cno, student.name || '', student.avg || '']);
                bottomRank++;
            });
            var ws3 = XLSX.utils.aoa_to_sheet(rows3);
            XLSX.utils.book_append_sheet(wb, ws3, 'Top & Bottom');

            var wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
            var blob = new Blob([wbout], { type: 'application/octet-stream' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'mkeka_report.xlsx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }

    });
    </script>
    <?php require_once('const/check-reply.php'); ?>
</body>
</html>