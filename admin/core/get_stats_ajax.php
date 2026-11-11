<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if (!isset($res) || $res != "1" || !isset($level) || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE status = 1")->fetchColumn();
    $inactive = $total - $active;
    $male = $pdo->query("SELECT COUNT(*) FROM tbl_students WHERE gender = 'Male'")->fetchColumn();
    $female = $total - $male;
    $classes = $pdo->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
    $percent = $total > 0 ? round(($active / $total) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
        'male' => $male,
        'female' => $female,
        'classes' => $classes,
        'percent' => $percent
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}
?>