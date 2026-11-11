<?php
session_start();
require_once '../config/db.php';
if (!isAcademicLoggedIn()) die("Unauthorized");
$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM tbl_events WHERE id = ?");
$stmt->execute([$id]);
header("Location: dashboard.php?msg=deleted");
exit;