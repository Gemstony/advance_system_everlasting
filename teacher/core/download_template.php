<?php
$root = dirname(__DIR__, 2);
require_once($root . '/db/config.php');
require_once($root . '/const/check_session.php');

if ($res != "1" || $level != "2") {
    header("location:../../");
    exit;
}

$teacher_id = $account_id;

$type = $_GET['type'] ?? 'empty';
$class_id = isset($_GET['class']) ? (int)$_GET['class'] : 0;

if ($type === 'filled' && !$class_id) {
    die("Class ID required for filled template.");
}

try {
    $pdo = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset, DBUser, DBPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // For filled template, verify teacher has access to this class
    if ($type === 'filled') {
        $stmt = $pdo->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ?");
        $stmt->execute([$teacher_id]);
        $combs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $allowed_classes = [];
        foreach ($combs as $serialized) {
            $classes = unserialize($serialized);
            if (is_array($classes)) {
                $allowed_classes = array_merge($allowed_classes, $classes);
            }
        }
        $allowed_classes = array_unique($allowed_classes);
        if (!in_array($class_id, $allowed_classes)) {
            throw new Exception("You do not have permission to access this class.");
        }
    }

    // Set CSV headers
    $filename = $type === 'empty' ? 'empty_template.csv' : 'students_template.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Registration Number', 'Student Name', 'Score']);

    if ($type === 'filled') {
        // Fetch students of the selected class
        $stmt = $pdo->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY fname, lname");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as $student) {
            $fullname = trim($student['fname'] . ' ' . $student['mname'] . ' ' . $student['lname']);
            fputcsv($output, [$student['id'], $fullname, '']);
        }
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo "Error generating template: " . $e->getMessage();
    exit;
}