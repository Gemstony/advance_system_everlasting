<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacher_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$new_status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("UPDATE tbl_staff SET status = ? WHERE id = ? AND level = 2");
    $stmt->execute([$new_status, $teacher_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found or update failed']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>