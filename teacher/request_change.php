<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "2") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_POST['action'] !== 'submit_change_request') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$student_id = $_POST['student_id'] ?? '';
$term_id = (int)($_POST['term_id'] ?? 0);
$new_score = (float)($_POST['new_score'] ?? -1);
$reason = trim($_POST['reason'] ?? '');

if (!$student_id || !$term_id || $new_score < 0 || $new_score > 100 || empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid data']);
    exit;
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get student's class and current score for this subject combination (from session)
    $current_subject = (int) $_SESSION['result__data']['subject'];
    $term = (int) $_SESSION['result__data']['term'];
    $class = (int) $_SESSION['result__data']['class'];

    // Verify that this teacher is assigned to this subject combination (extra safety)
    $stmt = $pdo->prepare("SELECT teacher FROM tbl_subject_combinations WHERE id = ?");
    $stmt->execute([$current_subject]);
    $teacher = $stmt->fetchColumn();
    if ((int)$teacher !== (int)$account_id) {
        echo json_encode(['success' => false, 'error' => 'You are not allowed to request changes for this subject.']);
        exit;
    }

    // Get current score
    $stmt = $pdo->prepare("SELECT score FROM tbl_exam_results WHERE student = ? AND term = ? AND class = ? AND subject_combination = ?");
    $stmt->execute([$student_id, $term, $class, $current_subject]);
    $current_score = (float) $stmt->fetchColumn();

    if ($current_score == $new_score) {
        echo json_encode(['success' => false, 'error' => 'New score is the same as current. No change needed.']);
        exit;
    }

    // Insert request
    $stmt = $pdo->prepare("INSERT INTO tbl_result_change_requests 
        (student_id, class_id, term_id, subject_combination_id, current_score, requested_score, reason, teacher_id, requested_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$student_id, $class, $term, $current_subject, $current_score, $new_score, $reason, $account_id]);

    echo json_encode(['success' => true, 'message' => 'Request submitted']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}