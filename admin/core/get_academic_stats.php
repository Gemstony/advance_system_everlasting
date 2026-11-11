<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $total = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 1 AND status = 1")->fetchColumn();
    $inactive = $total - $active;
    
    echo json_encode(['success' => true, 'total' => $total, 'active' => $active, 'inactive' => $inactive]);
} catch (PDOException $e) {
    echo json_encode(['success' => false]);
}
?>