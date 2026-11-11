<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if (!isset($res) || $res != "1" || !isset($level) || $level != "0") {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($search)) {
        echo json_encode(['success' => true, 'students' => []]);
        exit;
    }

    $sql = "SELECT s.id, s.fname, s.mname, s.lname, s.gender, s.email, s.status, s.class, c.name AS class_name
            FROM tbl_students s
            LEFT JOIN tbl_classes c ON s.class = c.id
            WHERE s.id LIKE :search 
               OR s.fname LIKE :search 
               OR s.mname LIKE :search 
               OR s.lname LIKE :search
            ORDER BY s.fname
            LIMIT " . intval($limit);
    
    $stmt = $pdo->prepare($sql);
    $searchParam = '%' . $search . '%';
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as &$s) {
        $s['class_name'] = $s['class_name'] ?? '—';
        $s['class'] = (int)$s['class'];
        $s['status'] = (int)$s['status'];
        $s['email'] = $s['email'] ?? '';
    }

    echo json_encode(['success' => true, 'students' => $students]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>