<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');

// Academic only (level 1)
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// Check for POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['student']) || !isset($_POST['term'])) {
    $_SESSION['error'] = 'Invalid request. Please select a class and term.';
    header("location:../academic/report_tool");
    exit;
}

$class_id = (int)$_POST['student'];
$term_id  = (int)$_POST['term'];

if ($class_id <= 0 || $term_id <= 0) {
    $_SESSION['error'] = 'Invalid class or term selected.';
    header("location:../academic/report_tool");
    exit;
}

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- School details ---
    $stmt = $conn->query("SELECT name, logo FROM tbl_school LIMIT 1");
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    $school_name = $school['name'] ?? 'EVERLASTING ACADEMY';
    $school_logo = $school['logo'] ?? '';

    // --- Class and term names ---
    $stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    $class_name = $class['name'] ?? 'Unknown Class';

    $stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term_id]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    $term_name = $term['name'] ?? 'Unknown Term';

    // --- Grading and division systems ---
    $grade_system = [];
    $stmt = $conn->query("SELECT * FROM tbl_grade_system ORDER BY min DESC");
    $grade_system = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $division_system = [];
    $stmt = $conn->query("SELECT * FROM tbl_division_system ORDER BY min_point ASC");
    $division_system = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Subject combinations for this class ---
    $stmt = $conn->query("SELECT * FROM tbl_subject_combinations");
    $all_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subject_combos = [];
    foreach ($all_combos as $combo) {
        $class_list = unserialize($combo['class']);
        if (is_array($class_list) && in_array($class_id, $class_list)) {
            $subject_combos[] = $combo;
        }
    }

    // Get subject names
    $subject_ids = array_column($subject_combos, 'subject');
    $subject_details = [];
    if (!empty($subject_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM tbl_subjects WHERE id IN ($placeholders)");
        $stmt->execute($subject_ids);
        $subject_details = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // --- Active students in this class ---
    $stmt = $conn->prepare("SELECT id, fname, mname, lname, gender FROM tbl_students WHERE class = ? AND status = 1 ORDER BY lname, fname");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Include subject combinations from historical results of promoted
    // students, even when those combinations belong to their former class.
    $historicalCombinationIds = [];
    if (!empty($students)) {
        $studentIds = array_column($students, 'id');
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $historyStmt = $conn->prepare("SELECT DISTINCT subject_combination FROM tbl_exam_results WHERE student IN ($studentPlaceholders) AND term = ?");
        $historyStmt->execute(array_merge($studentIds, [$term_id]));
        $historicalCombinationIds = array_fill_keys($historyStmt->fetchAll(PDO::FETCH_COLUMN), true);
        $selectedCombinationIds = array_fill_keys(array_column($subject_combos, 'id'), true);
        foreach ($all_combos as $combo) {
            if (isset($historicalCombinationIds[$combo['id']]) && !isset($selectedCombinationIds[$combo['id']])) {
                $subject_combos[] = $combo;
            }
        }
    }

    if (empty($students)) {
        $_SESSION['error'] = 'No active students found in this class.';
        header("location:../academic/report_tool");
        exit;
    }

    // --- Build student performance data ---
    $student_data = [];
    $division_totals = [];
    $total_students = count($students);
    $total_average_sum = 0;
    $pass_count = 0;
    $division_counts = [];

    $grade_point_map = [
        'A' => 1,
        'B' => 2,
        'C' => 3,
        'D' => 4,
        'E' => 5,
        'S' => 6,
        'F' => 7
    ];

    $pass_min = 45;

    foreach ($students as $student) {
        $student_id = $student['id'];
        $gender = $student['gender'];
        $full_name = trim($student['lname'] . ' ' . $student['fname'] . ' ' . $student['mname']);

        $scores = [];
        $total_score = 0;
        $subject_count = 0;

        foreach ($subject_combos as $combo) {
            $combo_id = $combo['id'];
            $subject_id = $combo['subject'];
            $subject_name = $subject_details[$subject_id] ?? 'Subject ID ' . $subject_id;

            $stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND term = ? AND subject_combination = ?");
            $stmt->execute([$student_id, $term_id, $combo_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $score = $result ? (float)$result['score'] : null;

            $scores[] = [
                'subject' => $subject_name,
                'score'   => $score,
            ];

            if ($score !== null && $score !== '') {
                $total_score += $score;
                $subject_count++;
            }
        }

        $average = ($subject_count > 0) ? round($total_score / $subject_count, 2) : 0;
        $total_average_sum += $average;

        // Determine grade using the shared NECTA grade engine.
        $grade = get_grade($average, $grade_system);

        if ($average >= $pass_min) {
            $pass_count++;
        }

        $subject_grade_pairs = [];

        foreach ($scores as $s) {
            $score = $s['score'];
            $subject_name = $s['subject'];
            if ($score === null || $score === '') {
                continue;
            }
            $subject_grade = get_grade($score, $grade_system);
            $subject_grade_pairs[] = $subject_name . "-'" . $subject_grade . "'";
        }

        $acsee = calculate_acsee_result(
            array_map(function ($subject) {
                return ['name' => $subject['subject'], 'score' => $subject['score']];
            }, array_filter($scores, function ($subject) {
                return $subject['score'] !== null && $subject['score'] !== '';
            })),
            detect_combination_from_class_name($class_name) ?: '',
            $grade_system,
            $division_system
        );
        $total_points = $acsee['points'] ?? 0;
        $division = $acsee['division'] ?? 'N/A';

        $subject_grade_string = implode(' ', $subject_grade_pairs);

        $student_data[] = [
            'id'          => $student_id,
            'name'        => $full_name,
            'gender'      => $gender,
            'total'       => $total_score,
            'average'     => $average,
            'grade'       => $grade,
            'total_points'=> $total_points,
            'division'    => $division,
            'subject_grades' => $subject_grade_string,
        ];

        if (!isset($division_totals[$division])) {
            $division_totals[$division] = ['BOYS' => 0, 'GIRLS' => 0];
        }
        if (!isset($division_counts[$division])) {
            $division_counts[$division] = 0;
        }
        if ($gender == 'Male') {
            $division_totals[$division]['BOYS']++;
        } else {
            $division_totals[$division]['GIRLS']++;
        }
        $division_counts[$division]++;
    }

    $division_order = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, '0' => 5, 'N/A' => 6];
    usort($student_data, function ($a, $b) use ($division_order) {
        $a_order = $division_order[$a['division']] ?? 99;
        $b_order = $division_order[$b['division']] ?? 99;
        if ($a_order !== $b_order) return $a_order <=> $b_order;

        $a_points = is_numeric($a['total_points']) ? (int)$a['total_points'] : PHP_INT_MAX;
        $b_points = is_numeric($b['total_points']) ? (int)$b['total_points'] : PHP_INT_MAX;
        if ($a_points !== $b_points) return $a_points <=> $b_points;
        if ($a['average'] != $b['average']) return $b['average'] <=> $a['average'];

        return strcasecmp($a['name'], $b['name']);
    });

    $overall_avg = ($total_students > 0) ? round($total_average_sum / $total_students, 2) : 0;
    $pass_rate = ($total_students > 0) ? round(($pass_count / $total_students) * 100, 2) : 0;

    // --- Generate analytics and advice (4 lines, human‑readable) ---
    $division_summary = [];
    foreach ($division_counts as $div => $count) {
        if ($div != 'N/A') {
            $division_summary[] = "$div: $count";
        }
    }
    $div_text = implode(', ', $division_summary);
    if (empty($div_text)) $div_text = 'No division data available.';

    // Build advice based on performance
    $advice_lines = [];

    // Line 1: Overall performance summary
    $advice_lines[] = "This report covers $total_students students with an average score of $overall_avg% and a pass rate of $pass_rate%.";

    // Line 2: Division distribution
    $advice_lines[] = "The division distribution is as follows: $div_text.";

    // Line 3: Strength / weakness identification (narrative)
    if ($pass_rate >= 80) {
        $advice_lines[] = "The class has shown outstanding academic strength, with the majority of students achieving high divisions. This is commendable and reflects effective teaching and learning.";
    } elseif ($pass_rate >= 60) {
        $advice_lines[] = "Overall performance is good, though there is noticeable room for improvement, particularly among students in the lower divisions. A focused approach could help raise their grades.";
    } else {
        $advice_lines[] = "The current performance indicates that additional support is needed. We recommend implementing remedial programmes to assist underperforming students and boost overall achievement.";
    }

    // Line 4: Specific recommendation
    if (isset($division_counts['I']) && $division_counts['I'] >= 5) {
        $advice_lines[] = "To sustain excellence, consider establishing peer mentoring or enrichment activities for top achievers in Division I.";
    } elseif (isset($division_counts['IV']) && $division_counts['IV'] > 3) {
        $advice_lines[] = "We suggest targeted interventions for students in Division IV, such as extra tuition or personalised study plans, to help them progress to higher divisions.";
    } else {
        $advice_lines[] = "Continue with the current teaching strategies and regularly monitor student progress to ensure sustained improvement across all divisions.";
    }

    // Ensure exactly 4 lines
    $advice_text = implode('<br>', array_slice($advice_lines, 0, 4));

    // --- Generate PDF ---
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($school_name);
    $pdf->SetTitle($class_name . ' - ' . $term_name . ' Performance Report');
    $pdf->SetSubject($class_name . ' - ' . $term_name);
    $pdf->SetKeywords('SRMS, ' . $school_name);

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
        require_once(dirname(__FILE__) . '/lang/eng.php');
        $pdf->setLanguageArray($l);
    }

    $pdf->setFontSubsetting(true);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->AddPage();

    // --- School header with bigger logo ---
    $logo_path = 'images/logo/' . $school_logo;
    $logo_html = '';
    if (!empty($school_logo) && file_exists($logo_path)) {
        $logo_html = '<td width="20%"><img src="' . $logo_path . '" width="110"></td>';
    }

    $html_header = '
    <table width="100%" style="border-bottom: 3px solid #4361ee; padding-bottom: 10px;">
        <tr>
            ' . $logo_html . '
            <td width="' . (empty($logo_html) ? '100' : '80') . '%" style="text-align: left;">
                <h2 style="color: #1e293b; font-weight: 800; margin: 0;">' . htmlspecialchars($school_name) . '</h2>
                <h4 style="color: #4361ee; margin: 5px 0 0 0;">Student Performance Report</h4>
                <p style="font-size: 14px; color: #475569; margin: 3px 0 0 0;">
                    <strong>' . htmlspecialchars($class_name) . '</strong> &bull; 
                    <strong>' . htmlspecialchars($term_name) . '</strong>
                </p>
            </td>
        </tr>
    </table>
    <br>
    ';

    $pdf->writeHTML($html_header, true, false, true, false, '');

    // --- Summary Statistics (compact cards) ---
    $stats_html = '
    <table width="100%" style="background: #f8fafc; border-radius: 12px; padding: 10px; border: 1px solid #e2e8f0;">
        <tr>
            <td width="25%" style="text-align: center; padding: 6px; background: #ffffff; border-radius: 8px; border-left: 4px solid #4361ee;">
                <div style="font-size: 18px; font-weight: 800; color: #1e293b;">' . $total_students . '</div>
                <div style="font-size: 11px; color: #64748b;">Total</div>
            </td>
            <td width="25%" style="text-align: center; padding: 6px; background: #ffffff; border-radius: 8px; border-left: 4px solid #0b8a5e;">
                <div style="font-size: 18px; font-weight: 800; color: #1e293b;">' . number_format($overall_avg, 1) . '%</div>
                <div style="font-size: 11px; color: #64748b;">Average</div>
            </td>
            <td width="25%" style="text-align: center; padding: 6px; background: #ffffff; border-radius: 8px; border-left: 4px solid #ffb703;">
                <div style="font-size: 18px; font-weight: 800; color: #1e293b;">' . number_format($pass_rate, 1) . '%</div>
                <div style="font-size: 11px; color: #64748b;">Pass Rate</div>
            </td>
            <td width="25%" style="text-align: center; padding: 6px; background: #ffffff; border-radius: 8px; border-left: 4px solid #ef476f;">
                <div style="font-size: 18px; font-weight: 800; color: #1e293b;">' . ($total_students - $pass_count) . '</div>
                <div style="font-size: 11px; color: #64748b;">Failed</div>
            </td>
        </tr>
    </table>
    <br>
    ';

    $pdf->writeHTML($stats_html, true, false, true, false, '');

    // --- Division Summary (compact table, no graph) ---
    $division_order = array_column($division_system, 'division');
    $html_div = '<h4 style="color: #1e293b; font-weight: 700;">Division Summary</h4>';
    $html_div .= '<table width="60%" cellpadding="4" style="border-collapse: collapse; border: 1px solid #e2e8f0; font-size: 11px;">';
    $html_div .= '<tr style="background: #e9edf2; color: #000000; font-weight: 700;">
        <th style="text-align: left;">Division</th>
        <th style="text-align: center;">Boys</th>
        <th style="text-align: center;">Girls</th>
        <th style="text-align: center;">Total</th>
    </tr>';

    foreach ($division_order as $div) {
        $boys = $division_totals[$div]['BOYS'] ?? 0;
        $girls = $division_totals[$div]['GIRLS'] ?? 0;
        $total = $boys + $girls;
        $bg = ($div == 'I') ? '#e6f7ed' : (($div == 'IV') ? '#fce8ec' : '#ffffff');
        $html_div .= '<tr style="background: ' . $bg . '; border-bottom: 1px solid #e2e8f0; color: #000000;">
            <td style="font-weight: 700;">' . htmlspecialchars($div) . '</td>
            <td style="text-align: center;">' . $boys . '</td>
            <td style="text-align: center;">' . $girls . '</td>
            <td style="text-align: center; font-weight: 700;">' . $total . '</td>
        </tr>';
    }
    $html_div .= '</table><br>';

    $pdf->writeHTML($html_div, true, false, true, false, '');

    // --- Analytics & Advice (4 lines, left‑aligned, no emoji, clean) ---
    $advice_html = '
    <table width="100%" style="background: #f8fafc; border-left: 4px solid #4361ee; padding: 10px 14px; border-radius: 6px; margin-bottom: 12px;">
        <tr>
            <td style="font-size: 11pt; color: #1e293b; line-height: 1.7; text-align: left;">
                <strong>Academic Analytics and Advice</strong><br>
                ' . $advice_text . '
            </td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($advice_html, true, false, true, false, '');

    // --- Student Details ---
    $html_details = '<h4 style="color: #1e293b; font-weight: 700;">Student Details</h4>';
    $html_details .= '<table border="1" cellpadding="4" style="font-size: 9pt; border-collapse: collapse; width: 100%;">';
    $html_details .= '<tr style="background: #e9edf2; color: #000000; font-weight: 700;">
        <th style="text-align: center; width: 8%;">ID</th>
        <th style="text-align: left; width: 18%;">Student Name</th>
        <th style="text-align: center; width: 5%;">Gender</th>
        <th style="text-align: center; width: 6%;">Total</th>
        <th style="text-align: center; width: 6%;">Avg</th>
        <th style="text-align: center; width: 6%;">Grade</th>
        <th style="text-align: center; width: 7%;">Points</th>
        <th style="text-align: center; width: 7%;">Division</th>
        <th style="text-align: left; width: 37%;">Subject Grades</th>
    </tr>';

    $counter = 1;
    foreach ($student_data as $student) {
        $bg = ($counter % 2 == 0) ? '#f8fafc' : '#ffffff';
        $html_details .= '<tr style="background: ' . $bg . '; color: #000000;">';
        $html_details .= '<td style="text-align: center;">' . htmlspecialchars($student['id']) . '</td>';
        $html_details .= '<td style="text-align: left;">' . htmlspecialchars($student['name']) . '</td>';
        $html_details .= '<td style="text-align: center;">' . htmlspecialchars($student['gender']) . '</td>';
        $html_details .= '<td style="text-align: center;">' . number_format($student['total'], 0) . '</td>';
        $html_details .= '<td style="text-align: center;">' . number_format($student['average'], 1) . '</td>';
        $html_details .= '<td style="text-align: center;">' . htmlspecialchars($student['grade']) . '</td>';
        $html_details .= '<td style="text-align: center;">' . $student['total_points'] . '</td>';
        $html_details .= '<td style="text-align: center;">' . htmlspecialchars($student['division']) . '</td>';
        $html_details .= '<td style="text-align: left; font-size: 8pt;">' . htmlspecialchars($student['subject_grades']) . '</td>';
        $html_details .= '</tr>';
        $counter++;
    }

    $html_details .= '</table>';

    $pdf->writeHTML($html_details, true, false, true, false, '');

    // --- Footer ---
    $html_footer = '<br><br>
    <table width="100%">
        <tr>
            <td style="text-align: left; font-size: 10px; color: #94a3b8;">
                Generated on: ' . date('F d, Y H:i:s') . '
            </td>
            <td style="text-align: right; font-size: 10px; color: #94a3b8;">
                ' . htmlspecialchars($school_name) . ' &bull; ' . htmlspecialchars($class_name) . '
            </td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html_footer, true, false, true, false, '');

    ob_end_clean();
    $pdf->Output($class_name . '_' . $term_name . '_Report.pdf', 'I');
    exit;

} catch (PDOException $e) {
    error_log('Report generation error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred. Please try again.';
    header("location:../academic/report_tool");
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    header("location:../academic/report_tool");
    exit;
}