<?php
/**
 * Student PDF Report Card (Save PDF)
 *
 * Mirrors the results page exactly:
 *
 *   1. School performance (total / average / grade / remark) uses ALL
 *      attended subjects — same as calculate_school_result().
 *
 *   2. Division and points use calculate_acsee_result() so that only
 *      the three principal subjects of the student's combination
 *      (detected from the class name or class override) drive the
 *      aggregate. Fallback promotion is silent, exactly like the
 *      results page.
 *
 *   3. Subjects with score 0 / null / '' are treated as NOT ATTENDED.
 *      They are hidden from the main results table and never counted.
 *
 *   4. Compact Division & Points block — small, professional, styled
 *      like the other summary tables (no oversized hero cards).
 */

chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');

// ---------- Access control ----------
if (!($res == "1" && $level == "3" && isset($_GET['term']))) {
    header("location:../");
    exit;
}

$term = (int)$_GET['term'];

/*
 * OPTIONAL CLASS -> COMBINATION OVERRIDES
 * Must match the override array used on the results page.
 */
$CLASS_COMBINATION_OVERRIDES = [];

// ---------- Database connection ----------
try {
    $conn = new PDO('mysql:host=' . DBHost . ';dbname=' . DBName . ';charset=' . DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ensure the images table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS `tbl_images` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_type` varchar(50) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `image_type` (`image_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // ---------- Grading & Division ----------
    $stmt = $conn->query("SELECT * FROM tbl_grade_system ORDER BY min DESC");
    $grading = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $GLOBALS['gradeSystem'] = $grading;

    $stmt = $conn->query("SELECT * FROM tbl_division_system ORDER BY min_point ASC");
    $divisionSystem = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $GLOBALS['divisionSystem'] = $divisionSystem;

    // ---------- Term details ----------
    $stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
    $stmt->execute([$term]);
    $termData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$termData) {
        header("location:./");
        exit;
    }
    $term_name = $termData['name'];
    $title = $term_name . ' Examination Report Card';

    // ---------- Student results for this term ----------
    $stmt = $conn->prepare("
        SELECT er.*, c.name AS class_name
        FROM tbl_exam_results er
        LEFT JOIN tbl_classes c ON er.class = c.id
        WHERE er.term = ? AND er.student = ?
    ");
    $stmt->execute([$term, $account_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($results)) {
        header("location:./");
        exit;
    }
    $class_id   = (int)$results[0]['class'];
    $class_name = $results[0]['class_name'];

    // ---------- Detect the student's combination ----------
    $combination = null;
    $combinationSource = 'not detected';

    if (isset($CLASS_COMBINATION_OVERRIDES[$class_id])) {
        $combination = normalize_combination_code($CLASS_COMBINATION_OVERRIDES[$class_id]);
        $combinationSource = 'class override';
    } else {
        $combination = detect_combination_from_class_name($class_name);
        if ($combination !== null) {
            $combinationSource = 'class name';
        }
    }
    $combinationDetails = get_combination_details($combination);

    // ---------- School contact ----------
    $contact = ['location' => '', 'phone1' => '', 'phone2' => '', 'email' => ''];
    try {
        $stmt = $conn->query("SELECT location, phone1, phone2, email FROM tbl_school_contacts LIMIT 1");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contact = array_merge($contact, $row);
        }
    } catch (PDOException $e) { /* ignore */ }

    // ---------- Signature & Seal ----------
    $signaturePath = null;
    $sealPath = null;
    $stmt = $conn->prepare("SELECT file_path FROM tbl_images WHERE image_type = 'signature' LIMIT 1");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $signaturePath = $row['file_path'];

    $stmt = $conn->prepare("SELECT file_path FROM tbl_images WHERE image_type = 'seal' LIMIT 1");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $sealPath = $row['file_path'];

    // ---------- Subject list for this class ----------
    $stmt = $conn->query("
        SELECT sc.id, sc.class, s.name AS subject_name
        FROM tbl_subject_combinations sc
        LEFT JOIN tbl_subjects s ON sc.subject = s.id
    ");
    $allCombinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subjectsByClass = [];
    foreach ($allCombinations as $comb) {
        $classList = @unserialize($comb['class']);
        if (!is_array($classList)) {
            if ($comb['class'] !== null && $comb['class'] !== '') {
                $classList = [(int)$comb['class']];
            } else {
                continue;
            }
        }
        foreach ($classList as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            $subjectsByClass[$cid][] = [
                'id'   => (int)$comb['id'],
                'name' => $comb['subject_name'] ?: 'Unknown Subject'
            ];
        }
    }

    $subjects = $subjectsByClass[$class_id] ?? [];
    if (empty($subjects)) {
        die("No subjects defined for this class.");
    }

    // ---------- Fetch scores for this term / class ----------
    $stmt = $conn->prepare("
        SELECT subject_combination, score
        FROM tbl_exam_results
        WHERE student = ? AND term = ?
    ");
    $stmt->execute([$account_id, $term]);
    $scoreMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $scoreMap[(int)$row['subject_combination']] = $row['score'];
    }

    // ---------- Build subjectObjects the same way the results page does ----------
    $subjectObjects   = [];
    $unattendedRows   = [];

    foreach ($subjects as $subj) {
        $sid = (int)$subj['id'];
        $raw = array_key_exists($sid, $scoreMap) ? $scoreMap[$sid] : null;

        if ($raw === null || $raw === '') {
            $unattendedRows[] = ['name' => $subj['name']];
            continue;
        }

        $subjectObjects[] = [
            'name'  => $subj['name'],
            'score' => (float)$raw,
            'id'    => $sid
        ];
    }

    // ---------- School result (all attended subjects) ----------
    $schoolResult = calculate_school_result($subjectObjects, $grading);

    // ---------- Division and points (combination-aware) ----------
    $acsee = null;
    if ($combination) {
        $acsee = calculate_acsee_result(
            $subjectObjects,
            $combination,
            $grading,
            $divisionSystem
        );
    }

    // ---------- Build attended rows for the table ----------
    $attendedRows = [];
    $n = 1;
    foreach ($schoolResult['attended'] as $row) {
        $attendedRows[] = [
            'n'      => $n++,
            'name'   => $row['name'],
            'score'  => $row['score'],
            'grade'  => $row['grade'],
            'remark' => $row['remark'],
        ];
    }

    $subjectCount    = count($attendedRows);
    $unattendedCount = count($unattendedRows);
    $totalSubjects   = $subjectCount + $unattendedCount;

    // ---------- Performance message ----------
    $average = $schoolResult['average'];
    if ($average >= 75) {
        $performanceMsg = 'Excellent performance! Keep it up.';
    } elseif ($average >= 60) {
        $performanceMsg = 'Good work. Can improve further.';
    } elseif ($average >= 40) {
        $performanceMsg = 'Fair performance. More effort needed.';
    } else {
        $performanceMsg = 'Needs improvement. Please work harder.';
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ---------- PDF Setup ----------
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(WBName);
$pdf->SetTitle($title);
$pdf->SetSubject($title);
$pdf->SetKeywords('SRMS, ' . WBName);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetAutoPageBreak(true, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('helvetica', '', 11);

if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    require_once(dirname(__FILE__) . '/lang/eng.php');
    $pdf->setLanguageArray($l);
}

$pdf->AddPage();

// ---------- Helper paths ----------
$rootPath = realpath(__DIR__ . '/../');

// ---------- Header ----------
$logoPath = 'images/logo/' . WBLogo;
if (!empty(WBLogo) && file_exists($rootPath . '/' . $logoPath)) {
    $logoImg = '<img src="' . $logoPath . '" width="80" height="80" style="border: 1px solid #aaa; padding: 2px; background: white;">';
} else {
    $logoImg = '<div style="width:80px;height:80px;background:#ddd;line-height:80px;text-align:center;">Logo</div>';
}

if ($img == "DEFAULT") {
    $studentPhoto = '<img width="80" height="80" src="images/students/' . $gender . '.png">';
} else {
    $studentPhoto = '<img width="80" height="80" src="images/students/' . $img . '">';
}

$currentDate = date('d/m/Y');

$contactStr = '';
if (!empty($contact['location']) || !empty($contact['phone1']) || !empty($contact['phone2']) || !empty($contact['email'])) {
    $parts = [];
    if (!empty($contact['location'])) $parts[] = $contact['location'];
    if (!empty($contact['phone1']))   $parts[] = 'Tel: ' . $contact['phone1'];
    if (!empty($contact['phone2']))   $parts[] = $contact['phone2'];
    if (!empty($contact['email']))    $parts[] = 'Email: ' . $contact['email'];
    $contactStr = '<span style="font-size:9pt;">' . implode('  |  ', $parts) . '</span><br>';
}

$headerHtml = '
<table cellpadding="5" style="border-bottom: 1px solid #333; margin-bottom: 10px;">
    <tr>
        <td width="15%" align="center">' . $logoImg . '</td>
        <td width="70%" align="center">
            <h2 style="margin:0; color:#2c3e50;">' . WBName . '</h2>
            <h4 style="margin:0; color:#34495e;">Student Examination Report Card</h4>
            ' . $contactStr . '
            <b style="color:#2980b9;">' . $term_name . '</b>  |  <b style="color:#2980b9;">' . $class_name . '</b>
        </td>
        <td width="15%" align="center">
            ' . $studentPhoto . '<br>
            <span style="font-size:8pt;">' . $currentDate . '</span>
        </td>
    </tr>
</table>
';
$pdf->writeHTML($headerHtml, true, false, true, false, '');

// ---------- Student Profile ----------
$profileHtml = '
<table cellpadding="5" style="border: 1px solid #bdc3c7; background-color: #ecf0f1; margin-bottom: 15px; border-radius: 5px;">
    <tr><td colspan="2" style="background-color: #3498db; color: white; font-weight: bold; padding: 6px;">STUDENT PROFILE</td></tr>
    <tr><td width="30%"><b>Registration No.</b></td><td>' . $account_id . '</td></tr>
    <tr><td><b>Student Name</b></td><td>' . $fname . ' ' . $mname . ' ' . $lname . '</td></tr>
    <tr><td><b>Gender</b></td><td>' . $gender . '</td></tr>
    <tr><td><b>Examination Term</b></td><td>' . $term_name . '</td></tr>
    <tr><td><b>Class</b></td><td>' . $class_name . '</td></tr>
    <tr><td><b>Total Subjects Assigned</b></td><td>' . $totalSubjects . ' (Attended: ' . $subjectCount . ', Unattended: ' . $unattendedCount . ')</td></tr>
</table>
';
$pdf->writeHTML($profileHtml, true, false, true, false, '');

// ---------- Attended Subjects Table ----------
if (!empty($attendedRows)) {
    $attendedTable = '
    <h4 style="color:#2c3e50;">Attended Subjects</h4>
    <table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; margin-bottom: 10px;">
        <thead>
            <tr style="background-color: #27ae60; color: white;">
                <th width="5%">#</th>
                <th width="35%">SUBJECT</th>
                <th width="20%">SCORE (%)</th>
                <th width="20%">GRADE</th>
                <th width="20%">REMARK</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($attendedRows as $row) {
        $attendedTable .= '
            <tr>
                <td align="center">' . $row['n'] . '</td>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td align="center">' . rtrim(rtrim(number_format($row['score'], 2, '.', ''), '0'), '.') . '%</td>
                <td align="center">' . htmlspecialchars($row['grade']) . '</td>
                <td align="center">' . htmlspecialchars($row['remark']) . '</td>
            </tr>';
    }
    $attendedTable .= '
        </tbody>
    </table>';
    $pdf->writeHTML($attendedTable, true, false, true, false, '');
} else {
    $pdf->writeHTML('<p style="font-size:10pt; color:#e74c3c;"><b>No attended subjects for this term.</b></p>', true, false, true, false, '');
}

// ---------- School Performance Summary (all attended subjects) ----------
$summaryHtml = '
<h4 style="color:#2c3e50;">School Performance (All Attended Subjects)</h4>
<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; margin-bottom: 15px;">
    <tr style="background-color: #f39c12; color: white;">
        <th>TOTAL SCORE</th><th>AVERAGE</th><th>GRADE</th><th>REMARK</th>
    </tr>
    <tr align="center">
        <td>' . $schoolResult['total'] . '</td>
        <td>' . $schoolResult['average'] . '%</td>
        <td>' . htmlspecialchars($schoolResult['average_grade']) . '</td>
        <td>' . htmlspecialchars($schoolResult['average_remark']) . '</td>
    </tr>
</table>
<p style="font-size:10pt; font-style:italic; text-align:center;">' . $performanceMsg . '</p>
';
$pdf->writeHTML($summaryHtml, true, false, true, false, '');

// =========================================================
//  COMPACT DIVISION & POINTS BLOCK
//  Same visual weight as the School Performance table.
//  No oversized hero cards. No excluded-subjects block.
//  No announcement banners.
// =========================================================
if ($combination && $acsee && $acsee['complete']) {

    // Section heading (matches the other section headings)
    $divisionHtml  = '<h4 style="color:#2c3e50;">Division and Points</h4>';

    // Compact POINTS / DIVISION summary row
    $divisionHtml .= '
    <table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; margin-bottom: 10px;">
        <tr style="background-color: #0f172a; color: white;">
            <th width="50%" align="center">POINTS</th>
            <th width="50%" align="center">DIVISION</th>
        </tr>
        <tr align="center">
            <td><b>' . (int)$acsee['points'] . '</b></td>
            <td><b>' . htmlspecialchars($acsee['division']) . '</b></td>
        </tr>
    </table>';

    // Compact subjects used table
    $divisionHtml .= '
    <table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; margin-bottom: 6px;">
        <thead>
            <tr style="background-color: #2980b9; color: white;">
                <th width="45%" align="left">SUBJECT USED</th>
                <th width="20%" align="center">SCORE</th>
                <th width="20%" align="center">GRADE</th>
                <th width="15%" align="center">POINTS</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($acsee['used_subjects'] as $used) {
        $uGrade = get_grade($used['score'], $grading);
        $uPts   = get_division_point_from_grade($uGrade);
        $divisionHtml .= '
            <tr>
                <td>' . htmlspecialchars($used['name']) . '</td>
                <td align="center">' . rtrim(rtrim(number_format($used['score'], 2, '.', ''), '0'), '.') . '%</td>
                <td align="center">' . htmlspecialchars($uGrade) . '</td>
                <td align="center"><b>' . $uPts . '</b></td>
            </tr>';
    }

    $divisionHtml .= '
        </tbody>
    </table>
    <p style="font-size: 8.5pt; color:#64748b; margin: 0 0 15px;">
        Points and division were computed from the three subjects shown above.
    </p>';

    $pdf->writeHTML($divisionHtml, true, false, true, false, '');
}
// No else-branch: when combination is missing or incomplete, silently omit.

// ---------- Unattended Subjects (kept, clean, no banner colours) ----------
if (!empty($unattendedRows)) {
    $unattendedTable = '
    <div style="margin-top: 15px; border: 1px solid #e5e7eb; background-color: #ffffff; padding: 10px; border-radius: 6px;">
        <h4 style="color:#2c3e50; margin: 0 0 6px;">Unattended Subjects</h4>
        <p style="font-size:9pt; color:#7f8c8d; margin: 0 0 8px;">
            These subjects were assigned to your class, but no score was recorded for you in this term.
        </p>
        <table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; width: 100%;">
            <thead>
                <tr style="background-color: #f8f9fa; color: #333;">
                    <th width="10%">#</th>
                    <th width="70%">SUBJECT</th>
                    <th width="20%">STATUS</th>
                </tr>
            </thead>
            <tbody>';
    $i = 1;
    foreach ($unattendedRows as $row) {
        $unattendedTable .= '
            <tr>
                <td align="center">' . $i++ . '</td>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td align="center"><span style="color:#7f8c8d; font-weight:bold;">Unattended</span></td>
            </tr>';
    }
    $unattendedTable .= '
            </tbody>
        </table>
    </div>';
    $pdf->writeHTML($unattendedTable, true, false, true, false, '');
}

// ---------- Grading System ----------
$gradeHeader = '<tr>';
$gradeRange  = '<tr>';
$gradeRemark = '<tr>';
foreach ($grading as $g) {
    $gradeHeader .= '<th style="background-color:#bdc3c7;">' . htmlspecialchars($g['name']) . '</th>';
    $gradeRange  .= '<td align="center">' . $g['min'] . '% – ' . $g['max'] . '%</td>';
    $gradeRemark .= '<td align="center">' . htmlspecialchars($g['remark']) . '</td>';
}
$gradeHeader .= '</tr>';
$gradeRange  .= '</tr>';
$gradeRemark .= '</tr>';

$gradingTable = '
<h4 style="color:#2c3e50;">Grading System</h4>
<table border="1" cellpadding="5" style="border-collapse: collapse; font-size: 10pt; margin-bottom: 15px;">
    ' . $gradeHeader . '
    ' . $gradeRange . '
    ' . $gradeRemark . '
</table>
';
$pdf->writeHTML($gradingTable, true, false, true, false, '');

// ---------- Signature & Seal ----------
$showSignature = !is_null($signaturePath);
$showSeal      = !is_null($sealPath);

if ($showSignature || $showSeal) {
    $pdf->Ln(5);
    $pdf->writeHTML('<h4 style="color:#2c3e50;">School Signature & Seal</h4>', true, false, true, false, '');

    $sigHtml = '<table cellpadding="5"><tr>';

    $sigHtml .= '<td align="center">';
    if ($showSignature && file_exists($rootPath . '/' . $signaturePath)) {
        $sigHtml .= '<img src="' . $signaturePath . '" width="120"><br><b>Principal\'s Endorsement</b>';
    } else {
        $sigHtml .= '';
    }
    $sigHtml .= '</td>';

    $sigHtml .= '<td align="center">';
    if ($showSeal && file_exists($rootPath . '/' . $sealPath)) {
        $sigHtml .= '<img src="' . $sealPath . '" width="120"><br><b>School Seal</b>';
    } else {
        $sigHtml .= '';
    }
    $sigHtml .= '</td>';

    $sigHtml .= '</tr></table>';
    $pdf->writeHTML($sigHtml, true, false, true, false, '');
}

// ---------- Footer note ----------
$footerNote = '
<p style="font-size:8pt; color:#7f8c8d; text-align:center; margin-top:20px;">
    This is a computer-generated report. Please verify the details with the academic office.
</p>
';
$pdf->writeHTML($footerNote, true, false, true, false, '');

ob_end_clean();
$pdf->Output($title . '.pdf', 'I');
?>