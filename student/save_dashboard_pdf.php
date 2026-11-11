<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student.");

$school_name = defined('WBName') ? WBName : 'Everlasting Academy';
$school_logo = defined('WBLogo') ? WBLogo : '';

// Get the term from URL if provided, otherwise use the latest term
$requested_term = isset($_GET['term']) ? (int)$_GET['term'] : null;

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Student info
    $stmt = $pdo->prepare("SELECT s.*, c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON s.class = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) die("Student not found.");

    // Grading system
    $grading = $pdo->query("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC")->fetchAll();
    $GLOBALS['gradeSystem'] = $grading;

    // Division system
    $divisionSystem = $pdo->query("SELECT * FROM tbl_division_system")->fetchAll();
    $GLOBALS['divisionSystem'] = $divisionSystem;

    // Determine which term to use for the main report
    if ($requested_term) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt->execute([$student_id, $requested_term]);
        if ($stmt->fetchColumn() == 0) die("No results found for the requested term.");
        $term_id = $requested_term;
        $stmt = $pdo->prepare("SELECT name FROM tbl_terms WHERE id = ?");
        $stmt->execute([$term_id]);
        $term_name = $stmt->fetchColumn();
        if (!$term_name) die("Invalid term.");
        $selected_term = ['id' => $term_id, 'name' => $term_name];
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT t.id, t.name FROM tbl_exam_results r LEFT JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY t.id DESC LIMIT 1");
        $stmt->execute([$student_id]);
        $selected_term = $stmt->fetch();
        if (!$selected_term) die("No results found.");
        $term_id = $selected_term['id'];
    }

    // ---- Helper function to get full scores for a term and class ----
    function getFullScoresForTerm($pdo, $student_id, $term_id, $class_id, $subjects_by_class) {
        $subjects = $subjects_by_class[$class_id] ?? [];
        if (empty($subjects)) return [];

        $stmt = $pdo->prepare("SELECT subject_combination, score FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt->execute([$student_id, $term_id]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['subject_combination']] = $row['score'];
        }

        $scores = [];
        foreach ($subjects as $subj) {
            $scores[] = array_key_exists($subj['id'], $results)
                ? (float)$results[$subj['id']]
                : null;
        }
        return $scores;
    }

    // Pre‑fetch all subject combinations and group by class (same as HTML results page)
    $stmt = $pdo->query("
        SELECT sc.id, sc.class, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
    ");
    $allCombinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subjects_by_class = [];
    foreach ($allCombinations as $comb) {
        $classList = unserialize($comb['class']);
        if (is_array($classList)) {
            foreach ($classList as $cid) {
                $subjects_by_class[$cid][] = [
                    'id'   => $comb['id'],
                    'name' => $comb['subject_name']
                ];
            }
        }
    }

    // ---- Main term (the one we are generating the PDF for) ----
    // Get the class for this term
    $stmt = $pdo->prepare("SELECT DISTINCT class FROM tbl_exam_results WHERE student = ? AND term = ?");
    $stmt->execute([$student_id, $term_id]);
    $classes_for_term = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($classes_for_term)) die("No class found for this term.");
    $class_id = $classes_for_term[0]; // use the first class if multiple (should not happen)

    // Build the complete scores array for the main term
    $main_scores = getFullScoresForTerm($pdo, $student_id, $term_id, $class_id, $subjects_by_class);
    if (empty($main_scores)) die("No subjects defined for the selected class.");

    // Build subject list for display (with names)
    $subjects_for_class = $subjects_by_class[$class_id] ?? [];
    $latest_results = [];
    $stmt = $pdo->prepare("SELECT subject_combination, score FROM tbl_exam_results WHERE student = ? AND term = ?");
    $stmt->execute([$student_id, $term_id]);
    $results_map = [];
    while ($row = $stmt->fetch()) {
        $results_map[$row['subject_combination']] = $row['score'];
    }
    foreach ($subjects_for_class as $subj) {
        $score = array_key_exists($subj['id'], $results_map)
            ? (float)$results_map[$subj['id']]
            : null;
        $latest_results[] = [
            'subject_combination' => $subj['id'],
            'subject_name'        => $subj['name'],
            'score'               => $score
        ];
    }

    // Process main term
    $subject_scores = [];
    $scores = array_values(array_filter($main_scores, function ($score) {
        return $score !== null && $score !== '';
    }));
    $pass_count = 0;
    $fail_count = 0;
    $failing_subjects = [];
    $grade_counts = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0,'S'=>0,'F'=>0];
    $subject_ids = [];

    foreach ($latest_results as $res) {
        $score = $res['score'];
        if ($score === null || $score === '') {
            continue;
        }
        $subject_scores[] = [
            'name'       => $res['subject_name'],
            'score'      => $score,
            'comb_id'    => $res['subject_combination']
        ];
        $subject_ids[] = $res['subject_combination'];
        if ($score >= 50) {
            $pass_count++;
        } else {
            $fail_count++;
            $failing_subjects[] = $res['subject_name'];
        }
        // Grade distribution (based on numeric score, including zero)
        $grade = get_grade($score, $grading);
        $grade_counts[$grade] = isset($grade_counts[$grade]) ? $grade_counts[$grade] + 1 : 1;

        $combination = detect_combination_from_class_name($student['class_name'] ?? '');
        $acseeResult = $combination
            ? calculate_acsee_result($subject_scores, $combination, $grading, $divisionSystem)
            : null;
    }

    // Class averages for comparison (use the same subject list)
    $class_avg = [];
    if (!empty($subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT subject_combination, AVG(score) AS avg_score
            FROM tbl_exam_results
            WHERE class = ? AND term = ? AND subject_combination IN ($placeholders)
            GROUP BY subject_combination
        ");
        $params = array_merge([$class_id, $term_id], $subject_ids);
        $stmt->execute($params);
        $class_avg_rows = $stmt->fetchAll();
        foreach ($class_avg_rows as $row) {
            $class_avg[$row['subject_combination']] = round($row['avg_score']);
        }
    }

    $total_score = array_sum($scores);
    $subject_count = count($scores);
    $avg = $subject_count ? round($total_score / $subject_count, 1) : 0;
    $pass_percent = $subject_count ? round(($pass_count / $subject_count) * 100) : 0;
    $fail_percent = 100 - $pass_percent;

    $overall_grade = $subject_count ? get_grade($avg, $grading) : 'N/A';
    $overall_remark = $subject_count ? get_grade_remark($avg, $grading) : 'N/A';

    $division = $acseeResult['division'] ?? null;
    $points = $acseeResult['points'] ?? null;

    usort($subject_scores, fn($a,$b) => $b['score'] <=> $a['score']);
    $top_subjects = array_slice($subject_scores, 0, 3);
    $bottom_subjects = array_slice($subject_scores, -3, 3);

    // ---- Term summary for all terms (using the same full‑subject method) ----
    $stmt = $pdo->prepare("SELECT DISTINCT t.id, t.name FROM tbl_exam_results r LEFT JOIN tbl_terms t ON r.term = t.id WHERE r.student = ? ORDER BY t.id DESC");
    $stmt->execute([$student_id]);
    $all_terms = $stmt->fetchAll();
    $term_summary = [];
    foreach ($all_terms as $t) {
        // Get the class for this term
        $stmt2 = $pdo->prepare("SELECT DISTINCT class FROM tbl_exam_results WHERE student = ? AND term = ?");
        $stmt2->execute([$student_id, $t['id']]);
        $term_classes = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        if (empty($term_classes)) continue;
        $term_class = $term_classes[0]; // first class

        // Get full scores for this term (including all subjects of that class)
        $term_scores = getFullScoresForTerm($pdo, $student_id, $t['id'], $term_class, $subjects_by_class);
        if (empty($term_scores)) continue;

        $term_subjects = [];
        foreach (($subjects_by_class[$term_class] ?? []) as $index => $subject) {
            $term_subjects[] = [
                'name' => $subject['name'],
                'score' => $term_scores[$index] ?? null
            ];
        }
        $term_student = calculate_acsee_result(
            $term_subjects,
            detect_combination_from_class_name($student['class_name'] ?? '') ?: '',
            $grading,
            $divisionSystem
        );

        $term_scores_present = array_values(array_filter($term_scores, function ($score) {
            return $score !== null && $score !== '';
        }));
        $term_avg = count($term_scores_present)
            ? round(array_sum($term_scores_present) / count($term_scores_present), 1)
            : 0;
        $term_summary[] = [
            'name' => $t['name'],
            'avg' => $term_avg,
            'points' => $term_student['points'] ?? null,
            'division' => $term_student['division'] ?? null,
        ];
    }

    // Announcements
    $stmt = $pdo->prepare("SELECT * FROM tbl_announcements WHERE level = '1' OR level = '2' ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// ---------- Generate Detailed Advice (unchanged) ----------
function generateDetailedAdvice($studentName, $avg, $grade, $pass_percent, $fail_percent, $pass_count, $fail_count, $failing_subjects, $top_subjects, $bottom_subjects, $previous_term_avg, $class_avg, $latest_results) {
    $advice = "";
    
    if ($avg >= 75) $rating = "[EXCELLENT]";
    elseif ($avg >= 60) $rating = "[VERY GOOD]";
    elseif ($avg >= 45) $rating = "[SATISFACTORY]";
    else $rating = "[NEEDS IMPROVEMENT]";
    
    $advice .= "===========================================================\n";
    $advice .= "Student: " . htmlspecialchars($studentName) . "\n";
    $advice .= "Overall Average: {$avg}%  |  Rating: {$rating}\n";
    $advice .= "===========================================================\n\n";
    
    $advice .= "Dear " . htmlspecialchars($studentName) . ",\n\n";
    
    if ($avg >= 75) {
        $advice .= "Outstanding work! Your average of {$avg}% is excellent. You have clearly mastered the material. ";
    } elseif ($avg >= 60) {
        $advice .= "Very good performance! With an average of {$avg}%, you're on the right track. Keep pushing to reach the top. ";
    } elseif ($avg >= 45) {
        $advice .= "You have achieved a satisfactory average of {$avg}%. There is room for improvement, and with focused effort you can raise your grades. ";
    } else {
        $advice .= "Your average of {$avg}% indicates that you need to invest more time and effort. Don't be discouraged – this is a chance to grow. ";
    }
    
    $advice .= "You passed {$pass_count} out of " . count($latest_results) . " subjects ({$pass_percent}% pass rate). ";
    if ($fail_count > 0) $advice .= "You had {$fail_count} subjects where you did not meet the pass mark ({$fail_percent}% fail rate).\n\n";
    else $advice .= "Congratulations on passing all subjects!\n\n";
    
    if (!empty($top_subjects)) {
        $advice .= "[+] STRENGTHS\n";
        $advice .= "Your top performing subjects are:\n";
        foreach ($top_subjects as $sub) {
            $advice .= "   * " . htmlspecialchars($sub['name']) . " – " . $sub['score'] . "%\n";
        }
        $advice .= "These show your strong understanding. Consider helping classmates or taking on advanced tasks to deepen your knowledge.\n\n";
    }
    
    if (!empty($bottom_subjects)) {
        $advice .= "[!] AREAS FOR IMPROVEMENT\n";
        $advice .= "Your lowest scoring subjects are:\n";
        foreach ($bottom_subjects as $sub) {
            $advice .= "   * " . htmlspecialchars($sub['name']) . " – " . $sub['score'] . "%\n";
        }
        $advice .= "These subjects pulled down your average. ";
        $weak_advice = [];
        foreach ($bottom_subjects as $sub) {
            if (isset($class_avg[$sub['comb_id']])) {
                $class_avg_score = $class_avg[$sub['comb_id']];
                $diff = $sub['score'] - $class_avg_score;
                if ($diff < 0) {
                    $weak_advice[] = htmlspecialchars($sub['name']) . " is " . abs($diff) . "% below class average";
                }
            }
        }
        if (!empty($weak_advice)) {
            $advice .= "Specifically, " . implode(" and ", $weak_advice) . ". ";
        }
        $advice .= "Dedicate extra time, ask for help, and practice regularly.\n\n";
    }
    
    // Trend analysis using previous term from term_summary
    if ($previous_term_avg !== null) {
        $diff = $avg - $previous_term_avg;
        $advice .= "[?] PROGRESS TREND\n";
        if ($diff > 5) {
            $advice .= "Your average has increased by {$diff} points compared to the previous term. Excellent improvement! Keep up the effective study habits.\n\n";
        } elseif ($diff < -5) {
            $advice .= "Your average has decreased by " . abs($diff) . " points. Let's identify the challenges you faced. Review your study schedule and seek support.\n\n";
        } else {
            $advice .= "Your performance is stable compared to the previous term. To move forward, try new revision techniques or study groups.\n\n";
        }
    }
    
    $advice .= "[*] NEXT STEPS\n";
    if ($avg >= 75) {
        $advice .= "Aim for 85%+ next term by tackling challenging problems and mentoring others. Your discipline is paying off!\n";
    } elseif ($avg >= 50) {
        if ($fail_count > 0) {
            $advice .= "Create a study plan that allocates 2 extra hours per week to your weakest subjects. Use online resources, ask teachers, and practice. You have the ability to turn these into strengths.\n";
        } else {
            $advice .= "You have a solid foundation. To elevate your grades, focus on consistent revision and attempt higher-order questions. Small daily improvements will lead to outstanding results.\n";
        }
    } else {
        $advice .= "It's time for a fresh start. Meet with your teachers to understand where you lost marks. Create a simple daily revision timetable and stick to it. Consider joining extra classes or study groups. Remember, every expert was once a beginner.\n";
    }
    
    $advice .= "\nWe believe in your potential. Stay focused and keep striving for excellence!\n";
    
    return nl2br($advice);
}

// Prepare data for advice
$previous_term_avg = null;
if (count($term_summary) > 1) $previous_term_avg = $term_summary[1]['avg'];
$studentName = $student['fname'] . ' ' . $student['lname'];
$detailedAdvice = generateDetailedAdvice(
    $studentName, $avg, $overall_grade, $pass_percent, $fail_percent,
    $pass_count, $fail_count, $failing_subjects, $top_subjects,
    $bottom_subjects, $previous_term_avg, $class_avg, $latest_results
);

// ---------- PDF Setup ----------
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($school_name);
$pdf->SetTitle($school_name . ' - Student Dashboard');
$pdf->SetSubject('Student Academic Dashboard');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetAutoPageBreak(true, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('helvetica', '', 11);
$pdf->AddPage();

function getAbsPath($rel) { return realpath(__DIR__ . '/../') . '/' . $rel; }

$logoPath = 'images/logo/' . $school_logo;
$logoAbs = getAbsPath($logoPath);
if (!empty($school_logo) && file_exists($logoAbs)) {
    $pdf->Image($logoAbs, 10, 8, 30, 0, '', '', '', false, 300, '', false, false, 0);
}

$date = date('d F Y');
$pdf->writeHTML('<div style="text-align:center;"><h1 style="color:#2c3e50;">'.htmlspecialchars($school_name).'</h1><h3 style="color:#3498db;">Student Academic Dashboard</h3><p style="font-size:9pt; color:#7f8c8d;">Generated on: '.$date.'</p><hr style="border-top:1px solid #bdc3c7;"></div>', true, false, true, false, '');

// Student Profile
$infoHtml = '<h4 style="background-color:#f1c40f; padding:5px; color:#2c3e50;">Student Profile</h4>
<table border="0" cellpadding="5" style="border-collapse:collapse; width:100%;">
    <tr style="background-color:#f8f9fa;"><td width="30%"><strong>Registration No.</strong> </td><td>'.htmlspecialchars($student['id']).'</td></tr>
    <tr><td width="30%"><strong>Name</strong> </td><td>'.htmlspecialchars($student['fname'].' '.$student['mname'].' '.$student['lname']).'</td></tr>
    <tr style="background-color:#f8f9fa;"><td><strong>Gender</strong> </td><td>'.htmlspecialchars($student['gender']).'</td></tr>
    <tr><td><strong>Class</strong> </td><td>'.htmlspecialchars($student['class_name']).'</td></tr>
    <tr style="background-color:#f8f9fa;"><td><strong>Email</strong> </td><td>'.htmlspecialchars($student['email']).'</td></tr>
</table><br>';
$pdf->writeHTML($infoHtml, true, false, true, false, '');

// Latest Term Summary
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Examination: ' . htmlspecialchars($selected_term['name']), 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 11);
$summaryHtml = '<table border="1" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <tr><td width="30%"><strong>Total Score</strong></td><td width="20%">'.$total_score.'</td><td width="30%"><strong>Average (%)</strong></td><td width="20%">'.$avg.'%</td></tr>
    <tr><td><strong>Grade</strong></td><td>'.$overall_grade.'</td><td><strong>Remark</strong></td><td>'.$overall_remark.'</td></tr>
    <tr><td><strong>Division</strong></td><td>'.$division.'</td><td><strong>Points</strong></td><td>'.$points.'</td></tr>
    <tr><td><strong>Pass Rate</strong></td><td>'.$pass_percent.'%</td><td><strong>Fail Rate</strong></td><td>'.$fail_percent.'%</td></tr>
</table><br>';
$pdf->writeHTML($summaryHtml, true, false, true, false, '');

// Subject Performance Table with Unattended handling
$subjectHtml = '<h4 style="background-color:#2ecc71; padding:5px; color:#fff;">Subject Performance</h4>
<table border="1" cellpadding="5" style="border-collapse:collapse; width:100%;">
    <thead><tr style="background-color:#3498db; color:#fff;">
        <th style="width:40%;">Subject</th>
        <th style="width:15%;">Score (%)</th>
        <th style="width:15%;">Grade</th>
        <th style="width:15%;">Class Avg</th>
        <th style="width:15%;">Comparison</th>
    </tr></thead>
    <tbody>';
foreach ($latest_results as $res) {
    $score = $res['score'];
    $is_unattended = ($score == 0);
    
    // Determine grade and remark for display
    if ($is_unattended) {
        $display_score = 'Unattended';
        $grade = 'N/A';
        $remark = 'Unattended';
    } else {
        $display_score = $score . '%';
        $grade = get_grade($score, $grading);
    }
    
    $class_avg_score = isset($class_avg[$res['subject_combination']]) ? $class_avg[$res['subject_combination']] : '-';
    $comparison = '';
    if ($class_avg_score !== '-') {
        $diff = $score - $class_avg_score;
        if ($diff > 0) $comparison = '> ' . $diff . '%';
        elseif ($diff < 0) $comparison = '< ' . abs($diff) . '%';
        else $comparison = '=';
    }
    
    // Row style based on pass/fail (unchanged: unattended (0) will be red as fail)
    $row_style = ($score >= 50) ? 'style="background-color:#d4edda;"' : 'style="background-color:#f8d7da;"';
    $subjectHtml .= '<tr ' . $row_style . '>
        <td style="width:40%;">' . htmlspecialchars($res['subject_name']) . '</td>
        <td style="width:15%; text-align:center;">' . $display_score . '</td>
        <td style="width:15%; text-align:center;">' . $grade . '</td>
        <td style="width:15%; text-align:center;">' . ($class_avg_score !== '-' ? $class_avg_score . '%' : '-') . '</td>
        <td style="width:15%; text-align:center;">' . $comparison . '</td>
    </tr>';
}
$subjectHtml .= '</tbody></table><br>';
$pdf->writeHTML($subjectHtml, true, false, true, false, '');

// Subject Highlights (top/bottom subjects – include zeros, they will appear as 0% in bottom)
$highlightsHtml = '<h4 style="background-color:#e67e22; padding:5px; color:#fff;">Subject Highlights</h4>
<table border="0" cellpadding="5" style="width:100%;">
    <tr><td style="width:50%; border:1px solid #ddd; vertical-align:top;">
        <strong>[+] TOP 3 SUBJECTS</strong><br><ul>';
foreach ($top_subjects as $sub) $highlightsHtml .= '<li>' . htmlspecialchars($sub['name']) . ' – <strong>' . $sub['score'] . '%</strong></li>';
$highlightsHtml .= '</ul></td><td style="width:50%; border:1px solid #ddd; vertical-align:top;">
        <strong>[!] BOTTOM 3 SUBJECTS</strong><br><ul>';
foreach ($bottom_subjects as $sub) $highlightsHtml .= '<li>' . htmlspecialchars($sub['name']) . ' – <strong>' . $sub['score'] . '%</strong></li>';
$highlightsHtml .= '</ul></td></tr>
</table><br>';
$pdf->writeHTML($highlightsHtml, true, false, true, false, '');

// Grade Distribution (unchanged – uses numeric scores, so zero falls into F)
$distHtml = '<h4 style="background-color:#9b59b6; padding:5px; color:#fff;">Grade Distribution</h4>
<table border="1" cellpadding="5" style="border-collapse:collapse; width:100%;">
    <thead><tr style="background-color:#bdc3c7;"><th>Grade</th><th>Count</th><th>Percentage</th></tr></thead>
    <tbody>';
foreach ($grade_counts as $grade => $cnt) {
    if ($cnt == 0) continue;
    $pct = round(($cnt / $subject_count) * 100);
    $distHtml .= '<tr><td align="center">'.$grade.'</td><td align="center">'.$cnt.'</td><td align="center">'.$pct.'%</td></tr>';
}
$distHtml .= '</tbody></table><br>';
$pdf->writeHTML($distHtml, true, false, true, false, '');

// Term-wise Performance (unchanged)
if (count($term_summary) > 1) {
    $termTable = '<h4 style="background-color:#1abc9c; padding:5px; color:#fff;">Term-wise Performance</h4>
    <table border="1" cellpadding="5" style="border-collapse:collapse; width:100%;">
        <thead><tr style="background-color:#16a085; color:#fff;"><th>Term</th><th>Average (%)</th><th>Points</th><th>Division</th></tr></thead>
        <tbody>';
    foreach ($term_summary as $ts) {
        $termTable .= '<tr><td>'.htmlspecialchars($ts['name']).'</td><td align="center">'.$ts['avg'].'%</td><td align="center">'.$ts['points'].'</td><td align="center">'.$ts['division'].'</td></tr>';
    }
    $termTable .= '</tbody></table><br>';
    $pdf->writeHTML($termTable, true, false, true, false, '');
}

// Academic Advice (unchanged)
$adviceHtml = '<h4 style="background-color:#f39c12; padding:5px; color:#fff;">Academic Advice</h4>
<div style="background-color:#fef9e7; padding:15px; border-left:5px solid #f39c12; font-size:10pt; font-family: monospace; line-height:1.5;">
    ' . $detailedAdvice . '
</div><br>';
$pdf->writeHTML($adviceHtml, true, false, true, false, '');

// Announcements (unchanged)
if (!empty($announcements)) {
    $annHtml = '<h4 style="background-color:#e74c3c; padding:5px; color:#fff;">Announcements</h4><ul>';
    foreach ($announcements as $ann) {
        $annHtml .= '<li><strong>'.htmlspecialchars($ann['title']).'</strong><br>'.nl2br($ann['announcement']).'<br><small style="color:#7f8c8d;">'.$ann['create_date'].'</small></li><br>';
    }
    $annHtml .= '</ul>';
    $pdf->writeHTML($annHtml, true, false, true, false, '');
}

ob_end_clean();
$pdf->Output('Student_Dashboard_' . $student['id'] . '.pdf', 'I');
?>