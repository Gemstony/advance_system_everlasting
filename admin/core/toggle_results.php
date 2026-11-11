<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
if ($res != "1" || $level != "0") { http_response_code(403); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['state'])) {
    $state = (int)$_POST['state'];
    try {
        $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName, DBUser, DBPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE tbl_school SET allow_results = ? WHERE id = 1");
        $stmt->execute([$state]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>