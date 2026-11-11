<?php
// This file is located at: academic/delete_student.php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

// Academic only
if ($res != "1" || $level != "1") {
    header("location:../");
    exit;
}

// Get student ID from GET
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
if (empty($student_id)) {
    $_SESSION['error'] = 'Student ID is required.';
    header("location:repeat_std");
    exit;
}

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // Verify student exists and is active
    $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE id = ? AND status = 1");
    $stmt->execute([$student_id]);
    if ($stmt->rowCount() == 0) {
        throw new Exception('Student not found or already inactive.');
    }

    // Soft delete: set status = 0
    $stmt = $conn->prepare("UPDATE tbl_students SET status = 0 WHERE id = ?");
    $stmt->execute([$student_id]);

    $conn->commit();

    $_SESSION['success'] = "Student $student_id has been deleted successfully.";
    header("location:repeat_std");
    exit;

} catch (PDOException $e) {
    if (isset($conn)) $conn->rollBack();
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header("location:repeat_std");
    exit;
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header("location:repeat_std");
    exit;
}