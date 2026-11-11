<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

if (isset($_SESSION['bulk_result_2'])) {
$class = $_SESSION['bulk_result_2']['student'];
$term = $_SESSION['bulk_result_2']['term'];

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
$stmt->execute();
$grading = $stmt->fetchAll();
$gradingAssoc = array_map(function ($grade) {
return ['name' => $grade[1], 'min' => $grade[2], 'max' => $grade[3], 'remark' => $grade[4]];
}, $grading);
$divisionLabels = ['I', 'II', 'III', 'IV', '0'];

foreach ($divisionLabels as $value) {

$_MATOKEO[$value]['BOYS'] = 0;
$_MATOKEO[$value]['GIRLS'] = 0;
}

try {
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$std_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
$stmt->execute([$term]);
$term_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
$stmt->execute([$std_data[0][6]]);
$class_data = $stmt->fetchAll();

$title = ''.$class_data[0][1].' ('.$term_data[0][1].' Perfomance Report)';
}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}



try {
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id");
$stmt->execute();
$result = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$result2 = $stmt->fetchAll();
$studentIds = array_column($result2, 0);
$historicalCombinationIds = [];
if (!empty($studentIds)) {
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $conn->prepare("SELECT DISTINCT subject_combination FROM tbl_exam_results WHERE student IN ($studentPlaceholders) AND term = ?");
    $stmt->execute(array_merge($studentIds, [$term]));
    $historicalCombinationIds = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

foreach($result2 as $row2)
{
$tscore = 0;
$t_subjects = 0;
$attended_subjects = 0;
$subssss = array();
$subjectObjects = array();


foreach ($result as $key => $row) {
$class_list = unserialize($row[1]);

if (in_array($class, $class_list) || isset($historicalCombinationIds[$row[0]]))
{
$t_subjects++;
$score = null;
$gnd = $row2[4];
$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE student = ? AND subject_combination = ? AND term = ?");
$stmt->execute([$row2[0], $row[0], $term]);
$ex_result = $stmt->fetchAll();

if (!empty($ex_result) && $ex_result[0][5] !== null && $ex_result[0][5] !== '') {
$score = $ex_result[0][5];
$tscore = $tscore + $score;
$attended_subjects++;
}
array_push($subssss, $score);
if ($score !== null && $score !== '') {
$subjectObjects[] = array('name' => $row[6], 'score' => $score);
}

}


}

if ($attended_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$attended_subjects, 1);
}

foreach($grading as $grade)
{

if ($av >= $grade[2] && $av <= $grade[3]) {

$grd = $grade[1];
$rm = $grade[4];

}

}

$divResult = calculate_acsee_result(
    $subjectObjects,
    detect_combination_from_class_name($class_data[0][1]) ?: '',
    $gradingAssoc,
    $conn->query("SELECT * FROM tbl_division_system")->fetchAll(PDO::FETCH_ASSOC)
);
$div = $divResult['division'] ?? '0';

if ($gnd == "Male") {

$_MATOKEO[$div]['BOYS'] = $_MATOKEO[$div]['BOYS'] +1;


}else{
$_MATOKEO[$div]['GIRLS'] = $_MATOKEO[$div]['GIRLS'] +1;

}
}


$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(WBName);
$pdf->SetTitle($title);
$pdf->SetSubject($title);
$pdf->SetKeywords('SRMS',WBName);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);


$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
require_once(dirname(__FILE__).'/lang/eng.php');
$pdf->setLanguageArray($l);
}

$pdf->setFontSubsetting(true);
$pdf->SetFont('helvetica', '', 14, '', true);

$pdf->AddPage();
$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));


$html = '<table width="100%">
<tr>
<td width="15%"><img src="images/logo/'.WBLogo.'"></td>
<td width="85%">
<h5><b style="font-size:18px;">'.WBName.'</b>
<br>Student Perfomance Report<br>
'.$class_data[0][1].'<br>
'.$term_data[0][1].'</h5>
</td>

</tr>
</table>';

$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

$pdf->SetFont('helvetica', '', 10, '', true);

$pdf->cell(0, 0, '', 0, 1, 'C');

$htmls = '<table border="1" cellpadding="5">
<tr>
<td>DIVISION</td>
<td>BOYS</td>
<td>GIRLS</td>
<td>Total</td>
</tr>
';

foreach ($divisionLabels as $value) {

$htmls = $htmls.'
<tr>
<td>'.$value.'</td>
<td>'.$_MATOKEO[$value]['BOYS'].'</td>
<td>'.$_MATOKEO[$value]['GIRLS'].'</td>
<td>'.($_MATOKEO[$value]['BOYS']+$_MATOKEO[$value]['GIRLS']).'</td>
</tr>
';


}

$htmls = $htmls.'</table>';

$pdf->writeHTMLCell(0, 0, '', '', $htmls, 0, 1, 0, true, '', true);

$html2 = '<br><br><b>Date : '.date('F d, Y G:i:s A').'</b>';
$pdf->writeHTMLCell(0, 0, '', '', $html2, 0, 1, 0, true, '', true);

ob_end_clean();
$pdf->Output(''.$title.'.pdf', 'I');

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
}else{
header("location:./");
}

?>
