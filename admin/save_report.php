<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');

if ($res == "1" && $level == "0") {} else { header("location:../"); }

if (isset($_SESSION['bulk_result_2'])) {
    $class = $_SESSION['bulk_result_2']['student'];
    $term = $_SESSION['bulk_result_2']['term'];

    try {
        $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch grading system
        $stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
        $stmt->execute();
        $grading = $stmt->fetchAll(PDO::FETCH_NUM);
        $gradingAssoc = array_map(function ($g) {
            return ['name' => $g[1], 'min' => $g[2], 'max' => $g[3], 'remark' => $g[4]];
        }, $grading);

        // Fetch term details
        $stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
        $stmt->execute([$term]);
        $term_data = $stmt->fetchAll(PDO::FETCH_NUM);
        if (empty($term_data)) die("Term not found.");

        // Fetch class details
        $stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
        $stmt->execute([$class]);
        $class_data = $stmt->fetchAll(PDO::FETCH_NUM);
        if (empty($class_data)) die("Class not found.");

        $className = $class_data[0][1];
        $termName = $term_data[0][1];
        $title = $className . ' (' . $termName . ' Performance Report)';

        // Fetch school contact information
        $contact = ['location' => '', 'phone1' => '', 'phone2' => '', 'email' => ''];
        try {
            $stmt = $conn->query("SELECT location, phone1, phone2, email FROM tbl_school_contacts LIMIT 1");
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $contact = array_merge($contact, $row);
            }
        } catch (PDOException $e) {
            // Table missing – ignore
        }

        // Get all students in the class
        $stmt = $conn->prepare("SELECT id, fname, mname, lname, gender FROM tbl_students WHERE class = ? AND status = 1");
        $stmt->execute([$class]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalStudents = count($students);
        $historicalCombinationIds = [];
        if (!empty($students)) {
            $studentIds = array_column($students, 'id');
            $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
            $historyStmt = $conn->prepare("SELECT DISTINCT subject_combination FROM tbl_exam_results WHERE student IN ($studentPlaceholders) AND term = ?");
            $historyStmt->execute(array_merge($studentIds, [$term]));
            $historicalCombinationIds = array_fill_keys($historyStmt->fetchAll(PDO::FETCH_COLUMN), true);
        }

        // Get all subject combinations for this class
        $stmt = $conn->prepare("
            SELECT sc.id AS sc_id, sc.class, s.id AS subject_id, s.name AS subject_name
            FROM tbl_subject_combinations sc
            LEFT JOIN tbl_subjects s ON sc.subject = s.id
        ");
        $stmt->execute();
        $allCombinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $subjects = [];
        $subjectNames = [];
        foreach ($allCombinations as $comb) {
            $classList = unserialize($comb['class']);
            if (is_array($classList) && (in_array($class, $classList) || isset($historicalCombinationIds[$comb['sc_id']]))) {
                $subjects[$comb['sc_id']] = $comb['subject_name'];
                $subjectNames[] = $comb['subject_name'];
            }
        }
        $subjectCount = count($subjects);

        // Fetch divisions directly from database
        $stmt = $conn->query("SELECT division FROM tbl_division_system ORDER BY points ASC");
        $divisions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Initialize division counters
        $divisionStats = [];
        foreach ($divisions as $div) {
            $divisionStats[$div] = ['BOYS' => 0, 'GIRLS' => 0];
        }

        // Additional statistics
        $gradeCounts = array_fill_keys(array_column($grading, 1), 0); // grade name => count
        $subjectScores = array_fill_keys(array_keys($subjects), []); // sc_id => list of scores
        $studentTotals = []; // student id => total score
        $studentAverages = []; // student id => average

        $studentsWithResults = 0;
        $totalScoreSum = 0;
        $passCount = 0; // average >= 50

        // Process each student
        foreach ($students as $student) {
            $sid = $student['id'];
            $gender = $student['gender'];
            $scores = [];
            $subjectObjects = [];
            $hasResult = false;

            foreach ($subjects as $scid => $sname) {
                $stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND subject_combination = ? AND term = ?");
                $stmt->execute([$sid, $scid, $term]);
                $ex = $stmt->fetch(PDO::FETCH_ASSOC);
                $score = $ex ? (float)$ex['score'] : null;
                $scores[] = $score;
                if ($score !== null && $score !== '') {
                    $subjectObjects[] = ['name' => $sname, 'score' => $score];
                }
                if ($score !== null && $score !== '') $hasResult = true;
                $subjectScores[$scid][] = $score;
            }

            if ($hasResult) {
                $studentsWithResults++;
                $presentScores = array_values(array_filter($scores, function ($score) {
                    return $score !== null && $score !== '';
                }));
                $total = array_sum($presentScores);
                $avg = count($presentScores) ? round($total / count($presentScores), 1) : 0;
                $studentTotals[$sid] = $total;
                $studentAverages[$sid] = $avg;
                $totalScoreSum += $avg;
                if ($avg >= 50) $passCount++;

                $acsee = calculate_acsee_result(
                    $subjectObjects,
                    detect_combination_from_class_name($className) ?: '',
                    $gradingAssoc,
                    $conn->query("SELECT * FROM tbl_division_system")->fetchAll(PDO::FETCH_ASSOC)
                );
                $division = $acsee['division'] ?? '0';
                if (isset($divisionStats[$division])) {
                    $divisionStats[$division][($gender == 'Male' ? 'BOYS' : 'GIRLS')]++;
                }

                // Grade distribution
                $gradeCounts[get_grade($avg, $gradingAssoc)]++;
            } else {
                // No results – skip division/grade stats
            }
        }

        $classAverage = $studentsWithResults > 0 ? round($totalScoreSum / $studentsWithResults) : 0;
        $passRate = $studentsWithResults > 0 ? round(($passCount / $studentsWithResults) * 100) : 0;

        // Subject averages
        $subjectAverages = [];
        foreach ($subjectScores as $scid => $scores) {
            $avg = count($scores) ? round(array_sum($scores) / count($scores)) : 0;
            $subjectAverages[$subjects[$scid]] = $avg;
        }

        // Top 5 students by total score
        arsort($studentTotals);
        $topStudents = array_slice($studentTotals, 0, 5, true);

        // ---------- Additional School‑Wide Statistics (for this term only) ----------
        $schoolTotalResults = 0;
        $schoolSumScores = 0;
        $schoolPassCount = 0;
        $schoolResults = $conn->prepare("SELECT score FROM tbl_exam_results WHERE term = ?");
        $schoolResults->execute([$term]);
        while ($row = $schoolResults->fetch(PDO::FETCH_ASSOC)) {
            $schoolTotalResults++;
            $schoolSumScores += $row['score'];
            if ($row['score'] >= 50) $schoolPassCount++;
        }
        $schoolAverage = $schoolTotalResults > 0 ? round($schoolSumScores / $schoolTotalResults, 1) : 0;
        $schoolPassPercent = $schoolTotalResults > 0 ? round(($schoolPassCount / $schoolTotalResults) * 100, 1) : 0;

        // Subject pass/fail across all classes for the term
        $subjectPassFail = [];
        $stmt = $conn->prepare("
            SELECT sc.id, s.name AS subject_name, er.score
            FROM tbl_exam_results er
            LEFT JOIN tbl_subject_combinations sc ON er.subject_combination = sc.id
            LEFT JOIN tbl_subjects s ON sc.subject = s.id
            WHERE er.term = ?
        ");
        $stmt->execute([$term]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $subj = $row['subject_name'] ?: 'Unknown';
            if (!isset($subjectPassFail[$subj])) {
                $subjectPassFail[$subj] = ['pass' => 0, 'fail' => 0, 'total' => 0];
            }
            $subjectPassFail[$subj]['total']++;
            if ($row['score'] >= 50) {
                $subjectPassFail[$subj]['pass']++;
            } else {
                $subjectPassFail[$subj]['fail']++;
            }
        }

        // ---------- Teacher Performance by Subject (for this term) ----------
        $teacherPerformance = [];
        $teachers = $conn->query("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 ORDER BY lname")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($teachers as $teacher) {
            $tid = $teacher['id'];
            // Get subject combinations for this teacher
            $stmt = $conn->prepare("
                SELECT sc.id, s.name AS subject_name
                FROM tbl_subject_combinations sc
                LEFT JOIN tbl_subjects s ON sc.subject = s.id
                WHERE sc.teacher = ?
            ");
            $stmt->execute([$tid]);
            $subjCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($subjCombos)) continue;

            $teacherData = [
                'name' => $teacher['fname'] . ' ' . $teacher['lname'],
                'subjects' => []
            ];
            $totalPass = 0;
            $totalFail = 0;
            $totalResults = 0;

            foreach ($subjCombos as $combo) {
                $scid = $combo['id'];
                $subjName = $combo['subject_name'] ?: 'Unknown';
                // Get all results for this subject combination in the selected term
                $stmt2 = $conn->prepare("SELECT score FROM tbl_exam_results WHERE subject_combination = ? AND term = ?");
                $stmt2->execute([$scid, $term]);
                $scores = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                $pass = 0;
                $fail = 0;
                foreach ($scores as $s) {
                    if ($s >= 50) $pass++; else $fail++;
                }
                $total = $pass + $fail;
                $passPercent = $total > 0 ? round(($pass / $total) * 100, 1) : 0;
                $teacherData['subjects'][] = [
                    'name' => $subjName,
                    'pass' => $pass,
                    'fail' => $fail,
                    'total' => $total,
                    'pass_percent' => $passPercent
                ];
                $totalPass += $pass;
                $totalFail += $fail;
                $totalResults += $total;
            }
            $teacherData['total_pass'] = $totalPass;
            $teacherData['total_fail'] = $totalFail;
            $teacherData['total_results'] = $totalResults;
            $teacherData['overall_pass_percent'] = $totalResults > 0 ? round(($totalPass / $totalResults) * 100, 1) : 0;
            $teacherPerformance[] = $teacherData;
        }

        // ---------- PDF Generation ----------
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(WBName);
        $pdf->SetTitle($title);
        $pdf->SetSubject($title);
        $pdf->SetKeywords('SRMS,' . WBName);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('helvetica', '', 10);

        if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
            require_once(dirname(__FILE__) . '/lang/eng.php');
            $pdf->setLanguageArray($l);
        }

        $pdf->AddPage();

        // ----- Header with school logo, name, and contact info -----
        $logoPath = 'images/logo/' . WBLogo;
        // Increased logo size to 90px
        $logoImg = '<img src="' . $logoPath . '" width="90">';

        $contactStr = '';
        if (!empty($contact['location']) || !empty($contact['phone1']) || !empty($contact['phone2']) || !empty($contact['email'])) {
            $parts = [];
            if (!empty($contact['location'])) $parts[] = $contact['location'];
            if (!empty($contact['phone1'])) $parts[] = 'Tel: ' . $contact['phone1'];
            if (!empty($contact['phone2'])) $parts[] = $contact['phone2'];
            if (!empty($contact['email'])) $parts[] = 'Email: ' . $contact['email'];
            $contactStr = '<span style="font-size:8pt;">' . implode(' | ', $parts) . '</span><br>';
        }

        // Header with logo on both left and right sides, text centered
        $headerHtml = '
        <table cellpadding="5" style="border-bottom: 1px solid #333; margin-bottom: 10px;">
            <tr>
                <td width="20%" align="left">' . $logoImg . '</td>
                <td width="60%" align="center">
                    <h2 style="margin:0; color:#2c3e50;">' . WBName . '</h2>
                    <h4 style="margin:0; color:#34495e;">Class Performance Report</h4>
                    ' . $contactStr . '
                    <b style="color:#2980b9;">' . $className . '</b> | <b style="color:#2980b9;">' . $termName . '</b>
                </td>
                <td width="20%" align="right">' . $logoImg . '</td>
            </tr>
        </table>
        ';
        $pdf->writeHTML($headerHtml, true, false, true, false, '');

        // ----- Summary Statistics -----
        $statsHtml = '
        <h4>Summary Statistics</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; margin-bottom: 15px; background-color:#f8f9fa;">
            <tr><td width="50%"><b>Total Students</b></td><td>' . $totalStudents . '</td></tr>
            <tr><td><b>Students with Results</b></td><td>' . $studentsWithResults . '</td></tr>
            <tr><td><b>Class Average Score</b></td><td>' . $classAverage . '%</td></tr>
            <tr><td><b>Pass Rate (≥50%)</b></td><td>' . $passRate . '%</td></tr>
            <tr><td><b>Number of Subjects</b></td><td>' . $subjectCount . '</td></tr>
        </table>
        ';
        $pdf->writeHTML($statsHtml, true, false, true, false, '');

        // ----- Division Breakdown (using fetched divisions) -----
        $divTable = '
        <h4>Division Breakdown by Gender</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
            <thead>
                <tr style="background-color: #2c3e50; color: white;">
                    <th>Division</th>
                    <th>Boys</th>
                    <th>Girls</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
        ';
        foreach ($divisions as $div) {
            $boys = isset($divisionStats[$div]['BOYS']) ? $divisionStats[$div]['BOYS'] : 0;
            $girls = isset($divisionStats[$div]['GIRLS']) ? $divisionStats[$div]['GIRLS'] : 0;
            $total = $boys + $girls;
            $divTable .= '
                <tr>
                    <td align="center"><b>' . $div . '</b></td>
                    <td align="center">' . $boys . '</td>
                    <td align="center">' . $girls . '</td>
                    <td align="center">' . $total . '</td>
                </tr>
            ';
        }
        $divTable .= '</tbody></table>';
        $pdf->writeHTML($divTable, true, false, true, false, '');

        // ----- Grade Distribution -----
        $gradeTable = '
        <h4>Grade Distribution</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
            <thead>
                <tr style="background-color: #3498db; color: white;">
                    <th>Grade</th>
                    <th>Students</th>
                </tr>
            </thead>
            <tbody>
        ';
        foreach ($gradeCounts as $grade => $count) {
            $gradeTable .= '<tr><td>' . $grade . '</td><td align="center">' . $count . '</td></tr>';
        }
        $gradeTable .= '</tbody></table>';
        $pdf->writeHTML($gradeTable, true, false, true, false, '');

        // ----- Subject Averages -----
        $subjectTable = '
        <h4>Subject Averages</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
            <thead>
                <tr style="background-color: #e67e22; color: white;">
                    <th>Subject</th>
                    <th>Average Score</th>
                </tr>
            </thead>
            <tbody>
        ';
        foreach ($subjectAverages as $subj => $avg) {
            $subjectTable .= '<tr><td>' . $subj . '</td><td align="center">' . $avg . '%</td></tr>';
        }
        $subjectTable .= '</tbody></table>';
        $pdf->writeHTML($subjectTable, true, false, true, false, '');

        // ----- Top 5 Students -----
        if (!empty($topStudents)) {
            $topTable = '
            <h4>Top 5 Students (by Total Score)</h4>
            <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
                <thead>
                    <tr style="background-color: #f39c12; color: white;">
                        <th>Rank</th>
                        <th>Student Name</th>
                        <th>Total Score</th>
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>
            ';
            $rank = 1;
            foreach ($topStudents as $sid => $total) {
                $name = '';
                foreach ($students as $s) {
                    if ($s['id'] == $sid) {
                        $name = trim($s['fname'] . ' ' . $s['mname'] . ' ' . $s['lname']);
                        break;
                    }
                }
                $avg = $studentAverages[$sid] ?? 0;
                $topTable .= '
                    <tr>
                        <td align="center">' . $rank++ . '</td>
                        <td>' . $name . '</td>
                        <td align="center">' . $total . '</td>
                        <td align="center">' . $avg . '%</td>
                    </tr>
                ';
            }
            $topTable .= '</tbody></table>';
            $pdf->writeHTML($topTable, true, false, true, false, '');
        }

        // ----- School‑Wide Performance (for this term) -----
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'School Wide Performance', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        $schoolStats = '
        <h4>Overall Statistics</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:50%;">
            <tr><td><b>Total Results Entered</b></td><td>' . $schoolTotalResults . '</td></tr>
            <tr><td><b>Average Score (All Results)</b></td><td>' . $schoolAverage . '%</td></tr>
            <tr><td><b>Pass Rate (Score ≥50%)</b></td><td>' . $schoolPassPercent . '%</td></tr>
        </table>
        ';
        $pdf->writeHTML($schoolStats, true, false, true, false, '');

        $pdf->Ln(5);

        $subjectFailTable = '
        <h4>Subject Pass/Fail (All Classes in Term)</h4>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
            <thead>
                <tr style="background-color: #2c3e50; color: white;">
                    <th>Subject</th>
                    <th>Pass</th>
                    <th>Fail</th>
                    <th>Total</th>
                    <th>Pass %</th>
                </tr>
            </thead>
            <tbody>
        ';
        foreach ($subjectPassFail as $subj => $data) {
            $passPercent = $data['total'] > 0 ? round(($data['pass'] / $data['total']) * 100, 1) : 0;
            $subjectFailTable .= "
                <tr>
                    <td>$subj</td>
                    <td align='center'>{$data['pass']}</td>
                    <td align='center'>{$data['fail']}</td>
                    <td align='center'>{$data['total']}</td>
                    <td align='center'>$passPercent%</td>
                </tr>
            ";
        }
        $subjectFailTable .= '</tbody></table>';
        $pdf->writeHTML($subjectFailTable, true, false, true, false, '');

        // ----- Teacher Performance by Subject (for this term) -----
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Teacher Performance by Subject', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);

        if (empty($teacherPerformance)) {
            $pdf->writeHTML('<p>No teacher data available for this term.</p>', true, false, true, false, '');
        } else {
            foreach ($teacherPerformance as $teacher) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, $teacher['name'] . ' – Overall Pass Rate: ' . $teacher['overall_pass_percent'] . '%', 0, 1);
                $pdf->SetFont('helvetica', '', 9);

                $table = '
                <table border="1" cellpadding="4" style="border-collapse: collapse; width:100%;">
                    <thead>
                        <tr style="background-color: #16a085; color: white;">
                            <th>Subject</th>
                            <th>Pass</th>
                            <th>Fail</th>
                            <th>Total</th>
                            <th>Pass %</th>
                        </tr>
                    </thead>
                    <tbody>
                ';
                foreach ($teacher['subjects'] as $subj) {
                    $table .= "
                        <tr>
                            <td>{$subj['name']}</td>
                            <td align='center'>{$subj['pass']}</td>
                            <td align='center'>{$subj['fail']}</td>
                            <td align='center'>{$subj['total']}</td>
                            <td align='center'>{$subj['pass_percent']}%</td>
                        </tr>
                    ";
                }
                $table .= '
                    </tbody>
                </table>
                ';
                $pdf->writeHTML($table, true, false, true, false, '');
                $pdf->Ln(5);
            }
        }

        // Output PDF
        ob_end_clean();
        $pdf->Output($title . '.pdf', 'I');

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
} else {
    header("location:./");
}
?>