<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

// Only admin
if (!($res == "1" && $level == "0")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['categories'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$categories = $_POST['categories']; // array
if (!is_array($categories)) {
    $categories = [$categories];
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $deleted = [];

    if (in_array('academics', $categories)) {
        // Delete academic staff (level 1) except current admin? Keep all admins untouched, but we delete level 1 only.
        $stmt = $pdo->prepare("DELETE FROM tbl_staff WHERE level = 1");
        $stmt->execute();
        $deleted['academics'] = $stmt->rowCount();
    }

    if (in_array('teachers', $categories)) {
        $stmt = $pdo->prepare("DELETE FROM tbl_staff WHERE level = 2");
        $stmt->execute();
        $deleted['teachers'] = $stmt->rowCount();
    }

    if (in_array('students', $categories)) {
        // Delete students and related exam results (cascades)
        $stmt = $pdo->query("DELETE FROM tbl_students");
        $deleted['students'] = $stmt->rowCount();
        // Also clear any orphaned results (should cascade, but just in case)
        $pdo->exec("DELETE FROM tbl_exam_results WHERE student NOT IN (SELECT id FROM tbl_students)");
    }

    if (in_array('all_accounts', $categories)) {
        // Delete all non-admin staff (level 1 and 2) and all students
        $pdo->exec("DELETE FROM tbl_staff WHERE level IN (1,2)");
        $pdo->exec("DELETE FROM tbl_students");
        $pdo->exec("DELETE FROM tbl_exam_results"); // all results gone
        $deleted['all'] = 'All non-admin accounts cleared';
    }

    echo json_encode(['success' => true, 'deleted' => $deleted, 'message' => 'Selected data has been reset.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}