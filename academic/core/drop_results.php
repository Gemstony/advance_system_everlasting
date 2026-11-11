<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
$src = $_GET['src'];
$std = $_GET['std'];
$class = $_GET['class'];
$term = $_GET['term'];

try {
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE student = ? AND class = ? AND term = ?");
$stmt->execute([$std, $class, $term]);

$_SESSION['reply'] = array (array("success",'Examination result deleted'));
header("location:../$src");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
