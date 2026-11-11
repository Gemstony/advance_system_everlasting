<?php
$root_path = dirname(__DIR__, 2);
chdir($root_path);

session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

if ($res != "1" || $level != "1") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$class_id = (int)$_POST['id'];

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT name, registration_date FROM tbl_classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY fname, lname");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT sc.class, s.name 
                            FROM tbl_subject_combinations sc
                            JOIN tbl_subjects s ON sc.subject = s.id
                            WHERE sc.status = 1");
    $stmt->execute();
    $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subjects = [];
    foreach ($combinations as $comb) {
        $class_array = @unserialize($comb['class']);
        if (is_array($class_array) && in_array($class_id, $class_array)) {
            $subjects[] = $comb['name'];
        }
    }
    $subjects = array_unique($subjects);
    sort($subjects);
    
    echo json_encode([
        'status' => 'success',
        'class_name' => $class['name'],
        'reg_date' => date('d M Y, h:i A', strtotime($class['registration_date'])),
        'student_count' => count($students),
        'students' => $students,
        'subjects' => $subjects
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>