<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "1") {
    header("location:../");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$class = filter_input(INPUT_POST, 'class', FILTER_VALIDATE_INT);
$class2 = filter_input(INPUT_POST, 'class2', FILTER_VALIDATE_INT);
$staffId = (int)($_SESSION['staff_id'] ?? ($account_id ?? 0));

if (!$class || !$class2 || $class === $class2 || $staffId < 1) {
    $_SESSION['reply'] = array (array("error",'Invalid promotion request'));
    header("location:../promote_students");
    exit;
}

try {
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->beginTransaction();

$classStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_classes WHERE id = ?");
$classStmt->execute([$class]);
$sourceExists = (int)$classStmt->fetchColumn() === 1;
$classStmt->execute([$class2]);
$targetExists = (int)$classStmt->fetchColumn() === 1;
if (!$sourceExists || !$targetExists) {
    throw new RuntimeException('The selected class does not exist.');
}

$studentStmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? AND status = 1");
$studentStmt->execute([$class]);
$students = $studentStmt->fetchAll(PDO::FETCH_COLUMN);

$updateStmt = $conn->prepare("UPDATE tbl_students SET class = ? WHERE id = ? AND class = ?");
$logStmt = $conn->prepare("
    INSERT INTO tbl_promotion_log
        (student_id, from_class, to_class, promoted_by, promotion_date, academic_year)
    VALUES (?, ?, ?, ?, NOW(), ?)
");
$updated = 0;
foreach ($students as $studentId) {
    $updateStmt->execute([$class2, $studentId, $class]);
    if ($updateStmt->rowCount() > 0) {
        $logStmt->execute([$studentId, $class, $class2, $staffId, date('Y')]);
        $updated++;
    }
}

$conn->commit();
$_SESSION['reply'] = array (array("success","$updated student(s) promoted; previous results were preserved"));
header("location:../promote_students");

}catch(Throwable $e)
{
if (isset($conn) && $conn->inTransaction()) {
    $conn->rollBack();
}
$_SESSION['reply'] = array (array("error",'Promotion failed: '.$e->getMessage()));
header("location:../promote_students");
}


}else{
header("location:../");
}
?>
