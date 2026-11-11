<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

$id = $_GET['id'];
$img = $_GET['img'];

if ($img == "DEFAULT") {

}else{
unlink('images/students/'.$img.'');
}

try {
$conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("DELETE FROM tbl_students WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['reply'] = array (array("success",'Student deleted successfully'));
header("location:../students");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
