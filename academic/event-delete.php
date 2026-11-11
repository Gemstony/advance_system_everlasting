<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "1") die("Unauthorized");

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM tbl_events WHERE id = ?");
$stmt->execute([$id]);
header("Location: academic/event-dashboard.php?msg=deleted");
exit;